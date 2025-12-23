// Print invoice functionality
document.addEventListener('DOMContentLoaded', function() {
    const printBtn = document.querySelector('.btn-print');
    if (printBtn) {
        printBtn.addEventListener('click', function() {
            window.print();
        });
    }
});
