<script setup>
import { computed, ref, watch, onUnmounted } from 'vue'
import { folderDisplayColor, folderCountBadgeStyle, folderTintStyles } from '../constants/galleryFolderColors.js'

const props = defineProps({
  folders: { type: Array, default: () => [] },
  photos: { type: Array, default: () => [] },
  expandedBranches: { type: Object, required: true },
  selectedFilename: { type: String, default: null },
  folderDropTargetId: { type: String, default: null },
  isDraggingPhotoToFolder: { type: Boolean, default: false },
  folderDragFilename: { type: String, default: null },
  bulkSelectMode: { type: Boolean, default: false },
  bulkFolderId: { type: String, default: null },
  reorderMode: { type: Boolean, default: false },
  reorderFolderId: { type: String, default: null },
  reorderInsertAt: { type: Number, default: null },
  reorderInsertFolderId: { type: String, default: null },
  isInReorderDragBlock: { type: Function, required: true },
  asModal: { type: Boolean, default: false },
  duplicatingFilename: { type: String, default: null },
  isBulkSelected: { type: Function, required: true },
  isReorderSelected: { type: Function, required: true },
  canDragPhotoToFolder: { type: Function, required: true },
  canDragPhotoToCanvas: { type: Function, required: true },
  folderPhotoCount: { type: Function, required: true },
  photoFolderId: { type: Function, required: true },
  liveThumbUrls: { type: Object, default: () => ({}) }
})

const emit = defineEmits([
  'toggle-branch',
  'expand-all',
  'collapse-all',
  'select-folder',
  'folder-drag-over',
  'folder-drag-leave',
  'folder-drop',
  'rename-folder',
  'delete-folder',
  'thumbnail-click',
  'bulk-toggle',
  'reorder-toggle',
  'folder-photo-drag-start',
  'folder-photo-drag-end',
  'reorder-pointer-down',
  'use-in-form',
  'duplicate',
  'delete-photo'
])

const treeListRef = ref(null)
const scrollRaf = ref(null)
const scrollVelocity = ref(0)

const SCROLL_EDGE = 52
const SCROLL_MAX_SPEED = 18

const isExpanded = (folderId) => props.expandedBranches.has(folderId)

const photosInFolder = (folderId) =>
  props.photos.filter((photo) => props.photoFolderId(photo) === folderId)

const allExpanded = computed(
  () =>
    props.folders.length > 0 &&
    props.folders.every((folder) => props.expandedBranches.has(folder.id))
)

const anyExpanded = computed(() => props.expandedBranches.size > 0)

const photoThumbnailUrl = (photo) => {
  if (!photo?.url) {
    return ''
  }
  return props.liveThumbUrls[photo.filename] || photo.url
}

const isReorderActiveInFolder = (folderId) =>
  props.reorderMode && props.reorderFolderId === folderId

const isBulkActiveInFolder = (folderId) =>
  props.bulkSelectMode && props.bulkFolderId === folderId

const isThumbnailDraggable = (photo) =>
  props.canDragPhotoToCanvas(photo) ||
  (props.canDragPhotoToFolder(photo) && !props.bulkSelectMode && !props.reorderMode)

const folderAccentStyle = (folder) => ({
  color: folderDisplayColor(folder)
})

const folderButtonStyle = (folder) => {
  if (isReorderActiveInFolder(folder.id) || isBulkActiveInFolder(folder.id)) {
    return {}
  }

  if (props.folderDropTargetId === folder.id) {
    return folderTintStyles(folder, { dropTarget: true })
  }

  if (isExpanded(folder.id)) {
    return folderTintStyles(folder, { active: true })
  }

  return folderTintStyles(folder)
}

const onPhotoPointerDown = (folderId, index, event) => {
  if (!isReorderActiveInFolder(folderId)) {
    return
  }

  const photo = photosInFolder(folderId)[index]
  if (props.canDragPhotoToCanvas(photo)) {
    return
  }

  emit('reorder-pointer-down', folderId, index, event)
}

const stopAutoScroll = () => {
  if (scrollRaf.value != null) {
    cancelAnimationFrame(scrollRaf.value)
    scrollRaf.value = null
  }
  scrollVelocity.value = 0
}

const autoScrollStep = () => {
  const el = treeListRef.value
  if (!el || scrollVelocity.value === 0) {
    stopAutoScroll()
    return
  }

  el.scrollTop += scrollVelocity.value
  scrollRaf.value = requestAnimationFrame(autoScrollStep)
}

