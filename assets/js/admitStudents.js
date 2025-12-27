function toggleAdmitForm() {
    const formCard = document.getElementById('admitFormCard');
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

function loadEditForm(id, firstName, gender, admissionFee, uniformFee, parentContact, dayBoarding, classId) {
    document.getElementById('editStudentId').value = id;
    document.getElementById('editFirstName').value = firstName;
    document.getElementById('editGender').value = gender;
    document.getElementById('editAdmissionFee').value = admissionFee;
    document.getElementById('editUniformFee').value = uniformFee;
    document.getElementById('editParentContact').value = parentContact;
    document.getElementById('editDayBoarding').value = dayBoarding;
    document.getElementById('editClassId').value = classId;
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
