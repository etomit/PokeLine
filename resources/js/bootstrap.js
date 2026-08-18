import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const meta = name => document.querySelector(`meta[name="${name}"]`)?.content;
const reverbKey = meta('reverb-key');
const reverbHost = meta('reverb-host');
const reverbPort = Number(meta('reverb-port') || 443);
const reverbScheme = meta('reverb-scheme') || 'https';

if (reverbKey && reverbHost) {
    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {headers: {'X-CSRF-TOKEN': meta('csrf-token')}},
    });
}