const updateAutoScroll = (clientY) => {
  const el = treeListRef.value
  if (!el || !props.isDraggingPhotoToFolder) {
    stopAutoScroll()
    return
  }

  const rect = el.getBoundingClientRect()
  let velocity = 0

  if (clientY < rect.top + SCROLL_EDGE) {
    velocity = -Math.min(
      SCROLL_MAX_SPEED,
      Math.max(4, (rect.top + SCROLL_EDGE - clientY) / 2.5)
    )
  } else if (clientY > rect.bottom - SCROLL_EDGE) {
    velocity = Math.min(
      SCROLL_MAX_SPEED,
      Math.max(4, (clientY - (rect.bottom - SCROLL_EDGE)) / 2.5)
    )
  }

  scrollVelocity.value = velocity

  if (velocity !== 0 && scrollRaf.value == null) {
    scrollRaf.value = requestAnimationFrame(autoScrollStep)
  } else if (velocity === 0) {
    stopAutoScroll()
  }
}

const onDocumentDragOver = (event) => {
  if (!props.isDraggingPhotoToFolder) {
    return
  }
  updateAutoScroll(event.clientY)
}

const onTreeDragOver = (event) => {
  if (props.isDraggingPhotoToFolder) {
    event.preventDefault()
    updateAutoScroll(event.clientY)
  }
}

watch(
  () => props.isDraggingPhotoToFolder,
  (active) => {
    if (active) {
      document.addEventListener('dragover', onDocumentDragOver)
      return
    }
    document.removeEventListener('dragover', onDocumentDragOver)
    stopAutoScroll()
  }
)

