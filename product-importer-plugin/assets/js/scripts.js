function handleProductImagesChange(e) {
    const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp']; // Các định dạng ảnh hợp lệ
    const files = e.target.files;
    const previewBody = document.getElementById('image-preview-body');
    const hiddenInputsContainer = document.getElementById('hidden-inputs-container'); // Container cho các input file ẩn
    previewBody.innerHTML = ''; // Clear previous preview
    hiddenInputsContainer.innerHTML = ''; // Clear previous hidden inputs
    const invalidFiles = [];
    const folderMap = {}; // Map để nhóm file theo root1

    // Group files by root1
    Array.from(files).forEach(file => {
        const fileExtension = file.name.split('.').pop().toLowerCase();
        // Kiểm tra định dạng file
        if (!allowedExtensions.includes(fileExtension)) {
            invalidFiles.push(file.name);
        }
        const pathParts = file.webkitRelativePath.split('/');
        const root1 = pathParts.length > 1 ? pathParts[1] : null; // root-1

        if (root1) {
            if (!folderMap[root1]) {
                folderMap[root1] = [];
            }
            folderMap[root1].push(file);
        }
    });

    // Nếu có file không hợp lệ, hiển thị thông báo và reset input
    if (invalidFiles.length > 0) {
        alert('The following files are not valid image formats: ' + invalidFiles.join(', '));
        e.target.value = ''; // Reset input nếu có file không hợp lệ
        return;
    }

    // Hiển thị thông báo nếu không có folder
    if (Object.keys(folderMap).length === 0) {
        alert('Please upload files inside folders.');
        e.target.value = ''; // Reset input nếu không có folder
        return;
    }


    const includeImageInGallery = document.getElementById('include_image_in_gallery')?.checked || false;

    // Render table rows
    Object.keys(folderMap).forEach(root1 => {
        const files = folderMap[root1];
        const allGalleryImages = [];
        let productImage = null;

        if (files.length > 0) {
            productImage = files[0]; // Ảnh đầu tiên là product image
            if (includeImageInGallery) {
                allGalleryImages.push(productImage); // Cũng thêm ảnh product image vào gallery
            }
            allGalleryImages.push(...files.slice(1)); // Các ảnh còn lại là gallery
        }

        // Xác định tên hiển thị: nếu có folder thì là tên folder, không thì là tên ảnh
        const displayName = root1 ? root1 : (productImage ? productImage.name : '');

        // Hiển thị thông tin trong bảng
        if (productImage) {
            const reader = new FileReader();
            reader.onload = function (event) {
                const row = document.createElement('tr');
                const galleryCell = document.createElement('td');

                // Thêm input file ẩn cho productImage
                const productImageInput = document.createElement('input');
                productImageInput.type = 'file';
                productImageInput.name = `product-image_${displayName}`;
                productImageInput.style.display = 'none';
                productImageInput.files = createFileList([productImage]);
                hiddenInputsContainer.appendChild(productImageInput);

                // Thêm input file ẩn cho từng ảnh trong gallery
                allGalleryImages.forEach((file, index) => {
                    const galleryInput = document.createElement('input');
                    galleryInput.type = 'file';
                    galleryInput.name = `product-gallery_${displayName}_${index}`;
                    galleryInput.style.display = 'none';
                    galleryInput.files = createFileList([file]);
                    hiddenInputsContainer.appendChild(galleryInput);

                    // Hiển thị ảnh gallery trong bảng
                    const galleryReader = new FileReader();
                    galleryReader.onload = function (event) {
                        const img = document.createElement('img');
                        img.src = event.target.result;
                        img.alt = file.name;
                        img.style = 'width: 100px; height: auto; margin-right: 10px;';
                        galleryCell.appendChild(img);
                    };
                    galleryReader.readAsDataURL(file);
                });

                row.innerHTML = `
                    <td><img src="${event.target.result}" alt="${productImage.name}" style="width: 100px; height: auto;" /></td>
                    <td>${displayName}</td>
                `;
                row.appendChild(galleryCell);
                previewBody.appendChild(row);
            };
            reader.readAsDataURL(productImage);
        }
    });
}

document.getElementById('product_images').addEventListener('change', handleProductImagesChange);

// Re-render the preview if the user toggles the gallery option after already picking images
document.getElementById('include_image_in_gallery').addEventListener('change', function () {
    const productImagesInput = document.getElementById('product_images');
    if (productImagesInput.files.length > 0) {
        handleProductImagesChange({ target: productImagesInput });
    }
});

// Hàm tạo FileList từ một mảng file
function createFileList(files) {
    const dataTransfer = new DataTransfer();
    files.forEach(file => dataTransfer.items.add(file));
    return dataTransfer.files;
}

