<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check if WordPress is loaded
if (!function_exists('wp_send_json_error')) {
    require_once(ABSPATH . 'wp-load.php');
}

use PhpOffice\PhpSpreadsheet\IOFactory;

// Include required WordPress files
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

function pip_handle_excel_import() {
    // Disable output buffering and compression
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Disable zlib compression
    if (ini_get('zlib.output_compression')) {
        ini_set('zlib.output_compression', 'Off');
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have sufficient permissions to access this page.'));
    }

    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(array('message' => 'No file uploaded or upload error.'));
    }

    try {
        // Set memory limit and execution time
        ini_set('memory_limit', '256M');
        set_time_limit(300);

        $inputFileName = $_FILES['excel_file']['tmp_name'];
        $spreadsheet = IOFactory::load($inputFileName);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Get highest row and column
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        
        // Get header row
        $headers = [];
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $headers[$col] = $worksheet->getCell($col . '1')->getValue();
        }

        $imported_products = array();
        $errors = array();
        $batch_size = 50; // Process 50 rows at a time
        $total_rows = $highestRow - 1; // Exclude header row

        for ($row = 2; $row <= $highestRow; $row += $batch_size) {
            $end_row = min($row + $batch_size - 1, $highestRow);
            
            for ($current_row = $row; $current_row <= $end_row; $current_row++) {
                if (empty($worksheet->getCell('A' . $current_row)->getValue())) continue;

                $product_data = array(
                    'name' => sanitize_text_field($worksheet->getCell('A' . $current_row)->getValue()),
                    'original_price' => floatval($worksheet->getCell('B' . $current_row)->getValue()),
                    'sale_price' => floatval($worksheet->getCell('C' . $current_row)->getValue()),
                    'description' => wp_kses_post($worksheet->getCell('E' . $current_row)->getValue()),
                    'category' => implode(',', array_filter(array_map('sanitize_text_field', array_map('trim', explode(',', (string) $worksheet->getCell('D' . $current_row)->getValue()))))),
                    'product_image' => array_values(array_filter(array_map('trim', explode(',', $worksheet->getCell('F' . $current_row)->getValue())))),
                    'tags' => array_values(array_filter(array_map('sanitize_text_field', array_map('trim', explode(',', (string) $worksheet->getCell('G' . $current_row)->getValue()))))),
                    'brands' => array_values(array_filter(array_map('sanitize_text_field', array_map('trim', explode(',', (string) $worksheet->getCell('H' . $current_row)->getValue())))))
                );

                // Validate required fields
                if (empty($product_data['name'])) {
                    $errors[] = "Row {$current_row}: Product name is required";
                    continue;
                }

                if ($product_data['original_price'] <= 0) {
                    $errors[] = "Row {$current_row}: Original price must be greater than 0";
                    continue;
                }

                if ($product_data['sale_price'] > 0 && $product_data['sale_price'] >= $product_data['original_price']) {
                    $errors[] = "Row {$current_row}: Sale price must be less than original price";
                    continue;
                }

                // Add to imported products array for preview
                $imported_products[] = array(
                    'name' => $product_data['name'],
                    'original_price' => $product_data['original_price'],
                    'sale_price' => $product_data['sale_price'],
                    'category' => $product_data['category'],
                    'product_image' => $product_data['product_image'][0] ?? '',
                    'gallery_images' => array_slice($product_data['product_image'], 1),
                    'tags' => $product_data['tags'],
                    'brands' => $product_data['brands'],
                    'description' => $product_data['description']
                );
            }
        }

        // Store products in transient for later import
        set_transient('pip_excel_import_preview', $imported_products, HOUR_IN_SECONDS);

        // Clear spreadsheet object
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        // Send response
        wp_send_json_success(array(
            'message' => sprintf('Found %d products to import.', count($imported_products)),
            'imported_products' => $imported_products
        ));

    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Error processing Excel file: ' . $e->getMessage()));
    }
}

// Hook for handling Excel import
add_action('wp_ajax_pip_handle_excel_import', 'pip_handle_excel_import');

