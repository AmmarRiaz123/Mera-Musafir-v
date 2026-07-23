import { defineRouter } from '#q-app/wrappers'
import {
  createRouter,
  createMemoryHistory,
  createWebHistory,
  createWebHashHistory,
} from 'vue-router'
import routes from './routes'
import { useAuthStore } from 'src/stores/authStore'

/*
 * If not building with SSR mode, you can
 * directly export the Router instantiation;
 *
 * The function below can be async too; either use
 * async/await or return a Promise which resolves
 * with the Router instance.
 */

export default defineRouter(({ store /*, ssrContext */ }) => {
  const createHistory = process.env.SERVER
    ? createMemoryHistory
    : process.env.VUE_ROUTER_MODE === 'history'
      ? createWebHistory
      : createWebHashHistory

  const Router = createRouter({
    scrollBehavior: () => ({ left: 0, top: 0 }),
    routes,

    // Leave this as is and make changes in quasar.conf.js instead!
    // quasar.conf.js -> build -> vueRouterMode
    // quasar.conf.js -> build -> publicPath
    history: createHistory(process.env.VUE_ROUTER_BASE),
  })

  // Where a logged-in user belongs when sent "home". An agency's home is its
  // dashboard, never the traveller feed — so this is the one place that decides
  // it, and every redirect below routes through it.
  const homeFor = (user) => {
    if (user?.type === 'agency' && user.agency_slug) {
      return `/agencies/${user.agency_slug}/dashboard?section=overview`
    }
    return '/'
  }

  Router.beforeEach((to, from, next) => {
    const authStore = useAuthStore(store)
    const user = authStore.user

    if (to.meta.requiresAuth && !authStore.isLoggedIn) {
      next('/login')
    } else if (to.meta.guestOnly && authStore.isLoggedIn) {
      next(homeFor(user))
    } else if (to.meta.requiresAgencyOwner && user?.type !== 'agency') {
      next(homeFor(user))
    } else if (to.meta.requiresAdmin && !user?.is_admin) {
      next(homeFor(user))
    } else if (to.meta.travelerOnly && user?.type === 'agency' && user.agency_slug) {
      // The traveller home, My Trips, bookings and trip-creation are not an
      // agency's world — send them to their own dashboard instead. The slug
      // check avoids a redirect loop for a stale session that predates it
      // (homeFor would fall back to '/', which is itself traveller-only); such
      // a session picks up the slug on its next login.
      next(homeFor(user))
    } else {
      next()
    }
  })

  return Router
})
