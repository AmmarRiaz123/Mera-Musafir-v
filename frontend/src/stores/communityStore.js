import { defineStore, acceptHMRUpdate } from 'pinia'
import { api } from 'src/boot/axios'

export const useCommunityStore = defineStore('community', {
  state: () => ({
    posts: [],
    loading: false,
    loadingMore: false,
    page: 1,
    lastPage: 1,
    // Comments are fetched lazily, keyed by post id.
    comments: {},
  }),

  getters: {
    hasMore: (state) => state.page < state.lastPage,
  },

  actions: {
    /** Loads page 1, replacing whatever is there. */
    async fetchFeed(filters = {}) {
      this.loading = true
      this.page = 1
      try {
        const { data } = await api.get('/api/v1/community/posts', {
          params: { ...filters, page: 1 },
        })
        this.posts = data.data
        this.lastPage = data.meta?.last_page ?? 1
      } finally {
        this.loading = false
      }
    },

    /** Appends the next page — used by infinite scroll. */
    async fetchMore(filters = {}) {
      if (this.loadingMore || !this.hasMore) return
      this.loadingMore = true
      try {
        const next = this.page + 1
        const { data } = await api.get('/api/v1/community/posts', {
          params: { ...filters, page: next },
        })
        // Guard against duplicates if a post shifted between pages.
        const seen = new Set(this.posts.map((p) => p.id))
        this.posts.push(...data.data.filter((p) => !seen.has(p.id)))
        this.page = data.meta?.current_page ?? next
        this.lastPage = data.meta?.last_page ?? this.lastPage
      } finally {
        this.loadingMore = false
      }
    },

    async createPost(payload) {
      const { data } = await api.post('/api/v1/community/posts', payload)
      this.posts.unshift(data.data)
      return data.data
    },

    async deletePost(id) {
      await api.delete(`/api/v1/community/posts/${id}`)
      this.posts = this.posts.filter((p) => p.id !== id)
    },

    async toggleLike(post) {
      // Optimistic: the button should respond instantly, but the server's
      // count is authoritative once it answers.
      const wasLiked = post.is_liked
      post.is_liked = !wasLiked
      post.likes_count += wasLiked ? -1 : 1

      try {
        const { data } = await api.post(`/api/v1/community/posts/${post.id}/like`)
        post.is_liked = data.is_liked
        post.likes_count = data.likes_count
      } catch (err) {
        post.is_liked = wasLiked
        post.likes_count += wasLiked ? 1 : -1
        throw err
      }
    },

    async fetchPost(id) {
      const { data } = await api.get(`/api/v1/community/posts/${id}`)
      return data.data
    },

    async fetchComments(postId) {
      const { data } = await api.get(`/api/v1/community/posts/${postId}/comments`)
      this.comments[postId] = data.data
      return data.data
    },

    async addComment(post, payload) {
      // Accepts a plain string (text only) or { body, media_url, media_type }.
      const body = typeof payload === 'string' ? { body: payload } : payload
      const { data } = await api.post(`/api/v1/community/posts/${post.id}/comments`, body)
      if (!this.comments[post.id]) this.comments[post.id] = []
      this.comments[post.id].push(data.data)
      post.comments_count = data.comments_count
      return data.data
    },

    async deleteComment(post, commentId) {
      const { data } = await api.delete(`/api/v1/community/posts/${post.id}/comments/${commentId}`)
      this.comments[post.id] = (this.comments[post.id] || []).filter((c) => c.id !== commentId)
      post.comments_count = data.comments_count
    },
  },
})

// Keep the live store in sync when this file is edited during dev.
if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useCommunityStore, import.meta.hot))
}
