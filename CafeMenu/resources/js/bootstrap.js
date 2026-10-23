import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Real-time is optional: a bundle built before REVERB credentials existed
// has no key baked in, and `new Echo()` would then throw while this module
// loads — taking every other script on the page (cart, POS) down with it.
// Without a key the app simply runs without live updates; every consumer
// already checks `window.Echo` before subscribing.
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (! reverbKey) {
    console.warn('Real-time updates are disabled: VITE_REVERB_APP_KEY was empty when assets were built. Run `php artisan app:ensure-reverb-credentials`, then `npm run build`.');
}

window.Echo = reverbKey ? new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
}) : null;
