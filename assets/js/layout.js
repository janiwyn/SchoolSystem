// Close notification modal functionality
function closeNotificationModal() {
    const modal = document.getElementById('notificationModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 400);
    }
}

// Navigate to notifications page
function goToNotifications() {
    window.location.href = '../../app/notifications/notifications.php';
}

// Initialize notification modal on DOM ready
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
