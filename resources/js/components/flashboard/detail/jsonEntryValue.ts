export type JsonEntryValue = { status: 'ok' | 'invalid' | 'truncated', text: string | null }

export function parseJsonEntry(value: unknown): JsonEntryValue {
  if (typeof value !== 'object' || value === null || !('status' in value) || !('text' in value)) {
    return { status: 'invalid', text: null }
  }
  if (value.status !== 'ok' && value.status !== 'truncated') {
    return { status: 'invalid', text: null }
  }
  if (typeof value.text !== 'string' || value.text.length > 32768) {
    return { status: value.status === 'truncated' ? 'truncated' : 'invalid', text: null }
  }
  return { status: value.status, text: value.text }
}
