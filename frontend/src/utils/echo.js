import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// One shared Reverb connection for the whole app. Chat, notifications and the
// community feed all subscribe to it — previously each store tore down and
// recreated window.Echo on subscribe, which silently killed everyone else's
// channels. This creates the instance once and reuses it, so channels coexist.
export function ensureEcho() {
  const token = localStorage.getItem('token')
  if (!token) return null
  if (window.Echo) return window.Echo

  window.Pusher = Pusher
  window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort: parseInt(import.meta.env.VITE_REVERB_PORT) || 8080,
    wssPort: parseInt(import.meta.env.VITE_REVERB_PORT) || 8080,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: 'http://localhost:8000/broadcasting/auth',
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    },
  })
  return window.Echo
}

// Drop the connection entirely — used on logout so the next login rebuilds it
// with a fresh token.
export function teardownEcho() {
  if (window.Echo) {
    window.Echo.disconnect()
    window.Echo = null
  }
}
