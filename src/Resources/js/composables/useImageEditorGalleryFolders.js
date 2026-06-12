import { computed, ref, unref } from 'vue'

function getNovaGalleryFoldersEnabled() {
  const Nova = typeof globalThis !== 'undefined' ? globalThis.Nova : null

  if (!Nova || typeof Nova.config !== 'function') {
    return null
  }

  const value = Nova.config('imageEditor')?.galleryFoldersEnabled

  return typeof value === 'boolean' ? value : null
}

function getInertiaGalleryFoldersEnabled(inertiaModule) {
  if (!inertiaModule?.usePage) {
    return null
  }

  try {
    const value = inertiaModule.usePage()?.props?.imageEditor?.galleryFoldersEnabled

    return typeof value === 'boolean' ? value : null
  } catch {
    return null
  }
}

function isInertiaDocument() {
  if (typeof globalThis !== 'undefined' && globalThis.Nova) {
    return false
  }

  if (typeof document === 'undefined') {
    return false
  }

  const app = document.getElementById('app')

  return Boolean(app?.hasAttribute('data-page'))
}

function readGalleryFoldersFromDataPage() {
  if (typeof document === 'undefined') {
    return null
  }

  const app = document.getElementById('app')
  const raw = app?.dataset?.page

  if (!raw) {
    return null
  }

  try {
    const page = JSON.parse(raw)
    const value = page?.props?.imageEditor?.galleryFoldersEnabled

    return typeof value === 'boolean' ? value : null
  } catch {
    return null
  }
}

function resolveGalleryFoldersEnabled(explicit, inertiaModule) {
  if (typeof explicit === 'boolean') {
    return explicit
  }

  const fromNova = getNovaGalleryFoldersEnabled()

  if (fromNova !== null) {
    return fromNova
  }

  const fromDataPage = readGalleryFoldersFromDataPage()

  if (fromDataPage !== null) {
    return fromDataPage
  }

  const fromInertia = getInertiaGalleryFoldersEnabled(inertiaModule)

  if (fromInertia !== null) {
    return fromInertia
  }

  return false
}

/**
 * Pastas virtuais na galeria (activas quando IMAGE_EDITOR_GALLERY_FOLDERS=true).
 */
export function useImageEditorGalleryFolders(galleryFoldersEnabledProp) {
  const shouldLoadInertia = isInertiaDocument()
  const inertiaModule = ref(null)
  const inertiaReady = ref(!shouldLoadInertia)

  if (shouldLoadInertia) {
    const loadInertia = new Function("return import('@inertiajs/vue3')")

    loadInertia()
      .then((module) => {
        inertiaModule.value = module
      })
      .catch(() => {
        inertiaModule.value = null
      })
      .finally(() => {
        inertiaReady.value = true
      })
  }

  return computed(() => {
    if (shouldLoadInertia) {
      void inertiaReady.value
    }

    return resolveGalleryFoldersEnabled(
      unref(galleryFoldersEnabledProp),
      inertiaModule.value
    )
  })
}
