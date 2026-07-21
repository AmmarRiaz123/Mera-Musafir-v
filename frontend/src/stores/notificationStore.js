import { defineStore, acceptHMRUpdate } from 'pinia'
import { api } from 'src/boot/axios'

export const useNotificationStore = defineStore('notifications', {
  state: () => ({
    requests: [],
    loading: false,
    // Target user for the "send a message request" compose dialog (or null).
    promptUser: null,
  }),
  getters: {
    count: (state) => state.requests.length,
  },
  actions: {
    async fetchRequests() {
      this.loading = true
      try {
        const { data } = await api.get('/api/v1/message-requests')
        this.requests = data.data
      } finally {
        this.loading = false
      }
    },

    async dismiss(id) {
      await api.delete(`/api/v1/message-requests/${id}`)
      this.requests = this.requests.filter((r) => r.id !== id)
    },

    // Accept a request: delivers the sender's held message into a real
    // conversation and returns its id so the caller can navigate there.
    async accept(request) {
      const { data } = await api.post(`/api/v1/message-requests/${request.id}/accept`)
      this.requests = this.requests.filter((r) => r.id !== request.id)
      return data.conversation_id
    },

    // --- Sender side: composing a request to a gated user ---
    promptRequest(user) {
      this.promptUser = user
    },
    cancelPrompt() {
      this.promptUser = null
    },
    async sendRequest(message) {
      if (!this.promptUser) return
      await api.post('/api/v1/message-requests', {
        recipient_id: this.promptUser.id,
        message: message || null,
      })
      this.promptUser = null
    },
  },
})

// Keep the live store in sync when this file is edited during dev.
// Without this, Pinia keeps the already-instantiated store (old actions)
// until a full page reload.
if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useNotificationStore, import.meta.hot))
}
