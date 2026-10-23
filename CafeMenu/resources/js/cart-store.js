const STORAGE_KEY = 'cafe_cart';

function read() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch (e) {
        return [];
    }
}

function write(items) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch (e) {
        // Storage unavailable (private browsing, quota) — cart just won't persist.
    }
}

export const CartStore = {
    getItems() {
        return read();
    },

    addItem(item) {
        const items = read();
        items.push(item);
        write(items);
    },

    updateQuantity(index, quantity) {
        const items = read();
        if (items[index]) {
            items[index].quantity = Math.max(1, quantity);
            write(items);
        }
    },

    removeItem(index) {
        const items = read();
        items.splice(index, 1);
        write(items);
    },

    clear() {
        write([]);
    },

    count() {
        return read().reduce((sum, item) => sum + item.quantity, 0);
    },

    lineTotal(item) {
        const optionsTotal = (item.options || []).reduce((sum, o) => sum + o.priceDelta, 0);
        return (item.unitPrice + optionsTotal) * item.quantity;
    },

    subtotal() {
        return read().reduce((sum, item) => sum + this.lineTotal(item), 0);
    },
};
