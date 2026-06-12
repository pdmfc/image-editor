import { onMounted, onUnmounted, watch } from 'vue'

export function useImageEditorRealtime(userIdRef, handlers = {}) {
  let channel = null

  const channelName = (id) => {
    const safe = String(id ?? '').replace(/[^a-zA-Z0-9_-]/g, '')
    return safe ? `image-editor.photos.${safe}` : null
  }

  const leave = () => {
    if (!channel || !window.Echo) {
      channel = null
      return
    }
    try {
      window.Echo.leave(channel.name)
    } catch {}
    channel = null
  }

  const subscribe = (userId) => {
    leave()

    const name = channelName(userId)
    if (!name || !window.Echo) {
      return
    }

    channel = window.Echo.private(name)
    channel.listen('.PhotosUploadedFromMobile', (payload) => {
      handlers.onPhotosUploaded?.(payload)
    })
  }

  onMounted(() => {
    subscribe(userIdRef.value ?? userIdRef)
  })

  if (typeof userIdRef === 'object' && 'value' in userIdRef) {
    watch(userIdRef, (id) => subscribe(id))
  }

  onUnmounted(() => {
    leave()
  })
}
