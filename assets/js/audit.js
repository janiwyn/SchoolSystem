// Currently no JavaScript functionality needed for audit page
// This file is created for future enhancements such as:
// - Export to PDF/Excel
// - Advanced filtering
// - Chart visualization
// - Real-time updates

function toggleExpensesDropdown() {
    const container = document.getElementById('expensesDetailContainer');
    const icon = document.getElementById('expensesToggleIcon');
    
    if (container.style.display === 'none') {
        container.style.display = 'table-row';
        icon.style.transform = 'rotate(90deg)';
    } else {
        container.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}
