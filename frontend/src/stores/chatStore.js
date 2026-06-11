import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

export const useChatStore = defineStore('chat', {
  state: () => ({
    messages: [],
    loading: false,
    currentTripId: null,
  }),

  actions: {
    async fetchMessages(tripId) {
      this.loading = true
      try {
        const response = await api.get(`/api/v1/trips/${tripId}/chat/messages`)
        this.messages = response.data.data
        this.currentTripId = tripId
      } catch (error) {
        console.error('Error fetching messages:', error)
      } finally {
        this.loading = false
      }
    },

    async sendMessage(tripId, body) {
      const response = await api.post(`/api/v1/trips/${tripId}/chat/messages`, { body })
      // Add own message immediately from HTTP response — no Echo round-trip needed
      this.addMessage(response.data.data)
    },

    addMessage(message) {
      // Deduplicate: if Echo also fires for the sender, don't add twice
      if (this.messages.find((m) => m.id === message.id)) return
      this.messages.push(message)
    },

    subscribeToTrip(tripId) {
      const token = localStorage.getItem('token')
      if (!token) return

      // Reinitialize Echo with the current token every time we subscribe.
      // The boot-time instance may have had a null token (user logged in after app load),
      // which causes private channel auth to silently fail.
      window.Pusher = Pusher

      if (window.Echo) {
        window.Echo.disconnect()
      }

      window.Echo = new Echo({
        broadcaster:       'reverb',
        key:               import.meta.env.VITE_REVERB_APP_KEY,
        wsHost:            import.meta.env.VITE_REVERB_HOST || 'localhost',
        wsPort:            parseInt(import.meta.env.VITE_REVERB_PORT) || 8080,
        wssPort:           parseInt(import.meta.env.VITE_REVERB_PORT) || 8080,
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

      window.Echo.private(`trip.${tripId}`).listen('.message.sent', (e) => {
        this.addMessage(e)
      })
    },

    unsubscribeFromTrip(tripId) {
      if (!window.Echo) return
      window.Echo.leave(`trip.${tripId}`)
    },
  },
})
