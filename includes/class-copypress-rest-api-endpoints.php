<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class COPYPRESS_REST_API_Endpoints {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'copypress_register_routes' ] );
        add_action( 'rest_api_init', [ $this, 'enable_cors_in_wp_rest' ], 15 );
    }

    // Register all the API routes
    public function copypress_register_routes() {
        register_rest_route( 'copypress-api/v1', '/posts', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'copypress_create_post' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'copypress-api/v1', '/posts/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [ $this, 'copypress_update_post' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'copypress-api/v1', '/posts/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'copypress_delete_post' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'copypress-api/v1', '/categories', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'copypress_get_categories' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'copypress-api/v1', '/tags', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'copypress_get_tags' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'copypress-api/v1', '/post-types', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'copypress_get_post_types' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'copypress-api/v1', '/get-taxonomies/(?P<post_type>[a-zA-Z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'copypress_get_taxonomy_by_post_type' ],
            'permission_callback' => '__return_true',
        ] );
    }

    // Enable CORS for WordPress REST API
    public function enable_cors_in_wp_rest() {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, X-Custom-Header, x-csrf-token, x-api-key");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
        header("Access-Control-Allow-Credentials: true");

        // Handle OPTIONS preflight request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
            header("Access-Control-Allow-Headers: Content-Type, X-Custom-Header, x-csrf-token, x-api-key");
            header("Access-Control-Allow-Credentials: true");
            exit(0); // Respond immediately to OPTIONS request
        }
    }

    // Create Post
    public function copypress_create_post( $data ) {
        
        // Handle post creation logic
        $title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
        $content = isset( $data['content'] ) ? sanitize_textarea_field( $data['content'] ) : '';
        $excerpt = isset( $data['excerpt'] ) ? sanitize_textarea_field( $data['excerpt'] ) : '';
        $category = isset( $data['category'] ) ? (int) $data['category'] : '';
        $tags = isset( $data['tags'] ) ? sanitize_text_field( $data['tags'] ) : '';
        $image_url = isset( $data['image'] ) ? esc_url_raw( $data['image'] ) : '';
        $post_type = isset( $data['post_type'] ) ? sanitize_text_field( $data['post_type'] ) : 'post';
        $author_id = isset( $data['author_id'] ) ? (int) $data['author_id'] : get_current_user_id(); // Default to current logged-in user
        $post_status = isset( $data['post_status'] ) ? sanitize_text_field( $data['post_status'] ) : '';

        // Validate required fields
        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', 'Please enter a title.', [ 'status' => 400 ] );
        }
        if ( empty( $content ) ) {
            return new WP_Error( 'missing_content', 'Please enter content.', [ 'status' => 400 ] );
        }
       
        $postarr = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status'  => $post_status,
            'post_category' => [ $category ],
            'tags_input'   => explode( ',', $tags ),
            'post_type'    => $post_type,
            'post_author'  => $author_id,
        ];

        $post_id = wp_insert_post( $postarr );
        if ( $post_id ) {
            
            if ($image_url) {
               
                $image_id = COPYPRESS_REST_API_Image::copypress_handle_image($image_url, $post_id);
                
                if ($image_id) {
                    set_post_thumbnail($post_id, $image_id);
                }
            }
           
            return new WP_REST_Response( [
                'message' => 'Post created successfully',
                'status'  => 200,
                'data'    => get_post( $post_id ),
            ], 200 );
        }

        return new WP_Error( 'create_failed', 'Post creation failed', [ 'status' => 500 ] );
    }

    // Update Post
    public function copypress_update_post( $data ) {
        // Handle post update logic
        $post_id = (int) $data['id'];
        $title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
        $content = isset( $data['content'] ) ? sanitize_textarea_field( $data['content'] ) : '';
        $excerpt = isset( $data['excerpt'] ) ? sanitize_textarea_field( $data['excerpt'] ) : '';
        $category = isset( $data['category'] ) ? (int) $data['category'] : '';
        $tags = isset( $data['tags'] ) ? sanitize_text_field( $data['tags'] ) : '';
        $image_url = isset( $data['image'] ) ? esc_url_raw( $data['image'] ) : '';
        $post_type = isset( $data['post_type'] ) ? sanitize_text_field( $data['post_type'] ) : 'post';

        // Validate required fields
        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', 'Please enter a title.', [ 'status' => 400 ] );
        }
        if ( empty( $content ) ) {
            return new WP_Error( 'missing_content', 'Please enter content.', [ 'status' => 400 ] );
        }

        $postarr = [
            'ID'           => $post_id,
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_category' => [ $category ],
            'tags_input'   => explode( ',', $tags ),
            'post_type'    => $post_type,
        ];

        $updated_post_id = wp_update_post( $postarr );
        if ( $updated_post_id ) {
            if ($image_url) {
                $image_id = copypress_handle_image($image_url, $updated_post_id);
                if ($image_id) {
                    set_post_thumbnail($updated_post_id, $image_id);
                }
            }
            return new WP_REST_Response( [
                'message' => 'Post updated successfully',
                'status'  => 200,
                'data'    => get_post( $updated_post_id ),
            ], 200 );
        }

        return new WP_Error( 'update_failed', 'Post update failed', [ 'status' => 500 ] );
    }

    // Delete Post
    public function copypress_delete_post( $data ) {
        // Handle post deletion logic
        $post_id = (int) $data['id'];
        if ( wp_delete_post( $post_id, true ) ) {
            return new WP_REST_Response( [
                'message' => 'Post deleted successfully',
                'status'  => 200,
            ], 200 );
        }

        return new WP_Error( 'delete_failed', 'Post delete failed', [ 'status' => 500 ] );
    }

    // Fetch categories
    public function copypress_get_categories() {
        $categories = get_categories( ['hide_empty' => false] );
        $response = [];
        
        foreach ( $categories as $category ) {
            $response[] = [
                'id'   => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
            ];
        }

        return new WP_REST_Response( $response, 200 );
    }

    // Fetch tags
    public function copypress_get_tags() {
        $tags = get_tags( ['hide_empty' => false] );
        $response = [];
        
        foreach ( $tags as $tag ) {
            $response[] = [
                'id'   => $tag->term_id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ];
        }

        return new WP_REST_Response( $response, 200 );
    }

    // Fetch post types
    public function copypress_get_post_types() {
        $post_types = get_post_types( ['public' => true], 'objects' );
        $response = [];

        foreach ( $post_types as $post_type ) {
            $response[] = [
                'name' => $post_type->name,
                'label' => $post_type->label,
            ];
        }

        return new WP_REST_Response( $response, 200 );
    }

    // Fetch Taxonomy by post-type
    public function copypress_get_taxonomy_by_post_type( $data ) {
        $post_type = $data['post_type'];
    
        // Check if the post type is valid
        if ( ! post_type_exists( $post_type ) ) {
            return new WP_Error( 'invalid_post_type', 'Invalid post type', array( 'status' => 400 ) );
        }
    
        // Get all taxonomies associated with the post type
        $taxonomies = get_object_taxonomies( $post_type, 'objects' );
    
        if ( empty( $taxonomies ) ) {
            return new WP_REST_Response( 
                array( 
                    'message' => 'No taxonomies found for this post type.',
                    'status' => '404', 
                ), 
                404 
            );
        }
    
        $response = array(
            'categories' => array(),
            'tags'       => array(),
        );
    
        // Loop through each taxonomy and retrieve the terms
        foreach ( $taxonomies as $taxonomy ) {
            $terms = get_terms( array(
                'taxonomy'   => $taxonomy->name,
                'hide_empty' => false,
            ) );
            
            // Check if terms are found for each taxonomy
            if ( ! empty( $terms ) ) {
                $term_data = array();
    
                foreach ( $terms as $term ) {
                    $term_data[] = array(
                        'term_id' => $term->term_id,   // Term ID
                        'slug'    => $term->slug,      // Term slug
                        'name'    => $term->name,      // Term name
                    );
                }
               
                // Check if the taxonomy is hierarchical
                if ( $taxonomy->hierarchical ) {
                    $response['categories'] = $term_data; // Hierarchical taxonomies go to 'categories'
                } else {
                    $response['tags'] = $term_data; // Non-hierarchical taxonomies go to 'tags'
                }
            }
        }
        
        // Return the taxonomies in categories and tags
        return new WP_REST_Response( 
            array( 
                'message' => 'Taxonomies retrieved successfully.', 
                'status'  => '200',
                'data'    => $response, 
            ), 
            200 
        );
    }

}

new COPYPRESS_REST_API_Endpoints();