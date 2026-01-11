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
    const departmentSelect = document.getElementById('department');
    const customDepartmentField = document.getElementById('customDepartmentField');
    
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
window.loadEditPayroll = function(id, name, department, salary, date) {
    document.getElementById('editPayrollId').value = id || '';
    document.getElementById('editPayrollName').value = name || '';
    document.getElementById('editPayrollSalary').value = salary || '';
    document.getElementById('editPayrollDate').value = date || '';

    // Set department dropdown
    const deptSelect = document.getElementById('editPayrollDepartment');
    if (deptSelect) {
        // Try to match existing option
        let matched = false;
        for (let i = 0; i < deptSelect.options.length; i++) {
            if (deptSelect.options[i].value.toLowerCase() === (department || '').toLowerCase()) {
                deptSelect.selectedIndex = i;
                matched = true;
                break;
            }
        }
        // If not matched, set to "other" and user can type
        if (!matched) {
            deptSelect.value = 'other';
        }
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    handleDepartmentChange();
});
