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
        if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
            return new WP_Error( 'invalid_image_url', 'Provided image URL is invalid.' );
        }

        $image_data = file_get_contents( $image_url );
        if ( ! $image_data ) {
            return new WP_Error( 'image_download_failed', 'Failed to download image.' );
        }

        $filename = basename( $image_url );
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['path'] . '/' . $filename;
        file_put_contents( $upload_path, $image_data );

        $attachment = [
            'guid' => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => mime_content_type( $upload_path ),
            'post_title' => sanitize_file_name( $filename ),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attach_id = wp_insert_attachment( $attachment, $upload_path, $post_id );
        if ( ! is_wp_error( $attach_id ) ) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata( $attach_id, $upload_path );
            wp_update_attachment_metadata( $attach_id, $attach_data );

            // Set as featured image (works for both classic and block editor)
            if ( ! is_null( $post_id ) ) {
                set_post_thumbnail( $post_id, $attach_id );
            }

            return $attach_id;
        }

        return new WP_Error( 'image_upload_failed', 'Failed to upload image.' );
    }
}

new COPYREAP_REST_API_Image();