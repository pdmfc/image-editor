<template>
  <div v-if="show" class="fixed inset-0 z-50 bg-black">
    <!-- Botão de Fechar -->
    <button
      @click="$emit('close')"
      class="absolute top-4 right-4 z-50 p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
    >
      <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>

    <div class="relative w-full h-full">
      <!-- Área da Câmera -->
      <div class="relative w-full h-full bg-black">
        <video
          v-if="!photo"
          ref="video"
          class="w-full h-full object-cover"
          :class="{ 'transform scale-x-[-1]': isMirrored }"
          autoplay
          playsinline
        ></video>
        <img
          v-else
          :src="photo"
          class="w-full h-full object-cover"
          :class="{ 'transform scale-x-[-1]': isMirrored }"
          alt="Foto capturada"
        />
      </div>

      <!-- Controles da Câmera -->
      <div class="absolute bottom-0 left-0 right-0 p-4 bg-black bg-opacity-50">
        <div class="flex justify-center space-x-4">
          <!-- Botão de Espelhar -->
          <button
            v-if="!photo"
            @click="toggleMirror"
            class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 transition-colors duration-200"
            :class="{ 'bg-blue-600 bg-opacity-75 hover:bg-opacity-100': isMirrored }"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
          </button>
          
          <!-- Botão de Capturar -->
          <button
            v-if="!photo"
            @click="capture"
            class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 transition-colors duration-200"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>
          
          <!-- Botões após captura -->
          <template v-if="photo">
            <button
              @click="retake"
              class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 transition-colors duration-200"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            </button>
            
            <button
              @click="savePhoto"
              class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 transition-colors duration-200"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </button>
          </template>
        </div>
      </div>
    </div>

    <!-- Notificações -->
    <div class="fixed inset-0 flex items-start justify-center px-4 py-6 pointer-events-none sm:p-6 z-50">
      <div class="max-w-sm w-full">
        <Notification
          :show="notification.show"
          :type="notification.type"
          :title="notification.title"
          :message="notification.message"
          :show-actions="notification.showActions"
          :duration="notification.duration"
          @confirm="confirmDelete(notification.photoToDelete)"
          @cancel="notification.show = false"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import axios from 'axios'
import Notification from './Notification.vue'

const props = defineProps({
  show: {
    type: Boolean,
    required: true
  },
  userId: {
    type: [String, Number],
    default: null
  }
})

const emit = defineEmits(['close', 'photo-saved'])

const video = ref(null)
const stream = ref(null)
const photo = ref(null)
const canvas = ref(null)
const isMirrored = ref(true)

const notification = ref({
  show: false,
  type: 'success',
  title: '',
  message: '',
  showActions: false,
  duration: 5000,
  confirmCallback: null
})

const showNotification = (type, title, message, showActions = false, duration = 5000, confirmCallback = null) => {
  notification.value = {
    show: true,
    type,
    title,
    message,
    showActions,
    duration,
    confirmCallback
  }
}

const startCamera = async () => {
  try {
    stream.value = await navigator.mediaDevices.getUserMedia({ 
      video: { 
        facingMode: 'environment',
        width: { ideal: 1920 },
        height: { ideal: 1080 }
      } 
    })
    
    if (video.value) {
      video.value.srcObject = stream.value
    }
  } catch (error) {
    console.error('Erro ao acessar a câmera:', error)
    showNotification('error', 'Erro', 'Não foi possível acessar a câmera')
  }
}

const stopCamera = () => {
  if (stream.value) {
    stream.value.getTracks().forEach(track => track.stop())
    stream.value = null
  }
}

const capture = () => {
  if (!video.value) return

  canvas.value = document.createElement('canvas')
  canvas.value.width = video.value.videoWidth
  canvas.value.height = video.value.videoHeight
  canvas.value.getContext('2d').drawImage(video.value, 0, 0)
  photo.value = canvas.value.toDataURL('image/jpeg')
}

const retake = async () => {
  photo.value = null
  stopCamera()
  await startCamera()
}

const savePhoto = async () => {
  if (!photo.value) return

  try {
    const { data } = await axios.post('/api/camera/capture', {
      user_id: props.userId,
      photo: photo.value.split(',')[1]
    })

    showNotification('success', 'Sucesso', 'Foto salva com sucesso!')
    emit('photo-saved', data)
    photo.value = null
    stopCamera()
    await startCamera()
  } catch (error) {
    console.error('Erro ao salvar a foto:', error)
    showNotification('error', 'Erro', error.message || 'Não foi possível salvar a foto')
  }
}

const toggleMirror = () => {
  isMirrored.value = !isMirrored.value
}

onMounted(() => {
  if (props.show) {
    startCamera()
  }
})

onUnmounted(() => {
  stopCamera()
})

watch(() => props.show, (newValue) => {
  if (newValue) {
    startCamera()
  } else {
    stopCamera()
  }
})
</script>

<style scoped>
.video-container {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
}

video, img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}
</style> 