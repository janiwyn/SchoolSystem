// Toggle payment form visibility
function togglePaymentForm() {
    const card = document.getElementById('paymentFormCard');
    const btn = document.querySelector('.btn-toggle-form');
    if (!card) return;

    if (card.style.display === 'none' || card.style.display === '') {
        card.style.display = 'block';
        if (btn) btn.querySelector('i').className = 'bi bi-chevron-down';
    } else {
        card.style.display = 'none';
        if (btn) btn.querySelector('i').className = 'bi bi-chevron-right';
    }
}

// Populate form fields when a student is selected from dropdown
function populateStudentData() {
    const select = document.getElementById('studentSelect');
    const opt = select.options[select.selectedIndex];

    if (!opt || !opt.value) {
        // Clear all fields
        ['fullName','gender','className','term','dayBoarding','expectedTuition',
         'admissionFee','uniformFee','parentContact','studentStatus'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const preview = document.getElementById('studentPreviewCard');
        if (preview) preview.style.display = 'none';
        return;
    }

    const firstName = opt.dataset.first || '';
    const classId = opt.dataset.class;
    const className = opt.dataset.className || 'N/A';
    const boarding = opt.dataset.boarding || '';
    const admissionFee = opt.dataset.admissionFee || 0;
    const uniformFee = opt.dataset.uniformFee || 0;
    const contact = opt.dataset.contact || '';
    const gender = opt.dataset.gender || '';
    const status = opt.dataset.status || '';

    document.getElementById('fullName').value = firstName;
    document.getElementById('gender').value = gender;
    document.getElementById('className').value = className;
    document.getElementById('dayBoarding').value = boarding;
    document.getElementById('admissionFee').value = parseFloat(admissionFee).toFixed(2);
    document.getElementById('uniformFee').value = parseFloat(uniformFee).toFixed(2);
    document.getElementById('parentContact').value = contact;
    document.getElementById('studentStatus').value = status === 'approved' ? 'Approved' : 'Pending Approval';

    // Expected tuition from class fee structure
    const expected = window.classExpected && window.classExpected[classId]
        ? window.classExpected[classId] : 0;
    document.getElementById('expectedTuition').value = expected.toFixed(2);

    // Term
    document.getElementById('term').value = window.currentTerm || '';

    // Update preview card
    const previewCard = document.getElementById('studentPreviewCard');
    if (previewCard) {
        document.getElementById('previewStudentName').textContent = firstName;
        document.getElementById('previewClass').textContent = className;
        document.getElementById('previewTerm').textContent = window.currentTerm || '-';
        document.getElementById('previewDayBoarding').textContent = boarding;
        document.getElementById('previewGender').textContent = gender;
        previewCard.style.display = 'block';
    }
}

// Set payment ID for additional payment modal
function setPaymentId(id, balance) {
    const paymentId = document.getElementById('modalPaymentId');
    const balanceField = document.getElementById('modalBalance');
    const amountField = document.getElementById('modalAmount');

    if (paymentId) paymentId.value = id;
    if (balanceField) balanceField.value = parseFloat(balance).toFixed(2);
    if (amountField) {
        amountField.value = '';
        amountField.max = balance;
    }
}

// Load edit payment data into modal
function loadEditPayment(id, amountPaid, admissionFee, uniformFee, expectedTuition, studentName) {
    const el = (selector) => document.getElementById(selector);

    if (!el('editPaymentId')) {
        console.error('Edit Payment Modal not found on page');
        return;
    }

    el('editPaymentId').value = id;
    el('editPaymentStudentName').textContent = studentName;
    el('editPaymentExpected').value = parseFloat(expectedTuition).toFixed(2);
    el('editPaymentAmountPaid').value = parseFloat(amountPaid).toFixed(2);
    el('editPaymentAdmissionFee').value = parseFloat(admissionFee).toFixed(2);
    el('editPaymentUniformFee').value = parseFloat(uniformFee).toFixed(2);
    calculateEditBalance();
}

// Calculate new balance when amount paid changes in edit modal
function calculateEditBalance() {
    const expectedField = document.getElementById('editPaymentExpected');
    const paidField = document.getElementById('editPaymentAmountPaid');
    const balanceField = document.getElementById('editPaymentNewBalance');

    if (!expectedField || !paidField || !balanceField) return;

    const expected = parseFloat(expectedField.value) || 0;
    const paid = parseFloat(paidField.value) || 0;
    const balance = expected - paid;
    balanceField.value = balance.toFixed(2);

    balanceField.style.color = balance <= 0 ? '#27ae60' : '#e74c3c';
}

// Prevent double form submission
document.addEventListener('DOMContentLoaded', function() {
    const paymentForm = document.getElementById('paymentForm');

    if (paymentForm) {
        let isSubmitting = false;

        paymentForm.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }

            const submitBtn = paymentForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Recording...';
            }

            isSubmitting = true;
        });
    }
});
