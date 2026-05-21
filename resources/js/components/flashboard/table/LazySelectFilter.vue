<script setup lang="ts">
import { refDebounced, useInfiniteScroll } from '@vueuse/core'
import { computed, onMounted, ref, watch } from 'vue'

type TableFilterOptionValue = string | number | boolean
type TableFilterModelValue = TableFilterOptionValue | TableFilterOptionValue[] | null | undefined

type TableFilterOptionShape = {
  label?: string
  value?: TableFilterOptionValue
}

type LazyTableFilterShape = {
  key: string
  label?: string
  multiple?: boolean
  options_per_page?: number
  options_url?: string
}

type LazyOptionsResponse = {
  items?: TableFilterOptionShape[]
  meta?: {
    has_more?: boolean
    next_page?: number | null
  }
}

type LazyOptionsRequest = {
  page: number
  replace: boolean
  selected?: TableFilterModelValue
  token: number
}

type SelectMenuExpose = {
  viewportRef?: HTMLElement | { value?: HTMLElement | null } | null
}

const props = defineProps<{
  filter: LazyTableFilterShape
  modelValue: TableFilterModelValue
}>()

const emit = defineEmits<{
  'update:modelValue': [value: TableFilterModelValue]
}>()

const DEFAULT_PER_PAGE = 15
const REQUEST_THROTTLE_MS = 1000
const SEARCH_DEBOUNCE_MS = 250
const SCROLL_DISTANCE_PX = 48

const selectMenuRef = ref<SelectMenuExpose | null>(null)
const isOpen = ref(false)
const searchTerm = ref('')
const debouncedSearchTerm = refDebounced(searchTerm, SEARCH_DEBOUNCE_MS)
const loadedItems = ref<TableFilterOptionShape[]>([])
const isLoading = ref(false)
const loadFailed = ref(false)
const hasMore = ref(false)
const nextPage = ref<number | null>(null)
const loadedOnce = ref(false)
let requestToken = 0
let activeRequestToken = 0
let lastRequestStartedAt = 0
let queuedRequest: LazyOptionsRequest | null = null
let queuedRequestTimer: ReturnType<typeof setTimeout> | null = null

onMounted(() => {
  scheduleLoadPage(1, true)
})

const items = computed<TableFilterOptionShape[]>(() => {
  if (loadedItems.value.length > 0) {
    return loadedItems.value
  }

  return []
})

const selectedValue = computed<TableFilterModelValue>(() => {
  const selectedValues = normalizeModelValue(props.modelValue)

  if (props.filter.multiple === true) {
    return selectedValues.map((selected) => matchedItemValue(selected) ?? selected)
  }

  const selected = selectedValues[0]

  if (!hasValue(selected)) {
    return null
  }

  return matchedItemValue(selected) ?? selected
})

const viewportElement = computed<HTMLElement | null>(() => {
  const viewport = selectMenuRef.value?.viewportRef

  if (!viewport) {
    return null
  }

  if (typeof HTMLElement !== 'undefined' && viewport instanceof HTMLElement) {
    return viewport
  }

  return 'value' in viewport ? viewport.value ?? null : null
})

watch(isOpen, (open) => {
  if (open && !loadedOnce.value) {
    scheduleLoadPage(1, true)
  }
})

watch(debouncedSearchTerm, () => {
  if (!isOpen.value) {
    return
  }

  scheduleLoadPage(1, true)
})

watch(
  () => props.modelValue,
  (value) => {
    if (normalizeModelValue(value).some((selected) => !hasLoadedValue(selected))) {
      scheduleLoadPage(1, true, value)
    }
  },
  { immediate: true },
)

useInfiniteScroll(
  viewportElement,
  async () => {
    await loadNextPage()
  },
  {
    distance: SCROLL_DISTANCE_PX,
    canLoadMore: () => hasMore.value && !isLoading.value,
  },
)

async function loadNextPage() {
  if (!hasMore.value || nextPage.value === null || isLoading.value) {
    return
  }

  scheduleLoadPage(nextPage.value, false)
}

