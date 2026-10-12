<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */
if (!function_exists('alamat_clean_part')) {
    function alamat_clean_part($value): string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/[\r\n\t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s*,\s*/u', ', ', $text) ?? $text;
        $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;

        return trim($text, " \t\n\r\0\x0B,;");
    }
}

if (!function_exists('alamat_lower')) {
    function alamat_lower($value): string
    {
        $text = alamat_clean_part($value);
        if ($text === '') {
            return '';
        }

        return function_exists('mb_strtolower')
            ? mb_strtolower($text, 'UTF-8')
            : strtolower($text);
    }
}

if (!function_exists('alamat_title_case')) {
    function alamat_title_case($value): string
    {
        $text = alamat_lower($value);
        if ($text === '') {
            return '';
        }

        $parts = preg_split('/(\s+|,|-|\/|\(|\))/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return $text;
        }

        foreach ($parts as &$part) {
            if ($part === '' || preg_match('/^(\s+|,|-|\/|\(|\))$/u', $part)) {
                continue;
            }

            if (preg_match('/^\d+[a-z]?$/u', $part) === 1) {
                $part = strtoupper($part);
                continue;
            }

            $first = function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
            $rest = function_exists('mb_substr') ? mb_substr($part, 1, null, 'UTF-8') : substr($part, 1);
            $part = (function_exists('mb_strtoupper') ? mb_strtoupper($first, 'UTF-8') : strtoupper($first)) . $rest;
        }
        unset($part);

        return implode('', $parts);
    }
}

if (!function_exists('alamat_is_dummy_value')) {
    function alamat_is_dummy_value($value): bool
    {
        $text = alamat_lower($value);
        if ($text === '') {
            return true;
        }

        $normalized = preg_replace('/[^a-z0-9]+/u', '', $text) ?? $text;
        if ($normalized === '') {
            return true;
        }

        return preg_match('/^(a+|x+|z+|na|n\/a|null|none|dummy|test)$/u', $normalized) === 1;
    }
}

if (!function_exists('alamat_fix_house_number')) {
    function alamat_fix_house_number($value): string
    {
        $text = alamat_clean_part($value);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\bNo\.?\s*(\d+)/iu', 'No $1', $text) ?? $text;
        $text = preg_replace('/\bNO\.?\s*(\d+)/u', 'No $1', $text) ?? $text;
        $text = preg_replace('/\bno\.?\s*(\d+)/u', 'No $1', $text) ?? $text;
        $text = preg_replace('/\bNo\s{2,}(\d+)/u', 'No $1', $text) ?? $text;

        return alamat_clean_part($text);
    }
}

if (!function_exists('alamat_fix_unit_format')) {
    function alamat_fix_unit_format($value): string
    {
        $text = alamat_clean_part($value);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\b([A-Za-z])\s*-\s*(\d+)\s*-\s*(\d+)\b/u', '$1-$2-$3', $text) ?? $text;
        $text = preg_replace('/\b([A-Za-z])\s+(\d+)\s+(\d+)\b/u', '$1-$2-$3', $text) ?? $text;
        $text = preg_replace('/\b([A-Za-z])\s*-\s*(\d+)\s*-\s*(\d+)\s*-\s*(\d+)\b/u', '$1-$2-$3-$4', $text) ?? $text;
        $text = preg_replace('/\b([A-Za-z])\s+(\d+)\s+(\d+)\s+(\d+)\b/u', '$1-$2-$3-$4', $text) ?? $text;

        return alamat_clean_part($text);
    }
}

