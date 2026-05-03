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

// Handle student name input from datalist
function handleStudentInput() {
    const input = document.getElementById('studentNameInput');
    const hiddenId = document.getElementById('studentIdHidden');
    const list = document.getElementById('studentList');
    const val = input.value;
    
    // Find matching option in datalist
    let matchingOption = null;
    for (let i = 0; i < list.options.length; i++) {
        if (list.options[i].value === val) {
            matchingOption = list.options[i];
            break;
        }
    }

    if (matchingOption) {
        // Existing student selected
        const data = matchingOption.dataset;
        hiddenId.value = data.id;
        input.style.borderColor = '#27ae60'; // Green for recognized student
        
        document.getElementById('gender').value = data.gender || '';
        document.getElementById('classSelect').value = data.class || '';
        document.getElementById('dayBoarding').value = data.boarding || '';
        document.getElementById('admissionFee').value = parseFloat(data.admissionFee || 0).toFixed(2);
        document.getElementById('uniformFee').value = parseFloat(data.uniformFee || 0).toFixed(2);
        document.getElementById('parentContact').value = data.contact || '';
        document.getElementById('parentEmail').value = data.email || '';
        document.getElementById('studentStatus').value = data.status === 'approved' ? 'Approved' : 'Pending Approval';
        document.getElementById('term').value = window.currentTerm || 'Term 1';
        
        // Auto-fill tuition for this class
        handleClassChange();
        
        updatePreview(val, data.className, window.currentTerm, data.boarding, data.gender);
    } else {
        // New student or manual typing
        hiddenId.value = '0';
        document.getElementById('studentStatus').value = 'New Admission';
        
        // CHECK FOR DUPLICATE NAME MANUALLY
        let isDuplicate = false;
        for (let i = 0; i < list.options.length; i++) {
            if (list.options[i].value.toLowerCase() === val.toLowerCase()) {
                isDuplicate = true;
                break;
            }
        }
        
        if (isDuplicate && val.length > 2) {
            input.style.borderColor = '#e74c3c'; // Red for duplicate warning
            const existingNote = document.querySelector('.text-muted.d-block.mt-2');
            if (existingNote) {
                existingNote.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> <span class="text-danger fw-bold">Warning: A student with this name already exists! Select them from the list to avoid duplicates.</span>';
            }
        } else {
            input.style.borderColor = ''; // Reset
            const existingNote = document.querySelector('.text-muted.d-block.mt-2');
            if (existingNote) {
                existingNote.innerHTML = '<i class="bi bi-info-circle"></i> If the student is new, just type the full name and fill other fields below.';
            }
        }

        const preview = document.getElementById('studentPreviewCard');
        if (preview) preview.style.display = 'none';
    }
}

// Auto-fill expected tuition when class or term changes
function handleClassChange() {
    const classSelect = document.getElementById('classSelect');
    const classId = classSelect.value;
    const term = document.getElementById('term').value;
    const tuitionInput = document.getElementById('expectedTuition');
    
    if (window.classTermTuition && window.classTermTuition[classId] && window.classTermTuition[classId][term]) {
        tuitionInput.value = parseFloat(window.classTermTuition[classId][term]).toFixed(2);
    } else {
        // Fallback or clear if no specific mapping found
        // tuitionInput.value = ''; // Or keep as is
    }
    
    // Update preview if name is present
    const name = document.getElementById('studentNameInput').value;
    if (name) {
        const className = classSelect.options[classSelect.selectedIndex] ? classSelect.options[classSelect.selectedIndex].text : '-';
        const boarding = document.getElementById('dayBoarding').value;
        const gender = document.getElementById('gender').value;
        updatePreview(name, className, term, boarding, gender);
    }
}

function updatePreview(name, className, term, boarding, gender) {
    const previewCard = document.getElementById('studentPreviewCard');
    if (previewCard) {
        document.getElementById('previewStudentName').textContent = name;
        document.getElementById('previewClass').textContent = className;
        document.getElementById('previewTerm').textContent = term || '-';
        document.getElementById('previewDayBoarding').textContent = boarding || '-';
        document.getElementById('previewGender').textContent = gender || '-';
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
