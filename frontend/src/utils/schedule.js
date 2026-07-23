import { api } from 'src/boot/axios'

// One in-flight fetch of the viewer's commitments, cached for the session so a
// booking dialog and a trip page don't each hit the endpoint. Invalidated after
// a booking or a join changes the schedule.
let cache = null
let inflight = null

export async function loadCommitments(force = false) {
  if (force) {
    cache = null
    inflight = null
  }
  if (cache) return cache
  if (inflight) return inflight

  inflight = api
    .get('/api/v1/me/commitments')
    .then(({ data }) => {
      cache = data.data || []
      return cache
    })
    .catch(() => {
      // A schedule we can't load just means no warning — never a blocked flow.
      cache = []
      return cache
    })
    .finally(() => {
      inflight = null
    })

  return inflight
}

export function invalidateCommitments() {
  cache = null
  inflight = null
}

// Two date ranges overlap when each starts on or before the other ends. Dates
// are 'YYYY-MM-DD' strings, which compare correctly lexicographically.
export function rangesOverlap(startA, endA, startB, endB) {
  if (!startA || !endA || !startB || !endB) return false
  return startA <= endB && startB <= endA
}

// The commitments that clash with a given range — what the warning lists.
export function conflictsWith(commitments, start, end) {
  return (commitments || []).filter((c) => rangesOverlap(start, end, c.start_date, c.end_date))
}

// "3–5 Aug" / "31 Jul – 3 Aug" — compact, and drops the repeated month/year.
export function formatRange(start, end) {
  if (!start) return ''
  const s = new Date(start + 'T00:00:00')
  const e = end ? new Date(end + 'T00:00:00') : s
  const d = (dt, withMonth) =>
    dt.toLocaleDateString('en-PK', { day: 'numeric', ...(withMonth ? { month: 'short' } : {}) })

  if (s.getTime() === e.getTime()) return s.toLocaleDateString('en-PK', { day: 'numeric', month: 'short' })
  const sameMonth = s.getMonth() === e.getMonth() && s.getFullYear() === e.getFullYear()
  return sameMonth ? `${d(s, false)}–${d(e, true)}` : `${d(s, true)} – ${d(e, true)}`
}
