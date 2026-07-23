import { defineStore, acceptHMRUpdate } from 'pinia'
import { api } from 'src/boot/axios'

export const useNotificationStore = defineStore('notifications', {
  state: () => ({
    // Message requests are actionable (accept/ignore) and keep their own
    // lifecycle — left exactly as they were.
    requests: [],
    loading: false,
    promptUser: null,

    // The general notification feed and its unread tallies.
    items: [],
    itemsLoading: false,
    // { profile, community, messages, agency, bookings, trips, total } — only
    // non-zero keys are present, so any key means "show a dot".
    unread: {},

    _poll: null,
    _subscribed: false,
  }),

  getters: {
    // Legacy: the bell badge used to count message requests. Now it's the
    // notification total plus any pending requests.
    count: (state) => (state.unread.total || 0) + state.requests.length,
    hasDot: (state) => (category) => (state.unread[category] || 0) > 0,
  },

  actions: {
    // ── Message requests (unchanged) ──────────────────────────
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

    async accept(request) {
      const { data } = await api.post(`/api/v1/message-requests/${request.id}/accept`)
      this.requests = this.requests.filter((r) => r.id !== request.id)
      return data.conversation_id
    },

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

    // ── Notification feed ─────────────────────────────────────
    async fetchUnread() {
      try {
        const { data } = await api.get('/api/v1/notifications/unread')
        this.unread = data.unread || {}
      } catch {
        // A failed poll shouldn't clear the dots — leave the last known state.
      }
    },

    async fetchItems() {
      this.itemsLoading = true
      try {
        const { data } = await api.get('/api/v1/notifications')
        this.items = data.data || []
        this.unread = data.unread || {}
      } finally {
        this.itemsLoading = false
      }
    },

    // Clear a sidebar dot when its tab is opened. Optimistic so the dot
    // vanishes instantly; the server call reconciles the real count.
    async markCategoryRead(category) {
      if (!this.unread[category]) return
      this.items.forEach((n) => {
        if (n.category === category) n.is_read = true
      })
      this._dropUnread(category)
      try {
        const { data } = await api.post('/api/v1/notifications/read', { category })
        this.unread = data.unread || {}
      } catch {
        this.fetchUnread()
      }
    },

    async markAllRead() {
      this.items.forEach((n) => { n.is_read = true })
      this.unread = {}
      try {
        await api.post('/api/v1/notifications/read', {})
      } catch {
        this.fetchUnread()
      }
    },

    _dropUnread(category) {
      const removed = this.unread[category] || 0
      const next = { ...this.unread }
      delete next[category]
      next.total = Math.max(0, (next.total || 0) - removed)
      if (!next.total) delete next.total
      this.unread = next
    },

    // A live notification arrived over the socket.
    ingest(payload) {
      if (this.items.some((n) => n.id === payload.id)) return
      this.items.unshift(payload)
      const cat = payload.category
      this.unread = {
        ...this.unread,
        [cat]: (this.unread[cat] || 0) + 1,
        total: (this.unread.total || 0) + 1,
      }
    },

    // ── Lifecycle ─────────────────────────────────────────────
    // Counts come from a light poll (robust regardless of the Echo singleton's
    // state); a live subscription layers on top when window.Echo exists.
    start(userId) {
      this.fetchUnread()
      this.fetchRequests()

      if (!this._poll) {
        this._poll = setInterval(() => this.fetchUnread(), 45000)
      }
      this.subscribe(userId)
    },

    stop() {
      if (this._poll) {
        clearInterval(this._poll)
        this._poll = null
      }
      this.items = []
      this.unread = {}
      this.requests = []
      this._subscribed = false
    },

    subscribe(userId) {
      // window.Echo is owned by the chat stores and may not exist yet; the poll
      // covers us until it does, and a later subscribe() picks up the socket.
      if (this._subscribed || !window.Echo || !userId) return
      try {
        window.Echo.private(`App.Models.User.${userId}`)
          .listen('.notification.created', (payload) => this.ingest(payload))
        this._subscribed = true
      } catch {
        // No socket — polling still keeps the badges fresh.
      }
    },
  },
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useNotificationStore, import.meta.hot))
}
