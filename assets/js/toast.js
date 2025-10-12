function showToast(message, type = 'info') {
    const colors = {
        success: '#4CAF50',
        error: '#F44336',
        warning: '#FFC107',
        info: '#2196F3'
    };

    // Create container if it doesn't exist
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.position = 'fixed';
        container.style.top = '20px';
        container.style.right = '20px';
        container.style.zIndex = '9999';
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        container.style.gap = '10px';
        document.body.appendChild(container);
    }

    // Prevent duplicate toasts
    const existing = [...container.children].some(el => el.textContent === message);
    if (existing) return;

    const toast = document.createElement('div');
    toast.className = 'toast-message';
    toast.style.backgroundColor = colors[type] || colors.info;
    toast.style.color = '#fff';
    toast.style.padding = '12px 18px';
    toast.style.borderRadius = '6px';
    toast.style.fontSize = '14px';
    toast.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
    toast.style.animation = 'fadeInOut 3.5s ease-in-out';
    toast.textContent = message;

    container.appendChild(toast);

    // Auto remove after animation
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(30px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Add animation CSS once
(function addToastStyleOnce() {
    if (document.getElementById('toast-style')) return;

    const style = document.createElement('style');
    style.id = 'toast-style';
    style.innerHTML = `
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateX(50px); }
            10% { opacity: 1; transform: translateX(0); }
            90% { opacity: 1; transform: translateX(0); }
            100% { opacity: 0; transform: translateX(50px); }
        }
        .toast-message {
            animation: fadeInOut 3.5s ease-in-out;
            transition: all 0.3s ease-in-out;
        }
    `;
    document.head.appendChild(style);
})();
