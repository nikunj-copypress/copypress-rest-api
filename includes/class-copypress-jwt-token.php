<?php
if ( ! class_exists( 'JWT_Token' ) ) {
    class JWT_Token {
        
        private $secret_key;

        public function __construct() {
            $this->secret_key = defined('JWT_SECRET_KEY') ? JWT_SECRET_KEY : '826657a98e396172f8aed51d110d529d';  // Can define this in wp-config.php
        }

        // Method to generate the JWT token for a logged-in user
        public function generate_token($user) {
            $issued_at = time();
            $expiration_time = $issued_at + 3600; // Token expires in 1 hour
            
            // JWT Header
            $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
            $base64_url_header = $this->base64UrlEncode($header);

            // JWT Payload
            $payload = json_encode([
                'iss' => get_bloginfo('url'),    // Issuer (your site URL)
                'iat' => $issued_at,             // Issued At
                'exp' => $expiration_time,       // Expiration Time
                'data' => [
                    'user_id' => $user->ID,      // User ID
                    'username' => $user->user_login, // Username
                    'email' => $user->user_email // Optional: User Email
                ]
            ]);
            $base64_url_payload = $this->base64UrlEncode($payload);

            // JWT Signature
            $signature = hash_hmac('sha256', $base64_url_header . '.' . $base64_url_payload, $this->secret_key, true);
            $base64_url_signature = $this->base64UrlEncode($signature);

            // Final JWT token
            return $base64_url_header . '.' . $base64_url_payload . '.' . $base64_url_signature;
        }

        // Helper method to Base64Url encode data
        private function base64UrlEncode($data) {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        }
    }
}