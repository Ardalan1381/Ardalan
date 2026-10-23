import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/menu.js',
                'resources/js/cart.js',
                'resources/js/product.js',
                'resources/js/order-tracking.js',
                'resources/js/admin-notifications.js',
                'resources/js/admin-waiter-calls.js',
                'resources/js/pos.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
