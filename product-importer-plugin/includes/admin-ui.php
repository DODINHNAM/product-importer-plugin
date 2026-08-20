<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function pip_render_admin_ui() {
    // Kiểm tra quyền truy cập
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'product-importer-plugin' ) );
    }

    // Kiểm tra WooCommerce
    if ( ! class_exists( 'WooCommerce' ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce plugin is required for this plugin to work.', 'product-importer-plugin' ) . '</p></div>';
        return;
    }

    // Kiểm tra license
    $license_check = pip_verify_license_on_load();
    if ( $license_check !== true ) {
        echo '<div class="notice notice-error"><p>' . esc_html( $license_check ) . '</p></div>';
        return; // Dừng hiển thị giao diện nếu license không hợp lệ
    }
    // Enqueue Select2 for improved multiselects in the admin UI
    if ( function_exists( 'wp_enqueue_script' ) ) {
        wp_enqueue_style( 'pip-select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0' );
        wp_enqueue_script( 'pip-select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0-rc.0', true );
    }
    // Enqueue the WordPress media uploader so products can pull gallery images from the Media Library
    wp_enqueue_media();
    ?>
    <div class="wrap pip-container">
        <h1><?php esc_html_e( 'Import Products', 'product-importer-plugin' ); ?></h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="#manual-upload" class="nav-tab nav-tab-active"><?php esc_html_e( 'Manual Upload', 'product-importer-plugin' ); ?></a>
            <a href="#advanced-manual-upload" class="nav-tab"><?php esc_html_e( 'Manual Upload Plus', 'product-importer-plugin' ); ?></a>
            <a href="#excel-import" class="nav-tab"><?php esc_html_e( 'Excel Import', 'product-importer-plugin' ); ?></a>
            <a href="#lencam-import" class="nav-tab"><?php esc_html_e( 'LENCAM Import', 'product-importer-plugin' ); ?></a>
        </h2>

        <div id="manual-upload" class="tab-content">
            <form id="product-import-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'product_importer_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Export/Import JSON', 'product-importer-plugin' ); ?></th>
                        <td>
                            <button type="button" id="export-json" class="button"><?php esc_html_e( 'Export to JSON', 'product-importer-plugin' ); ?></button>
                            <input type="file" id="import-json" accept="application/json" style="display: none;" />
                            <button type="button" id="import-json-button" class="button"><?php esc_html_e( 'Import from JSON', 'product-importer-plugin' ); ?></button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="product_images"><?php esc_html_e( 'Upload Images', 'product-importer-plugin' ); ?></label></th>
                        <td>
                            <input type="file" id="product_images" name="product_images[]" webkitdirectory multiple />
                            <p>
                                <label>
                                    <input type="checkbox" id="include_image_in_gallery" name="include_image_in_gallery" />
                                    <?php esc_html_e( 'Also include the product image in Gallery Images', 'product-importer-plugin' ); ?>
                                </label>
                            </p>
                            <p>
                                <button type="button" id="select-media-gallery-images" class="button"><?php esc_html_e( 'Add Images from Media Library', 'product-importer-plugin' ); ?></button>
                                <button type="button" id="clear-media-gallery-images" class="button" style="display: none;"><?php esc_html_e( 'Clear Selected', 'product-importer-plugin' ); ?></button>
                            </p>
                            <p class="description"><?php esc_html_e( 'Images selected here will be added to the Gallery Images of every product imported in this batch.', 'product-importer-plugin' ); ?></p>
                            <div id="media-gallery-preview" style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px;"></div>
                            <div id="image-preview" style="margin-top: 20px;">
                                <table border="1" style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Image', 'product-importer-plugin' ); ?></th>
                                            <th><?php esc_html_e( 'Product Name', 'product-importer-plugin' ); ?></th>
                                            <th><?php esc_html_e( 'Gallery Images', 'product-importer-plugin' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="image-preview-body"></tbody>
                                </table>
                                <div id="hidden-inputs-container" style="display: none;"></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="original_price"><?php esc_html_e( 'Original Price', 'product-importer-plugin' ); ?></label></th>
                        <td><input type="text" id="original_price" name="original_price" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sale_price"><?php esc_html_e( 'Sale Price', 'product-importer-plugin' ); ?></label></th>
                        <td><input type="text" id="sale_price" name="sale_price" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="product_category"><?php esc_html_e( 'Product Category', 'product-importer-plugin' ); ?></label></th>
                        <td>
                            <?php
                            $categories = get_terms( array(
                                'taxonomy' => 'product_cat',
                                'hide_empty' => false,
                            ) );
                            ?>
                            <select id="product_category" name="product_category[]" class="pip-multiselect-select" multiple data-placeholder="<?php esc_attr_e( 'Select categories', 'product-importer-plugin' ); ?>" style="width:100%;">
                                <?php foreach ( $categories as $category ) : ?>
                                    <option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="product_tag"><?php esc_html_e( 'Product Tag', 'product-importer-plugin' ); ?></label></th>
                        <td>
                            <?php
                            $tags = get_terms( array(
                                'taxonomy' => 'product_tag',
                                'hide_empty' => false,
                            ) );
                            ?>
                            <select id="product_tag" name="product_tag[]" class="pip-multiselect-select" multiple data-placeholder="<?php esc_attr_e( 'Select tags', 'product-importer-plugin' ); ?>" style="width:100%;">
                                <?php foreach ( $tags as $tag ) : ?>
                                    <option value="<?php echo esc_attr( $tag->term_id ); ?>"><?php echo esc_html( $tag->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="product_description"><?php esc_html_e( 'Product Description', 'product-importer-plugin' ); ?></label></th>
                        <td>
                            <?php
                            wp_editor(
                                '',
                                'product_description',
                                array(
                                    'textarea_name' => 'product_description',
                                    'media_buttons' => true,
                                    'textarea_rows' => 10,
                                    'teeny'         => false,
                                    'quicktags'     => true,
                                )
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="product_type"><?php esc_html_e( 'Product Type', 'product-importer-plugin' ); ?></label></th>
                        <td>
                            <select id="product_type" name="product_type" required>
                                <option value="simple"><?php esc_html_e( 'Single Product', 'product-importer-plugin' ); ?></option>
                                <option value="variable"><?php esc_html_e( 'Variant Product', 'product-importer-plugin' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr id="attributes_row" style="display: none;">
                        <th scope="row"><?php esc_html_e( 'Product Attributes', 'product-importer-plugin' ); ?></th>
                        <td>
                            <div id="attributes_container">
                                <div class="attribute-group">
                                    <select class="attribute-taxonomy" name="attribute_taxonomy[]">
                                        <option value=""><?php esc_html_e( 'Select Attribute', 'product-importer-plugin' ); ?></option>
                                        <?php
                                        $attribute_taxonomies = wc_get_attribute_taxonomies();
                                        if ( $attribute_taxonomies ) {
                                            foreach ( $attribute_taxonomies as $taxonomy ) {
                                                echo '<option value="pa_' . esc_attr( $taxonomy->attribute_name ) . '">' . esc_html( $taxonomy->attribute_label ) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                    <button type="button" class="button remove-attribute-group"><?php esc_html_e( 'Remove', 'product-importer-plugin' ); ?></button>
                                </div>
                            </div>
                            <button type="button" id="add-attribute" class="button"><?php esc_html_e( 'Add Another Attribute', 'product-importer-plugin' ); ?></button>
                            <div id="variants-preview" style="margin-top: 20px; display: none;">
                                <h4><?php esc_html_e( 'Generated Variants Preview:', 'product-importer-plugin' ); ?></h4>
                                <div id="variants-table"></div>
                            </div>
                        </td>
                    </tr>
                </table>
                <div id="feedback-message"></div>
                <?php submit_button( esc_html__( 'Import', 'product-importer-plugin' ) ); ?>
            </form>
        </div>

        <div id="advanced-manual-upload" class="tab-content" style="display: none;">
            <?php
            $advanced_categories = get_terms( array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            ) );
            $advanced_tags = get_terms( array(
                'taxonomy'   => 'product_tag',
                'hide_empty' => false,
            ) );
            $brand_taxonomy = null;
            foreach ( array( 'brand', 'product_brand', 'pa_brand' ) as $possible_brand_taxonomy ) {
                if ( taxonomy_exists( $possible_brand_taxonomy ) ) {
                    $brand_taxonomy = $possible_brand_taxonomy;
                    break;
                }
            }
            $advanced_brands = $brand_taxonomy ? get_terms( array(
                'taxonomy'   => $brand_taxonomy,
                'hide_empty' => false,
            ) ) : array();
            ?>
            <form id="advanced-product-import-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'product_importer_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="advanced_product_images"><?php esc_html_e( 'Upload Images', 'product-importer-plugin' ); ?></label></th>
                        <td>
                            <input type="file" id="advanced_product_images" name="advanced_product_images[]" webkitdirectory multiple accept="image/*" />
                            <p class="description"><?php esc_html_e( 'Upload folders of images. The first image in each folder becomes the featured image and the rest become gallery images.', 'product-importer-plugin' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Preview & Taxonomies', 'product-importer-plugin' ); ?></th>
                        <td>
                            <div id="advanced-image-preview" style="margin-top: 20px; display: none;">
                                <table class="widefat striped" style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Image', 'product-importer-plugin' ); ?></th>
                                            <th><?php esc_html_e( 'Product Name', 'product-importer-plugin' ); ?></th>
                                            <th><?php esc_html_e( 'Gallery Images', 'product-importer-plugin' ); ?></th>
                                            <th><?php esc_html_e( 'Category', 'product-importer-plugin' ); ?></th>
                                            <th><?php esc_html_e( 'Brand', 'product-importer-plugin' ); ?></th>
                                            <th><?php esc_html_e( 'Tag', 'product-importer-plugin' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="advanced-image-preview-body"></tbody>
                                </table>
                            </div>
                            <div id="advanced-image-preview-templates" style="display: none;">
                                <div id="advanced-category-template" class="pip-multiselect-template">
                                    <select class="pip-multiselect-select" multiple data-placeholder="<?php esc_attr_e( 'Select categories', 'product-importer-plugin' ); ?>" style="width:100%;">
                                        <?php foreach ( $advanced_categories as $category ) : ?>
                                            <option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="advanced-brand-template" class="pip-multiselect-template">
                                    <select class="pip-multiselect-select" multiple data-placeholder="<?php esc_attr_e( 'Select brands', 'product-importer-plugin' ); ?>" style="width:100%;">
                                        <?php if ( ! empty( $advanced_brands ) ) : ?>
                                            <?php foreach ( $advanced_brands as $brand ) : ?>
                                                <option value="<?php echo esc_attr( $brand->term_id ); ?>"><?php echo esc_html( $brand->name ); ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div id="advanced-tag-template" class="pip-multiselect-template">
                                    <select class="pip-multiselect-select" multiple data-placeholder="<?php esc_attr_e( 'Select tags', 'product-importer-plugin' ); ?>" style="width:100%;">
                                        <?php foreach ( $advanced_tags as $tag ) : ?>
                                            <option value="<?php echo esc_attr( $tag->term_id ); ?>"><?php echo esc_html( $tag->name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="advanced_original_price"><?php esc_html_e( 'Original Price', 'product-importer-plugin' ); ?></label></th>
                        <td><input type="text" id="advanced_original_price" name="original_price" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="advanced_sale_price"><?php esc_html_e( 'Sale Price', 'product-importer-plugin' ); ?></label></th>
                        <td><input type="text" id="advanced_sale_price" name="sale_price" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="advanced_product_description"><?php esc_html_e( 'Product Description', 'product-importer-plugin' ); ?></label></th>
                        <td>
                            <?php
                            wp_editor(
                                '',
                                'advanced_product_description',
                                array(
                                    'textarea_name' => 'product_description',
                                    'media_buttons' => true,
                                    'textarea_rows' => 10,
                                    'teeny'         => false,
                                    'quicktags'     => true,
                                )
                            );
                            ?>
                        </td>
                    </tr>
                </table>
                <div id="advanced-feedback-message"></div>
                <?php submit_button( esc_html__( 'Import with Taxonomies', 'product-importer-plugin' ) ); ?>
            </form>
        </div>

        <div id="excel-import" class="tab-content" style="display: none;">
            <form id="excel-import-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'excel_importer_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Download Template', 'product-importer-plugin' ); ?></th>
                        <td>
                            <a href="<?php echo esc_url( add_query_arg( 'download_excel_template', '1' ) ); ?>" class="button">
                                <?php esc_html_e( 'Download Excel Template', 'product-importer-plugin' ); ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="excel_file"><?php esc_html_e( 'Upload Excel File', 'product-importer-plugin' ); ?></label></th>
                        <td>
                            <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls" required />
                            <p class="description">
                                <?php esc_html_e( 'Upload your Excel file with product data. Make sure to follow the template format.', 'product-importer-plugin' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <?php submit_button( esc_html__( 'Upload Excel', 'product-importer-plugin' ) ); ?>
                        </td>
                    </tr>
                </table>
                <div id="excel-feedback-message"></div>
            </form>
            <div id="excel-preview-container"></div>
        </div>
        <div id="lencam-import" class="tab-content" style="display: none;">
            <form id="lencam-import-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'lencam_importer_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="lencam_excel_file"><?php esc_html_e( 'Upload Excel File', 'product-importer-plugin' ); ?></label></th>
                        <td>
                            <input type="file" id="lencam_excel_file" name="lencam_excel_file" accept=".xlsx,.xls" required />
                            <p class="description">
                                <?php esc_html_e( 'Excel must contain columns: Product Name (A), Product Slug (B), Image Link (C), Product Description (D), Original Price (E), Sale Price (F).', 'product-importer-plugin' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lencam_product_category"><?php esc_html_e( 'Product Category', 'product-importer-plugin' ); ?></label></th>
                        <td>
                            <?php
                            $categories = get_terms( array(
                                'taxonomy' => 'product_cat',
                                'hide_empty' => false,
                            ) );
                            ?>
                            <select id="lencam_product_category" name="lencam_product_category" required>
                                <option value=""><?php esc_html_e( 'Select a category', 'product-importer-plugin' ); ?></option>
                                <?php foreach ( $categories as $category ) : ?>
                                    <option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lencam_default_description"><?php esc_html_e( 'Default Product Description', 'product-importer-plugin' ); ?></label></th>
                        <td>
                            <?php
                            wp_editor(
                                '',
                                'lencam_default_description',
                                array(
                                    'textarea_name' => 'default_description',
                                    'media_buttons' => true,
                                    'textarea_rows' => 10,
                                    'teeny'         => false,
                                    'quicktags'     => true,
                                )
                            );
                            ?>
                            <p class="description"><?php esc_html_e( 'This description will be used if the Excel file does not contain a description for a product.', 'product-importer-plugin' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lencam_default_original_price"><?php esc_html_e( 'Default Original Price', 'product-importer-plugin' ); ?></label></th>
                        <td>
                            <input type="number" id="lencam_default_original_price" name="default_original_price" step="0.01" min="0" />
                            <p class="description"><?php esc_html_e( 'This price will be used if the Excel file does not contain an original price for a product.', 'product-importer-plugin' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lencam_default_sale_price"><?php esc_html_e( 'Default Sale Price', 'product-importer-plugin' ); ?></label></th>
                        <td>
                            <input type="number" id="lencam_default_sale_price" name="default_sale_price" step="0.01" min="0" />
                            <p class="description"><?php esc_html_e( 'This price will be used if the Excel file does not contain a sale price for a product.', 'product-importer-plugin' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <?php submit_button( esc_html__( 'Upload Excel', 'product-importer-plugin' ) ); ?>
                        </td>
                    </tr>
                </table>
                <div id="lencam-feedback-message"></div>
            </form>
            <div id="lencam-preview-container"></div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Tab switching
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Show target content
            $('.tab-content').hide();
            $(target).show();
        });

        // Handle Excel file upload
        $('#excel-import-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            formData.append('action', 'pip_handle_excel_import');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response && response.success) {
                        // Show preview table
                        var products = response.data.imported_products;
                        var previewHtml = '<div class="excel-preview-container">';
                        previewHtml += '<h3><?php esc_html_e('Products to be imported:', 'product-importer-plugin'); ?></h3>';
                        previewHtml += '<table class="widefat">';
                        previewHtml += '<thead><tr>';
                        previewHtml += '<th><?php esc_html_e('Product Name', 'product-importer-plugin'); ?></th>';
                        previewHtml += '<th><?php esc_html_e('Original Price', 'product-importer-plugin'); ?></th>';
                        previewHtml += '<th><?php esc_html_e('Sale Price', 'product-importer-plugin'); ?></th>';
                        previewHtml += '<th><?php esc_html_e('Category', 'product-importer-plugin'); ?></th>';
                        previewHtml += '<th><?php esc_html_e('Description', 'product-importer-plugin'); ?></th>';
                        previewHtml += '<th><?php esc_html_e('Product Image', 'product-importer-plugin'); ?></th>';
                        previewHtml += '<th><?php esc_html_e('Gallery Images', 'product-importer-plugin'); ?></th>';
                        previewHtml += '</tr></thead><tbody>';

                        products.forEach(function(product) {
                            previewHtml += '<tr>';
                            previewHtml += '<td>' + product.name + '</td>';
                            previewHtml += '<td>' + product.original_price + '</td>';
                            previewHtml += '<td>' + product.sale_price + '</td>';
                            previewHtml += '<td>' + product.category + '</td>';
                            previewHtml += '<td>' + product.description + '</td>';
                            previewHtml += '<td>';
                            if (product.product_image) {
                                previewHtml += '<img src="' + product.product_image + '" style="max-width: 50px; height: auto;">';
                            }
                            previewHtml += '</td>';
                            previewHtml += '<td>';
                            if (product.gallery_images && product.gallery_images.length > 0) {
                                product.gallery_images.forEach(function(image) {
                                    previewHtml += '<img src="' + image + '" style="max-width: 50px; height: auto; margin-right: 5px;">';
                                });
                            }
                            previewHtml += '</td>';
                            previewHtml += '</tr>';
                        });

                        previewHtml += '</tbody></table>';
                        previewHtml += '<div class="excel-preview-actions">';
                        previewHtml += '<button type="button" class="button button-primary" id="confirm-import"><?php esc_html_e('Confirm Import', 'product-importer-plugin'); ?></button>';
                        previewHtml += '<button type="button" class="button" id="cancel-import"><?php esc_html_e('Cancel', 'product-importer-plugin'); ?></button>';
                        previewHtml += '</div></div>';

                        $('#excel-preview-container').html(previewHtml);
                        $('#excel-feedback-message').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    } else {
                        console.log(response);
                        $('#excel-feedback-message').html('<div class="notice notice-error"><p>' + (response.data ? response.data.message : 'Error importing products.') + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#excel-feedback-message').html('<div class="notice notice-error"><p>Error: ' + error + '</p></div>');
                }
            });
        });

        // Handle confirm import
        $(document).on('click', '#confirm-import', function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pip_confirm_excel_import',
                    nonce: '<?php echo wp_create_nonce('pip_confirm_import'); ?>'
                },
                success: function(response) {
                    try {
                        if (response && response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            var errorMessage = response && response.data && response.data.message ? response.data.message : 'Error importing products.';
                            alert(errorMessage);
                        }
                    } catch (error) {
                        console.error('Error processing response:', error);
                        alert('Error processing response. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    alert('Error importing products. Please try again.');
                }
            });
        });

        // Handle cancel import
        $(document).on('click', '#cancel-import', function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pip_cancel_excel_import',
                    nonce: '<?php echo wp_create_nonce('pip_cancel_import'); ?>'
                },
                success: function() {
                    location.reload();
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    alert('Error canceling import. Please try again.');
                }
            });
        });

        // Handle LENCAM file upload
        $('#lencam-import-form').on('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            formData.append('action', 'pip_handle_lencam_import');
            formData.append('product_category', $('#lencam_product_category').val());
            formData.append('default_description', $('#lencam_default_description').val());
            formData.append('default_original_price', $('#lencam_default_original_price').val());
            formData.append('default_sale_price', $('#lencam_default_sale_price').val());
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response && response.success) {
                        var products = response.data.imported_products;
                        var previewHtml = '<div class="excel-preview-container">';
                        previewHtml += '<h3><?php esc_html_e('Products to be imported:', 'product-importer-plugin'); ?></h3>';
                        previewHtml += '<table class="widefat">';
                        previewHtml += '<thead><tr>';
                        previewHtml += '<th><?php esc_html_e('Product Name', 'product-importer-plugin'); ?></th>';
                        previewHtml += '<th><?php esc_html_e('Product Slug', 'product-importer-plugin'); ?></th>';
                        previewHtml += '<th><?php esc_html_e('Image Link', 'product-importer-plugin'); ?></th>';
                        previewHtml += '<th><?php esc_html_e('Product Description', 'product-importer-plugin'); ?></th>';
                        previewHtml += '<th><?php esc_html_e('Original Price', 'product-importer-plugin'); ?></th>';
                        previewHtml += '<th><?php esc_html_e('Sale Price', 'product-importer-plugin'); ?></th>';
                        previewHtml += '</tr></thead><tbody>';
                        products.forEach(function(product) {
                            previewHtml += '<tr>';
                            previewHtml += '<td>' + product.name + '</td>';
                            previewHtml += '<td>' + product.slug + '</td>';
                            previewHtml += '<td>' + (product.image ? '<img src="' + product.image + '" style="max-width: 50px; height: auto;" />' : '') + '</td>';
                            previewHtml += '<td>' + (product.description || '<?php esc_html_e('Will use default', 'product-importer-plugin'); ?>') + '</td>';
                            previewHtml += '<td>' + (product.original_price > 0 ? product.original_price : '<?php esc_html_e('Will use default', 'product-importer-plugin'); ?>') + '</td>';
                            previewHtml += '<td>' + (product.sale_price > 0 ? product.sale_price : '<?php esc_html_e('Will use default', 'product-importer-plugin'); ?>') + '</td>';
                            previewHtml += '</tr>';
                        });
                        previewHtml += '</tbody></table>';
                        previewHtml += '<div class="excel-preview-actions">';
                        previewHtml += '<button type="button" class="button button-primary" id="lencam-confirm-import"><?php esc_html_e('Confirm Import', 'product-importer-plugin'); ?></button>';
                        previewHtml += '<button type="button" class="button" id="lencam-cancel-import"><?php esc_html_e('Cancel', 'product-importer-plugin'); ?></button>';
                        previewHtml += '</div></div>';
                        $('#lencam-preview-container').html(previewHtml);
                        $('#lencam-feedback-message').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    } else {
                        $('#lencam-feedback-message').html('<div class="notice notice-error"><p>' + (response && response.data ? response.data.message : 'Error importing products.') + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#lencam-feedback-message').html('<div class="notice notice-error"><p>Error: ' + error + '</p></div>');
                }
            });
        });

        // LENCAM confirm import
        $(document).on('click', '#lencam-confirm-import', function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pip_confirm_lencam_import',
                    nonce: '<?php echo wp_create_nonce('pip_confirm_lencam_import'); ?>',
                    product_category: $('#lencam_product_category').val(),
                    default_description: $('#lencam_default_description').val(),
                    default_original_price: $('#lencam_default_original_price').val(),
                    default_sale_price: $('#lencam_default_sale_price').val()
                },
                success: function(response) {
                    try {
                        if (response && response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            var errorMessage = response && response.data && response.data.message ? response.data.message : 'Error importing products.';
                            alert(errorMessage);
                        }
                    } catch (error) {
                        console.error('Error processing response:', error);
                        alert('Error processing response. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    alert('Error importing products. Please try again.');
                }
            });
        });

        // LENCAM cancel import
        $(document).on('click', '#lencam-cancel-import', function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pip_cancel_lencam_import',
                    nonce: '<?php echo wp_create_nonce('pip_cancel_lencam_import'); ?>'
                },
                success: function() {
                    location.reload();
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    alert('Error canceling import. Please try again.');
                }
            });
        });

        // Multiselect: toggle panel, search, and update selected labels
        // Initialize Select2 on elements
        function initSelect2On($els) {
            if ( typeof $.fn.select2 !== 'function' ) return;
            $els.each(function() {
                var $s = $(this);
                if ( $s.data('select2') ) return;
                $s.select2({
                    placeholder: $s.data('placeholder') || '',
                    allowClear: true,
                    width: 'resolve'
                });
            });
        }

        // Initialize existing template selects
        initSelect2On( $('.pip-multiselect-select') );

        // Initialize when focused (for dynamically inserted rows)
        $(document).on('focus', '.pip-multiselect-select', function() {
            initSelect2On( $(this) );
        });
    });
    </script>
    <?php
}
?>