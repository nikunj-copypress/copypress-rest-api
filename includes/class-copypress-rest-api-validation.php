<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class COPYPRESS_REST_API_Validation {
    public function __construct() {
        add_filter( 'rest_pre_dispatch', [ $this, 'copypress_validate_api_request' ], 10, 3 );
    }

    // Validate API request based on API Key and Nonce
    public function copypress_validate_api_request( $response, $server, $request ) {
        // Only check for the specific namespace
        if ( strpos( $request->get_route(), '/copypress-api/v1' ) === false ) {
            return $response;
        }
    
        // Get the API Key and Nonce from headers
        $api_key = isset( $_SERVER['HTTP_X_API_KEY'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_API_KEY'] ) ) : '';
        // $nonce = isset( $_SERVER['HTTP_X_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_NONCE'] ) ) : '';
       
        // Check if the API Key is valid
        if ( $api_key !== get_option( 'copypress_rest_api_integration_key' ) ) {
            return new WP_Error( 'forbidden', 'Invalid API Key', [ 'status' => 403 ] );
        }
    
        return $response;
    }
    
}

new COPYPRESS_REST_API_Validation();
