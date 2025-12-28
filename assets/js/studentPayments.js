// Toggle Record Student Payment collapsible form
function togglePaymentForm() {
    const card = document.getElementById('paymentFormCard');
    if (!card) return;

    const btn = document.querySelector('.btn-toggle-form');
    const icon = btn ? btn.querySelector('i') : null;

    const isHidden = card.style.display === 'none' || card.style.display === '';
    card.style.display = isHidden ? 'block' : 'none';

    if (icon) {
        icon.classList.toggle('bi-chevron-right', !isHidden);
        icon.classList.toggle('bi-chevron-down', isHidden);
    }
}

// Populate form fields when a student is selected
function populateStudentData() {
    const select = document.getElementById('studentSelect');
    if (!select) return;

    const option = select.options[select.selectedIndex];

    // Form fields
    const fullNameInput     = document.getElementById('fullName');
    const statusInput       = document.getElementById('studentStatus');
    const genderInput       = document.getElementById('gender');
    const classNameInput    = document.getElementById('className');
    const termInput         = document.getElementById('term');
    const dayBoardingInput  = document.getElementById('dayBoarding');
    const expectedInput     = document.getElementById('expectedTuition');
    const admissionFeeInput = document.getElementById('admissionFee');
    const uniformFeeInput   = document.getElementById('uniformFee');
    const parentContactInput= document.getElementById('parentContact');

    // If any required field is missing, log and stop (avoid JS errors on hosted app)
    if (!fullNameInput || !statusInput || !genderInput || !classNameInput ||
        !termInput || !dayBoardingInput || !expectedInput ||
        !admissionFeeInput || !uniformFeeInput || !parentContactInput) {
        console.warn('Student payment form fields missing on this page', {
            fullNameInput,
            statusInput,
            genderInput,
            classNameInput,
            termInput,
            dayBoardingInput,
            expectedInput,
            admissionFeeInput,
            uniformFeeInput,
            parentContactInput
        });
        return;
    }

    // If no student selected → clear fields
    if (!option || !option.value) {
        fullNameInput.value      = '';
        statusInput.value        = '';
        genderInput.value        = '';
        classNameInput.value     = '';
        termInput.value          = '';
        dayBoardingInput.value   = '';
        expectedInput.value      = '';
        admissionFeeInput.value  = '';
        uniformFeeInput.value    = '';
        parentContactInput.value = '';
        return;
    }

    // Read data-* attributes from <option>
    const firstName   = option.getAttribute('data-first')   || '';
    const lastName    = option.getAttribute('data-last')    || '';
    const gender      = option.getAttribute('data-gender')  || '';
    const classId     = option.getAttribute('data-class')   || '';
    const dayBoarding = option.getAttribute('data-boarding')|| '';
    const admFee      = option.getAttribute('data-admission-fee') || '0';
    const uniFee      = option.getAttribute('data-uniform-fee')   || '0';
    const contact     = option.getAttribute('data-contact')       || '';
    const status      = option.getAttribute('data-status')        || '';

    // Fill text fields
    fullNameInput.value      = (firstName + ' ' + lastName).trim();
    statusInput.value        = status ? status.charAt(0).toUpperCase() + status.slice(1) : '';
    genderInput.value        = gender;
    classNameInput.value     = classId;        // Numeric class id (you can change to name if you pass it)
    dayBoardingInput.value   = dayBoarding;
    admissionFeeInput.value  = parseFloat(admFee || 0).toFixed(2);
    uniformFeeInput.value    = parseFloat(uniFee || 0).toFixed(2);
    parentContactInput.value = contact;

    // Term: use server-provided currentTerm if available
    if (typeof window.currentTerm === 'string' && window.currentTerm.length > 0) {
        termInput.value = window.currentTerm;
    } else {
        termInput.value = '';
    }

    // Expected tuition: look up by class_id from server-provided map
    let expected = 0;
    if (window.classExpected && classId && window.classExpected[classId] !== undefined) {
        expected = parseFloat(window.classExpected[classId]) || 0;
    }
    expectedInput.value = expected.toFixed(2);
}

// Set data for additional payment modal
function setPaymentId(paymentId, balance) {
    const idField      = document.getElementById('modalPaymentId');
    const balanceField = document.getElementById('modalBalance');
    const amountField  = document.getElementById('modalAmount');

    if (idField)      idField.value = paymentId;
    if (balanceField) balanceField.value = parseFloat(balance || 0).toFixed(2);
    if (amountField)  amountField.value = '';
}

// Initialize behaviour on page load
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('studentSelect');
    if (select && select.value) {
        // If a student is already selected (e.g., after validation error), repopulate
        populateStudentData();
    }
});
