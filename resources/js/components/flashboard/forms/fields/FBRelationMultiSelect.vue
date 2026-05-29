<script setup lang="ts">
import { refDebounced, useInfiniteScroll } from '@vueuse/core'
import { computed, onMounted, ref, watch } from 'vue'

type RelationOptionValue = string | number | boolean
type RelationMultiModelValue = RelationOptionValue | RelationOptionValue[] | null | undefined

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
  selected?: RelationMultiModelValue
  token: number
}

type SelectMenuExpose = {
  viewportRef?: HTMLElement | { value?: HTMLElement | null } | null
}

const props = defineProps<{
  disabled?: boolean
  maxItems?: number
  modelValue?: RelationMultiModelValue
  name?: string
  optionsPerPage?: number
  optionsUrl?: string
  placeholder?: string
  relatedRoutes?: Record<string, unknown>
  required?: boolean
  selectedOptions?: RelationOptionShape[] | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: RelationOptionValue[]]
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

const maxSelectedItems = computed(() => {
  if (!Number.isInteger(props.maxItems) || props.maxItems === undefined || props.maxItems < 1) {
    return null
  }

  return props.maxItems
})

const selectedValues = computed<RelationOptionValue[]>(() =>
  normalizeModelValue(props.modelValue)
    .map((value) => matchedItemValue(value) ?? value),
)

const selectedChipOptions = computed<RelationOptionShape[]>(() =>
  normalizeModelValue(props.modelValue)
    .map((value) => matchedItem(value) ?? {
      label: String(value),
      value,
    })
    .filter((option) => hasValue(option.value)),
)

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
    const values = normalizeModelValue(value)

    if (values.length > 0 && !hasLoadedValues(values)) {
      scheduleLoadPage(1, true, values)
    }
  },
  { immediate: true },
)

watch(
  () => props.selectedOptions,
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

function scheduleLoadPage(page: number, replace: boolean, selected?: RelationMultiModelValue) {
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

    for (const selectedValue of normalizeModelValue(request.selected)) {
      url.searchParams.append('selected[]', String(selectedValue))
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
  return normalizeOptions(props.selectedOptions ?? [])
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
    throw new Error('Relation multi-select options request failed.')
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

function hasLoadedValues(values: RelationOptionValue[]): boolean {
  return values.every((value) => matchedItem(value) !== undefined)
}

function matchedItem(value: RelationOptionValue): RelationOptionShape | undefined {
  return items.value
    .find((item) => hasValue(item.value) && String(item.value) === String(value))
}

function matchedItemValue(value: RelationOptionValue): RelationOptionValue | undefined {
  return matchedItem(value)?.value
}

function normalizeModelValue(value: RelationMultiModelValue): RelationOptionValue[] {
  const values = Array.isArray(value) ? value : [value]
  const valuesByKey = new Map<string, RelationOptionValue>()

  for (const item of values) {
    if (hasValue(item)) {
      valuesByKey.set(String(item), item)
    }
  }

  return Array.from(valuesByKey.values())
}

function emitModelValue(value: RelationMultiModelValue) {
  const normalized = normalizeModelValue(value)
  const limit = maxSelectedItems.value

  emit('update:modelValue', limit === null ? normalized : normalized.slice(0, limit))
}

function hasDetailUrl(option: RelationOptionShape): boolean {
  return props.relatedRoutes?.detail === true && typeof option.url === 'string' && option.url !== ''
}

function hasValue(value: RelationOptionValue | null | undefined): value is RelationOptionValue {
  return value !== null && value !== undefined && value !== ''
}
</script>

<template>
  <div class="grid w-full gap-2">
    <USelectMenu
      ref="selectMenuRef"
      v-model:open="isOpen"
      v-model:search-term="searchTerm"
      class="w-full"
      :clear="!props.required"
      :disabled="props.disabled"
      :ignore-filter="true"
      :items="items"
      :loading="isLoading"
      :model-value="selectedValues"
      multiple
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

    <div
      v-if="selectedChipOptions.length > 0"
      class="flex min-w-0 flex-wrap gap-1.5"
    >
      <component
        :is="hasDetailUrl(option) ? 'a' : 'span'"
        v-for="option in selectedChipOptions"
        :key="String(option.value)"
        class="inline-flex h-7 max-w-full items-center gap-1 rounded-md border border-muted bg-muted px-2 text-xs font-medium text-muted"
        :class="{ 'hover:bg-elevated hover:text-default': hasDetailUrl(option) }"
        :href="hasDetailUrl(option) ? option.url : undefined"
        target="_self"
      >
        <span class="truncate">{{ option.label ?? option.value }}</span>
        <UIcon
          v-if="hasDetailUrl(option)"
          class="size-3 shrink-0"
          name="i-lucide-external-link"
        />
      </component>
    </div>
  </div>
</template>
