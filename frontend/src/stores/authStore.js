import { defineStore, acceptHMRUpdate } from 'pinia'
import { api } from 'src/boot/axios'
import { teardownEcho } from 'src/utils/echo'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user')) || null,
    token: localStorage.getItem('token') || null
  }),
  getters: {
    isLoggedIn: (state) => !!state.token,
    isAdmin: (state) => !!state.user?.is_admin
  },
  actions: {
    async register(formData) {
      const response = await api.post('/api/v1/register', formData)
      this.setAuthData(response.data.data)
      return response
    },
    async login(formData) {
      const response = await api.post('/api/v1/login', formData)
      this.setAuthData(response.data.data)
      return response
    },
    async logout() {
      try {
        if (this.token) {
          await api.post('/api/v1/logout')
        }
      } catch (error) {
        console.error('Logout error', error)
      } finally {
        this.clearAuthData()
      }
    },
    async updateProfile(formData) {
      const response = await api.put(`/api/v1/users/${this.user.id}`, formData)
      this.user = response.data.data
      localStorage.setItem('user', JSON.stringify(this.user))
    },
    setAuthData(data) {
      this.user = data.user
      this.token = data.token
      localStorage.setItem('user', JSON.stringify(data.user))
      localStorage.setItem('token', data.token)
    },
    clearAuthData() {
      teardownEcho()
      this.user = null
      this.token = null
      localStorage.removeItem('user')
      localStorage.removeItem('token')
    }
  }
})

// Keep the live store in sync when this file is edited during dev.
// Without this, Pinia keeps the already-instantiated store (old actions)
// until a full page reload.
if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useAuthStore, import.meta.hot))
}
