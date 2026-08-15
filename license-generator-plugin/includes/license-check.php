<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Giải mã và kiểm tra license key bằng public key.
 *
 * @param string $license_key License key được mã hóa.
 * @return bool|string Trả về true nếu hợp lệ, hoặc thông báo lỗi nếu không hợp lệ.
 */
function pip_verify_license_genenerator( $license_key ) {
    $public_key = PIP_PUBLIC_KEY;

    // Giải mã license key từ base64
    $decoded_license = base64_decode( $license_key );

    // Tạo biến để lưu dữ liệu giải mã
    $decrypted_data = '';

    // Giải mã license key bằng public key
    $result = openssl_public_decrypt( $decoded_license, $decrypted_data, $public_key );

    if ( $result ) {
        $license_data = json_decode( $decrypted_data, true );

        if ( ! $license_data ) {
            return __( 'Invalid license data.', 'license-generator-plugin' );
        }

        $current_domain = $_SERVER['HTTP_HOST'];
        $current_ip = $_SERVER['SERVER_ADDR'];

        if ( $license_data['domain'] !== $current_domain ) {
            return __( 'License is not valid for this domain.', 'license-generator-plugin' );
        }

        if ( $license_data['ip'] !== $current_ip ) {
            return __( 'License is not valid for this IP address.', 'license-generator-plugin' );
        }

        if ( strtotime( $license_data['expiry'] ) < time() ) {
            return __( 'License has expired.', 'license-generator-plugin' );
        }

        return true; // License hợp lệ
    }

    return __( 'Failed to verify license.', 'license-generator-plugin' );
}