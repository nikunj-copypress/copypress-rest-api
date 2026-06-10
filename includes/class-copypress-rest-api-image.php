<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class COPYREAP_REST_API_Image {
    public function __construct() {
        // Image handling logic can be placed here if needed
    }

    // Handle image upload
    public static function copyreap_handle_image( $image_url, $post_id = null ) {

       if ( empty( $image_url ) ) { return new WP_Error( 'invalid_image_url', 'Image URL is required.' ); }

        $image_url = esc_url_raw( $image_url );

        if ( ! wp_http_validate_url( $image_url ) ) { return new WP_Error( 'invalid_image_url', 'Provided image URL is invalid.' ); }

        // Allow only image extensions
        $allowed_extensions = array(
            'jpg',
            'jpeg',
            'png',
            'webp'
        );

        $path      = wp_parse_url( $image_url, PHP_URL_PATH );
        $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

        if ( ! in_array( $extension, $allowed_extensions, true ) ) { return new WP_Error( 'invalid_file_type', 'Only JPG, JPEG, PNG and WEBP images are allowed.' ); }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Download file to temporary location
        $tmp_file = download_url( $image_url, 30 );

        if ( is_wp_error( $tmp_file ) ) { return new WP_Error( 'image_download_failed', 'Failed to download image.' ); }

        $file_array = array( 'name'     => wp_basename( $path ), 'tmp_name' => $tmp_file, );

        // Validate real mime type
        $filetype = wp_check_filetype_and_ext( $file_array['tmp_name'], $file_array['name'] );

        $image_info = getimagesize( $file_array['tmp_name'] ); 
        if ( false === $image_info ) { wp_delete_file( $tmp_file ); return new WP_Error( 'invalid_image', 'Downloaded file is not a valid image.', [ 'status' => 400 ] ); }

        $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp', );

        if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed_mimes, true ) ) { wp_delete_file( $tmp_file ); return new WP_Error( 'invalid_image', 'Downloaded file is not a valid image.', [ 'status' => 400 ] ); }

        // Upload using WordPress core API
        $attach_id = media_handle_sideload(
            $file_array,
            $post_id
        );

        if ( is_wp_error( $attach_id ) ) { if ( file_exists( $tmp_file ) ) { wp_delete_file( $tmp_file ); } return new WP_Error( 'image_upload_failed', $attach_id->get_error_message() ); }

        if ( ! is_null( $post_id ) ) { set_post_thumbnail( $post_id, $attach_id ); }

            return $attach_id;
    }

    public static function copyreap_validate_image_url( $image_url ) {

        if ( empty( $image_url ) ) {
            return new WP_Error( 'invalid_image_url', 'Image URL is required.' );
        }

        $image_url = esc_url_raw( $image_url );

        if ( ! wp_http_validate_url( $image_url ) ) {
            return new WP_Error( 'invalid_image_url', 'Provided image URL is invalid.' );
        }

        $allowed_extensions = array(
            'jpg',
            'jpeg',
            'png',
            'webp'
        );

        $path      = wp_parse_url( $image_url, PHP_URL_PATH );
        $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

        if ( ! in_array( $extension, $allowed_extensions, true ) ) {
            return new WP_Error(
                'invalid_file_type',
                'Only JPG, JPEG, PNG and WEBP images are allowed.'
            );
        }

        $host = wp_parse_url(
            $image_url,
            PHP_URL_HOST
        );

        $blocked_hosts = array(
            'localhost',
            '127.0.0.1',
            '::1'
        );

        if ( in_array( strtolower( $host ), $blocked_hosts, true ) ) {
            return new WP_Error(
                'invalid_image_url',
                'Local URLs are not allowed.'
            );
        }

        if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {

            if (
                ! filter_var(
                    $host,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                )
            ) {
                return new WP_Error(
                    'invalid_image_url',
                    'Private network addresses are not allowed.'
                );
            }
        }
        return true;
    }
}

new COPYREAP_REST_API_Image();