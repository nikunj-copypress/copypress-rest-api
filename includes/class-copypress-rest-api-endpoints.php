<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class COPYREAP_REST_API_Endpoints {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'copyreap_register_routes_endpoint' ] );
        add_action( 'rest_api_init', [ $this, 'copyreap_enable_cors_in_wp_rest' ], 15 );
    }

    // Register all the API routes
    public function copyreap_register_routes_endpoint() {
        register_rest_route('copypress-api/v1', '/login', [
            'methods' => 'POST',
            'callback' => [$this, 'copyreap_login_user'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route( 'copypress-api/v1', '/posts', [
            'methods'             => 'POST',
            'callback'            => [$this, 'copyreap_create_post' ],
            'permission_callback' => [$this, 'copyreap_jwt_permission_check']
        ]);

        register_rest_route( 'copypress-api/v1', '/posts/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [ $this, 'copyreap_update_post' ],
            'permission_callback' => [$this, 'copyreap_jwt_permission_check']
        ]);

        register_rest_route( 'copypress-api/v1', '/posts/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'copyreap_delete_post' ],
            'permission_callback' => [$this, 'copyreap_jwt_permission_check']
        ]);

        register_rest_route( 'copypress-api/v1', '/categories', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'copyreap_get_categories' ],
            'permission_callback' => [$this, 'copyreap_jwt_permission_check']
        ]);

        register_rest_route( 'copypress-api/v1', '/tags', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'copyreap_get_tags' ],
            'permission_callback' => [$this, 'copyreap_jwt_permission_check']
        ]);

        register_rest_route( 'copypress-api/v1', '/post-types', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'copyreap_get_post_types' ],
            'permission_callback' => [$this, 'copyreap_jwt_permission_check']
        ]);

        register_rest_route( 'copypress-api/v1', '/get-taxonomies/(?P<post_type>[a-zA-Z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'copyreap_get_taxonomy_by_post_type' ],
            'permission_callback' => [$this, 'copyreap_jwt_permission_check']
        ] );

        register_rest_route( 'copypress-api/v1', '/dante-authors', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'copyreap_get_dante_users' ],
            'permission_callback' => [$this, 'copyreap_jwt_permission_check']
        ]);
    }

    function copyreap_login_user(WP_REST_Request $request) {
        // Get username and password from the request
        $username = $request->get_param('username');
        $password = $request->get_param('password');
    
        // Validate that username and password are provided
        if (empty($username) || empty($password)) {
            return new WP_Error('missing_credentials', 'Username and password are required.', ['status' => 400]);
        }
    
        // Authenticate the user
        $user = wp_authenticate($username, $password);
    
        // Check if authentication failed
        if (is_wp_error($user)) {
            return new WP_Error('invalid_credentials', 'Invalid username or password.', ['status' => 403]);
        }
    
        // If authentication is successful, generate a JWT token
        $jwt = new COPYREAP_JWT_Token();
        $token = $jwt->copyreap_generate_token($user);
    
        // Return the token in the response
        return rest_ensure_response([
            'token' => $token,
            'user_id' => $user->ID,
            'username' => $user->user_login
        ]);
    }    

    public function copyreap_jwt_permission_check() {
        $auth_header = null;
    
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = sanitize_text_field(wp_unslash($_SERVER['HTTP_AUTHORIZATION'])); 
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_header = sanitize_text_field(wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])); 
        }
    
        if (!$auth_header || strpos($auth_header, 'Bearer ') !== 0) {
            return false;
        }
    
        $token = substr($auth_header, 7);
        $jwt = new COPYREAP_JWT_Token();
        $user_data = $jwt->copyreap_validate_token($token);
    
        if (!$user_data) {
            return false;
        }
    
        wp_set_current_user($user_data['user_id']);
    
        // ✅ Allow multiple roles
        $allowed_roles = ['administrator', 'editor', 'contributor'];
        $user = get_user_by('id', $user_data['user_id']);
        
        if ($user && array_intersect($allowed_roles, (array) $user->roles)) {
            return true;
        }
    
        return false;
    }    
    
    public function copyreap_enable_cors_in_wp_rest() {
        // Ensure this is only for REST API requests and unslash $_SERVER['REQUEST_URI']
        if (isset($_SERVER['REQUEST_URI'])) {
            // Sanitize the input value before using it
            $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])); 
    
            if (strpos($request_uri, '/wp-json/') !== false) {
                header("Access-Control-Allow-Origin: *");
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
                header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Custom-Header, x-csrf-token");
                header("Access-Control-Allow-Credentials: true");
    
                // Handle preflight (OPTIONS) request
                if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                    status_header(200);
                    exit();
                }
            }
        }
    }    

    // Create Post
    public function copyreap_create_post( $data ) {
        $title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
        $content = isset( $data['content'] ) ?  $data['content'] : '';
        $excerpt = isset( $data['excerpt'] ) ? sanitize_textarea_field( $data['excerpt'] ) : '';
        $category_main = isset( $data['category_main'] ) ? sanitize_text_field( $data['category_main'] ) : '';
        $category = isset( $data['category'] ) ? sanitize_text_field( $data['category'] ) : '';
        $tags_main = isset( $data['tags_main'] ) ? sanitize_text_field( $data['tags_main'] ) : '';
        $tags = isset( $data['tags'] ) ? sanitize_text_field( $data['tags'] ) : '';
        $image_url = isset( $data['image'] ) ? esc_url_raw( $data['image'] ) : '';
        $post_type = isset( $data['post_type'] ) ? sanitize_text_field( $data['post_type'] ) : 'post';
        $author_id = isset( $data['author_id'] ) ? (int) $data['author_id'] : get_current_user_id(); // Default to current logged-in user
        $post_status = isset( $data['post_status'] ) ? sanitize_text_field( $data['post_status'] ) : '';
    
        // Handle category assignment
        $cat_exists = get_term_by( 'slug', $category, $category_main ); // Get term by slug
        if ( !$cat_exists ) {
            // Create category if not exists
            $cat_created = wp_insert_term( $category, $category_main );
            if ( !is_wp_error( $cat_created ) ) {
                $cat_id = $cat_created['term_id'];
            }
        } else {
            $cat_id = $cat_exists->term_id;
        }
    
        // Handle tag assignment
        $tags_array = explode( ',', $tags ); // Split tags by comma
        $tags_ids = [];
        foreach ( $tags_array as $tag ) {
            $tag_exists = get_term_by( 'slug', $tag, $tags_main ); // Get term by slug
            if ( !$tag_exists ) {
                // If the tag does not exist, create it
                $tag_created = wp_insert_term( $tag, $tags_main );
                if ( !is_wp_error( $tag_created ) ) {
                    $tags_ids[] = $tag_created['term_id']; // Add the newly created term ID to the list
                }
            } else {
                // If the tag exists, get its term ID
                $tags_ids[] = $tag_exists->term_id;
            }
        }
    
        // Validate required fields
        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', 'Please enter a title.', [ 'status' => 400 ] );
        }
        if ( empty( $content ) ) {
            return new WP_Error( 'missing_content', 'Please enter content.', [ 'status' => 400 ] );
        }
    
        // Prepare the post array
        $postarr = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status'  => $post_status,
            'post_type'    => $post_type,
            'post_author'  => $author_id,
        ];
    
        // Insert the post into the database
        $post_id = wp_insert_post( $postarr );
        if ( $post_id ) {
            // Set category and tags for the post
            wp_set_object_terms( $post_id, $cat_id, $category_main );
            wp_set_object_terms( $post_id, $tags_ids, $tags_main );
    
            // Handle the post image if provided
            if ( $image_url ) {
                $image_id = COPYREAP_REST_API_Image::copyreap_handle_image( $image_url, $post_id );
                if ( $image_id ) {
                    set_post_thumbnail( $post_id, $image_id );
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
    public function copyreap_update_post( $data ) {
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
                $image_id = copyreap_handle_image($image_url, $updated_post_id);
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
    public function copyreap_delete_post( $data ) {
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
    public function copyreap_get_categories() {
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
    public function copyreap_get_tags() {
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
    public function copyreap_get_post_types() {
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
    public function copyreap_get_taxonomy_by_post_type( $data ) {
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
    
        $response = array();
    
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
                    // hierarchical taxonomies go to 'category'
                    $response['categories'][$taxonomy->name] = $term_data; 
                } else {
                    // Non-hierarchical taxonomies go to 'tags'
                    $response['tags'][$taxonomy->name] = $term_data; // You may want to collect all tags in a 'tags' array
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

    // Fetch the list of Dante authors
    public function copyreap_get_dante_users() {
        // Query all users without filtering by meta_key
        $args = [
            'fields' => ['ID', 'user_login', 'user_email'],  // Specify the fields you want to return
        ];
    
        $users = get_users($args);
    
        // Remove duplicates by user ID if needed
        $unique_users = [];
        foreach ($users as $user) {
            $unique_users[$user->ID] = $user;
        }
        $users = array_values($unique_users); // Reindex the array
    
        // Prepare the response
        $response = [];
        foreach ($users as $user) {
            $response[] = [
                'id'    => $user->ID,
                'login' => $user->user_login,
                'email' => $user->user_email,
            ];
        }
    
        return new WP_REST_Response(
            array(
                'message' => 'All User List retrieved successfully.',
                'status'  => '200',
                'data'    => $response,
            ),
            200
        );
    }    

}

new COPYREAP_REST_API_Endpoints();