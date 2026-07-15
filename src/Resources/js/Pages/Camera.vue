<template>
  <div
    v-editor-tooltip-root
    class="flex overflow-hidden bg-gray-100"
    :class="asModal ? 'h-full min-h-0' : 'h-screen'"
  >
    <EditorTooltipLayer />
    <div class="flex min-h-0 flex-1">
      <aside class="flex min-h-0 w-56 shrink-0 flex-col border-r border-gray-200 bg-white sm:w-64">
        <div class="flex flex-wrap justify-center gap-1.5 border-b border-gray-100 bg-gray-50/80 px-2 py-1.5">
          <label
            v-if="isActionEnabled('upload')"
            :for="fileInputId"
            :title="uploadTitle"
            class="toolbar-icon-btn cursor-pointer"
            :class="{ 'pointer-events-none opacity-50': uploading || isGalleryAtLimit }"
          >
            <svg class="toolbar-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
          </label>
          <input
            :id="fileInputId"
            type="file"
            accept="image/jpeg,image/png,image/gif,image/webp"
            multiple
            class="hidden"
            :disabled="uploading || isGalleryAtLimit"
            @change="handleFileUpload"
          />
          <button
            v-if="isActionEnabled('qrcode')"
            type="button"
            title="QR Code"
            class="toolbar-icon-btn"
            :class="{ 'pointer-events-none opacity-50': isGalleryAtLimit }"
            :disabled="isGalleryAtLimit"
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
            :class="{ 'pointer-events-none opacity-50': isGalleryAtLimit }"
            :disabled="isGalleryAtLimit"
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
            :title="blankCanvasTitle"
            class="toolbar-icon-btn disabled:opacity-50"
            :disabled="creatingBlank || isGalleryAtLimit"
            @click="createBlankCanvas"
          >
            <svg class="toolbar-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 13h6m-3-3v6M6 4h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2z"
              />
            </svg>
          </button>
        </div>

        <div
          v-if="galleryFoldersEnabled && folders.length > 0"
          class="border-b border-gray-100 bg-white px-2 py-2"
        >
          <label class="block">
            <span class="mb-1 block px-0.5 text-[10px] font-medium text-gray-500">
              Pasta para novas imagens
            </span>
            <select
              v-model="newPhotoFolderId"
              class="w-full rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:opacity-50"
              :disabled="uploading || creatingBlank"
            >
              <option v-for="folder in folders" :key="folder.id" :value="folder.id">
                {{ folder.name }}
              </option>
            </select>
          </label>
        </div>

        <template v-if="galleryFoldersEnabled">
          <div class="flex min-h-0 flex-1 flex-col">
            <div class="shrink-0 border-b border-gray-100 bg-gray-50/80 px-2 py-2.5">
              <div class="mb-2 flex items-center justify-between gap-2 px-1">
                <p class="text-xs font-medium text-gray-500">Pastas</p>
                <div class="flex items-center gap-0.5">
                  <button
                    type="button"
                    class="rounded-md p-1 text-gray-500 transition hover:bg-white hover:text-gray-700 disabled:opacity-40"
                    :class="{
                      'bg-white text-amber-600 ring-1 ring-amber-200': folderReorderMode
                    }"
                    :disabled="folderActionLoading || folders.length < 2"
                    :title="
                      folderReorderMode
                        ? 'Sair da ordenação de pastas'
                        : 'Ordenar pastas'
                    "
                    @click="toggleFolderReorderMode"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 6h16M4 10h16M4 14h16M4 18h16"
                      />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="rounded-md p-1 text-gray-500 transition hover:bg-white hover:text-gray-700 disabled:opacity-40"
                    :class="{
                      'bg-white text-blue-600 ring-1 ring-blue-200':
                        folderEditor.open && folderEditor.mode === 'create'
                    }"
                    :disabled="folderActionLoading || folderReorderMode"
                    :title="folderEditor.open && folderEditor.mode === 'create' ? 'Cancelar' : 'Nova pasta'"
                    @click="toggleFolderCreate"
                  >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      v-if="folderEditor.open && folderEditor.mode === 'create'"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M6 18L18 6M6 6l12 12"
                    />
                    <path
                      v-else
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 4v16m8-8H4"
                    />
                  </svg>
                  </button>
                </div>
              </div>

              <p
                v-if="folderReorderMode"
                class="mb-2 px-1 text-[10px] leading-snug text-amber-700"
              >
                Arraste as pastas para reordenar. A pasta Entrada fica sempre em primeiro.
                <span v-if="folderReorderSaving" class="text-amber-500"> A guardar…</span>
              </p>

              <div
                v-if="folderEditor.open"
                class="rounded-lg border-2 border-blue-200 bg-white p-2 shadow-sm"
              >
                <p class="mb-1.5 text-[10px] font-medium text-gray-500">
                  {{ folderEditor.mode === 'create' ? 'Nova pasta' : 'Editar pasta' }}
                </p>
                <input
                  ref="folderNameInputRef"
                  v-model="folderEditor.name"
                  type="text"
                  maxlength="80"
                  autocomplete="off"
                  class="w-full rounded-md border border-gray-200 px-2 py-1.5 text-xs text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                  :placeholder="folderEditor.mode === 'create' ? 'Nome da pasta' : 'Novo nome'"
                  :disabled="folderActionLoading"
                  @keydown.enter.prevent="submitFolderEditor"
                  @keydown.esc.prevent="closeFolderEditor"
                />
                <div class="mt-2">
                  <p class="mb-1 text-[10px] font-medium text-gray-500">Cor</p>
                  <div class="flex flex-wrap gap-1.5">
                    <button
                      v-for="color in GALLERY_FOLDER_COLORS"
                      :key="color"
                      type="button"
                      class="h-5 w-5 rounded-full border-2 transition hover:scale-110"
                      :class="
                        folderEditor.color === color
                          ? 'border-gray-900 ring-2 ring-blue-200'
                          : 'border-white shadow-sm'
                      "
                      :style="{ backgroundColor: color }"
                      :title="`Cor ${color}`"
                      :disabled="folderActionLoading"
                      @click="folderEditor.color = color"
                    />
                  </div>
                </div>
                <p v-if="folderEditor.error" class="mt-1 text-[10px] text-red-600">
                  {{ folderEditor.error }}
                </p>
                <div class="mt-2 flex justify-end gap-1.5">
                  <button
                    type="button"
                    class="rounded-md px-2 py-1 text-[10px] font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-40"
                    :disabled="folderActionLoading"
                    @click="closeFolderEditor"
                  >
                    Cancelar
                  </button>
                  <button
                    type="button"
                    class="rounded-md bg-blue-600 px-2 py-1 text-[10px] font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="folderActionLoading || !folderEditor.name.trim()"
                    @click="submitFolderEditor"
                  >
                    {{
                      folderActionLoading
                        ? 'A guardar…'
                        : folderEditor.mode === 'create'
                          ? 'Criar'
                          : 'Guardar'
                    }}
                  </button>
                </div>
              </div>
            </div>

            <div class="shrink-0 border-b border-gray-100 px-3 py-2">
              <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-gray-500">
                  Imagens
                  <span v-if="!loading && galleryCountLabel">({{ galleryCountLabel }})</span>
                </p>
                <div v-if="!loading && photos.length > 0" class="flex items-center gap-0.5">
                  <button
                    type="button"
                    class="rounded-md p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                    :class="{ 'bg-violet-50 text-violet-600 ring-1 ring-violet-200': reorderMode }"
                    :title="reorderMode ? 'Sair da ordenação' : 'Ordenar imagens dentro de cada pasta'"
                    @click="toggleReorderMode"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16"
                      />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="rounded-md p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                    :class="{ 'bg-blue-50 text-blue-600 ring-1 ring-blue-200': bulkSelectMode }"
                    :title="bulkSelectMode ? 'Sair da seleção' : 'Seleccionar imagens para eliminar'"
                    @click="toggleBulkSelectMode"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                      />
                    </svg>
                  </button>
                </div>
              </div>
              <div
                v-if="reorderMode"
                class="mt-2 space-y-1"
              >
                <p class="text-[10px] leading-snug text-violet-700">
                  A ordenar em <span class="font-semibold">{{ reorderFolderName }}</span>.
                  Clique no nome de outra pasta para mudar. Arraste as imagens; com 2 ou mais selecionadas, o grupo move-se em conjunto.
                  <span v-if="reorderSaving" class="text-violet-500"> A guardar…</span>
                </p>
                <div class="flex flex-wrap items-center gap-1.5">
                  <span class="text-[10px] font-medium text-violet-800">
                    {{ reorderSelectionCount }} para mover
                  </span>
                  <button
                    type="button"
                    class="rounded px-1.5 py-0.5 text-[10px] font-medium text-violet-800 hover:bg-violet-50"
                    @click="selectAllReorder"
                  >
                    Todas
                  </button>
                  <button
                    type="button"
                    class="rounded px-1.5 py-0.5 text-[10px] font-medium text-violet-800 hover:bg-violet-50"
                    :disabled="reorderSelectionCount === 0"
                    @click="clearReorderSelection"
                  >
                    Limpar
                  </button>
                </div>
              </div>
              <div v-if="bulkSelectMode" class="mt-2 space-y-1.5">
                <p class="text-[10px] leading-snug text-gray-600">
                  A seleccionar em <span class="font-semibold">{{ bulkFolderName }}</span>.
                  Clique no nome de outra pasta para mudar. Toque nas imagens e clique Eliminar.
                </p>
                <div class="flex flex-wrap items-center gap-1.5">
                  <span class="text-[10px] font-medium text-gray-600">
                    {{ bulkSelectedCount }} selecionada{{ bulkSelectedCount === 1 ? '' : 's' }}
                  </span>
                  <button
                    type="button"
                    class="rounded px-1.5 py-0.5 text-[10px] font-medium text-gray-600 hover:bg-gray-100"
                    @click="selectAllBulk"
                  >
                    Todas
                  </button>
                  <button
                    type="button"
                    class="rounded px-1.5 py-0.5 text-[10px] font-medium text-gray-600 hover:bg-gray-100"
                    :disabled="bulkSelectedCount === 0"
                    @click="clearBulkSelection"
                  >
                    Limpar
                  </button>
                  <button
                    type="button"
                    class="ml-auto rounded-md bg-red-600 px-2 py-0.5 text-[10px] font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="bulkSelectedCount === 0 || bulkDeleting"
                    @click="handleConfirmBulkDelete"
                  >
                    {{ bulkDeleting ? 'A eliminar…' : 'Eliminar' }}
                  </button>
                </div>
              </div>
            </div>

            <div v-if="loading" class="flex min-h-0 flex-1 justify-center py-8">
              <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-blue-600"></div>
            </div>
            <p
              v-else-if="photos.length === 0"
              class="px-4 py-6 text-center text-sm text-gray-500"
            >
              Nenhuma imagem. Carregue um ficheiro ou tire uma foto.
            </p>
            <template v-else>
            <GalleryFolderTree
              :folders="folders"
              :photos="photos"
              :expanded-branches="expandedFolderBranches"
              :selected-filename="selectedPhoto?.filename ?? null"
              :folder-drop-target-id="folderDropTargetId"
              :is-dragging-photo-to-folder="isDraggingPhotoToFolder"
              :folder-drag-filename="folderDragFilename"
              :bulk-select-mode="bulkSelectMode"
              :bulk-folder-id="bulkFolderId"
              :reorder-mode="reorderMode"
              :reorder-folder-id="reorderFolderId"
              :reorder-insert-at="reorderInsertAt"
              :reorder-insert-folder-id="reorderInsertFolderId"
              :is-in-reorder-drag-block="isInReorderDragBlock"
              :as-modal="asModal"
              :duplicating-filename="duplicatingFilename"
              :is-bulk-selected="isBulkSelected"
              :is-reorder-selected="isReorderSelected"
              :can-drag-photo-to-folder="canDragPhotoToFolder"
              :can-drag-photo-to-canvas="canDragPhotoToCanvas"
              :folder-photo-count="folderPhotoCount"
              :photo-folder-id="photoFolderId"
              :live-thumb-urls="galleryLiveThumbUrls"
              :folder-reorder-mode="folderReorderMode"
              :folder-reorder-saving="folderReorderSaving"
              @reorder-pointer-down="onFolderReorderPointerDown"
              @reorder-toggle="toggleReorderSelection"
              @toggle-branch="toggleFolderBranch"
              @expand-all="expandAllFolderBranches"
              @collapse-all="collapseAllFolderBranches"
              @select-folder="onTreeSelectFolder"
              @folder-drag-over="onFolderDragOver"
              @folder-drag-leave="onFolderDragLeave"
              @folder-drop="onFolderDrop"
              @rename-folder="openFolderEditor('rename', $event)"
              @delete-folder="confirmDeleteGalleryFolder"
              @thumbnail-click="onThumbnailClick"
              @bulk-toggle="toggleBulkSelection"
              @folder-photo-drag-start="(event, photo) => onGalleryThumbnailDragStart(-1, event, photo)"
              @folder-photo-drag-end="onGalleryThumbnailDragEnd"
              @use-in-form="usePhotoInForm"
              @duplicate="handleDuplicate"
              @delete-photo="handleConfirmDelete"
              @folder-reorder="handleFolderReorder"
            />
            </template>
          </div>
        </template>

        <template v-else>
        <div class="shrink-0 border-b border-gray-100 px-3 py-2">
          <div class="flex items-center justify-between gap-2">
            <p class="text-xs font-medium text-gray-500">
              Imagens
              <span v-if="!loading && galleryCountLabel">({{ galleryCountLabel }})</span>
            </p>
            <div v-if="!loading && displayPhotos.length > 0" class="flex items-center gap-0.5">
              <button
                v-if="canReorderGallery"
                type="button"
                class="rounded-md p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                :class="{ 'bg-violet-50 text-violet-600 ring-1 ring-violet-200': reorderMode }"
                :title="reorderMode ? 'Sair da ordenação' : 'Ordenar imagens por prioridade'"
                @click="toggleReorderMode"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"
                  />
                </svg>
              </button>
              <button
                type="button"
                class="rounded-md p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                :class="{ 'bg-blue-50 text-blue-600 ring-1 ring-blue-200': bulkSelectMode }"
                :title="bulkSelectMode ? 'Sair da seleção' : 'Seleccionar várias para eliminar'"
                @click="toggleBulkSelectMode"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                  />
                </svg>
              </button>
            </div>
          </div>
          <div
            v-if="reorderMode"
            class="mt-2 space-y-1"
          >
            <p class="text-[10px] leading-snug text-violet-700">
              Arraste para a linha roxa entre imagens. Com 2 ou mais selecionadas, o grupo move-se em conjunto.
              <span v-if="reorderSaving" class="text-violet-500"> A guardar…</span>
            </p>
            <div class="flex flex-wrap items-center gap-1.5">
              <span class="text-[10px] font-medium text-violet-800">
                {{ reorderSelectionCount }} para mover
              </span>
              <button
                type="button"
                class="rounded px-1.5 py-0.5 text-[10px] font-medium text-violet-800 hover:bg-violet-50"
                @click="selectAllReorder"
              >
                Todas
              </button>
              <button
                type="button"
                class="rounded px-1.5 py-0.5 text-[10px] font-medium text-violet-800 hover:bg-violet-50"
                :disabled="reorderSelectionCount === 0"
                @click="clearReorderSelection"
              >
                Limpar
              </button>
            </div>
          </div>
          <div
            v-if="bulkSelectMode"
            class="mt-2 space-y-1.5"
          >
            <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-[10px] font-medium text-gray-600">
              {{ bulkSelectedCount }} selecionada{{ bulkSelectedCount === 1 ? '' : 's' }}
            </span>
            <button
              type="button"
              class="rounded px-1.5 py-0.5 text-[10px] font-medium text-gray-600 hover:bg-gray-100"
              @click="selectAllBulk"
            >
              Todas
            </button>
            <button
              type="button"
              class="rounded px-1.5 py-0.5 text-[10px] font-medium text-gray-600 hover:bg-gray-100"
              :disabled="bulkSelectedCount === 0"
              @click="clearBulkSelection"
            >
              Limpar
            </button>
            <button
              type="button"
              class="ml-auto rounded-md bg-red-600 px-2 py-0.5 text-[10px] font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40"
              :disabled="bulkSelectedCount === 0 || bulkDeleting"
              @click="handleConfirmBulkDelete"
            >
              {{ bulkDeleting ? 'A eliminar…' : 'Eliminar' }}
            </button>
            </div>
          </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-2">
          <div v-if="loading" class="flex justify-center py-8">
            <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-blue-600"></div>
          </div>
          <p v-else-if="displayPhotos.length === 0" class="px-2 py-6 text-center text-sm text-gray-500">
            Nenhuma imagem. Carregue um ficheiro ou tire uma foto.
          </p>
          <ul
            v-else
            ref="galleryListRef"
            class="space-y-2"
          >
            <template v-for="(photo, index) in displayPhotos" :key="photo.filename">
              <li
                v-if="reorderMode && reorderInsertAt === index"
                class="pointer-events-none mx-1 flex items-center gap-1 py-0.5"
                aria-hidden="true"
              >
                <span class="h-1 flex-1 rounded-full bg-violet-500 shadow-sm" />
              </li>
              <li
                data-reorder-item
                class="rounded-lg transition"
                :class="{ 'opacity-45': isInReorderDragBlock(photo.filename) }"
              >
              <div
                role="button"
                tabindex="0"
                class="group relative w-full rounded-lg border-2 text-left transition select-none"
                :class="[
                  bulkSelectMode && isBulkSelected(photo.filename)
                    ? 'border-red-400 ring-2 ring-red-200'
                    : reorderMode && isReorderSelected(photo.filename)
                      ? 'border-violet-400 ring-2 ring-violet-200'
                      : selectedPhoto?.filename === photo.filename
                        ? 'border-blue-500 ring-2 ring-blue-200'
                        : photo.is_blank_canvas
                          ? 'border-sky-400 hover:border-sky-500'
                          : 'border-gray-200 hover:border-gray-300',
                  reorderMode || isThumbnailDraggable(photo)
                    ? 'cursor-grab active:cursor-grabbing touch-none'
                    : 'cursor-pointer'
                ]"
                :draggable="isThumbnailDraggable(photo)"
                @click="onThumbnailClick(photo)"
                @keydown.enter.prevent="onThumbnailClick(photo)"
                @keydown.space.prevent="onThumbnailClick(photo)"
                @pointerdown="onReorderPointerDown(index, $event)"
                @dragstart="onGalleryThumbnailDragStart(index, $event, photo)"
                @dragend="onGalleryThumbnailDragEnd"
              >
                <label
                  v-if="reorderMode"
                  class="pointer-events-auto absolute left-1.5 top-1.5 z-10 flex h-5 w-5 cursor-pointer items-center justify-center rounded bg-white/95 shadow ring-1 ring-violet-200"
                  @click.stop
                  @mousedown.stop
                  @dragstart.prevent
                >
                  <input
                    type="checkbox"
                    class="h-3.5 w-3.5 rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                    :checked="isReorderSelected(photo.filename)"
                    @change="toggleReorderSelection(photo.filename)"
                  />
                </label>
                <span
                  v-if="reorderMode"
                  class="pointer-events-none absolute right-1.5 top-1.5 z-10 rounded bg-violet-600/90 px-1.5 py-0.5 text-[9px] font-semibold text-white shadow"
                >
                  {{ index + 1 }}
                </span>
                <label
                  v-if="bulkSelectMode"
                  class="pointer-events-auto absolute left-1.5 top-1.5 z-10 flex h-5 w-5 cursor-pointer items-center justify-center rounded bg-white/95 shadow ring-1 ring-gray-200"
                  @click.stop
                >
                  <input
                    type="checkbox"
                    class="h-3.5 w-3.5 rounded border-gray-300 text-red-600 focus:ring-red-500"
                    :checked="isBulkSelected(photo.filename)"
                    @change="toggleBulkSelection(photo.filename)"
                  />
                </label>
                <div
                  class="relative aspect-[4/3] w-full overflow-hidden"
                  :class="photo.is_blank_canvas ? 'thumbnail-checker' : 'bg-gray-100'"
                >
                  <img
                    :src="photoUrlForGallery(photo)"
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
                  v-if="!bulkSelectMode && !reorderMode"
                  class="pointer-events-none absolute inset-x-0 top-0 z-10 flex justify-end gap-1 p-1.5 opacity-0 transition-opacity group-hover:opacity-100"
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
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
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
              </div>
              </li>
            </template>
            <li
              v-if="reorderMode && reorderInsertAt === displayPhotos.length"
              class="pointer-events-none mx-1 flex items-center gap-1 py-0.5"
              aria-hidden="true"
            >
              <span class="h-1 flex-1 rounded-full bg-violet-500 shadow-sm" />
            </li>
          </ul>
        </div>
        </template>
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
          :key="selectedPhotoEditorKey"
          embedded
          :user-id="userId"
          :image-url="selectedPhoto.url"
          :photo="selectedPhoto"
          :show-use-in-form="asModal"
          :gallery-index="selectedPhotoIndex"
          :gallery-total="displayPhotos.length"
          :gallery-folders-enabled="galleryFoldersEnabled"
          :gallery-folders="folders"
          @save="handleSaveEdit"
          @use-in-form="usePhotoInForm"
          @gallery-navigate="onGalleryNavigate"
          @preview-updated="onEditorPreviewUpdated"
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
      :max-files="qrMaxFiles"
      :max-upload-mb="galleryMaxUploadMb"
      @close="closeQrCode"
    />

    <Teleport to="body">
      <div
        v-if="reorderDragPreview.visible"
        class="reorder-drag-ghost pointer-events-none fixed z-[100001]"
        :style="{
          left: `${reorderDragPreview.x}px`,
          top: `${reorderDragPreview.y}px`
        }"
      >
        <div class="relative w-24 overflow-hidden rounded-lg border-2 border-violet-500 bg-white shadow-2xl ring-2 ring-violet-200/80">
          <img
            v-if="reorderDragPreview.imageUrl"
            :src="reorderDragPreview.imageUrl"
            alt=""
            class="aspect-[4/3] h-auto w-full object-cover"
            draggable="false"
          />
          <span
            v-if="reorderDragPreview.count > 1"
            class="absolute right-1 top-1 rounded-full bg-violet-600 px-1.5 py-0.5 text-[10px] font-bold text-white shadow"
          >
            {{ reorderDragPreview.count }}
          </span>
        </div>
      </div>
    </Teleport>

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
import { ref, computed, onMounted, toRef, nextTick } from 'vue'
import axios from '../http/client.js'
import Notification from '../Components/Notification.vue'
import EditorTooltipLayer from '../Components/EditorTooltipLayer.vue'
import ImageEditor from '../Components/ImageEditor.vue'
import { editorTooltipRoot } from '../composables/editorTooltip.js'

