// Backwards‑compatibility wrapper: ANY call to showSubTab(...) will work.
function showSubTab(tabName) {
    switchSubTab(tabName);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Get current active tab from URL or default to student_payments
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || 'student_payments';
    const activeSubTab = urlParams.get('subtab') || 'payments';
    
    // Show the correct tab content
    showTab(activeTab);
    
    // Show the correct sub-tab if on student_payments tab
    if (activeTab === 'student_payments') {
        switchSubTab(activeSubTab); // ensure Student Payments → Payments is visible by default
    }
});

// Switch main tabs
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.pending-tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab content
    const selectedTab = document.getElementById(tabName + '-tab');
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Mark the button as active
    const activeBtn = document.querySelector(`.pending-tab-btn[href="?tab=${tabName}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }
}

// Switch sub-tabs (called from HTML onclick)
function switchSubTab(tabName) {
    // Hide all sub-tab contents
    document.querySelectorAll('.sub-tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active class from all sub-tab buttons
    document.querySelectorAll('.sub-tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected sub-tab content
    const selectedContent = document.getElementById(tabName + '-content');
    if (selectedContent) {
        selectedContent.classList.add('active');
    }
    
    // Mark the button as active
    const activeBtn = document.querySelector(`.sub-tab-btn[onclick="switchSubTab('${tabName}')"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }
    
    // Update URL
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('subtab', tabName);
    window.history.pushState(null, '', '?' + urlParams.toString());
}
