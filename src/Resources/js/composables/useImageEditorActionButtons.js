import { computed, ref, unref } from 'vue'

export const DEFAULT_ACTION_BUTTONS = ['upload', 'qrcode', 'camera', 'canvas']

function normalizeActionButtons(list) {
  const source =
    Array.isArray(list) && list.length ? list : DEFAULT_ACTION_BUTTONS

  return new Set(source.map((key) => String(key).toLowerCase()))
}

function getNovaActionButtons() {
  const Nova = typeof globalThis !== 'undefined' ? globalThis.Nova : null

  if (!Nova || typeof Nova.config !== 'function') {
    return null
  }

  const buttons = Nova.config('imageEditor')?.actionButtons

  return Array.isArray(buttons) && buttons.length ? buttons : null
}

function getInertiaActionButtons(inertiaModule) {
  if (!inertiaModule?.usePage) {
    return null
  }

  try {
    const buttons = inertiaModule.usePage()?.props?.imageEditor?.actionButtons

    return Array.isArray(buttons) && buttons.length ? buttons : null
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

function resolveActionButtonsList(explicit, inertiaModule) {
  if (explicit != null) {
    return explicit
  }

  const fromNova = getNovaActionButtons()

  if (fromNova) {
    return fromNova
  }

  const fromInertia = getInertiaActionButtons(inertiaModule)

  if (fromInertia) {
    return fromInertia
  }

  return DEFAULT_ACTION_BUTTONS
}

/**
 * Resolves toolbar actions from (in order): prop, Nova.config('imageEditor'), Inertia shared props, defaults.
 */
export function useImageEditorActionButtons(actionButtonsProp) {
  const shouldLoadInertia = isInertiaDocument()
  const inertiaModule = ref(null)
  const inertiaReady = ref(!shouldLoadInertia)

  if (shouldLoadInertia) {
    // Hidden from Rollup so Nova hosts without @inertiajs/vue3 still build.
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

    const explicit = unref(actionButtonsProp)

    return normalizeActionButtons(
      resolveActionButtonsList(explicit, inertiaModule.value)
    )
  })
}
