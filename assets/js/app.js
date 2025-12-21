// Detect offline / online
function updateConnectionStatus() {
    if (!navigator.onLine) {
        console.warn("Offline mode enabled");
    }
}

window.addEventListener("offline", updateConnectionStatus);
window.addEventListener("online", updateConnectionStatus);

// Run on page load
updateConnectionStatus();
