import { CartStore } from './cart-store';
import { formatMoney } from './money';
import { trans } from './i18n';
import './cart-badge';

document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('cart-page');
    if (! page) {
        return;
    }

    const itemsContainer = document.getElementById('cart-items');
    const emptyMessage = document.getElementById('cart-empty');
    const subtotalEl = document.getElementById('cart-subtotal');
    const orderTypeSelect = document.getElementById('order-type');
    const customerFields = document.getElementById('customer-fields');
    const deliveryField = document.getElementById('delivery-address-field');
    const cashOnPickupField = document.getElementById('cash-on-pickup-field');
    const cashOnPickupCheckbox = document.getElementById('pay-cash-on-pickup');
    const errorBox = document.getElementById('checkout-error');
    const placeOrderButton = document.getElementById('place-order');

    // Ordering disabled: the page renders a message instead of the cart/
    // checkout form (see cart/index.blade.php), so none of the elements
    // above exist — nothing to wire up.
    if (! itemsContainer) {
        return;
    }

    const accepted = {
        dine_in: page.dataset.acceptsDineIn === '1' && page.dataset.hasTable === '1',
        takeaway: page.dataset.acceptsTakeaway === '1',
        pickup: page.dataset.acceptsPickup === '1',
        delivery: page.dataset.acceptsDelivery === '1',
    };
    const acceptsCashOnPickup = page.dataset.acceptsCashOnPickup === '1';

    function renderOrderTypeOptions() {
        Array.from(orderTypeSelect.options).forEach((option) => {
            option.hidden = ! accepted[option.value];
            option.disabled = ! accepted[option.value];
        });

        const firstAvailable = Array.from(orderTypeSelect.options).find((o) => accepted[o.value]);
        if (firstAvailable) {
            orderTypeSelect.value = firstAvailable.value;
        }

        toggleConditionalFields();
    }

    function toggleConditionalFields() {
        const type = orderTypeSelect.value;
        customerFields.hidden = type === 'dine_in';
        deliveryField.hidden = type !== 'delivery';

        const showCashOption = type === 'pickup' && acceptsCashOnPickup;
        cashOnPickupField.hidden = ! showCashOption;
        if (! showCashOption) {
            // Leaving pickup (or the option becoming unavailable) must clear
            // the checkbox too, not just hide it — otherwise switching back
            // and forth could carry a stale "pay cash" choice into a
            // checkout payload for a different order type.
            cashOnPickupCheckbox.checked = false;
        }
    }

    function renderItems() {
        const items = CartStore.getItems();
        itemsContainer.innerHTML = '';
        emptyMessage.hidden = items.length > 0;
        placeOrderButton.disabled = items.length === 0;

        items.forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'flex items-start justify-between gap-3 rounded-xl bg-white p-3 text-sm shadow-sm';

            const optionsText = (item.options || []).map((o) => o.name).join(', ');

            row.innerHTML = `
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-gray-900">${item.name}</p>
                    ${optionsText ? `<p class="text-xs text-gray-500">${optionsText}</p>` : ''}
                    ${item.notes ? `<p class="text-xs text-gray-500">${trans('js.note_prefix', { note: item.notes })}</p>` : ''}
                    <p class="mt-1 text-xs font-semibold text-primary">${formatMoney(CartStore.lineTotal(item))}</p>
                </div>
                <div class="flex items-center gap-1.5">
                    <button type="button" data-action="decrement" class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-200 text-gray-600 hover:bg-gray-50">−</button>
                    <span class="w-5 text-center font-medium">${item.quantity}</span>
                    <button type="button" data-action="increment" class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-200 text-gray-600 hover:bg-gray-50">+</button>
                    <button type="button" data-action="remove" class="ms-1.5 text-xs text-danger hover:underline">${trans('js.remove')}</button>
                </div>
            `;

            row.querySelector('[data-action="increment"]').addEventListener('click', () => {
                CartStore.updateQuantity(index, item.quantity + 1);
                renderItems();
            });
            row.querySelector('[data-action="decrement"]').addEventListener('click', () => {
                if (item.quantity > 1) {
                    CartStore.updateQuantity(index, item.quantity - 1);
                    renderItems();
                }
            });
            row.querySelector('[data-action="remove"]').addEventListener('click', () => {
                CartStore.removeItem(index);
                renderItems();
            });

            itemsContainer.appendChild(row);
        });

        subtotalEl.textContent = formatMoney(CartStore.subtotal());
    }

    orderTypeSelect.addEventListener('change', toggleConditionalFields);

    placeOrderButton.addEventListener('click', async () => {
        errorBox.hidden = true;
        placeOrderButton.disabled = true;

        const items = CartStore.getItems().map((item) => ({
            product_id: item.productId,
            quantity: item.quantity,
            notes: item.notes || null,
            option_ids: (item.options || []).map((o) => o.id),
        }));

        const payload = {
            type: orderTypeSelect.value,
            customer_name: document.getElementById('customer-name').value || null,
            customer_phone: document.getElementById('customer-phone').value || null,
            delivery_address: document.getElementById('delivery-address').value || null,
            notes: document.getElementById('order-notes').value || null,
            payment_method: ! cashOnPickupField.hidden && cashOnPickupCheckbox.checked ? 'cash' : null,
            items,
        };

        try {
            const response = await fetch('/checkout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (! response.ok) {
                const messages = data.errors ? Object.values(data.errors).flat() : [data.message || trans('js.something_went_wrong')];
                errorBox.textContent = messages.join(' ');
                errorBox.hidden = false;
                placeOrderButton.disabled = false;
                return;
            }

            CartStore.clear();
            window.location.href = data.redirect;
        } catch (e) {
            errorBox.textContent = trans('js.network_error');
            errorBox.hidden = false;
            placeOrderButton.disabled = false;
        }
    });

    renderOrderTypeOptions();
    renderItems();
});
