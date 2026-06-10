<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (!class_exists('COPYREAP_JWT_Token')) {
    class COPYREAP_JWT_Token {
        private $secret_key;

        public function __construct() {
            // Use a secret key from wp-config.php if defined
            $this->secret_key = defined('COPYREAP_JWT_SECRET_KEY') ? COPYREAP_JWT_SECRET_KEY : wp_salt('auth');
        }

        // Generate JWT Token
        public function copyreap_generate_token($user) {
            $issued_at = time();
            $expiration_time = $issued_at + DAY_IN_SECONDS; 
            
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

            if (empty($token)) { return false; }

            $parts = explode('.', $token);

            if (count($parts) !== 3) { return false; }

            list($header, $payload, $signature) = $parts;

            $expected_signature = hash_hmac(
                'sha256',
                $header . '.' . $payload,
                $this->secret_key,
                true
            );

            $expected_signature = $this->copyreap_base64UrlEncode( $expected_signature );

            if (!hash_equals($expected_signature, $signature)) {
                return false;
            }

            $payload_data = json_decode( $this->copyreap_base64UrlDecode($payload), true );

            if ( empty($payload_data) || !isset($payload_data['exp']) ) { return false; }

            if (time() >= $payload_data['exp']) { return false; }

            return $payload_data['data'];
        }

        // Helper: Base64 URL Encode
        private function copyreap_base64UrlEncode($data) {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        }

        // Helper: Base64 URL Decode
        private function copyreap_base64UrlDecode($data) {

            $remainder = strlen($data) % 4;

            if ($remainder) {
                $data .= str_repeat('=', 4 - $remainder);
            }

            return base64_decode(
                strtr($data, '-_', '+/')
            );
        }
    }
}
