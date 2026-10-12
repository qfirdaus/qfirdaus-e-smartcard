<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
declare(strict_types=1);

final class AiChatbotPdfTextExtractor
{
    private const MIN_USEFUL_CHARS = 80;
    private const MAX_TEXT_BYTES = 3000000;

    /**
     * @return array{text:string,char_count:int,method:string,text_path:string}
     */
    public function extractToSidecar(string $pdfPath): array
    {
        $pdfPath = realpath($pdfPath) ?: $pdfPath;
        if (!is_file($pdfPath) || !is_readable($pdfPath)) {
            throw new RuntimeException('PDF file is not readable.');
        }

        $textPath = preg_replace('/\.pdf$/i', '.txt', $pdfPath);
        if (!is_string($textPath) || $textPath === $pdfPath) {
            $textPath = $pdfPath . '.txt';
        }

        $method = 'pdftotext';
        $text = $this->extractWithPdftotext($pdfPath, $textPath);
        if ($text === '') {
            $method = 'php_best_effort';
            $text = $this->extractBestEffort($pdfPath);
            if ($text !== '') {
                file_put_contents($textPath, $text, LOCK_EX);
            }
        }

        $text = $this->cleanText($text);
        if (mb_strlen($text, 'UTF-8') < self::MIN_USEFUL_CHARS) {
            @unlink($textPath);
            throw new RuntimeException('Unable to extract useful text from PDF. The file may be scanned/image-based or encrypted.');
        }

        file_put_contents($textPath, $text, LOCK_EX);

        return [
            'text' => $text,
            'char_count' => mb_strlen($text, 'UTF-8'),
            'method' => $method,
            'text_path' => $textPath,
        ];
    }

    private function extractWithPdftotext(string $pdfPath, string $textPath): string
    {
        $binary = $this->findPdftotextBinary();
        if ($binary === null || !function_exists('proc_open')) {
            return '';
        }

        @unlink($textPath);
        $command = [$binary, '-layout', '-enc', 'UTF-8', $pdfPath, $textPath];
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($command, $descriptor, $pipes);
        if (!is_resource($process)) {
            return '';
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $exitCode = proc_close($process);
        if ($exitCode !== 0 || !is_file($textPath)) {
            return '';
        }

        $text = file_get_contents($textPath, false, null, 0, self::MAX_TEXT_BYTES);
        return is_string($text) ? $text : '';
    }

    private function findPdftotextBinary(): ?string
    {
        $candidates = [
            'pdftotext',
            'C:/Program Files/poppler/Library/bin/pdftotext.exe',
            'C:/Program Files/poppler/bin/pdftotext.exe',
            'C:/poppler/Library/bin/pdftotext.exe',
            'C:/poppler/bin/pdftotext.exe',
        ];

        foreach ($candidates as $candidate) {
            if (str_contains($candidate, DIRECTORY_SEPARATOR) || str_contains($candidate, ':')) {
                if (is_file($candidate) && is_executable($candidate)) {
                    return $candidate;
                }
                continue;
            }

            if (!function_exists('proc_open')) {
                continue;
            }

            $check = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
                ? ['where', $candidate]
                : ['which', $candidate];
            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = @proc_open($check, $descriptor, $pipes);
            if (!is_resource($process)) {
                continue;
            }
            $output = stream_get_contents($pipes[1]);
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (proc_close($process) === 0) {
                $path = trim((string)preg_split('/\R/', (string)$output)[0]);
                return $path !== '' ? $path : $candidate;
            }
        }

        return null;
    }

    private function extractBestEffort(string $pdfPath): string
    {
        $raw = file_get_contents($pdfPath, false, null, 0, self::MAX_TEXT_BYTES);
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        $streams = [];
        if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $raw, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress(ltrim((string)$stream));
                $streams[] = is_string($decoded) ? $decoded : (string)$stream;
            }
        }
        $search = $streams !== [] ? implode("\n", $streams) : $raw;

        $parts = [];
        if (preg_match_all('/\((?://.|[^//)])*\)\s*Tj/s', $search, $matches)) {
            foreach ($matches[0] as $token) {
                if (preg_match('/\((.*)\)\s*Tj/s', $token, $textMatch)) {
                    $parts[] = $this->decodePdfString($textMatch[1]);
                }
            }
        }
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $search, $matches)) {
            foreach ($matches[1] as $arrayToken) {
                if (preg_match_all('/\((?://.|[^//)])*\)/s', (string)$arrayToken, $stringMatches)) {
                    $line = '';
                    foreach ($stringMatches[0] as $stringToken) {
                        $line .= $this->decodePdfString(substr($stringToken, 1, -1));
                    }
                    $parts[] = $line;
                }
            }
        }

        return implode("\n", array_filter(array_map('trim', $parts)));
    }

    private function decodePdfString(string $value): string
    {
        $value = preg_replace_callback('///([nrtbf()//])/', static function (array $match): string {
            return match ($match[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\b",
                'f' => "\f",
                default => $match[1],
            };
        }, $value);
        $value = preg_replace_callback('///([0-7]{1,3})/', static fn(array $match): string => chr(octdec($match[1])), (string)$value);

        return (string)$value;
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', (string)$text);
        $text = preg_replace("/\n{3,}/u", "\n\n", (string)$text);

        return trim((string)$text);
    }
}
