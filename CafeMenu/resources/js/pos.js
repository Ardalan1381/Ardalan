import { formatMoney } from './money';
import { trans } from './i18n';

// Shared cart-building logic for the staff POS/waiter screens
// (admin/pos/table.blade.php and admin/pos/counter.blade.php). The cart
// lives only in memory for the lifetime of the page — there is one page
// visit per table/counter order, unlike the public menu's cart which
// persists across page navigations in localStorage (see cart-store.js) —
// so a plain in-memory array is enough here.
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-pos-form]');
    if (! form) {
        return;
    }

    const cartItemsEl = document.getElementById('pos-cart-items');
    const cartEmptyEl = document.getElementById('pos-cart-empty');
    const cartTotalEl = document.getElementById('pos-cart-total');
    const cartErrorEl = document.getElementById('pos-cart-error');
    const itemsInputsEl = document.getElementById('pos-items-inputs');
    const submitButton = document.getElementById('pos-submit-button');

    /** @type {Array<{productId:number,name:string,unitPrice:number,quantity:number,options:Array<{id:number,name:string,priceDelta:number}>,notes:?string}>} */
    let cart = [];

    function lineTotal(item) {
        const optionsTotal = item.options.reduce((sum, o) => sum + o.priceDelta, 0);
        return (item.unitPrice + optionsTotal) * item.quantity;
    }

    function renderCart() {
        if (! cartItemsEl) {
            return;
        }

        cartItemsEl.innerHTML = '';

        if (cartEmptyEl) {
            cartEmptyEl.hidden = cart.length > 0;
        }
        if (submitButton) {
            submitButton.disabled = cart.length === 0;
        }

        cart.forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'flex items-start justify-between gap-2 rounded-lg border border-gray-200 dark:border-gray-700 p-2.5 text-sm';

            const optionsText = item.options.map((o) => o.name).join(', ');

            row.innerHTML = `
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-gray-900 dark:text-gray-100">${item.quantity}&times; ${item.name}</p>
                    ${optionsText ? `<p class="text-xs text-gray-500 dark:text-gray-400">${optionsText}</p>` : ''}
                    ${item.notes ? `<p class="text-xs text-gray-500 dark:text-gray-400">${trans('js.note_prefix', { note: item.notes })}</p>` : ''}
                    <p class="mt-1 text-xs font-semibold text-primary">${formatMoney(lineTotal(item))}</p>
                </div>
                <button type="button" data-remove class="shrink-0 text-xs text-danger hover:underline">${trans('js.remove')}</button>
            `;

            row.querySelector('[data-remove]').addEventListener('click', () => {
                cart.splice(index, 1);
                renderCart();
            });

            cartItemsEl.appendChild(row);
        });

        const total = cart.reduce((sum, item) => sum + lineTotal(item), 0);
        if (cartTotalEl) {
            cartTotalEl.textContent = formatMoney(total);
        }
    }

    // ---- Product catalog: search filter ----

    const productSearch = document.getElementById('pos-product-search');
    productSearch?.addEventListener('input', () => {
        const term = productSearch.value.trim().toLowerCase();
        document.querySelectorAll('[data-pos-product]').forEach((card) => {
            const matches = ! term || (card.dataset.productName || '').includes(term);
            card.hidden = ! matches;
        });
    });

    // ---- Product catalog: inline expand + configure + add to cart ----

    document.querySelectorAll('[data-pos-product-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const panel = button.nextElementSibling;
            if (panel) {
                panel.hidden = ! panel.hidden;
            }
        });
    });

    document.querySelectorAll('[data-pos-product-panel]').forEach((panel) => {
        const unitPrice = Number(panel.dataset.unitPrice);
        const groups = Array.from(panel.querySelectorAll('[data-option-group]'));
        const qtyInput = panel.querySelector('[data-pos-qty]');
        const notesInput = panel.querySelector('[data-pos-item-notes]');
        const lineTotalEl = panel.querySelector('[data-pos-line-total]');
        const errorEl = panel.querySelector('[data-pos-option-error]');
        const addButton = panel.querySelector('[data-pos-add-to-order]');

        function selectedOptions() {
            const options = [];
            groups.forEach((group) => {
                group.querySelectorAll('input:checked').forEach((input) => {
                    options.push({
                        id: Number(input.value),
                        name: input.dataset.optionName,
                        priceDelta: Number(input.dataset.priceDelta),
                    });
                });
            });
            return options;
        }

        function updatePreview() {
            const quantity = Math.max(1, Number(qtyInput?.value || 1));
            const optionsTotal = selectedOptions().reduce((sum, o) => sum + o.priceDelta, 0);
            if (lineTotalEl) {
                lineTotalEl.textContent = formatMoney((unitPrice + optionsTotal) * quantity);
            }
        }

        // Mirrors product.js's validateGroups(): a group whose selected
        // count falls outside [min, max] blocks adding the item, and names
        // the offending group so the error message can call it out.
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

        panel.addEventListener('change', updatePreview);
        qtyInput?.addEventListener('input', updatePreview);

        panel.querySelector('[data-pos-qty-increment]')?.addEventListener('click', () => {
            qtyInput.value = Math.min(50, Number(qtyInput.value || 1) + 1);
            updatePreview();
        });
        panel.querySelector('[data-pos-qty-decrement]')?.addEventListener('click', () => {
            qtyInput.value = Math.max(1, Number(qtyInput.value || 1) - 1);
            updatePreview();
        });

        updatePreview();

        addButton?.addEventListener('click', () => {
            const invalidGroup = validateGroups();

            if (invalidGroup) {
                if (errorEl) {
                    errorEl.textContent = trans('js.complete_option_group', { group: invalidGroup });
                    errorEl.hidden = false;
                }
                return;
            }

            if (errorEl) {
                errorEl.hidden = true;
            }

            cart.push({
                productId: Number(panel.dataset.productId),
                name: panel.dataset.productDisplayName,
                unitPrice,
                quantity: Math.max(1, Number(qtyInput?.value || 1)),
                options: selectedOptions(),
                notes: notesInput?.value || null,
            });

            // Reset the panel back to its default state and collapse it,
            // ready for the next time this product is added.
            groups.forEach((group) => {
                group.querySelectorAll('input').forEach((input) => {
                    input.checked = false;
                });
            });
            if (qtyInput) {
                qtyInput.value = 1;
            }
            if (notesInput) {
                notesInput.value = '';
            }
            updatePreview();
            panel.hidden = true;

            if (cartErrorEl) {
                cartErrorEl.hidden = true;
            }

            window.showToast?.(trans('js.pos.added_to_order', { name: panel.dataset.productDisplayName }), 'success');
            renderCart();
        });
    });

    // ---- Customer picker: client-side filter over the <select> options ----
    // There is no server-side customer search endpoint, so filtering just
    // rebuilds the option list from the full set captured on load rather
    // than relying on <option hidden>, which some browsers still render.

    const customerSelect = document.getElementById('customer_id');
    const customerSearch = document.getElementById('pos-customer-search');
    const allCustomerOptions = customerSelect ? Array.from(customerSelect.options) : [];

    customerSearch?.addEventListener('input', () => {
        const term = customerSearch.value.trim().toLowerCase();
        const previouslySelected = customerSelect.value;

        customerSelect.innerHTML = '';
        allCustomerOptions
            .filter((option) => ! option.value || ! term || (option.dataset.search || '').includes(term))
            .forEach((option) => customerSelect.appendChild(option));

        customerSelect.value = Array.from(customerSelect.options).some((o) => o.value === previouslySelected)
            ? previouslySelected
            : '';
        customerSelect.dispatchEvent(new Event('change'));
    });

    // ---- Loyalty reward: only meaningful once a customer is attached ----
    // Disabling (not just hiding) the select when no customer is picked
    // keeps a stale reward id out of the submitted form entirely.

    const loyaltyField = document.getElementById('pos-loyalty-field');
    const loyaltySelect = document.getElementById('loyalty_reward_id');

    function syncLoyaltyField() {
        const hasCustomer = !! customerSelect?.value;

        if (loyaltyField) {
            loyaltyField.hidden = ! hasCustomer;
        }
        if (loyaltySelect) {
            loyaltySelect.disabled = ! hasCustomer;
            if (! hasCustomer) {
                loyaltySelect.value = '';
            }
        }
    }

    customerSelect?.addEventListener('change', syncLoyaltyField);
    syncLoyaltyField();

    // ---- Counter-only: order type + guest name/phone toggling ----
    // Mirrors StorePosCounterOrderRequest's own rule: customer_name/phone
    // are only required when no customer_id is selected, so once a
    // customer is picked those fields are hidden rather than left visible
    // and confusing.

    const orderTypeSelect = document.getElementById('pos-order-type');
    const deliveryField = document.getElementById('pos-delivery-field');
    const customerNameFields = document.getElementById('pos-customer-name-fields');

    function syncOrderType() {
        if (deliveryField) {
            deliveryField.hidden = orderTypeSelect.value !== 'delivery';
        }
    }

    orderTypeSelect?.addEventListener('change', syncOrderType);
    syncOrderType();

    function syncCustomerNameFields() {
        if (customerNameFields) {
            customerNameFields.hidden = !! customerSelect?.value;
        }
    }

    customerSelect?.addEventListener('change', syncCustomerNameFields);
    syncCustomerNameFields();

    // ---- Restore an in-progress cart after a validation error round-trip ----
    // The server redirects back with withInput() on failure, but a plain
    // in-memory cart is otherwise lost on that reload — rebuild it from
    // old('items') using the embedded catalog map so staff don't have to
    // re-enter everything after fixing e.g. a missing customer name.

    function restoreOldItems() {
        const catalogEl = document.getElementById('pos-catalog-data');
        const oldItemsEl = document.getElementById('pos-old-items');
        if (! catalogEl || ! oldItemsEl) {
            return;
        }

        let catalog;
        let oldItems;
        try {
            catalog = JSON.parse(catalogEl.textContent);
            oldItems = JSON.parse(oldItemsEl.textContent);
        } catch (e) {
            return;
        }

        if (! Array.isArray(oldItems) || oldItems.length === 0) {
            return;
        }

        oldItems.forEach((oldItem) => {
            const product = catalog[oldItem.product_id];
            if (! product) {
                return;
            }

            const options = (oldItem.option_ids || [])
                .map((id) => product.options[id] && { id: Number(id), ...product.options[id] })
                .filter(Boolean);

            cart.push({
                productId: Number(oldItem.product_id),
                name: product.name,
                unitPrice: product.unitPrice,
                quantity: Math.max(1, Number(oldItem.quantity || 1)),
                options,
                notes: oldItem.notes || null,
            });
        });
    }

    restoreOldItems();
    renderCart();

    // ---- Submit: serialize the in-memory cart into hidden inputs ----

    form.addEventListener('submit', (event) => {
        if (cart.length === 0) {
            event.preventDefault();
            if (cartErrorEl) {
                cartErrorEl.textContent = trans('js.pos.cart_empty_error');
                cartErrorEl.hidden = false;
            }
            return;
        }

        if (! itemsInputsEl) {
            return;
        }

        itemsInputsEl.innerHTML = '';

        cart.forEach((item, index) => {
            const fields = [
                ['product_id', item.productId],
                ['quantity', item.quantity],
                ['notes', item.notes || ''],
            ];

            fields.forEach(([field, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `items[${index}][${field}]`;
                input.value = value;
                itemsInputsEl.appendChild(input);
            });

            item.options.forEach((option) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `items[${index}][option_ids][]`;
                input.value = option.id;
                itemsInputsEl.appendChild(input);
            });
        });
    });
});
