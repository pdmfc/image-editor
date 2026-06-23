import { computed, ref, unref } from 'vue'
import { isInertiaDocument } from './isInertiaDocument.js'

function parseGalleryMaxImages(value) {
  const n = Number(value)

  return Number.isFinite(n) && n > 0 ? Math.round(n) : 0
}

function getNovaGalleryMaxImages() {
  const Nova = typeof globalThis !== 'undefined' ? globalThis.Nova : null

  if (!Nova || typeof Nova.config !== 'function') {
    return null
  }

  const value = Nova.config('imageEditor')?.galleryMaxImages

  return value == null ? null : parseGalleryMaxImages(value)
}

function getInertiaGalleryMaxImages(inertiaModule) {
  if (!inertiaModule?.usePage) {
    return null
  }

  try {
    const value = inertiaModule.usePage()?.props?.imageEditor?.galleryMaxImages

    return value == null ? null : parseGalleryMaxImages(value)
  } catch {
    return null
  }
}

function readGalleryMaxImagesFromDataPage() {
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
    const value = page?.props?.imageEditor?.galleryMaxImages

    return value == null ? null : parseGalleryMaxImages(value)
  } catch {
    return null
  }
}

function resolveGalleryMaxImages(explicit, inertiaModule) {
  if (explicit != null && explicit !== '') {
    return parseGalleryMaxImages(explicit)
  }

  const fromNova = getNovaGalleryMaxImages()

  if (fromNova !== null) {
    return fromNova
  }

  const fromDataPage = readGalleryMaxImagesFromDataPage()

  if (fromDataPage !== null) {
    return fromDataPage
  }

  const fromInertia = getInertiaGalleryMaxImages(inertiaModule)

  if (fromInertia !== null) {
    return fromInertia
  }

  return 0
}

/**
 * Limite máximo de imagens na galeria (IMAGE_EDITOR_GALLERY_MAX_IMAGES / IMAGE_EDITOR_GALLERY_TOTAL).
 * 0 = sem limite.
 */
export function useImageEditorGalleryMaxImages(galleryMaxImagesProp) {
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

    return resolveGalleryMaxImages(
      unref(galleryMaxImagesProp),
      inertiaModule.value
    )
  })
}
