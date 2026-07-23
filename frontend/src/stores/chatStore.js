import { defineStore, acceptHMRUpdate } from 'pinia'
import { api } from 'src/boot/axios'
import { ensureEcho } from 'src/utils/echo'

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
      // One shared connection (see utils/echo) — reused, not recreated, so this
      // no longer kills the DM, notification or community-feed channels.
      const echo = ensureEcho()
      if (!echo) return

      echo.private(`trip.${tripId}`).listen('.message.sent', (e) => {
        this.addMessage(e)
      })
    },

    unsubscribeFromTrip(tripId) {
      if (!window.Echo) return
      window.Echo.leave(`trip.${tripId}`)
    },
  },
})

// Keep the live store in sync when this file is edited during dev.
// Without this, Pinia keeps the already-instantiated store (old actions)
// until a full page reload.
if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useChatStore, import.meta.hot))
}
