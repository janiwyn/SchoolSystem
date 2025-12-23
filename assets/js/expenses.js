// Toggle Expense Form
function toggleExpenseForm() {
    const form = document.getElementById('expenseFormCard');
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
