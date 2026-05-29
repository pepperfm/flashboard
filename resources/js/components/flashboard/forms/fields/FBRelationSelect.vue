<script setup lang="ts">
import { refDebounced, useInfiniteScroll } from '@vueuse/core'
import { computed, onMounted, ref, watch } from 'vue'

type RelationOptionValue = string | number | boolean
type RelationModelValue = RelationOptionValue | null | undefined

type RelationOptionShape = {
  label?: string
  url?: string
  value?: RelationOptionValue
}

type LazyOptionsResponse = {
  items?: RelationOptionShape[]
  meta?: {
    has_more?: boolean
    next_page?: number | null
  }
}

type LazyOptionsRequest = {
  page: number
  replace: boolean
  selected?: RelationModelValue
  token: number
}

type SelectMenuExpose = {
  viewportRef?: HTMLElement | { value?: HTMLElement | null } | null
}

const props = defineProps<{
  disabled?: boolean
  modelValue?: RelationModelValue
  name?: string
  optionsPerPage?: number
  optionsUrl?: string
  placeholder?: string
  relatedRoutes?: Record<string, unknown>
  required?: boolean
  selectedOption?: RelationOptionShape | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: RelationModelValue]
}>()

const DEFAULT_PER_PAGE = 15
const REQUEST_THROTTLE_MS = 1000
const SEARCH_DEBOUNCE_MS = 250
const SCROLL_DISTANCE_PX = 48

const selectMenuRef = ref<SelectMenuExpose | null>(null)
const isOpen = ref(false)
const searchTerm = ref('')
const debouncedSearchTerm = refDebounced(searchTerm, SEARCH_DEBOUNCE_MS)
const loadedItems = ref<RelationOptionShape[]>(normalizeInitialOptions())
const isLoading = ref(false)
const loadFailed = ref(false)
const hasMore = ref(false)
const nextPage = ref<number | null>(null)
const loadedOnce = ref(loadedItems.value.length > 0)
let requestToken = 0
let activeRequestToken = 0
let lastRequestStartedAt = 0
let queuedRequest: LazyOptionsRequest | null = null
let queuedRequestTimer: ReturnType<typeof setTimeout> | null = null

onMounted(() => {
  scheduleLoadPage(1, true)
})

const items = computed<RelationOptionShape[]>(() => loadedItems.value)

const selectedValue = computed<RelationModelValue>(() => {
  if (!hasValue(props.modelValue)) {
    return null
  }

  return matchedItemValue(props.modelValue) ?? props.modelValue
})

const selectedOptionUrl = computed(() => {
  if (!hasValue(props.modelValue) || props.relatedRoutes?.detail !== true) {
    return null
  }

  return items.value
    .find((item) => hasValue(item.value) && String(item.value) === String(props.modelValue))
    ?.url ?? null
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
    if (hasValue(value) && !hasLoadedValue(value)) {
      scheduleLoadPage(1, true, value)
    }
  },
  { immediate: true },
)

watch(
  () => props.selectedOption,
  () => {
    loadedItems.value = mergeOptions(loadedItems.value, normalizeInitialOptions())
  },
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

function scheduleLoadPage(page: number, replace: boolean, selected?: RelationModelValue) {
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
  if (typeof window === 'undefined' || !props.optionsUrl) {
    return
  }

  const currentRequestToken = request.token
  activeRequestToken = currentRequestToken
  lastRequestStartedAt = Date.now()
  isLoading.value = true
  loadFailed.value = false

  try {
    const url = new URL(props.optionsUrl, window.location.origin)
    url.searchParams.set('page', String(request.page))
    url.searchParams.set('per_page', String(props.optionsPerPage ?? DEFAULT_PER_PAGE))

    if (debouncedSearchTerm.value.trim() !== '') {
      url.searchParams.set('search', debouncedSearchTerm.value.trim())
    }

    if (hasValue(request.selected)) {
      url.searchParams.set('selected', String(request.selected))
    }

    const payload = await requestOptions(url.toString())

    if (currentRequestToken !== requestToken) {
      return
    }

    const responseItems = normalizeOptions(payload.items ?? [])
    loadedItems.value = request.replace ? mergeOptions(normalizeInitialOptions(), responseItems) : mergeOptions(loadedItems.value, responseItems)
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

function normalizeInitialOptions(): RelationOptionShape[] {
  return normalizeOptions(props.selectedOption ? [props.selectedOption] : [])
}

function normalizeOptions(options: RelationOptionShape[]): RelationOptionShape[] {
  return options
    .filter((option) => hasValue(option.value))
    .map((option) => ({
      label: String(option.label ?? option.value),
      url: option.url,
      value: option.value,
    }))
}

async function requestOptions(url: string): Promise<LazyOptionsResponse> {
  const response = await window.fetch(url, {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  })

  if (!response.ok) {
    throw new Error('Relation select options request failed.')
  }

  return await response.json() as LazyOptionsResponse
}

function mergeOptions(
  currentOptions: RelationOptionShape[],
  nextOptions: RelationOptionShape[],
): RelationOptionShape[] {
  const optionsByValue = new Map<string, RelationOptionShape>()

  for (const option of [...currentOptions, ...nextOptions]) {
    if (!hasValue(option.value)) {
      continue
    }

    const key = String(option.value)
    const existingOption = optionsByValue.get(key)

    optionsByValue.set(key, {
      ...existingOption,
      ...option,
      url: option.url ?? existingOption?.url,
    })
  }

  return Array.from(optionsByValue.values())
}

function hasLoadedValue(value: RelationOptionValue): boolean {
  return items.value.some((item) => hasValue(item.value) && String(item.value) === String(value))
}

function matchedItemValue(value: RelationOptionValue): RelationOptionValue | undefined {
  return items.value
    .find((item) => hasValue(item.value) && String(item.value) === String(value))
    ?.value
}

function emitModelValue(value: RelationModelValue) {
  emit('update:modelValue', hasValue(value) ? value : undefined)
}

function hasValue(value: RelationOptionValue | null | undefined): value is RelationOptionValue {
  return value !== null && value !== undefined && value !== ''
}
</script>

<template>
  <div class="flex w-full items-center gap-2">
    <USelectMenu
      ref="selectMenuRef"
      v-model:open="isOpen"
      v-model:search-term="searchTerm"
      class="min-w-0 flex-1"
      :clear="!props.required"
      :disabled="props.disabled"
      :ignore-filter="true"
      :items="items"
      :loading="isLoading"
      :model-value="selectedValue"
      :placeholder="props.placeholder"
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
        Could not load records.
      </template>
    </USelectMenu>

    <UButton
      v-if="selectedOptionUrl"
      color="neutral"
      :href="selectedOptionUrl"
      icon="i-lucide-external-link"
      size="md"
      square
      target="_self"
      variant="ghost"
      :aria-label="`Open ${props.placeholder ?? props.name ?? 'record'}`"
    />
  </div>
</template>
