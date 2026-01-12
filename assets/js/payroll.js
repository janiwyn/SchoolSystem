// Toggle Payroll Form
function togglePayrollForm() {
    const form = document.getElementById('payrollFormCard');
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

// Handle department change - show custom field if "other" selected
function handleDepartmentChange() {
    const departmentSelect     = document.getElementById('department');
    const customDepartmentField = document.getElementById('customDepartmentField');

    // Safeguard for pages/roles where the form is hidden or not rendered
    if (!departmentSelect || !customDepartmentField) {
        return;
    }

    if (departmentSelect.value === 'other') {
        customDepartmentField.style.display = 'block';
    } else {
        customDepartmentField.style.display = 'none';
    }
}

// Print payroll slip
function printPayroll(payrollId) {
    // Open print page in new window
    window.open('print_payroll.php?id=' + payrollId, '_blank');
}

// Load data into Edit Payroll modal
window.loadEditPayroll = function(id, name, department, expectedSalary, paidSalary, date) {
    console.log('loadEditPayroll called', { id, name, department, expectedSalary, paidSalary, date });

    const idField       = document.getElementById('editPayrollId');
    const nameField     = document.getElementById('editPayrollName');
    const expectedField = document.getElementById('editExpectedSalary');
    const salField      = document.getElementById('editPayrollSalary');
    const dateField     = document.getElementById('editPayrollDate');
    const deptSelect    = document.getElementById('editPayrollDepartment');

    if (!idField || !nameField || !expectedField || !salField || !dateField || !deptSelect) {
        console.warn('Edit Payroll fields missing', { idField, nameField, expectedField, salField, dateField, deptSelect });
        return;
    }

    idField.value       = id || '';
    nameField.value     = name || '';
    expectedField.value = expectedSalary || 0;
    salField.value      = paidSalary || 0;
    dateField.value     = date || '';

    // Set department dropdown
    let matched = false;
    const dept = (department || '').toLowerCase();
    for (let i = 0; i < deptSelect.options.length; i++) {
        if (deptSelect.options[i].value.toLowerCase() === dept) {
            deptSelect.selectedIndex = i;
            matched = true;
            break;
        }
    }
    if (!matched) {
        deptSelect.value = 'other';
    }
};

// Load Pay modal with payroll data
window.loadPayModal = function(id, name, expected, paid) {
    console.log('loadPayModal called', { id, name, expected, paid });

    const idField = document.getElementById('payPayrollId');
    const nameField = document.getElementById('payEmployeeName');
    const expectedField = document.getElementById('payExpectedSalary');
    const paidField = document.getElementById('payAlreadyPaid');
    const balanceField = document.getElementById('payRemainingBalance');
    const amountInput = document.getElementById('paymentAmount');

    if (!idField || !nameField || !expectedField || !paidField || !balanceField || !amountInput) {
        console.warn('Pay modal fields missing');
        return;
    }

    const remaining = expected - paid;

    idField.value = id || '';
    nameField.textContent = name || '';
    expectedField.textContent = '$' + (expected || 0).toFixed(2);
    paidField.textContent = '$' + (paid || 0).toFixed(2);
    balanceField.textContent = '$' + remaining.toFixed(2);
    amountInput.value = '';
    amountInput.max = remaining.toFixed(2);
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    handleDepartmentChange();
});
