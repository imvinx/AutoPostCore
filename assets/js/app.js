/**
 * Master Application UI Controller
 * YouTube Automation Scheduling Platform
 */

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initToasts();
});

/**
 * Mobile Sidebar Controller
 */
function initSidebar() {
    const toggleBtn = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('open');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }
}

/**
 * Toast Notification Dispatcher System
 */
let toastContainer;

function initToasts() {
    toastContainer = document.createElement('div');
    toastContainer.className = 'toast-container';
    document.body.appendChild(toastContainer);
}

/**
 * Show a toast notification
 * @param {string} title
 * @param {string} message
 * @param {string} type 'success' | 'error' | 'warning' | 'info'
 * @param {number} duration ms before auto close
 */
function showToast(title, message, type = 'info', duration = 4000) {
    if (!toastContainer) {
        initToasts();
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    let icon = '';
    switch (type) {
        case 'success':
            icon = '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
            break;
        case 'error':
            icon = '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
            break;
        case 'warning':
            icon = '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
            break;
        default:
            icon = '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
    }

    toast.innerHTML = `
        <div class="toast-icon">${icon}</div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close">&times;</button>
    `;

    toastContainer.appendChild(toast);

    // Auto-remove toast
    const fadeTimer = setTimeout(() => {
        removeToast(toast);
    }, duration);

    // Close on click
    toast.querySelector('.toast-close').addEventListener('click', () => {
        clearTimeout(fadeTimer);
        removeToast(toast);
    });
}

function removeToast(toast) {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(20px) scale(0.9)';
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 300);
}

/**
 * Modal controller helpers
 */
const Modal = {
    open(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.style.opacity = '1';
                modal.querySelector('.modal-content').style.transform = 'translateY(0) scale(1)';
            }, 10);
        }
    },
    close(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.querySelector('.modal-content').style.transform = 'translateY(20px) scale(0.95)';
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
    }
};
