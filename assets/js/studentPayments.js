// Toggle Record Student Payment collapsible form
function togglePaymentForm() {
    const card = document.getElementById('paymentFormCard');
    if (!card) return;

    const btn = document.querySelector('.btn-toggle-form');
    const icon = btn ? btn.querySelector('i') : null;
    const previewCard = document.getElementById('studentPreviewCard');

    const isHidden = card.style.display === 'none' || card.style.display === '';
    card.style.display = isHidden ? 'block' : 'none';

    if (icon) {
        icon.classList.toggle('bi-chevron-right', !isHidden);
        icon.classList.toggle('bi-chevron-down', isHidden);
    }

    // Hide preview card when closing the form
    if (!isHidden && previewCard) {
        previewCard.classList.remove('visible');
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

    // Preview card elements
    const previewCard = document.getElementById('studentPreviewCard');
    const previewName = document.getElementById('previewStudentName');
    const previewClass = document.getElementById('previewClass');
    const previewTerm = document.getElementById('previewTerm');
    const previewDayBoarding = document.getElementById('previewDayBoarding');
    const previewGender = document.getElementById('previewGender');

    // If any required field is missing, log and stop (avoid JS errors on hosted app)
    if (!fullNameInput || !statusInput || !genderInput || !classNameInput ||
        !termInput || !dayBoardingInput || !expectedInput ||
        !admissionFeeInput || !uniformFeeInput || !parentContactInput) {
        console.warn('Student payment form fields missing on this page');
        return;
    }

    // If no student selected → clear fields and hide preview card
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
        
        // Hide preview card
        if (previewCard) {
            previewCard.classList.remove('visible');
        }
        return;
    }

    // Read data-* attributes from <option>
    const firstName   = option.getAttribute('data-first')   || '';
    const lastName    = option.getAttribute('data-last')    || '';
    const gender      = option.getAttribute('data-gender')  || '';
    const classId     = option.getAttribute('data-class')   || '';
    const className   = option.getAttribute('data-class-name') || ''; // ADD THIS
    const dayBoarding = option.getAttribute('data-boarding')|| '';
    const admFee      = option.getAttribute('data-admission-fee') || '0';
    const uniFee      = option.getAttribute('data-uniform-fee')   || '0';
    const contact     = option.getAttribute('data-contact')       || '';
    const status      = option.getAttribute('data-status')        || '';

    const fullName = (firstName + ' ' + lastName).trim();

    // Fill text fields
    fullNameInput.value      = fullName;
    statusInput.value        = status ? status.charAt(0).toUpperCase() + status.slice(1) : '';
    genderInput.value        = gender;
    classNameInput.value     = classId;
    dayBoardingInput.value   = dayBoarding;
    admissionFeeInput.value  = parseFloat(admFee || 0).toFixed(2);
    uniformFeeInput.value    = parseFloat(uniFee || 0).toFixed(2);
    parentContactInput.value = contact;

    // Term: use server-provided currentTerm if available
    const termValue = (typeof window.currentTerm === 'string' && window.currentTerm.length > 0) 
        ? window.currentTerm 
        : '';
    termInput.value = termValue;

    // Expected tuition: look up by class_id from server-provided map
    let expected = 0;
    if (window.classExpected && classId && window.classExpected[classId] !== undefined) {
        expected = parseFloat(window.classExpected[classId]) || 0;
    }
    expectedInput.value = expected.toFixed(2);

    // Populate preview card
    if (previewCard && previewName && previewClass && previewTerm && previewDayBoarding && previewGender) {
        previewName.textContent = fullName;
        previewClass.textContent = className || getClassName(classId); // USE className first
        previewTerm.textContent = termValue || 'N/A';
        previewDayBoarding.textContent = dayBoarding;
        previewGender.textContent = gender === 'Male' ? 'M' : 'F';
        
        // Show preview card with animation
        previewCard.classList.add('visible');
    }
}

// Helper function to get class name from class ID
function getClassName(classId) {
    // Use the server-provided class names map
    if (window.classNames && classId && window.classNames[classId]) {
        return window.classNames[classId];
    }
    
    // Fallback to ID if name not found
    return 'Class ' + classId;
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

// NEW: Track if students have been loaded
let studentsLoaded = false;

// NEW: Load students when dropdown is focused (clicked)
document.addEventListener('DOMContentLoaded', function() {
    const studentSelect = document.getElementById('studentSelect');
    
    if (studentSelect) {
        // Load students when user clicks on the dropdown
        studentSelect.addEventListener('focus', function() {
            if (!studentsLoaded) {
                loadStudents();
            }
        }, { once: false }); // Allow retry if first attempt fails
    }
});

// NEW: Function to load students via AJAX
function loadStudents() {
    const studentSelect = document.getElementById('studentSelect');
    
    if (!studentSelect) return;
    
    // Show loading message
    studentSelect.innerHTML = '<option value="">Loading students...</option>';
    studentSelect.disabled = true;
    
    fetch('../../app/api/loadStudents.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load students');
            }
            return response.json();
        })
        .then(students => {
            // Clear loading message
            studentSelect.innerHTML = '<option value="">-- Select Student --</option>';
            
            if (students.length === 0) {
                studentSelect.innerHTML = '<option value="">No students available</option>';
                studentSelect.disabled = true;
                return;
            }
            
            // Populate dropdown with students
            students.forEach(student => {
                const option = document.createElement('option');
                option.value = student.id;
                option.textContent = `${student.first_name} ${student.last_name} (${student.admission_no})`;
                
                // Set data attributes
                option.dataset.admission = student.admission_no;
                option.dataset.first = student.first_name;
                option.dataset.last = student.last_name;
                option.dataset.gender = student.gender;
                option.dataset.class = student.class_id;
                option.dataset.className = student.class_name || 'N/A';
                option.dataset.boarding = student.day_boarding;
                option.dataset.admissionFee = student.admission_fee;
                option.dataset.uniformFee = student.uniform_fee;
                option.dataset.contact = student.parent_contact;
                option.dataset.email = student.parent_email || '';
                option.dataset.status = student.status;
                
                // Add status indicator for unapproved students
                if (student.status === 'unapproved') {
                    option.textContent += ' ● Pending Approval';
                    option.style.color = '#f39c12';
                }
                
                studentSelect.appendChild(option);
            });
            
            studentSelect.disabled = false;
            studentsLoaded = true;
        })
        .catch(error => {
            console.error('Error loading students:', error);
            studentSelect.innerHTML = '<option value="">Error loading students. Please refresh.</option>';
            studentSelect.disabled = true;
        });
}
