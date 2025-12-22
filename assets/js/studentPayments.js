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
        document.getElementById('term').value = '';
        document.getElementById('expectedTuition').value = '';
        document.getElementById('admissionFee').value = '';
        document.getElementById('uniformFee').value = '';
        document.getElementById('parentContact').value = '';
        document.getElementById('parentEmail').value = '';
        return;
    }
    
    // Populate fields from data attributes
    document.getElementById('fullName').value = option.dataset.first + ' ' + option.dataset.last;
    document.getElementById('gender').value = option.dataset.gender;
    document.getElementById('className').value = option.dataset.class;
    document.getElementById('dayBoarding').value = option.dataset.boarding;
    document.getElementById('admissionFee').value = option.dataset.admissionFee;
    document.getElementById('uniformFee').value = option.dataset.uniformFee;
    document.getElementById('parentContact').value = option.dataset.contact;
    document.getElementById('parentEmail').value = option.dataset.email;
    
    // Get expected tuition and term from server
    fetch(`../api/getStudentTuition.php?class_id=${option.dataset.class}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('expectedTuition').value = data.tuition || 0;
            document.getElementById('term').value = data.term || '';
        })
        .catch(error => console.error('Error:', error));
}

function setPaymentId(paymentId, balance) {
    document.getElementById('modalPaymentId').value = paymentId;
    document.getElementById('modalBalance').value = balance.toFixed(2);
    document.getElementById('modalAmount').value = '';
    document.getElementById('modalAmount').max = balance;
}
