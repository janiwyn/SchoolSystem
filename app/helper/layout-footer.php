</div>

</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Layout JS -->
<script src="../../assets/js/layout.js"></script>

<script>
    function closeNotificationModal() {
        const modal = document.getElementById('notificationModal');
        if (modal) {
            modal.classList.remove('show');
            // Remove from DOM after animation completes
            setTimeout(() => {
                modal.style.display = 'none';
            }, 400);
        }
    }

    function goToNotifications() {
        window.location.href = '<?php echo preg_replace('/\/[^\/]+$/', '', dirname($_SERVER['SCRIPT_NAME'])); ?>/notifications/notifications.php';
    }

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-close notification modal after 10 seconds
        const modal = document.getElementById('notificationModal');
        if (modal && modal.classList.contains('show')) {
            setTimeout(() => {
                closeNotificationModal();
            }, 10000);
        }

        // Allow clicking outside modal to close
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeNotificationModal();
                }
            });
        }

        // Make sure close buttons work
        const closeButtons = document.querySelectorAll('.notification-modal-close');
        closeButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                closeNotificationModal();
            });
        });

        // Make sure close button in footer works
        const closeFooterBtn = document.querySelector('.btn-close-modal');
        if (closeFooterBtn) {
            closeFooterBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeNotificationModal();
            });
        }
    });

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
</script>

</body>
</html>