const vEditorTooltipRoot = editorTooltipRoot
import QRCodePopup from '../Components/QRCodePopup.vue'
import CameraPopup from '../Components/CameraPopup.vue'
import GalleryFolderTree from '../Components/GalleryFolderTree.vue'
import { useImageEditorRealtime } from '../composables/useImageEditorRealtime.js'
import { useImageEditorActionButtons } from '../composables/useImageEditorActionButtons.js'
import { useImageEditorGalleryFolders } from '../composables/useImageEditorGalleryFolders.js'
import { useImageEditorGalleryMaxImages } from '../composables/useImageEditorGalleryMaxImages.js'
import { useImageEditorGalleryMaxUploadMb } from '../composables/useImageEditorGalleryMaxUploadMb.js'
import {
  GALLERY_FOLDER_COLORS,
  defaultFolderColor,
  folderDisplayColor
} from '../constants/galleryFolderColors.js'

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
  },
  galleryFoldersEnabled: {
    type: Boolean,
    default: null
  },
  galleryMaxImages: {
    type: Number,
    default: null
  },
  galleryMaxUploadMb: {
    type: Number,
    default: null
  }
})

const enabledActionButtons = useImageEditorActionButtons(
  toRef(props, 'actionButtons')
)

const galleryFoldersEnabled = useImageEditorGalleryFolders(
  toRef(props, 'galleryFoldersEnabled')
)

