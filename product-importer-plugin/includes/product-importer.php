<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Product_Importer {

    public function __construct() {
        add_action( 'admin_post_import_products', array( $this, 'import_products' ) );
        add_action( 'wp_ajax_import_products', array( $this, 'import_products' ) ); // Đăng ký action AJAX
        add_action( 'wp_ajax_import_products_with_taxonomies', array( $this, 'import_products_with_taxonomies' ) );
    }

    public function import_products() {
        // Kiểm tra quyền truy cập
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have sufficient permissions to access this page.'));
        }

        // Kiểm tra nonce để bảo mật
        // if (!check_ajax_referer('product_importer_nonce', '_wpnonce', false)) {
        //     wp_send_json_error(array('message' => 'Invalid nonce. Please refresh the page and try again.'));
        // }

        // Validate required fields
        if (empty($_POST['product_name'])) {
            wp_send_json_error(array('message' => 'Product name is required.'));
        }

        // Lấy dữ liệu từ form
        $product_name = sanitize_text_field($_POST['product_name']);
        $product_description = isset($_POST['product_description']) ? wp_kses_post($_POST['product_description']) : '';
        // Thêm product_name vào product_description nếu là manual upload
        $product_description = '<h2>' . esc_html($product_name) . '</h2>' . "\n\n" . $product_description;
        $original_price = isset($_POST['original_price']) ? floatval($_POST['original_price']) : 0;
        $sale_price = isset($_POST['sale_price']) ? floatval($_POST['sale_price']) : 0;
        $product_category_ids = isset($_POST['product_category']) ? array_values(array_filter(array_map('intval', (array) $_POST['product_category']))) : array();
        $product_tag_ids = isset($_POST['product_tag']) ? array_values(array_filter(array_map('intval', (array) $_POST['product_tag']))) : array();
        $product_type = isset($_POST['product_type']) ? sanitize_text_field($_POST['product_type']) : 'simple';
        $product_attributes = isset($_POST['product_attributes']) ? json_decode(stripslashes($_POST['product_attributes']), true) : array();
        $variant_prices = isset($_POST['variant_prices']) ? json_decode(stripslashes($_POST['variant_prices']), true) : array();

        // Validate prices
        if ($original_price <= 0) {
            wp_send_json_error(array('message' => 'Original price must be greater than 0.'));
        }

        if ($sale_price > 0 && $sale_price >= $original_price) {
            wp_send_json_error(array('message' => 'Sale price must be less than original price.'));
        }

        // Validate category
        if (empty($product_category_ids)) {
            wp_send_json_error(array('message' => 'Please select at least one product category.'));
        }

        // Validate attributes for variant products
        if ($product_type === 'variable' && empty($product_attributes)) {
            wp_send_json_error(array('message' => 'At least one attribute with values is required for variant products.'));
        }

        // Kiểm tra file ảnh đại diện
        $product_image_id = null;
        if (isset($_FILES['product_image']) && is_array($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $file = array(
                'name'     => $_FILES['product_image']['name'],
                'type'     => $_FILES['product_image']['type'],
                'tmp_name' => $_FILES['product_image']['tmp_name'],
                'error'    => $_FILES['product_image']['error'],
                'size'     => $_FILES['product_image']['size'],
            );
            $product_image_id = $this->upload_file($file);
            if (is_wp_error($product_image_id)) {
                wp_send_json_error(array('message' => 'Error uploading product image: ' . $product_image_id->get_error_message()));
            }
        }

        // Kiểm tra file thư viện ảnh
        $gallery_ids = array();
        if (!empty($_FILES['product_gallery'])) {
            foreach ($_FILES['product_gallery']['name'] as $key => $value) {
                if ($_FILES['product_gallery']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = array(
                        'name'     => $_FILES['product_gallery']['name'][$key],
                        'type'     => $_FILES['product_gallery']['type'][$key],
                        'tmp_name' => $_FILES['product_gallery']['tmp_name'][$key],
                        'error'    => $_FILES['product_gallery']['error'][$key],
                        'size'     => $_FILES['product_gallery']['size'][$key],
                    );
                    $gallery_id = $this->upload_file($file);
                    if (!is_wp_error($gallery_id)) {
                        $gallery_ids[] = $gallery_id;
                    }
                }
            }
        }

        // Ảnh được chọn từ Media Library, thêm trực tiếp vào Gallery Images (không cần upload lại)
        if (!empty($_POST['media_gallery_ids']) && is_array($_POST['media_gallery_ids'])) {
            foreach ($_POST['media_gallery_ids'] as $media_id) {
                $media_id = intval($media_id);
                if ($media_id > 0 && get_post_type($media_id) === 'attachment' && wp_attachment_is_image($media_id)) {
                    $gallery_ids[] = $media_id;
                }
            }
            $gallery_ids = array_values(array_unique($gallery_ids));
        }

        // Tạo sản phẩm WooCommerce
        $product_id = wp_insert_post(array(
            'post_title'    => $product_name,
            'post_content'  => $product_description,
            'post_status'   => 'publish',
            'post_type'     => 'product',
        ));

        if (is_wp_error($product_id)) {
            wp_send_json_error(array('message' => 'Failed to create product: ' . $product_id->get_error_message()));
        }

        // --- Thêm đoạn này ---
        if ($product_image_id) {
            set_post_thumbnail($product_id, $product_image_id);
        }
        if (!empty($gallery_ids)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
        }
        wp_set_object_terms($product_id, $product_category_ids, 'product_cat');
        if (!empty($product_tag_ids)) {
            wp_set_object_terms($product_id, $product_tag_ids, 'product_tag', false);
        }
        // --- Kết thúc thêm ---

        // Gán giá và danh mục
        update_post_meta($product_id, '_regular_price', $original_price);
        if ($sale_price > 0) {
            update_post_meta($product_id, '_sale_price', $sale_price);
            update_post_meta($product_id, '_price', $sale_price);
        } else {
            update_post_meta($product_id, '_price', $original_price);
        }


        update_post_meta($product_id, '_manage_stock', 'no');
        update_post_meta($product_id, '_stock', ''); // Empty stock for variable products
        update_post_meta($product_id, '_backorders', 'no'); // No backorders
        // Set stock status to "In Stock"
        update_post_meta($product_id, '_stock_status', 'instock');
        
        // Set flag để force instock chỉ cho VARIABLE products
        if ($product_type === 'variable') {
            update_post_meta($product_id, '_pip_force_instock', 'yes');
            error_log("[PIP] Set _pip_force_instock flag for VARIABLE product {$product_id}");
        }
        
        // Force refresh cache
        clean_post_cache($product_id);
        wp_cache_delete($product_id, 'posts');
        
        // Sử dụng WooCommerce object để set stock status
        $product = wc_get_product($product_id);
        if ($product) {
            $product->set_stock_status('instock');
            $product->set_manage_stock(false);
            wc_update_product_stock($product_id, '', 'set');
            $product->set_backorders('no');
            $product->save();
            error_log("Final stock status set for product {$product_id}: instock");
            
            // Kiểm tra stock status sau khi set
            $final_stock_status = get_post_meta($product_id, '_stock_status', true);
            error_log("Final stock status in database for product {$product_id}: {$final_stock_status}");
        }

        // Final check và force set stock status một lần nữa - chỉ cho variable products
        if ($product_type === 'variable') {
            wp_schedule_single_event(time() + 2, 'pip_final_stock_status_check', array($product_id));
        }

            wp_send_json_success(array(
                'message' => 'Product imported successfully!',
                'product_id' => $product_id,
                'edit_link' => get_edit_post_link($product_id)
            ));
    }

    public function import_products_with_taxonomies() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have sufficient permissions to access this page.' ) );
        }

        if ( empty( $_POST['product_name'] ) ) {
            wp_send_json_error( array( 'message' => 'Product name is required.' ) );
        }

        $product_name = sanitize_text_field( $_POST['product_name'] );
        $product_description = isset( $_POST['product_description'] ) ? wp_kses_post( $_POST['product_description'] ) : '';
        $product_description = '<h2>' . esc_html( $product_name ) . '</h2>' . "\n\n" . $product_description;
        $original_price = isset( $_POST['original_price'] ) ? floatval( $_POST['original_price'] ) : 0;
        $sale_price = isset( $_POST['sale_price'] ) ? floatval( $_POST['sale_price'] ) : 0;
        $product_category_ids = isset( $_POST['product_category_ids'] ) ? array_values( array_filter( array_map( 'intval', (array) $_POST['product_category_ids'] ) ) ) : array();
        $product_tag_ids = isset( $_POST['product_tag_ids'] ) ? array_values( array_filter( array_map( 'intval', (array) $_POST['product_tag_ids'] ) ) ) : array();
        $product_brand_ids = isset( $_POST['product_brand_ids'] ) ? array_values( array_filter( array_map( 'intval', (array) $_POST['product_brand_ids'] ) ) ) : array();

        if ( $original_price <= 0 ) {
            wp_send_json_error( array( 'message' => 'Original price must be greater than 0.' ) );
        }

        if ( $sale_price > 0 && $sale_price >= $original_price ) {
            wp_send_json_error( array( 'message' => 'Sale price must be less than original price.' ) );
        }

        if ( empty( $product_category_ids ) ) {
            wp_send_json_error( array( 'message' => 'Please select at least one product category.' ) );
        }

        $product_image_id = null;
        if ( isset( $_FILES['product_image'] ) && is_array( $_FILES['product_image'] ) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK ) {
            $file = array(
                'name'     => $_FILES['product_image']['name'],
                'type'     => $_FILES['product_image']['type'],
                'tmp_name' => $_FILES['product_image']['tmp_name'],
                'error'    => $_FILES['product_image']['error'],
                'size'     => $_FILES['product_image']['size'],
            );
            $product_image_id = $this->upload_file( $file );
            if ( is_wp_error( $product_image_id ) ) {
                wp_send_json_error( array( 'message' => 'Error uploading product image: ' . $product_image_id->get_error_message() ) );
            }
        }

        $gallery_ids = array();
        if ( ! empty( $_FILES['product_gallery'] ) ) {
            foreach ( $_FILES['product_gallery']['name'] as $key => $value ) {
                if ( $_FILES['product_gallery']['error'][ $key ] === UPLOAD_ERR_OK ) {
                    $file = array(
                        'name'     => $_FILES['product_gallery']['name'][ $key ],
                        'type'     => $_FILES['product_gallery']['type'][ $key ],
                        'tmp_name' => $_FILES['product_gallery']['tmp_name'][ $key ],
                        'error'    => $_FILES['product_gallery']['error'][ $key ],
                        'size'     => $_FILES['product_gallery']['size'][ $key ],
                    );
                    $gallery_id = $this->upload_file( $file );
                    if ( ! is_wp_error( $gallery_id ) ) {
                        $gallery_ids[] = $gallery_id;
                    }
                }
            }
        }

        $product_id = wp_insert_post( array(
            'post_title'   => $product_name,
            'post_content' => $product_description,
            'post_status'  => 'publish',
            'post_type'    => 'product',
        ) );

        if ( is_wp_error( $product_id ) ) {
            wp_send_json_error( array( 'message' => 'Failed to create product: ' . $product_id->get_error_message() ) );
        }

        if ( $product_image_id ) {
            set_post_thumbnail( $product_id, $product_image_id );
        }

        if ( ! empty( $gallery_ids ) ) {
            update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
        }

        wp_set_object_terms( $product_id, $product_category_ids, 'product_cat' );

        if ( ! empty( $product_tag_ids ) ) {
            wp_set_object_terms( $product_id, $product_tag_ids, 'product_tag', false );
        }

        $brand_taxonomy = $this->get_brand_taxonomy();
        if ( $brand_taxonomy && ! empty( $product_brand_ids ) ) {
            wp_set_object_terms( $product_id, $product_brand_ids, $brand_taxonomy, false );
        }

        update_post_meta( $product_id, '_regular_price', $original_price );
        if ( $sale_price > 0 ) {
            update_post_meta( $product_id, '_sale_price', $sale_price );
            update_post_meta( $product_id, '_price', $sale_price );
        } else {
            update_post_meta( $product_id, '_price', $original_price );
        }

        update_post_meta( $product_id, '_manage_stock', 'no' );
        update_post_meta( $product_id, '_stock', '' );
        update_post_meta( $product_id, '_backorders', 'no' );
        update_post_meta( $product_id, '_stock_status', 'instock' );
        wp_set_object_terms( $product_id, 'simple', 'product_type' );
        update_post_meta( $product_id, '_product_type', 'simple' );

        clean_post_cache( $product_id );
        wp_cache_delete( $product_id, 'posts' );

        $product = wc_get_product( $product_id );
        if ( $product ) {
            $product->set_stock_status( 'instock' );
            $product->set_manage_stock( false );
            wc_update_product_stock( $product_id, '', 'set' );
            $product->set_backorders( 'no' );
            $product->save();
        }

        wp_send_json_success( array(
            'message'    => 'Product imported successfully!',
            'product_id' => $product_id,
            'edit_link'  => get_edit_post_link( $product_id ),
        ) );
    }

    private function get_brand_taxonomy() {
        foreach ( array( 'brand', 'product_brand', 'pa_brand' ) as $taxonomy ) {
            if ( taxonomy_exists( $taxonomy ) ) {
                return $taxonomy;
            }
        }

        return null;
    }

    // Hàm upload file
    private function upload_file( $file ) {

        // Kiểm tra xem $file có phải là một mảng hợp lệ không
        if ( ! is_array( $file ) || ! isset( $file['name'], $file['type'], $file['tmp_name'], $file['error'], $file['size'] ) ) {
            error_log( 'Invalid file format: ' . print_r( $file, true ) );
            return null;
        }

        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        // Tạo một mảng giả lập $_FILES để truyền vào media_handle_sideload
        $file_array = array(
            'name'     => $file['name'],
            'type'     => $file['type'],
            'tmp_name' => $file['tmp_name'],
            'error'    => $file['error'],
            'size'     => $file['size'],
        );

        // Xử lý upload file
        $attachment_id = media_handle_sideload( $file_array, 0 );

        if ( is_wp_error( $attachment_id ) ) {
            error_log( 'File upload error: ' . $attachment_id->get_error_message() );
            return null;
        }

        return $attachment_id;
    }

    // Create product variants for variable products
    private function create_product_variants($product_id, $attributes, $original_price, $sale_price, $variant_prices) {
        try {
            error_log("[PIP] Start creating variants for product ID: {$product_id}");
            
            // ✅ Đặt product type thành variable ngay từ đầu
            wp_set_object_terms($product_id, 'variable', 'product_type');
            update_post_meta($product_id, '_product_type', 'variable');
            
            // 1. Tạo attributes từ form data (không đọc từ DB)
            $woo_attributes = array();
            $attribute_values = array();
            
            foreach ($attributes as $index => $attribute) {
                $taxonomy = sanitize_text_field($attribute['taxonomy']);
                $values = array();
                
                // Ensure taxonomy exists
                if (!taxonomy_exists($taxonomy)) {
                    error_log("Taxonomy {$taxonomy} does not exist, creating it...");
                    $this->create_attribute_taxonomy($taxonomy);
                }
                
                // Collect attribute values for combinations
                foreach ($attribute['values'] as $value) {
                    $term_name = sanitize_text_field($value['name']);
                    $values[] = $term_name; // Sử dụng name đã được sanitize
                    $attribute_values[$taxonomy] = $values;
                }
                
                // Create WooCommerce attribute format - chỉ sử dụng name, không lưu ID
                $woo_attributes[$taxonomy] = array(
                    'name' => $taxonomy,
                    'value' => '',
                    'position' => $index,
                    'is_visible' => 1,
                    'is_variation' => 1,
                    'is_taxonomy' => 1
                );
            }
            
            // Update product attributes meta
            update_post_meta($product_id, '_product_attributes', $woo_attributes);
            
            // Log để debug
            error_log("Updated _product_attributes meta: " . print_r($woo_attributes, true));
            
            // 1.5. Set attribute terms cho product chính để tạo term relationships
            foreach ($attributes as $attribute) {
                $taxonomy = sanitize_text_field($attribute['taxonomy']);
                $term_names = array();
                
                foreach ($attribute['values'] as $value) {
                    $term_name = sanitize_text_field($value['name']);
                    $term_names[] = $term_name;
                    
                    // Tìm term bằng name
                    $term = get_term_by('name', $term_name, $taxonomy);
                    if (!$term) {
                        // Tạo term mới nếu chưa tồn tại
                        $term = wp_insert_term($term_name, $taxonomy);
                        if (is_wp_error($term)) {
                            error_log("Error creating term '{$term_name}' for taxonomy '{$taxonomy}': " . $term->get_error_message());
                            continue;
                        }
                        error_log("Created new term '{$term_name}' with ID: {$term['term_id']} for taxonomy '{$taxonomy}'");
                    }
                }
                
                if (!empty($term_names)) {
                    // Set terms cho product chính - chỉ sử dụng name, không lưu ID
                    $terms_result = wp_set_object_terms($product_id, $term_names, $taxonomy);
                    if (is_wp_error($terms_result)) {
                        error_log("Error setting terms for product {$product_id} - {$taxonomy}: " . $terms_result->get_error_message());
                    } else {
                        error_log("Successfully set terms for product {$product_id} - {$taxonomy}: " . print_r($terms_result, true));
                    }
                }
            }
            
            // 2. Tạo combinations sử dụng cartesian_product
            $combinations = $this->cartesian_product($attribute_values);
            error_log("Số variation sẽ tạo: " . count($combinations));
            
            // 3. Xoá variation cũ nếu có
            $existing = get_children([
                'post_parent' => $product_id,
                'post_type' => 'product_variation',
                'post_status' => 'any',
            ]);
            foreach ($existing as $v) {
                wp_delete_post($v->ID, true);
                error_log("Xoá variation cũ ID: {$v->ID}");
            }
            
            // 4. Tạo variants mới
            $default_attributes = array();
            $created_variant_ids = array();
            
            // Log để debug combinations
            error_log("Combinations to create: " . print_r($combinations, true));
            
            foreach ($combinations as $index => $combo) {
                $meta_input = array();
                
                // Set variant attributes - sử dụng slug thay vì name
                foreach ($combo as $taxonomy => $term_value) {
                    $term_slug = ''; // Khởi tạo slug
                    
                    // Tìm term bằng name để lấy slug
                    $term = get_term_by('name', $term_value, $taxonomy);
                    if (!$term) {
                        // Tạo term mới nếu chưa tồn tại
                        $term = wp_insert_term($term_value, $taxonomy);
                        if (is_wp_error($term)) {
                            error_log("Error creating term '{$term_value}' for variant {$index} - {$taxonomy}: " . $term->get_error_message());
                            // Fallback: sử dụng sanitize_title để tạo slug
                            $term_slug = sanitize_title($term_value);
                            error_log("Fallback: Using sanitized slug for {$taxonomy}: {$term_value} -> {$term_slug}");
                        } else {
                            // Term được tạo thành công
                            $term_slug = $term['slug'];
                            error_log("Created new term '{$term_value}' with slug: {$term_slug}");
                        }
                    } else {
                        // Term đã tồn tại, lấy slug
                        $term_slug = $term->slug;
                        error_log("Found existing term '{$term_value}' with slug: {$term_slug}");
                    }
                    
                    // Đảm bảo luôn có slug
                    if (empty($term_slug)) {
                        $term_slug = sanitize_title($term_value);
                        error_log("Final fallback: Using sanitized slug for {$taxonomy}: {$term_value} -> {$term_slug}");
                    }
                    
                    // Lưu slug vào variant meta
                    $meta_input['attribute_' . $taxonomy] = $term_slug;
                    error_log("Set variant attribute {$taxonomy}: {$term_value} -> {$term_slug}");
                }
                
                // Set variant prices từ Generated Variants Preview
                $variant_original_price = $original_price;
                $variant_sale_price = $sale_price;
                
                if (isset($variant_prices[$index])) {
                    $custom_prices = $variant_prices[$index];
                    if (isset($custom_prices['original_price']) && $custom_prices['original_price'] > 0) {
                        $variant_original_price = floatval($custom_prices['original_price']);
                    }
                    if (isset($custom_prices['sale_price']) && $custom_prices['sale_price'] > 0) {
                        $variant_sale_price = floatval($custom_prices['sale_price']);
                    }
                }
                
                $meta_input['_regular_price'] = $variant_original_price;
                $meta_input['_price'] = $variant_original_price;
                if ($variant_sale_price > 0 && $variant_sale_price < $variant_original_price) {
                    $meta_input['_sale_price'] = $variant_sale_price;
                    $meta_input['_price'] = $variant_sale_price;
                }
                $meta_input['_stock_status'] = 'instock';
                $meta_input['_manage_stock'] = 'no';
                $meta_input['_stock'] = ''; // Empty stock for variations
                $meta_input['_backorders'] = 'no'; // No backorders
                
                // Log meta_input để debug
                error_log("Meta input for variant {$index}: " . print_r($meta_input, true));
                
                // Tạo variation post
                $variation_id = wp_insert_post([
                    'post_title' => 'Variation',
                    'post_name' => 'product-' . $product_id . '-variation-' . sanitize_title(implode('-', $combo)),
                    'post_status' => 'publish',
                    'post_type' => 'product_variation',
                    'post_parent' => $product_id,
                    'meta_input' => $meta_input,
                ]);
                
                if (!is_wp_error($variation_id)) {
                    $created_variant_ids[] = $variation_id;
                    error_log("Tạo variation mới ID: {$variation_id} | " . json_encode($combo));
                    
                    // Set term relationships cho variant - chỉ sử dụng name, không lưu ID
                    foreach ($combo as $taxonomy => $term_value) {
                        // Tìm term bằng name
                        $term = get_term_by('name', $term_value, $taxonomy);
                        if (!$term) {
                            // Tạo term mới nếu chưa tồn tại
                            $term = wp_insert_term($term_value, $taxonomy);
                            if (is_wp_error($term)) {
                                error_log("Error creating term '{$term_value}' for variant {$variation_id} - {$taxonomy}: " . $term->get_error_message());
                                continue;
                            }
                            error_log("Created new term '{$term_value}' with ID: {$term['term_id']} for variant {$variation_id} - {$taxonomy}");
                        }
                        
                        // Set terms cho variant bằng name, không sử dụng ID
                        $terms_result = wp_set_object_terms($variation_id, $term_value, $taxonomy, true);
                        if (is_wp_error($terms_result)) {
                            error_log("Error setting terms for variant {$variation_id} - {$taxonomy}: " . $terms_result->get_error_message());
                        } else {
                            error_log("Successfully set terms for variant {$variation_id} - {$taxonomy}: " . print_r($terms_result, true));
                        }
                    }
                    
                    // Set default attributes từ biến thể đầu tiên (index = 0)
                    if ($index === 0) {
                        foreach ($combo as $taxonomy => $term_value) {
                            $term_slug = ''; // Khởi tạo slug
                            
                            // Tìm term để lấy slug cho default attributes
                            $term = term_exists($term_value, $taxonomy);
                            if ($term) {
                                if (is_array($term)) {
                                    $term_slug = $term['slug'];
                                } else {
                                    $term_obj = get_term($term, $taxonomy);
                                    $term_slug = $term_obj ? $term_obj->slug : sanitize_title($term_value);
                                }
                            } else {
                                // Term không tồn tại, tạo slug từ term_value
                                $term_slug = sanitize_title($term_value);
                                error_log("Term not found for default attribute, using sanitized slug: {$term_value} -> {$term_slug}");
                            }
                            
                            // Đảm bảo luôn có slug
                            if (empty($term_slug)) {
                                $term_slug = sanitize_title($term_value);
                                error_log("Fallback: Using sanitized slug for default attribute: {$term_value} -> {$term_slug}");
                            }
                            
                            // Sử dụng slug cho default attributes
                            $default_attributes[$taxonomy] = $term_slug;
                            error_log("Set default attribute for {$taxonomy}: {$term_value} -> {$term_slug}");
                        }
                    }
                } else {
                    error_log("Lỗi tạo variation: " . $variation_id->get_error_message());
                }
            }
            
            // 5. Cập nhật _default_attributes cho sản phẩm parent
            error_log("Default attributes before setting: " . print_r($default_attributes, true));
            
            if (!empty($default_attributes)) {
                update_post_meta($product_id, '_default_attributes', $default_attributes);
                error_log("Đã set _default_attributes cho sản phẩm: " . print_r($default_attributes, true));
                
                // Đảm bảo WooCommerce nhận ra default attributes
                $product = wc_get_product($product_id);
                if ($product && method_exists($product, 'set_default_attributes')) {
                    $product->set_default_attributes($default_attributes);
                    error_log("Set default attributes via WooCommerce object");
                }
            }
            
            // 6. Force refresh cache
            clean_post_cache($product_id);
            wp_cache_delete($product_id, 'posts');
            
            // Also refresh cache for all variants
            foreach ($created_variant_ids as $variant_id) {
                clean_post_cache($variant_id);
                wp_cache_delete($variant_id, 'posts');
            }
            
            // Force WooCommerce to recognize the product as variable and refresh all caches
            $product = wc_get_product($product_id);
            if ($product) {
                // Luôn set stock status = 'instock' cho product chính
                $product->set_stock_status('instock');
                error_log("Setting product stock status to instock for product {$product_id}");
                
                $product->set_manage_stock(false);
                wc_update_product_stock($product_id, '', 'set');
                $product->set_backorders('no');
                
                // Đảm bảo default attributes được set đúng cách
                if (!empty($default_attributes)) {
                    $product->set_default_attributes($default_attributes);
                    error_log("Final set default attributes via WooCommerce object: " . print_r($default_attributes, true));
                }
                
                $product->save(); // This triggers WooCommerce's internal refresh
                error_log("Updated product stock status and default attributes via WooCommerce object");
            }
            
            error_log("[PIP] Successfully created " . count($created_variant_ids) . " variants");
            
            return true;
            
        } catch (Exception $e) {
            error_log('Error creating product variants: ' . $e->getMessage());
            return false;
        }
    }
    
    // Generate all possible variant combinations
    private function generate_variant_combinations($attributes) {
        if (empty($attributes)) {
            return array();
        }
        
        $combinations = array();
        $this->generate_combinations_recursive($attributes, 0, array(), $combinations);
        
        return $combinations;
    }
    
    // Recursive helper to generate combinations
    private function generate_combinations_recursive($attributes, $index, $current, &$combinations) {
        if ($index === count($attributes)) {
            $combinations[] = $current;
            return;
        }
        
        $current_attribute = $attributes[$index];
        foreach ($current_attribute['values'] as $value) {
            $current[$index] = array(
                'taxonomy' => $current_attribute['taxonomy'],
                'slug' => $value['slug'],
                'name' => $value['name']
            );
            $this->generate_combinations_recursive($attributes, $index + 1, $current, $combinations);
        }
    }
    
    // Generate variant title from attributes
    private function generate_variant_title($variant) {
        $names = array();
        foreach ($variant as $attr) {
            $names[] = $attr['name'];
        }
        return implode(' - ', $names);
    }
    
    // Create WooCommerce attributes in correct format
    private function create_woocommerce_attributes($attributes) {
        $woo_attributes = array();
        
        foreach ($attributes as $index => $attribute) {
            $taxonomy = sanitize_text_field($attribute['taxonomy']);
            $values = array();
            
            foreach ($attribute['values'] as $value) {
                $values[] = intval($value);
            }
            
            if (!empty($values)) {
                // Format: attribute_names[0]=pa_color&attribute_values[0][]=87
                $woo_attributes[$taxonomy] = array(
                    'name' => $taxonomy,
                    'value' => '',
                    'position' => $index,
                    'is_visible' => 1,
                    'is_variation' => 1,
                    'is_taxonomy' => 1
                );
            }
        }
        
        return $woo_attributes;
    }

    // Helper function to create cartesian product (tổ hợp các attributes)
    private function cartesian_product($arrays) {
        $result = [[]];
        foreach ($arrays as $key => $values) {
            $append = [];
            foreach ($result as $product) {
                foreach ($values as $item) {
                    $product_copy = $product;
                    $product_copy[$key] = strtolower($item); // chuyển về viết thường
                    $append[] = $product_copy;
                }
            }
            $result = $append;
        }
        return $result;
    }
    
    // Helper to create attribute taxonomy if it doesn't exist
    private function create_attribute_taxonomy($taxonomy_name) {
        if (!taxonomy_exists($taxonomy_name)) {
            register_taxonomy($taxonomy_name, 'product', array(
                'hierarchical' => false,
                'show_ui' => true,
                'show_in_nav_menus' => false,
                'show_tagcloud' => false,
                'meta_box_cb' => 'taxonomy_meta_box_cb', // Ensure meta box is available
                'labels' => array(
                    'name' => ucfirst($taxonomy_name),
                    'singular_name' => ucfirst($taxonomy_name),
                    'search_items' => 'Search ' . ucfirst($taxonomy_name),
                    'all_items' => 'All ' . ucfirst($taxonomy_name),
                    'parent_item' => 'Parent ' . ucfirst($taxonomy_name),
                    'parent_item_colon' => 'Parent ' . ucfirst($taxonomy_name) . ':',
                    'edit_item' => 'Edit ' . ucfirst($taxonomy_name),
                    'update_item' => 'Update ' . ucfirst($taxonomy_name),
                    'add_new_item' => 'Add New ' . ucfirst($taxonomy_name),
                    'new_item_name' => 'New ' . ucfirst($taxonomy_name) . ' Name',
                    'menu_name' => ucfirst($taxonomy_name),
                ),
                'show_in_quick_edit' => false,
                'show_admin_column' => true,
                'query_var' => true,
                'rewrite' => array(
                    'slug' => sanitize_title($taxonomy_name),
                    'with_front' => false,
                    'hierarchical' => false,
                ),
                'capabilities' => array(
                    'manage_terms' => 'manage_product_terms',
                    'edit_terms' => 'edit_product_terms',
                    'delete_terms' => 'delete_product_terms',
                    'assign_terms' => 'assign_product_terms',
                ),
                'sort' => true,
                'args' => array(
                    'orderby' => 'term_order',
                    'order' => 'ASC',
                ),
            ));
        }
    }
}

