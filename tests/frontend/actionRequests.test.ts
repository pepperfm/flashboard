import { expect, test } from 'bun:test'
import { actionEndpoint, actionOutcome, actionState } from '../../resources/js/components/flashboard/actionRequests'

test('action requests only use same-origin endpoints', () => {
  expect(actionEndpoint('/admin/actions/grant', 'https://panel.test')).toBe('https://panel.test/admin/actions/grant')
  expect(() => actionEndpoint('https://other.test/action', 'https://panel.test')).toThrow()
})

test('validation failure retains field errors, including dotted keys', () => {
  expect(actionOutcome(422, { errors: { 'payload.days': ['Required.'] } })).toEqual({
    successful: false, errors: { days: 'Required.' }, message: 'Check the submitted values.',
  })
})

test('domain failure never becomes transport success', () => {
  expect(actionOutcome(200, { successful: false }).successful).toBe(false)
  expect(actionOutcome(409, { successful: false, message: 'Denied.' }).message).toBe('Denied.')
  expect(actionOutcome(500, { successful: true, message: 'private error' }).message).toBe('Action could not be completed.')
})

test('explicit success retains message and redirect', () => {
  expect(actionOutcome(200, { successful: true, message: 'Saved', redirect_to: '/admin' })).toEqual({
    successful: true, message: 'Saved', redirectTo: '/admin',
  })
})

test('empty defaults always produce a serializable object state', () => {
  const state = actionState([])
  state.units = 3
  state.reason = 'Test'
  expect(JSON.stringify({ payload: state })).toBe('{"payload":{"units":3,"reason":"Test"}}')
  expect(actionState({ enabled: false, units: 0, reason: null })).toEqual({ enabled: false, units: 0, reason: null })
})