// Gửi dữ liệu từ bảng đến PHP khi submit form
document.getElementById('product-import-form').addEventListener('submit', function (e) {
    e.preventDefault(); // Ngăn chặn hành động submit mặc định

    const hiddenInputsContainer = document.getElementById('hidden-inputs-container'); // Container chứa các input file ẩn
    const inputs = hiddenInputsContainer.querySelectorAll('input[type="file"]'); // Lấy tất cả input file
    const productData = {};

    // Thu thập dữ liệu từ các input file ẩn
    inputs.forEach(input => {
        const nameParts = input.name.split('_'); // Tách tên input để xác định productImage hoặc productGallery
        const root1 = nameParts[1]; // Lấy root1 từ tên input
        const type = nameParts[0]; // Xác định loại (product_image hoặc product_gallery)

        if (!productData[root1]) {
            const productName = root1.replace(/\.[^/.]+$/, ''); // Loại bỏ phần mở rộng (ví dụ: .jpg, .png)
            productData[root1] = {
                productImage: null,
                productGallery: [],
                productName: productName // Tên sản phẩm là productName
            };
        }

        if (type === 'product-image' && input.files.length > 0) {
            productData[root1].productImage = input.files[0]; // Lưu file của productImage
        } else if (type === 'product-gallery' && input.files.length > 0) {
            productData[root1].productGallery.push(input.files[0]); // Lưu file của productGallery
        }
    });

    // Thu thập dữ liệu từ giao diện
    const originalPrice = document.getElementById('original_price')?.value.trim() || ''; // Giá gốc
    const salePrice = document.getElementById('sale_price')?.value.trim() || ''; // Giá khuyến mãi
    const productCategory = document.getElementById('product_category')?.value.trim() || ''; // Danh mục sản phẩm
    const productImages = document.getElementById('product_images').files;
    const productType = document.getElementById('product_type')?.value || 'simple'; // Loại sản phẩm

    let productDescription = '';
    if (typeof tinymce !== 'undefined') {
        const editor = tinymce.get('product_description'); // Lấy trình biên soạn TinyMCE
        if (editor && !editor.isHidden()) {
            productDescription = editor.getContent().trim(); // Lấy nội dung từ TinyMCE
        } else {
            // Nếu TinyMCE không được khởi tạo, lấy giá trị từ textarea
            productDescription = document.getElementById('product_description')?.value.trim() || '';
        }
    } else {
        // Nếu TinyMCE không được tải, lấy giá trị từ textarea
        productDescription = document.getElementById('product_description')?.value.trim() || '';
    }
    
    // Thu thập dữ liệu attributes nếu là variant product
    let productAttributes = [];
    let variantPrices = [];
    
    if (productType === 'variable') {
        const attributeGroups = document.querySelectorAll('.attribute-group');
        attributeGroups.forEach(group => {
            const taxonomy = group.querySelector('.attribute-taxonomy').value;
            const selectedValues = group.querySelectorAll('input[type="checkbox"]:checked');
            
            if (taxonomy && selectedValues.length > 0) {
                const values = Array.from(selectedValues).map(checkbox => ({
                    id: checkbox.value,
                    slug: checkbox.dataset.slug,
                    name: checkbox.dataset.name
                }));
                
                productAttributes.push({
                    taxonomy: taxonomy,
                    values: values
                });
            }
        });
        
        // Collect variant prices and ensure all variants are captured
        const variantOriginalPrices = document.querySelectorAll('.variant-original-price');
        const variantSalePrices = document.querySelectorAll('.variant-sale-price');
        
        if (variantOriginalPrices.length > 0) {
            variantOriginalPrices.forEach((input, index) => {
                const originalPrice = parseFloat(input.value) || 0;
                const salePrice = parseFloat(variantSalePrices[index]?.value) || 0;
                
                variantPrices.push({
                    original_price: originalPrice,
                    sale_price: salePrice
                });
            });
            
            console.log('Collected variant prices from inputs:', variantPrices);
        } else {
            // Try to get variant data from form dataset
            const form = document.getElementById('product-import-form');
            if (form && form.dataset.variantPrices) {
                try {
                    variantPrices = JSON.parse(form.dataset.variantPrices);
                    console.log('Collected variant prices from form data:', variantPrices);
                } catch (e) {
                    console.error('Error parsing variant prices from form data:', e);
                }
            }
            
            // If still no variant prices, create default ones
            if (variantPrices.length === 0 && productAttributes.length > 0) {
                const defaultOriginalPrice = parseFloat(document.getElementById('original_price').value) || 0;
                const defaultSalePrice = parseFloat(document.getElementById('sale_price').value) || 0;
                
                const totalCombinations = productAttributes.reduce((total, attr) => total * attr.values.length, 1);
                for (let i = 0; i < totalCombinations; i++) {
                    variantPrices.push({
                        original_price: defaultOriginalPrice,
                        sale_price: defaultSalePrice
                    });
                }
                
                console.log('Created default variant prices:', variantPrices);
            }
        }
        
        // Validate that we have the right number of variants
        if (productAttributes.length > 0) {
            const expectedVariants = productAttributes.reduce((total, attr) => total * attr.values.length, 1);
            if (variantPrices.length !== expectedVariants) {
                console.warn(`Expected ${expectedVariants} variants but found ${variantPrices.length} prices`);
            }
        }
        
        // Validate attributes for variant products
        if (productType === 'variable' && productAttributes.length === 0) {
            errors.push('At least one attribute with values is required for variant products.');
        }
    }
    
    // Kiểm tra điều kiện validate
    let errors = [];

    // original_price: Bắt buộc và phải là số
    if (!originalPrice || isNaN(originalPrice) || parseFloat(originalPrice) <= 0) {
        errors.push('Original Price is required and must be a valid number greater than 0.');
    }

    // sale_price: Không bắt buộc nhưng nếu có thì phải là số
    if (salePrice && (isNaN(salePrice) || parseFloat(salePrice) < 0)) {
        errors.push('Sale Price must be a valid number greater than or equal to 0.');
    }

    // product_category: Bắt buộc chọn
    if (!productCategory) {
        errors.push('Product Category is required.');
    }

    // product_images: Bắt buộc phải có ít nhất một file
    if (!productImages || productImages.length === 0) {
        errors.push('At least one product image is required.');
    }

    // Hiển thị lỗi nếu có
    if (errors.length > 0) {
        alert(errors.join('\n'));
        return;
    }
    
    // Hiển thị thông báo đang xử lý
    const feedbackMessage = document.getElementById('feedback-message');
    feedbackMessage.innerHTML = 'Importing products, please wait...';

    // Gửi từng sản phẩm qua AJAX
    const results = [];
    const sendProduct = async (product) => {
        const formData = new FormData();
        formData.append('action', 'import_products'); // Action cho AJAX
        formData.append('product_image', product.productImage); // File của Product Image
        formData.append('product_name', product.productName); // Tên sản phẩm
        formData.append('original_price', originalPrice); // Giá gốc
        formData.append('sale_price', salePrice); // Giá khuyến mãi
        formData.append('product_category', productCategory); // Danh mục sản phẩm
        formData.append('product_description', productDescription); // Mô tả sản phẩm
        formData.append('product_type', productType); // Loại sản phẩm

        // Thêm dữ liệu attributes nếu là variant product
        if (productType === 'variable') {
            formData.append('product_attributes', JSON.stringify(productAttributes));
            formData.append('variant_prices', JSON.stringify(variantPrices)); // Thêm giá variant
            
            // Log the data being sent
            console.log('Sending product attributes:', productAttributes);
            console.log('Sending variant prices:', variantPrices);
            console.log('Total variants to create:', variantPrices.length);
        }

        // Thêm từng file trong productGallery vào FormData
        product.productGallery.forEach((file, index) => {
            formData.append(`product_gallery[${index}]`, file);
        });

        try {
            const response = await fetch(ajax_object.ajax_url, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            if (data.success) {
                console.log(`Product "${product.productName}" imported successfully!`);
                results.push(`Product "${product.productName}" imported successfully!`);
            } else {
                console.error(`Error importing product "${product.productName}": ${data.message}`);
                results.push(`Error importing product "${product.productName}": ${data.message}`);
            }
        } catch (error) {
            console.error(`Error importing product "${product.productName}": ${error.message}`);
            results.push(`Error importing product "${product.productName}": ${error.message}`);
        }
    };

    // Sử dụng vòng lặp để gửi từng sản phẩm
    (async () => {
        for (const root1 in productData) {
            await sendProduct(productData[root1]); // Gửi từng sản phẩm
        }

        // Hiển thị kết quả sau khi tất cả sản phẩm được gửi
        feedbackMessage.innerHTML = results.join('<br>');
    })();
});

document.getElementById('export-json').addEventListener('click', function () {
    const originalPrice = document.getElementById('original_price').value.trim();
    const salePrice = document.getElementById('sale_price').value.trim();
    const productCategory = document.getElementById('product_category').value.trim();
    const productType = document.getElementById('product_type').value;
    let productDescription = '';

    if (typeof tinymce !== 'undefined') {
        const editor = tinymce.get('product_description');
        if (editor && !editor.isHidden()) {
            productDescription = editor.getContent().trim();
        } else {
            productDescription = document.getElementById('product_description').value.trim();
        }
    } else {
        productDescription = document.getElementById('product_description').value.trim();
    }

    // Bỏ qua Product Attributes - chỉ export basic fields
    let productAttributes = [];
    let variantPrices = [];

    const jsonData = {
        original_price: originalPrice,
        sale_price: salePrice,
        product_category: productCategory,
        product_description: productDescription,
        product_type: productType,
        product_attributes: productAttributes,
        variant_prices: variantPrices
    };
    
    console.log('Exporting JSON data:', jsonData);

    const jsonString = JSON.stringify(jsonData, null, 2);
    const blob = new Blob([jsonString], { type: 'application/json' });
    const url = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = 'product-data.json';
    a.click();
    URL.revokeObjectURL(url);
});

document.getElementById('import-json-button').addEventListener('click', function () {
    document.getElementById('import-json').click();
});

document.getElementById('import-json').addEventListener('change', function (e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function (event) {
        try {
            const jsonData = JSON.parse(event.target.result);

            // Điền dữ liệu vào form
            document.getElementById('original_price').value = jsonData.original_price || '';
            document.getElementById('sale_price').value = jsonData.sale_price || '';
            document.getElementById('product_category').value = jsonData.product_category || '';
            document.getElementById('product_type').value = jsonData.product_type || 'simple';

            if (typeof tinymce !== 'undefined') {
                const editor = tinymce.get('product_description');
                if (editor && !editor.isHidden()) {
                    editor.setContent(jsonData.product_description || '');
                } else {
                    document.getElementById('product_description').value = jsonData.product_description || '';
                }
            } else {
                document.getElementById('product_description').value = jsonData.product_description || '';
            }

            // Handle product type change to show/hide attributes
            const productTypeEvent = new Event('change');
            document.getElementById('product_type').dispatchEvent(productTypeEvent);

            // Bỏ qua Product Attributes import - chỉ import basic fields
            console.log('Skipping product attributes import - only basic fields imported');

            alert('JSON imported successfully!');
        } catch (error) {
            alert('Invalid JSON file.');
        }
    };

    reader.readAsText(file);
});

// Function to import product attributes
function importProductAttributes(attributes) {
    const container = document.getElementById('attributes_container');
    
    // Clear existing attributes
    container.innerHTML = '';
    
    attributes.forEach((attribute, index) => {
        const newGroup = document.createElement('div');
        newGroup.className = 'attribute-group';
        newGroup.style.marginBottom = '10px';
        
        newGroup.innerHTML = `
            <select class="attribute-taxonomy" name="attribute_taxonomy[]">
                <option value="">Select Attribute</option>
                ${getAttributeOptions()}
            </select>
            <button type="button" class="button add-attribute-values">Add Values</button>
            <button type="button" class="button remove-attribute-group">Remove</button>
        `;
        
        container.appendChild(newGroup);
        
        // Set the selected taxonomy
        const taxonomySelect = newGroup.querySelector('.attribute-taxonomy');
        taxonomySelect.value = attribute.taxonomy;
        
        // Add event listeners
        newGroup.querySelector('.remove-attribute-group').addEventListener('click', function() {
            if (document.querySelectorAll('.attribute-group').length > 1) {
                container.removeChild(newGroup);
                generateVariantsPreview();
            }
        });
        
        // Auto-load values for the selected attribute
        if (attribute.taxonomy) {
            setTimeout(() => {
                handleAttributeSelection(taxonomySelect);
                
                // Set the selected values after loading
                setTimeout(() => {
                    setSelectedAttributeValues(newGroup, attribute.values);
                    console.log('Set selected values for', attribute.taxonomy, ':', attribute.values);
                }, 1500);
            }, 200);
        }
    });
}

// Function to set selected attribute values
function setSelectedAttributeValues(group, values) {
    const checkboxes = group.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        const valueId = parseInt(checkbox.value);
        const isSelected = values.some(v => parseInt(v.id) === valueId);
        checkbox.checked = isSelected;
    });
    
    // Trigger variants preview update
    generateVariantsPreview();
}

