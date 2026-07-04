import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

export const useSocialStore = defineStore('social', {
  state: () => ({
    // People discovery
    users: [],
    usersMeta: { total: 0, per_page: 20, current_page: 1, last_page: 1 },
    usersLoading: false,

    // Single user profile (source of truth for follow state)
    currentUser: null,
    currentUserLoading: false,

    // Conversations list
    conversations: [],
    convsLoading: false,

    // Active DM
    activeConversation: null,
    messages: [],
    messagesLoading: false,
  }),

  getters: {
    totalUnread: (state) =>
      state.conversations.reduce((acc, c) => acc + (c.unread_count || 0), 0),
  },

  actions: {
    // -------------------------------------------------------------------
    // People / user discovery
    // -------------------------------------------------------------------
    async fetchUsers(params = {}) {
      this.usersLoading = true
      try {
        const res = await api.get('/api/v1/users', { params })
        this.users = res.data.data
        this.usersMeta = res.data.meta
      } catch (e) {
        console.error('fetchUsers', e)
      } finally {
        this.usersLoading = false
      }
    },

    // Fetch a single user fresh from the API. The response carries the
    // authoritative is_following / followers_count, so callers should always
    // read those off currentUser rather than tracking local copies.
    async fetchUser(userId) {
      this.currentUserLoading = true
      try {
        const res = await api.get(`/api/v1/users/${userId}`)
        this.currentUser = res.data.data
        return this.currentUser
      } finally {
        this.currentUserLoading = false
      }
    },

    async followUser(userId) {
      const res = await api.post(`/api/v1/users/${userId}/follow`)

      // Patch the discovery card if present (clamp so count never goes negative)
      const u = this.users.find((x) => x.id === userId)
      if (u) {
        u.is_following = res.data.following
        u.followers_count = Math.max(0, (u.followers_count ?? 0) + (res.data.following ? 1 : -1))
      }

      // Patch the open profile if it's the same user
      if (this.currentUser && this.currentUser.id === userId) {
        this.currentUser.is_following = res.data.following
        this.currentUser.followers_count = Math.max(
          0,
          (this.currentUser.followers_count ?? 0) + (res.data.following ? 1 : -1),
        )
      }

      return res.data
    },

    // -------------------------------------------------------------------
    // Conversations inbox
    // -------------------------------------------------------------------
    async fetchConversations() {
      this.convsLoading = true
      try {
        const res = await api.get('/api/v1/conversations')
        this.conversations = res.data.data
      } catch (e) {
        console.error('fetchConversations', e)
      } finally {
        this.convsLoading = false
      }
    },

    async startConversation(userId) {
      const res = await api.post('/api/v1/conversations', { user_id: userId })
      return res.data.data
    },

    // -------------------------------------------------------------------
    // Active DM
    // -------------------------------------------------------------------
    async fetchConversation(conversationId) {
      this.messagesLoading = true
      try {
        const res = await api.get(`/api/v1/conversations/${conversationId}`)
        this.activeConversation = res.data.data
        this.messages = res.data.data.messages ?? []
        // Mark as read — update local unread count
        const conv = this.conversations.find((c) => c.id === conversationId)
        if (conv) conv.unread_count = 0
      } catch (e) {
        console.error('fetchConversation', e)
        throw e
      } finally {
        this.messagesLoading = false
      }
    },

    async sendMessage(conversationId, payload) {
      const res = await api.post(`/api/v1/conversations/${conversationId}/messages`, payload)
      this.addMessage(res.data.data)
      this.bumpConversation(conversationId, res.data.data)
      return res.data.data
    },

    async respondToInvite(conversationId, messageId, action) {
      const res = await api.post(
        `/api/v1/conversations/${conversationId}/messages/${messageId}/respond`,
        { action },
      )
      // Update the message in place
      const idx = this.messages.findIndex((m) => m.id === messageId)
      if (idx !== -1) this.messages[idx] = res.data.data
      return res.data
    },

    addMessage(message) {
      if (this.messages.find((m) => m.id === message.id)) return
      this.messages.push(message)
    },

    bumpConversation(conversationId, lastMsg) {
      const conv = this.conversations.find((c) => c.id === conversationId)
      if (conv) {
        conv.last_message = {
          body: lastMsg.body?.substring(0, 60),
          type: lastMsg.type,
          created_at: lastMsg.created_at,
          is_mine: true,
        }
        conv.last_message_at = lastMsg.created_at
      }
    },

    // -------------------------------------------------------------------
    // Echo / Reverb subscription for DMs
    // -------------------------------------------------------------------
    subscribeToConversation(conversationId) {
      const token = localStorage.getItem('token')
      if (!token) return

      window.Pusher = Pusher

      if (window.Echo) {
        window.Echo.disconnect()
      }

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

      window.Echo.private(`conversation.${conversationId}`).listen('.message.sent', (e) => {
        this.addMessage(e)
        this.bumpConversation(conversationId, e)
      })
    },

    unsubscribeFromConversation(conversationId) {
      if (!window.Echo) return
      window.Echo.leave(`conversation.${conversationId}`)
    },
  },
})