const galleryMaxImages = useImageEditorGalleryMaxImages(
  toRef(props, 'galleryMaxImages')
)

const galleryMaxUploadMb = useImageEditorGalleryMaxUploadMb(
  toRef(props, 'galleryMaxUploadMb')
)

const galleryMaxUploadBytes = computed(
  () => galleryMaxUploadMb.value * 1024 * 1024
)

const isActionEnabled = (action) => enabledActionButtons.value.has(action)

const photoFolderId = (photo) => photo?.folder_id || 'entrada'

const expandedFolderBranches = ref(new Set())

const photosInFolder = (folderId) =>
  photos.value.filter((photo) => photoFolderId(photo) === folderId)

const toggleFolderBranch = (folderId) => {
  const next = new Set(expandedFolderBranches.value)
  const willExpand = !next.has(folderId)

  if (willExpand) {
    next.add(folderId)
    if (bulkSelectMode.value) {
      bulkFolderId.value = folderId
      bulkSelectedFilenames.value = []
    }
    if (reorderMode.value) {
      reorderFolderId.value = folderId
      reorderSelection.value = []
      resetReorderDragState()
    }
  } else {
    next.delete(folderId)
  }

  expandedFolderBranches.value = next
}

const openFolderBranch = (folderId) => {
  if (expandedFolderBranches.value.has(folderId)) {
    return
  }

  const next = new Set(expandedFolderBranches.value)
  next.add(folderId)
  expandedFolderBranches.value = next
}

const expandAllFolderBranches = () => {
  expandedFolderBranches.value = new Set(folders.value.map((folder) => folder.id))
}

const collapseAllFolderBranches = () => {
  expandedFolderBranches.value = new Set()
}

const pruneExpandedFolderBranches = () => {
  if (!galleryFoldersEnabled.value) {
    expandedFolderBranches.value = new Set()
    return
  }

  const validIds = new Set(folders.value.map((folder) => folder.id))
  expandedFolderBranches.value = new Set(
    [...expandedFolderBranches.value].filter((id) => validIds.has(id))
  )
}

/** Lista linear para navegação no editor (←/→), independente de pastas expandidas. */
const displayPhotos = computed(() => {
  if (!galleryFoldersEnabled.value) {
    return photos.value
  }

  const result = []
  for (const folder of folders.value) {
    result.push(...photosInFolder(folder.id))
  }
  return result
})

const canReorderGallery = computed(() => !galleryFoldersEnabled.value)

const reorderFolderId = ref(null)
const reorderInsertFolderId = ref(null)
const reorderDragFolderId = ref(null)

const folderNameById = (folderId) =>
  folders.value.find((item) => item.id === folderId)?.name ?? 'pasta'

const reorderFolderName = computed(() => folderNameById(reorderFolderId.value))

const bulkFolderId = ref(null)

const bulkFolderName = computed(() => folderNameById(bulkFolderId.value))

const resolveActiveFolderId = () => {
  if (galleryFoldersEnabled.value && folders.value.length > 0) {
    if (newPhotoFolderId.value && folders.value.some((folder) => folder.id === newPhotoFolderId.value)) {
      return newPhotoFolderId.value
    }
  }

  if (selectedPhoto.value) {
    return photoFolderId(selectedPhoto.value)
  }

  const expanded = [...expandedFolderBranches.value]

  if (expanded.length === 1) {
    return expanded[0]
  }

  if (expanded.length > 1) {
    const nonEntrada = expanded.find(
      (folderId) => folderId !== 'entrada' && folders.value.some((folder) => folder.id === folderId)
    )
    if (nonEntrada) {
      return nonEntrada
    }

    for (const folder of folders.value) {
      if (expandedFolderBranches.value.has(folder.id)) {
        return folder.id
      }
    }
  }

  return folders.value[0]?.id ?? 'entrada'
}

const folderPhotoCount = (folderId) =>
  photos.value.filter((photo) => photoFolderId(photo) === folderId).length

