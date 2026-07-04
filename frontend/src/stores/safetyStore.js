import { defineStore } from 'pinia'
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

    async toggleBlock(userId) {
      const r = await api.post(`/api/v1/users/${userId}/block`)
      if (!r.data.blocked) {
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
