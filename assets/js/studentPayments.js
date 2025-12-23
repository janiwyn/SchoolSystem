function togglePaymentForm() {
    const formCard = document.getElementById('paymentFormCard');
    const toggleBtn = document.querySelector('.btn-toggle-form');
    const icon = toggleBtn.querySelector('i');
    
    if (formCard.style.display === 'none') {
        // Open form
        formCard.style.display = 'block';
        toggleBtn.classList.remove('collapsed');
        toggleBtn.classList.add('expanded');
        icon.classList.remove('bi-chevron-right');
        icon.classList.add('bi-chevron-down');
    } else {
        // Close form
        formCard.style.display = 'none';
        toggleBtn.classList.remove('expanded');
        toggleBtn.classList.add('collapsed');
        icon.classList.remove('bi-chevron-down');
        icon.classList.add('bi-chevron-right');
    }
}

function populateStudentData() {
    const select = document.getElementById('studentSelect');
    const option = select.options[select.selectedIndex];

    if (option.value === '') {
        // Clear all fields
        document.getElementById('fullName').value = '';
        document.getElementById('gender').value = '';
        document.getElementById('className').value = '';
        document.getElementById('dayBoarding').value = '';
        document.getElementById('expectedTuition').value = '';
        document.getElementById('admissionFee').value = '';
        document.getElementById('uniformFee').value = '';
        document.getElementById('parentContact').value = '';
        document.getElementById('parentEmail').value = '';
        document.getElementById('studentStatus').value = '';
        return;
    }

    // Populate fields from data attributes
    const firstName = option.getAttribute('data-first');
    const lastName = option.getAttribute('data-last');
    const fullName = firstName + ' ' + lastName;

    document.getElementById('fullName').value = fullName;
    document.getElementById('gender').value = option.getAttribute('data-gender');
    document.getElementById('className').value = option.getAttribute('data-class');
    document.getElementById('dayBoarding').value = option.getAttribute('data-boarding');
    document.getElementById('expectedTuition').value = option.getAttribute('data-admission-fee') || 0;
    document.getElementById('admissionFee').value = option.getAttribute('data-admission-fee');
    document.getElementById('uniformFee').value = option.getAttribute('data-uniform-fee');
    document.getElementById('parentContact').value = option.getAttribute('data-contact');
    document.getElementById('parentEmail').value = option.getAttribute('data-email');
    
    // Display status with styling
    const status = option.getAttribute('data-status');
    const statusField = document.getElementById('studentStatus');
    statusField.value = status.charAt(0).toUpperCase() + status.slice(1);
    
    if (status === 'unapproved') {
        statusField.style.backgroundColor = '#fff3cd';
        statusField.style.color = '#856404';
    } else {
        statusField.style.backgroundColor = '';
        statusField.style.color = '';
    }
}

function setPaymentId(paymentId, balance) {
    document.getElementById('modalPaymentId').value = paymentId;
    document.getElementById('modalBalance').value = balance.toFixed(2);
    document.getElementById('modalAmount').value = '';
    document.getElementById('modalAmount').max = balance;
}
