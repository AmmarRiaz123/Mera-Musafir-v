import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'

export const useTripStore = defineStore('trip', {
  state: () => ({
    trips: [],
    currentTrip: null,
    myTrips: { created: [], joined: [] },
    pagination: {
      page: 1,
      rowsPerPage: 12,
      rowsNumber: 0,
      lastPage: 1
    },
    loading: false,
    filters: {
      type: '',
      visibility: '',
      search: ''
    }
  }),

  actions: {
    async fetchTrips(page = 1) {
      this.loading = true
      try {
        const params = { page }
        if (this.filters.type) params.type = this.filters.type
        if (this.filters.visibility) params.visibility = this.filters.visibility
        if (this.filters.search) params.search = this.filters.search

        const response = await api.get('/api/v1/trips', { params })
        this.trips = response.data.data

        if (response.data.meta) {
          this.pagination.page = response.data.meta.current_page
          this.pagination.rowsPerPage = response.data.meta.per_page
          this.pagination.rowsNumber = response.data.meta.total
          this.pagination.lastPage = response.data.meta.last_page
        }
      } catch (error) {
        console.error('Error fetching trips:', error)
      } finally {
        this.loading = false
      }
    },

    async fetchTrip(id) {
      this.loading = true
      this.currentTrip = null
      try {
        const response = await api.get(`/api/v1/trips/${id}`)
        this.currentTrip = response.data.data
      } catch (error) {
        console.error('Error fetching trip:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    async createTrip(data) {
      const response = await api.post('/api/v1/trips', data)
      return response.data
    },

    async joinTrip(id) {
      const response = await api.post(`/api/v1/trips/${id}/join`)
      if (this.currentTrip?.id === id) {
        await this.fetchTrip(id)
      }
      return response.data
    },

    async leaveTrip(id) {
      const response = await api.post(`/api/v1/trips/${id}/leave`)
      if (this.currentTrip?.id === id) {
        await this.fetchTrip(id)
      }
      return response.data
    },

    async fetchMyTrips() {
      this.loading = true
      try {
        const response = await api.get('/api/v1/trips/my')
        this.myTrips = response.data.data
      } catch (error) {
        console.error('Error fetching my trips:', error)
      } finally {
        this.loading = false
      }
    },

    setFilter(key, value) {
      this.filters[key] = value || ''
      this.fetchTrips(1)
    },

    clearFilters() {
      this.filters = { type: '', visibility: '', search: '' }
      this.fetchTrips(1)
    }
  }
})
