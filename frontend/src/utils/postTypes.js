/**
 * Single source of truth for community post categories.
 * Mirrors the `type` enum on community_posts.
 */
export const POST_TYPES = [
  { value: 'companion',    label: 'Find companions', short: 'Companions', icon: 'group_add',    color: 'teal' },
  { value: 'story',        label: 'Story',           short: 'Stories',    icon: 'auto_stories', color: 'purple' },
  { value: 'tip',          label: 'Travel tip',      short: 'Tips',       icon: 'lightbulb',    color: 'blue' },
  { value: 'question',     label: 'Question',        short: 'Questions',  icon: 'help',         color: 'indigo' },
  { value: 'review',       label: 'Review',          short: 'Reviews',    icon: 'star',         color: 'amber' },
  { value: 'alert',        label: 'Road / weather',  short: 'Alerts',     icon: 'warning',      color: 'red' },
  { value: 'gear',         label: 'Gear & packing',  short: 'Gear',       icon: 'backpack',     color: 'green' },
  { value: 'budget',       label: 'Budget',          short: 'Budget',     icon: 'payments',     color: 'cyan' },
  { value: 'safety',       label: 'Safety',          short: 'Safety',     icon: 'shield',       color: 'pink' },
  { value: 'announcement', label: 'Announcement',    short: 'Agency',     icon: 'campaign',     color: 'deep' },
]

const BY_VALUE = Object.fromEntries(POST_TYPES.map((t) => [t.value, t]))

export const postType = (value) => BY_VALUE[value] ?? BY_VALUE.story

/** Types a traveller can post (announcements are for agencies). */
export const travellerTypes = POST_TYPES.filter((t) => t.value !== 'announcement')
