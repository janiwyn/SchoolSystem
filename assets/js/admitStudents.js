// Toggle Admit Student collapsible form
function toggleAdmitForm() {
    const card = document.getElementById('admitFormCard');
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

// Fill the Edit Student modal with current data
// Signature MUST match onclick in admitStudents.php:
// loadEditForm(id, firstName, gender, admissionFee, uniformFee, parentContact, dayBoarding, classId)
function loadEditForm(id, firstName, gender, admissionFee, uniformFee, parentContact, dayBoarding, classId) {
    const idField          = document.getElementById('editStudentId');
    const nameField        = document.getElementById('editFirstName');
    const genderField      = document.getElementById('editGender');
    const classField       = document.getElementById('editClassId');
    const dayBoardingField = document.getElementById('editDayBoarding');
    const admField         = document.getElementById('editAdmissionFee');
    const uniField         = document.getElementById('editUniformFee');
    const contactField     = document.getElementById('editParentContact');

    // If any required field is missing, log and stop to avoid JS error
    if (!idField || !nameField || !genderField || !classField ||
        !dayBoardingField || !admField || !uniField || !contactField) {
        console.warn('Edit modal fields missing on this page', {
            idField, nameField, genderField, classField,
            dayBoardingField, admField, uniField, contactField
        });
        return;
    }

    idField.value          = id;
    nameField.value        = firstName;
    genderField.value      = gender;       // "Male" or "Female"
    classField.value       = classId;      // numeric class_id
    dayBoardingField.value = dayBoarding;  // "Day" or "Boarding"
    admField.value         = admissionFee;
    uniField.value         = uniformFee;
    contactField.value     = parentContact;
}

document.querySelectorAll('.btn-icon-view').forEach(btn => {
    btn.addEventListener('click', function() {
        const imageSrc = this.getAttribute('data-image');
        const fullImagePath = '/SchoolSystem/' + imageSrc;
        console.log('Loading image from:', fullImagePath);
        document.getElementById('modalImage').src = fullImagePath;
        document.getElementById('modalImage').onerror = function() {
            console.log('Image failed to load from:', fullImagePath);
            this.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="200"%3E%3Crect fill="%23ddd" width="200" height="200"/%3E%3Ctext x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="16" fill="%23999"%3EImage Not Found%3C/text%3E%3C/svg%3E';
        };
    });
});

// Auto-fill Expected Tuition when class changes in Admit form
document.addEventListener('DOMContentLoaded', function () {
    const classSelect = document.querySelector('select[name="class_id"]');
    const expectedInput = document.getElementById('expectedTuition');

    if (classSelect && expectedInput) {
        const updateExpected = () => {
            const opt = classSelect.options[classSelect.selectedIndex];
            if (!opt) {
                expectedInput.value = '';
                return;
            }
            const val = opt.getAttribute('data-expected-tuition') || '0';
            const num = parseFloat(val) || 0;
            expectedInput.value = num.toFixed(2);
        };

        classSelect.addEventListener('change', updateExpected);
        // Set initial value if a class is pre-selected
        updateExpected();
    }
});
