const routes = [
  {
    path: '/',
    component: () => import('layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', component: () => import('pages/IndexPage.vue') },
      { path: 'destinations', component: () => import('pages/destinations/DestinationsPage.vue') },
      { path: 'destinations/:slug', component: () => import('pages/destinations/DestinationDetailPage.vue') },
      { path: 'profile', component: () => import('pages/profile/ProfilePage.vue') },
      { path: 'profile/:id', component: () => import('pages/profile/ProfilePage.vue') },
      { path: 'trips', component: () => import('pages/trips/TripsPage.vue') },
      { path: 'trips/create', component: () => import('pages/trips/CreateTripPage.vue') },
      { path: 'trips/:id', component: () => import('pages/trips/TripDetailPage.vue') },
      { path: 'my-trips', component: () => import('pages/trips/MyTripsPage.vue') },
    ],
  },
  {
    path: '/login',
    component: () => import('pages/auth/LoginPage.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/register',
    component: () => import('pages/auth/RegisterPage.vue'),
    meta: { guestOnly: true },
  },

  // Always leave this as last one,
  // but you can also remove it
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/ErrorNotFound.vue'),
  },
]

export default routes
