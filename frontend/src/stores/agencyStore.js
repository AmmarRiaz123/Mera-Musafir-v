import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'

export const useAgencyStore = defineStore('agency', {
  state: () => ({
    agencies: [],
    currentAgency: null,
    myAgency: null,
    packages: [],
    currentPackage: null,
    myPackages: [],
    bookings: [],
    analytics: null,
    loading: false,
    packagesLoading: false,
    pagination: { page: 1, rowsPerPage: 12, rowsNumber: 0, lastPage: 1 },
    packagesPagination: { page: 1, rowsPerPage: 12, rowsNumber: 0, lastPage: 1 },
  }),

  actions: {
    // ─── Agencies ───────────────────────────────────────────────────────────

    async fetchAgencies(page = 1, search = '') {
      this.loading = true
      try {
        const params = { page }
        if (search) params.search = search
        const r = await api.get('/api/v1/agencies', { params })
        this.agencies = r.data.data
        if (r.data.meta) {
          this.pagination.page        = r.data.meta.current_page
          this.pagination.rowsNumber  = r.data.meta.total
          this.pagination.lastPage    = r.data.meta.last_page
        }
      } finally {
        this.loading = false
      }
    },

    async fetchAgency(slug) {
      this.loading = true
      this.currentAgency = null
      try {
        const r = await api.get(`/api/v1/agencies/${slug}`)
        this.currentAgency = r.data.data
        return r.data.data
      } finally {
        this.loading = false
      }
    },

    async fetchMyAgency() {
      try {
        const r = await api.get('/api/v1/agencies/my')
        this.myAgency = r.data.data
        return r.data.data
      } catch (err) {
        if (err.response?.status === 404) this.myAgency = null
        return null
      }
    },

    async registerAgency(data) {
      const r = await api.post('/api/v1/agencies/register', data)
      this.myAgency = r.data.data
      return r.data
    },

    async updateAgency(slug, data) {
      const r = await api.put(`/api/v1/agencies/${slug}`, data)
      this.myAgency = r.data.data
      if (this.currentAgency?.slug === slug) this.currentAgency = r.data.data
      return r.data
    },

    async followAgency(slug) {
      const r = await api.post(`/api/v1/agencies/${slug}/follow`)
      if (this.currentAgency?.slug === slug) {
        this.currentAgency.is_following   = r.data.is_following
        this.currentAgency.follower_count = r.data.follower_count
      }
      if (this.currentPackage?.agency?.slug === slug) {
        this.currentPackage.agency.is_following   = r.data.is_following
        this.currentPackage.agency.follower_count = r.data.follower_count
      }
      return r.data
    },

    async fetchAnalytics(slug) {
      const r = await api.get(`/api/v1/agencies/${slug}/analytics`)
      this.analytics = r.data
      return r.data
    },

    async fetchAgencyBookings(slug, params = {}) {
      this.loading = true
      try {
        const r = await api.get(`/api/v1/agencies/${slug}/bookings`, { params })
        this.bookings = r.data.data
        return r.data
      } finally {
        this.loading = false
      }
    },

    // ─── Packages ───────────────────────────────────────────────────────────

    async fetchPackages(page = 1, filters = {}) {
      this.packagesLoading = true
      try {
        const r = await api.get('/api/v1/packages', { params: { page, ...filters } })
        this.packages = r.data.data
        if (r.data.meta) {
          this.packagesPagination.page       = r.data.meta.current_page
          this.packagesPagination.rowsNumber = r.data.meta.total
          this.packagesPagination.lastPage   = r.data.meta.last_page
        }
      } finally {
        this.packagesLoading = false
      }
    },

    async fetchPackage(slug) {
      this.loading = true
      this.currentPackage = null
      try {
        const r = await api.get(`/api/v1/packages/${slug}`)
        this.currentPackage = r.data.data
        return r.data.data
      } finally {
        this.loading = false
      }
    },

    async fetchMyPackages(page = 1) {
      this.loading = true
      try {
        const r = await api.get('/api/v1/packages/my', { params: { page } })
        this.myPackages = r.data.data
        return r.data
      } finally {
        this.loading = false
      }
    },

    async createPackage(data) {
      const r = await api.post('/api/v1/packages', data)
      return r.data
    },

    async updatePackage(slug, data) {
      const r = await api.put(`/api/v1/packages/${slug}`, data)
      const updated = r.data.data
      const idx = this.myPackages.findIndex((p) => p.slug === slug)
      if (idx !== -1) this.myPackages[idx] = updated
      if (this.currentPackage?.slug === slug) this.currentPackage = updated
      return r.data
    },

    async deletePackage(slug) {
      await api.delete(`/api/v1/packages/${slug}`)
      this.myPackages = this.myPackages.filter((p) => p.slug !== slug)
    },

    async bookPackage(slug, data) {
      const r = await api.post(`/api/v1/packages/${slug}/book`, data)
      if (this.currentPackage?.slug === slug) {
        this.currentPackage.booked_count += data.travelers_count
        this.currentPackage.spots_left   -= data.travelers_count
      }
      return r.data
    },

    async fetchPackageBookings(packageSlug, params = {}) {
      const r = await api.get(`/api/v1/packages/${packageSlug}/bookings`, { params })
      return r.data.data
    },

    async confirmBooking(packageSlug, bookingId) {
      const r = await api.post(`/api/v1/packages/${packageSlug}/bookings/${bookingId}/confirm`)
      this._updateBooking(r.data.data)
      return r.data
    },

    async cancelBooking(packageSlug, bookingId) {
      const r = await api.post(`/api/v1/packages/${packageSlug}/bookings/${bookingId}/cancel`)
      this._updateBooking(r.data.data)
      return r.data
    },

    _updateBooking(updated) {
      const idx = this.bookings.findIndex((b) => b.id === updated.id)
      if (idx !== -1) this.bookings[idx] = updated
    },
  },
})