// Product type selection handler
function initializeProductTypeHandler() {
    const productTypeSelect = document.getElementById('product_type');
    console.log('Product type select element:', productTypeSelect);
    if (productTypeSelect) {
        productTypeSelect.addEventListener('change', function() {
            const productType = this.value;
            const attributesRow = document.getElementById('attributes_row');
            console.log('Product type changed to:', productType);
            console.log('Attributes row element:', attributesRow);
            
            if (productType === 'variable') {
                attributesRow.style.display = 'table-row';
                console.log('Showing attributes row');
            } else {
                attributesRow.style.display = 'none';
                console.log('Hiding attributes row');
                // Clear variants preview
                const variantsPreview = document.getElementById('variants-preview');
                const variantsTable = document.getElementById('variants-table');
                if (variantsPreview) variantsPreview.style.display = 'none';
                if (variantsTable) variantsTable.innerHTML = '';
            }
        });
    }
}

// Add attribute group
function initializeAddAttributeHandler() {
    const addAttributeBtn = document.getElementById('add-attribute');
    if (addAttributeBtn) {
        addAttributeBtn.addEventListener('click', function() {
    const container = document.getElementById('attributes_container');
    const newGroup = document.createElement('div');
    newGroup.className = 'attribute-group';
    newGroup.style.marginBottom = '10px';
    
    newGroup.innerHTML = `
        <select class="attribute-taxonomy" name="attribute_taxonomy[]">
            <option value="">Select Attribute</option>
            ${getAttributeOptions()}
        </select>
        <button type="button" class="button remove-attribute-group">Remove</button>
    `;
    
    container.appendChild(newGroup);
    
    // Add event listeners to new group
    newGroup.querySelector('.attribute-taxonomy').addEventListener('change', function() {
        if (this.value) {
            handleAttributeSelection(this);
        }
    });
    
    newGroup.querySelector('.remove-attribute-group').addEventListener('click', function() {
        container.removeChild(newGroup);
        generateVariantsPreview();
    });
        });
    }
}

