const routes = [
  {
    path: '/',
    component: () => import('layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', component: () => import('pages/HomePage.vue'), meta: { travelerOnly: true } },
      { path: 'destinations', component: () => import('pages/destinations/DestinationsPage.vue') },
      { path: 'destinations/:slug', component: () => import('pages/destinations/DestinationDetailPage.vue') },
      { path: 'profile', component: () => import('pages/profile/ProfilePage.vue') },
      { path: 'profile/:id', component: () => import('pages/profile/ProfilePage.vue') },
      { path: 'privacy', component: () => import('pages/profile/PrivacySettingsPage.vue') },
      { path: 'my-bookings', component: () => import('pages/bookings/MyBookingsPage.vue'), meta: { travelerOnly: true } },
      { path: 'checkout', component: () => import('pages/payments/CheckoutPage.vue') },
      { path: 'transactions', component: () => import('pages/payments/TransactionsPage.vue') },
      { path: 'subscription', component: () => import('pages/agencies/SubscriptionPage.vue') },
      { path: 'trips', component: () => import('pages/trips/TripsPage.vue') },
      { path: 'trips/create', component: () => import('pages/trips/CreateTripPage.vue'), meta: { travelerOnly: true } },
      { path: 'trips/:id', component: () => import('pages/trips/TripDetailPage.vue') },
      { path: 'trips/:id/chat', component: () => import('pages/trips/ChatPage.vue') },
      { path: 'trips/:id/itinerary', component: () => import('pages/trips/ItineraryPage.vue') },
      { path: 'trips/:id/expenses', component: () => import('pages/trips/ExpensesPage.vue') },
      { path: 'trips/:id/checklist', component: () => import('pages/trips/ChecklistPage.vue') },
      { path: 'my-trips', component: () => import('pages/trips/MyTripsPage.vue'), meta: { travelerOnly: true } },

      // Agencies — register/my before :slug
      { path: 'agencies', component: () => import('pages/agencies/AgenciesPage.vue') },
      { path: 'agencies/register', component: () => import('pages/agencies/AgencyRegisterPage.vue') },
      { path: 'agencies/:slug', component: () => import('pages/agencies/AgencyProfilePage.vue') },
      { path: 'agencies/:slug/dashboard', component: () => import('pages/agencies/AgencyDashboardPage.vue'), meta: { requiresAgencyOwner: true } },

      // Packages — create before :slug
      { path: 'packages', component: () => import('pages/packages/PackagesPage.vue') },
      { path: 'packages/create', component: () => import('pages/agencies/CreatePackagePage.vue') },
      { path: 'packages/:slug', component: () => import('pages/packages/PackageDetailPage.vue') },

      // Social
      { path: 'people', component: () => import('pages/social/PeoplePage.vue') },
      { path: 'community', component: () => import('pages/community/CommunityPage.vue') },
      { path: 'messages', component: () => import('pages/social/MessagesPage.vue') },
      { path: 'messages/:id', component: () => import('pages/social/DMPage.vue') },
    ],
  },
  {
    path: '/admin',
    component: () => import('layouts/AdminLayout.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
    children: [
      { path: '', component: () => import('pages/admin/AdminDashboardPage.vue') },
      { path: 'users', component: () => import('pages/admin/AdminUsersPage.vue') },
      { path: 'agencies', component: () => import('pages/admin/AdminAgenciesPage.vue') },
      { path: 'reports', component: () => import('pages/admin/AdminReportsPage.vue') },
      { path: 'destinations', component: () => import('pages/admin/AdminDestinationsPage.vue') },
      { path: 'broadcast', component: () => import('pages/admin/AdminBroadcastPage.vue') },
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
