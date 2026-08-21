import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo?: Echo<'reverb'>;
    }
}

const enabled = import.meta.env.VITE_REVERB_ENABLED === 'true';
const key = import.meta.env.VITE_REVERB_APP_KEY;
const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
const port = Number(import.meta.env.VITE_REVERB_PORT || 8081);
const useTLS = scheme === 'https';

if (enabled && key) {
    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
        wsPort: port,
        wssPort: port,
        forceTLS: useTLS,
        enabledTransports: useTLS ? ['wss'] : ['ws'],
    });
}
