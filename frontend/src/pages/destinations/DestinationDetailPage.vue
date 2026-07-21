<template>
  <q-page padding>
    <div v-if="destinationStore.loading" class="row justify-center q-py-xl">
      <q-spinner-dots color="primary" size="4em" />
    </div>

    <div v-else-if="!destination" class="text-center q-py-xl">
      <q-icon name="error_outline" size="64px" color="negative" />
      <div class="text-h5 q-mt-md">Destination not found</div>
      <q-btn color="primary" label="Back to Destinations" to="/destinations" class="q-mt-lg" />
    </div>

    <div v-else>
      <q-btn
        flat
        color="primary"
        icon="arrow_back"
        label="Back to Destinations"
        @click="$router.push('/destinations')"
        class="q-mb-md"
      />

      <q-img
        :src="destination.cover_image"
        v-if="destination.cover_image"
        ratio="2.4"
        class="rounded-borders shadow-2 q-mb-lg bg-grey-3"
      />

      <div class="row items-center justify-between q-mb-md">
        <div class="text-h4 text-weight-bold">{{ destination.name }}</div>
        <div class="q-gutter-sm">
          <q-badge color="primary" class="q-pa-sm text-subtitle2">{{ destination.province }}</q-badge>
          <q-badge color="primary" outline class="q-pa-sm text-subtitle2">{{ destination.region }}</q-badge>
          <q-badge color="secondary" class="q-pa-sm text-subtitle2 capitalize">{{ destination.type }}</q-badge>
        </div>
      </div>

      <div class="row items-center text-grey-8 q-mb-lg">
        <q-icon name="calendar_today" size="sm" class="q-mr-sm" />
        <span class="text-subtitle1">Best time to visit: <strong class="text-black">{{ destination.best_season }}</strong></span>
      </div>

      <div class="text-body1 text-justify q-mb-xl text-grey-9" style="line-height: 1.8;">
        {{ destination.description }}
      </div>

      <q-card v-if="destination.travel_tips" flat bordered class="bg-blue-grey-1 q-mb-xl">
        <q-card-section>
          <div class="row items-center q-mb-sm">
            <q-icon name="lightbulb" color="amber-8" size="sm" class="q-mr-sm" />
            <div class="text-h6">Travel Tips</div>
          </div>
          <div class="text-body2 text-grey-9">{{ destination.travel_tips }}</div>
        </q-card-section>
      </q-card>

      <div v-if="destination.coordinates && destination.coordinates.lat" class="row items-center text-grey-7 q-mb-xl bg-grey-2 q-pa-md rounded-borders">
        <q-icon name="place" size="sm" color="red" class="q-mr-sm" />
        <span class="text-subtitle2">
          <strong>Coordinates:</strong> Lat {{ destination.coordinates.lat }}, Lng {{ destination.coordinates.lng }}
        </span>
      </div>

      <!-- Packages section -->
      <div class="q-mb-xl">
        <div class="row items-center justify-between q-mb-md">
          <div>
            <div class="text-h6 text-weight-bold">Travel Packages Here</div>
            <div class="text-caption text-grey-6">Packages offered by agencies for {{ destination.name }}</div>
          </div>
          <q-btn flat color="deep-purple" label="All Packages" icon-right="arrow_forward" to="/packages" />
        </div>

        <!-- Loading -->
        <div v-if="pkgLoading" class="row q-col-gutter-md">
          <div v-for="n in 3" :key="n" class="col-12 col-sm-6 col-md-4">
            <q-card flat bordered>
              <q-skeleton height="160px" square />
              <q-card-section>
                <q-skeleton type="text" class="text-subtitle1" />
                <q-skeleton type="text" width="60%" />
                <q-skeleton type="text" width="40%" />
              </q-card-section>
            </q-card>
          </div>
        </div>

        <!-- Empty -->
        <div v-else-if="packages.length === 0" class="text-center q-py-lg bg-grey-1 rounded-borders">
          <q-icon name="card_travel" size="3em" color="grey-4" />
          <div class="text-body2 text-grey-5 q-mt-sm">No packages available for this destination yet</div>
        </div>

        <!-- Package cards -->
        <div v-else class="row q-col-gutter-md">
          <div v-for="pkg in packages" :key="pkg.id" class="col-12 col-sm-6 col-md-4">
            <q-card
              class="cursor-pointer card-hover"
              flat bordered
              @click="$router.push(`/packages/${pkg.slug}`)"
            >
              <q-img
                :src="pkg.cover_image || 'https://via.placeholder.com/600x400?text=Package'"
                ratio="1.7778"
                class="bg-grey-3"
              >
                <div class="absolute-top-right q-pa-sm">
                  <q-badge color="deep-purple" class="shadow-1 q-pa-xs text-caption capitalize">
                    {{ pkg.type?.replace('_', ' ') }}
                  </q-badge>
                </div>
                <div v-if="pkg.is_full" class="absolute-bottom bg-negative text-white text-center text-caption q-pa-xs">
                  FULLY BOOKED
                </div>
              </q-img>

              <q-card-section>
                <div class="text-subtitle1 text-weight-bold ellipsis">{{ pkg.title }}</div>
                <div class="row items-center text-caption text-grey-7 q-mt-xs q-gutter-xs">
                  <q-icon name="business" size="xs" />
                  <span>{{ pkg.agency?.business_name }}</span>
                  <q-icon v-if="pkg.agency?.is_verified" name="verified" color="primary" size="10px" />
                </div>
              </q-card-section>

              <q-card-section class="q-pt-none">
                <div class="row items-center justify-between q-mb-xs">
                  <div class="text-h6 text-weight-bold text-deep-purple">{{ pkg.formatted_price }}</div>
                  <div class="text-caption text-grey-6">per person</div>
                </div>
                <div class="row items-center justify-between text-caption text-grey-7">
                  <div class="row items-center">
                    <q-icon name="calendar_today" size="xs" class="q-mr-xs" />
                    {{ fmtDate(pkg.start_date) }}
                  </div>
                  <div>{{ pkg.duration_days }}D</div>
                  <div class="row items-center" :class="pkg.spots_left < 5 ? 'text-negative' : ''">
                    <q-icon name="event_seat" size="xs" class="q-mr-xs" />
                    {{ pkg.spots_left }} left
                  </div>
                </div>
              </q-card-section>

              <q-card-actions class="q-pt-none">
                <q-space />
                <q-btn flat color="deep-purple" label="View Package" dense :to="`/packages/${pkg.slug}`" />
              </q-card-actions>
            </q-card>
          </div>
        </div>
      </div>

      <!-- Community posts about this destination -->
      <div class="q-mt-xl">
        <div class="text-h6 text-weight-bold">From the community</div>
        <div class="text-caption text-grey-6 q-mb-md">
          Stories and tips travellers shared about {{ destination.name }}
        </div>

        <div v-if="postsLoading" class="row justify-center q-py-lg">
          <q-spinner-dots color="primary" size="28px" />
        </div>

        <div v-else-if="!posts.length" class="dest-empty">
          <q-icon name="forum" size="30px" />
          <div>No posts about {{ destination.name }} yet.</div>
          <q-btn flat no-caps dense color="primary" label="Share the first one" to="/community" />
        </div>

        <div v-else class="column q-gutter-md">
          <PostCard
            v-for="post in posts"
            :key="post.id"
            :post="post"
            :comments="communityStore.comments[post.id] || []"
            :show-comments="openComments.has(post.id)"
            @like="onLike"
            @toggle-comments="onToggleComments"
            @comment="onComment"
          />
        </div>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDestinationStore } from 'src/stores/destinationStore'
