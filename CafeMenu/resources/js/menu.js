import './cart-badge';
import { trans } from './i18n';

document.addEventListener('DOMContentLoaded', () => {
    const callWaiterButton = document.getElementById('call-waiter-button');

    if (callWaiterButton) {
        callWaiterButton.addEventListener('click', async () => {
            callWaiterButton.disabled = true;

            try {
                const response = await fetch('/call-waiter', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                const data = await response.json();

                if (! response.ok) {
                    const message = data.errors ? Object.values(data.errors).flat()[0] : data.message;
                    window.showToast?.(message || trans('js.something_went_wrong'), 'danger');
                    return;
                }

                window.showToast?.(data.message, 'success');
            } catch (e) {
                window.showToast?.(trans('js.network_error'), 'danger');
            } finally {
                callWaiterButton.disabled = false;
            }
        });
    }

    const searchInput = document.getElementById('menu-search');
    const productCards = Array.from(document.querySelectorAll('[data-product-card]'));
    const sections = Array.from(document.querySelectorAll('[data-category-section]'));
    const navLinks = Array.from(document.querySelectorAll('[data-category-link]'));

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const term = searchInput.value.trim().toLowerCase();

            productCards.forEach((card) => {
                const name = (card.dataset.productName || '').toLowerCase();
                card.hidden = term.length > 0 && ! name.includes(term);
            });

            sections.forEach((section) => {
                const visibleCards = section.querySelectorAll('[data-product-card]:not([hidden])');
                section.hidden = term.length > 0 && visibleCards.length === 0;
            });
        });
    }

    if (navLinks.length && sections.length && 'IntersectionObserver' in window) {
        const activeClasses = ['bg-primary', 'text-white'];
        const inactiveClasses = ['bg-white', 'text-gray-600'];

        const setActive = (id) => {
            navLinks.forEach((link) => {
                const isActive = link.getAttribute('href') === `#${id}`;
                link.classList.remove(...(isActive ? inactiveClasses : activeClasses));
                link.classList.add(...(isActive ? activeClasses : inactiveClasses));
            });
        };

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries.find((entry) => entry.isIntersecting);
                if (visible) {
                    setActive(visible.target.id);
                }
            },
            { rootMargin: '-45% 0px -50% 0px' }
        );

        sections.forEach((section) => observer.observe(section));
    }
});
