// Shared message length rules. Mirrors the backend validation
// (`max:1000` on ConversationController, ChatController and MessageRequestController)
// so the UI can stop the user before a request is ever made.
export const MESSAGE_MAX = 1000

/** Hard-truncate any text to the limit. */
export function clampMessage(text) {
  const value = text ?? ''
  return value.length > MESSAGE_MAX ? value.slice(0, MESSAGE_MAX) : value
}

/**
 * How many characters a paste would lose.
 * `selectionLength` is the text about to be replaced by the paste.
 */
export function overflowFromPaste(pasted, currentLength, selectionLength = 0) {
  const room = MESSAGE_MAX - (currentLength - selectionLength)
  return Math.max(0, (pasted?.length ?? 0) - room)
}

/** Consistent, friendly "we trimmed your paste" notification. */
export function notifyTrimmed($q, removed) {
  $q.notify({
    icon: 'content_cut',
    color: 'amber-8',
    textColor: 'white',
    message: 'Message trimmed to fit',
    caption: `${removed.toLocaleString()} character${removed === 1 ? '' : 's'} over the ${MESSAGE_MAX.toLocaleString()}-character limit ${removed === 1 ? 'was' : 'were'} removed.`,
    position: 'top',
    timeout: 4000,
  })
}