new Product_Importer();

// Đăng ký action cho AJAX
add_action( 'wp_ajax_export_product_json', 'pip_export_product_json' );

function pip_export_product_json() {
    check_ajax_referer( 'product_importer_nonce', 'security' );

    $data = array(
        'original_price'     => sanitize_text_field( $_POST['original_price'] ?? '' ),
        'sale_price'         => sanitize_text_field( $_POST['sale_price'] ?? '' ),
        'product_category'   => sanitize_text_field( $_POST['product_category'] ?? '' ),
        'product_description'=> wp_kses_post( $_POST['product_description'] ?? '' ),
        'product_type'       => sanitize_text_field( $_POST['product_type'] ?? 'simple' ),
        // Bỏ qua Product Attributes và Variant Prices
    );

    wp_send_json_success( $data );
}

add_action( 'wp_ajax_import_product_json', 'pip_import_product_json' );

// Custom AJAX endpoint for getting attribute values
add_action( 'wp_ajax_get_attribute_values', 'pip_get_attribute_values' );
add_action( 'wp_ajax_nopriv_get_attribute_values', 'pip_get_attribute_values' );

// Hook để final check stock status
add_action( 'pip_final_stock_status_check', 'pip_final_stock_status_check_handler' );

// Hook để set stock status sau khi WooCommerce đã hoàn thành
add_action( 'woocommerce_product_set_stock_status', 'pip_force_stock_status_after_woo', 10, 3 );

