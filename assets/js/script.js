document.addEventListener('DOMContentLoaded', function() {
    // Timeout for Alerts (success, warning, danger) that are not permanent
    const autoCloseAlerts = document.querySelectorAll('.alert.alert-success:not(.alert-permanent), .alert.alert-warning:not(.alert-permanent), .alert.alert-danger:not(.alert-permanent)');
    if (autoCloseAlerts.length > 0) {
        autoCloseAlerts.forEach(alert => {
            setTimeout(() => {
                if (alert && alert.parentNode) {
                    const bsAlertInstance = bootstrap.Alert.getInstance(alert);
                    if (bsAlertInstance) {
                        bsAlertInstance.close();
                    }
                }
            }, 5000);
        });
    }

    // ID Card input validation
    const idCardInputs = document.querySelectorAll('input[name="id_card"], input[name="new_id_card"]');
    idCardInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 13) {
                this.value = this.value.slice(0, 13);
            }
        });
    });

    // Bootstrap Tooltip Initialization
    const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // --- Sidebar Logic ---
    const sidebarToggler = document.getElementById('headerCollapse'); // Hamburger in Navbar
    const sidebarCloseBtnInside = document.getElementById('sidebarCollapse'); // Close button inside Sidebar
    const leftSidebar = document.querySelector('.left-sidebar');
    const body = document.body;
    const mainWrapper = document.getElementById('main-wrapper'); // Used to check if logged in (add data-attributes in header.php)

    function pageShouldHaveSidebar() {
        const currentPage = new URLSearchParams(window.location.search).get('page') || 'home';
        const isUserSessionActive = mainWrapper && (mainWrapper.hasAttribute('data-user-id') || mainWrapper.hasAttribute('data-admin-id') || mainWrapper.hasAttribute('data-teacher-id'));
        return currentPage !== 'home' && isUserSessionActive;
    }

    function updateSidebarLayout() {
        if (!leftSidebar) return; // Do nothing if sidebar element doesn't exist

        if (pageShouldHaveSidebar()) {
            if (window.innerWidth >= 1200) { // Desktop view
                body.classList.add('sidebar-enabled');
                leftSidebar.classList.remove('show-sidebar'); // This class is primarily for mobile
            } else { // Mobile/Tablet view
                body.classList.remove('sidebar-enabled');
                // 'show-sidebar' on leftSidebar itself will be toggled by buttons for mobile
            }
            body.classList.remove('login-page-active'); // Ensure login-page-active is removed
        } else { // Login page or no user session
            body.classList.remove('sidebar-enabled');
            leftSidebar.classList.remove('show-sidebar');
            if (currentPage === 'home') { // Add specific class for login page styling
                 body.classList.add('login-page-active');
            } else {
                 body.classList.remove('login-page-active');
            }
        }
    }

    function toggleMobileSidebar() {
        if (leftSidebar && window.innerWidth < 1200 && pageShouldHaveSidebar()) {
            leftSidebar.classList.toggle('show-sidebar');
            // Example: Add an overlay to the body when mobile sidebar is open
            // if (leftSidebar.classList.contains('show-sidebar')) {
            //     const overlay = document.createElement('div');
            //     overlay.className = 'sidebar-overlay';
            //     overlay.onclick = toggleMobileSidebar; // Close sidebar on overlay click
            //     document.body.appendChild(overlay);
            // } else {
            //     const overlay = document.querySelector('.sidebar-overlay');
            //     if (overlay) overlay.remove();
            // }
        }
    }

    if (sidebarToggler) {
        sidebarToggler.addEventListener('click', function(e) {
            e.preventDefault();
            toggleMobileSidebar();
        });
    }
    if (sidebarCloseBtnInside) {
        sidebarCloseBtnInside.addEventListener('click', function(e) {
            e.preventDefault();
            toggleMobileSidebar();
        });
    }

    // Initial layout update and on resize
    updateSidebarLayout();
    window.addEventListener('resize', updateSidebarLayout);

    // Activate sidebar link based on current page
    if (pageShouldHaveSidebar()) {
        const currentQueryString = window.location.search; // e.g., "?page=student_dashboard"
        const sidebarNav = document.querySelector('.sidebar-nav');

        if (sidebarNav) {
            const sidebarLinks = sidebarNav.querySelectorAll('a.sidebar-link');

            sidebarLinks.forEach(link => {
                const linkHref = link.getAttribute('href');
                // Check if the link directly matches or if it's a parent of an active submenu item
                if (linkHref === currentQueryString) {
                    link.classList.add('active');

                    // Expand parent submenu if this link is inside one
                    const parentCollapseEl = link.closest('.collapse');
                    if (parentCollapseEl) {
                        const triggerLink = document.querySelector(`a.sidebar-link[data-bs-target="#${parentCollapseEl.id}"]`);
                        if (triggerLink) {
                            triggerLink.classList.add('active'); // Also make the trigger link active
                            triggerLink.setAttribute('aria-expanded', 'true');
                            triggerLink.classList.remove('collapsed');
                        }
                        // Ensure the collapse itself is shown
                        if (!parentCollapseEl.classList.contains('show')) {
                             const bsCollapse = bootstrap.Collapse.getInstance(parentCollapseEl) || new bootstrap.Collapse(parentCollapseEl, { toggle: false });
                             bsCollapse.show();
                        }
                    }
                } else {
                    link.classList.remove('active');
                }
            });
        }
    }
});

// SweetAlert2 function for delete confirmation
function confirmDelete(message = 'คุณแน่ใจหรือไม่ที่จะลบรายการนี้?') {
    return Swal.fire({
        title: 'ยืนยันการลบ',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'var(--bs-danger)',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        return result.isConfirmed;
    });
}
