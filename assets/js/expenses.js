// Toggle Expense Form
function toggleExpenseForm() {
    const form = document.getElementById('expenseFormCard');
    const toggleBtn = document.querySelector('.btn-toggle-form');
    const toggleIcon = document.getElementById('toggleIcon');
    const toggleText = document.getElementById('toggleText');

    if (form.style.display === 'none') {
        form.style.display = 'block';
        toggleIcon.classList.remove('bi-chevron-down');
        toggleIcon.classList.add('bi-chevron-up');
        toggleText.textContent = 'Hide Form';
    } else {
        form.style.display = 'none';
        toggleIcon.classList.remove('bi-chevron-up');
        toggleIcon.classList.add('bi-chevron-down');
        toggleText.textContent = 'Show Form';
    }
}

// Calculate Expected Amount (Quantity × Unit Price)
function calculateExpected() {
    const quantityField = document.getElementById('quantity');
    const unitPriceField = document.getElementById('unit_price');
    const expectedField = document.getElementById('expected');
    
    if (quantityField && unitPriceField && expectedField) {
        const quantity = parseFloat(quantityField.value) || 0;
        const unitPrice = parseFloat(unitPriceField.value) || 0;
        const expected = quantity * unitPrice;
        expectedField.value = expected.toFixed(2);
    }
}

// Handle category change - show/hide sub-category and fields
function handleCategoryChange() {
    const categorySelect = document.getElementById('category');
    const subCategoryField = document.getElementById('subCategoryField');
    const subCategorySelect = document.getElementById('sub_category');
    
    if (!categorySelect || !subCategoryField) return;
    
    const category = categorySelect.value;
    
    if (category === 'General Expenses') {
        // Show sub-category dropdown
        subCategoryField.style.display = 'block';
        subCategorySelect.required = true;
        
        // Hide all optional fields initially
        hideAllOptionalFields();
    } else if (category === 'Salaries') {
        // Hide sub-category dropdown
        subCategoryField.style.display = 'none';
        subCategorySelect.required = false;
        subCategorySelect.value = '';
        
        // Hide all optional fields for Salaries
        hideAllOptionalFields();
    } else {
        // No category selected
        subCategoryField.style.display = 'none';
        subCategorySelect.required = false;
        subCategorySelect.value = '';
        hideAllOptionalFields();
    }
}

// NEW: Handle sub-category change
function handleSubCategoryChange() {
    const subCategory = document.getElementById('sub_category')?.value;
    
    const quantityField = document.getElementById('quantityField');
    const unitPriceField = document.getElementById('unitPriceField');
    const expectedField = document.getElementById('expectedField');
    
    if (!quantityField || !unitPriceField || !expectedField) return;
    
    if (subCategory === 'Food' || subCategory === 'Administrative') {
        // Show Quantity, Unit Price, Expected
        quantityField.style.display = 'block';
        unitPriceField.style.display = 'block';
        expectedField.style.display = 'block';
        
        // Make fields required
        document.getElementById('quantity').required = true;
        document.getElementById('unit_price').required = true;
    } else if (subCategory === 'Utilities') {
        // Hide all optional fields
        hideAllOptionalFields();
    } else {
        // No sub-category selected
        hideAllOptionalFields();
    }
}

// Helper function to hide all optional fields
function hideAllOptionalFields() {
    const quantityField = document.getElementById('quantityField');
    const unitPriceField = document.getElementById('unitPriceField');
    const expectedField = document.getElementById('expectedField');
    
    if (quantityField) quantityField.style.display = 'none';
    if (unitPriceField) unitPriceField.style.display = 'none';
    if (expectedField) expectedField.style.display = 'none';
    
    // Reset values and make fields optional
    const quantityInput = document.getElementById('quantity');
    const unitPriceInput = document.getElementById('unit_price');
    const expectedInput = document.getElementById('expected');
    
    if (quantityInput) {
        quantityInput.value = '0';
        quantityInput.required = false;
    }
    if (unitPriceInput) {
        unitPriceInput.value = '0';
        unitPriceInput.required = false;
    }
    if (expectedInput) {
        expectedInput.value = '0.00';
    }
}

// Initialize calculation on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set initial value
    calculateExpected();
    
    // Add event listeners for calculation
    const quantityField = document.getElementById('quantity');
    const unitPriceField = document.getElementById('unit_price');
    
    if (quantityField && unitPriceField) {
        quantityField.addEventListener('input', calculateExpected);
        quantityField.addEventListener('change', calculateExpected);
        unitPriceField.addEventListener('input', calculateExpected);
        unitPriceField.addEventListener('change', calculateExpected);
    }
    
    // Initialize category and sub-category handlers
    handleCategoryChange();
});