// LENCAM: handle upload and preview (3 cols: Product Name, Product Slug, Image Link)
function pip_handle_lencam_import() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'product-importer-plugin')));
    }

    if (!isset($_FILES['lencam_excel_file']) || $_FILES['lencam_excel_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(array('message' => __('No file uploaded or upload error.', 'product-importer-plugin')));
    }

    $category_term_id = isset($_POST['product_category']) ? intval($_POST['product_category']) : 0;
    if ($category_term_id <= 0) {
        wp_send_json_error(array('message' => __('Please select a product category.', 'product-importer-plugin')));
    }

    try {
        ini_set('memory_limit', '256M');
        set_time_limit(300);

        $inputFileName = $_FILES['lencam_excel_file']['tmp_name'];
        $spreadsheet = IOFactory::load($inputFileName);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestRow();
        $products = array();
        for ($row = 2; $row <= $highestRow; $row++) {
            $name = sanitize_text_field($sheet->getCell('A' . $row)->getValue());
            if ($name === '' || $name === null) { continue; }
            $slug = sanitize_title($sheet->getCell('B' . $row)->getValue());
            $image = esc_url_raw($sheet->getCell('C' . $row)->getValue());
            $description = wp_kses_post($sheet->getCell('D' . $row)->getValue());
            $original_price = floatval($sheet->getCell('E' . $row)->getValue());
            $sale_price = floatval($sheet->getCell('F' . $row)->getValue());
            $products[] = array(
                'name' => $name,
                'slug' => $slug,
                'image' => $image,
                'description' => $description,
                'original_price' => $original_price,
                'sale_price' => $sale_price,
            );
        }

        set_transient('pip_lencam_import_preview', array(
            'category' => $category_term_id,
            'products' => $products,
            'defaults' => array(
                'description' => isset($_POST['default_description']) ? wp_kses_post($_POST['default_description']) : '',
                'original_price' => isset($_POST['default_original_price']) ? floatval($_POST['default_original_price']) : 0,
                'sale_price' => isset($_POST['default_sale_price']) ? floatval($_POST['default_sale_price']) : 0,
            ),
        ), HOUR_IN_SECONDS);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        wp_send_json_success(array(
            'message' => sprintf(__('Found %d products to import.', 'product-importer-plugin'), count($products)),
            'imported_products' => $products,
        ));
    } catch (Exception $e) {
        wp_send_json_error(array('message' => __('Error processing Excel file: ', 'product-importer-plugin') . $e->getMessage()));
    }
}
add_action('wp_ajax_pip_handle_lencam_import', 'pip_handle_lencam_import');

// LENCAM: confirm import
add_action('wp_ajax_pip_confirm_lencam_import', function() {
    check_ajax_referer('pip_confirm_lencam_import', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'product-importer-plugin')));
    }

    $preview = get_transient('pip_lencam_import_preview');
    if (!$preview || empty($preview['products'])) {
        wp_send_json_error(array('message' => __('No products to import.', 'product-importer-plugin')));
    }

    $category_term_id = isset($_POST['product_category']) ? intval($_POST['product_category']) : (int)($preview['category'] ?? 0);
    if ($category_term_id <= 0) {
        wp_send_json_error(array('message' => __('Please select a product category.', 'product-importer-plugin')));
    }

    $defaults = $preview['defaults'] ?? array();
    $default_description = isset($_POST['default_description']) ? wp_kses_post($_POST['default_description']) : ($defaults['description'] ?? '');
    $default_original_price = isset($_POST['default_original_price']) ? floatval($_POST['default_original_price']) : ($defaults['original_price'] ?? 0);
    $default_sale_price = isset($_POST['default_sale_price']) ? floatval($_POST['default_sale_price']) : ($defaults['sale_price'] ?? 0);

    $imported = 0;
    $errors = array();
    foreach ($preview['products'] as $product) {
        try {
            $postarr = array(
                'post_title' => $product['name'],
                'post_name' => $product['slug'] !== '' ? $product['slug'] : sanitize_title($product['name']),
                'post_content' => !empty($product['description']) ? $product['description'] : $default_description,
                'post_status' => 'publish',
                'post_type' => 'product',
            );
            $product_id = wp_insert_post($postarr, true);
            if (is_wp_error($product_id)) {
                throw new Exception($product_id->get_error_message());
            }

            $set_terms = wp_set_object_terms($product_id, array($category_term_id), 'product_cat');
            if (is_wp_error($set_terms)) {
                throw new Exception($set_terms->get_error_message());
            }

            // Set product type as simple - both terms and meta
            wp_set_object_terms($product_id, 'simple', 'product_type');
            update_post_meta($product_id, '_product_type', 'simple');
            


            // Set product prices
            $final_original_price = $product['original_price'] > 0 ? $product['original_price'] : $default_original_price;
            $final_sale_price = $product['sale_price'] > 0 ? $product['sale_price'] : $default_sale_price;

            if ($final_original_price > 0) {
                update_post_meta($product_id, '_regular_price', $final_original_price);
                update_post_meta($product_id, '_price', $final_original_price);
                
                if ($final_sale_price > 0 && $final_sale_price < $final_original_price) {
                    update_post_meta($product_id, '_sale_price', $final_sale_price);
                    update_post_meta($product_id, '_price', $final_sale_price);
                }
                

            }

            if (!empty($product['image'])) {
                $image_id = pip_upload_image_from_url($product['image']);
                if ($image_id) {
                    set_post_thumbnail($product_id, $image_id);
                }
            }

            // Force refresh cache and ensure WooCommerce recognizes the product
            clean_post_cache($product_id);
            wp_cache_delete($product_id, 'posts');
            
            // Use WooCommerce object to ensure proper product type and price setting
            $wc_product = wc_get_product($product_id);
            if ($wc_product) {
                $wc_product->set_status('publish');
                $wc_product->save();
                

            }

            $imported++;
        } catch (Exception $e) {
            $errors[] = sprintf(__('Error importing "%s": %s', 'product-importer-plugin'), $product['name'], $e->getMessage());
        }
    }

    delete_transient('pip_lencam_import_preview');
    wp_send_json_success(array(
        'message' => sprintf(__('Successfully imported %d products. %d errors.', 'product-importer-plugin'), $imported, count($errors)),
        'errors' => $errors,
    ));
});

