<script setup>
import { watch, onUnmounted } from 'vue'
import Camera from '../Pages/Camera.vue'

const props = defineProps({
  open: {
    type: Boolean,
    default: false
  },
  initialFilename: {
    type: String,
    default: null
  },
  title: {
    type: String,
    default: 'Editor de imagens'
  },
  subtitle: {
    type: String,
    default: null
  },
  zIndex: {
    type: [Number, String],
    default: 200
  },
  userId: {
    type: [String, Number],
    default: null
  },
  actionButtons: {
    type: Array,
    default: null
  }
})

const emit = defineEmits(['update:open', 'close', 'useInForm'])

const closeModal = () => {
  emit('update:open', false)
  emit('close')
}

const onUseInForm = (payload) => {
  emit('useInForm', payload)
  closeModal()
}

watch(
  () => props.open,
  (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : ''
  },
  { immediate: true }
)

onUnmounted(() => {
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 flex flex-col bg-gray-900"
      :style="{ zIndex }"
      role="dialog"
      aria-modal="true"
      :aria-label="title"
    >
      <header
        class="flex shrink-0 items-center justify-between gap-3 border-b border-white/10 bg-gray-900 px-4 py-2.5"
      >
        <p class="truncate text-sm font-medium text-white">
          {{ title }}
          <span v-if="subtitle" class="text-white/50"> — {{ subtitle }}</span>
        </p>
        <button
          type="button"
          class="shrink-0 rounded-lg bg-white/10 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/20"
          @click="closeModal"
        >
          Fechar
        </button>
      </header>

      <div class="min-h-0 flex-1">
        <Camera
          as-modal
          :initial-filename="initialFilename || undefined"
          :user-id="userId"
          :action-buttons="actionButtons"
          @use-in-form="onUseInForm"
        />
      </div>
    </div>
  </Teleport>
</template>
