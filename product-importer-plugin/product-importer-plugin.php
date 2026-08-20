<?php
/**
 * Plugin Name: Product Importer Plugin
 * Description: A plugin to import products into WooCommerce with an easy-to-use interface.
 * Version: 1.5.6
 * Author: NamDD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define constants
define( 'PIP_VERSION', '1.5.6' );
define( 'PIP_DIR', plugin_dir_path( __FILE__ ) );
define( 'PIP_URL', plugin_dir_url( __FILE__ ) );
define( 'PIP_PUBLIC_KEY', <<<EOD
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuXu3qbW7j0CHBGE/xDA9
fvvpcqLWYv0hwVeL3Zm9/BuzuRMvmIt3DoBRPJUKGXd60vr0P69AsFZIOzc9uqz5
XVchBPgwZrpgBYoYCCGDixsk+DIdjRBaubs+5oYeuMl96EbQYpmk5rfmBU3jJtT7
20wQcsLlfEudBUzq7vIAHcVMd3NRxUI7anUsKqlA6ulr6yop1helIlGr79iAhaik
gjDJqsD++RQJ1UbHRPi4q3IdtN/GTp7foppSq0EaZXkvB+0pjOsEeaJ8hNUAHuu8
pKr2wn9OTE7IT1bISA1P0VMDaV6etlXucujRdZNWGKPDhnT8unP8Q+Zva3ORZ5NN
1QIDAQAB
-----END PUBLIC KEY-----
EOD
);

// Include Composer autoloader
if (file_exists(PIP_DIR . 'vendor/autoload.php')) {
    require_once PIP_DIR . 'vendor/autoload.php';
}

// Include necessary files
require_once PIP_DIR . 'includes/admin-ui.php';
require_once PIP_DIR . 'includes/product-importer.php';
require_once PIP_DIR . 'includes/helpers.php';
require_once PIP_DIR . 'includes/license-check.php';
require_once PIP_DIR . 'includes/excel-template-generator.php';
require_once PIP_DIR . 'includes/excel-importer.php';

// Register admin menu
add_action( 'admin_menu', 'pip_register_admin_menu' );

function pip_register_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=product',
        'Import Products',
        'Import Products',
        'manage_options',
        'product-importer',
        'pip_render_admin_ui'
    );
}

// Register license menu in Settings
add_action( 'admin_menu', 'pip_register_license_menu' );

function pip_register_license_menu() {
    add_options_page(
        __( 'Plugin License', 'product-importer-plugin' ), // Page title
        __( 'Plugin License', 'product-importer-plugin' ), // Menu title
        'manage_options',                                  // Capability
        'pip-license',                                    // Menu slug
        'pip_render_license_page'                         // Callback function
    );
}

// Register "Add License" menu in Admin Menu even if plugin is not active
add_action( 'admin_menu', 'pip_register_add_license_menu', 0 );

function pip_register_add_license_menu() {
    add_menu_page(
        __( 'Add License PIP', 'product-importer-plugin' ), // Page title
        __( 'Add License PIP', 'product-importer-plugin' ), // Menu title
        'manage_options',                               // Capability
        'pip-add-license',                              // Menu slug
        'pip_render_license_page',                     // Callback function
        'dashicons-admin-network',                     // Icon
        80                                              // Position
    );
}

function pip_render_license_page() {
    // Kiểm tra quyền truy cập
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Xử lý khi người dùng gửi form
    if ( isset( $_POST['pip_license_key'] ) ) {
        check_admin_referer( 'pip_license_nonce' ); // Kiểm tra nonce để bảo mật

        $license_key = sanitize_text_field( $_POST['pip_license_key'] );

        // Lưu license key vào cơ sở dữ liệu
        update_option( 'pip_license_key', $license_key );

        echo '<div class="updated"><p>' . __( 'License key saved successfully.', 'product-importer-plugin' ) . '</p></div>';
    }

    // Lấy license key hiện tại từ cơ sở dữ liệu
    $license_key = get_option( 'pip_license_key', '' );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Add License', 'product-importer-plugin' ); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'pip_license_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="pip_license_key"><?php esc_html_e( 'License Key', 'product-importer-plugin' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="pip_license_key" id="pip_license_key" value="<?php echo esc_attr( $license_key ); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Save License Key', 'product-importer-plugin' ) ); ?>
        </form>
    </div>
    <?php
}

// Enqueue scripts and styles
add_action( 'admin_enqueue_scripts', 'pip_enqueue_scripts' );

function pip_enqueue_scripts() {
    wp_enqueue_style( 'pip-styles', PIP_URL . 'assets/css/styles.css', array(), PIP_VERSION );
    wp_enqueue_script( 'pip-scripts', PIP_URL . 'assets/js/scripts.js', array( 'jquery' ), PIP_VERSION, true );
    wp_localize_script( 'pip-scripts', 'ajax_object', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'product_importer_nonce' ), // Tạo nonce
    ) );
}

?>