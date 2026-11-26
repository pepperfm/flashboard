import { expect, test } from 'bun:test'
import { parseJsonEntry } from '../../resources/js/components/flashboard/detail/jsonEntryValue'

test('structured values retain text and never enable copying invalid or over-limit data', () => {
  expect(parseJsonEntry({ status: 'ok', text: '{"text":"<script>"}' })).toEqual({ status: 'ok', text: '{"text":"<script>"}' })
  expect(parseJsonEntry({ status: 'truncated', text: null })).toEqual({ status: 'truncated', text: null })
  expect(parseJsonEntry({ status: 'ok', text: 'x'.repeat(32769) })).toEqual({ status: 'invalid', text: null })
  expect(parseJsonEntry({ status: 'invalid', text: 'not public' })).toEqual({ status: 'invalid', text: null })
  expect(parseJsonEntry('raw')).toEqual({ status: 'invalid', text: null })
})