// Hook để chặn WooCommerce override stock status
add_filter( 'woocommerce_product_get_stock_status', 'pip_prevent_stock_status_override', 10, 2 );
add_filter( 'woocommerce_product_variation_get_stock_status', 'pip_prevent_stock_status_override', 10, 2 );

// Hook để chặn WooCommerce update stock status
add_action( 'woocommerce_before_product_object_save', 'pip_prevent_stock_status_save', 10, 2 );

// Hook để chặn WooCommerce update post meta
add_action( 'update_post_meta', 'pip_prevent_stock_status_meta_update', 10, 4 );
add_action( 'added_post_meta', 'pip_prevent_stock_status_meta_added', 10, 4 );

// Function để chặn WooCommerce override stock status
function pip_prevent_stock_status_override($stock_status, $product) {
    // Chỉ xử lý VARIABLE products được import bởi plugin
    if (get_post_meta($product->get_id(), '_pip_force_instock', true)) {
        // Kiểm tra product type - chỉ áp dụng cho variable products
        $product_type = $product->get_type();
        if ($product_type === 'variable') {
            error_log("[PIP] Preventing stock status override for VARIABLE product {$product->get_id()}, forcing instock");
            return 'instock';
        }
    }
    return $stock_status;
}

// Function để chặn WooCommerce save stock status
function pip_prevent_stock_status_save($product, $data_store) {
    // Chỉ xử lý VARIABLE products được import bởi plugin
    if (get_post_meta($product->get_id(), '_pip_force_instock', true)) {
        // Kiểm tra product type - chỉ áp dụng cho variable products
        $product_type = $product->get_type();
        if ($product_type === 'variable') {
            // Force set stock status = 'instock' trước khi save
            $product->set_stock_status('instock');
            error_log("[PIP] Preventing stock status save override for VARIABLE product {$product->get_id()}, forcing instock");
        }
    }
}