// Initialize all event listeners
function initializeAllEventListeners() {
    console.log('Initializing all event listeners...');
    
    // Initialize product type handler
    initializeProductTypeHandler();
    
    // Initialize add attribute handler
    initializeAddAttributeHandler();
    
    // Add event listeners to initial attribute group
    const initialGroup = document.querySelector('.attribute-group');
    if (initialGroup) {
        // Auto-load values when attribute is selected
        initialGroup.querySelector('.attribute-taxonomy').addEventListener('change', function() {
            if (this.value) {
                handleAttributeSelection(this);
            }
        });
        
        initialGroup.querySelector('.remove-attribute-group').addEventListener('click', function() {
            if (document.querySelectorAll('.attribute-group').length > 1) {
                initialGroup.remove();
                generateVariantsPreview();
            }
        });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeAllEventListeners();
});

// Handle attribute selection and auto-load values
function handleAttributeSelection(selectElement) {
    const taxonomy = selectElement.value;
    const container = selectElement.closest('.attribute-group');
    
    if (!taxonomy) {
        // Clear existing values if no attribute selected
        const existingValues = container.querySelector('.attribute-values');
        if (existingValues) {
            existingValues.remove();
        }
        generateVariantsPreview();
        return;
    }
    
    // Add loading state
    container.classList.add('loading');
    
    // Fetch attribute values from API automatically
    fetchAttributeValues(taxonomy, container);
}

// Handle adding attribute values
function handleAddAttributeValues(button) {
    const attributeSelect = button.parentNode.querySelector('.attribute-taxonomy');
    const taxonomy = attributeSelect.value;
    
    if (!taxonomy) {
        alert('Please select an attribute first.');
        return;
    }
    
    // Fetch attribute values from API
    fetchAttributeValues(taxonomy, button.parentNode);
}

// Fetch attribute values from API
async function fetchAttributeValues(taxonomy, container) {
    try {
        // Use current domain instead of hardcoded URL
        const currentDomain = window.location.origin;
        
        // Use our custom AJAX endpoint (known working method)
        const apiUrl = `${currentDomain}/wp-admin/admin-ajax.php?action=get_attribute_values&taxonomy=${taxonomy}`;
        console.log('Fetching attribute values from:', apiUrl);
        
        const response = await fetch(apiUrl);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseData = await response.json();
        
        console.log('Raw API response:', responseData);
        
        // Check if response has success and data structure
        if (responseData.success && Array.isArray(responseData.data)) {
            const data = responseData.data;
            console.log('Extracted data:', data);
            
            // Remove loading state
            container.classList.remove('loading');
            
            // Create attribute values selection
            createAttributeValuesSelection(container, taxonomy, data);
        } else if (Array.isArray(responseData)) {
            // Fallback: if response is directly an array
            console.log('Direct array response:', responseData);
            
            // Remove loading state
            container.classList.remove('loading');
            
            // Create attribute values selection
            createAttributeValuesSelection(container, taxonomy, responseData);
        } else {
            throw new Error('Invalid data format received from API. Expected {success: true, data: [...]} or array.');
        }
        
    } catch (error) {
        console.error('Error fetching attribute values:', error);
        
        // Remove loading state
        container.classList.remove('loading');
        
        // Show user-friendly error message
        let errorMessage = 'Error fetching attribute values. ';
        if (error.message.includes('Failed to fetch')) {
            errorMessage += 'Please check your internet connection.';
        } else if (error.message.includes('HTTP error')) {
            errorMessage += 'Server error. Please try again later.';
        } else {
            errorMessage += 'Please try again.';
        }
        
        alert(errorMessage);
    }
}