const emit = defineEmits(['close', 'saved', 'useInForm'])

const fileInputId = computed(() => (props.asModal ? 'camera-file-modal' : 'camera-file-page'))

const showCamera = ref(false)
const photos = ref([])

const galleryImageCount = computed(() => photos.value.length)

const galleryCountLabel = computed(() => {
  const count = galleryImageCount.value
  const max = galleryMaxImages.value
  if (max > 0) {
    return `${count}/${max}`
  }
  return count > 0 ? String(count) : ''
})

const galleryRemainingSlots = computed(() => {
  const max = galleryMaxImages.value
  if (max <= 0) {
    return Number.POSITIVE_INFINITY
  }
  return Math.max(0, max - galleryImageCount.value)
})

const qrMaxFiles = computed(() => {
  const remaining = galleryRemainingSlots.value
  return Number.isFinite(remaining) && remaining > 0 ? remaining : null
})

const isGalleryAtLimit = computed(
  () => galleryMaxImages.value > 0 && galleryImageCount.value >= galleryMaxImages.value
)

const galleryLimitMessage = computed(() => {
  const max = galleryMaxImages.value
  return max > 0
    ? `Limite da galeria atingido (${max} imagens). Elimine imagens para adicionar novas.`
    : ''
})

const folders = ref([])
const folderActionLoading = ref(false)
const folderEditor = ref({
  open: false,
  mode: 'create',
  folderId: null,
  name: '',
  color: GALLERY_FOLDER_COLORS[0],
  error: ''
})
const folderNameInputRef = ref(null)
const folderDropTargetId = ref(null)
const folderDragFilename = ref(null)
const isDraggingPhotoToFolder = ref(false)
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
const uploadProgress = ref({ current: 0, total: 0 })
const uploadTitle = computed(() => {
  if (isGalleryAtLimit.value) {
    return galleryLimitMessage.value
  }
  if (!uploading.value) {
    if (galleryFoldersEnabled.value && newPhotoFolderId.value) {
      return `Carregar imagens para ${folderNameById(newPhotoFolderId.value)} (${galleryMaxUploadMb.value} MB máx.)`
    }
    return `Carregar imagens (máx. ${galleryMaxUploadMb.value} MB cada)`
  }
  if (uploadProgress.value.total > 1) {
    return `A carregar ${uploadProgress.value.current}/${uploadProgress.value.total}…`
  }
  return 'A carregar…'
})
const newPhotoFolderId = ref('entrada')
const blankCanvasTitle = computed(() => {
  if (isGalleryAtLimit.value) {
    return galleryLimitMessage.value
  }
  if (galleryFoldersEnabled.value && newPhotoFolderId.value) {
    return `Nova folha em branco em ${folderNameById(newPhotoFolderId.value)}`
  }
  return 'Nova folha em branco'
})
const creatingBlank = ref(false)
const imageEditorRef = ref(null)
const galleryLiveThumbUrls = ref({})
const isDropTargetActive = ref(false)
const pendingCanvasDrag = ref(null)
const bulkSelectMode = ref(false)
const bulkSelectedFilenames = ref([])
const folderReorderMode = ref(false)
const folderReorderSaving = ref(false)
const bulkDeleting = ref(false)
const reorderMode = ref(false)
const galleryListRef = ref(null)
const reorderDragIndex = ref(null)
const reorderDragFilenames = ref([])
const reorderInsertAt = ref(null)
const reorderSaving = ref(false)
const reorderSelection = ref([])
const reorderPointerActive = ref(false)
const reorderSuppressClick = ref(false)
const reorderDragPreview = ref({
  visible: false,
  x: 0,
  y: 0,
  imageUrl: '',
  count: 1
})

const DRAG_PHOTO_MIME = 'application/x-image-editor-photo'
const FOLDER_DROP_MIME = 'application/x-image-editor-folder-move'

const requireUserId = () => {
  if (props.userId == null || props.userId === '') {
    showNotification('error', 'Erro', 'ID do utilizador em falta.')
    return false
  }
  return true
}

const userParams = () => ({ user_id: props.userId })

const newPhotoFolderParams = () =>
  galleryFoldersEnabled.value && newPhotoFolderId.value
    ? { folder_id: newPhotoFolderId.value }
    : {}

const ensureNewPhotoFolderId = () => {
  if (!galleryFoldersEnabled.value || folders.value.length === 0) {
    return
  }

  if (!folders.value.some((folder) => folder.id === newPhotoFolderId.value)) {
    newPhotoFolderId.value = folders.value[0]?.id ?? 'entrada'
  }
}

const isBlankCanvasSelected = computed(
  () => Boolean(selectedPhoto.value?.is_blank_canvas)
)

const canDragPhotoToCanvas = (photo) => {
  if (!isBlankCanvasSelected.value || !photo?.url) {
    return false
  }
  return photo.filename !== selectedPhoto.value?.filename
}

const onThumbnailDragEnd = () => {
  isDropTargetActive.value = false
}

const canDragPhotoToFolder = (photo) =>
  galleryFoldersEnabled.value &&
  Boolean(photo?.filename) &&
  !bulkSelectMode.value &&
  !reorderMode.value

const isThumbnailDraggable = (photo) =>
  canDragPhotoToCanvas(photo) ||
  (canDragPhotoToFolder(photo) && !bulkSelectMode.value && !reorderMode.value)

const transferHasCanvasDrag = (event) => {
  if (pendingCanvasDrag.value) {
    return true
  }
  const types = event?.dataTransfer?.types
  if (!types) {
    return false
  }
  return Array.from(types).includes(DRAG_PHOTO_MIME)
}

const folderDragGhostImage = (() => {
  if (typeof Image === 'undefined') {
    return null
  }

  const image = new Image()
  image.src =
    'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'

  return image
})()

const onDocumentFolderDragOver = (event) => {
  if (!isDraggingPhotoToFolder.value) {
    return
  }

  reorderDragPreview.value = {
    ...reorderDragPreview.value,
    x: event.clientX,
    y: event.clientY
  }
}

const onFolderPhotoDragStart = (event, photo) => {
  if (!canDragPhotoToFolder(photo)) {
    event.preventDefault()
    return
  }

  event.stopPropagation()
  folderDragFilename.value = photo.filename
  isDraggingPhotoToFolder.value = true
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', photo.filename)
  event.dataTransfer.setData(FOLDER_DROP_MIME, photo.filename)

  if (folderDragGhostImage) {
    try {
      event.dataTransfer.setDragImage(folderDragGhostImage, 0, 0)
    } catch {
      // Alguns browsers rejeitam setDragImage; o fantasma customizado compensa.
    }
  }

  reorderDragPreview.value = {
    visible: true,
    x: event.clientX,
    y: event.clientY,
    imageUrl: photo.url ?? '',
    count: 1
  }
  document.addEventListener('dragover', onDocumentFolderDragOver)
}

const suppressThumbnailClickAfterDrag = ref(false)

const onFolderPhotoDragEnd = () => {
  document.removeEventListener('dragover', onDocumentFolderDragOver)
  hideReorderDragPreview()
  folderDragFilename.value = null
  isDraggingPhotoToFolder.value = false
  folderDropTargetId.value = null
  suppressThumbnailClickAfterDrag.value = true
  setTimeout(() => {
    suppressThumbnailClickAfterDrag.value = false
  }, 0)
}

const onGalleryThumbnailDragStart = (_index, event, photo) => {
  pendingCanvasDrag.value = null

  if (reorderMode.value && !canDragPhotoToCanvas(photo)) {
    event.preventDefault()
    return
  }
  if (bulkSelectMode.value && !canDragPhotoToCanvas(photo)) {
    event.preventDefault()
    return
  }

  let allowed = false

  if (canDragPhotoToFolder(photo)) {
    onFolderPhotoDragStart(event, photo)
    allowed = true
  }

  if (canDragPhotoToCanvas(photo)) {
    const payload = { url: photo.url, filename: photo.filename }
    pendingCanvasDrag.value = payload
    event.dataTransfer.setData(DRAG_PHOTO_MIME, JSON.stringify(payload))
    event.dataTransfer.setData('text/plain', photo.filename)
    allowed = true
  }

  if (!allowed) {
    event.preventDefault()
    return
  }

  const forFolder = canDragPhotoToFolder(photo)
  const forCanvas = canDragPhotoToCanvas(photo)

  if (forFolder && forCanvas) {
    event.dataTransfer.effectAllowed = 'copyMove'
  } else if (forFolder) {
    event.dataTransfer.effectAllowed = 'move'
  } else {
    event.dataTransfer.effectAllowed = 'copy'
  }
}

const onGalleryThumbnailDragEnd = () => {
  pendingCanvasDrag.value = null
  onFolderPhotoDragEnd()
  onThumbnailDragEnd()
}

const onEditorDragOver = (event) => {
  if (!isBlankCanvasSelected.value) {
    return
  }
  if (transferHasCanvasDrag(event)) {
    event.preventDefault()
    event.dataTransfer.dropEffect = 'copy'
    isDropTargetActive.value = true
  }
}

const onEditorDragLeave = () => {
  isDropTargetActive.value = false
}

