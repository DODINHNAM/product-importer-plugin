<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

function pip_generate_excel_template() {
    // Create new Spreadsheet object
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Products');

    // Set column headers
    $headers = [
        'A1' => 'Product Name',
        'B1' => 'Original Price',
        'C1' => 'Sale Price',
        'D1' => 'Category (comma-separated)',
        'E1' => 'Description',
        'F1' => 'Product Image URL',
        'G1' => 'Tags (comma-separated)',
        'H1' => 'Brand (comma-separated)',
    ];

    // Set headers
    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }

    // Get all product categories for dropdown
    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ]);

    $categoryList = [];
    foreach ($categories as $category) {
        $categoryList[] = $category->name;
    }

    // Create dropdown for Category column
    $categoryValidation = $sheet->getCell('D2')->getDataValidation();
    $categoryValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
    $categoryValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
    $categoryValidation->setAllowBlank(false);
    $categoryValidation->setShowInputMessage(true);
    $categoryValidation->setShowErrorMessage(true);
    $categoryValidation->setShowDropDown(true);
    $categoryValidation->setFormula1('"' . implode(',', $categoryList) . '"');

    // Apply validation to all cells in column D
    $sheet->setDataValidation('D2:D1000', $categoryValidation);

    // Add example row
    $exampleData = [
        'A2' => 'Example Product',
        'B2' => '100',
        'C2' => '80',
        'D2' => !empty($categoryList) ? implode(',', array_slice($categoryList, 0, 2)) : '',
        'E2' => '<p>Product description with HTML support</p>',
        'F2' => 'https://example.com/product-image.jpg',
        'G2' => 'tag1, tag2',
        'H2' => !empty($categoryList) ? ($categoryList[0] ?? '') : '',
    ];

    foreach ($exampleData as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(30);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(20);
    $sheet->getColumnDimension('E')->setWidth(40);
    $sheet->getColumnDimension('F')->setWidth(40);
    $sheet->getColumnDimension('G')->setWidth(30);
    $sheet->getColumnDimension('H')->setWidth(30);

    // Style the header row
    $headerStyle = [
        'font' => [
            'bold' => true,
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => [
                'rgb' => 'CCCCCC',
            ],
        ],
    ];
    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

    // Create Categories sheet
    $categoriesSheet = $spreadsheet->createSheet();
    $categoriesSheet->setTitle('Categories');
    $categoriesSheet->setCellValue('A1', 'Category Name');
    $categoriesSheet->setCellValue('B1', 'Category ID');
    $categoriesSheet->getStyle('A1:B1')->applyFromArray($headerStyle);
    $categoriesSheet->getColumnDimension('A')->setWidth(40);
    $categoriesSheet->getColumnDimension('B')->setWidth(15);

    // Populate categories list
    $row = 2;
    foreach ($categories as $category) {
        $categoriesSheet->setCellValue('A' . $row, $category->name);
        $categoriesSheet->setCellValue('B' . $row, $category->term_id);
        $row++;
    }

    // Create Brands sheet (populate if a brand-like taxonomy exists)
    $brand_taxonomy = null;
    foreach (array('brand', 'product_brand', 'pa_brand') as $t) {
        if (taxonomy_exists($t)) {
            $brand_taxonomy = $t;
            break;
        }
    }

    $brandsSheet = $spreadsheet->createSheet();
    $brandsSheet->setTitle('Brands');
    $brandsSheet->setCellValue('A1', 'Brand Name');
    $brandsSheet->setCellValue('B1', 'Brand ID');
    $brandsSheet->getStyle('A1:B1')->applyFromArray($headerStyle);
    $brandsSheet->getColumnDimension('A')->setWidth(40);
    $brandsSheet->getColumnDimension('B')->setWidth(15);

    $brow = 2;
    if ($brand_taxonomy) {
        $brand_terms = get_terms([
            'taxonomy' => $brand_taxonomy,
            'hide_empty' => false,
        ]);
        if (!is_wp_error($brand_terms)) {
            foreach ($brand_terms as $bt) {
                $brandsSheet->setCellValue('A' . $brow, $bt->name);
                $brandsSheet->setCellValue('B' . $brow, $bt->term_id);
                $brow++;
            }
        }
    }

    // Create the Excel file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="product-import-template.xlsx"');
    header('Cache-Control: max-age=0');

    // Save file to output
    $writer->save('php://output');
    exit;
}

// Hook to handle template download
add_action('admin_init', function() {
    if (isset($_GET['download_excel_template']) && $_GET['download_excel_template'] === '1') {
        pip_generate_excel_template();
    }
}); 