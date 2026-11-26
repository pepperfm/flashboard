import { refDebounced } from '@vueuse/core'
import { computed, ref, watch, type Ref } from 'vue'
import { fetchRelationOptions } from './relationRequests'
import type { RelationOptionShape, RelationScalarValue } from './types'
import { hasRelationValue } from './types'

const DEFAULT_PER_PAGE = 15
const SEARCH_DEBOUNCE_MS = 250

type SelectedValueInput = RelationScalarValue | RelationScalarValue[] | null | undefined

export function useRelationOptions(
  optionsUrl: Ref<string | null | undefined>,
  perPage: Ref<number | null | undefined>,
  selected: Ref<SelectedValueInput>,
) {
  const isOpen = ref(false)
  const searchTerm = ref('')
  const debouncedSearchTerm = refDebounced(searchTerm, SEARCH_DEBOUNCE_MS)
  const items = ref<RelationOptionShape[]>([])
  const isLoading = ref(false)
  const isLoadingMore = ref(false)
  const loadFailed = ref(false)
  const hasMore = ref(false)
  const nextPage = ref<number | null>(null)
  let requestToken = 0

  const normalizedPerPage = computed(() => Math.max(1, perPage.value ?? DEFAULT_PER_PAGE))

  watch(isOpen, (open) => {
    if (open) {
      void loadPage(1, true)
    }
  })

  watch(debouncedSearchTerm, () => {
    if (isOpen.value) {
      void loadPage(1, true)
    }
  })

  watch(selected, (value) => {
    if (isOpen.value && hasSelectedValue(value) && !selectedOptionsPresent(value)) {
      void loadPage(1, true)
    }
  })

  function selectedOptionsPresent(value: SelectedValueInput): boolean {
    const values = Array.isArray(value) ? value : [value]
    return values.filter(hasRelationValue).every((key) =>
      items.value.some((item) => String(item.value) === String(key)),
    )
  }

  async function loadPage(page: number, replace: boolean) {
    if (typeof window === 'undefined' || !optionsUrl.value) {
      return
    }

    const currentToken = ++requestToken
    isLoading.value = replace
    isLoadingMore.value = !replace
    loadFailed.value = false
    if (replace) {
      hasMore.value = false
      nextPage.value = null
    }

    try {
      const payload = await fetchRelationOptions(optionsUrl.value, {
        page,
        perPage: normalizedPerPage.value,
        search: debouncedSearchTerm.value,
        selected: selected.value,
      })

      if (currentToken !== requestToken) {
        return
      }

      const nextItems = normalizeOptions(payload.items ?? [])
      items.value = replace ? nextItems : mergeOptions(items.value, nextItems)
      hasMore.value = payload.meta?.has_more === true
      nextPage.value = payload.meta?.next_page ?? null
    } catch {
      if (currentToken === requestToken) {
        loadFailed.value = true
      }
    } finally {
      if (currentToken === requestToken) {
        isLoading.value = false
        isLoadingMore.value = false
      }
    }
  }

  function loadMore() {
    if (!hasMore.value || nextPage.value === null || isLoading.value || isLoadingMore.value) {
      return
    }

    void loadPage(nextPage.value, false)
  }

  return {
    hasMore,
    isLoading,
    isLoadingMore,
    isOpen,
    items,
    loadFailed,
    loadMore,
    searchTerm,
  }
}

function normalizeOptions(options: RelationOptionShape[]): RelationOptionShape[] {
  return options
    .filter((option) => hasRelationValue(option.value))
    .map((option) => ({
      label: String(option.label ?? option.value),
      url: option.url,
      value: option.value,
    }))
}

function mergeOptions(
  currentOptions: RelationOptionShape[],
  nextOptions: RelationOptionShape[],
): RelationOptionShape[] {
  const optionsByValue = new Map<string, RelationOptionShape>()

  for (const option of [...currentOptions, ...nextOptions]) {
    if (!hasRelationValue(option.value)) {
      continue
    }

    optionsByValue.set(String(option.value), option)
  }

  return Array.from(optionsByValue.values())
}

function hasSelectedValue(value: SelectedValueInput): boolean {
  if (Array.isArray(value)) {
    return value.some((item) => hasRelationValue(item))
  }

  return hasRelationValue(value)
}