// Function để chặn WooCommerce update post meta stock status
function pip_prevent_stock_status_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
    // Chỉ xử lý stock status meta cho VARIABLE products
    if ($meta_key === '_stock_status' && get_post_meta($post_id, '_pip_force_instock', true)) {
        // Kiểm tra product type - chỉ áp dụng cho variable products
        $product = wc_get_product($post_id);
        if ($product && $product->get_type() === 'variable') {
            // Nếu cố gắng set thành 'outofstock', chặn lại
            if ($meta_value === 'outofstock') {
                error_log("[PIP] Blocking stock status update to outofstock for VARIABLE product {$post_id}");
                // Không cho phép update
                return false;
            }
        }
    }
}

// Function để chặn WooCommerce add post meta stock status
function pip_prevent_stock_status_meta_added($meta_id, $post_id, $meta_key, $meta_value) {
    // Chỉ xử lý stock status meta cho VARIABLE products
    if ($meta_key === '_stock_status' && get_post_meta($post_id, '_pip_force_instock', true)) {
        // Kiểm tra product type - chỉ áp dụng cho variable products
        $product = wc_get_product($post_id);
        if ($product && $product->get_type() === 'variable') {
            // Nếu cố gắng add 'outofstock', chặn lại
            if ($meta_value === 'outofstock') {
                error_log("[PIP] Blocking stock status addition of outofstock for VARIABLE product {$post_id}");
                // Xóa meta vừa thêm
                delete_metadata_by_mid('post', $meta_id);
                // Thêm lại với giá trị 'instock'
                add_post_meta($post_id, '_stock_status', 'instock', true);
            }
        }
    }
}