function scheduleLoadPage(page: number, replace: boolean, selected?: TableFilterModelValue) {
  queuedRequest = {
    page,
    replace,
    selected: selected ?? (replace ? props.modelValue : undefined),
    token: ++requestToken,
  }

  flushQueuedRequest()
}

function flushQueuedRequest() {
  if (typeof window === 'undefined' || queuedRequestTimer !== null || queuedRequest === null) {
    return
  }

  if (isLoading.value) {
    return
  }

  const elapsedMs = Date.now() - lastRequestStartedAt
  const delayMs = Math.max(REQUEST_THROTTLE_MS - elapsedMs, 0)

  queuedRequestTimer = window.setTimeout(() => {
    queuedRequestTimer = null

    if (isLoading.value) {
      flushQueuedRequest()
      return
    }

    const request = queuedRequest
    queuedRequest = null

    if (request !== null) {
      void loadPage(request)
    }
  }, delayMs)
}

async function loadPage(request: LazyOptionsRequest) {
  if (typeof window === 'undefined' || !props.filter.options_url) {
    return
  }

  const currentRequestToken = request.token
  activeRequestToken = currentRequestToken
  lastRequestStartedAt = Date.now()
  isLoading.value = true
  loadFailed.value = false

  try {
    const url = new URL(props.filter.options_url, window.location.origin)
    url.searchParams.set('page', String(request.page))
    url.searchParams.set('per_page', String(props.filter.options_per_page ?? DEFAULT_PER_PAGE))

    if (debouncedSearchTerm.value.trim() !== '') {
      url.searchParams.set('search', debouncedSearchTerm.value.trim())
    }

    const selectedValues = normalizeModelValue(request.selected)

    if (selectedValues.length === 1) {
      url.searchParams.set('selected', String(selectedValues[0]))
    } else {
      for (const selected of selectedValues) {
        url.searchParams.append('selected[]', String(selected))
      }
    }

    const payload = await requestOptions(url.toString())

    if (currentRequestToken !== requestToken) {
      return
    }

    const responseItems = normalizeOptions(payload.items ?? [])
    loadedItems.value = request.replace ? responseItems : mergeOptions(loadedItems.value, responseItems)
    hasMore.value = payload.meta?.has_more === true
    nextPage.value = payload.meta?.next_page ?? null
    loadedOnce.value = true
  } catch {
    if (currentRequestToken === requestToken) {
      loadFailed.value = true
    }
  } finally {
    if (currentRequestToken === activeRequestToken) {
      isLoading.value = false
    }

    flushQueuedRequest()
  }
}

function normalizeOptions(options: TableFilterOptionShape[]): TableFilterOptionShape[] {
  return options
    .filter((option) => hasValue(option.value))
    .map((option) => ({
      label: String(option.label ?? option.value),
      value: option.value,
    }))
}

async function requestOptions(url: string): Promise<LazyOptionsResponse> {
  if (typeof window !== 'undefined' && typeof window.fetch === 'function') {
    const response = await window.fetch(url, {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })

    if (!response.ok) {
      throw new Error('Lazy select options request failed.')
    }

    return await response.json() as LazyOptionsResponse
  }

  if (typeof window !== 'undefined' && typeof window.XMLHttpRequest === 'function') {
    return await requestOptionsWithXhr(url)
  }

  return await requestOptionsWithFrame(url)
}

function requestOptionsWithXhr(url: string): Promise<LazyOptionsResponse> {
  return new Promise((resolve, reject) => {
    const request = new window.XMLHttpRequest()
    request.open('GET', url, true)
    request.withCredentials = true
    request.setRequestHeader('Accept', 'application/json')
    request.setRequestHeader('X-Requested-With', 'XMLHttpRequest')

    request.onload = () => {
      if (request.status < 200 || request.status >= 300) {
        reject(new Error('Lazy select options request failed.'))
        return
      }

      try {
        resolve(JSON.parse(request.responseText) as LazyOptionsResponse)
      } catch (error) {
        reject(error instanceof Error ? error : new Error('Lazy select options response was invalid.'))
      }
    }

    request.onerror = () => {
      reject(new Error('Lazy select options request failed.'))
    }

    request.send()
  })
}

