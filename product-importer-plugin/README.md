# Product Importer Plugin

A WordPress plugin for importing products into WooCommerce with support for both single and variant products.

## Features

- **Manual Upload**: Upload product images and set product details manually
- **Excel Import**: Import products from Excel files using templates
- **Product Type Support**: 
  - Single Products: Standard WooCommerce simple products
  - Variant Products: Products with multiple variations based on attributes
- **Attribute Management**: Select product attributes and values from WooCommerce
- **Automatic Variant Generation**: Automatically creates all possible variant combinations
- **JSON Export/Import**: Save and restore product configurations
- **Image Management**: Support for product images and gallery images

## Installation

1. Upload the plugin files to `/wp-content/plugins/product-importer-plugin/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce is installed and activated
4. Enter your license key in the plugin settings

## Usage

### Manual Upload

1. Go to **Import Products** in your WordPress admin
2. Select **Manual Upload** tab
3. Choose **Product Type**:
   - **Single Product**: Standard product import (no changes to existing behavior)
   - **Variant Product**: Product with multiple variations

#### For Variant Products:

1. Select **Variant Product** as the product type
2. Click **Add Another Attribute** to add product attributes
3. Select an attribute (e.g., Color, Size) from the dropdown
4. Attribute values will automatically load from your WooCommerce store
5. Check the attribute values you want to use
6. Repeat for additional attributes if needed
7. The plugin will automatically generate a preview of all possible variants
8. **Customize Variant Prices**: Each variant shows input fields for Original Price and Sale Price
   - Default values are automatically filled from the main form
   - You can customize individual variant prices as needed
   - If a variant price is set to 0 or left empty, it will use the default price from the main form
9. Each variant will inherit the Stock Status (default: "In Stock")

### Excel Import

1. Download the Excel template
2. Fill in your product data
3. Upload the completed Excel file
4. Review the preview and confirm import

### JSON Export/Import

- **Export**: Save your current product configuration to a JSON file
- **Import**: Restore a previously saved configuration from a JSON file

## API Integration

The plugin integrates with WooCommerce's attribute system and fetches attribute values from:
```
https://demo3.lazyecommerce.com/wp-admin/admin-ajax.php?taxonomy={taxonomy}&limit=50&orderby=menu_order&action=woocommerce_json_search_taxonomy_terms&security=2bd47971a1
```

## Variant Generation

When creating variant products, the plugin:

1. Generates all possible combinations of selected attributes
2. Creates individual variation products in WooCommerce
3. Sets prices and stock status for each variant
4. Maintains proper parent-child relationships

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.4 or higher
- Valid plugin license

## Troubleshooting

### Product Attributes Not Displaying

If product attributes are not showing up in the WooCommerce product page:

1. **Check Product Type**: Ensure the product is set to "Variable Product" type
2. **Clear Cache**: Clear WooCommerce cache and WordPress cache
3. **Check Attributes**: Verify that attributes were created with proper taxonomy names
4. **Debug Mode**: Check WordPress error logs for any error messages

### Variants Not Showing Values

If variants are created but don't display attribute values:

1. **Attribute Terms**: Ensure attribute terms are properly assigned to the product
2. **Variation Attributes**: Check that variation attributes are set correctly
3. **Product Cache**: Force refresh the product by editing and saving it
4. **WooCommerce Version**: Ensure you're using WooCommerce 3.0 or higher

### Error Creating Product Variants

If you encounter "Error creating product variants":

1. **Check Error Logs**: Look at WordPress error logs for detailed error messages
2. **Validate Input Data**: Ensure all required fields are filled correctly
3. **Check WooCommerce**: Verify WooCommerce is active and up to date
4. **Test with Simple Data**: Try creating a product with minimal attributes first

#### Debug Steps:

1. **Enable WordPress Debug**: Add to wp-config.php:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. **Check Error Logs**: Look in `/wp-content/debug.log`

3. **Verify Data Structure**: Ensure attributes have correct format:
   ```php
   array(
       'taxonomy' => 'pa_color',
       'values' => array(
           array('id' => 1, 'slug' => 'red', 'name' => 'Red')
       )
   )
   ```

4. **Test Function**: Use the test function in helpers.php to debug:
   ```php
   $result = pip_test_variant_creation($product_id);
   var_dump($result);
   ```

### Common Issues and Solutions

- **Attributes not visible**: Check if `_product_attributes` meta is properly set
- **Variants missing**: Ensure `product_type` is set to 'variable'
- **Price not updating**: Clear WooCommerce transients and product cache
- **Import errors**: Check that all required fields are filled correctly
- **WooCommerce functions not available**: Ensure WooCommerce plugin is active

## Support

For support and feature requests, please contact the plugin developer.

## Changelog

### Version 2.0
- Added product type selection (Single/Variant)
- Added attribute management for variant products
- Added automatic variant generation
- Added variants preview
- Enhanced JSON export/import functionality
- Improved user interface and styling

### Version 1.0
- Initial release with manual upload and Excel import
- Basic product import functionality