function pip_force_stock_status_after_woo($product_id, $status, $product) {
    // Chỉ xử lý nếu product có post meta _pip_force_instock VÀ là variable product
    if (get_post_meta($product_id, '_pip_force_instock', true)) {
        // Kiểm tra product type - chỉ áp dụng cho variable products
        $product_type = $product->get_type();
        if ($product_type === 'variable') {
            // Luôn force set stock status = 'instock'
            $target_stock_status = 'instock';
            
            if ($status !== $target_stock_status) {
                error_log("[PIP] WooCommerce tried to set {$status} for VARIABLE product {$product_id}, forcing to {$target_stock_status}");
                
                // Force set lại stock status = 'instock'
                update_post_meta($product_id, '_stock_status', $target_stock_status);
                clean_post_cache($product_id);
                
                // Set lại qua WooCommerce object
                $wc_product = wc_get_product($product_id);
                if ($wc_product) {
                    $wc_product->set_stock_status($target_stock_status);
                    $wc_product->save();
                }
                
                error_log("[PIP] Forced stock status to {$target_stock_status} for VARIABLE product {$product_id}");
            }
            
            // Xóa flag sau khi đã xử lý
            delete_post_meta($product_id, '_pip_force_instock');
            error_log("[PIP] Removed _pip_force_instock flag for VARIABLE product {$product_id}");
        }
    }
}

