import { defineStore, acceptHMRUpdate } from 'pinia'
import { api } from 'src/boot/axios'

export const useSafetyStore = defineStore('safety', {
  state: () => ({
    blockedUsers: [],
    loading: false,
  }),

  actions: {
    async reportContent(reportedId, reportedType, reason, description = null) {
      return api.post('/api/v1/report', {
        reported_id:   reportedId,
        reported_type: reportedType,
        reason,
        description,
      })
    },

    // `user` is optional; pass it so the blocked list keeps name/avatar for
    // the Privacy Settings screen without needing a refetch.
    async toggleBlock(userId, user = null) {
      const r = await api.post(`/api/v1/users/${userId}/block`)
      if (r.data.blocked) {
        if (!this.blockedUsers.some(u => u.id === userId)) {
          this.blockedUsers.push({
            id:     userId,
            name:   user?.name ?? null,
            avatar: user?.avatar ?? null,
          })
        }
      } else {
        this.blockedUsers = this.blockedUsers.filter(u => u.id !== userId)
      }
      return r.data
    },

    async fetchBlockList() {
      this.loading = true
      try {
        const r = await api.get('/api/v1/users/blocked')
        this.blockedUsers = r.data.data
      } finally {
        this.loading = false
      }
    },

    isBlocked(userId) {
      return this.blockedUsers.some(u => u.id === userId)
    },
  },
})

// Keep the live store in sync when this file is edited during dev.
// Without this, Pinia keeps the already-instantiated store (old actions)
// until a full page reload.
if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useSafetyStore, import.meta.hot))
}