function requestOptionsWithFrame(url: string): Promise<LazyOptionsResponse> {
  return new Promise((resolve, reject) => {
    if (typeof document === 'undefined' || document.body === null) {
      reject(new Error('Lazy select options request transport is unavailable.'))
      return
    }

    const frame = document.createElement('iframe')
    const timeout = window.setTimeout(() => {
      cleanup()
      reject(new Error('Lazy select options request timed out.'))
    }, 15000)

    const cleanup = () => {
      window.clearTimeout(timeout)
      frame.remove()
    }

    frame.hidden = true
    frame.onload = () => {
      try {
        const text = frame.contentDocument?.body?.innerText
          ?? frame.contentWindow?.document.body?.innerText
          ?? ''

        if (text.trim() === '') {
          throw new Error('Lazy select options response was empty.')
        }

        resolve(JSON.parse(text) as LazyOptionsResponse)
      } catch (error) {
        reject(error instanceof Error ? error : new Error('Lazy select options response was invalid.'))
      } finally {
        cleanup()
      }
    }
    frame.onerror = () => {
      cleanup()
      reject(new Error('Lazy select options request failed.'))
    }
    frame.src = url
    document.body.appendChild(frame)
  })
}

function mergeOptions(
  currentOptions: TableFilterOptionShape[],
  nextOptions: TableFilterOptionShape[],
): TableFilterOptionShape[] {
  const optionsByValue = new Map<string, TableFilterOptionShape>()

  for (const option of [...currentOptions, ...nextOptions]) {
    if (!hasValue(option.value)) {
      continue
    }

    optionsByValue.set(String(option.value), option)
  }

  return Array.from(optionsByValue.values())
}

function hasLoadedValue(value: TableFilterOptionValue | null | undefined): boolean {
  if (!hasValue(value)) {
    return true
  }

  return items.value
    .some((item) => String(item.value) === String(value))
}

function matchedItemValue(value: TableFilterOptionValue): TableFilterOptionValue | undefined {
  return items.value
    .find((item) => String(item.value) === String(value))
    ?.value
}

function normalizeModelValue(value: TableFilterModelValue): TableFilterOptionValue[] {
  if (!Array.isArray(value)) {
    return hasValue(value) ? [value] : []
  }

  const valuesByKey = new Map<string, TableFilterOptionValue>()

  for (const item of value) {
    if (hasValue(item)) {
      valuesByKey.set(String(item), item)
    }
  }

  return Array.from(valuesByKey.values())
}

function emitModelValue(value: TableFilterModelValue) {
  if (props.filter.multiple === true) {
    emit('update:modelValue', normalizeModelValue(value))

    return
  }

  emit('update:modelValue', normalizeModelValue(value)[0] ?? undefined)
}

function hasValue(value: TableFilterOptionValue | null | undefined): value is TableFilterOptionValue {
  return value !== null && value !== undefined && value !== ''
}

</script>

<template>
  <USelectMenu
    ref="selectMenuRef"
    v-model:open="isOpen"
    v-model:search-term="searchTerm"
    class="w-full"
    clear
    :ignore-filter="true"
    :items="items"
    :loading="isLoading"
    :model-value="selectedValue"
    :multiple="filter.multiple === true"
    :placeholder="filter.label ?? filter.key"
    :reset-search-term-on-blur="false"
    :search-input="{
      autocomplete: 'off',
      icon: 'i-lucide-search',
      placeholder: 'Search',
    }"
    value-key="value"
    @update:model-value="emitModelValue($event)"
  >
    <template v-if="loadFailed" #empty>
      Could not load options.
    </template>
  </USelectMenu>
</template>
