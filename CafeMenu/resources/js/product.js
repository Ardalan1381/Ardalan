import { CartStore } from './cart-store';
import { formatMoney } from './money';
import { trans } from './i18n';
import './cart-badge';

document.addEventListener('DOMContentLoaded', () => {
    const addButton = document.getElementById('add-to-cart');
    if (! addButton) {
        return;
    }

    const quantityInput = document.getElementById('quantity');
    const liveTotal = document.getElementById('live-total');
    const groups = Array.from(document.querySelectorAll('[data-option-group]'));
    const unitPrice = Number(addButton.dataset.unitPrice);

    function selectedOptions() {
        const options = [];

        groups.forEach((group) => {
            const inputs = group.querySelectorAll('input:checked');
            inputs.forEach((input) => {
                options.push({
                    id: Number(input.value),
                    name: input.dataset.optionName,
                    priceDelta: Number(input.dataset.priceDelta),
                });
            });
        });

        return options;
    }

    function updateTotal() {
        const quantity = Math.max(1, Number(quantityInput?.value || 1));
        const optionsTotal = selectedOptions().reduce((sum, o) => sum + o.priceDelta, 0);
        const total = (unitPrice + optionsTotal) * quantity;

        if (liveTotal) {
            liveTotal.textContent = formatMoney(total);
        }
    }

    function validateGroups() {
        for (const group of groups) {
            const min = Number(group.dataset.min || 0);
            const max = group.dataset.max ? Number(group.dataset.max) : null;
            const selected = group.querySelectorAll('input:checked').length;

            if (selected < min || (max !== null && selected > max)) {
                return group.dataset.groupName || false;
            }
        }

        return null;
    }

    groups.forEach((group) => {
        group.addEventListener('change', updateTotal);
    });
    quantityInput?.addEventListener('input', updateTotal);
    updateTotal();

    addButton.addEventListener('click', () => {
        const invalidGroup = validateGroups();
        const errorBox = document.getElementById('option-error');

        if (invalidGroup) {
            if (errorBox) {
                errorBox.textContent = trans('js.complete_option_group', { group: invalidGroup });
                errorBox.hidden = false;
            }

            return;
        }

        if (errorBox) {
            errorBox.hidden = true;
        }

        CartStore.addItem({
            productId: Number(addButton.dataset.productId),
            productSlug: addButton.dataset.productSlug,
            name: addButton.dataset.productName,
            unitPrice,
            quantity: Math.max(1, Number(quantityInput?.value || 1)),
            options: selectedOptions(),
            notes: document.getElementById('item-notes')?.value || null,
        });

        window.location.href = '/cart';
    });
});