onUnmounted(() => {
  document.removeEventListener('dragover', onDocumentDragOver)
  stopAutoScroll()
})
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col">
    <div class="shrink-0 border-b border-gray-100 px-2 py-2">
      <div class="flex items-center justify-between gap-2">
        <p class="text-xs font-medium text-gray-500">Galeria</p>
        <div class="flex items-center gap-1">
          <button
            v-if="!allExpanded"
            type="button"
            class="rounded px-1.5 py-0.5 text-[10px] font-medium text-gray-600 hover:bg-gray-100"
            @click="emit('expand-all')"
          >
            Expandir tudo
          </button>
          <button
            v-else-if="anyExpanded"
            type="button"
            class="rounded px-1.5 py-0.5 text-[10px] font-medium text-gray-600 hover:bg-gray-100"
            @click="emit('collapse-all')"
          >
            Recolher tudo
          </button>
        </div>
      </div>
    </div>

    <ul
      ref="treeListRef"
      class="min-h-0 flex-1 space-y-1 overflow-y-auto p-2"
      :class="{ 'gallery-tree--dragging': isDraggingPhotoToFolder }"
      @dragover="onTreeDragOver"
    >
      <li
        v-for="folder in folders"
        :key="folder.id"
        class="gallery-tree-branch"
        @dragenter="emit('folder-drag-over', folder.id, $event)"
        @dragover="emit('folder-drag-over', folder.id, $event)"
        @dragleave="emit('folder-drag-leave', folder.id, $event)"
        @drop="emit('folder-drop', folder.id, $event)"
      >
        <div class="group flex items-center gap-0.5">
          <button
            type="button"
            class="gallery-tree-chevron"
            :class="{ 'gallery-tree-chevron--expanded': isExpanded(folder.id) }"
            :style="isExpanded(folder.id) ? { color: folderDisplayColor(folder) } : undefined"
            :title="isExpanded(folder.id) ? 'Recolher pasta' : 'Expandir pasta'"
            @click="emit('toggle-branch', folder.id)"
          >
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
          <button
            type="button"
            class="gallery-folder-btn min-w-0 flex-1"
            :style="folderButtonStyle(folder)"
            :class="{
              'gallery-folder-btn--reorder-target': isReorderActiveInFolder(folder.id),
              'gallery-folder-btn--bulk-target': isBulkActiveInFolder(folder.id)
            }"
            :title="
              reorderMode
                ? 'Seleccionar pasta para ordenar'
                : bulkSelectMode
                  ? 'Seleccionar pasta para eliminar'
                  : 'Abrir pasta'
            "
            @click="emit('select-folder', folder.id)"
          >
            <svg
              class="gallery-folder-icon"
              :style="folderAccentStyle(folder)"
              fill="currentColor"
              fill-opacity="0.18"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"
              />
            </svg>
            <span class="min-w-0 flex-1 truncate text-left">{{ folder.name }}</span>
            <span
              class="gallery-folder-count"
              :style="folderCountBadgeStyle(folder, { active: isExpanded(folder.id) })"
            >{{ folderPhotoCount(folder.id) }}</span>
          </button>
          <div
            v-if="!folder.system"
            class="flex shrink-0 items-center gap-0.5 pr-0.5 opacity-100 sm:opacity-0 sm:group-hover:opacity-100"
          >
            <button
              type="button"
              class="rounded-md p-1 text-gray-500 hover:bg-white hover:text-gray-700"
              title="Renomear pasta"
              @click.stop="emit('rename-folder', folder)"
            >
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                />
              </svg>
            </button>
            <button
              type="button"
              class="rounded-md p-1 text-gray-500 hover:bg-white hover:text-red-600"
              title="Eliminar pasta"
              @click.stop="emit('delete-folder', folder)"
            >
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                />
              </svg>
            </button>
          </div>
        </div>

        <ul
          v-if="isExpanded(folder.id)"
          class="gallery-tree-children"
          :data-reorder-folder="folder.id"
        >
          <li
            v-if="photosInFolder(folder.id).length === 0"
            class="gallery-tree-empty"
          >
            Pasta vazia
          </li>
          <template
            v-for="(photo, index) in photosInFolder(folder.id)"
            :key="photo.filename"
          >
            <li
              v-if="isReorderActiveInFolder(folder.id) && reorderInsertFolderId === folder.id && reorderInsertAt === index"
              class="pointer-events-none flex items-center gap-1 py-0.5"
              aria-hidden="true"
            >
              <span class="h-1 flex-1 rounded-full bg-violet-500 shadow-sm" />
            </li>
            <li
              :data-reorder-item="isReorderActiveInFolder(folder.id) ? '' : null"
              class="gallery-tree-photo"
              :class="{
                'opacity-45':
                  (isReorderActiveInFolder(folder.id) && isInReorderDragBlock(photo.filename)) ||
                  (isDraggingPhotoToFolder && folderDragFilename === photo.filename)
              }"
            >
            <div
              role="button"
              tabindex="0"
              class="group relative w-full rounded-lg border-2 text-left transition select-none"
              :title="photo.filename"
              :class="[
                isBulkActiveInFolder(folder.id) && isBulkSelected(photo.filename)
                  ? 'border-red-400 ring-2 ring-red-200'
                  : isReorderActiveInFolder(folder.id) && isReorderSelected(photo.filename)
                    ? 'border-violet-400 ring-2 ring-violet-200'
                    : selectedFilename === photo.filename
                      ? 'border-blue-500 ring-2 ring-blue-200'
                      : photo.is_blank_canvas
                        ? 'border-sky-400 hover:border-sky-500'
                        : 'border-gray-200 hover:border-gray-300',
                isReorderActiveInFolder(folder.id) || isThumbnailDraggable(photo)
                  ? 'cursor-grab touch-none active:cursor-grabbing'
                  : 'cursor-pointer'
              ]"
              :draggable="isThumbnailDraggable(photo)"
              @click="emit('thumbnail-click', photo)"
              @keydown.enter.prevent="emit('thumbnail-click', photo)"
              @keydown.space.prevent="emit('thumbnail-click', photo)"
              @pointerdown="onPhotoPointerDown(folder.id, index, $event)"
              @dragstart="emit('folder-photo-drag-start', $event, photo)"
              @dragend="emit('folder-photo-drag-end')"
            >
              <label
                v-if="isBulkActiveInFolder(folder.id)"
                class="pointer-events-auto absolute left-1.5 top-1.5 z-10 flex h-5 w-5 cursor-pointer items-center justify-center rounded bg-white/95 shadow ring-1 ring-gray-200"
                @click.stop
                @mousedown.stop
                @dragstart.prevent
              >
                <input
                  type="checkbox"
                  class="h-3.5 w-3.5 rounded border-gray-300 text-red-600 focus:ring-red-500"
                  :checked="isBulkSelected(photo.filename)"
                  @change="emit('bulk-toggle', photo.filename)"
                />
              </label>
              <label
                v-else-if="isReorderActiveInFolder(folder.id)"
                class="pointer-events-auto absolute left-1.5 top-1.5 z-10 flex h-5 w-5 cursor-pointer items-center justify-center rounded bg-white/95 shadow ring-1 ring-violet-200"
                @click.stop
                @mousedown.stop
                @dragstart.prevent
              >
                <input
                  type="checkbox"
                  class="h-3.5 w-3.5 rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                  :checked="isReorderSelected(photo.filename)"
                  @change="emit('reorder-toggle', photo.filename)"
                />
              </label>
              <div
                class="relative aspect-[4/3] w-full overflow-hidden"
                :class="photo.is_blank_canvas ? 'thumbnail-checker' : 'bg-gray-100'"
              >
                <img
                  :src="photoThumbnailUrl(photo)"
                  :alt="photo.filename"
                  class="pointer-events-none h-full w-full object-cover"
                  :class="{ 'ring-2 ring-inset ring-sky-400/80': photo.is_blank_canvas }"
                  loading="lazy"
                  draggable="false"
                />
              </div>
              <p
                class="truncate border-t border-gray-100 bg-gray-50 px-2 py-1 text-[10px] font-medium text-gray-600"
                :title="photo.filename"
              >
                {{ photo.filename }}
              </p>
              <div
                v-if="!isBulkActiveInFolder(folder.id) && !isReorderActiveInFolder(folder.id)"
                class="pointer-events-none absolute inset-x-0 top-0 z-10 flex justify-end gap-1 p-1.5 opacity-0 transition-opacity group-hover:opacity-100"
              >
                <button
                  v-if="asModal"
                  type="button"
                  title="Usar no formulário"
                  class="pointer-events-auto rounded-full bg-black/55 p-1 text-emerald-300 hover:bg-emerald-600/90 hover:text-white"
                  @click.stop="emit('use-in-form', photo)"
                >
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                </button>
                <button
                  type="button"
                  title="Duplicar"
                  :disabled="duplicatingFilename === photo.filename"
                  class="pointer-events-auto rounded-full bg-black/55 p-1 text-white hover:bg-black/75 disabled:opacity-40"
                  @click.stop="emit('duplicate', photo)"
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
                  @click.stop="emit('delete-photo', photo)"
                >
                  <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path
                      fill-rule="evenodd"
                      d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                      clip-rule="evenodd"
                    />
                  </svg>
                </button>
              </div>
            </div>
            </li>
          </template>
          <li
            v-if="isReorderActiveInFolder(folder.id) && reorderInsertFolderId === folder.id && reorderInsertAt === photosInFolder(folder.id).length"
            class="pointer-events-none flex items-center gap-1 py-0.5"
            aria-hidden="true"
          >
            <span class="h-1 flex-1 rounded-full bg-violet-500 shadow-sm" />
          </li>
        </ul>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.gallery-folder-btn {
  @apply flex w-full items-center gap-2 rounded-lg border-2 bg-white px-2 py-1.5 text-xs text-gray-700 transition hover:brightness-[0.98];
}

