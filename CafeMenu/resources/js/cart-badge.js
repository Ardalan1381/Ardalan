import { CartStore } from './cart-store';

export function renderCartBadge() {
    const badges = document.querySelectorAll('[data-cart-count]');
    const count = CartStore.count();

    badges.forEach((badge) => {
        badge.textContent = count;
        badge.hidden = count === 0;
    });
}

document.addEventListener('DOMContentLoaded', renderCartBadge);
