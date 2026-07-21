import { defineStore, acceptHMRUpdate } from 'pinia'
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

// Keep the live store in sync when this file is edited during dev.
// Without this, Pinia keeps the already-instantiated store (old actions)
// until a full page reload.
if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useMatchStore, import.meta.hot))
}
