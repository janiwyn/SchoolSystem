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

// Function to populate student data from dropdown
function populateStudentData() {
    const select = document.getElementById('studentSelect');
    const selectedOption = select.options[select.selectedIndex];

    if (!selectedOption || !selectedOption.value) {
        // Clear all fields if no student selected
        document.getElementById('fullName').value = '';
        document.getElementById('gender').value = '';
        document.getElementById('className').value = '';
        document.getElementById('dayBoarding').value = '';
        document.getElementById('expectedTuition').value = '';
        document.getElementById('admissionFee').value = '';
        document.getElementById('uniformFee').value = '';
        document.getElementById('parentContact').value = '';
        document.getElementById('studentStatus').value = '';
        document.getElementById('term').value = '';
        
        // Hide preview card
        document.getElementById('studentPreviewCard').style.display = 'none';
        return;
    }

    // Get data from selected option
    const firstName = selectedOption.dataset.first || '';
    const classId = selectedOption.dataset.class;
    const className = selectedOption.dataset.className || '';
    const boarding = selectedOption.dataset.boarding || '';
    const admissionFee = selectedOption.dataset.admissionFee || 0;
    const uniformFee = selectedOption.dataset.uniformFee || 0;
    const contact = selectedOption.dataset.contact || '';
    const gender = selectedOption.dataset.gender || '';
    const status = selectedOption.dataset.status || '';

    // FIX: Use only first_name
    const fullName = firstName;

    // Populate form fields
    document.getElementById('fullName').value = fullName;
    document.getElementById('gender').value = gender;
    document.getElementById('className').value = className;
    document.getElementById('dayBoarding').value = boarding;
    document.getElementById('admissionFee').value = admissionFee;
    document.getElementById('uniformFee').value = uniformFee;
    document.getElementById('parentContact').value = contact;
    document.getElementById('studentStatus').value = status === 'approved' ? 'Approved' : 'Pending Approval';

    // Get expected tuition and term from window globals
    const expectedTuition = window.classExpected && window.classExpected[classId] 
        ? window.classExpected[classId] 
        : 0;
    const currentTerm = window.currentTerm || '';

    document.getElementById('expectedTuition').value = expectedTuition.toFixed(2);
    document.getElementById('term').value = currentTerm;

    // Update preview card
    document.getElementById('previewStudentName').textContent = fullName;
    document.getElementById('previewClass').textContent = className;
    document.getElementById('previewTerm').textContent = currentTerm;
    document.getElementById('previewDayBoarding').textContent = boarding;
    document.getElementById('previewGender').textContent = gender;
    
    // Show preview card with animation
    const previewCard = document.getElementById('studentPreviewCard');
    previewCard.style.display = 'block';
    setTimeout(() => {
        previewCard.style.opacity = '1';
        previewCard.style.transform = 'translateY(0)';
    }, 10);
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

// Calculate new balance when amount paid changes
function calculateEditBalance() {
    const expectedField = document.getElementById('editPaymentExpected');
    const paidField = document.getElementById('editPaymentAmountPaid');
    const balanceField = document.getElementById('editPaymentNewBalance');
    
    if (!expectedField || !paidField || !balanceField) return;
    
    const expected = parseFloat(expectedField.value) || 0;
    const paid = parseFloat(paidField.value) || 0;
    const balance = expected - paid;
    balanceField.value = balance.toFixed(2);

    // Color the balance field
    if (balance <= 0) {
        balanceField.style.color = '#27ae60'; // green - paid
    } else {
        balanceField.style.color = '#e74c3c'; // red - outstanding
    }
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
        }, { once: false });
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
                
                // FIX: Use only first_name (no last_name in your database)
                option.textContent = `${student.first_name} (${student.admission_no})`;
                
                // Set data attributes
                option.dataset.admission = student.admission_no;
                option.dataset.first = student.first_name;
                option.dataset.last = ''; // No last_name field
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
            
            // Disable submit button
            const submitBtn = paymentForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Recording...';
            }
            
            isSubmitting = true;
        });
    }
});
