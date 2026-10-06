/**
 * Barangay Health Center Survey Management System
 * Main JavaScript File
 * Covers DOM manipulation, AJAX Helpers, Form Validation, Charts, etc.
 */

const App = (function() {
    'use strict';

    // Private variables
    let csrfToken = '';

    // =========================================================================
    // 1. Initialization & Core
    // =========================================================================
    const init = () => {
        // Get CSRF Token from meta tag
        const metaCsrf = document.querySelector('meta[name="csrf-token"]');
        if (metaCsrf) {
            csrfToken = metaCsrf.getAttribute('content');
        }

        // Initialize features
        loadDarkMode();
        initSidebarToggle();
        initTooltips();
        initActiveNav();
        initFormLoaders();
        initPhoneInputs();
        initPasswordToggles();
        fetchNotifications();
        setInterval(fetchNotifications, 30000); // Poll every 30s (was 10s)
        window.addEventListener('focus', fetchNotifications);

        // Attach event to static server-rendered Mark All as Read button
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.mark-all-read-btn');
            if (btn) {
                e.preventDefault();
                markAllNotificationsRead(btn);
            }
        });
    };

    const initPhoneInputs = () => {
        document.addEventListener('input', (e) => {
            if (e.target && (e.target.name === 'phone' || e.target.id === 'phone' || e.target.type === 'tel')) {
                e.target.value = e.target.value.replace(/[a-zA-Z]/g, '');
            }
        });
    };

    const initFormLoaders = () => {
        // Intercept form submissions to display in-app loading animation
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form && !form.classList.contains('no-loader')) {
                showLoading('Processing action, please wait...');
            }
        });

        // Hide overlay on page show (e.g. back/forward navigation)
        window.addEventListener('pageshow', () => {
            hideLoading();
        });
    };

    const initSidebarToggle = () => {
        const toggleBtn = document.querySelector('.navbar-toggler') || document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        
        let overlay = document.querySelector('.sidebar-overlay');
        if (!overlay && sidebar) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
        }

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', (e) => {
                e.preventDefault();
                sidebar.classList.toggle('show');
                if (overlay) overlay.classList.toggle('show');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', () => {
                if (sidebar) sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        }
    };

    const initTooltips = () => {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    };

    const initActiveNav = () => {
        const currentPath = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.sidebar .nav-link, .sidebar-nav .nav-link');
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href && currentPath && (currentPath === href.split('/').pop() || href.includes(currentPath))) {
                link.classList.add('active', 'bg-primary', 'text-white', 'rounded');
            }
        });
    };

    const initPasswordToggles = () => {
        document.addEventListener('click', (e) => {
            const toggleBtn = e.target.closest('.pw-toggle-btn, [data-pw-toggle]');
            if (toggleBtn) {
                e.preventDefault();
                const targetId = toggleBtn.getAttribute('data-pw-toggle');
                const input = targetId ? document.getElementById(targetId) : toggleBtn.previousElementSibling;
                const icon = toggleBtn.querySelector('i');
                if (input) {
                    const isHidden = input.type === 'password';
                    input.type = isHidden ? 'text' : 'password';
                    if (icon) {
                        icon.classList.toggle('bi-eye', !isHidden);
                        icon.classList.toggle('bi-eye-slash', isHidden);
                    }
                }
            }
        });
    };

    // =========================================================================
    // 2. Dark Mode Handling
    // =========================================================================
    const applyTheme = (isDark) => {
        document.body.classList.toggle('dark-mode', isDark);
        document.documentElement.classList.toggle('dark-mode', isDark);
        document.documentElement.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
        document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        updateDarkModeIcon(isDark);
    };

    const loadDarkMode = () => {
        const isDark = localStorage.getItem('theme') === 'dark';
        applyTheme(isDark);

        const toggleBtn = document.getElementById('darkModeToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleDarkMode);
        }
    };

    const toggleDarkMode = (e) => {
        if(e) e.preventDefault();
        const isDark = !document.body.classList.contains('dark-mode');
        applyTheme(isDark);
        window.dispatchEvent(new Event('themeChanged'));
    };

    const updateDarkModeIcon = (isDark) => {
        const icon = document.querySelector('#darkModeToggle i');
        if (icon) {
            if (isDark) {
                icon.classList.remove('bi-moon');
                icon.classList.add('bi-sun');
            } else {
                icon.classList.remove('bi-sun');
                icon.classList.add('bi-moon');
            }
        }
    };

    // =========================================================================
    // 3. UI Helpers (Toasts, Loaders, Modals)
    // =========================================================================
    
    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - 'success', 'error', 'warning', 'info'
     */
    const showToast = (message, type = 'success') => {
        // Ensure container exists
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '1060';
            document.body.appendChild(container);
        }

        // Determine icon and title based on type
        const icons = {
            success: '<i class="bi bi-check-circle-fill text-success me-2"></i>',
            error: '<i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>',
            warning: '<i class="bi bi-exclamation-circle-fill text-warning me-2"></i>',
            info: '<i class="bi bi-info-circle-fill text-info me-2"></i>'
        };
        
        const titles = {
            success: 'Success',
            error: 'Error',
            warning: 'Warning',
            info: 'Information'
        };

        const toastId = 'toast-' + Date.now();
        const toastHTML = `
            <div id="${toastId}" class="toast toast-${type}" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    ${icons[type]}
                    <strong class="me-auto">${titles[type]}</strong>
                    <small>Just now</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', toastHTML);
        const toastElement = document.getElementById(toastId);
        
        // Initialize bootstrap toast
        if (typeof bootstrap !== 'undefined') {
            const bsToast = new bootstrap.Toast(toastElement, { delay: 5000 });
            bsToast.show();
            
            // Clean up DOM after hidden
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        } else {
            // Fallback if bootstrap JS is missing
            toastElement.classList.add('showing', 'show');
            setTimeout(() => {
                toastElement.classList.remove('show');
                setTimeout(() => toastElement.remove(), 300);
            }, 5000);
        }
    };

    /**
     * Show/Hide Full Page Loading Spinner
     */
    const showLoading = (text = 'Processing, please wait...') => {
        let overlay = document.getElementById('loadingOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'loadingOverlay';
            overlay.className = 'loading-overlay';
            overlay.innerHTML = `
                <div class="spinner"></div>
                <div class="loading-text" id="loadingText">${text}</div>
            `;
            document.body.appendChild(overlay);
        } else {
            let textEl = document.getElementById('loadingText');
            if (textEl) {
                textEl.innerText = text;
            }
        }
        
        // Force reflow and activate
        void overlay.offsetWidth;
        overlay.classList.add('active');
    };

    const hideLoading = () => {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.classList.remove('active');
        }
    };

    /**
     * Confirmation Dialog (Custom System Modal)
     */
    const confirmAction = (message, callback, options = {}) => {
        let modalEl = document.getElementById('confirmModal');
        if (!modalEl) {
            const modalHTML = `
                <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content shadow-lg border-0">
                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title fw-bold" id="confirmModalTitle">Confirm Action</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center py-4">
                                <div class="mb-3">
                                    <i class="bi bi-question-circle text-primary" style="font-size: 3rem;" id="confirmModalIcon"></i>
                                </div>
                                <h5 class="fw-bold mb-2" id="confirmModalHeading">Are you sure?</h5>
                                <p class="text-muted mb-0 px-2" id="confirmModalMessage"></p>
                            </div>
                            <div class="modal-footer border-0 pt-0 justify-content-center gap-2 pb-4">
                                <button type="button" class="btn btn-light px-4 border" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary px-4" id="confirmModalBtn">Confirm</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            modalEl = document.getElementById('confirmModal');
        }

        const title = options.title || 'Confirm Action';
        const heading = options.heading || 'Are you sure?';
        const confirmText = options.confirmText || 'Confirm';
        const confirmClass = options.confirmClass || 'btn-primary';
        const iconClass = options.iconClass || 'bi bi-question-circle text-primary';

        document.getElementById('confirmModalTitle').innerText = title;
        document.getElementById('confirmModalHeading').innerText = heading;
        document.getElementById('confirmModalMessage').innerText = message;
        
        const confirmBtn = document.getElementById('confirmModalBtn');
        confirmBtn.innerText = confirmText;
        confirmBtn.className = `btn ${confirmClass} px-4`;

        const iconEl = document.getElementById('confirmModalIcon');
        if (iconEl) {
            iconEl.className = iconClass;
        }

        if (typeof bootstrap !== 'undefined') {
            const bsModal = new bootstrap.Modal(modalEl);
            
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            
            newConfirmBtn.addEventListener('click', () => {
                bsModal.hide();
                if (typeof callback === 'function') callback();
            });
            
            bsModal.show();
        } else {
            if (confirm(message)) {
                if (typeof callback === 'function') callback();
            }
        }
    };

    // =========================================================================
    // 4. AJAX Helper
    // =========================================================================
    /**
     * Perform an AJAX request
     * @param {string} url 
     * @param {string} method (GET, POST, PUT, DELETE)
     * @param {object|FormData} data 
     * @param {function} successCallback 
     * @param {function} errorCallback 
     */
    const ajaxRequest = async (url, method = 'GET', data = null, successCallback = null, errorCallback = null) => {
        showLoading('Processing...');
        
        try {
            const options = {
                method: method.toUpperCase(),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            // Add CSRF
            if (csrfToken) {
                options.headers['X-CSRF-TOKEN'] = csrfToken;
            }

            // Handle data
            if (data) {
                if (data instanceof FormData) {
                    options.body = data;
                    // Don't set Content-Type for FormData, browser does it automatically with boundary
                } else {
                    options.headers['Content-Type'] = 'application/json';
                    options.body = JSON.stringify(data);
                }
            }

            const response = await fetch(url, options);
            const result = await response.json();
            
            hideLoading();

            if (response.ok && result.success !== false) {
                if (successCallback) successCallback(result);
            } else {
                const errorMsg = result.message || result.error || 'An error occurred processing your request.';
                showToast(errorMsg, 'error');
                if (errorCallback) errorCallback(result);
            }
        } catch (error) {
            hideLoading();
            console.error('AJAX Error:', error);
            showToast('Network error or server unreachable.', 'error');
            if (errorCallback) errorCallback(error);
        }
    };

    // =========================================================================
    // 5. Form Validation
    // =========================================================================
    const validateForm = (formId) => {
        const form = document.getElementById(formId);
        if (!form) return false;

        let isValid = true;
        
        // Clear previous validation marks
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());

        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            let fieldValid = true;
            let errorMsg = 'This field is required.';

            if (!input.value.trim()) {
                fieldValid = false;
            } else if (input.type === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(input.value)) {
                    fieldValid = false;
                    errorMsg = 'Please enter a valid email address.';
                }
            } else if (input.minLength > 0 && input.value.length < input.minLength) {
                fieldValid = false;
                errorMsg = `Minimum length is ${input.minLength} characters.`;
            }

            if (!fieldValid) {
                isValid = false;
                input.classList.add('is-invalid');
                
                const feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                feedback.innerText = errorMsg;
                
                // Append after input (or after input-group if wrapped)
                if (input.parentElement.classList.contains('input-group')) {
                    input.parentElement.insertAdjacentElement('afterend', feedback);
                } else {
                    input.insertAdjacentElement('afterend', feedback);
                }
            }
        });

        return isValid;
    };



    // =========================================================================
    // 7. Table Search / Filter / Pagination
    // =========================================================================
    const initSearch = (inputId, tableId) => {
        const input = document.getElementById(inputId);
        const table = document.getElementById(tableId);
        
        if (!input || !table) return;

        input.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    };

    const filterTable = (filterValue, tableId, columnIndex) => {
        const table = document.getElementById(tableId);
        if (!table) return;

        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            if (filterValue === 'all' || filterValue === '') {
                row.style.display = '';
                return;
            }
            
            const cell = row.cells[columnIndex];
            if (cell) {
                const text = cell.textContent.trim().toLowerCase();
                if (text === filterValue.toLowerCase() || text.includes(filterValue.toLowerCase())) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    };

    // =========================================================================
    // 8. Chart Helpers (Requires Chart.js CDN in header)
    // =========================================================================
    const defaultColors = [
        '#2563eb', '#38bdf8', '#0ea5e9', '#1d4ed8', '#6366f1', 
        '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1'
    ];

    const getChartOptions = (isDark) => {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: isDark ? '#e2e8f0' : '#1e293b'
                    }
                }
            }
        };
    };

    const createPieChart = (canvasId, labels, data, colors = defaultColors) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx || typeof Chart === 'undefined') return null;
        
        const isDark = document.body.classList.contains('dark-mode');
        
        return new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors.slice(0, data.length),
                    borderWidth: 1,
                    borderColor: isDark ? '#152033' : '#ffffff'
                }]
            },
            options: getChartOptions(isDark)
        });
    };

    const createBarChart = (canvasId, labels, data, label = 'Dataset', colors = defaultColors) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx || typeof Chart === 'undefined') return null;
        
        const isDark = document.body.classList.contains('dark-mode');
        const options = getChartOptions(isDark);
        options.scales = {
            y: {
                beginAtZero: true,
                ticks: { color: isDark ? '#94a3b8' : '#64748b' },
                grid: { color: isDark ? '#334155' : '#e2e8f0' }
            },
            x: {
                ticks: { color: isDark ? '#94a3b8' : '#64748b' },
                grid: { display: false }
            }
        };

        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: data,
                    backgroundColor: colors[0],
                    borderRadius: 4
                }]
            },
            options: options
        });
    };

    const createLineChart = (canvasId, labels, data, label = 'Dataset', color = defaultColors[0]) => {
        const ctx = document.getElementById(canvasId);
        if (!ctx || typeof Chart === 'undefined') return null;
        
        const isDark = document.body.classList.contains('dark-mode');
        const options = getChartOptions(isDark);
        
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: data,
                    borderColor: color,
                    backgroundColor: color + '33', // 20% opacity
                    tension: 0.4,
                    fill: true
                }]
            },
            options: options
        });
    };

    // =========================================================================
    // 9. Export & Print Helpers
    // =========================================================================
    const printReport = () => {
        window.print();
    };

    /**
     * Export Table to Excel
     * Requires SheetJS: <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
     */
    const exportToExcel = (tableId, filename = 'export.xlsx') => {
        if (typeof XLSX === 'undefined') {
            showToast('Export library not loaded. Please contact administrator.', 'error');
            return;
        }
        
        const table = document.getElementById(tableId);
        if (!table) return;

        const wb = XLSX.utils.table_to_book(table, {sheet: "Sheet1"});
        XLSX.writeFile(wb, filename);
    };

    /**
     * Export element to PDF
     * Requires:
     * <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
     * <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
     */
    const exportToPDF = async (elementId, filename = 'export.pdf') => {
        if (typeof html2canvas === 'undefined' || typeof window.jspdf === 'undefined') {
            showToast('PDF Export library not loaded.', 'error');
            return;
        }

        showLoading('Generating PDF...');
        try {
            const element = document.getElementById(elementId);
            const canvas = await html2canvas(element, { scale: 2 });
            const imgData = canvas.toDataURL('image/png');
            
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('p', 'mm', 'a4');
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
            
            pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
            pdf.save(filename);
            
            hideLoading();
            showToast('PDF exported successfully!');
        } catch (error) {
            hideLoading();
            console.error(error);
            showToast('Failed to generate PDF.', 'error');
        }
    };

    // =========================================================================
    // 10. Utilities (Password, Dates, etc.)
    // =========================================================================
    const checkPasswordStrength = (password) => {
        let strength = 0;
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]+/)) strength++;
        if (password.match(/[A-Z]+/)) strength++;
        if (password.match(/[0-9]+/)) strength++;
        if (password.match(/[$@#&!]+/)) strength++;
        
        // Returns 0 to 5
        return strength;
    };

    const formatDate = (dateString, includeTime = false) => {
        if (!dateString) return '';
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        if (includeTime) {
            options.hour = '2-digit';
            options.minute = '2-digit';
        }
        return new Date(dateString).toLocaleDateString('en-PH', options);
    };



    // Expose globally for pages that call togglePassword() inline
    window.togglePassword = (inputId = 'password', toggleId = 'toggleIcon') => {
        const input = document.getElementById(inputId);
        const icon  = document.getElementById(toggleId);
        if (!input || !icon) return;
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        icon.classList.toggle('bi-eye',      !isHidden);
        icon.classList.toggle('bi-eye-slash',  isHidden);
    };

    const updateNotificationBell = (count) => {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            badge.innerText = count > 99 ? '99+' : count;
            badge.style.display = count > 0 ? 'inline-block' : 'none';
        }
    };

    const fetchNotifications = () => {
        const isAdmin   = window.location.pathname.includes('/admin/');
        const isAuth    = window.location.pathname.includes('/auth/');
        const isResident = window.location.pathname.includes('/resident/');
        const prefix = (isAdmin || isAuth || isResident) ? '../ajax/' : 'ajax/';
        const url = prefix + 'notifications.php';

        fetch(url, { cache: 'no-store' })
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                const count = data.unread_count || 0;
                updateNotificationBell(count);

                const menu = document.querySelector('.dropdown-menu-notifications');
                if (!menu) return;

                let html = '<li><h6 class="dropdown-header d-flex justify-content-between align-items-center fw-bold">'
                         + '<span><i class="bi bi-bell me-1"></i>Notifications</span>';

                if (count > 0) {
                    html += `<span class="badge bg-danger rounded-pill" style="font-size:0.65rem">${count > 99 ? '99+' : count}</span>`;
                }
                html += '</h6></li>';

                if (count > 0 && data.items && data.items.length > 0) {
                    data.items.forEach(item => {
                        const icon    = item.icon   || 'bi-bell';
                        const title   = item.title  || 'Notification';
                        const message = item.message || '';
                        const time    = item.time   || '';

                        html += `
                            <li>
                                <a class="dropdown-item notif-item py-2" href="${item.link}">
                                    <div class="d-flex align-items-start gap-2">
                                        <span class="notif-icon-wrap flex-shrink-0">
                                            <i class="bi ${icon} fs-5"></i>
                                        </span>
                                        <div class="notif-content flex-grow-1">
                                            <div class="notif-title fw-semibold text-wrap">${title}</div>
                                            <div class="notif-msg text-muted small text-wrap">${message}</div>
                                            <div class="text-muted mt-1" style="font-size:0.68rem">${time}</div>
                                        </div>
                                    </div>
                                </a>
                            </li>`;
                    });
                    html += '<li><hr class="dropdown-divider my-1"></li>';
                    html += '<li><a class="dropdown-item text-center small text-primary py-2 fw-semibold mark-all-read-btn" href="#" id="markAllReadBtn">'
                          + '<i class="bi bi-check-all me-1"></i>Mark all as read</a></li>';
                } else {
                    html += '<li><div class="dropdown-item text-muted text-center py-4">'
                          + '<i class="bi bi-check2-circle text-success fs-4 d-block mb-1"></i>'
                          + '<small>No new notifications</small></div></li>';
                }

                menu.innerHTML = html;

                // Attach event listener to the Mark All button
                const markAllBtn = menu.querySelector('#markAllReadBtn');
                if (markAllBtn) {
                    markAllBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        markAllNotificationsRead(this);
                    });
                }
            })
            .catch(() => {});
    };

    // Global helper to mark all notifications read
    let _markingAllRead = false;
    const markAllNotificationsRead = (btnEl) => {
        if (_markingAllRead) return;
        _markingAllRead = true;

        // Show loading state on button
        if (btnEl && btnEl.tagName) {
            btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Marking...';
            btnEl.style.pointerEvents = 'none';
        }

        const isAdmin    = window.location.pathname.includes('/admin/');
        const isAuth     = window.location.pathname.includes('/auth/');
        const isResident = window.location.pathname.includes('/resident/');
        const prefix = (isAdmin || isAuth || isResident) ? '../ajax/' : 'ajax/';
        const url = prefix + 'mark_notification.php';

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_all_read'
        })
        .then(res => res.json())
        .then(data => {
            _markingAllRead = false;
            if (data.success) {
                updateNotificationBell(0);
                const menu = document.querySelector('.dropdown-menu-notifications');
                if (menu) {
                    menu.innerHTML = '<li><h6 class="dropdown-header d-flex justify-content-between align-items-center fw-bold">'
                                   + '<span><i class="bi bi-bell me-1"></i>Notifications</span></h6></li>'
                                   + '<li><div class="dropdown-item text-muted text-center py-4">'
                                   + '<i class="bi bi-check2-circle text-success fs-4 d-block mb-1"></i>'
                                   + '<small>No new notifications</small></div></li>';
                }
                showToast('All notifications marked as read.', 'success');
            } else {
                showToast('Could not mark notifications as read.', 'error');
                if (btnEl && btnEl.tagName) {
                    btnEl.innerHTML = '<i class="bi bi-check-all me-1"></i>Mark all as read';
                    btnEl.style.pointerEvents = '';
                }
            }
        })
        .catch(() => {
            _markingAllRead = false;
            showToast('Network error. Please try again.', 'error');
            if (btnEl && btnEl.tagName) {
                btnEl.innerHTML = '<i class="bi bi-check-all me-1"></i>Mark all as read';
                btnEl.style.pointerEvents = '';
            }
        });
    };

    // Expose globally for legacy inline calls (kept for backward compat)
    window.markAllNotificationsRead = function(e) {
        if (e && e.preventDefault) e.preventDefault();
        markAllNotificationsRead(e && e.currentTarget ? e.currentTarget : null);
    };

    // Auto Initialize on DOM Ready
    document.addEventListener('DOMContentLoaded', init);

    // Expose Public API
    return {
        showToast,
        showLoading,
        hideLoading,
        confirmAction,
        ajaxRequest,
        validateForm,
        initSearch,
        filterTable,
        createPieChart,
        createBarChart,
        createLineChart,
        printReport,
        exportToExcel,
        exportToPDF,
        checkPasswordStrength,
        formatDate,
        updateNotificationBell,
        toggleDarkMode
    };

})();

// Export for module systems if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = App;
}
