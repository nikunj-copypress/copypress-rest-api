<?php
if (!class_exists('COPYREAP_JWT_Token')) {
    class COPYREAP_JWT_Token {
        private $secret_key;

        public function __construct() {
            // Use a secret key from wp-config.php if defined
            $this->secret_key = defined('COPYREAP_JWT_SECRET_KEY') ? COPYREAP_JWT_SECRET_KEY : '826657a98e396172f8aed51d110d529d';
        }

        // Generate JWT Token
        public function copyreap_generate_token($user) {
            $issued_at = time();
            $expiration_time = $issued_at + 7200; // Token expires in 2 hour
            
            $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
            $payload = json_encode([
                'iss' => get_bloginfo('url'),
                'iat' => $issued_at,
                'exp' => $expiration_time,
                'data' => [
                    'user_id' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email
                ]
            ]);

            $base64_url_header = $this->copyreap_base64UrlEncode($header);
            $base64_url_payload = $this->copyreap_base64UrlEncode($payload);
            $signature = hash_hmac('sha256', $base64_url_header . '.' . $base64_url_payload, $this->secret_key, true);
            $base64_url_signature = $this->copyreap_base64UrlEncode($signature);

            return $base64_url_header . '.' . $base64_url_payload . '.' . $base64_url_signature;
        }

        // Validate JWT Token
        public function copyreap_validate_token($token) {
            if (!$token) return false;

            $token_parts = explode('.', $token);
            if (count($token_parts) !== 3) return false;

            list($header, $payload, $signature) = $token_parts;

            // Verify signature
            $expected_signature = hash_hmac('sha256', $header . '.' . $payload, $this->secret_key, true);
            $expected_signature = $this->copyreap_base64UrlEncode($expected_signature);
            if (!hash_equals($expected_signature, $signature)) return false;

            // Decode payload and check expiration
            $payload_data = json_decode(base64_decode($payload), true);
            if (!$payload_data || time() > $payload_data['exp']) return false;

            return $payload_data['data'];
        }

        // Helper: Base64 URL Encode
        private function copyreap_base64UrlEncode($data) {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        }
    }
}
