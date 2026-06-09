import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'

export const useDestinationStore = defineStore('destination', {
  state: () => ({
    destinations: [],
    currentDestination: null,
    pagination: {
      page: 1,
      rowsPerPage: 15,
      rowsNumber: 0,
      lastPage: 1
    },
    loading: false,
    filters: {
      province: '',
      type: '',
      search: ''
    }
  }),
  actions: {
    async fetchDestinations(page = 1) {
      this.loading = true
      try {
        const params = { page }
        if (this.filters.province) params.province = this.filters.province
        if (this.filters.type) params.type = this.filters.type
        if (this.filters.search) params.search = this.filters.search

        const response = await api.get('/api/v1/destinations', { params })
        this.destinations = response.data.data
        
        if (response.data.meta) {
          this.pagination.page = response.data.meta.current_page
          this.pagination.rowsPerPage = response.data.meta.per_page
          this.pagination.rowsNumber = response.data.meta.total
          this.pagination.lastPage = response.data.meta.last_page
        }
      } catch (error) {
        console.error('Error fetching destinations:', error)
      } finally {
        this.loading = false
      }
    },
    
    async fetchDestination(slug) {
      this.loading = true
      this.currentDestination = null
      try {
        const response = await api.get(`/api/v1/destinations/${slug}`)
        this.currentDestination = response.data.data
      } catch (error) {
        console.error('Error fetching destination:', error)
        throw error
      } finally {
        this.loading = false
      }
    },
    
    setFilter(key, value) {
      this.filters[key] = value || ''
      this.fetchDestinations(1)
    },
    
    clearFilters() {
      this.filters = {
        province: '',
        type: '',
        search: ''
      }
      this.fetchDestinations(1)
    }
  }
})
