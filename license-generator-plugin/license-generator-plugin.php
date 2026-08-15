<?php
/**
 * Plugin Name: License Generator Plugin
 * Description: A plugin to generate and verify license keys using private and public keys.
 * Version: 1.0.0
 * Author: NamDD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Include necessary files
require_once plugin_dir_path( __FILE__ ) . 'includes/LicenseGenerator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/license-check.php';

// Add menu page
add_action( 'admin_menu', 'lgp_add_admin_menu' );

/**
 * Thêm menu vào WordPress Admin.
 */
function lgp_add_admin_menu() {
    add_menu_page(
        'License Generator', // Page title
        'License Generator', // Menu title
        'manage_options',    // Capability
        'license-generator', // Menu slug
        'lgp_render_admin_page', // Callback function
        'dashicons-admin-tools', // Icon
        100                   // Position
    );
}

/**
 * Render giao diện admin để sinh và kiểm tra license.
 */
function lgp_render_admin_page() {
    // Xử lý form khi submit
    $generated_license_key = ''; // Biến để lưu license key đã tạo

    if ( isset( $_POST['lgp_generate_license'] ) ) {
        $private_key = sanitize_textarea_field( $_POST['private_key'] );
        $data        = json_decode( stripslashes( $_POST['data'] ), true );

        if ( empty( $private_key ) || empty( $data ) ) {
            $error_message = 'Private key and data are required.';
        } else {
            try {
                $generator = new LicenseGenerator( $private_key );
                $generated_license_key = $generator->generateLicense( $data );
                $success_message = 'Generated License Key successfully!';
            } catch ( Exception $e ) {
                $error_message = 'Error: ' . esc_html( $e->getMessage() );
            }
        }
    }

    if ( isset( $_POST['lgp_verify_license'] ) ) {
        $public_key  = sanitize_textarea_field( $_POST['public_key'] );
        $license_key = sanitize_textarea_field( $_POST['license_key'] );

        if ( empty( $public_key ) || empty( $license_key ) ) {
            $error_message = 'Public key and license key are required.';
        } else {
            try {
                define( 'PIP_PUBLIC_KEY', $public_key );
                $result = pip_verify_license_genenerator( $license_key );
                if ( $result === true ) {
                    $success_message = 'License is valid!';
                } else {
                    $error_message = $result;
                }
            } catch ( Exception $e ) {
                $error_message = 'Error: ' . esc_html( $e->getMessage() );
            }
        }
    }

    ?>
    <div class="wrap">
        <h1>License Generator</h1>
        <?php if ( isset( $error_message ) ) : ?>
            <div class="notice notice-error"><p><?php echo $error_message; ?></p></div>
        <?php endif; ?>
        <?php if ( isset( $success_message ) ) : ?>
            <div class="notice notice-success"><p><?php echo $success_message; ?></p></div>
        <?php endif; ?>
        <form method="post">
            <h2>Generate License</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="private_key">Private Key</label>
                    </th>
                    <td>
                        <textarea name="private_key" id="private_key" rows="10" cols="50" class="large-text" required><?php echo isset( $_POST['private_key'] ) ? esc_textarea( $_POST['private_key'] ) : ''; ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="data">Data (JSON format)</label>
                    </th>
                    <td>
                        <textarea name="data" id="data" rows="10" cols="50" class="large-text" required><?php echo isset( $_POST['data'] ) ? esc_textarea( $_POST['data'] ) : ''; ?></textarea>
                        <p class="description">Enter data in JSON format, e.g., {"domain":"example.com","ip":"127.0.0.1","expiry":"2025-12-31"}</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="generated_license_key">Generated License Key</label>
                    </th>
                    <td>
                        <textarea name="generated_license_key" id="generated_license_key" rows="5" cols="50" class="large-text" readonly><?php echo esc_textarea( $generated_license_key ); ?></textarea>
                        <p class="description">This is the generated license key. Copy it for use.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Generate License', 'primary', 'lgp_generate_license' ); ?>
        </form>

        <hr>

        <form method="post">
            <h2>Verify License</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="public_key">Public Key</label>
                    </th>
                    <td>
                        <textarea name="public_key" id="public_key" rows="10" cols="50" class="large-text" required><?php echo isset( $_POST['public_key'] ) ? esc_textarea( $_POST['public_key'] ) : ''; ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="license_key">License Key</label>
                    </th>
                    <td>
                        <textarea name="license_key" id="license_key" rows="10" cols="50" class="large-text" required><?php echo isset( $_POST['license_key'] ) ? esc_textarea( $_POST['license_key'] ) : ''; ?></textarea>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Verify License', 'primary', 'lgp_verify_license' ); ?>
        </form>
    </div>
    <?php
}