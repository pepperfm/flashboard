export type PageScriptAsset = {
  type?: string
  url: string
}

export type PageStyleAsset = {
  url: string
}

export type PageComponentAssets = {
  scripts?: PageScriptAsset[]
  styles?: PageStyleAsset[]
}

const loadedScripts = new Set<string>()
const loadingScripts = new Map<string, Promise<void>>()
const loadedStyles = new Set<string>()
const loadingStyles = new Map<string, Promise<void>>()

export async function loadPageComponentAssets(assets?: PageComponentAssets | null): Promise<void> {
  if (typeof document === 'undefined') {
    return
  }

  await Promise.all((assets?.styles ?? []).map((asset) => loadStyleAsset(asset)))

  for (const asset of assets?.scripts ?? []) {
    await loadScriptAsset(asset)
  }
}

function loadStyleAsset(asset: PageStyleAsset): Promise<void> {
  const url = normalizeAssetUrl(asset.url)

  if (loadedStyles.has(url) || document.querySelector(`link[rel="stylesheet"][href="${cssEscape(url)}"]`)) {
    loadedStyles.add(url)
    return Promise.resolve()
  }

  const existingLoad = loadingStyles.get(url)

  if (existingLoad) {
    return existingLoad
  }

  const load = new Promise<void>((resolve, reject) => {
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = url
    link.onload = () => {
      loadedStyles.add(url)
      loadingStyles.delete(url)
      resolve()
    }
    link.onerror = () => {
      loadingStyles.delete(url)
      reject(new Error('Failed to load custom page style asset.'))
    }

    document.head.appendChild(link)
  })

  loadingStyles.set(url, load)

  return load
}

function loadScriptAsset(asset: PageScriptAsset): Promise<void> {
  const url = normalizeAssetUrl(asset.url)
  const type = normalizeScriptType(asset.type)
  const key = `${type}:${url}`

  if (loadedScripts.has(key)) {
    return Promise.resolve()
  }

  const existingLoad = loadingScripts.get(key)

  if (existingLoad) {
    return existingLoad
  }

  const load = new Promise<void>((resolve, reject) => {
    const script = document.createElement('script')
    script.async = false
    script.src = url
    script.type = type
    script.onload = () => {
      loadedScripts.add(key)
      loadingScripts.delete(key)
      resolve()
    }
    script.onerror = () => {
      loadingScripts.delete(key)
      reject(new Error('Failed to load custom page script asset.'))
    }

    document.head.appendChild(script)
  })

  loadingScripts.set(key, load)

  return load
}

function normalizeAssetUrl(url: string): string {
  return url.trim()
}

function normalizeScriptType(type?: string): string {
  return type?.trim() || 'module'
}

function cssEscape(value: string): string {
  if (typeof CSS !== 'undefined' && CSS.escape) {
    return CSS.escape(value)
  }

  return value.replace(/"/g, '\\"')
}
