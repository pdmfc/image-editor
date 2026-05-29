<template>
  <div
    class="flex overflow-hidden bg-gray-100"
    :class="asModal ? 'h-full min-h-0' : 'h-screen'"
  >
    <div class="flex min-h-0 flex-1">
      <aside class="flex w-56 shrink-0 flex-col border-r border-gray-200 bg-white sm:w-64">
        <div class="flex flex-wrap justify-center gap-1.5 border-b border-gray-100 bg-gray-50/80 px-2 py-1.5">
          <label
            v-if="isActionEnabled('upload')"
            :for="fileInputId"
            :title="uploading ? 'A carregar…' : 'Carregar'"
            class="toolbar-icon-btn cursor-pointer"
            :class="{ 'pointer-events-none opacity-50': uploading }"
          >
            <svg class="toolbar-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
          </label>
          <input
            :id="fileInputId"
            type="file"
            accept="image/jpeg,image/png,image/gif,image/webp"
            class="hidden"
            :disabled="uploading"
            @change="handleFileUpload"
          />
          <button
            v-if="isActionEnabled('qrcode')"
            type="button"
            title="QR Code"
            class="toolbar-icon-btn"
            @click="getQRCode"
          >
            <svg class="toolbar-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7" rx="1" />
              <rect x="14" y="3" width="7" height="7" rx="1" />
              <rect x="3" y="14" width="7" height="7" rx="1" />
              <rect x="14" y="14" width="3" height="3" fill="currentColor" stroke="none" />
              <rect x="18" y="14" width="3" height="3" fill="currentColor" stroke="none" />
              <rect x="14" y="18" width="3" height="3" fill="currentColor" stroke="none" />
              <rect x="18" y="18" width="3" height="3" fill="currentColor" stroke="none" />
            </svg>
          </button>
          <button
            v-if="isActionEnabled('camera')"
            type="button"
            title="Tirar foto"
            class="toolbar-icon-btn"
            @click="showCamera = true"
          >
            <svg class="toolbar-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
              />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>
          <button
            v-if="isActionEnabled('canvas')"
            type="button"
            title="Nova folha em branco"
            class="toolbar-icon-btn disabled:opacity-50"
            :disabled="creatingBlank"
            @click="createBlankCanvas"
          >
            <svg class="toolbar-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
              />
            </svg>
          </button>
        </div>

        <div class="border-b border-gray-100 px-3 py-2">
          <p class="text-xs font-medium text-gray-500">
            Imagens
            <span v-if="!loading && photos.length">({{ photos.length }})</span>
          </p>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-2">
          <div v-if="loading" class="flex justify-center py-8">
            <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-blue-600"></div>
          </div>
          <p v-else-if="photos.length === 0" class="px-2 py-6 text-center text-sm text-gray-500">
            Nenhuma imagem. Carregue um ficheiro ou tire uma foto.
          </p>
          <ul v-else class="space-y-2">
            <li v-for="photo in photos" :key="photo.filename">
              <button
                type="button"
                class="group relative w-full overflow-hidden rounded-lg border-2 text-left transition"
                :class="[
                  selectedPhoto?.filename === photo.filename
                    ? 'border-blue-500 ring-2 ring-blue-200'
                    : 'border-transparent hover:border-gray-300',
                  canDragPhotoToCanvas(photo) ? 'cursor-grab active:cursor-grabbing' : ''
                ]"
                :draggable="canDragPhotoToCanvas(photo)"
                @click="selectPhoto(photo)"
                @dragstart="onThumbnailDragStart($event, photo)"
                @dragend="onThumbnailDragEnd"
              >
                <img
                  :src="photo.url"
                  :alt="photo.filename"
                  class="aspect-[4/3] w-full object-cover bg-gray-100 pointer-events-none"
                  :class="{ 'ring-1 ring-inset ring-sky-400/60': photo.is_blank_canvas }"
                  loading="lazy"
                  draggable="false"
                />
                <div
                  class="pointer-events-none absolute inset-x-0 top-0 flex justify-end gap-1 p-1.5 opacity-0 transition-opacity group-hover:opacity-100"
                >
                  <button
                    v-if="asModal"
                    type="button"
                    title="Usar no formulário"
                    class="pointer-events-auto rounded-full bg-black/55 p-1 text-emerald-300 hover:bg-emerald-600/90 hover:text-white"
                    @click.stop="usePhotoInForm(photo)"
                  >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                      />
                    </svg>
                  </button>
                  <button
                    type="button"
                    title="Duplicar"
                    :disabled="duplicatingFilename === photo.filename"
                    class="pointer-events-auto rounded-full bg-black/55 p-1 text-white hover:bg-black/75 disabled:opacity-40"
                    @click.stop="handleDuplicate(photo)"
                  >
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M7 9a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V9z" />
                      <path d="M5 3a2 2 0 00-2 2v6a2 2 0 002 2V5h8a2 2 0 00-2-2H5z" />
                    </svg>
                  </button>
                  <button
                    type="button"
                    title="Eliminar"
                    class="pointer-events-auto rounded-full bg-black/55 p-1 text-red-300 hover:bg-red-600/90 hover:text-white"
                    @click.stop="handleConfirmDelete(photo)"
                  >
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                  </button>
                </div>
                <div
                  class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-2 pb-1.5 pt-6 opacity-0 transition group-hover:opacity-100"
                >
                  <p class="truncate text-[10px] text-white" :title="photo.filename">{{ photo.filename }}</p>
                </div>
              </button>
            </li>
          </ul>
        </div>
      </aside>

      <main
        class="relative min-w-0 flex-1 bg-gray-900"
        :class="{ 'ring-2 ring-inset ring-sky-400/50': isDropTargetActive }"
        @dragover.prevent="onEditorDragOver"
        @dragleave="onEditorDragLeave"
        @drop.prevent="onEditorDrop"
      >
        <ImageEditor
          v-if="selectedPhoto"
          ref="imageEditorRef"
          :key="selectedPhoto.filename"
          embedded
          :user-id="userId"
          :image-url="selectedPhoto.url"
          :photo="selectedPhoto"
          @save="handleSaveEdit"
          @error="(msg) => showNotification('error', 'Erro', msg)"
        />
        <div
          v-else
          class="flex h-full flex-col items-center justify-center px-6 text-center text-gray-400"
        >
          <svg class="mb-4 h-16 w-16 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <p class="text-lg font-medium text-gray-300">Selecione uma imagem</p>
          <p class="mt-1 max-w-sm text-sm">Escolha uma miniatura à esquerda para abrir o editor nesta área.</p>
        </div>
      </main>
    </div>

    <CameraPopup
      v-if="showCamera"
      :show="showCamera"
      :user-id="userId"
      @close="showCamera = false"
      @photo-saved="onPhotoCaptured"
    />

    <QRCodePopup
      v-if="showQRCode"
      :show="showQRCode"
      :qr-code="qrCodeData"
      @close="closeQrCode"
    />

    <div class="pointer-events-none fixed inset-0 z-50 flex items-end justify-center px-4 py-6 sm:p-6">
      <div class="max-w-sm w-full">
        <Notification
          :show="notification.show"
          :type="notification.type"
          :title="notification.title"
          :message="notification.message"
          :show-actions="notification.showActions"
          :duration="notification.duration"
          :confirm-label="notification.confirmLabel"
          :cancel-label="notification.cancelLabel"
          @confirm="onNotificationConfirm"
          @cancel="onNotificationCancel"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, toRef } from 'vue'
