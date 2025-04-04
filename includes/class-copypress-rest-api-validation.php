<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class COPYREAP_REST_API_Validation {
    public function __construct() {
        add_filter( 'rest_pre_dispatch', [ $this, 'copyreap_validate_api_request' ], 10, 3 );
    }

    // Validate API request based on JWT Token
    public function copyreap_validate_api_request( $response, $server, $request ) {
    
        if ( strpos( $request->get_route(), '/copypress-api/v1/login' ) !== false ) {
            return $response;  // Skip validation for the login route
        }
    
        // Only check for the specific namespace
        if ( strpos( $request->get_route(), '/copypress-api/v1' ) === false ) {
            return $response;
        }
    
        // Check if the Authorization header is provided using the REST API request object
        $auth_header = $request->get_header('Authorization');
        
        if (!$auth_header) {
            return new WP_Error('missing_token', 'Authorization header missing', ['status' => 403]);
        }
    
        if (strpos($auth_header, 'Bearer ') !== 0) {
            return new WP_Error('invalid_token', 'Invalid token format', ['status' => 403]);
        }
    
        // Extract the token by removing the 'Bearer ' prefix
        $token = substr($auth_header, 7);
        
        // Validate the JWT token
        $jwt = new COPYREAP_JWT_Token();
        $user_data = $jwt->copyreap_validate_token($token);
    
        if (!$user_data) {
            return new WP_Error('invalid_token', 'Invalid token or expired', ['status' => 403]);
        }
    
        // Optionally, you can set the user based on the validated JWT token (for further user-related checks)
        wp_set_current_user($user_data['user_id']);
    
        return $response; // Continue the request processing
    }
}

new COPYREAP_REST_API_Validation();