.gallery-folder-btn--reorder-target {
  @apply border-violet-400 bg-violet-50 font-medium text-violet-900 ring-2 ring-violet-200;
}

.gallery-folder-btn--bulk-target {
  @apply border-red-400 bg-red-50 font-medium text-red-900 ring-2 ring-red-200;
}

.gallery-folder-icon {
  @apply h-4 w-4 shrink-0;
}

.gallery-folder-count {
  @apply shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium;
}

.gallery-tree-chevron {
  @apply flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-gray-400 transition hover:bg-white hover:text-gray-600;
}

.gallery-tree-chevron--expanded {
  @apply rotate-90;
}

.gallery-tree-children {
  @apply ml-3 space-y-2 border-l-2 border-gray-200 py-1 pl-2;
}

.gallery-tree-empty {
  @apply py-1 pl-1 text-[10px] italic text-gray-400;
}

.gallery-tree-photo {
  @apply w-full;
}

.gallery-tree--dragging {
  scroll-behavior: auto;
}

.thumbnail-checker {
  background-color: #f3f4f6;
  background-image:
    linear-gradient(45deg, #e5e7eb 25%, transparent 25%),
    linear-gradient(-45deg, #e5e7eb 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, #e5e7eb 75%),
    linear-gradient(-45deg, transparent 75%, #e5e7eb 75%);
  background-size: 12px 12px;
  background-position: 0 0, 0 6px, 6px -6px, -6px 0;
}
</style>