import { useCommunityStore } from 'src/stores/communityStore'
import { api } from 'src/boot/axios'
import PostCard from 'components/PostCard.vue'

const route = useRoute()
const router = useRouter() // eslint-disable-line no-unused-vars
const destinationStore = useDestinationStore()

const destination = computed(() => destinationStore.currentDestination)

const packages = ref([])
const pkgLoading = ref(false)

const communityStore = useCommunityStore()
const posts = ref([])
const postsLoading = ref(false)
const openComments = ref(new Set())

// This page shows only posts tagged with this destination.
const loadPosts = async (destinationId) => {
  postsLoading.value = true
  try {
    const r = await api.get('/api/v1/community/posts', {
      params: { destination_id: destinationId, per_page: 5 },
    })
    posts.value = r.data.data || []
  } catch {
    posts.value = []
  } finally {
    postsLoading.value = false
  }
}

const onLike = (post) => communityStore.toggleLike(post).catch(() => {})

const onToggleComments = async (post) => {
  const set = new Set(openComments.value)
  set.has(post.id) ? set.delete(post.id) : set.add(post.id)
  openComments.value = set
  if (!communityStore.comments[post.id]) await communityStore.fetchComments(post.id)
}

const onComment = (post, body) => communityStore.addComment(post, body).catch(() => {})

onMounted(async () => {
  const slug = route.params.slug
  if (slug) {
    try {
      await destinationStore.fetchDestination(slug)
      if (destination.value?.id) {
        loadPackages(destination.value.id)
        loadPosts(destination.value.id)
      }
    } catch (e) {
      console.error(e)
    }
  }
})

const loadPackages = async (destinationId) => {
  pkgLoading.value = true
  try {
    const r = await api.get('/api/v1/packages', { params: { destination_id: destinationId, per_page: 6 } })
    packages.value = r.data.data
  } catch {
    packages.value = []
  } finally {
    pkgLoading.value = false
  }
}

const fmtDate = (d) => {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PK', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<style scoped>
.dest-empty {
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  padding: 32px 16px; border: 1px solid #ece6f0; border-radius: 13px;
  background: #fff; color: #b0a3b8; font-size: 13px; text-align: center;
}
.capitalize { text-transform: capitalize; }
.card-hover { transition: transform 0.2s, box-shadow 0.2s; }
.card-hover:hover { transform: translateY(-4px); box-shadow: 0 4px 15px rgba(0,0,0,.1); }
</style>