const onEditorDrop = async (event) => {
  event.stopPropagation()
  isDropTargetActive.value = false
  if (!isBlankCanvasSelected.value || !imageEditorRef.value) {
    pendingCanvasDrag.value = null
    return
  }

  let payload = pendingCanvasDrag.value
  if (!payload) {
    const raw = event.dataTransfer?.getData(DRAG_PHOTO_MIME)
    if (raw) {
      try {
        payload = JSON.parse(raw)
      } catch {
        payload = null
      }
    }
  }

  pendingCanvasDrag.value = null

  if (!payload?.url) {
    return
  }
  if (payload.filename === selectedPhoto.value?.filename) {
    return
  }
  try {
    await imageEditorRef.value.addImageOverlayFromUrl(payload.url)
  } catch (error) {
    console.error(error)
    showNotification('error', 'Erro', 'Não foi possível adicionar a imagem à folha')
  }
}

const createBlankCanvas = async () => {
  if (creatingBlank.value || !requireUserId() || isGalleryAtLimit.value) {
    if (isGalleryAtLimit.value) {
      showNotification('error', 'Limite atingido', galleryLimitMessage.value)
    }
    return
  }
  creatingBlank.value = true
  try {
    const response = await axios.post('/api/camera/blank', {
      width: 1600,
      height: 1200,
      ...userParams(),
      ...newPhotoFolderParams()
    })
    if (response.data?.success) {
      const fn = response.data.filename || response.data.photo?.filename
      if (galleryFoldersEnabled.value && newPhotoFolderId.value) {
        openFolderBranch(newPhotoFolderId.value)
      }
      await loadPhotos({ selectFilename: fn, autoSelectFirst: false })
      const folderLabel =
        galleryFoldersEnabled.value && newPhotoFolderId.value
          ? ` em ${folderNameById(newPhotoFolderId.value)}`
          : ''
      showNotification('success', 'Sucesso', `Folha em branco criada${folderLabel}`)
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

const photoUrlWithCacheBust = (photo) => {
  if (!photo?.url) {
    return ''
  }
  const baseUrl = String(photo.url).split('?')[0]
  const version =
    photo.timestamp != null ? String(photo.timestamp) : String(Date.now())
  return `${baseUrl}?v=${version}`
}

const photoUrlForGallery = (photo) => {
  if (!photo?.filename) {
    return photo?.url ?? ''
  }
  return galleryLiveThumbUrls.value[photo.filename] || photo.url || ''
}

const clearGalleryLiveThumb = (filename) => {
  if (!filename || !galleryLiveThumbUrls.value[filename]) {
    return
  }
  const next = { ...galleryLiveThumbUrls.value }
  delete next[filename]
  galleryLiveThumbUrls.value = next
}

const onEditorPreviewUpdated = ({ filename, url }) => {
  if (!filename) {
    return
  }
  if (!url) {
    clearGalleryLiveThumb(filename)
    return
  }
  galleryLiveThumbUrls.value = {
    ...galleryLiveThumbUrls.value,
    [filename]: url
  }
}

const withCacheBustedUrl = (photo) => {
  if (!photo?.url) {
    return photo
  }
  return { ...photo, url: photoUrlWithCacheBust(photo) }
}

const dedupePhotosByFilename = (list) => {
  const byFilename = new Map()
  for (const photo of list) {
    const key = photo?.filename
    if (!key) {
      continue
    }
    const previous = byFilename.get(key)
    if (!previous || (photo.timestamp ?? 0) >= (previous.timestamp ?? 0)) {
      byFilename.set(key, photo)
    }
  }

  const seen = new Set()
  const result = []
  for (const photo of list) {
    const key = photo?.filename
    if (!key || seen.has(key)) {
      continue
    }
    seen.add(key)
    result.push(byFilename.get(key))
  }

  return result
}

const selectedPhotoEditorKey = computed(() => {
  const photo = selectedPhoto.value
  if (!photo) {
    return ''
  }
  const version = photo.timestamp != null ? String(photo.timestamp) : '0'
  const urlVersion = photo.url?.includes('?') ? photo.url.split('?')[1] : ''
  return `${photo.filename}-${version}-${urlVersion}`
})

const normalizePhotosList = (list) =>
  dedupePhotosByFilename((list ?? []).map((photo) => withCacheBustedUrl(photo)))

const syncSelectedPhotoFromList = (filename) => {
  if (!filename) {
    selectedPhoto.value = null
    return
  }
  const found = photos.value.find((p) => p.filename === filename)
  selectedPhoto.value = found ? withCacheBustedUrl(found) : null
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
    selectedPhoto.value = withCacheBustedUrl(photo)
    return true
  }
  if (selectedPhoto.value && editorHasUnsavedChanges()) {
    pendingPhotoSwitch.value = photo
    showSwitchDiscardWarning()
    return false
  }
  selectedPhoto.value = withCacheBustedUrl(photo)
  pendingPhotoSwitch.value = null
  if (galleryFoldersEnabled.value) {
    const folderId = photoFolderId(photo)
    openFolderBranch(folderId)
    newPhotoFolderId.value = folderId
  }
  return true
}

const selectPhoto = (photo) => {
  applyPhotoSelection(photo)
}

const bulkSelectedCount = computed(() => bulkSelectedFilenames.value.length)

const isBulkSelected = (filename) => bulkSelectedFilenames.value.includes(filename)

const isPhotoInBulkFolder = (photo) =>
  !galleryFoldersEnabled.value ||
  !bulkFolderId.value ||
  photoFolderId(photo) === bulkFolderId.value

const isPhotoInReorderFolder = (photo) =>
  !galleryFoldersEnabled.value ||
  !reorderFolderId.value ||
  photoFolderId(photo) === reorderFolderId.value

const toggleBulkSelection = (filename) => {
  const photo = photos.value.find((item) => item.filename === filename)
  if (photo && !isPhotoInBulkFolder(photo)) {
    return
  }

  if (isBulkSelected(filename)) {
    bulkSelectedFilenames.value = bulkSelectedFilenames.value.filter((name) => name !== filename)
  } else {
    bulkSelectedFilenames.value = [...bulkSelectedFilenames.value, filename]
  }
}

const toggleBulkSelectMode = () => {
  bulkSelectMode.value = !bulkSelectMode.value
  if (bulkSelectMode.value) {
    folderReorderMode.value = false
    reorderMode.value = false
    reorderFolderId.value = null
    resetReorderDragState()
    if (galleryFoldersEnabled.value) {
      bulkFolderId.value = resolveActiveFolderId()
      openFolderBranch(bulkFolderId.value)
    }
  } else {
    bulkFolderId.value = null
    bulkSelectedFilenames.value = []
  }
}

const toggleReorderMode = () => {
  reorderMode.value = !reorderMode.value
  if (reorderMode.value) {
    folderReorderMode.value = false
    bulkSelectMode.value = false
    bulkFolderId.value = null
    bulkSelectedFilenames.value = []
    if (galleryFoldersEnabled.value) {
      reorderFolderId.value = resolveActiveFolderId()
      openFolderBranch(reorderFolderId.value)
    }
  } else {
    reorderFolderId.value = null
    reorderSelection.value = []
    resetReorderDragState()
  }
}

const toggleFolderReorderMode = () => {
  folderReorderMode.value = !folderReorderMode.value
  if (folderReorderMode.value) {
    bulkSelectMode.value = false
    bulkFolderId.value = null
    bulkSelectedFilenames.value = []
    reorderMode.value = false
    reorderFolderId.value = null
    reorderSelection.value = []
    resetReorderDragState()
    closeFolderEditor()
    collapseAllFolderBranches()
  }
}

const handleFolderReorder = async (folderIds) => {
  if (!galleryFoldersEnabled.value || !requireUserId() || !Array.isArray(folderIds)) {
    return
  }

  const previous = folders.value.map((folder) => folder.id).join('\0')
  const next = folderIds.join('\0')
  if (previous === next) {
    return
  }

  const byId = new Map(folders.value.map((folder) => [folder.id, folder]))
  folders.value = folderIds.map((id) => byId.get(id)).filter(Boolean)

  folderReorderSaving.value = true
  try {
    const response = await axios.post('/api/camera/folders/reorder', {
      folder_ids: folderIds,
      ...userParams()
    })
    if (response.data?.error) {
      throw new Error(response.data.error)
    }
    if (Array.isArray(response.data?.folders)) {
      folders.value = response.data.folders
    }
  } catch (error) {
    console.error(error)
    await loadPhotos({ silent: true, autoSelectFirst: false })
    showNotification(
      'error',
      'Erro',
      error.response?.data?.error || error.message || 'Não foi possível guardar a ordem das pastas'
    )
  } finally {
    folderReorderSaving.value = false
  }
}

const reorderSelectionCount = computed(() => reorderSelection.value.length)

const isReorderSelected = (filename) => reorderSelection.value.includes(filename)

const toggleReorderSelection = (filename) => {
  const photo = photos.value.find((item) => item.filename === filename)
  if (photo && !isPhotoInReorderFolder(photo)) {
    return
  }

  if (isReorderSelected(filename)) {
    reorderSelection.value = reorderSelection.value.filter((name) => name !== filename)
  } else {
    reorderSelection.value = [...reorderSelection.value, filename]
  }
}

const selectAllReorder = () => {
  if (galleryFoldersEnabled.value && reorderFolderId.value) {
    reorderSelection.value = photosInFolder(reorderFolderId.value).map((photo) => photo.filename)
    return
  }

  reorderSelection.value = photos.value.map((photo) => photo.filename)
}

const clearReorderSelection = () => {
  reorderSelection.value = []
}

const getReorderDragFilenames = (dragIndex, list = displayPhotos.value) => {
  const dragged = list[dragIndex]?.filename
  if (!dragged) {
    return []
  }

  const selected = list
    .filter((photo) => reorderSelection.value.includes(photo.filename))
    .map((photo) => photo.filename)

  if (selected.length >= 2) {
    return selected
  }

  return [dragged]
}

const isInReorderDragBlock = (filename) => {
  if (reorderDragFilenames.value.length > 0) {
    return reorderDragFilenames.value.includes(filename)
  }
  if (reorderDragIndex.value === null) {
    return false
  }

  const list =
    galleryFoldersEnabled.value && reorderDragFolderId.value
      ? photosInFolder(reorderDragFolderId.value)
      : displayPhotos.value

  return list[reorderDragIndex.value]?.filename === filename
}

const rebuildPhotosWithFolderOrder = (folderId, folderPhotos) => {
  const byFolder = new Map()

  for (const photo of photos.value) {
    const fid = photoFolderId(photo)
    if (!byFolder.has(fid)) {
      byFolder.set(fid, [])
    }
    byFolder.get(fid).push(photo)
  }

  byFolder.set(folderId, folderPhotos)

  const result = []

  for (const folder of folders.value) {
    if (byFolder.has(folder.id)) {
      result.push(...byFolder.get(folder.id))
    }
  }

  photos.value = result
}

const getBlockInsertTarget = (list, blockFilenames, insertAt) => {
  const blockSet = new Set(blockFilenames)
  let target = 0
  for (let i = 0; i < Math.min(insertAt, list.length); i++) {
    if (!blockSet.has(list[i].filename)) {
      target++
    }
  }
  return target
}

const movePhotosBlock = (list, blockFilenames, insertAt) => {
  const blockSet = new Set(blockFilenames)
  const block = list.filter((photo) => blockSet.has(photo.filename))
  const remaining = list.filter((photo) => !blockSet.has(photo.filename))
  const target = getBlockInsertTarget(list, blockFilenames, insertAt)

  return [...remaining.slice(0, target), ...block, ...remaining.slice(target)]
}

const hideReorderDragPreview = () => {
  reorderDragPreview.value.visible = false
}

const resetReorderDragState = () => {
  reorderDragIndex.value = null
  reorderDragFilenames.value = []
  reorderInsertAt.value = null
  reorderInsertFolderId.value = null
  reorderDragFolderId.value = null
  reorderPointerActive.value = false
  hideReorderDragPreview()
}

const resolveReorderInsertAt = (clientY, listEl) => {
  const items = listEl.querySelectorAll('[data-reorder-item]')
  if (!items.length) {
    return 0
  }

  for (let i = 0; i < items.length; i++) {
    const rect = items[i].getBoundingClientRect()
    const midpoint = rect.top + rect.height / 2
    if (clientY < midpoint) {
      return i
    }
  }

  return items.length
}

const applyReorderMove = async (from, insertAt, dragFilenames, folderId = null) => {
  if (!reorderMode.value || from === null || insertAt === null) {
    return false
  }

  const list = folderId ? photosInFolder(folderId) : photos.value
  const orderKey = (items) => items.map((photo) => photo.filename).join('\0')

  if (dragFilenames.length >= 2) {
    const reordered = movePhotosBlock(list, dragFilenames, insertAt)

    if (orderKey(reordered) === orderKey(list)) {
      return false
    }

    if (folderId) {
      rebuildPhotosWithFolderOrder(folderId, reordered)
      await persistGalleryOrder(folderId)
    } else {
      photos.value = reordered
      await persistGalleryOrder()
    }

    return true
  }

  if (insertAt === from || insertAt === from + 1) {
    return false
  }

  const updated = [...list]
  const [item] = updated.splice(from, 1)
  let target = insertAt
  if (from < insertAt) {
    target -= 1
  }
  updated.splice(target, 0, item)

  if (folderId) {
    rebuildPhotosWithFolderOrder(folderId, updated)
    await persistGalleryOrder(folderId)
  } else {
    photos.value = updated
    await persistGalleryOrder()
  }

  return true
}

const startReorderPointerDrag = ({ folderId, index, event, list, listEl }) => {
  if (!reorderMode.value) {
    return
  }
  if (event.pointerType === 'mouse' && event.button !== 0) {
    return
  }
  if (event.target.closest('label, input, button')) {
    return
  }
  if (!listEl) {
    return
  }

  const dragFilenames = getReorderDragFilenames(index, list)
  const startX = event.clientX
  const startY = event.clientY
  let moved = false
  const primaryPhoto =
    list.find((photo) => photo.filename === dragFilenames[0]) ?? list[index]

  reorderPointerActive.value = true
  reorderDragIndex.value = index
  reorderDragFilenames.value = dragFilenames
  reorderInsertAt.value = index
  reorderInsertFolderId.value = folderId
  reorderDragFolderId.value = folderId
  reorderDragPreview.value = {
    visible: false,
    x: startX,
    y: startY,
    imageUrl: primaryPhoto?.url ?? '',
    count: dragFilenames.length
  }

  const onMove = (moveEvent) => {
    if (!reorderPointerActive.value) {
      return
    }
    if (
      !moved &&
      (Math.abs(moveEvent.clientY - startY) > 4 || Math.abs(moveEvent.clientX - startX) > 4)
    ) {
      moved = true
    }
    reorderDragPreview.value = {
      ...reorderDragPreview.value,
      visible: moved,
      x: moveEvent.clientX,
      y: moveEvent.clientY
    }
    reorderInsertAt.value = resolveReorderInsertAt(moveEvent.clientY, listEl)
    if (folderId) {
      reorderInsertFolderId.value = folderId
    }
  }

  const onUp = async (upEvent) => {
    window.removeEventListener('pointermove', onMove)
    window.removeEventListener('pointerup', onUp)
    window.removeEventListener('pointercancel', onUp)

    if (!reorderPointerActive.value) {
      return
    }

    const insertAt = resolveReorderInsertAt(upEvent.clientY, listEl)
    const from = index
    const filenames = [...dragFilenames]

    reorderPointerActive.value = false
    resetReorderDragState()

    if (moved) {
      reorderSuppressClick.value = true
      window.setTimeout(() => {
        reorderSuppressClick.value = false
      }, 0)
      await applyReorderMove(from, insertAt, filenames, folderId)
    }
  }

  window.addEventListener('pointermove', onMove)
  window.addEventListener('pointerup', onUp)
  window.addEventListener('pointercancel', onUp)
  event.preventDefault()
}

const onReorderPointerDown = (index, event) => {
  if (!reorderMode.value) {
    return
  }

  const photo = displayPhotos.value[index]
  if (canDragPhotoToCanvas(photo)) {
    return
  }

  startReorderPointerDrag({
    folderId: null,
    index,
    event,
    list: displayPhotos.value,
    listEl: galleryListRef.value
  })
}

const onFolderReorderPointerDown = (folderId, index, event) => {
  if (folderId !== reorderFolderId.value) {
    return
  }

  startReorderPointerDrag({
    folderId,
    index,
    event,
    list: photosInFolder(folderId),
    listEl: event.target.closest('[data-reorder-folder]')
  })
}

const persistGalleryOrder = async (folderId = null) => {
  if (!requireUserId()) {
    return
  }

  const selectedFilename = selectedPhoto.value?.filename
  reorderSaving.value = true

  const payload = {
    ...userParams(),
    filenames:
      galleryFoldersEnabled.value && folderId
        ? photosInFolder(folderId).map((photo) => photo.filename)
        : photos.value.map((photo) => photo.filename)
  }

  if (galleryFoldersEnabled.value && folderId) {
    payload.folder_id = folderId
  }

  try {
    const { data } = await axios.post('/api/camera/photos/reorder', payload)
    if (data?.photos) {
      photos.value = normalizePhotosList(data.photos)
    }
    applyFoldersFromResponse(data)
  } catch (error) {
    console.error('Erro ao guardar ordem das fotos:', error)
    await loadPhotos({ autoSelectFirst: false })
    syncSelectedPhotoFromList(selectedFilename)
    showNotification('error', 'Erro', 'Não foi possível guardar a ordem das imagens')
  } finally {
    reorderSaving.value = false
  }
}

const onTreeSelectFolder = (folderId) => {
  openFolderBranch(folderId)

  if (!galleryFoldersEnabled.value) {
    return
  }

  newPhotoFolderId.value = folderId

  if (reorderMode.value) {
    reorderFolderId.value = folderId
    reorderSelection.value = []
    resetReorderDragState()
  }

  if (bulkSelectMode.value) {
    bulkFolderId.value = folderId
    bulkSelectedFilenames.value = []
  }
}

const applyFoldersFromResponse = (data) => {
  if (!galleryFoldersEnabled.value) {
    folders.value = []
    return
  }

  // Em eventos realtime (ex.: upload por QR) pode não vir "folders".
  // Nesse caso, preservamos as pastas actuais para evitar "desaparecerem" até refresh.
  if (Array.isArray(data?.folders)) {
    folders.value = data.folders
  }
  ensureNewPhotoFolderId()
  pruneExpandedFolderBranches()
}

const focusFolderEditorInput = () => {
  nextTick(() => {
    folderNameInputRef.value?.focus()
    folderNameInputRef.value?.select()
  })
}

const openFolderEditor = (mode, folder = null) => {
  folderEditor.value = {
    open: true,
    mode,
    folderId: folder?.id ?? null,
    name: folder?.name ?? '',
    color: folder?.color ?? defaultFolderColor(folders.value),
    error: ''
  }
  focusFolderEditorInput()
}

const toggleFolderCreate = () => {
  if (folderEditor.value.open && folderEditor.value.mode === 'create') {
    closeFolderEditor()
    return
  }
  openFolderEditor('create')
}

const closeFolderEditor = () => {
  folderEditor.value.open = false
  folderEditor.value.error = ''
  folderEditor.value.name = ''
  folderEditor.value.folderId = null
  folderEditor.value.color = defaultFolderColor(folders.value)
}

const submitFolderEditor = async () => {
  const name = folderEditor.value.name.trim()
  if (!name) {
    folderEditor.value.error = 'Indique um nome para a pasta.'
    return
  }

  if (folderEditor.value.mode === 'rename') {
    const current = folders.value.find((item) => item.id === folderEditor.value.folderId)
    if (
      current &&
      current.name === name &&
      folderDisplayColor(current) === folderEditor.value.color
    ) {
      closeFolderEditor()
      return
    }
    await renameGalleryFolder(
      folderEditor.value.folderId,
      name,
      folderEditor.value.color
    )
    return
  }

  await createGalleryFolder(name, folderEditor.value.color)
}

const createGalleryFolder = async (name, color) => {
  if (!galleryFoldersEnabled.value || !requireUserId() || folderActionLoading.value) {
    return
  }

  folderActionLoading.value = true
  folderEditor.value.error = ''
  try {
    const response = await axios.post('/api/camera/folders', {
      name,
      color,
      ...userParams()
    })
    if (response.data?.error) {
      throw new Error(response.data.error)
    }
    folders.value = response.data.folders ?? folders.value
    if (response.data.folder?.id) {
      const next = new Set(expandedFolderBranches.value)
      next.add(response.data.folder.id)
      expandedFolderBranches.value = next
    }
    closeFolderEditor()
    showNotification('success', 'Pasta criada', `A pasta «${name}» foi criada.`)
  } catch (error) {
    console.error(error)
    folderEditor.value.error =
      error.response?.data?.error || error.message || 'Não foi possível criar a pasta.'
  } finally {
    folderActionLoading.value = false
  }
}

const renameGalleryFolder = async (folderId, name, color) => {
  if (!folderId || folderActionLoading.value || !requireUserId()) {
    return
  }

  folderActionLoading.value = true
  folderEditor.value.error = ''
  try {
    const response = await axios.patch(`/api/camera/folders/${encodeURIComponent(folderId)}`, {
      name,
      color,
      ...userParams()
    })
    if (response.data?.error) {
      throw new Error(response.data.error)
    }
    folders.value = response.data.folders ?? folders.value
    closeFolderEditor()
    showNotification('success', 'Pasta actualizada', `A pasta «${name}» foi actualizada.`)
  } catch (error) {
    console.error(error)
    folderEditor.value.error =
      error.response?.data?.error || error.message || 'Não foi possível renomear a pasta.'
  } finally {
    folderActionLoading.value = false
  }
}

const confirmDeleteGalleryFolder = (folder) => {
  if (!folder || folder.system) {
    return
  }

  const count = photosInFolder(folder.id).length
  const imagesLabel =
    count === 1 ? '1 imagem será eliminada' : `${count} imagens serão eliminadas`

  showNotification(
    'warning',
    'Eliminar pasta',
    count > 0
      ? `A pasta «${folder.name}» e as imagens dentro dela serão eliminadas permanentemente (${imagesLabel}). Continuar?`
      : `A pasta «${folder.name}» será eliminada. Continuar?`,
    true,
    folder,
    0,
    'delete-folder'
  )
}

const deleteGalleryFolder = async (folder) => {
  if (!folder || !requireUserId() || folderActionLoading.value) {
    return
  }

  folderActionLoading.value = true
  try {
    const response = await axios.delete(
      `/api/camera/folders/${encodeURIComponent(folder.id)}`,
      { data: userParams() }
    )
    if (response.data?.error) {
      throw new Error(response.data.error)
    }
    folders.value = response.data.folders ?? folders.value
    if (expandedFolderBranches.value.has(folder.id)) {
      const next = new Set(expandedFolderBranches.value)
      next.delete(folder.id)
      expandedFolderBranches.value = next
    }
    if (
      selectedPhoto.value &&
      photoFolderId(selectedPhoto.value) === folder.id
    ) {
      selectedPhoto.value = null
    }
    await loadPhotos({ silent: true, autoSelectFirst: false })
    const deletedCount = Number(response.data?.deleted_photos_count ?? 0)
    if (response.data?.partial) {
      showNotification(
        'warning',
        'Eliminação parcial',
        `A pasta «${folder.name}» foi eliminada, mas algumas imagens falharam.`
      )
      return
    }
    const photosLabel =
      deletedCount === 1
        ? '1 imagem eliminada'
        : deletedCount > 0
          ? `${deletedCount} imagens eliminadas`
          : null
    showNotification(
      'success',
      'Pasta eliminada',
      photosLabel
        ? `A pasta «${folder.name}» e ${photosLabel}.`
        : `A pasta «${folder.name}» foi eliminada.`
    )
  } catch (error) {
    console.error(error)
    showNotification(
      'error',
      'Erro',
      error.response?.data?.error || error.message || 'Não foi possível eliminar a pasta'
    )
  } finally {
    folderActionLoading.value = false
  }
}

const movePhotosToFolder = async (filenames, folderId, options = {}) => {
  if (!galleryFoldersEnabled.value || !folderId || !requireUserId()) {
    return false
  }

  const names = [...new Set(filenames.filter(Boolean))]
  if (names.length === 0) {
    return false
  }

  folderActionLoading.value = true
  try {
    const response = await axios.post('/api/camera/photos/move', {
      filenames: names,
      folder_id: folderId,
      ...userParams()
    })
    if (response.data?.error) {
      throw new Error(response.data.error)
    }
    photos.value = normalizePhotosList(response.data.photos)
    applyFoldersFromResponse(response.data)

    if (names.length === 1) {
      syncSelectedPhotoFromList(names[0])
    }

    if (options.clearBulk) {
      bulkSelectedFilenames.value = []
      bulkSelectMode.value = false
      bulkFolderId.value = null
    }

    if (options.expandFolder) {
      openFolderBranch(folderId)
    }

    const message =
      names.length === 1
        ? 'A imagem foi movida para a pasta.'
        : `${names.length} imagens foram movidas para a pasta.`
    showNotification('success', 'Imagens movidas', message)
    return true
  } catch (error) {
    console.error(error)
    showNotification(
      'error',
      'Erro',
      error.response?.data?.error || error.message || 'Não foi possível mover as imagens'
    )
    return false
  } finally {
    folderActionLoading.value = false
  }
}

const onFolderDragOver = (folderId, event) => {
  if (reorderMode.value || !galleryFoldersEnabled.value || !isDraggingPhotoToFolder.value) {
    return
  }
  event.preventDefault()
  event.stopPropagation()
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move'
  }
  folderDropTargetId.value = folderId
}

const onFolderDragLeave = (folderId, event) => {
  const related = event.relatedTarget
  if (related && event.currentTarget?.contains(related)) {
    return
  }
  if (folderDropTargetId.value === folderId) {
    folderDropTargetId.value = null
  }
}

const onFolderDrop = async (folderId, event) => {
  if (reorderMode.value) {
    return
  }

  event.preventDefault()
  event.stopPropagation()

  const filename =
    event.dataTransfer?.getData(FOLDER_DROP_MIME) ||
    event.dataTransfer?.getData('text/plain') ||
    folderDragFilename.value
  folderDropTargetId.value = null
  folderDragFilename.value = null
  isDraggingPhotoToFolder.value = false
  hideReorderDragPreview()

  if (!filename) {
    return
  }
  await movePhotosToFolder([filename], folderId, { expandFolder: true })
}

const selectAllBulk = () => {
  if (galleryFoldersEnabled.value) {
    if (!bulkFolderId.value) {
      return
    }
    bulkSelectedFilenames.value = photosInFolder(bulkFolderId.value).map((photo) => photo.filename)
    return
  }

  bulkSelectedFilenames.value = displayPhotos.value.map((photo) => photo.filename)
}

const clearBulkSelection = () => {
  bulkSelectedFilenames.value = []
}

const onThumbnailClick = (photo) => {
  if (suppressThumbnailClickAfterDrag.value || reorderSuppressClick.value) {
    return
  }
  if (bulkSelectMode.value) {
    if (isPhotoInBulkFolder(photo)) {
      toggleBulkSelection(photo.filename)
    }
    return
  }
  selectPhoto(photo)
}

const selectedPhotoIndex = computed(() => {
  const filename = selectedPhoto.value?.filename
  if (!filename) {
    return -1
  }
  return displayPhotos.value.findIndex((photo) => photo.filename === filename)
})

const onGalleryNavigate = (direction) => {
  const index = selectedPhotoIndex.value
  if (index < 0) {
    return
  }
  const nextIndex = direction === 'next' ? index + 1 : index - 1
  if (nextIndex < 0 || nextIndex >= displayPhotos.value.length) {
    return
  }
  selectPhoto(displayPhotos.value[nextIndex])
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
      photoToDelete:
        action === 'delete' || action === 'delete-bulk' || action === 'delete-folder'
          ? photoToDelete
          : null,
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
  if (action === 'delete-bulk') {
    confirmBulkDelete(notification.value.photoToDelete)
  }
  if (action === 'delete-folder') {
    deleteGalleryFolder(notification.value.photoToDelete)
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

    photos.value = normalizePhotosList(response.data.photos)
    applyFoldersFromResponse(response.data)

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
    photos.value = normalizePhotosList(payload.photos)
    applyFoldersFromResponse(payload)
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
  const files = Array.from(event.target.files ?? []).filter((file) =>
    String(file.type || '').startsWith('image/')
  )
  event.target.value = ''
  if (files.length === 0 || !requireUserId()) {
    return
  }

  const maxBytes = galleryMaxUploadBytes.value
  const acceptedBySize = []
  const rejectedBySize = []
  for (const file of files) {
    if (file.size > maxBytes) {
      rejectedBySize.push(file)
    } else {
      acceptedBySize.push(file)
    }
  }
  if (rejectedBySize.length > 0) {
    const mb = galleryMaxUploadMb.value
    const label =
      rejectedBySize.length === 1
        ? `"${rejectedBySize[0].name}" excede ${mb} MB.`
        : `${rejectedBySize.length} ficheiro(s) excedem ${mb} MB.`
    showNotification('warning', 'Ficheiro demasiado grande', label)
  }
  if (acceptedBySize.length === 0) {
    return
  }

  if (isGalleryAtLimit.value) {
    showNotification('error', 'Limite atingido', galleryLimitMessage.value)
    return
  }
  const remaining = galleryRemainingSlots.value
  const filesToUpload =
    Number.isFinite(remaining) && remaining < acceptedBySize.length
      ? acceptedBySize.slice(0, remaining)
      : acceptedBySize
  if (filesToUpload.length < acceptedBySize.length) {
    showNotification(
      'warning',
      'Limite da galeria',
      `Só é possível carregar mais ${remaining} imagem(ns).`
    )
  }
  uploading.value = true
  uploadProgress.value = { current: 0, total: filesToUpload.length }
  let lastFilename = null
  let uploaded = 0
  let failed = 0
  try {
    for (let i = 0; i < filesToUpload.length; i++) {
      uploadProgress.value = { current: i + 1, total: filesToUpload.length }
      const formData = new FormData()
      formData.append('photo', filesToUpload[i])
      formData.append('user_id', String(props.userId))
      if (galleryFoldersEnabled.value && newPhotoFolderId.value) {
        formData.append('folder_id', newPhotoFolderId.value)
      }
      try {
        const response = await axios.post('/api/camera/upload', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        if (response.data?.success) {
          lastFilename = response.data.filename || response.data.photo?.filename
          uploaded++
        } else {
          failed++
        }
      } catch (error) {
        console.error('Erro ao carregar imagem:', error)
        failed++
      }
    }
    if (uploaded === 0) {
      throw new Error('Nenhuma imagem foi carregada')
    }
    if (galleryFoldersEnabled.value && newPhotoFolderId.value) {
      openFolderBranch(newPhotoFolderId.value)
    }
    await loadPhotos({ selectFilename: lastFilename, autoSelectFirst: false })
    const folderLabel =
      galleryFoldersEnabled.value && newPhotoFolderId.value
        ? ` em ${folderNameById(newPhotoFolderId.value)}`
        : ''
    if (failed === 0) {
      showNotification(
        'success',
        'Sucesso',
        uploaded === 1
          ? `Imagem carregada com sucesso${folderLabel}`
          : `${uploaded} imagens carregadas com sucesso${folderLabel}`
      )
    } else {
      showNotification(
        'warning',
        'Carregamento parcial',
        `${uploaded} imagem(ns) carregada(s), ${failed} falharam.`
      )
    }
  } catch (error) {
    console.error('Erro ao carregar imagens:', error)
    const msg = error.response?.data?.error || error.message || 'Erro ao carregar as imagens'
    showNotification('error', 'Erro', msg)
  } finally {
    uploading.value = false
    uploadProgress.value = { current: 0, total: 0 }
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

const handleConfirmBulkDelete = () => {
  const filenames = [...bulkSelectedFilenames.value]
  if (filenames.length === 0) {
    return
  }

  const selectedFilename = selectedPhoto.value?.filename
  if (
    selectedFilename &&
    filenames.includes(selectedFilename) &&
    editorHasUnsavedChanges()
  ) {
    showNotification(
      'warning',
      'Alterações não guardadas',
      'Uma das imagens selecionadas tem alterações por guardar. Se continuar, perde o que ainda não guardou.',
      true,
      filenames,
      0,
      'delete-bulk',
      'Eliminar mesmo assim',
      'Cancelar'
    )
    return
  }

  const label = filenames.length === 1 ? 'esta foto' : `estas ${filenames.length} fotos`
  showNotification(
    'warning',
    'Confirmar exclusão',
    `Tem a certeza de que deseja eliminar ${label}?`,
    true,
    filenames,
    0,
    'delete-bulk',
    'Eliminar',
    'Cancelar'
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
    clearGalleryLiveThumb(photo.filename)
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

const confirmBulkDelete = async (filenames) => {
  if (!requireUserId() || !Array.isArray(filenames) || filenames.length === 0) {
    return
  }

  const selectedFilename = selectedPhoto.value?.filename
  const deletingSelected = selectedFilename && filenames.includes(selectedFilename)
  bulkDeleting.value = true

  try {
    const response = await axios.delete('/api/camera/photos', {
      data: { filenames, ...userParams() }
    })
    const deletedCount = response.data?.deleted_count ?? filenames.length
    const failedCount = response.data?.failed_count ?? 0

    bulkSelectedFilenames.value = []
    bulkSelectMode.value = false
    bulkFolderId.value = null

    await loadPhotos({ autoSelectFirst: deletingSelected })

    if (deletingSelected) {
      if (photos.value.length > 0) {
        selectedPhoto.value = photos.value[0]
      } else {
        selectedPhoto.value = null
      }
    }

    if (failedCount > 0) {
      showNotification(
        'warning',
        'Eliminação parcial',
        `${deletedCount} eliminada(s), ${failedCount} falharam.`
      )
      return
    }

    const message =
      deletedCount === 1
        ? 'Foto eliminada com sucesso'
        : `${deletedCount} fotos eliminadas com sucesso`
    showNotification('success', 'Sucesso', message)
  } catch (error) {
    console.error('Erro ao eliminar fotos:', error)
    const partial = error.response?.data?.deleted_count
    if (partial > 0) {
      bulkSelectedFilenames.value = []
      bulkSelectMode.value = false
      bulkFolderId.value = null
      await loadPhotos({ autoSelectFirst: true })
      showNotification(
        'warning',
        'Eliminação parcial',
        `${partial} eliminada(s); algumas falharam.`
      )
      return
    }
    showNotification('error', 'Erro', 'Erro ao eliminar as fotos selecionadas')
  } finally {
    bulkDeleting.value = false
  }
}

const getQRCode = async () => {
  if (isGalleryAtLimit.value) {
    showNotification('error', 'Limite atingido', galleryLimitMessage.value)
    return
  }
  if (props.userId == null || props.userId === '') {
    showNotification('error', 'Erro', 'ID do utilizador em falta.')
    return
  }
  try {
    ensureNewPhotoFolderId()
    const response = await axios.post('/api/camera/qrcode', {
      ...userParams(),
      ...newPhotoFolderParams()
    })
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
  if (isGalleryAtLimit.value) {
    showNotification('error', 'Limite atingido', galleryLimitMessage.value)
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
    if (fn) {
      clearGalleryLiveThumb(fn)
    }
    const photo = fn ? photos.value.find((p) => p.filename === fn) : selectedPhoto.value
    const useInForm = Boolean(payload && typeof payload === 'object' && payload.useInForm)

    if (photo && useInForm) {
      emit('useInForm', {
        filename: photo.filename,
        url: photoUrlWithCacheBust(photo),
        is_blank_canvas: Boolean(photo.is_blank_canvas)
      })
      const isCopy = payload?.saveMode === 'copy'
      showNotification(
        'success',
        'Formulário',
        isCopy
          ? 'Nova imagem criada e pronta para usar no formulário. O original mantém-se na lista.'
          : 'Imagem guardada e pronta para usar no formulário'
      )
      return
    }

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
    if (isCopy && payload?.saveCopyFolderId && galleryFoldersEnabled.value) {
      openFolderBranch(payload.saveCopyFolderId)
    }
    const copyFolderLabel =
      isCopy && payload?.saveCopyFolderId
        ? folderNameById(payload.saveCopyFolderId)
        : null
    showNotification(
      'success',
      'Sucesso',
      isCopy
        ? copyFolderLabel
          ? `Nova imagem criada na pasta «${copyFolderLabel}»; o original mantém-se.`
          : 'Nova imagem criada; o ficheiro original mantém-se.'
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

.reorder-drag-ghost {
  transform: translate(14px, 14px);
  opacity: 0.95;
  will-change: transform, left, top;
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

.gallery-folder-btn {
  @apply flex w-full items-center gap-2 rounded-lg border-2 border-gray-200 bg-white px-2 py-2 text-xs text-gray-700 transition hover:border-gray-300;
}

.gallery-folder-btn--active {
  @apply border-blue-500 bg-blue-50 font-medium text-blue-900 ring-2 ring-blue-200;
}

.gallery-folder-icon {
  @apply h-4 w-4 shrink-0 text-gray-400;
}

.gallery-folder-btn--active .gallery-folder-icon {
  @apply text-blue-600;
}

.gallery-folder-count {
  @apply shrink-0 rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-500;
}

.gallery-folder-btn--active .gallery-folder-count {
  @apply bg-blue-100 text-blue-700;
}

.gallery-folder-btn--drop-target {
  @apply border-blue-400 bg-blue-100 ring-2 ring-blue-300;
}

</style>