// Create attribute values selection
function createAttributeValuesSelection(container, taxonomy, values) {
    // Remove existing values selection if any
    const existingValues = container.querySelector('.attribute-values');
    if (existingValues) {
        existingValues.remove();
    }
    
    const valuesDiv = document.createElement('div');
    valuesDiv.className = 'attribute-values';
    valuesDiv.style.marginTop = '10px';
    
    const label = document.createElement('label');
    label.textContent = 'Select Values:';
    label.style.display = 'block';
    label.style.marginBottom = '5px';
    valuesDiv.appendChild(label);
    
    const valuesContainer = document.createElement('div');
    valuesContainer.style.maxHeight = '200px';
    valuesContainer.style.overflowY = 'auto';
    valuesContainer.style.border = '1px solid #ddd';
    valuesContainer.style.padding = '10px';
    
    values.forEach(value => {
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = `attribute_values_${taxonomy}[]`;
        checkbox.value = value.term_id;
        checkbox.dataset.slug = value.slug;
        checkbox.dataset.name = value.name;
        
        const label = document.createElement('label');
        label.appendChild(checkbox);
        label.appendChild(document.createTextNode(` ${value.name}`));
        label.style.display = 'block';
        label.style.marginBottom = '5px';
        
        valuesContainer.appendChild(label);
        
        // Add change event to regenerate variants
        checkbox.addEventListener('change', function() {
            generateVariantsPreview();
            updateFormData(); // Update form data when attributes change
        });
    });
    
    valuesDiv.appendChild(valuesContainer);
    container.appendChild(valuesDiv);
    
    // Generate initial variants preview
    generateVariantsPreview();
}

// Generate variants preview
function generateVariantsPreview() {
    const productType = document.getElementById('product_type').value;
    
    if (productType !== 'variable') {
        return;
    }
    
    const attributeGroups = document.querySelectorAll('.attribute-group');
    const selectedAttributes = [];
    
    attributeGroups.forEach(group => {
        const taxonomy = group.querySelector('.attribute-taxonomy').value;
        const selectedValues = group.querySelectorAll('input[type="checkbox"]:checked');
        
        if (taxonomy && selectedValues.length > 0) {
            const values = Array.from(selectedValues).map(checkbox => ({
                id: checkbox.value,
                slug: checkbox.dataset.slug,
                name: checkbox.dataset.name
            }));
            
            selectedAttributes.push({
                taxonomy: taxonomy,
                values: values
            });
        }
    });
    
    if (selectedAttributes.length === 0) {
        document.getElementById('variants-preview').style.display = 'none';
        return;
    }
    
    // Generate all possible combinations
    const variants = generateVariants(selectedAttributes);
    
    // Display variants preview
    displayVariantsPreview(variants);
}

// Generate all possible variant combinations
function generateVariants(attributes) {
    if (attributes.length === 0) return [];
    
    const combinations = [];
    
    function generateCombination(index, currentCombination) {
        if (index === attributes.length) {
            combinations.push([...currentCombination]);
            return;
        }
        
        const currentAttribute = attributes[index];
        currentAttribute.values.forEach(value => {
            currentCombination[index] = {
                taxonomy: currentAttribute.taxonomy,
                value: value
            };
            generateCombination(index + 1, currentCombination);
        });
    }
    
    generateCombination(0, new Array(attributes.length));
    return combinations;
}

// Display variants preview
function displayVariantsPreview(variants) {
    const previewDiv = document.getElementById('variants-preview');
    const tableDiv = document.getElementById('variants-table');
    
    if (variants.length === 0) {
        previewDiv.style.display = 'none';
        return;
    }
    
    // Get default prices from main form
    const defaultOriginalPrice = document.getElementById('original_price').value || '0';
    const defaultSalePrice = document.getElementById('sale_price').value || '0';
    
    let tableHTML = '<table class="widefat" style="margin-top: 10px;">';
    tableHTML += '<thead><tr>';
    tableHTML += '<th>Variant</th>';
    tableHTML += '<th>Attributes</th>';
    tableHTML += '<th>Original Price</th>';
    tableHTML += '<th>Sale Price</th>';
    tableHTML += '<th>Stock Status</th>';
    tableHTML += '</tr></thead><tbody>';
    
    variants.forEach((variant, index) => {
        const attributeText = variant.map(attr => attr.value.name).join(' - ');
        
        tableHTML += '<tr>';
        tableHTML += `<td>Variant ${index + 1}</td>`;
        tableHTML += `<td>${attributeText}</td>`;
        tableHTML += `<td><input type="number" step="0.01" min="0" class="variant-original-price" value="${defaultOriginalPrice}" data-variant-index="${index}"></td>`;
        tableHTML += `<td><input type="number" step="0.01" min="0" class="variant-sale-price" value="${defaultSalePrice}" data-variant-index="${index}"></td>`;
        tableHTML += '<td>In Stock</td>';
        tableHTML += '</tr>';
    });
    
    tableHTML += '</tbody></table>';
    
    tableDiv.innerHTML = tableHTML;
    previewDiv.style.display = 'block';
    
    // Add event listeners for price inputs
    addPriceInputEventListeners();
}

