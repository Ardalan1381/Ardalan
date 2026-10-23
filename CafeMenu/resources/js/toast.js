const COLORS = {
    success: 'bg-success text-white',
    danger: 'bg-danger text-white',
    warning: 'bg-warning text-white',
    info: 'bg-gray-900 text-white',
};

export function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toast-container');
    if (! container || ! message) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = `pointer-events-auto max-w-sm rounded-lg px-4 py-3 text-sm shadow-lg transition-opacity duration-300 ${COLORS[type] || COLORS.info}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

window.showToast = showToast;
