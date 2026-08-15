<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class LicenseGenerator {

    private $private_key;

    /**
     * Constructor để khởi tạo private key.
     *
     * @param string $private_key Private key để mã hóa license.
     */
    public function __construct( $private_key ) {
        $this->private_key = $private_key;
    }

    /**
     * Tạo license key bằng private key.
     *
     * @param array $data Dữ liệu license.
     * @return string License key đã được mã hóa.
     * @throws \Exception Nếu mã hóa thất bại.
     */
    public function generateLicense( array $data ): string {
        // Chuyển dữ liệu thành JSON
        $json_data = json_encode( $data );
        if ( $json_data === false ) {
            throw new Exception( 'Failed to encode license data to JSON.' );
        }

        // Mã hóa dữ liệu bằng private key
        $encrypted_data = '';
        $result = openssl_private_encrypt( $json_data, $encrypted_data, $this->private_key );

        if ( ! $result ) {
            throw new Exception( 'Failed to encrypt license key.' );
        }

        // Chuyển dữ liệu mã hóa sang Base64 để dễ lưu trữ
        return base64_encode( $encrypted_data );
    }
}