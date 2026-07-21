/**
 * Visual tone for backend message codes.
 *
 * Mirrors backend/app/Support/Messages.php — that file owns the wording, this
 * one owns how it looks. A blocked action should read as a friendly nudge, so
 * only genuine failures fall through to the red default.
 */
const TONES = {
  // Trips: joining
  women_only:        { icon: 'favorite',      color: 'pink-8' },
  already_joined:    { icon: 'luggage',       color: 'teal-7' },
  trip_full:         { icon: 'event_busy',    color: 'orange-8' },
  not_in_trip:       { icon: 'help_outline',  color: 'blue-grey-7' },
  host_cannot_leave: { icon: 'flag',          color: 'indigo-7' },
  join_blocked:      { icon: 'lock_person',   color: 'blue-grey-7' },

  // Trips: inviting
  invite_pending:        { icon: 'hourglass_top', color: 'deep-purple-6' },
  invite_already_member: { icon: 'groups',        color: 'teal-7' },
  not_a_member:          { icon: 'lock_person',   color: 'blue-grey-7' },
  invite_not_found:      { icon: 'search_off',    color: 'blue-grey-7' },
  invite_answered:       { icon: 'task_alt',      color: 'blue-grey-7' },
  invite_own:            { icon: 'schedule_send', color: 'deep-purple-6' },
}

const FALLBACK = { icon: 'error', color: 'negative' }

/**
 * Show an API error with the tone that matches its code.
 * Falls back to a plain red notification for genuine/unknown failures.
 */
export function notifyError($q, err, fallback = 'Something went wrong') {
  const data = err?.response?.data
  const tone = TONES[data?.code] ?? FALLBACK

  $q.notify({
    icon: tone.icon,
    color: tone.color,
    textColor: 'white',
    message: data?.message || fallback,
    position: 'top',
    timeout: 5000,
  })
}

export function notifySuccess($q, message, icon = 'check_circle') {
  $q.notify({ icon, color: 'positive', textColor: 'white', message, position: 'top', timeout: 3000 })
}