if (!function_exists('alamat_fix_merged_tokens')) {
    function alamat_fix_merged_tokens($value): string
    {
        $text = alamat_clean_part($value);
        if ($text === '') {
            return '';
        }

        $patterns = [
            '/\bJln(?=[A-Z0-9])/u' => 'Jalan ',
            '/\bJnn(?=[A-Z0-9])/u' => 'Jalan ',
            '/\bTmn(?=[A-Z0-9])/u' => 'Taman ',
            '/\bKg(?=[A-Z0-9])/u' => 'Kampung ',
            '/\bSg(?=[A-Z])/u' => 'Sungai ',
            '/\bLrg(?=[A-Z0-9])/u' => 'Lorong ',
            '/\bBt(?=[A-Z0-9])/u' => 'Batu ',
            '/\bBdr(?=[A-Z])/u' => 'Bandar ',
            '/\bBlok(?=[A-Z0-9])/u' => 'Blok ',
            '/\bNo(?=\d)/u' => 'No ',
            '/\bNo\.(?=\d)/u' => 'No ',
            '/\bSungai(?=[A-Z])/u' => 'Sungai ',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;

        return alamat_clean_part($text);
    }
}

if (!function_exists('alamat_expand_abbreviations')) {
    function alamat_expand_abbreviations($value): string
    {
        $text = alamat_fix_house_number($value);
        $text = alamat_fix_unit_format($text);
        $text = alamat_fix_merged_tokens($text);
        if ($text === '') {
            return '';
        }

        $map = [
            '/\bjln\b\.?/iu' => 'Jalan',
            '/\bjnn\b\.?/iu' => 'Jalan',
            '/\btmn\b\.?/iu' => 'Taman',
            '/\bkg\b\.?/iu' => 'Kampung',
            '/\bsg\b\.?/iu' => 'Sungai',
            '/\blrg\b\.?/iu' => 'Lorong',
            '/\bkl\b\.?/iu' => 'Kuala Lumpur',
            '/\bk\.l\b\.?/iu' => 'Kuala Lumpur',
            '/\bbt\b\.?/iu' => 'Batu',
            '/\btkt\b\.?/iu' => 'Tingkat',
            '/\bbdr\b\.?/iu' => 'Bandar',
            '/\bwp\b\.?/iu' => 'Wilayah Persekutuan',
            '/\bno\b\.?/iu' => 'No',
        ];

        foreach ($map as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        $text = preg_replace('/\s*,\s*/u', ', ', $text) ?? $text;
        $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;

        return alamat_clean_part($text);
    }
}

if (!function_exists('alamat_normalize_line')) {
    function alamat_normalize_line($value): string
    {
        $text = alamat_clean_part($value);
        if ($text === '' || alamat_is_dummy_value($text)) {
            return '';
        }

        $text = alamat_expand_abbreviations($text);
        $text = alamat_title_case($text);
        $text = preg_replace('/\bNo\.?\s*(\d+)/iu', 'No $1', $text) ?? $text;
        $text = preg_replace('/\b([A-Za-z])\s*-\s*(\d+)\s*-\s*(\d+)\b/u', '$1-$2-$3', $text) ?? $text;
        $text = preg_replace('/\b([A-Za-z])\s+(\d+)\s+(\d+)\b/u', '$1-$2-$3', $text) ?? $text;
        $text = preg_replace('/\bBlok\s*([A-Z])\s*(\d+)/u', 'Blok $1 $2', $text) ?? $text;
        $text = preg_replace('/\bSungai\s*Besi\b/u', 'Sungai Besi', $text) ?? $text;

        return alamat_clean_part($text);
    }
}

if (!function_exists('alamat_state_variants')) {
    function alamat_state_variants(): array
    {
        return [
            'wilayah persekutuan kuala lumpur' => 'Wilayah Persekutuan Kuala Lumpur',
            'wilayah persekutuan putrajaya' => 'Wilayah Persekutuan Putrajaya',
            'wilayah persekutuan labuan' => 'Wilayah Persekutuan Labuan',
            'wilayah persekutuan' => 'Wilayah Persekutuan Kuala Lumpur',
            'w p putrajaya' => 'Wilayah Persekutuan Putrajaya',
            'wp putrajaya' => 'Wilayah Persekutuan Putrajaya',
            'w p labuan' => 'Wilayah Persekutuan Labuan',
            'wp labuan' => 'Wilayah Persekutuan Labuan',
            'w p kuala lumpur' => 'Wilayah Persekutuan Kuala Lumpur',
            'wp kuala lumpur' => 'Wilayah Persekutuan Kuala Lumpur',
            'kuala lumpur' => 'Wilayah Persekutuan Kuala Lumpur',
            'putrajaya' => 'Wilayah Persekutuan Putrajaya',
            'labuan' => 'Wilayah Persekutuan Labuan',
            'johor' => 'Johor',
            'kedah' => 'Kedah',
            'kelantan' => 'Kelantan',
            'melaka' => 'Melaka',
            'malacca' => 'Melaka',
            'negeri sembilan' => 'Negeri Sembilan',
            'pahang' => 'Pahang',
            'perak' => 'Perak',
            'perlis' => 'Perlis',
            'pulau pinang' => 'Pulau Pinang',
            'penang' => 'Pulau Pinang',
            'sabah' => 'Sabah',
            'sarawak' => 'Sarawak',
            'selangor' => 'Selangor',
            'terengganu' => 'Terengganu',
            'trg' => 'Terengganu',
            'jhr' => 'Johor',
            'kdh' => 'Kedah',
            'ktn' => 'Kelantan',
            'mlk' => 'Melaka',
            'nsn' => 'Negeri Sembilan',
            'phg' => 'Pahang',
            'prk' => 'Perak',
            'pls' => 'Perlis',
            'png' => 'Pulau Pinang',
            'sbh' => 'Sabah',
            'swk' => 'Sarawak',
            'sgr' => 'Selangor',
        ];
    }
}

if (!function_exists('alamat_normalize_state')) {
    function alamat_normalize_state($value): string
    {
        $text = alamat_lower(alamat_expand_abbreviations($value));
        if ($text === '' || alamat_is_dummy_value($text)) {
            return '';
        }

        $normalized = preg_replace('/[^a-z0-9 ]+/u', ' ', $text) ?? $text;
        $normalized = preg_replace('/\s{2,}/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        $states = alamat_state_variants();
        if (isset($states[$normalized])) {
            return $states[$normalized];
        }

        foreach ($states as $variant => $standard) {
            if ($normalized === $variant || str_contains(' ' . $normalized . ' ', ' ' . $variant . ' ')) {
                return $standard;
            }
        }

        return alamat_title_case($value);
    }
}

if (!function_exists('alamat_infer_state_from_context')) {
    function alamat_infer_state_from_context($poskod, $bandar, $fallback = ''): string
    {
        $resolvedFallback = alamat_normalize_state($fallback);
        $normalizedBandar = alamat_lower($bandar);
        $normalizedPoskod = preg_replace('/\D+/', '', (string)$poskod) ?? '';

        if ($normalizedBandar !== '') {
            if (str_contains($normalizedBandar, 'cyberjaya')) {
                return 'Selangor';
            }

            if (str_contains($normalizedBandar, 'putrajaya')) {
                return 'Wilayah Persekutuan Putrajaya';
            }
        }

        if (preg_match('/^62\d{3}$/', $normalizedPoskod) === 1) {
            return 'Wilayah Persekutuan Putrajaya';
        }

        if ($normalizedPoskod === '63000' && ($normalizedBandar === '' || str_contains($normalizedBandar, 'cyberjaya'))) {
            return 'Selangor';
        }

        return $resolvedFallback;
    }
}

if (!function_exists('alamat_normalize_country')) {
    function alamat_normalize_country($value): string
    {
        $text = alamat_lower($value);
        if ($text === '' || alamat_is_dummy_value($text)) {
            return 'Malaysia';
        }

        $normalized = preg_replace('/[^a-z0-9 ]+/u', ' ', $text) ?? $text;
        $normalized = preg_replace('/\s{2,}/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if (in_array($normalized, ['malaysia', 'my', 'mys'], true)) {
            return 'Malaysia';
        }

        return alamat_title_case($value);
    }
}

if (!function_exists('alamat_extract_postcode_and_city')) {
    function alamat_extract_postcode_and_city($value): array
    {
        $text = alamat_normalize_line($value);
        if ($text === '') {
            return ['text' => '', 'poskod' => '', 'bandar' => ''];
        }

        $poskod = '';
        $bandar = '';
        if (preg_match('/\b(?:lot|no|pt|hs|h\s*s|geran|gm|pm|pn)\s+\d{5}\b/iu', $text) === 1) {
            return [
                'text' => alamat_clean_part($text),
                'poskod' => '',
                'bandar' => '',
            ];
        }

        if (preg_match('/\b(\d{5})\b\s*(.*)$/u', $text, $matches) === 1) {
            $poskod = $matches[1];
            $bandar = alamat_normalize_line($matches[2] ?? '');
            $text = alamat_clean_part(str_replace($matches[0], '', $text));
        }

        return [
            'text' => alamat_clean_part($text),
            'poskod' => $poskod,
            'bandar' => $bandar,
        ];
    }
}

if (!function_exists('alamat_remove_duplicate_tokens')) {
    function alamat_remove_duplicate_tokens(array $parts): array
    {
        $seen = [];
        $result = [];

        foreach ($parts as $part) {
            $clean = alamat_clean_part($part);
            if ($clean === '') {
                continue;
            }

            $key = alamat_lower($clean);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $clean;
        }

        return $result;
    }
}

if (!function_exists('normalize_distance_address_data')) {
    function normalize_distance_address_data($data): array
    {
        if (is_array($data)) {
            return [
                'alamat1' => alamat_clean_part($data['alamat1'] ?? ''),
                'alamat2' => alamat_clean_part($data['alamat2'] ?? ''),
                'alamat3' => alamat_clean_part($data['alamat3'] ?? ''),
                'poskod' => alamat_clean_part($data['poskod'] ?? ''),
                'negeri' => alamat_clean_part($data['negeri'] ?? ''),
                'negara' => alamat_clean_part($data['negara'] ?? ''),
            ];
        }

        return [
            'alamat1' => alamat_clean_part($data),
            'alamat2' => '',
            'alamat3' => '',
            'poskod' => '',
            'negeri' => '',
            'negara' => '',
        ];
    }
}

if (!function_exists('alamat_normalize_output')) {
    function alamat_normalize_output($data): array
    {
        $source = normalize_distance_address_data($data);
        $rawValues = [
            $source['alamat1'],
            $source['alamat2'],
            $source['alamat3'],
            $source['poskod'],
            $source['negeri'],
            $source['negara'],
        ];

        $meaningfulValues = array_values(array_filter($rawValues, static fn($value) => alamat_clean_part($value) !== '' && !alamat_is_dummy_value($value)));
        if ($meaningfulValues === []) {
            return [
                'alamat1' => '',
                'alamat2' => '',
                'alamat3' => '',
                'poskod' => '',
                'bandar' => '',
                'negeri' => '',
                'negara' => 'Malaysia',
                'alamat_standard' => '',
                'status' => 'EMPTY',
                'raw' => '',
            ];
        }

        $line1 = alamat_normalize_line($source['alamat1']);
        $line2 = alamat_normalize_line($source['alamat2']);
        $line3 = alamat_normalize_line($source['alamat3']);

        $line1Extract = alamat_extract_postcode_and_city($line1);
        $line2Extract = alamat_extract_postcode_and_city($line2);
        $line3Extract = alamat_extract_postcode_and_city($line3);

        $line1 = $line1Extract['text'];
        $line2 = $line2Extract['text'];
        $line3 = $line3Extract['text'];

        $poskod = '';
        foreach ([
            preg_replace('/\D+/', '', $source['poskod']) ?? '',
            $line3Extract['poskod'],
            $line2Extract['poskod'],
            $line1Extract['poskod'],
        ] as $candidatePoskod) {
            if (preg_match('/^\d{5}$/', (string)$candidatePoskod) === 1) {
                $poskod = (string)$candidatePoskod;
                break;
            }
        }

        $bandar = '';
        foreach ([
            $line3Extract['bandar'],
            $line2Extract['bandar'],
            $line1Extract['bandar'],
        ] as $candidateBandar) {
            $candidateBandar = alamat_normalize_line($candidateBandar);
            if ($candidateBandar !== '') {
                $bandar = $candidateBandar;
                break;
            }
        }

        $negeri = alamat_infer_state_from_context($poskod, $bandar, $source['negeri']);
        $negara = alamat_normalize_country($source['negara']);

        $streetParts = alamat_remove_duplicate_tokens([$line1, $line2]);
        $kawasan = alamat_clean_part($line3);

        if ($kawasan !== '' && $bandar !== '' && alamat_lower($kawasan) === alamat_lower($bandar)) {
            $kawasan = '';
        }
        if ($kawasan !== '' && $negeri !== '' && alamat_lower($kawasan) === alamat_lower($negeri)) {
            $kawasan = '';
        }

        if ($bandar === '' && $kawasan !== '') {
            $segments = array_values(array_filter(array_map('alamat_clean_part', explode(',', $kawasan)), static fn($value) => $value !== ''));
            if (count($segments) > 1) {
                $bandar = array_pop($segments);
                $kawasan = implode(', ', $segments);
            }
        }

        $street = implode(', ', $streetParts);
        $street = alamat_clean_part($street);
        $kawasan = alamat_clean_part($kawasan);
        $bandar = alamat_clean_part($bandar);

        $poskodBandar = trim(implode(' ', array_values(array_filter([$poskod, $bandar], static fn($value) => $value !== ''))));
        $finalParts = alamat_remove_duplicate_tokens([$street, $kawasan, $poskodBandar, $negeri, 'Malaysia']);
        $alamatStandard = implode(', ', $finalParts);

        $hasStreet = $street !== '';
        $hasNegeri = $negeri !== '';
        $hasBandar = $bandar !== '';
        $hasPoskod = preg_match('/^\d{5}$/', $poskod) === 1;
        $rawPoskodProvided = alamat_clean_part($source['poskod']) !== ''
            || $line1Extract['poskod'] !== ''
            || $line2Extract['poskod'] !== ''
            || $line3Extract['poskod'] !== '';
        $hasInvalidPoskod = $rawPoskodProvided && !$hasPoskod;
        $status = ($hasStreet && $hasNegeri && ($hasBandar || $hasPoskod) && !$hasInvalidPoskod) ? 'VALID' : 'INVALID';

        return [
            'alamat1' => $streetParts[0] ?? '',
            'alamat2' => $streetParts[1] ?? '',
            'alamat3' => $kawasan,
            'poskod' => $poskod,
            'bandar' => $bandar,
            'negeri' => $negeri,
            'negara' => $negara,
            'alamat_standard' => $alamatStandard,
            'status' => $status,
            'raw' => implode(', ', $meaningfulValues),
        ];
    }
}

if (!function_exists('alamat_parse_components')) {
    function alamat_parse_components($data): array
    {
        return alamat_normalize_output($data);
    }
}

if (!function_exists('format_alamat_standard')) {
    function format_alamat_standard($data): string
    {
        $normalized = alamat_normalize_output($data);
        return (string)($normalized['alamat_standard'] ?? '');
    }
}

if (!function_exists('alamat_strip_premise_prefix')) {
    function alamat_strip_premise_prefix($value): string
    {
        $text = alamat_normalize_line($value);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/^(No)\s+[A-Z0-9\-\/]+\s*,?\s*/iu', '', $text) ?? $text;
        $text = preg_replace('/^[A-Z]?\d+[A-Z0-9\-\/]*\s*,?\s*/u', '', $text) ?? $text;

        return alamat_clean_part($text);
    }
}

if (!function_exists('alamat_is_unit_prefix')) {
    function alamat_is_unit_prefix(string $value): bool
    {
        $text = alamat_normalize_line($value);
        if ($text === '') {
            return false;
        }

        return preg_match('/^No\s+[A-Z0-9\-\/]+$/iu', $text) === 1
            || preg_match('/^[A-Z]?\d+[A-Z0-9\-\/]*$/u', $text) === 1;
    }
}

if (!function_exists('alamat_strip_unit_only')) {
    function alamat_strip_unit_only($value): string
    {
        $text = alamat_normalize_line($value);
        if ($text === '') {
            return '';
        }

        $segments = array_values(array_filter(
            array_map('alamat_clean_part', explode(',', $text)),
            static fn($segment) => $segment !== ''
        ));

        if (count($segments) >= 2 && alamat_is_unit_prefix($segments[0])) {
            return implode(', ', array_slice($segments, 1));
        }

        return alamat_strip_premise_prefix($text);
    }
}

if (!function_exists('build_distance_search_candidates')) {
    function build_distance_search_candidates($data): array
    {
        $normalized = alamat_normalize_output($data);
        $negara = 'Malaysia';
        $negeri = (string)($normalized['negeri'] ?? '');
        $poskod = (string)($normalized['poskod'] ?? '');
        $bandar = (string)($normalized['bandar'] ?? '');
        $street1 = (string)($normalized['alamat1'] ?? '');
        $street2 = (string)($normalized['alamat2'] ?? '');
        $kawasan = (string)($normalized['alamat3'] ?? '');

        $street1Broad = alamat_strip_unit_only($street1);
        $street2Broad = alamat_strip_unit_only($street2);
        $postcodeCity = trim(implode(' ', array_values(array_filter([$poskod, $bandar], static fn($value) => $value !== ''))));

        $candidateSets = [
            [$street1, $street2, $kawasan, $postcodeCity, $negeri, $negara],
            [$street1Broad, $street2, $kawasan, $postcodeCity, $negeri, $negara],
            [$street1Broad, $street2Broad, $kawasan, $postcodeCity, $negeri, $negara],
            [$street1Broad, $kawasan, $postcodeCity, $negeri, $negara],
            [$street2Broad, $kawasan, $postcodeCity, $negeri, $negara],
            [$street1Broad, $street2Broad, $postcodeCity, $negeri, $negara],
            [$kawasan, $postcodeCity, $negeri, $negara],
        ];

        $candidates = [];
        foreach ($candidateSets as $candidateParts) {
            $candidate = implode(', ', array_values(array_filter(array_map('alamat_clean_part', $candidateParts), static fn($value) => $value !== '')));
            if ($candidate === '') {
                continue;
            }

            $candidates[$candidate] = true;
        }

        return array_keys($candidates);
    }
}

if (!function_exists('build_google_distance_search_candidates')) {
    function build_google_distance_search_candidates($data): array
    {
        $normalized = alamat_normalize_output($data);
        $negara = 'Malaysia';
        $negeri = (string)($normalized['negeri'] ?? '');
        $poskod = (string)($normalized['poskod'] ?? '');
        $bandar = (string)($normalized['bandar'] ?? '');
        $street1 = (string)($normalized['alamat1'] ?? '');
        $street2 = (string)($normalized['alamat2'] ?? '');
        $kawasan = (string)($normalized['alamat3'] ?? '');

        $street1Broad = alamat_strip_unit_only($street1);
        $street2Broad = alamat_strip_unit_only($street2);
        $postcodeCity = trim(implode(' ', array_values(array_filter([$poskod, $bandar], static fn($value) => $value !== ''))));
        $street2Segments = array_values(array_filter(array_map('alamat_clean_part', explode(',', $street2)), static fn($value) => $value !== ''));
        $street2Primary = $street2Segments[0] ?? '';
        $street2Locality = $street2Segments[count($street2Segments) - 1] ?? '';
        $street2LocalityBroad = preg_replace('/\b(?:Pekan|Bandar|Kg|Kampung|Taman|Tmn)\s+/iu', '', $street2Locality) ?? $street2Locality;
        $street2LocalityBroad = alamat_clean_part($street2LocalityBroad);

        $candidateSets = [
            [$street1, $street2, $kawasan, $postcodeCity, $negeri, $negara],
            [$street2, $kawasan, $postcodeCity, $negeri, $negara],
            [$street2Primary, $street2LocalityBroad, $postcodeCity, $negeri, $negara],
            [$street2Primary, $street2LocalityBroad, $negeri, $negara],
            [$street1Broad, $street2, $kawasan, $postcodeCity, $negeri, $negara],
            [$street1Broad, $street2Broad, $kawasan, $postcodeCity, $negeri, $negara],
            [$street1, $street2, $kawasan, $poskod, $negeri, $negara],
            [$street1Broad, $street2, $kawasan, $poskod, $negeri, $negara],
        ];

        $candidates = [];
        foreach ($candidateSets as $candidateParts) {
            $candidate = implode(', ', array_values(array_filter(array_map('alamat_clean_part', $candidateParts), static fn($value) => $value !== '')));
            if ($candidate === '') {
                continue;
            }

            $candidates[$candidate] = true;
        }

        return array_keys($candidates);
    }
}

if (!function_exists('validate_alamat')) {
    function validate_alamat($data): string
    {
        $normalized = alamat_normalize_output($data);
        return (string)($normalized['status'] ?? 'EMPTY');
    }
}

if (!function_exists('generate_map_url')) {
    function generate_map_url($alamat): string
    {
        $cleanAlamat = alamat_clean_part($alamat);
        return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($cleanAlamat);
    }
}