import axios from 'axios'
import Notification from '../Components/Notification.vue'
import ImageEditor from '../Components/ImageEditor.vue'
import QRCodePopup from '../Components/QRCodePopup.vue'
import CameraPopup from '../Components/CameraPopup.vue'
import { useImageEditorRealtime } from '../composables/useImageEditorRealtime.js'
import { useImageEditorActionButtons } from '../composables/useImageEditorActionButtons.js'

const props = defineProps({
  asModal: {
    type: Boolean,
    default: false
  },
  initialFilename: {
    type: String,
    default: null
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

const enabledActionButtons = useImageEditorActionButtons(
  toRef(props, 'actionButtons')
)

const isActionEnabled = (action) => enabledActionButtons.value.has(action)

const emit = defineEmits(['close', 'saved', 'useInForm'])

const fileInputId = computed(() => (props.asModal ? 'camera-file-modal' : 'camera-file-page'))

const showCamera = ref(false)
const photos = ref([])
const loading = ref(false)
const selectedPhoto = ref(null)
const notification = ref({
  show: false,
  type: 'success',
  title: '',
  message: '',
  showActions: false,
  photoToDelete: null,
  action: null,
  confirmLabel: 'Confirmar',
  cancelLabel: 'Cancelar',
  duration: 5000
})
const pendingPhotoSwitch = ref(null)
const showQRCode = ref(false)
const qrCodeData = ref('')
const duplicatingFilename = ref(null)
const uploading = ref(false)
const creatingBlank = ref(false)
const imageEditorRef = ref(null)
const isDropTargetActive = ref(false)

const DRAG_PHOTO_MIME = 'application/x-image-editor-photo'

const requireUserId = () => {
  if (props.userId == null || props.userId === '') {
    showNotification('error', 'Erro', 'ID do utilizador em falta.')
    return false
  }
  return true
}

const userParams = () => ({ user_id: props.userId })

const isBlankCanvasSelected = computed(
  () => Boolean(selectedPhoto.value?.is_blank_canvas)
)

const canDragPhotoToCanvas = (photo) => {
  if (!isBlankCanvasSelected.value || !photo?.url) {
    return false
  }
  return photo.filename !== selectedPhoto.value?.filename
}

const onThumbnailDragStart = (event, photo) => {
  if (!canDragPhotoToCanvas(photo)) {
    event.preventDefault()
    return
  }
  event.dataTransfer.effectAllowed = 'copy'
  event.dataTransfer.setData(
    DRAG_PHOTO_MIME,
    JSON.stringify({ url: photo.url, filename: photo.filename })
  )
}

const onThumbnailDragEnd = () => {
  isDropTargetActive.value = false
}

const onEditorDragOver = (event) => {
  if (!isBlankCanvasSelected.value) {
    return
  }
  if (event.dataTransfer?.types?.includes(DRAG_PHOTO_MIME)) {
    event.dataTransfer.dropEffect = 'copy'
    isDropTargetActive.value = true
  }
}

const onEditorDragLeave = () => {
  isDropTargetActive.value = false
}

const onEditorDrop = async (event) => {
  isDropTargetActive.value = false
  if (!isBlankCanvasSelected.value || !imageEditorRef.value) {
    return
  }
  const raw = event.dataTransfer?.getData(DRAG_PHOTO_MIME)
  if (!raw) {
    return
  }
  try {
    const { url, filename } = JSON.parse(raw)
    if (!url || filename === selectedPhoto.value?.filename) {
      return
    }
    await imageEditorRef.value.addImageOverlayFromUrl(url)
  } catch (error) {
    console.error(error)
    showNotification('error', 'Erro', 'Não foi possível adicionar a imagem à folha')
  }
}

const createBlankCanvas = async () => {
  if (creatingBlank.value || !requireUserId()) {
    return
  }
  creatingBlank.value = true
  try {
    const response = await axios.post('/api/camera/blank', {
      width: 1600,
      height: 1200,
      ...userParams()
    })
    if (response.data?.success) {
      const fn = response.data.filename || response.data.photo?.filename
      await loadPhotos({ selectFilename: fn, autoSelectFirst: false })
      showNotification('success', 'Sucesso', 'Folha em branco criada')
    } else {
      throw new Error(response.data?.error || 'Falha ao criar folha')
    }
  } catch (error) {
    console.error(error)
    const msg = error.response?.data?.error || error.message || 'Erro ao criar folha em branco'
    showNotification('error', 'Erro', msg)
  } finally {
    creatingBlank.value = false
  }
}

const syncSelectedPhotoFromList = (filename) => {
  if (!filename) {
    selectedPhoto.value = null
    return
  }
  const found = photos.value.find((p) => p.filename === filename)
  selectedPhoto.value = found || null
}

const editorHasUnsavedChanges = () =>
  Boolean(imageEditorRef.value?.hasUnsavedChanges?.())

const showSwitchDiscardWarning = () => {
  showNotification(
    'warning',
    'Alterações não guardadas',
    'Tem alterações por guardar nesta imagem. Se continuar, perde o que ainda não guardou.',
    true,
    null,
    0,
    'switch',
    'Descartar e continuar',
    'Ficar nesta imagem'
  )
}

const applyPhotoSelection = (photo) => {
  if (!photo) {
    selectedPhoto.value = null
    pendingPhotoSwitch.value = null
    return true
  }
  if (selectedPhoto.value?.filename === photo.filename) {
    return true
  }
  if (selectedPhoto.value && editorHasUnsavedChanges()) {
    pendingPhotoSwitch.value = photo
    showSwitchDiscardWarning()
    return false
  }
  selectedPhoto.value = photo
  pendingPhotoSwitch.value = null
  return true
}

const selectPhoto = (photo) => {
  applyPhotoSelection(photo)
}

const showNotification = (
  type,
  title,
  message,
  showActions = false,
  photoToDelete = null,
  duration = 3000,
  action = null,
  confirmLabel = 'Confirmar',
  cancelLabel = 'Cancelar'
) => {
  notification.value.show = false

  setTimeout(() => {
    notification.value = {
      show: true,
      type,
      title,
      message,
      showActions,
      photoToDelete: action === 'delete' ? photoToDelete : null,
      action,
      confirmLabel,
      cancelLabel,
      duration
    }
  }, 100)
}

const onNotificationConfirm = () => {
  const action = notification.value.action
  notification.value.show = false
  if (action === 'switch') {
    if (pendingPhotoSwitch.value) {
      selectedPhoto.value = pendingPhotoSwitch.value
      pendingPhotoSwitch.value = null
    }
    return
  }
  if (action === 'delete') {
    confirmDelete(notification.value.photoToDelete)
  }
}

const onNotificationCancel = () => {
  notification.value.show = false
  if (notification.value.action === 'switch') {
    pendingPhotoSwitch.value = null
  }
}

const loadPhotos = async (options = {}) => {
  if (!requireUserId()) {
    return false
  }
  const silent = Boolean(options.silent)
  if (!silent) {
    loading.value = true
  }
  try {
    const response = await axios.get('/api/camera/photos', { params: userParams() })

    if (response.data?.error) {
      throw new Error(response.data.error)
    }

    photos.value = response.data.photos ?? []

    if (options.selectFilename) {
      const found = photos.value.find((p) => p.filename === options.selectFilename)
      if (found) {
        applyPhotoSelection(found)
      }
    } else if (selectedPhoto.value) {
      syncSelectedPhotoFromList(selectedPhoto.value.filename)
    } else if (photos.value.length > 0 && options.autoSelectFirst !== false) {
      selectedPhoto.value = photos.value[0]
    }
    return true
  } catch (error) {
    console.error('Erro ao carregar fotos:', error)
    if (!silent) {
      const msg =
        error.response?.data?.error ||
        error.response?.data?.message ||
        error.message ||
        'Erro ao carregar fotos'
      showNotification('error', 'Erro', msg)
    }
    return false
  } finally {
    if (!silent) {
      loading.value = false
    }
  }
}

const handlePhotosUploadedFromMobile = async (payload) => {
  const previous = new Set(photos.value.map((p) => p.filename))
  const newNames = new Set(payload?.new_filenames ?? [])

  if (Array.isArray(payload?.photos) && payload.photos.length > 0) {
    photos.value = payload.photos
  } else {
    await loadPhotos({ silent: true, autoSelectFirst: false })
  }

  const newcomers = photos.value.filter((p) =>
    newNames.size > 0 ? newNames.has(p.filename) : !previous.has(p.filename)
  )

  if (newcomers.length === 0) {
    return
  }

  const newest = newcomers.reduce((best, p) =>
    (p.timestamp ?? 0) > (best.timestamp ?? 0) ? p : best
  )

  applyPhotoSelection(newest)

  const label =
    newcomers.length === 1
      ? 'Nova foto do telemóvel — já pode editar.'
      : `${newcomers.length} fotos do telemóvel — a mais recente foi seleccionada.`

  showNotification('success', 'Fotos recebidas', label)
}

useImageEditorRealtime(toRef(props, 'userId'), {
  onPhotosUploaded: handlePhotosUploadedFromMobile
})

const handleFileUpload = async (event) => {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file || !requireUserId()) {
    return
  }
  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('photo', file)
    formData.append('user_id', String(props.userId))
    const response = await axios.post('/api/camera/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    if (response.data?.success) {
      const fn = response.data.filename || response.data.photo?.filename
      await loadPhotos({ selectFilename: fn, autoSelectFirst: false })
      showNotification('success', 'Sucesso', 'Imagem carregada com sucesso')
    } else {
      throw new Error(response.data?.error || 'Falha no upload')
    }
  } catch (error) {
    console.error('Erro ao carregar imagem:', error)
    const msg = error.response?.data?.error || error.message || 'Erro ao carregar a imagem'
    showNotification('error', 'Erro', msg)
  } finally {
    uploading.value = false
  }
}

const onPhotoCaptured = async () => {
  showCamera.value = false
  await loadPhotos({ autoSelectFirst: !selectedPhoto.value })
}

const handleConfirmDelete = (photo) => {
  showNotification(
    'warning',
    'Confirmar exclusão',
    'Tem a certeza de que deseja eliminar esta foto?',
    true,
    photo,
    0,
    'delete'
  )
}

const confirmDelete = async (photo) => {
  if (!requireUserId()) {
    return
  }
  const wasSelected = selectedPhoto.value?.filename === photo.filename
  try {
    await axios.delete('/api/camera/photos', {
      data: { filename: photo.filename, ...userParams() }
    })
    await loadPhotos({ autoSelectFirst: wasSelected })
    if (wasSelected && photos.value.length > 0) {
      selectedPhoto.value = photos.value[0]
    } else if (wasSelected) {
      selectedPhoto.value = null
    }
    showNotification('success', 'Sucesso', 'Foto excluída com sucesso')
  } catch (error) {
    console.error('Erro ao excluir foto:', error)
    showNotification('error', 'Erro', 'Erro ao excluir foto')
  }
}

const getQRCode = async () => {
  if (props.userId == null || props.userId === '') {
    showNotification('error', 'Erro', 'ID do utilizador em falta.')
    return
  }
  try {
    const response = await axios.post('/api/camera/qrcode', userParams())
    qrCodeData.value = response.data?.qr_image || response.data?.svg || ''
    showQRCode.value = true
  } catch (error) {
    console.error('Erro ao obter QR code:', error)
    const msg = error.response?.data?.error || error.response?.data?.message || 'Erro ao obter QR code.'
    showNotification('error', 'Erro', msg)
  }
}

const handleDuplicate = async (photo) => {
  if (duplicatingFilename.value || !requireUserId()) {
    return
  }
  duplicatingFilename.value = photo.filename
  try {
    const response = await axios.post('/api/camera/photos/duplicate', {
      filename: photo.filename,
      ...userParams()
    })
    if (response.data?.success) {
      const fn = response.data.filename
      await loadPhotos({ selectFilename: fn, autoSelectFirst: false })
      showNotification('success', 'Sucesso', 'Imagem duplicada com sucesso')
    } else {
      throw new Error(response.data?.error || 'Falha ao duplicar')
    }
  } catch (error) {
    console.error('Erro ao duplicar foto:', error)
    const msg = error.response?.data?.error || error.message || 'Erro ao duplicar a imagem'
    showNotification('error', 'Erro', msg)
  } finally {
    duplicatingFilename.value = null
  }
}

const photoUrlWithCacheBust = (photo) => {
  if (!photo?.url) {
    return ''
  }
  const version =
    photo.timestamp != null ? String(photo.timestamp) : String(Date.now())
  const sep = photo.url.includes('?') ? '&' : '?'
  return `${photo.url}${sep}v=${version}`
}

const usePhotoInForm = async (photo) => {
  if (!photo?.filename || !photo?.url) {
    showNotification('error', 'Erro', 'Imagem inválida')
    return
  }
  try {
    await loadPhotos({
      selectFilename: photo.filename,
      autoSelectFirst: false
    })
  } catch (error) {
    console.error('Erro ao atualizar foto para o formulário:', error)
  }
  const fresh =
    photos.value.find((p) => p.filename === photo.filename) ||
    selectedPhoto.value ||
    photo
  emit('useInForm', {
    filename: fresh.filename,
    url: photoUrlWithCacheBust(fresh),
    is_blank_canvas: Boolean(photo.is_blank_canvas)
  })
  showNotification('success', 'Formulário', 'Imagem pronta para usar no formulário')
}

const handleSaveEdit = async (payload) => {
  try {
    const keepFilename =
      payload && typeof payload === 'object' ? payload.filename : null
    await loadPhotos({
      selectFilename: keepFilename || selectedPhoto.value?.filename,
      autoSelectFirst: false
    })
    const fn = keepFilename || selectedPhoto.value?.filename
    const photo = fn ? photos.value.find((p) => p.filename === fn) : selectedPhoto.value
    if (photo && !props.asModal) {
      emit('saved', {
        filename: photo.filename,
        url: photo.url,
        saveMode: payload?.saveMode,
        is_blank_canvas: Boolean(photo.is_blank_canvas)
      })
    }
    const isCopy =
      payload &&
      typeof payload === 'object' &&
      payload.saveMode === 'copy'
    showNotification(
      'success',
      'Sucesso',
      isCopy
        ? 'Nova imagem criada; o ficheiro original mantém-se.'
        : 'Foto atualizada com sucesso!'
    )
  } catch (error) {
    console.error('Erro ao atualizar a foto:', error)
    showNotification('error', 'Erro', error.message || 'Não foi possível atualizar a foto')
  }
}

const closeQrCode = () => {
  showQRCode.value = false
}

onMounted(() => {
  loadPhotos({
    selectFilename: props.initialFilename || undefined,
    autoSelectFirst: !props.initialFilename
  })
})
</script>

<style scoped>
/* Estilo da barra de edição, tamanho compacto para a barra lateral */
.toolbar-icon-btn {
  @apply inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-black/50 text-white transition hover:bg-black/75 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-1 focus:ring-offset-gray-100 disabled:pointer-events-none;
}

.toolbar-icon-svg {
  @apply h-[18px] w-[18px];
}
</style>
