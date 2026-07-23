import { boot } from 'quasar/wrappers'
import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost:8000',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// A suspension lands mid-session as a 403 from the server middleware. Wipe the
// session and bounce to login so the app doesn't keep pretending they're in.
let handlingSuspension = false
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 403 && error.response?.data?.code === 'account_suspended') {
      if (!handlingSuspension) {
        handlingSuspension = true
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        // A hard reload is deliberate: clearing storage alone leaves every
        // Pinia store's in-memory state (including the auth token) intact, so
        // the guestOnly guard would bounce us straight back off /login. The
        // reload re-inits the app with no token — genuinely logged out — and
        // the query flag lets the login page say why.
        window.location.hash = '#/login?suspended=1'
        window.location.reload()
      }
    }
    return Promise.reject(error)
  },
)

export default boot(({ app }) => {
  app.config.globalProperties.$axios = axios
  app.config.globalProperties.$api = api
})

export { api }