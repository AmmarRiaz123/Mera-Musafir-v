import { defineStore, acceptHMRUpdate } from 'pinia'
import { api } from 'src/boot/axios'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

function makeEcho() {
  const token = localStorage.getItem('token')
  if (!token) return null

  window.Pusher = Pusher

  if (window.Echo) window.Echo.disconnect()

  window.Echo = new Echo({
    broadcaster:       'reverb',
    key:               import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:            import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort:            parseInt(import.meta.env.VITE_REVERB_PORT) || 8080,
    wssPort:           parseInt(import.meta.env.VITE_REVERB_PORT) || 8080,
    forceTLS:          false,
    enabledTransports: ['ws', 'wss'],
    authEndpoint:      'http://localhost:8000/broadcasting/auth',
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept:        'application/json',
      },
    },
  })

  return window.Echo
}

export const usePlanningStore = defineStore('planning', {
  state: () => ({
    itinerary:  [],
    expenses:   [],
    settlement: [],
    checklist:  [],
    loading:    false,
  }),

  actions: {
    // ── Itinerary ──────────────────────────────────────
    async fetchItinerary(tripId) {
      this.loading = true
      try {
        const r = await api.get(`/api/v1/trips/${tripId}/itinerary`)
        this.itinerary = r.data.itinerary
      } finally {
        this.loading = false
      }
    },

    async addDay(tripId, date) {
      const r = await api.post(`/api/v1/trips/${tripId}/itinerary/days`, { date })
      this.itinerary = r.data.itinerary
    },

    async addItineraryItem(tripId, dayId, data) {
      const r = await api.post(`/api/v1/trips/${tripId}/itinerary/days/${dayId}/items`, data)
      this.itinerary = r.data.itinerary
    },

    async updateItineraryItem(tripId, itemId, data) {
      const r = await api.put(`/api/v1/trips/${tripId}/itinerary/items/${itemId}`, data)
      this.itinerary = r.data.itinerary
    },

    async deleteItineraryItem(tripId, itemId) {
      const r = await api.delete(`/api/v1/trips/${tripId}/itinerary/items/${itemId}`)
      this.itinerary = r.data.itinerary
    },

    async deleteDay(tripId, dayId) {
      const r = await api.delete(`/api/v1/trips/${tripId}/itinerary/days/${dayId}`)
      this.itinerary = r.data.itinerary
    },

    // ── Expenses ───────────────────────────────────────
    async fetchExpenses(tripId) {
      this.loading = true
      try {
        const r = await api.get(`/api/v1/trips/${tripId}/expenses`)
        this.expenses   = r.data.expenses
        this.settlement = r.data.settlement
      } finally {
        this.loading = false
      }
    },

    async addExpense(tripId, data) {
      const r = await api.post(`/api/v1/trips/${tripId}/expenses`, data)
      this.expenses   = r.data.expenses
      this.settlement = r.data.settlement
    },

    async settleShare(tripId, shareId) {
      const r = await api.post(`/api/v1/trips/${tripId}/expenses/shares/${shareId}/settle`)
      this.expenses   = r.data.expenses
      this.settlement = r.data.settlement
    },

    // ── Checklist ──────────────────────────────────────
    async fetchChecklist(tripId) {
      this.loading = true
      try {
        const r = await api.get(`/api/v1/trips/${tripId}/checklist`)
        this.checklist = r.data.items
      } finally {
        this.loading = false
      }
    },

    async addChecklistItem(tripId, data) {
      const r = await api.post(`/api/v1/trips/${tripId}/checklist`, data)
      this.checklist = r.data.items
    },

    async toggleChecklistItem(tripId, itemId) {
      const r = await api.post(`/api/v1/trips/${tripId}/checklist/${itemId}/toggle`)
      this.checklist = r.data.items
    },

    async deleteChecklistItem(tripId, itemId) {
      const r = await api.delete(`/api/v1/trips/${tripId}/checklist/${itemId}`)
      this.checklist = r.data.items
    },

    async updateChecklistItem(tripId, itemId, data) {
      const r = await api.put(`/api/v1/trips/${tripId}/checklist/${itemId}`, data)
      this.checklist = r.data.items
    },

    // ── Real-time ──────────────────────────────────────
    subscribeToPlanning(tripId) {
      const echo = makeEcho()
      if (!echo) return

      echo
        .private(`trip.${tripId}`)
        .listen('.itinerary.updated', (e) => {
          this.itinerary = e.itinerary
        })
        .listen('.expense.updated', (e) => {
          this.expenses   = e.expenses
          this.settlement = e.settlement
        })
        .listen('.checklist.updated', (e) => {
          this.checklist = e.items
        })
    },

    unsubscribeFromPlanning(tripId) {
      if (!window.Echo) return
      window.Echo.leave(`trip.${tripId}`)
    },
  },
})

// Keep the live store in sync when this file is edited during dev.
// Without this, Pinia keeps the already-instantiated store (old actions)
// until a full page reload.
if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(usePlanningStore, import.meta.hot))
}
