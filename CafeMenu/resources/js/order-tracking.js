document.addEventListener('DOMContentLoaded', () => {
    const badge = document.querySelector('[data-order-status]');
    if (! badge || ! window.Echo) {
        return;
    }

    const orderUuid = badge.dataset.orderUuid;

    window.Echo.channel(`order.${orderUuid}`).listen('.order.status-changed', (event) => {
        badge.textContent = window.__translations?.order_status?.[event.to_status] ?? event.to_status.replace('_', ' ');
    });
});