// Add event listeners for price inputs
function addPriceInputEventListeners() {
    // Original price inputs
    document.querySelectorAll('.variant-original-price').forEach(input => {
        input.addEventListener('input', function() {
            const variantIndex = this.dataset.variantIndex;
            const salePriceInput = document.querySelector(`.variant-sale-price[data-variant-index="${variantIndex}"]`);
            
            // Validate that sale price is not greater than or equal to original price
            if (salePriceInput.value && parseFloat(salePriceInput.value) >= parseFloat(this.value)) {
                salePriceInput.setCustomValidity('Sale price must be less than original price');
                salePriceInput.style.borderColor = '#dc3232';
            } else {
                salePriceInput.setCustomValidity('');
                salePriceInput.style.borderColor = '';
            }
        });
    });
    
    // Sale price inputs
    document.querySelectorAll('.variant-sale-price').forEach(input => {
        input.addEventListener('input', function() {
            const variantIndex = this.dataset.variantIndex;
            const originalPriceInput = document.querySelector(`.variant-original-price[data-variant-index="${variantIndex}"]`);
            
            // Validate that sale price is not greater than or equal to original price
            if (this.value && parseFloat(this.value) >= parseFloat(originalPriceInput.value)) {
                this.setCustomValidity('Sale price must be less than original price');
                this.style.borderColor = '#dc3232';
            } else {
                this.setCustomValidity('');
                this.style.borderColor = '';
            }
        });
    });
}

// Helper function to get attribute options HTML
function getAttributeOptions() {
    const attributeGroups = document.querySelectorAll('.attribute-taxonomy option');
    let optionsHTML = '';
    
    attributeGroups.forEach(option => {
        if (option.value) {
            optionsHTML += `<option value="${option.value}">${option.textContent}</option>`;
        }
    });
    
    return optionsHTML;
}

// Add event listeners to main form price inputs to update variants
document.addEventListener('DOMContentLoaded', function() {
    const originalPriceInput = document.getElementById('original_price');
    const salePriceInput = document.getElementById('sale_price');
    
    if (originalPriceInput) {
        originalPriceInput.addEventListener('input', function() {
            updateVariantPrices('original');
        });
    }
    
    if (salePriceInput) {
        salePriceInput.addEventListener('input', function() {
            updateVariantPrices('sale');
        });
    }
});

// Update variant prices when main form prices change
function updateVariantPrices(priceType) {
    const mainOriginalPrice = document.getElementById('original_price').value || '0';
    const mainSalePrice = document.getElementById('sale_price').value || '0';
    
    if (priceType === 'original') {
        document.querySelectorAll('.variant-original-price').forEach(input => {
            input.value = mainOriginalPrice;
        });
    } else if (priceType === 'sale') {
        document.querySelectorAll('.variant-sale-price').forEach(input => {
            input.value = mainSalePrice;
        });
    }
    
    // Re-validate all price inputs
    validateAllVariantPrices();
    
    // Update form data when prices change
    updateFormData();
}

// Validate all variant prices
function validateAllVariantPrices() {
    document.querySelectorAll('.variant-original-price').forEach((input, index) => {
        const originalPrice = parseFloat(input.value) || 0;
        const salePriceInput = document.querySelector(`.variant-sale-price[data-variant-index="${index}"]`);
        const salePrice = parseFloat(salePriceInput.value) || 0;
        
        if (salePrice > 0 && salePrice >= originalPrice) {
            salePriceInput.setCustomValidity('Sale price must be less than original price');
            salePriceInput.style.borderColor = '#dc3232';
        } else {
            salePriceInput.setCustomValidity('');
            salePriceInput.style.borderColor = '';
        }
    });
}

// Function to generate variant combinations for export
function generateVariantCombinations(attributes) {
    if (attributes.length === 0) return [];
    
    const combinations = [];
    
    function generateCombinationsRecursive(attributes, index, current) {
        if (index === attributes.length) {
            combinations.push([...current]);
            return;
        }
        
        const currentAttribute = attributes[index];
        currentAttribute.values.forEach(value => {
            current.push({
                taxonomy: currentAttribute.taxonomy,
                name: value.name,
                slug: value.slug,
                id: value.id
            });
            generateCombinationsRecursive(attributes, index + 1, current);
            current.pop();
        });
    }
    
    generateCombinationsRecursive(attributes, 0, []);
    return combinations;
}

// Function to import variant prices
function importVariantPrices(variantPrices) {
    const originalPriceInputs = document.querySelectorAll('.variant-original-price');
    const salePriceInputs = document.querySelectorAll('.variant-sale-price');
    
    variantPrices.forEach((variantPrice, index) => {
        if (originalPriceInputs[index]) {
            originalPriceInputs[index].value = variantPrice.original_price || 0;
        }
        if (salePriceInputs[index]) {
            salePriceInputs[index].value = variantPrice.sale_price || 0;
        }
    });
    
    // Validate all prices after import
    validateAllVariantPrices();
}

// Refresh variants preview and update form data
function refreshVariantsPreview() {
    const productType = document.getElementById('product_type').value;
    
    if (productType === 'variable') {
        generateVariantsPreview();
        
        // Update the hidden form data
        updateFormData();
    }
}

