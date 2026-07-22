import { defineStore, acceptHMRUpdate } from 'pinia'

const MUTE_KEY = 'feed.muted'

/**
 * One player for the whole feed, Instagram-style.
 *
 * Only the post currently in view plays, and switching posts switches the
 * track — so scrolling past a post stops its music automatically. Mute is a
 * single global preference, not a per-post toggle, and it persists.
 *
 * Autoplay caveat: browsers block sound until the user has interacted with the
 * page. We keep the *intent* (unmuted) and arm a one-time gesture listener, so
 * sound starts on the first click/scroll instead of silently failing.
 */
export const useFeedAudioStore = defineStore('feedAudio', {
  state: () => ({
    activeId: null,
    playing: false,
    muted: localStorage.getItem(MUTE_KEY) === 'true',
    // True when the browser refused autoplay and we're waiting for a gesture.
    blocked: false,
    _el: null,
    _armed: false,
  }),

  actions: {
    _audio() {
      if (!this._el) {
        const el = new Audio()
        el.loop = true
        el.preload = 'none'
        el.addEventListener('playing', () => {
          this.playing = true
          this.blocked = false
        })
        el.addEventListener('pause', () => (this.playing = false))
        this._el = el
      }
      this._el.muted = this.muted
      return this._el
    },

    /**
     * Called when a post scrolls into view. Switching posts replaces the track,
     * which is what makes the previous post's music stop on its own.
     */
    activate(postId, audio) {
      if (!audio?.audio_url) {
        // The post in view has no music — silence whatever was playing.
        this.stop()
        return
      }

      if (this.activeId === postId) return

      this.activeId = postId
      const el = this._audio()
      el.src = audio.audio_url
      el.currentTime = 0
      this._tryPlay()
    },

    /** Called when a post leaves view; ignored if another post already took over. */
    deactivate(postId) {
      if (this.activeId === postId) this.stop()
    },

    stop() {
      this.activeId = null
      this.blocked = false
      if (this._el) {
        this._el.pause()
        this._el.removeAttribute('src')
      }
      this.playing = false
    },

    toggleMute() {
      this.muted = !this.muted
      localStorage.setItem(MUTE_KEY, String(this.muted))

      if (!this._el) return
      this._el.muted = this.muted

      // Unmuting counts as a gesture, so this is a good moment to (re)start.
      if (!this.muted && this.activeId) this._tryPlay()
    },

    _tryPlay() {
      const el = this._audio()
      el.play().catch(() => {
        // Autoplay refused — wait for the first real interaction.
        this.blocked = true
        this._armGesture()
      })
    },

    _armGesture() {
      if (this._armed) return
      this._armed = true

      const go = () => {
        this._armed = false
        window.removeEventListener('pointerdown', go)
        window.removeEventListener('keydown', go)
        if (this.activeId) this._audio().play().catch(() => {})
      }

      window.addEventListener('pointerdown', go, { once: true })
      window.addEventListener('keydown', go, { once: true })
    },
  },
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useFeedAudioStore, import.meta.hot))
}
