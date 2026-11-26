import { expect, test } from 'bun:test'
import { useScreenActions } from '../../resources/js/components/flashboard/useScreenActions'

function setup(confirm = true) {
  const calls: unknown[][] = []
  const confirmations: string[] = []
  const navigation = Object.fromEntries(['get', 'put', 'patch', 'delete', 'post'].map((method) => [
    method, (...args: unknown[]) => calls.push([method, ...args]),
  ])) as unknown as NonNullable<Parameters<typeof useScreenActions>[0]>
  const actions = useScreenActions(navigation, (message) => { confirmations.push(message); return confirm })
  return { ...actions, calls, confirmations }
}

test('parameterized and contextual confirmed actions open one dialog without navigation', () => {
  const { activeAction, runAction, calls, confirmations } = setup()
  runAction({ key: 'first', url: '/run', form_url: '/form' })
  runAction({ key: 'second', url: '/other', scope: 'record', requires_confirmation: true })
  expect(activeAction.value?.key).toBe('first')
  expect(calls).toHaveLength(0)
  expect(confirmations).toHaveLength(0)
  activeAction.value = null
  runAction({ key: 'second', url: '/other', scope: 'record', requires_confirmation: true })
  expect(activeAction.value?.key).toBe('second')
})

test('legacy confirmation and HTTP navigation preserve their existing behavior', () => {
  const { runAction, calls, confirmations } = setup(false)
  runAction({ key: 'missing' })
  runAction({ key: 'remove', url: '/remove', method: 'DELETE', requires_confirmation: true })
  expect(calls).toHaveLength(0)
  expect(confirmations).toHaveLength(1)
  runAction({ key: 'remove', url: '/remove', method: 'DELETE', requires_confirmation: true }, true)
  expect(calls[0]).toEqual(['delete', '/remove', { preserveScroll: true }])
  runAction({ key: 'view', url: '/view', method: 'GET' })
  expect(calls[1]).toEqual(['get', '/view', {}, { preserveScroll: true }])
  runAction({ key: 'default', url: '/default' })
  expect(calls[2]).toEqual(['post', '/default', {}, { preserveScroll: true }])
})