function pip_final_stock_status_check_handler($product_id) {
    error_log("[PIP] Final stock status check for product ID: {$product_id}");
    
    // Kiểm tra product type - chỉ áp dụng cho variable products
    $product = wc_get_product($product_id);
    if ($product && $product->get_type() === 'variable' && get_post_meta($product_id, '_pip_force_instock', true)) {
        // Luôn force set stock status = 'instock'
        $target_stock_status = 'instock';
        
        update_post_meta($product_id, '_manage_stock', 'no');
        update_post_meta($product_id, '_stock', '');
        update_post_meta($product_id, '_backorders', 'no');
        update_post_meta($product_id, '_stock_status', $target_stock_status);
        
        // Force refresh cache
        clean_post_cache($product_id);
        wp_cache_delete($product_id, 'posts');
        
        // Use WooCommerce object
        $wc_product = wc_get_product($product_id);
        if ($wc_product) {
            $wc_product->set_stock_status($target_stock_status);
            $wc_product->set_manage_stock(false);
            wc_update_product_stock($product_id, '', 'set');
            $wc_product->set_backorders('no');
            $wc_product->save();
            
            error_log("[PIP] Final stock status set to {$target_stock_status} for VARIABLE product ID: {$product_id}");
            
            // Verify final status
            $final_status = get_post_meta($product_id, '_stock_status', true);
            error_log("[PIP] Verified final stock status for VARIABLE product ID {$product_id}: {$final_status}");
            
            // Xóa flag sau khi đã xử lý
            delete_post_meta($product_id, '_pip_force_instock');
            error_log("[PIP] Removed _pip_force_instock flag for VARIABLE product {$product_id}");
        }
    }
}

