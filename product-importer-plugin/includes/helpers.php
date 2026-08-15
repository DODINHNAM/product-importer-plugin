<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Helper function to create a product
 */
function pip_create_product( $product_data ) {
    $product_id = wp_insert_post( array(
        'post_title'   => $product_data['title'],
        'post_content' => $product_data['content'],
        'post_status'  => 'publish',
        'post_type'    => 'product',
    ) );

    return ! is_wp_error( $product_id ) ? $product_id : new WP_Error( 'product_creation_error', __( 'Failed to create product.', 'product-importer' ) );
}

/**
 * Helper function to upload a file
 */
function pip_upload_file( $file ) {
    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
    }

    $upload_overrides = array( 'test_form' => false );
    $moved_file = wp_handle_upload( $file, $upload_overrides );

    if ( $moved_file && ! isset( $moved_file['error'] ) ) {
        return $moved_file['url'];
    } else {
        return new WP_Error( 'upload_error', __( 'Failed to upload file.', 'product-importer' ) );
    }
}
?>