// LENCAM: cancel import
add_action('wp_ajax_pip_cancel_lencam_import', function() {
    check_ajax_referer('pip_cancel_lencam_import', 'nonce');
    delete_transient('pip_lencam_import_preview');
    wp_send_json_success();
});

// Hook for confirming import
add_action('wp_ajax_pip_confirm_excel_import', function() {
    check_ajax_referer('pip_confirm_import', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permission denied.', 'product-importer-plugin')]);
    }

    $products = get_transient('pip_excel_import_preview');
    if (!$products) {
        wp_send_json_error(['message' => __('No products to import.', 'product-importer-plugin')]);
    }

    $imported = 0;
    $errors = [];

    foreach ($products as $product) {
        try {
            // Create product
            $product_id = wp_insert_post([
                'post_title' => $product['name'],
                'post_content' => $product['description'],
                'post_status' => 'publish',
                'post_type' => 'product'
            ]);

            if (is_wp_error($product_id)) {
                throw new Exception($product_id->get_error_message());
            }

            // Set product meta
            update_post_meta($product_id, '_regular_price', $product['original_price']);
            update_post_meta($product_id, '_price', $product['original_price']);
            
            if (!empty($product['sale_price'])) {
                update_post_meta($product_id, '_sale_price', $product['sale_price']);
                update_post_meta($product_id, '_price', $product['sale_price']);
            }

            // Set categories (support comma-separated values from Excel column D)
            $category_term_ids = [];
            $category_values = array_filter(array_map('trim', explode(',', (string) ($product['category'] ?? ''))));

            foreach ($category_values as $category_value) {
                $term = get_term_by('name', $category_value, 'product_cat');
                if (!$term || is_wp_error($term)) {
                    $term = get_term_by('slug', sanitize_title($category_value), 'product_cat');
                }

                if ($term && !is_wp_error($term)) {
                    $category_term_ids[] = (int) $term->term_id;
                }
            }

            if (!empty($category_term_ids)) {
                wp_set_object_terms($product_id, array_values(array_unique($category_term_ids)), 'product_cat');
            }

            // Set product tags (product_tag taxonomy)
            if (!empty($product['tags'])) {
                $tags = array_values(array_filter($product['tags']));
                if (!empty($tags)) {
                    wp_set_object_terms($product_id, $tags, 'product_tag', true);
                }
            }

            // Set product brand if a brand-like taxonomy exists
            $brand_tax = null;
            foreach (array('brand', 'product_brand', 'pa_brand') as $t) {
                if (taxonomy_exists($t)) {
                    $brand_tax = $t;
                    break;
                }
            }
            if ($brand_tax && !empty($product['brands'])) {
                $bterms = array_values(array_filter($product['brands']));
                if (!empty($bterms)) {
                    wp_set_object_terms($product_id, $bterms, $brand_tax, true);
                }
            }

            // Handle product image
            if (!empty($product['product_image'])) {
                $image_id = pip_upload_image_from_url($product['product_image']);
                if ($image_id) {
                    set_post_thumbnail($product_id, $image_id);
                }
            }

            // Handle gallery images
            if (!empty($product['gallery_images'])) {
                $gallery_ids = [];
                foreach ($product['gallery_images'] as $image_url) {
                    $image_id = pip_upload_image_from_url(trim($image_url));
                    if ($image_id) {
                        $gallery_ids[] = $image_id;
                    }
                }
                if (!empty($gallery_ids)) {
                    update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
                }
            }

            $imported++;
        } catch (Exception $e) {
            $errors[] = sprintf(__('Error importing product "%s": %s', 'product-importer-plugin'), 
                $product['name'], 
                $e->getMessage()
            );
        }
    }

    // Clear transient
    delete_transient('pip_excel_import_preview');

    wp_send_json_success([
        'message' => sprintf(
            __('Successfully imported %d products. %d errors.', 'product-importer-plugin'),
            $imported,
            count($errors)
        ),
        'errors' => $errors
    ]);
});

// Hook for canceling import
add_action('wp_ajax_pip_cancel_excel_import', function() {
    check_ajax_referer('pip_cancel_import', 'nonce');
    delete_transient('pip_excel_import_preview');
    wp_send_json_success();
});

// Helper function to upload image from URL
function pip_upload_image_from_url($url) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $temp_file = download_url($url);
    if (is_wp_error($temp_file)) {
        return false;
    }

    $file_array = array(
        'name' => basename($url),
        'tmp_name' => $temp_file
    );

    $attachment_id = media_handle_sideload($file_array, 0);
    if (is_wp_error($attachment_id)) {
        @unlink($temp_file);
        return false;
    }

    return $attachment_id;
}











