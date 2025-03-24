<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class COPYPRESS_REST_API_Menu {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'copypress_add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'copypress_admin_enqueue_scripts' ] ); // Enqueue styles
    }

    // Enqueue CSS styles
    public function copypress_admin_enqueue_scripts() {
        if (isset($_GET['page']) && $_GET['page'] === 'copypress-rest-api') {
            if (isset($_GET['_wpnonce'])) {
                $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'])); // Unslash and sanitize nonce

                if (wp_verify_nonce($nonce, 'copypress_admin_action')) {
                    wp_enqueue_style('copypress-rest-api', plugin_dir_url(__FILE__) . '../assets/css/copypress-rest-api.css', [], '1.0.0');
                } else {
                    wp_die(esc_html__('Nonce verification failed.', 'copypress-rest-api'));
                }
            }
        }
    }

    // Add the menu page to the WordPress admin
    public function copypress_add_admin_menu() {
        $menu_slug = 'copypress-rest-api';
        $menu_url  = add_query_arg('_wpnonce', wp_create_nonce('copypress_admin_action'), admin_url('admin.php?page=' . $menu_slug));

        add_menu_page(
            esc_html__('CP Rest API', 'copypress-rest-api'),
            esc_html__('CP Rest API', 'copypress-rest-api'),
            'manage_options',
            $menu_slug,
            [ $this, 'copypress_admin_page' ],
            'dashicons-admin-tools',
            60
        );
    }

    // Display the admin page
    public function copypress_admin_page() {
        ?>
        <div class="wrap cp-main">
            <h2><?php esc_html_e('CopyPress REST API Integration', 'copypress-rest-api'); ?></h2>
            <p><?php esc_html_e( 'Generate a new API key or view the current API key for integration purposes.', 'copypress-rest-api' ); ?></p>
    
            <?php
            // Handle API key generation logic with nonce verification
            if ( isset( $_POST['generate_api_key'] ) ) {
                // Check if nonce field exists, and verify it
                if ( isset( $_POST['copypress_api_key_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['copypress_api_key_nonce'] ) ), 'copypress_generate_api_key' ) ) {
                    // If nonce is verified, proceed with JWT token generation
    
                    if (is_user_logged_in()) {
                        // Get the current logged-in user
                        $user = wp_get_current_user();
    
                        // Generate the JWT token
                        $jwt = new JWT_Token();
                        $jwt_token = $jwt->generate_token($user);
    
                        // Update the option in the WordPress database
                        update_option( 'copypress_rest_api_integration_key', $jwt_token );
    
                        // Display success message
                        echo '<div class="updated"><p><strong>' . esc_html('JWT Token generated successfully!', 'copypress-rest-api') . '</strong></p></div>';
                    } else {
                        echo '<div class="error"><p><strong>' . esc_html_e('You must be logged in to generate a JWT token.', 'copypress-rest-api') . '</strong></p></div>';
                    }
                } else {
                    // Nonce verification failed
                    echo '<div class="error"><p><strong>' . esc_html_e('Nonce verification failed. Please try again.', 'copypress-rest-api') . '</strong></p></div>';
                }
            }
            ?>
            <form method="post" action="" style="max-width: 600px; padding: 20px; background-color: #f9f9f9; border-radius: 5px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="copypress_api_key"><?php echo  esc_html__( 'API Key', 'copypress-rest-api' ); ?></label></th>
                        <td>
                            <input type="text" name="copypress_api_key" id="copypress_api_key" class="regular-text" value="<?php echo esc_html( get_option( 'copypress_rest_api_integration_key' ) ); ?>" readonly />
                            <p class="description"><?php esc_html_e( 'This is the generated API key. You can use it for the integration.', 'copypress-rest-api' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <!-- Add nonce field for security -->
                <?php wp_nonce_field( 'copypress_generate_api_key', 'copypress_api_key_nonce' ); ?>
    
                <p class="submit">
                    <?php 
                    // Only show the button if the API key doesn't exist
                    if ( ! get_option( 'copypress_rest_api_integration_key' ) ) {
                        echo '<input type="submit" name="generate_api_key" class="button button-primary" value="' . esc_attr( 'Generate API Key' ) . '" />';
                    }
                    ?>
                </p>
            </form>
        </div>
        <?php
    }    
}

new COPYPRESS_REST_API_Menu();
