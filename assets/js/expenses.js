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

// Handle category change - show/hide fields based on selection
function handleCategoryChange() {
    const categorySelect = document.getElementById('category');
    const quantityField = document.getElementById('quantity').parentElement;
    const unitPriceField = document.getElementById('unit_price').parentElement;
    const expectedField = document.getElementById('expected').parentElement;
    
    if (categorySelect.value === 'Cooks' || categorySelect.value === 'Administrative') {
        // Show fields for Cooks and Administrative
        quantityField.style.display = 'block';
        unitPriceField.style.display = 'block';
        expectedField.style.display = 'block';
    } else if (categorySelect.value === 'Utilities') {
        // Hide fields for Utilities
        quantityField.style.display = 'none';
        unitPriceField.style.display = 'none';
        expectedField.style.display = 'none';
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
    
    // Initialize category change handler
    handleCategoryChange();
});
