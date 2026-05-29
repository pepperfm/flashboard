import type { RelationManagerPayload, RelationOptionsResponse, RelationScalarValue } from './types'

type RelationOptionsRequest = {
  page: number
  perPage: number
  search?: string
  selected?: RelationScalarValue | RelationScalarValue[] | null
}

export async function fetchRelationPayload(url: string): Promise<RelationManagerPayload> {
  const response = await window.fetch(url, {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  })

  if (!response.ok) {
    throw new Error('Relation records request failed.')
  }

  return await response.json() as RelationManagerPayload
}

export async function fetchRelationOptions(
  url: string,
  request: RelationOptionsRequest,
): Promise<RelationOptionsResponse> {
  const nextUrl = new URL(url, window.location.origin)
  nextUrl.searchParams.set('page', String(request.page))
  nextUrl.searchParams.set('per_page', String(request.perPage))

  if (request.search !== undefined && request.search.trim() !== '') {
    nextUrl.searchParams.set('search', request.search.trim())
  }

  for (const selectedValue of normalizedSelectedValues(request.selected)) {
    nextUrl.searchParams.append('selected[]', String(selectedValue))
  }

  const response = await window.fetch(nextUrl.toString(), {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  })

  if (!response.ok) {
    throw new Error('Relation options request failed.')
  }

  return await response.json() as RelationOptionsResponse
}

function normalizedSelectedValues(
  value: RelationScalarValue | RelationScalarValue[] | null | undefined,
): RelationScalarValue[] {
  if (value === null || value === undefined) {
    return []
  }

  return Array.isArray(value) ? value : [value]
}
