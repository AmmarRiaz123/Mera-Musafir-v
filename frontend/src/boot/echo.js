import { boot } from 'quasar/wrappers'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

export default boot(() => {
  const token = localStorage.getItem('token')

  window.Echo = new Echo({
    broadcaster:       'reverb',
    key:               import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:            import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort:            import.meta.env.VITE_REVERB_PORT || 8080,
    wssPort:           import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS:          false,
    enabledTransports: ['ws', 'wss'],
    authEndpoint:      'http://localhost:8000/broadcasting/auth',
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept:        'application/json',
      },
    },
  })
})
