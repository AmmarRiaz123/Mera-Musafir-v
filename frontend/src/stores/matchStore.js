import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'

export const useMatchStore = defineStore('match', {
  state: () => ({
    suggestedTrips: [],
    suggestedTravelers: [],
    loading: false,
    travelersLoading: false,
  }),

  actions: {
    async fetchSuggestedTrips() {
      this.loading = true
      try {
        const r = await api.get('/api/v1/match/trips')
        this.suggestedTrips = r.data.data
      } finally {
        this.loading = false
      }
    },

    async fetchSuggestedTravelers(tripId) {
      this.travelersLoading = true
      try {
        const r = await api.get(`/api/v1/match/trips/${tripId}/travelers`)
        this.suggestedTravelers = r.data.data
      } finally {
        this.travelersLoading = false
      }
    },
  },
})
