import { afterEach, expect, test } from 'bun:test'
import { fetchRelationOptions } from '../../resources/js/components/flashboard/relations/relationRequests'

const environment = globalThis as typeof globalThis & { window?: Window }
const originalWindow = environment.window

afterEach(() => {
  if (originalWindow === undefined) {
    delete environment.window
  } else {
    environment.window = originalWindow
  }
})

function installFetchMock(handler: (url: URL) => Promise<Response>) {
  environment.window = {
    location: { origin: 'https://flashboard.test' },
    fetch: async (input: RequestInfo | URL) => handler(new URL(String(input))),
  } as unknown as Window
}

test('relation options encode pagination, trimmed search and selected values', async () => {
  let requestedUrl: URL | null = null

  installFetchMock(async (url) => {
    requestedUrl = url

    return new Response(JSON.stringify({
      items: [{ label: 'Example', value: 12 }],
      meta: { has_more: false, next_page: null },
    }), { status: 200 })
  })

  const result = await fetchRelationOptions('/admin/options', {
    page: 3,
    perPage: 15,
    search: '  example  ',
    selected: [12, 13],
  })

  expect(requestedUrl?.origin).toBe('https://flashboard.test')
  expect(requestedUrl?.searchParams.get('page')).toBe('3')
  expect(requestedUrl?.searchParams.get('per_page')).toBe('15')
  expect(requestedUrl?.searchParams.get('search')).toBe('example')
  expect(requestedUrl?.searchParams.getAll('selected[]')).toEqual(['12', '13'])
  expect(result.items?.[0]?.value).toBe(12)
})

test('relation options omit blank search and reject HTTP failures', async () => {
  let requestedUrl: URL | null = null
  installFetchMock(async (url) => {
    requestedUrl = url
    return new Response('Denied', { status: 403 })
  })

  await expect(fetchRelationOptions('/admin/options', {
    page: 1,
    perPage: 10,
    search: '   ',
  })).rejects.toThrow('Relation options request failed.')

  expect(requestedUrl?.searchParams.has('search')).toBe(false)
  expect(requestedUrl?.searchParams.has('selected[]')).toBe(false)
})
