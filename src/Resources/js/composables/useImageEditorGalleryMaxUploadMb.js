import { computed, ref, unref } from 'vue'
import { isInertiaDocument } from './isInertiaDocument.js'

function parseGalleryMaxUploadMb(value) {
  const n = Number(value)

  return Number.isFinite(n) && n > 0 ? Math.round(n) : 10
}

function getNovaGalleryMaxUploadMb() {
  const Nova = typeof globalThis !== 'undefined' ? globalThis.Nova : null

  if (!Nova || typeof Nova.config !== 'function') {
    return null
  }

  const value = Nova.config('imageEditor')?.galleryMaxUploadMb

  return value == null ? null : parseGalleryMaxUploadMb(value)
}

function getInertiaGalleryMaxUploadMb(inertiaModule) {
  if (!inertiaModule?.usePage) {
    return null
  }

  try {
    const value = inertiaModule.usePage()?.props?.imageEditor?.galleryMaxUploadMb

    return value == null ? null : parseGalleryMaxUploadMb(value)
  } catch {
    return null
  }
}

function readGalleryMaxUploadMbFromDataPage() {
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
    const value = page?.props?.imageEditor?.galleryMaxUploadMb

    return value == null ? null : parseGalleryMaxUploadMb(value)
  } catch {
    return null
  }
}

function resolveGalleryMaxUploadMb(explicit, inertiaModule) {
  if (explicit != null && explicit !== '') {
    return parseGalleryMaxUploadMb(explicit)
  }

  const fromNova = getNovaGalleryMaxUploadMb()

  if (fromNova !== null) {
    return fromNova
  }

  const fromDataPage = readGalleryMaxUploadMbFromDataPage()

  if (fromDataPage !== null) {
    return fromDataPage
  }

  const fromInertia = getInertiaGalleryMaxUploadMb(inertiaModule)

  if (fromInertia !== null) {
    return fromInertia
  }

  return 10
}

/**
 * Tamanho máximo de upload por imagem (IMAGE_EDITOR_GALLERY_MAX_UPLOAD_MB).
 */
export function useImageEditorGalleryMaxUploadMb(galleryMaxUploadMbProp) {
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

    return resolveGalleryMaxUploadMb(
      unref(galleryMaxUploadMbProp),
      inertiaModule.value
    )
  })
}