// Update form data based on current preview
function updateFormData() {
    const variantOriginalPrices = document.querySelectorAll('.variant-original-price');
    const variantSalePrices = document.querySelectorAll('.variant-sale-price');
    
    // Store current variant data in a data attribute for form submission
    const form = document.getElementById('product-import-form');
    if (form) {
        const variantData = [];
        
        variantOriginalPrices.forEach((input, index) => {
            const originalPrice = parseFloat(input.value) || 0;
            const salePrice = parseFloat(variantSalePrices[index]?.value) || 0;
            
            variantData.push({
                original_price: originalPrice,
                sale_price: salePrice
            });
        });
        
        form.dataset.variantPrices = JSON.stringify(variantData);
        console.log('Updated form variant data:', variantData);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const advancedInput = document.getElementById('advanced_product_images');
    const advancedForm = document.getElementById('advanced-product-import-form');
    if (!advancedInput || !advancedForm) {
        return;
    }

    const advancedPreviewBody = document.getElementById('advanced-image-preview-body');
    const advancedPreviewContainer = document.getElementById('advanced-image-preview');
    const advancedFeedbackMessage = document.getElementById('advanced-feedback-message');
    const advancedCategoryTemplate = document.getElementById('advanced-category-template');
    const advancedBrandTemplate = document.getElementById('advanced-brand-template');
    const advancedTagTemplate = document.getElementById('advanced-tag-template');

    const advancedProducts = [];
    const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    advancedInput.addEventListener('change', function (event) {
        const files = Array.from(event.target.files || []);
        advancedProducts.length = 0;
        advancedPreviewBody.innerHTML = '';
        advancedFeedbackMessage.innerHTML = '';

        const invalidFiles = [];
        const folderMap = {};

        files.forEach(file => {
            const fileExtension = file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(fileExtension)) {
                invalidFiles.push(file.name);
            }

            const pathParts = file.webkitRelativePath.split('/');
            const rootFolder = pathParts.length > 1 ? pathParts[1] : null;

            if (rootFolder) {
                if (!folderMap[rootFolder]) {
                    folderMap[rootFolder] = [];
                }
                folderMap[rootFolder].push(file);
            }
        });

        if (invalidFiles.length > 0) {
            alert('The following files are not valid image formats: ' + invalidFiles.join(', '));
            event.target.value = '';
            return;
        }

        if (Object.keys(folderMap).length === 0) {
            alert('Please upload files inside folders.');
            event.target.value = '';
            return;
        }

        Object.keys(folderMap).forEach(rootFolder => {
            const filesInFolder = folderMap[rootFolder];
            const productImage = filesInFolder[0] || null;
            const galleryImages = filesInFolder.slice(1);

            const productData = {
                key: rootFolder,
                productName: rootFolder,
                productImage: productImage,
                galleryImages: galleryImages
            };

            advancedProducts.push(productData);

            if (!productImage) {
                return;
            }

            const reader = new FileReader();
            reader.onload = function (readerEvent) {
                const row = document.createElement('tr');
                row.dataset.pipKey = rootFolder;

                const categorySelect = createAdvancedMultiSelect(advancedCategoryTemplate, 'categories');
                const brandSelect = createAdvancedMultiSelect(advancedBrandTemplate, 'brands');
                const tagSelect = createAdvancedMultiSelect(advancedTagTemplate, 'tags');

                const galleryWrapper = document.createElement('div');
                galleryWrapper.className = 'pip-advanced-gallery-wrapper';
                if (galleryImages.length > 0) {
                    galleryImages.forEach(function (file) {
                        const galleryReader = new FileReader();
                        galleryReader.onload = function (galleryEvent) {
                            const img = document.createElement('img');
                            img.src = galleryEvent.target.result;
                            img.alt = file.name;
                            img.className = 'pip-advanced-gallery-image';
                            galleryWrapper.appendChild(img);
                        };
                        galleryReader.readAsDataURL(file);
                    });
                } else {
                    galleryWrapper.textContent = '-';
                }

                const imageCell = document.createElement('td');
                const featuredImage = document.createElement('img');
                featuredImage.src = readerEvent.target.result;
                featuredImage.alt = productImage.name;
                featuredImage.className = 'pip-advanced-featured-image';
                imageCell.appendChild(featuredImage);

                const nameCell = document.createElement('td');
                const productNameInput = document.createElement('input');
                productNameInput.type = 'text';
                productNameInput.className = 'pip-advanced-product-name';
                productNameInput.value = rootFolder;
                nameCell.appendChild(productNameInput);

                const galleryCell = document.createElement('td');
                const categoryCell = document.createElement('td');
                const brandCell = document.createElement('td');
                const tagCell = document.createElement('td');

                row.appendChild(imageCell);
                row.appendChild(nameCell);
                galleryCell.appendChild(galleryWrapper);
                categoryCell.appendChild(categorySelect);
                brandCell.appendChild(brandSelect);
                tagCell.appendChild(tagSelect);

                initializeAdvancedMultiSelect(categorySelect);
                initializeAdvancedMultiSelect(brandSelect);
                initializeAdvancedMultiSelect(tagSelect);

                row.appendChild(galleryCell);
                row.appendChild(categoryCell);
                row.appendChild(brandCell);
                row.appendChild(tagCell);

                advancedPreviewBody.appendChild(row);
                advancedPreviewContainer.style.display = 'block';
            };
            reader.readAsDataURL(productImage);
        });
    });

    advancedForm.addEventListener('submit', function (event) {
        event.preventDefault();

        const originalPrice = document.getElementById('advanced_original_price')?.value.trim() || '';
        const salePrice = document.getElementById('advanced_sale_price')?.value.trim() || '';
        const productDescription = getAdvancedEditorContent();
        const rows = advancedPreviewBody.querySelectorAll('tr');

        const errors = [];
        if (!originalPrice || isNaN(originalPrice) || parseFloat(originalPrice) <= 0) {
            errors.push('Original Price is required and must be a valid number greater than 0.');
        }
        if (salePrice && (isNaN(salePrice) || parseFloat(salePrice) < 0)) {
            errors.push('Sale Price must be a valid number greater than or equal to 0.');
        }
        if (rows.length === 0) {
            errors.push('Please upload at least one image folder.');
        }

        if (errors.length > 0) {
            alert(errors.join('\n'));
            return;
        }

        advancedFeedbackMessage.innerHTML = 'Importing products, please wait...';

        const results = [];
        (async () => {
            for (const row of rows) {
                const rowKey = row.dataset.pipKey;
                const productData = advancedProducts.find(function (item) {
                    return item.key === rowKey;
                });

                if (!productData || !productData.productImage) {
                    continue;
                }

                const productNameInput = row.querySelector('.pip-advanced-product-name');
                const categoryIds = getSelectedValues(row.querySelector('.pip-advanced-multiselect-dropdown[data-type="categories"]'));
                const brandIds = getSelectedValues(row.querySelector('.pip-advanced-multiselect-dropdown[data-type="brands"]'));
                const tagIds = getSelectedValues(row.querySelector('.pip-advanced-multiselect-dropdown[data-type="tags"]'));

                if (categoryIds.length === 0) {
                    results.push(`Please select at least one category for product "${productData.productName}".`);
                    continue;
                }

                const formData = new FormData();
                formData.append('action', 'import_products_with_taxonomies');
                formData.append('product_image', productData.productImage);
                formData.append('product_name', (productNameInput?.value || productData.productName || '').trim());
                formData.append('original_price', originalPrice);
                formData.append('sale_price', salePrice);
                formData.append('product_description', productDescription);

                categoryIds.forEach(function (termId) {
                    formData.append('product_category_ids[]', termId);
                });
                brandIds.forEach(function (termId) {
                    formData.append('product_brand_ids[]', termId);
                });
                tagIds.forEach(function (termId) {
                    formData.append('product_tag_ids[]', termId);
                });

                productData.galleryImages.forEach(function (file, index) {
                    formData.append(`product_gallery[${index}]`, file);
                });

                try {
                    const response = await fetch(ajax_object.ajax_url, {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }

                    const data = await response.json();
                    if (data.success) {
                        results.push(`Product "${productData.productName}" imported successfully!`);
                    } else {
                        results.push(`Error importing product "${productData.productName}": ${data.message}`);
                    }
                } catch (error) {
                    results.push(`Error importing product "${productData.productName}": ${error.message}`);
                }
            }

            advancedFeedbackMessage.innerHTML = results.map(escapeHtml).join('<br>');
        })();
    });

    function getAdvancedEditorContent() {
        if (typeof tinymce !== 'undefined') {
            const editor = tinymce.get('advanced_product_description');
            if (editor && !editor.isHidden()) {
                return editor.getContent().trim();
            }
        }

        const textarea = document.getElementById('advanced_product_description');
        return textarea ? textarea.value.trim() : '';
    }

    function getSelectedValues(selectElement) {
        if (!selectElement) return [];

        // Support both the wrapper div and the actual select element.
        const select = selectElement.tagName && selectElement.tagName.toLowerCase() === 'select'
            ? selectElement
            : selectElement.querySelector('select');

        if (select) {
            return Array.from(select.selectedOptions).map(function(opt) { return opt.value; }).filter(function(v){ return v !== ''; });
        }

        // Fallback: old checkbox-based dropdown
        const checkboxes = selectElement.querySelectorAll('input[type="checkbox"]:checked');
        return Array.from(checkboxes).map(function (checkbox) {
            return checkbox.value;
        }).filter(function (value) {
            return value !== '';
        });
    }

    function createAdvancedMultiSelect(template, type) {
        const container = document.createElement('div');
        container.className = 'pip-advanced-multiselect-dropdown';
        container.dataset.type = type;
        container.dataset.placeholder = 'Select ' + type;

        if (!template) {
            return container;
        }

        // If template contains a <select>, clone and use it (Select2-ready)
        const select = template.querySelector('select');
        if (select) {
            const clonedSelect = select.cloneNode(true);
            clonedSelect.removeAttribute('id');
            clonedSelect.classList.add('pip-multiselect-select');
            container.appendChild(clonedSelect);
            return container;
        }

        // Fallback: clone old template structure
        const clone = template.cloneNode(true);
        clone.removeAttribute('id');
        clone.classList.add('pip-advanced-multiselect-dropdown');
        clone.dataset.type = type;
        return clone;
    }

    function initializeAdvancedMultiSelect(dropdown) {
        if (!dropdown || dropdown.dataset.pipInitialized === '1') {
            return;
        }

        // If dropdown contains a select (Select2), initialize it
        const select = dropdown.querySelector('select.pip-multiselect-select');
        if (select) {
            // Try initializing Select2, retry a few times if library not loaded yet
            var tries = 0;
            var maxTries = 6;
            var tryInit = function() {
                tries++;
                if (window.jQuery && typeof jQuery.fn.select2 === 'function') {
                    try {
                        jQuery(select).select2({
                            placeholder: dropdown.dataset.placeholder || '',
                            allowClear: true,
                            width: 'resolve'
                        });
                    } catch (e) {
                        console.error('Select2 init error:', e);
                    }
                    dropdown.dataset.pipInitialized = '1';
                } else if (tries < maxTries) {
                    setTimeout(tryInit, 200);
                } else {
                    // final fallback: leave native select usable
                    dropdown.dataset.pipInitialized = '1';
                }
            };
            tryInit();
            return;
        }

        // Fallback: existing toggle/panel/checkbox behavior
        const toggle = dropdown.querySelector('.pip-multiselect-toggle');
        const panel = dropdown.querySelector('.pip-multiselect-panel');
        const options = dropdown.querySelectorAll('input[type="checkbox"]');
        if (!toggle || !panel) {
            return;
        }

        const updateLabel = function () {
            const selected = Array.from(options).filter(function (checkbox) {
                return checkbox.checked;
            }).map(function (checkbox) {
                return checkbox.dataset.label || checkbox.nextElementSibling?.textContent || checkbox.value;
            });

            if (selected.length === 0) {
                toggle.textContent = dropdown.dataset.placeholder || 'Select options';
            } else {
                toggle.textContent = selected.length <= 2 ? selected.join(', ') : selected.length + ' selected';
            }
        };

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            const isOpen = dropdown.classList.contains('is-open');
            closeAdvancedMultiSelectPanels();
            if (!isOpen) {
                dropdown.classList.add('is-open');
            }
        });

        options.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                updateLabel();
            });
        });

        panel.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        updateLabel();
        dropdown.dataset.pipInitialized = '1';
    }

    function closeAdvancedMultiSelectPanels() {
        document.querySelectorAll('.pip-advanced-multiselect-dropdown.is-open').forEach(function (dropdown) {
            dropdown.classList.remove('is-open');
        });
    }

    document.addEventListener('click', function () {
        closeAdvancedMultiSelectPanels();
    });

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