function pip_get_attribute_values() {
    $taxonomy = sanitize_text_field( $_GET['taxonomy'] ?? '' );
    
    if ( empty( $taxonomy ) ) {
        wp_send_json_error( array( 'message' => 'Taxonomy is required.' ) );
    }
    
    // Get terms for the taxonomy
    $terms = get_terms( array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'orderby' => 'menu_order',
        'order' => 'ASC',
        'number' => 50
    ) );
    
    if ( is_wp_error( $terms ) ) {
        wp_send_json_error( array( 'message' => 'Error getting terms.' ) );
    }
    
    $formatted_terms = array();
    foreach ( $terms as $term ) {
        $formatted_terms[] = array(
            'term_id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'taxonomy' => $term->taxonomy
        );
    }
    
    wp_send_json_success( $formatted_terms );
}

function pip_import_product_json() {
    check_ajax_referer( 'product_importer_nonce', 'security' );

    $json_data = json_decode( file_get_contents( 'php://input' ), true );

    if ( ! $json_data ) {
        wp_send_json_error( array( 'message' => 'Invalid JSON data.' ) );
    }

    $original_price = sanitize_text_field( $json_data['original_price'] ?? '' );
    $sale_price = sanitize_text_field( $json_data['sale_price'] ?? '' );
    $product_category = sanitize_text_field( $json_data['product_category'] ?? '' );
    $product_description = wp_kses_post( $json_data['product_description'] ?? '' );
    $product_type = sanitize_text_field( $json_data['product_type'] ?? 'simple' );
    // Bỏ qua Product Attributes và Variant Prices

    // Xử lý dữ liệu (ví dụ: lưu vào cơ sở dữ liệu hoặc hiển thị)
    wp_send_json_success( array(
        'original_price'     => $original_price,
        'sale_price'         => $sale_price,
        'product_category'   => $product_category,
        'product_description'=> $product_description,
        'product_type'       => $product_type,
        // Bỏ qua Product Attributes và Variant Prices
    ));
}

function pip_verify_license_on_load() {
    // Lấy license key từ cơ sở dữ liệu
    $license_key = get_option( 'pip_license_key' );

    if ( ! $license_key ) {
        return __( 'License key is missing. Please activate the plugin with a valid license.', 'product-importer-plugin' );
    }

    // Kiểm tra license
    $result = pip_verify_license( $license_key );

    if ( $result !== true ) {
        return $result; // Trả về thông báo lỗi nếu license không hợp lệ
    }

    return true; // License hợp lệ
}
?>