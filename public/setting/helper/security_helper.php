<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */function get_client_ip() {
    return $_SERVER['HTTP_CLIENT_IP']     ?? 
           $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
           $_SERVER['REMOTE_ADDR']         ?? 'UNKNOWN';
}

class Encryption {
    private $key    = 'esmartcardbtmkupnmsecret';
    private $cipher = 'AES-256-ECB';

    public function encode($value) {
        if (!$value) return false;
        $encrypted = openssl_encrypt($value, $this->cipher, $this->key, OPENSSL_RAW_DATA);
        return $this->safe_b64encode($encrypted);
    }

    public function decode($value) {
        if (!$value) return false;
        $decoded = $this->safe_b64decode($value);
        return openssl_decrypt($decoded, $this->cipher, $this->key, OPENSSL_RAW_DATA);
    }

    private function safe_b64encode($string) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($string));
    }

    private function safe_b64decode($string) {
        $data = str_replace(['-', '_'], ['+', '/'], $string);
        $mod4 = strlen($data) % 4;
        if ($mod4) $data .= substr('====', $mod4);
        return base64_decode($data);
    }
}
