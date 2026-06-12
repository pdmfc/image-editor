<template>
  <div
    class="flex min-h-0 flex-col overflow-hidden bg-black"
    :class="embedded ? 'h-full w-full' : 'fixed inset-0 z-50'"
  >
    <!-- Botão de Fechar (modo ecrã inteiro / popup) -->
    <button
      v-if="!embedded"
      type="button"
      title="Fechar editor"
      @click="$emit('close')"
      class="absolute top-4 right-4 z-50 p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
    >
      <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>

    <!-- Botões de Ação -->
    <div class="absolute top-4 left-4 z-50 flex max-w-[min(28rem,calc(100vw-4rem))] flex-col gap-2">
      <div class="flex space-x-2">
        <button
          type="button"
          title="Desfazer (Ctrl+Z)"
          :disabled="!canUndoEdit"
          @click="undoEdit"
          class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white disabled:opacity-35 disabled:pointer-events-none"
        >
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a4 4 0 014 4v2M3 10l4-4M3 10l4 4" />
          </svg>
        </button>
        <button
          type="button"
          title="Refazer (Ctrl+Shift+Z)"
          :disabled="!canRedoEdit"
          @click="redoEdit"
          class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white disabled:opacity-35 disabled:pointer-events-none"
        >
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a4 4 0 00-4 4v2M21 10l-4-4M21 10l-4 4" />
          </svg>
        </button>
      </div>
      <div class="flex space-x-2">
        <button
          type="button"
          title="Repor todas as edições (voltar ao original)"
          :disabled="!hasChanges"
          @click="resetFilters"
          class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white disabled:opacity-35 disabled:pointer-events-none"
        >
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </button>
        <button
          type="button"
          title="Guardar imagem"
          class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white disabled:opacity-35 disabled:pointer-events-none"
          :class="{ 'bg-opacity-75 ring-2 ring-white/60': showSavePanel && hasChanges }"
          :disabled="!hasChanges || isSaving"
          @click="toggleSavePanel"
        >
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
        </button>
        <button
          v-if="showUseInForm"
          type="button"
          title="Usar no formulário"
          class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white disabled:opacity-35 disabled:pointer-events-none"
          :class="{ 'bg-opacity-75 ring-2 ring-white/60': showUseInFormWarning }"
          :disabled="isSaving"
          @click="requestUseInForm"
        >
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </button>
      </div>
      <div
        v-if="showSavePanel"
        class="w-[min(18rem,calc(100vw-5rem))] rounded-lg border border-white/15 bg-black/85 px-3 py-2 text-xs text-white shadow-xl backdrop-blur-sm"
      >
        <div class="mb-2 flex items-center justify-between gap-2">
          <p class="font-medium text-white/90">Ao guardar</p>
          <button
            type="button"
            title="Fechar"
            class="rounded p-0.5 text-white/70 hover:bg-white/10 hover:text-white"
            @click="closeSavePanel"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <label class="flex cursor-pointer items-start gap-2 py-0.5">
          <input v-model="saveMode" type="radio" value="overwrite" class="mt-0.5 shrink-0" />
          <span>Substituir o ficheiro original</span>
        </label>
        <label class="flex cursor-pointer items-start gap-2 py-0.5">
          <input v-model="saveMode" type="radio" value="copy" class="mt-0.5 shrink-0" />
          <span>Manter o original e criar uma nova imagem</span>
        </label>
        <label v-if="showSaveCopyFolderPicker" class="mt-2 block">
          <span class="mb-1 block text-white/80">Pasta da nova imagem</span>
          <select
            v-model="saveCopyFolderId"
            class="w-full rounded-md border border-white/20 bg-black/40 px-2 py-1.5 text-white focus:border-white/40 focus:outline-none"
          >
            <option v-for="folder in galleryFolders" :key="folder.id" :value="folder.id">
              {{ folder.name }}
            </option>
          </select>
        </label>
        <div class="mt-2 border-t border-white/20 pt-2">
          <p class="mb-1 font-medium text-white/90">Formato</p>
          <label class="flex cursor-pointer items-center gap-2 py-0.5">
            <input v-model="saveFormat" type="radio" value="jpeg" class="shrink-0" />
            <span>JPEG</span>
          </label>
          <label class="flex cursor-pointer items-center gap-2 py-0.5">
            <input v-model="saveFormat" type="radio" value="png" class="shrink-0" />
            <span>PNG (sem perda)</span>
          </label>
          <div v-if="saveFormat === 'jpeg'" class="mt-1.5">
            <label class="text-white/80">Qualidade: {{ saveQuality }}%</label>
            <input
              v-model.number="saveQuality"
              type="range"
              min="50"
              max="100"
              step="1"
              class="mt-1 w-full accent-white"
            />
          </div>
        </div>
        <div class="mt-3 flex gap-2">
          <button
            type="button"
            class="flex-1 rounded-lg bg-white/10 px-2 py-1.5 text-white/90 hover:bg-white/20"
            @click="closeSavePanel"
          >
            Cancelar
          </button>
          <button
            type="button"
            class="flex-1 rounded-lg bg-green-600 px-2 py-1.5 font-medium text-white hover:bg-green-500 disabled:opacity-50"
            :disabled="isSaving"
            @click="confirmSave"
          >
            {{ isSaving ? 'A guardar…' : 'Guardar' }}
          </button>
        </div>
      </div>
      <div
        v-if="showUseInFormWarning"
        class="w-[min(18rem,calc(100vw-5rem))] rounded-lg border border-amber-400/30 bg-black/85 px-3 py-2 text-xs text-white shadow-xl backdrop-blur-sm"
      >
        <div class="mb-2 flex items-center justify-between gap-2">
          <p class="font-medium text-amber-200">Alterações por guardar</p>
          <button
            type="button"
            title="Fechar"
            class="rounded p-0.5 text-white/70 hover:bg-white/10 hover:text-white"
            @click="closeUseInFormWarning"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <p class="text-white/80">
          Guarde primeiro para o formulário receber a versão actual, ou use o ficheiro já guardado no disco.
        </p>
        <div class="mt-3 border-t border-white/15 pt-2">
          <p class="mb-1 font-medium text-white/90">Ao guardar</p>
          <label class="flex cursor-pointer items-start gap-2 py-0.5">
            <input v-model="saveMode" type="radio" value="overwrite" class="mt-0.5 shrink-0" />
            <span>Substituir o ficheiro original</span>
          </label>
          <label class="flex cursor-pointer items-start gap-2 py-0.5">
            <input v-model="saveMode" type="radio" value="copy" class="mt-0.5 shrink-0" />
            <span>Manter o original e criar uma nova imagem</span>
          </label>
          <label v-if="showSaveCopyFolderPicker" class="mt-2 block">
            <span class="mb-1 block text-white/80">Pasta da nova imagem</span>
            <select
              v-model="saveCopyFolderId"
              class="w-full rounded-md border border-white/20 bg-black/40 px-2 py-1.5 text-white focus:border-white/40 focus:outline-none"
            >
              <option v-for="folder in galleryFolders" :key="folder.id" :value="folder.id">
                {{ folder.name }}
              </option>
            </select>
          </label>
        </div>
        <div class="mt-3 flex flex-col gap-2">
          <button
            type="button"
            class="rounded-lg bg-emerald-600 px-2 py-1.5 font-medium text-white hover:bg-emerald-500 disabled:opacity-50"
            :disabled="isSaving"
            @click="saveAndUseInForm"
          >
            {{
              isSaving
                ? 'A guardar…'
                : saveMode === 'copy'
                  ? 'Criar nova imagem e usar no formulário'
                  : 'Guardar e usar no formulário'
            }}
          </button>
          <button
            type="button"
            class="rounded-lg bg-white/10 px-2 py-1.5 text-white/90 hover:bg-white/20"
            :disabled="isSaving"
            @click="useInFormWithoutSaving"
          >
            Usar sem guardar alterações
          </button>
          <button
            type="button"
            class="rounded-lg px-2 py-1.5 text-white/70 hover:bg-white/10 hover:text-white"
            @click="closeUseInFormWarning"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <div class="relative flex h-full min-h-0 min-w-0 w-full flex-col">
      <div
        v-if="isBlankCanvas && showBlankCanvasHint"
        class="absolute left-1/2 top-14 z-[46] flex max-w-md -translate-x-1/2 items-start gap-2 rounded-lg bg-sky-900/90 px-3 py-2 text-xs text-sky-100 shadow-lg"
      >
        <p class="flex-1 text-center leading-snug">
          Arraste imagens da barra lateral. Use Desenho (menu inferior) para círculos, setas e linhas sobre e entre as fotos.
        </p>
        <button
          type="button"
          title="Fechar dica"
          class="shrink-0 rounded p-0.5 text-sky-200/80 hover:bg-white/10 hover:text-white"
          @click="dismissBlankCanvasHint"
        >
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <!-- Área de Edição: margem inferior quando há crop/zona desfoque para as alças não ficarem sob o menu -->
      <div
        ref="viewportRef"
        class="relative min-h-0 min-w-0 flex-1 overflow-hidden touch-none transition-[padding] duration-200"
        :class="[
          viewportBottomPaddingClass,
          {
            'cursor-grab': viewPanHandMode && !isViewPanning,
            'cursor-grabbing': isViewPanning
          }
        ]"
        @wheel.prevent="onViewportWheel"
        @mousedown="onViewportMouseDown"
        @touchstart="onViewportTouchStart"
        @touchmove="onViewportTouchMove"
        @touchend="onViewportTouchEnd"
        @touchcancel="onViewportTouchEnd"
      >
        <div
          class="relative h-full w-full will-change-transform"
          :style="stageTransformStyle"
        >
        <!-- Composição detalhe ampliado (fundo branco + foto reduzida + recortes) -->
        <div
          v-if="zoomLayout"
          class="absolute inset-0 z-[12] flex items-center justify-center"
        >
          <div
            ref="layoutBoardRef"
            class="relative bg-white shadow-2xl"
            :style="layoutBoardContainerStyle"
          >
            <img
              :src="currentImageUrl"
              alt="Imagem principal"
              ref="imageRef"
              class="zoom-layout-base-img absolute block h-full w-full select-none"
              :style="layoutBaseImgStyle"
              draggable="false"
              @load="onImageLoad"
            />
            <div
              v-if="zoomDetailSelecting && zoomSelectBoardStyle"
              class="pointer-events-none absolute z-[20] border-2 border-dashed border-violet-400 bg-violet-500/20"
              :style="zoomSelectBoardStyle"
            />
            <div
              v-for="(callout, calloutIndex) in zoomCallouts"
              :key="callout.id"
              class="absolute z-[25] overflow-visible shadow-lg transition-shadow"
              :class="
                canEditZoomCallouts && callout.id === selectedZoomCalloutId
                  ? 'ring-4 ring-violet-300'
                  : canEditZoomCallouts
                    ? 'ring-2 ring-violet-500'
                    : ''
              "
              :style="zoomCalloutBoxStyle(callout)"
              @click.stop="canEditZoomCallouts && selectZoomCallout(callout.id)"
            >
              <div class="absolute inset-0 overflow-hidden rounded-sm">
                <img
                  :src="callout.src"
                  alt=""
                  class="zoom-callout-img pointer-events-none absolute inset-0 block h-full w-full"
                  draggable="false"
                />
              </div>
              <div
                v-if="canEditZoomCallouts && !zoomDetailSelecting"
                class="absolute inset-0 z-0 cursor-move touch-none"
                title="Arrastar detalhe"
                @mousedown.stop.prevent="startZoomCalloutMove($event, callout.id)"
                @touchstart.prevent="startZoomCalloutMove($event, callout.id)"
              />
              <div
                v-if="canEditZoomCallouts && !zoomDetailSelecting"
                class="absolute -bottom-1 -right-1 z-10 h-3 w-3 cursor-se-resize rounded-sm border border-violet-200 bg-violet-600/90"
                title="Redimensionar detalhe"
                @mousedown.stop.prevent="startZoomCalloutResize($event, callout.id)"
                @touchstart.stop.prevent="startZoomCalloutResize($event, callout.id)"
              />
              <button
                v-if="canEditZoomCallouts"
                type="button"
                title="Remover detalhe"
                class="absolute -right-2 -top-2 z-10 flex h-4 w-4 cursor-pointer items-center justify-center rounded-full bg-red-500 text-xs text-white shadow"
                @click.stop="removeZoomCallout(callout.id)"
              >×</button>
            </div>
            <svg
              v-if="showLayoutDrawingsOverlay"
              class="pointer-events-none absolute inset-0 z-[26] h-full w-full overflow-visible"
              preserveAspectRatio="none"
              :viewBox="layoutDrawingSvgViewBox"
              xmlns="http://www.w3.org/2000/svg"
            >
              <template v-for="(shape, idx) in layoutDrawingOverlayShapes" :key="'layout-draw-' + idx">
                <line
                  v-if="shape.kind === 'line'"
                  :x1="shape.x1"
                  :y1="shape.y1"
                  :x2="shape.x2"
                  :y2="shape.y2"
                  :stroke="shape.stroke"
                  :stroke-width="shape.strokeWidth"
                  stroke-linecap="round"
                  vector-effect="non-scaling-stroke"
                />
                <g v-else-if="shape.kind === 'arrow'">
                  <line
                    :x1="shape.x1"
                    :y1="shape.y1"
                    :x2="shape.shaftX"
                    :y2="shape.shaftY"
                    :stroke="shape.stroke"
                    :stroke-width="shape.strokeWidth"
                    stroke-linecap="round"
                    vector-effect="non-scaling-stroke"
                  />
                  <polygon :points="shape.headPoints" :fill="shape.stroke" stroke="none" />
                </g>
                <rect
                  v-else-if="shape.kind === 'rectangle'"
                  :x="shape.x"
                  :y="shape.y"
                  :width="shape.width"
                  :height="shape.height"
                  :stroke="shape.stroke"
                  :stroke-width="shape.strokeWidth"
                  :fill="shape.fill"
                  :fill-opacity="shape.fillOpacity"
                  vector-effect="non-scaling-stroke"
                />
                <ellipse
                  v-else-if="shape.kind === 'ellipse'"
                  :cx="shape.cx"
                  :cy="shape.cy"
                  :rx="shape.rx"
                  :ry="shape.ry"
                  :stroke="shape.stroke"
                  :stroke-width="shape.strokeWidth"
                  :fill="shape.fill"
                  :fill-opacity="shape.fillOpacity"
                  vector-effect="non-scaling-stroke"
                />
                <circle
                  v-else-if="shape.kind === 'circle'"
                  :cx="shape.cx"
                  :cy="shape.cy"
                  :r="shape.r"
                  :stroke="shape.stroke"
                  :stroke-width="shape.strokeWidth"
                  :fill="shape.fill"
                  :fill-opacity="shape.fillOpacity"
                  vector-effect="non-scaling-stroke"
                />
                <polyline
                  v-else-if="shape.kind === 'pen'"
                  :points="shape.points"
                  :stroke="shape.stroke"
                  :stroke-width="shape.strokeWidth"
                  fill="none"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  vector-effect="non-scaling-stroke"
                />
                <polyline
                  v-else-if="shape.kind === 'polygon'"
                  :points="shape.points"
                  :stroke="shape.stroke"
                  :stroke-width="shape.strokeWidth"
                  fill="none"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  vector-effect="non-scaling-stroke"
                />
                <path
                  v-else-if="shape.kind === 'bezier'"
                  :d="shape.d"
                  :stroke="shape.stroke"
                  :stroke-width="shape.strokeWidth"
                  fill="none"
                  stroke-linecap="round"
                  vector-effect="non-scaling-stroke"
                />
                <circle
                  v-else-if="shape.kind === 'pixel'"
                  :cx="shape.cx"
                  :cy="shape.cy"
                  r="2"
                  :fill="shape.stroke"
                />
              </template>
            </svg>
            <svg
              v-if="showLayoutDrawingLiveOverlay"
              class="pointer-events-none absolute inset-0 z-[27] h-full w-full overflow-visible"
              preserveAspectRatio="none"
              :viewBox="layoutDrawingSvgViewBox"
              xmlns="http://www.w3.org/2000/svg"
            >
              <g
                v-if="drawingRubberBand"
                :stroke="drawStrokeColor"
                :stroke-width="Math.max(1, drawStrokeWidth)"
                stroke-linecap="round"
                stroke-linejoin="round"
                fill="none"
                vector-effect="non-scaling-stroke"
              >
                <line
                  v-if="drawingRubberBand.tool === 'line'"
                  :x1="drawingRubberBand.x0"
                  :y1="drawingRubberBand.y0"
                  :x2="drawingRubberBand.x1"
                  :y2="drawingRubberBand.y1"
                />
                <g v-else-if="drawingRubberBand.tool === 'arrow'">
                  <line
                    :x1="drawingRubberBand.x0"
                    :y1="drawingRubberBand.y0"
                    :x2="drawingRubberBand.shaftX"
                    :y2="drawingRubberBand.shaftY"
                  />
                  <polygon
                    :points="drawingRubberBand.headPoints"
                    :fill="drawStrokeColor"
                    stroke="none"
                  />
                </g>
                <rect
                  v-else-if="drawingRubberBand.tool === 'rectangle'"
                  :x="drawingRubberBand.left"
                  :y="drawingRubberBand.top"
                  :width="drawingRubberBand.w"
                  :height="drawingRubberBand.h"
                  :fill="drawingPreviewFill"
                  :fill-opacity="drawingPreviewFillOpacity"
                  :stroke="drawStrokeColor"
                />
                <ellipse
                  v-else-if="drawingRubberBand.tool === 'ellipse'"
                  :cx="drawingRubberBand.cx"
                  :cy="drawingRubberBand.cy"
                  :rx="drawingRubberBand.rx"
                  :ry="drawingRubberBand.ry"
                  :fill="drawingPreviewFill"
                  :fill-opacity="drawingPreviewFillOpacity"
                  :stroke="drawStrokeColor"
                />
                <circle
                  v-else-if="drawingRubberBand.tool === 'circle'"
                  :cx="drawingRubberBand.cx"
                  :cy="drawingRubberBand.cy"
                  :r="drawingRubberBand.r"
                  :fill="drawingPreviewFill"
                  :fill-opacity="drawingPreviewFillOpacity"
                  :stroke="drawStrokeColor"
                />
              </g>
              <g
                v-else-if="drawingTool === 'pen' && penDraftPoints.length > 1"
                :stroke="drawStrokeColor"
                :stroke-width="Math.max(1, drawStrokeWidth)"
                stroke-linecap="round"
                stroke-linejoin="round"
                fill="none"
                vector-effect="non-scaling-stroke"
              >
                <polyline :points="penDraftPointsAttr" />
              </g>
              <g
                v-else-if="drawingTool === 'polygon' && pathDraftPoints.length > 0"
                :stroke="drawStrokeColor"
                :stroke-width="Math.max(1, drawStrokeWidth)"
                stroke-linecap="round"
                stroke-linejoin="round"
                fill="none"
                vector-effect="non-scaling-stroke"
              >
                <polyline :points="polygonDraftPointsAttr" />
                <line
                  v-if="pathDraftHoverPos"
                  :x1="pathDraftPoints[pathDraftPoints.length - 1].x"
                  :y1="pathDraftPoints[pathDraftPoints.length - 1].y"
                  :x2="pathDraftHoverPos.x"
                  :y2="pathDraftHoverPos.y"
                  stroke-dasharray="6 5"
                  class="opacity-90"
                />
              </g>
              <g
                v-else-if="drawingTool === 'bezier' && pathDraftPoints.length > 0"
                :stroke="drawStrokeColor"
                :stroke-width="Math.max(1, drawStrokeWidth)"
                stroke-linecap="round"
                stroke-linejoin="round"
                fill="none"
                vector-effect="non-scaling-stroke"
              >
                <template v-if="pathDraftPoints.length < 3">
                  <polyline :points="polygonDraftPointsAttr" />
                  <line
                    v-if="pathDraftHoverPos"
                    :x1="pathDraftPoints[pathDraftPoints.length - 1].x"
                    :y1="pathDraftPoints[pathDraftPoints.length - 1].y"
                    :x2="pathDraftHoverPos.x"
                    :y2="pathDraftHoverPos.y"
                    stroke-dasharray="6 5"
                    class="opacity-85"
                  />
                </template>
                <path v-else-if="bezierDraftPathD" :d="bezierDraftPathD" />
              </g>
            </svg>
            <div
              v-if="isDrawingCaptureActive && zoomLayout"
              class="absolute inset-0 z-[30] touch-none cursor-crosshair"
              @mousedown="onDrawingSurfaceMouseDown"
              @click.stop="onDrawingSurfaceClick"
              @touchstart="onDrawingSurfaceTouchStart"
              @mousemove="onDrawingSurfaceMoveDrawingDraft"
              @mouseleave="onDrawingSurfaceLeaveDrawingDraft"
              @touchmove="onDrawingSurfaceMoveDrawingDraft"
            />
            <div
              v-if="zoomDetailSelecting"
              class="absolute z-[18] cursor-crosshair touch-none"
              :style="layoutBaseImgStyle"
              title="Arraste na foto para escolher a zona a ampliar"
              @mousedown.prevent="startZoomSelectOnLayout"
              @touchstart.prevent="startZoomSelectOnLayout"
            />
          </div>
        </div>
        <div
          v-else
          class="relative h-full w-full"
          @mousedown.self="deselectDrawing"
        >
        <img
          :src="displayImageUrl"
          alt="Imagem sendo editada"
          ref="imageRef"
          @load="onImageLoad"
          @mousedown="onImageMouseDown"
          @contextmenu.prevent="onImageContextMenu"
          @click="onImageClickUnified"
          @touchstart="onImageTouchStartUnified"
          class="editor-view-img h-full w-full"
          :class="[imageCursorClass, { 'cursor-crosshair': zoomDetailMode && zoomDetailSelecting }]"
          :style="maskBrushCursorStyle"
          @mousemove="onDrawingSurfaceMoveDrawingDraft"
          @mouseleave="onDrawingSurfaceLeaveDrawingDraft"
          @touchmove="onDrawingSurfaceMoveDrawingDraft"
        />
        <div
          v-if="zoomDetailMode && zoomDetailSelecting && zoomSelectImgStyle"
          class="pointer-events-none absolute z-[20] border-2 border-dashed border-violet-400 bg-violet-500/20"
          :style="zoomSelectImgStyle"
        />
        <div v-if="showCrop && !showingOriginal" class="pointer-events-none absolute inset-0 z-[30]">
          <div
            class="pointer-events-auto absolute z-[31] border-2 border-dashed border-white shadow-lg"
            :style="cropStyle"
          >
            <div
              class="absolute inset-0 z-0 cursor-move touch-none"
              title="Arrastar área de recorte"
              @mousedown.stop.prevent="startCropPan"
              @touchstart.stop.prevent="startCropPan"
            ></div>
            <div class="absolute -left-2 -top-2 z-10 h-4 w-4 cursor-nw-resize rounded-sm border border-gray-800 bg-white/90 touch-none"
              @mousedown.stop.prevent="startResize('crop', 'nw')"
              @touchstart.stop.prevent="startResize('crop', 'nw')"
            ></div>
            <div class="absolute -right-2 -top-2 z-10 h-4 w-4 cursor-ne-resize rounded-sm border border-gray-800 bg-white/90 touch-none"
              @mousedown.stop.prevent="startResize('crop', 'ne')"
              @touchstart.stop.prevent="startResize('crop', 'ne')"
            ></div>
            <div class="absolute -left-2 -bottom-2 z-10 h-4 w-4 cursor-sw-resize rounded-sm border border-gray-800 bg-white/90 touch-none"
              @mousedown.stop.prevent="startResize('crop', 'sw')"
              @touchstart.stop.prevent="startResize('crop', 'sw')"
            ></div>
            <div class="absolute -right-2 -bottom-2 z-10 h-4 w-4 cursor-se-resize rounded-sm border border-gray-800 bg-white/90 touch-none"
              @mousedown.stop.prevent="startResize('crop', 'se')"
              @touchstart.stop.prevent="startResize('crop', 'se')"
            ></div>
            <div class="absolute -left-2 top-1/2 z-10 h-4 w-4 -translate-y-1/2 cursor-w-resize rounded-sm border border-gray-800 bg-white/90 touch-none"
              @mousedown.stop.prevent="startResize('crop', 'w')"
              @touchstart.stop.prevent="startResize('crop', 'w')"
            ></div>
            <div class="absolute -right-2 top-1/2 z-10 h-4 w-4 -translate-y-1/2 cursor-e-resize rounded-sm border border-gray-800 bg-white/90 touch-none"
              @mousedown.stop.prevent="startResize('crop', 'e')"
              @touchstart.stop.prevent="startResize('crop', 'e')"
            ></div>
            <div class="absolute left-1/2 -top-2 z-10 h-4 w-4 -translate-x-1/2 cursor-n-resize rounded-sm border border-gray-800 bg-white/90 touch-none"
              @mousedown.stop.prevent="startResize('crop', 'n')"
              @touchstart.stop.prevent="startResize('crop', 'n')"
            ></div>
            <div class="absolute left-1/2 -bottom-2 z-10 h-4 w-4 -translate-x-1/2 cursor-s-resize rounded-sm border border-gray-800 bg-white/90 touch-none"
              @mousedown.stop.prevent="startResize('crop', 's')"
              @touchstart.stop.prevent="startResize('crop', 's')"
            ></div>
          </div>
          <div class="pointer-events-auto absolute top-3 left-1/2 z-[35] flex -translate-x-1/2 gap-1 rounded-full bg-black/75 px-2 py-1.5 shadow-lg">
            <button
              v-for="opt in cropAspectOptions"
              :key="opt.id"
              type="button"
              class="rounded-full px-2.5 py-1 text-[11px] text-white transition"
              :class="cropAspectPreset === opt.id ? 'bg-blue-600' : 'bg-white/10 hover:bg-white/20'"
              @click.stop="setCropAspectPreset(opt.id)"
            >
              {{ opt.label }}
            </button>
          </div>
          <div class="pointer-events-auto absolute bottom-24 left-1/2 z-[35] flex -translate-x-1/2 gap-2">
            <button
              type="button"
              class="rounded-full bg-blue-600 px-5 py-2 text-sm font-medium text-white shadow-lg hover:bg-blue-500"
              @click.stop="confirmCrop"
            >
              Aplicar recorte
            </button>
            <button
              type="button"
              class="rounded-full bg-black/70 px-4 py-2 text-sm text-white shadow-lg hover:bg-black/85"
              @click.stop="cancelCrop"
            >
              Cancelar
            </button>
          </div>
        </div>
        </div>
        <!-- Pré-visualização local dos desenhos em curso (evita esperar pelo /preview) -->
        <svg
          v-if="showDrawingLiveOverlay"
          class="pointer-events-none absolute inset-0 z-[31] h-full w-full overflow-visible"
          xmlns="http://www.w3.org/2000/svg"
        >
          <g v-if="drawingRubberBand" :stroke="drawStrokeColor" :stroke-width="Math.max(1, drawStrokeWidth)" stroke-linecap="round" stroke-linejoin="round" fill="none" vector-effect="non-scaling-stroke">
            <line
              v-if="drawingRubberBand.tool === 'line'"
              :x1="drawingRubberBand.x0"
              :y1="drawingRubberBand.y0"
              :x2="drawingRubberBand.x1"
              :y2="drawingRubberBand.y1"
            />
            <g v-else-if="drawingRubberBand.tool === 'arrow'">
              <line
                :x1="drawingRubberBand.x0"
                :y1="drawingRubberBand.y0"
                :x2="drawingRubberBand.shaftX"
                :y2="drawingRubberBand.shaftY"
              />
              <polygon
                :points="drawingRubberBand.headPoints"
                :fill="drawStrokeColor"
                stroke="none"
              />
            </g>
            <rect
              v-else-if="drawingRubberBand.tool === 'rectangle'"
              :x="drawingRubberBand.left"
              :y="drawingRubberBand.top"
              :width="drawingRubberBand.w"
              :height="drawingRubberBand.h"
              :fill="drawingPreviewFill"
              :fill-opacity="drawingPreviewFillOpacity"
              :stroke="drawStrokeColor"
            />
            <ellipse
              v-else-if="drawingRubberBand.tool === 'ellipse'"
              :cx="drawingRubberBand.cx"
              :cy="drawingRubberBand.cy"
              :rx="drawingRubberBand.rx"
              :ry="drawingRubberBand.ry"
              :fill="drawingPreviewFill"
              :fill-opacity="drawingPreviewFillOpacity"
              :stroke="drawStrokeColor"
            />
            <circle
              v-else-if="drawingRubberBand.tool === 'circle'"
              :cx="drawingRubberBand.cx"
              :cy="drawingRubberBand.cy"
              :r="drawingRubberBand.r"
              :fill="drawingPreviewFill"
              :fill-opacity="drawingPreviewFillOpacity"
              :stroke="drawStrokeColor"
            />
          </g>
          <g
            v-else-if="drawingTool === 'pen' && penDraftPoints.length > 1"
            :stroke="drawStrokeColor"
            :stroke-width="Math.max(1, drawStrokeWidth)"
            stroke-linecap="round"
            stroke-linejoin="round"
            fill="none"
            vector-effect="non-scaling-stroke"
          >
            <polyline :points="penDraftPointsAttr" />
          </g>
          <g
            v-else-if="drawingTool === 'polygon' && pathDraftPoints.length > 0"
            :stroke="drawStrokeColor"
            :stroke-width="Math.max(1, drawStrokeWidth)"
            stroke-linecap="round"
            stroke-linejoin="round"
            fill="none"
            vector-effect="non-scaling-stroke"
          >
            <polyline :points="polygonDraftPointsAttr" />
            <line
              v-if="pathDraftHoverPos"
              :x1="pathDraftPoints[pathDraftPoints.length - 1].x"
              :y1="pathDraftPoints[pathDraftPoints.length - 1].y"
              :x2="pathDraftHoverPos.x"
              :y2="pathDraftHoverPos.y"
              stroke-dasharray="6 5"
              class="opacity-90"
            />
          </g>
          <g
            v-else-if="drawingTool === 'bezier' && pathDraftPoints.length > 0"
            :stroke="drawStrokeColor"
            :stroke-width="Math.max(1, drawStrokeWidth)"
            stroke-linecap="round"
            stroke-linejoin="round"
            fill="none"
            vector-effect="non-scaling-stroke"
          >
            <circle v-if="pathDraftPoints.length === 1" :cx="pathDraftPoints[0].x" :cy="pathDraftPoints[0].y" r="3" />
            <template v-else-if="pathDraftPoints.length === 2">
              <line
                :x1="pathDraftPoints[0].x"
                :y1="pathDraftPoints[0].y"
                :x2="pathDraftPoints[1].x"
                :y2="pathDraftPoints[1].y"
              />
              <line
                v-if="pathDraftHoverPos"
                :x1="pathDraftPoints[1].x"
                :y1="pathDraftPoints[1].y"
                :x2="pathDraftHoverPos.x"
                :y2="pathDraftHoverPos.y"
                stroke-dasharray="6 5"
                class="opacity-85"
              />
            </template>
            <path v-else-if="bezierDraftPathD" :d="bezierDraftPathD" />
          </g>
        </svg>
        <!-- Área de desfoque local: só o retângulo captura eventos -->
        <div
          v-if="showBlurRegion && blurShapeMode === 'rectangle'"
          class="absolute inset-0 z-10 pointer-events-none"
        >
          <div
            class="absolute border-2 border-purple-400 border-dashed shadow-lg pointer-events-auto bg-black/20"
            :style="blurRegionStyle"
          >
            <!-- Arrastar zona (interior; alças ficam por cima) -->
            <div
              class="absolute inset-0 cursor-move z-0 touch-none"
              title="Arrastar zona de desfoque"
              @mousedown.stop.prevent="startBlurPan"
              @touchstart.stop.prevent="startBlurPan"
            ></div>
            <div class="absolute -left-1 -top-1 w-2 h-2 bg-purple-300 border border-purple-900 cursor-nw-resize z-10"
              @mousedown.stop.prevent="startResize('blur', 'nw')"
              @touchstart.stop.prevent="startResize('blur', 'nw')"
            ></div>
            <div class="absolute -right-1 -top-1 w-2 h-2 bg-purple-300 border border-purple-900 cursor-ne-resize z-10"
              @mousedown.stop.prevent="startResize('blur', 'ne')"
              @touchstart.stop.prevent="startResize('blur', 'ne')"
            ></div>
            <div class="absolute -left-1 -bottom-1 w-2 h-2 bg-purple-300 border border-purple-900 cursor-sw-resize z-10"
              @mousedown.stop.prevent="startResize('blur', 'sw')"
              @touchstart.stop.prevent="startResize('blur', 'sw')"
            ></div>
            <div class="absolute -right-1 -bottom-1 w-2 h-2 bg-purple-300 border border-purple-900 cursor-se-resize z-10"
              @mousedown.stop.prevent="startResize('blur', 'se')"
              @touchstart.stop.prevent="startResize('blur', 'se')"
            ></div>
            <div class="absolute -left-1 top-1/2 -translate-y-1/2 w-2 h-2 bg-purple-300 border border-purple-900 cursor-w-resize z-10"
              @mousedown.stop.prevent="startResize('blur', 'w')"
              @touchstart.stop.prevent="startResize('blur', 'w')"
            ></div>
            <div class="absolute -right-1 top-1/2 -translate-y-1/2 w-2 h-2 bg-purple-300 border border-purple-900 cursor-e-resize z-10"
              @mousedown.stop.prevent="startResize('blur', 'e')"
              @touchstart.stop.prevent="startResize('blur', 'e')"
            ></div>
            <div class="absolute left-1/2 -top-1 -translate-x-1/2 w-2 h-2 bg-purple-300 border border-purple-900 cursor-n-resize z-10"
              @mousedown.stop.prevent="startResize('blur', 'n')"
              @touchstart.stop.prevent="startResize('blur', 'n')"
            ></div>
            <div class="absolute left-1/2 -bottom-1 -translate-x-1/2 w-2 h-2 bg-purple-300 border border-purple-900 cursor-s-resize z-10"
              @mousedown.stop.prevent="startResize('blur', 's')"
              @touchstart.stop.prevent="startResize('blur', 's')"
            ></div>
          </div>
        </div>

        <div
          v-if="showPixelateRegion && pixelateShapeMode === 'rectangle'"
          class="absolute inset-0 z-10 pointer-events-none"
        >
          <div
            class="absolute border-2 border-amber-400 border-dashed shadow-lg pointer-events-auto bg-amber-500/15"
            :style="pixelateRegionStyle"
          >
            <div
              class="absolute inset-0 cursor-move z-0 touch-none"
              title="Arrastar zona de pixelização"
              @mousedown.stop.prevent="startPixelatePan"
              @touchstart.stop.prevent="startPixelatePan"
            ></div>
            <div class="absolute -left-1 -top-1 w-2 h-2 bg-amber-300 border border-amber-900 cursor-nw-resize z-10"
              @mousedown.stop.prevent="startResize('pixelate', 'nw')"
              @touchstart.stop.prevent="startResize('pixelate', 'nw')"
            ></div>
            <div class="absolute -right-1 -top-1 w-2 h-2 bg-amber-300 border border-amber-900 cursor-ne-resize z-10"
              @mousedown.stop.prevent="startResize('pixelate', 'ne')"
              @touchstart.stop.prevent="startResize('pixelate', 'ne')"
            ></div>
            <div class="absolute -left-1 -bottom-1 w-2 h-2 bg-amber-300 border border-amber-900 cursor-sw-resize z-10"
              @mousedown.stop.prevent="startResize('pixelate', 'sw')"
              @touchstart.stop.prevent="startResize('pixelate', 'sw')"
            ></div>
            <div class="absolute -right-1 -bottom-1 w-2 h-2 bg-amber-300 border border-amber-900 cursor-se-resize z-10"
              @mousedown.stop.prevent="startResize('pixelate', 'se')"
              @touchstart.stop.prevent="startResize('pixelate', 'se')"
            ></div>
            <div class="absolute -left-1 top-1/2 -translate-y-1/2 w-2 h-2 bg-amber-300 border border-amber-900 cursor-w-resize z-10"
              @mousedown.stop.prevent="startResize('pixelate', 'w')"
              @touchstart.stop.prevent="startResize('pixelate', 'w')"
            ></div>
            <div class="absolute -right-1 top-1/2 -translate-y-1/2 w-2 h-2 bg-amber-300 border border-amber-900 cursor-e-resize z-10"
              @mousedown.stop.prevent="startResize('pixelate', 'e')"
              @touchstart.stop.prevent="startResize('pixelate', 'e')"
            ></div>
            <div class="absolute left-1/2 -top-1 -translate-x-1/2 w-2 h-2 bg-amber-300 border border-amber-900 cursor-n-resize z-10"
              @mousedown.stop.prevent="startResize('pixelate', 'n')"
              @touchstart.stop.prevent="startResize('pixelate', 'n')"
            ></div>
            <div class="absolute left-1/2 -bottom-1 -translate-x-1/2 w-2 h-2 bg-amber-300 border border-amber-900 cursor-s-resize z-10"
              @mousedown.stop.prevent="startResize('pixelate', 's')"
              @touchstart.stop.prevent="startResize('pixelate', 's')"
            ></div>
          </div>
        </div>

        <!-- Textos Adicionados (coords naturais → object-fit: contain) -->
        <div
          v-for="(text, index) in texts"
          :key="'text-' + index"
          class="absolute z-20"
          :style="textItemOuterStyle(text)"
        >
          <div
            class="relative cursor-move select-none touch-none"
            :class="selectedTextIndex === index ? 'ring-2 ring-sky-400 ring-offset-1 ring-offset-transparent rounded px-1 py-0.5' : ''"
            title="Clique para editar; arraste para mover"
            @click.stop="selectText(index)"
            @mousedown.stop="startMovingText($event, index)"
            @touchstart.stop="startMovingText($event, index)"
          >
            <div :style="textItemInnerStyle(text)" class="whitespace-pre-wrap break-words max-w-[min(90vw,24rem)]">
              {{ text.content }}
            </div>
            <button
              type="button"
              title="Remover este texto"
              @click.stop="removeText(index)"
              class="absolute -top-2 -right-2 z-10 w-4 h-4 bg-red-500 rounded-full text-white flex items-center justify-center text-xs cursor-pointer"
            >×</button>
          </div>
        </div>

        <!-- Legenda — faixa branca por baixo da composição -->
        <div
          v-if="photoCaptionApplied && !zoomLayout && !showingOriginal"
          class="absolute z-[21] flex items-center justify-center overflow-hidden bg-white text-center shadow-sm ring-1 ring-black/10"
          :style="photoCaptionBandStyle"
        >
          <span
            class="block w-full whitespace-pre-wrap break-words px-1"
            :style="photoCaptionTextStyle"
          >{{ formatCaptionText(photoCaptionApplied.number, photoCaptionApplied.description) }}</span>
        </div>

        <!-- Imagens arrastadas da barra lateral (só folha em branco) -->
        <div
          v-for="ov in imageOverlays"
          v-show="isBlankCanvas && !zoomLayout && !shouldBakeImageOverlaysInPreview"
          :key="ov.id"
          class="absolute z-[22] select-none touch-none overflow-visible ring-1 ring-white/70"
          :class="{
            'pointer-events-none': !canMoveImageOverlays,
            'ring-2 ring-sky-400': selectedOverlayId === ov.id
          }"
          :style="overlayBoxStyle(ov)"
        >
            <div
              class="relative h-full w-full cursor-move overflow-visible rounded-sm bg-black/20"
              :title="canMoveImageOverlays ? 'Arrastar para mover' : 'Selecione outra ferramenta ou feche Desenho para mover'"
              @mousedown.stop="startOverlayMove($event, ov.id)"
              @touchstart.stop="startOverlayMove($event, ov.id)"
            >
            <div class="absolute inset-0 overflow-hidden rounded-sm">
              <img :src="ov.src" alt="" class="pointer-events-none h-full w-full" draggable="false" style="object-fit: fill" />
            </div>
            <div
              class="absolute -bottom-1 -right-1 z-10 h-3 w-3 cursor-se-resize rounded-sm border border-sky-300 bg-sky-600/90"
              title="Redimensionar"
              @mousedown.stop.prevent="startOverlayResize($event, ov.id)"
              @touchstart.stop.prevent="startOverlayResize($event, ov.id)"
            ></div>
            <button
              type="button"
              title="Remover imagem"
              class="absolute -right-2 -top-2 z-10 flex h-4 w-4 cursor-pointer items-center justify-center rounded-full bg-red-500 text-xs text-white shadow"
              @click.stop="removeImageOverlay(ov.id)"
            >×</button>
          </div>
        </div>
        <!-- Desenhos guardados por cima da imagem (camada vectorial até guardar) -->
        <svg
          v-if="showDrawingsOverlay"
          class="pointer-events-none absolute inset-0 z-[27] h-full w-full overflow-visible"
          xmlns="http://www.w3.org/2000/svg"
        >
          <template v-for="(shape, idx) in drawingOverlayShapes" :key="'draw-overlay-' + idx">
            <line
              v-if="shape.kind === 'line'"
              :x1="shape.x1"
              :y1="shape.y1"
              :x2="shape.x2"
              :y2="shape.y2"
              :stroke="shape.stroke"
              :stroke-width="shape.strokeWidth"
              stroke-linecap="round"
              vector-effect="non-scaling-stroke"
            />
            <g v-else-if="shape.kind === 'arrow'">
              <line
                :x1="shape.x1"
                :y1="shape.y1"
                :x2="shape.shaftX"
                :y2="shape.shaftY"
                :stroke="shape.stroke"
                :stroke-width="shape.strokeWidth"
                stroke-linecap="round"
                vector-effect="non-scaling-stroke"
              />
              <polygon :points="shape.headPoints" :fill="shape.stroke" stroke="none" />
            </g>
            <rect
              v-else-if="shape.kind === 'rectangle'"
              :x="shape.x"
              :y="shape.y"
              :width="shape.width"
              :height="shape.height"
              :stroke="shape.stroke"
              :stroke-width="shape.strokeWidth"
              :fill="shape.fill"
              :fill-opacity="shape.fillOpacity"
              vector-effect="non-scaling-stroke"
            />
            <ellipse
              v-else-if="shape.kind === 'ellipse'"
              :cx="shape.cx"
              :cy="shape.cy"
              :rx="shape.rx"
              :ry="shape.ry"
              :stroke="shape.stroke"
              :stroke-width="shape.strokeWidth"
              :fill="shape.fill"
              :fill-opacity="shape.fillOpacity"
              vector-effect="non-scaling-stroke"
            />
            <circle
              v-else-if="shape.kind === 'circle'"
              :cx="shape.cx"
              :cy="shape.cy"
              :r="shape.r"
              :stroke="shape.stroke"
              :stroke-width="shape.strokeWidth"
              :fill="shape.fill"
              :fill-opacity="shape.fillOpacity"
              vector-effect="non-scaling-stroke"
            />
            <polyline
              v-else-if="shape.kind === 'pen'"
              :points="shape.points"
              :stroke="shape.stroke"
              :stroke-width="shape.strokeWidth"
              fill="none"
              stroke-linecap="round"
              stroke-linejoin="round"
              vector-effect="non-scaling-stroke"
            />
            <polyline
              v-else-if="shape.kind === 'polygon'"
              :points="shape.points"
              :stroke="shape.stroke"
              :stroke-width="shape.strokeWidth"
              fill="none"
              stroke-linecap="round"
              stroke-linejoin="round"
              vector-effect="non-scaling-stroke"
            />
            <path
              v-else-if="shape.kind === 'bezier'"
              :d="shape.d"
              :stroke="shape.stroke"
              :stroke-width="shape.strokeWidth"
              fill="none"
              stroke-linecap="round"
              vector-effect="non-scaling-stroke"
            />
            <circle
              v-else-if="shape.kind === 'pixel'"
              :cx="shape.cx"
              :cy="shape.cy"
              r="2"
              :fill="shape.stroke"
            />
          </template>
        </svg>
        <!-- Áreas para mover desenhos (fora do modo Desenho) -->
        <div v-if="canMoveDrawings" class="pointer-events-none absolute inset-0 z-[28]">
          <div
            v-for="box in drawingHitBoxes"
            :key="'draw-hit-' + box.index"
            class="pointer-events-auto absolute touch-none rounded-sm border transition-colors"
            :class="
              movingDrawingIndex === box.index || selectedDrawingIndex === box.index
                ? 'cursor-move border-sky-300/45 bg-sky-400/[0.04]'
                : 'cursor-move border-transparent hover:border-sky-300/25'
            "
            :style="box.style"
            title="Arrastar desenho"
            @mousedown.stop.prevent="startDrawingMove($event, box.index)"
            @touchstart.stop.prevent="startDrawingMove($event, box.index)"
          />
        </div>
        <!-- Camada de desenho por cima da composição (folha + imagens arrastadas) -->
        <div
          v-if="isDrawingCaptureActive && !zoomLayout"
          class="absolute inset-0 z-[30] touch-none"
          :class="imageCursorClass"
          @mousedown="onDrawingSurfaceMouseDown"
          @click="onDrawingSurfaceClick"
          @touchstart="onDrawingSurfaceTouchStart"
          @mousemove="onDrawingSurfaceMoveDrawingDraft"
          @mouseleave="onDrawingSurfaceLeaveDrawingDraft"
          @touchmove="onDrawingSurfaceMoveDrawingDraft"
        />
        </div>
      </div>

      <!-- Zoom da vista (não altera a imagem guardada) -->
      <div class="absolute right-16 top-4 z-[48] flex flex-col items-center gap-1 rounded-lg bg-black/60 p-1.5 shadow-lg">
        <div
          v-if="galleryTotal > 1"
          class="mb-0.5 flex w-full items-center justify-between gap-0.5 border-b border-white/15 pb-1"
        >
          <button
            type="button"
            title="Imagem anterior (← ou arrastar para a direita)"
            class="flex h-7 flex-1 items-center justify-center rounded-md text-white hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-35"
            :disabled="!canGalleryPrev"
            @click="navigateGallery('prev')"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <span class="min-w-[2.25rem] text-center text-[10px] tabular-nums text-white/75">
            {{ galleryPositionLabel }}
          </span>
          <button
            type="button"
            title="Imagem seguinte (→ ou arrastar para a esquerda)"
            class="flex h-7 flex-1 items-center justify-center rounded-md text-white hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-35"
            :disabled="!canGalleryNext"
            @click="navigateGallery('next')"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>
        <button type="button" title="Aumentar zoom" class="flex h-8 w-8 items-center justify-center rounded-md text-white hover:bg-white/20" @click="zoomViewIn">+</button>
        <span class="min-w-[2.75rem] text-center text-[11px] tabular-nums text-white/90">{{ viewZoomPercent }}%</span>
        <button type="button" title="Reduzir zoom" class="flex h-8 w-8 items-center justify-center rounded-md text-white hover:bg-white/20" @click="zoomViewOut">−</button>
        <button type="button" title="Zoom 100% e centrar" class="w-full rounded-md px-1 py-0.5 text-[10px] text-white/80 hover:bg-white/20" @click="resetViewTransform">100%</button>
        <button
          type="button"
          title="Mover imagem (arrastar). Também: botão do meio ou barra de espaço + arrastar"
          class="flex h-8 w-8 items-center justify-center rounded-md text-white hover:bg-white/20"
          :class="{ 'bg-white/25 ring-1 ring-white/40': viewPanHandMode }"
          @click="viewPanHandMode = !viewPanHandMode"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9V6a1 1 0 00-2 0v3M6 9h12M6 15h12M8 18v3a1 1 0 002 0v-3" />
          </svg>
        </button>
        <button
          v-if="hasChanges"
          type="button"
          title="Segurar para ver a imagem original"
          class="mt-1 w-full min-w-[2.75rem] rounded-md border-t border-white/15 pt-1.5 text-[10px] font-medium text-white/90 hover:bg-white/20 select-none touch-none"
          :class="{ 'bg-white/25 ring-1 ring-white/40': showingOriginal }"
          @pointerdown.prevent="showingOriginal = true"
          @pointerup="showingOriginal = false"
          @pointerleave="showingOriginal = false"
          @pointercancel="showingOriginal = false"
        >
          Original
        </button>
      </div>

      <!-- Botão para mostrar/esconder controles -->
      <button
        type="button"
        :title="showControls ? 'Esconder barra de ferramentas' : 'Mostrar barra de ferramentas'"
        @click="showControls = !showControls"
        class="absolute bottom-4 right-4 z-[45] p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
      >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path v-if="!showControls" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <!-- Controles de Edição (z-index acima da zona de desfoque para o slider funcionar) -->
      <div 
        class="absolute bottom-0 left-0 right-0 z-40 p-4 bg-black bg-opacity-50 transition-transform duration-300"
        :class="{'translate-y-full': !showControls && !activeControl && !drawingTool && !showDrawingMenu && !showPixelateMenu && !showBlurMenu && !showFilterMenu && !zoomDetailMode}"
      >
        <div class="flex justify-center space-x-4" v-if="!activeControl">
          <!-- Botão de Crop -->
          <button
            type="button"
            title="Recortar – definir área na imagem"
            @click="toggleCrop"
            class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
            :class="{ 'bg-blue-500': showCrop }"
          >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
          </button>

          <div class="relative">
            <button
              type="button"
              title="Filtros — P&B, sépia, documento, vívido"
              @click="toggleFilterMenu"
              class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
              :class="{
                'bg-indigo-600': activeFilterPreset,
                'ring-2 ring-indigo-200/60': showFilterMenu
              }"
            >
              <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h7l2 2h4a2 2 0 012 2v12a4 4 0 01-4 4z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10h6M9 14h4" />
              </svg>
            </button>
            <div
              v-show="showFilterMenu"
              class="absolute bottom-full left-1/2 z-[55] mb-2 w-[min(100vw-2rem,16rem)] ml-[calc(min(100vw-2rem,16rem)/-2)] rounded-2xl border border-white/15 bg-black/90 px-3 py-3 shadow-2xl backdrop-blur-sm"
              role="menu"
              @click.stop
            >
              <p class="mb-2 text-center text-[11px] text-white/70">Filtros rápidos</p>
              <div class="flex flex-col gap-1.5">
                <button
                  v-for="fp in filterPresetList"
                  :key="fp.id"
                  type="button"
                  class="rounded-xl border px-3 py-2 text-left transition"
                  :class="activeFilterPreset === fp.id || (fp.id === 'neutral' && !activeFilterPreset)
                    ? 'border-indigo-400 bg-indigo-900/50 text-white'
                    : 'border-white/20 bg-white/5 text-white hover:bg-white/15'"
                  @click="applyFilterPreset(fp.id)"
                >
                  <span class="block text-sm font-medium">{{ fp.label }}</span>
                  <span class="block text-[10px] text-white/60">{{ fp.desc }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Gama (curva de tons) -->
          <button
            type="button"
            title="Gama — tons médios (centro = neutro)"
            @click="toggleControl('gamma')"
            class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
            :class="{ 'bg-blue-500': activeControl === 'gamma' }"
          >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 18c3-8 5-12 8-12s5 4 8 12M4 6c2 3 4 5 8 5s6-2 8-5" />
            </svg>
          </button>

          <div class="relative">
            <button
              type="button"
              title="Desfoque — zona ou imagem inteira"
              @click="toggleBlurMenu"
              class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
              :class="{
                'bg-purple-600': showBlurRegion || committedBlurRegion || committedBlurMask || blurApplyGlobal,
                'bg-blue-500': activeControl === 'blur' && !showBlurRegion && !committedBlurRegion && !committedBlurMask && !blurApplyGlobal,
                'ring-2 ring-purple-200/60': showBlurMenu
              }"
            >
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
              </svg>
            </button>
            <div
              v-show="showBlurMenu"
              class="absolute bottom-full left-1/2 z-[55] mb-2 w-[min(100vw-2rem,18rem)] ml-[calc(min(100vw-2rem,18rem)/-2)] rounded-2xl border border-white/15 bg-black/90 px-3 py-3 shadow-2xl backdrop-blur-sm"
              role="menu"
              @click.stop
            >
              <p class="mb-2 text-center text-[11px] text-white/70">Tipo de desfoque</p>
              <div class="flex flex-col gap-2">
                <button
                  type="button"
                  title="Ajustar retângulo na foto; intensidade no slider em baixo"
                  class="rounded-xl border border-white/20 bg-white/5 px-3 py-2 text-left text-sm text-white transition hover:bg-white/15"
                  @click="selectBlurRectangle"
                >
                  Só numa zona (retângulo)
                </button>
                <button
                  type="button"
                  title="Borracha — arrastar na foto com o botão premido; intensidade no slider"
                  class="rounded-xl border border-white/20 bg-white/5 px-3 py-2 text-left text-sm text-white transition hover:bg-white/15"
                  @click="selectBlurBrush"
                >
                  Área livre (borracha)
                </button>
                <button
                  type="button"
                  title="Desfocar a imagem toda — use o slider em baixo"
                  class="rounded-xl border border-white/20 bg-white/5 px-3 py-2 text-left text-sm text-white transition hover:bg-white/15"
                  @click="selectBlurGlobal"
                >
                  Toda a imagem
                </button>
              </div>
            </div>
          </div>

          <div class="relative">
            <button
              type="button"
              title="Pixelização — zona ou imagem inteira"
              @click="togglePixelateMenu"
              class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
              :class="{
                'bg-amber-600': showPixelateRegion || committedPixelateRegion || committedPixelateMask || pixelateApplyGlobal,
                'bg-blue-500': activeControl === 'pixelate' && !showPixelateRegion && !committedPixelateRegion && !committedPixelateMask && !pixelateApplyGlobal,
                'ring-2 ring-amber-200/60': showPixelateMenu
              }"
            >
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h4v4H4V4zm6 0h4v4h-4V4zm6 0h4v4h-4V4zM4 10h4v4H4v-4zm6 0h4v4h-4v-4zm6 0h4v4h-4v-4zM4 16h4v4H4v-4zm6 0h4v4h-4v-4zm6 0h4v4h-4v-4z" />
              </svg>
            </button>
            <div
              v-show="showPixelateMenu"
              class="absolute bottom-full left-1/2 z-[55] mb-2 w-[min(100vw-2rem,18rem)] ml-[calc(min(100vw-2rem,18rem)/-2)] rounded-2xl border border-white/15 bg-black/90 px-3 py-3 shadow-2xl backdrop-blur-sm"
              role="menu"
              @click.stop
            >
              <p class="mb-2 text-center text-[11px] text-white/70">Tipo de pixelização</p>
              <div class="flex flex-col gap-2">
                <button
                  type="button"
                  title="Ajustar retângulo na foto; intensidade no slider em baixo"
                  class="rounded-xl border border-white/20 bg-white/5 px-3 py-2 text-left text-sm text-white transition hover:bg-white/15"
                  @click="selectPixelateRectangle"
                >
                  Só numa zona (retângulo)
                </button>
                <button
                  type="button"
                  title="Borracha — arrastar na foto com o botão premido; intensidade no slider"
                  class="rounded-xl border border-white/20 bg-white/5 px-3 py-2 text-left text-sm text-white transition hover:bg-white/15"
                  @click="selectPixelateBrush"
                >
                  Área livre (borracha)
                </button>
                <button
                  type="button"
                  title="Pixelizar a imagem toda — tamanho do bloco no slider"
                  class="rounded-xl border border-white/20 bg-white/5 px-3 py-2 text-left text-sm text-white transition hover:bg-white/15"
                  @click="selectPixelateGlobal"
                >
                  Toda a imagem
                </button>
              </div>
            </div>
          </div>

          <!-- Botão de Brilho -->
          <button
            type="button"
            title="Brilho"
            @click="toggleControl('brightness')"
            class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
            :class="{ 'bg-blue-500': activeControl === 'brightness' }"
          >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </button>

          <!-- Botão de Contraste -->
          <button
            type="button"
            title="Contraste"
            @click="toggleControl('contrast')"
            class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
            :class="{ 'bg-blue-500': activeControl === 'contrast' }"
          >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </button>

          <!-- Botão de Saturação -->
          <button
            type="button"
            title="Saturação"
            @click="toggleControl('saturation')"
            class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
            :class="{ 'bg-blue-500': activeControl === 'saturation' }"
          >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4.586a1 1 0 01.707.293l7.414 7.414a1 1 0 01.293.707V17a4 4 0 01-4 4H7z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3" />
            </svg>
          </button>

          <button
            type="button"
            title="Nitidez"
            @click="toggleControl('sharpen')"
            class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
            :class="{ 'bg-blue-500': activeControl === 'sharpen' }"
          >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 104.243-4.243 3 3 0 00-4.243 4.243z" />
            </svg>
          </button>

          <div class="relative">
            <button
              type="button"
              :title="showDrawingMenu || drawingTool ? 'Fechar desenho' : 'Desenhar na imagem — ferramentas'"
              @click="toggleDrawingMenu"
              class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
              :class="{ 'bg-emerald-600': showDrawingMenu || drawingTool }"
            >
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
              </svg>
            </button>
            <!-- Sem translate no painel: transform no ancestral quebra tooltips nativos (title) no Chrome/WebKit -->
            <div
              v-show="showDrawingMenu || drawingTool"
              class="absolute bottom-full left-1/2 z-[55] mb-2 w-[min(100vw-2rem,22rem)] ml-[calc(min(100vw-2rem,22rem)/-2)] rounded-2xl border border-white/15 bg-black/90 px-3 py-3 shadow-2xl backdrop-blur-sm"
              role="menu"
              @click.stop
            >
              <div class="mb-2 flex items-center justify-between gap-2 border-b border-white/10 pb-2">
                <span class="text-xs font-medium text-white/90">
                  {{ drawingTool ? drawingToolLabel : 'Desenho' }}
                </span>
                <button
                  type="button"
                  title="Fechar painel de desenho"
                  class="rounded-full bg-white/20 p-1 hover:bg-white/30"
                  @click="closeDrawingPanel"
                >
                  <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <div v-show="showDrawingMenu">
              <p class="mb-2 text-center text-[11px] text-white/70">Escolha a ferramenta</p>
              <div class="grid grid-cols-4 gap-2">
                <button type="button" title="Caneta — arrastar livremente" @click="selectDrawingTool('pen')" class="flex h-11 w-11 items-center justify-center rounded-xl border text-white transition" :class="drawingTool === 'pen' ? 'border-emerald-400 bg-emerald-800/80' : 'border-white/20 bg-white/5 hover:bg-white/15'">
                  <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                </button>
                <button type="button" title="Linha — arrastar" @click="selectDrawingTool('line')" class="flex h-11 w-11 items-center justify-center rounded-xl border text-white transition" :class="drawingTool === 'line' ? 'border-emerald-400 bg-emerald-800/80' : 'border-white/20 bg-white/5 hover:bg-white/15'">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 20L20 4" /></svg>
                </button>
                <button type="button" title="Seta — arrastar" @click="selectDrawingTool('arrow')" class="flex h-11 w-11 items-center justify-center rounded-xl border text-white transition" :class="drawingTool === 'arrow' ? 'border-emerald-400 bg-emerald-800/80' : 'border-white/20 bg-white/5 hover:bg-white/15'">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h12M13 7l5 5-5 5" />
                  </svg>
                </button>
                <button type="button" title="Retângulo — arrastar" @click="selectDrawingTool('rectangle')" class="flex h-11 w-11 items-center justify-center rounded-xl border text-white transition" :class="drawingTool === 'rectangle' ? 'border-emerald-400 bg-emerald-800/80' : 'border-white/20 bg-white/5 hover:bg-white/15'">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16v12H4V6z" /></svg>
                </button>
                <button type="button" title="Elipse — arrastar" @click="selectDrawingTool('ellipse')" class="flex h-11 w-11 items-center justify-center rounded-xl border text-white transition" :class="drawingTool === 'ellipse' ? 'border-emerald-400 bg-emerald-800/80' : 'border-white/20 bg-white/5 hover:bg-white/15'">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><ellipse cx="12" cy="12" rx="9" ry="6" stroke="currentColor" stroke-width="2" /></svg>
                </button>
                <button type="button" title="Círculo — arrastar" @click="selectDrawingTool('circle')" class="flex h-11 w-11 items-center justify-center rounded-xl border text-white transition" :class="drawingTool === 'circle' ? 'border-emerald-400 bg-emerald-800/80' : 'border-white/20 bg-white/5 hover:bg-white/15'">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="7" stroke="currentColor" stroke-width="2" /></svg>
                </button>
                <button type="button" title="Polígono — cliques, depois fechar" @click="selectDrawingTool('polygon')" class="flex h-11 w-11 items-center justify-center rounded-xl border text-white transition" :class="drawingTool === 'polygon' ? 'border-emerald-400 bg-emerald-800/80' : 'border-white/20 bg-white/5 hover:bg-white/15'">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 18L9 6l5 12 6-8" /></svg>
                </button>
                <button type="button" title="Bézier — 4 cliques" @click="selectDrawingTool('bezier')" class="flex h-11 w-11 items-center justify-center rounded-xl border text-white transition" :class="drawingTool === 'bezier' ? 'border-emerald-400 bg-emerald-800/80' : 'border-white/20 bg-white/5 hover:bg-white/15'">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 17c3-6 6-10 9-10s6 4 9 10M3 7c3 4 6 6 9 6s6-2 9-6" /></svg>
                </button>
                <button type="button" title="Píxel — um clique" @click="selectDrawingTool('pixel')" class="flex h-11 w-11 items-center justify-center rounded-xl border text-white transition" :class="drawingTool === 'pixel' ? 'border-emerald-400 bg-emerald-800/80' : 'border-white/20 bg-white/5 hover:bg-white/15'">
                  <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><rect x="10" y="10" width="4" height="4" rx="0.5" /></svg>
                </button>
                <button type="button" title="Preencher (fill) — cuidado" @click="selectDrawingTool('fill')" class="flex h-11 w-11 items-center justify-center rounded-xl border text-white transition" :class="drawingTool === 'fill' ? 'border-emerald-400 bg-emerald-800/80' : 'border-white/20 bg-white/5 hover:bg-white/15'">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                </button>
              </div>
              </div>
              <div v-if="drawingTool" class="mt-3 space-y-2 border-t border-white/10 pt-3 text-[11px] text-white" :class="{ '!mt-0 !border-t-0 !pt-0': !showDrawingMenu }">
                <div class="flex flex-wrap items-center justify-center gap-2">
                  <label class="flex items-center gap-1">Traço <input type="color" v-model="drawStrokeColor" class="h-6 w-8 cursor-pointer rounded border-0 bg-transparent" /></label>
                  <label class="flex items-center gap-1"><input v-model="drawFillEnabled" type="checkbox" class="rounded" /> Preench.</label>
                  <label v-if="drawFillEnabled" class="flex items-center gap-1">Cor <input type="color" v-model="drawFillColor" class="h-6 w-8 cursor-pointer rounded border-0 bg-transparent" /></label>
                </div>
                <div class="flex items-center justify-center gap-2">
                  <span class="text-white/60">Esp.</span>
                  <input v-model.number="drawStrokeWidth" type="range" min="0" max="24" class="h-1 w-28 accent-emerald-500" />
                  <button type="button" class="rounded bg-white/10 px-2 py-0.5 hover:bg-white/20" title="Anular último" @click="undoLastDrawing">↶</button>
                  <button type="button" class="rounded bg-red-900/50 px-2 py-0.5 hover:bg-red-800/70" title="Limpar desenhos" @click="clearDrawings">✕</button>
                </div>
                <div v-if="drawingTool === 'polygon'" class="flex flex-col gap-1 text-center text-amber-100/90">
                  <span>Vértices na foto (≥3)</span>
                  <div class="flex justify-center gap-2">
                    <button type="button" title="Fechar polígono e aplicar" class="rounded bg-emerald-700 px-2 py-1 text-xs" @click="commitPolygonFromPath">Fechar</button>
                    <button type="button" title="Limpar vértices do rascunho" class="rounded bg-white/10 px-2 py-1 text-xs" @click="clearPathDraft">Limpar</button>
                  </div>
                </div>
                <p v-if="drawingTool === 'bezier'" class="text-center text-amber-200/90">4 pontos (curva cúbica)</p>
                <p v-if="(drawings.length || layoutDrawings.length) && (drawingTool || showDrawingMenu)" class="text-center text-emerald-200/90">
                  Feche o Desenho para mover os traços na foto.
                </p>
              </div>
            </div>
          </div>

          <!-- Botão de Flip Horizontal -->
          <button
            type="button"
            title="Espelhar na horizontal"
            @click="toggleFlip('horizontal')"
            class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
            :class="{ 'bg-blue-500': flipHorizontal }"
          >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
          </button>

          <!-- Botão de Flip Vertical -->
          <button
            type="button"
            title="Espelhar na vertical"
            @click="toggleFlip('vertical')"
            class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
            :class="{ 'bg-blue-500': flipVertical }"
          >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
            </svg>
          </button>

          <!-- Botão de Rotação -->
          <button
            type="button"
            title="Rodar 90° no sentido horário"
            @click="rotateImage"
            class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
          >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </button>

          <!-- Botão de Adicionar Texto -->
          <button
            type="button"
            title="Adicionar texto sobre a imagem"
            @click="toggleControl('text')"
            class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
            :class="{ 'bg-blue-500': activeControl === 'text' }"
          >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
          </button>
          <button
            type="button"
            title="Legendas — faixa branca por baixo (estilo Word)"
            @click="toggleControl('caption')"
            class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
            :class="{ 'bg-blue-500': activeControl === 'caption' || hasActiveCaptions }"
          >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h10M4 14h14M4 18h8" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17l3 3m0 0l3-3m-3 3V14" />
            </svg>
          </button>
          <button
            type="button"
            title="Marca de água — texto ou logótipo no canto"
            @click="toggleControl('watermark')"
            class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
            :class="{ 'bg-blue-500': activeControl === 'watermark' || watermarkApplied }"
          >
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
            </svg>
          </button>
          <div class="relative">
            <button
              type="button"
              :title="zoomDetailMode ? 'Fechar painel (o zoom mantém-se)' : (zoomLayout ? 'Editar detalhe ampliado' : 'Detalhe ampliado — escolher zona e colocar no ecrã')"
              @click="toggleZoomDetailMode"
              class="p-2 rounded-full bg-black bg-opacity-50 text-white hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white"
              :class="{ 'bg-violet-600': zoomDetailMode || zoomLayout, 'ring-2 ring-violet-200/60': zoomDetailMode }"
            >
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
              </svg>
            </button>
            <div
              v-show="zoomDetailMode"
              class="absolute bottom-full left-1/2 z-[55] mb-2 w-[min(100vw-2rem,16rem)] ml-[calc(min(100vw-2rem,16rem)/-2)] rounded-2xl border border-white/15 bg-black/90 px-3 py-2.5 text-center text-[11px] text-white/80 shadow-2xl backdrop-blur-sm"
              @click.stop
            >
              <p v-if="!zoomLayout">Arraste na foto para escolher a zona.</p>
              <p v-else-if="zoomDetailSelecting">Arraste na foto reduzida para outra zona.</p>
              <p v-else-if="!selectedZoomCalloutId && zoomCallouts.length">Toque num detalhe para o selecionar.</p>
              <p v-else-if="!selectedZoomCalloutId">Foto reduzida; arraste os detalhes para posicionar.</p>
              <p v-else class="text-violet-200">Detalhe {{ selectedZoomCalloutIndex + 1 }} selecionado</p>
              <label v-if="selectedZoomCalloutId" class="mt-2 flex flex-col gap-1 text-left">
                <span class="flex justify-between text-white/90">
                  <span>Ampliação deste detalhe</span>
                  <span class="tabular-nums text-violet-200">{{ selectedCalloutZoomLevel }}%</span>
                </span>
                <input
                  v-model.number="selectedCalloutZoomLevel"
                  type="range"
                  min="50"
                  max="400"
                  step="5"
                  class="h-1.5 w-full accent-violet-500"
                />
              </label>
              <label v-else class="mt-2 flex flex-col gap-1 text-left">
                <span class="flex justify-between text-white/90">
                  <span>Ampliação (próximo detalhe)</span>
                  <span class="tabular-nums text-violet-200">{{ zoomDetailLevel }}%</span>
                </span>
                <input
                  v-model.number="zoomDetailLevel"
                  type="range"
                  min="50"
                  max="400"
                  step="5"
                  class="h-1.5 w-full accent-violet-500"
                />
              </label>
              <button
                v-if="zoomLayout"
                type="button"
                class="mt-2 w-full rounded-lg bg-violet-700/80 px-2 py-1 text-xs text-white hover:bg-violet-600"
                @click="startAnotherZoomCallout"
              >
                + Nova zona
              </button>
              <button
                v-if="zoomLayout"
                type="button"
                class="mt-1.5 w-full rounded-lg px-2 py-1 text-xs text-white/70 hover:bg-white/10"
                @click="removeAllZoomCallouts"
              >
                Remover todos os detalhes
              </button>
            </div>
          </div>
          <input
            ref="watermarkImageInputRef"
            type="file"
            accept="image/*"
            class="hidden"
            @change="onWatermarkImageInput"
          />
        </div>

        <!-- Controle Ativo -->
        <div v-if="activeControl" class="absolute bottom-0 left-0 right-0 z-40 p-4 bg-black bg-opacity-50">
          <div class="flex flex-col items-center">
            <div class="flex items-center justify-between w-full max-w-xs">
              <label class="text-white text-sm">{{ activeControl === 'gamma' ? 'Curva e gama' : getControlLabel(activeControl) }}:</label>
              <button
                type="button"
                title="Fechar este controlo"
                @click="closeActiveControlPanel"
                class="p-1 rounded-full bg-white bg-opacity-20 hover:bg-opacity-30"
              >
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            
            <!-- Controles de Texto -->
            <div v-if="activeControl === 'text'" class="w-full max-w-xs mt-2 max-h-[50vh] overflow-y-auto space-y-3">
              <p v-if="selectedTextIndex !== null" class="text-center text-[11px] text-sky-200">
                A editar texto {{ selectedTextIndex + 1 }}
              </p>
              <p v-else class="text-center text-[11px] text-white/60">
                Novo texto: clique na imagem para colocar
              </p>
              <div>
                <label class="block text-white text-sm mb-1">Conteúdo</label>
                <textarea
                  v-model="textContent"
                  rows="3"
                  placeholder="Escreva aqui (Enter = nova linha)"
                  class="w-full resize-y rounded bg-white/20 p-2 text-sm text-white placeholder-gray-400"
                  @input="onTextPanelInput"
                />
              </div>
              <div>
                <label class="block text-white text-sm mb-1">Tamanho: {{ textSize }}px (ecrã)</label>
                <input v-model.number="textSize" type="range" min="12" max="120" class="w-full accent-white" @input="onTextPanelInput" />
              </div>
              <div>
                <label class="block text-white text-sm mb-1">Cor</label>
                <div class="flex flex-wrap gap-2">
                  <button v-for="color in textColors" :key="color" type="button" :title="'Cor: ' + color" class="h-6 w-6 rounded-full border border-white" :style="{ backgroundColor: color }" :class="{ 'ring-2 ring-white': textColor === color }" @click="textColor = color; onTextPanelInput()" />
                  <input v-model="textColor" type="color" class="h-6 w-8 cursor-pointer rounded border-0 bg-transparent" @input="onTextPanelInput" />
                </div>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="rounded-lg px-3 py-1.5 text-xs text-white" :class="textBold ? 'bg-blue-600' : 'bg-white/15'" @click="textBold = !textBold; onTextPanelInput()">Negrito</button>
                <button v-for="opt in textAlignOptions" :key="opt.id" type="button" class="rounded-lg px-2 py-1 text-xs text-white" :class="textAlign === opt.id ? 'bg-blue-600' : 'bg-white/15'" @click="textAlign = opt.id; onTextPanelInput()">{{ opt.label }}</button>
              </div>
              <div>
                <label class="block text-white text-sm mb-1">Rotação: {{ textAngle }}°</label>
                <input v-model.number="textAngle" type="range" min="-180" max="180" class="w-full accent-white" @input="onTextPanelInput" />
              </div>
              <div class="rounded-lg border border-white/15 bg-white/5 p-2">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-white">
                  <input v-model="textStrokeEnabled" type="checkbox" class="rounded" @change="onTextPanelInput" />
                  Contorno
                </label>
                <div v-if="textStrokeEnabled" class="mt-2 flex items-center gap-2">
                  <input v-model="textStrokeColor" type="color" class="h-6 w-8 border-0 bg-transparent" @input="onTextPanelInput" />
                  <input v-model.number="textStrokeWidth" type="range" min="1" max="8" class="flex-1 accent-white" @input="onTextPanelInput" />
                </div>
              </div>
              <div class="rounded-lg border border-white/15 bg-white/5 p-2">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-white">
                  <input v-model="textBgEnabled" type="checkbox" class="rounded" @change="onTextPanelInput" />
                  Fundo
                </label>
                <div v-if="textBgEnabled" class="mt-2 space-y-2">
                  <div class="flex items-center gap-2">
                    <span class="text-xs text-white/70">Cor</span>
                    <input v-model="textBgColor" type="color" class="h-6 w-8 border-0 bg-transparent" @input="onTextPanelInput" />
                  </div>
                  <div>
                    <label class="block text-xs text-white/70">Opacidade: {{ textBgOpacity }}%</label>
                    <input v-model.number="textBgOpacity" type="range" min="10" max="100" class="w-full accent-white" @input="onTextPanelInput" />
                  </div>
                  <div>
                    <label class="block text-xs text-white/70">Margem: {{ textBgPadding }}px</label>
                    <input v-model.number="textBgPadding" type="range" min="0" max="24" class="w-full accent-white" @input="onTextPanelInput" />
                  </div>
                </div>
              </div>
              <div v-if="selectedTextIndex !== null" class="flex gap-2">
                <button type="button" class="flex-1 rounded-lg bg-white/15 py-2 text-xs text-white" @click="duplicateSelectedText">Duplicar</button>
                <button type="button" class="flex-1 rounded-lg bg-red-900/60 py-2 text-xs text-white" @click="removeText(selectedTextIndex)">Apagar</button>
              </div>
              <p class="text-[10px] text-white/50">Clique no texto para editar. Gravado na imagem ao guardar.</p>
            </div>

            <!-- Legendas (estilo Word) -->
            <div v-else-if="activeControl === 'caption'" class="mt-2 max-h-[50vh] w-full max-w-xs space-y-3 overflow-y-auto">
              <p class="text-center text-[11px] text-white/60">
                Uma legenda por baixo de toda a composição (foto ou folha em branco).
              </p>
              <div>
                <label class="mb-1 block text-sm text-white">Prefixo da numeração</label>
                <div class="flex flex-wrap gap-1">
                  <button
                    v-for="preset in captionPrefixPresets"
                    :key="'cap-pre-' + preset"
                    type="button"
                    class="rounded-lg border px-2 py-0.5 text-xs text-white transition"
                    :class="isCaptionPrefixPresetActive(preset) ? 'border-sky-400 bg-sky-900/50' : 'border-white/15 bg-white/10 hover:bg-white/20'"
                    @click="setCaptionPrefix(preset)"
                  >{{ captionPrefixPresetLabel(preset) }}</button>
                </div>
                <input
                  v-if="showCustomCaptionPrefix"
                  v-model="captionSettings.prefix"
                  type="text"
                  maxlength="40"
                  placeholder="Ex.: Quadro, Planta…"
                  class="mt-2 w-full rounded bg-white/20 p-2 text-sm text-white placeholder-gray-400"
                  @input="onCaptionSettingsChange"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm text-white">Separador</label>
                <div class="flex flex-wrap gap-1">
                  <button
                    v-for="opt in captionSeparatorOptions"
                    :key="'cap-sep-' + opt.id"
                    type="button"
                    class="rounded-lg border px-2 py-1 text-xs text-white transition"
                    :class="captionSettings.separator === opt.id ? 'border-sky-400 bg-sky-900/50' : 'border-white/15 bg-white/10 hover:bg-white/20'"
                    @click="captionSettings.separator = opt.id; onCaptionSettingsChange()"
                  >{{ opt.label }}</button>
                </div>
              </div>
              <div>
                <label class="mb-1 block text-sm text-white">Tamanho do texto: {{ captionSettings.fontSize }} px (ecrã)</label>
                <input
                  v-model.number="captionSettings.fontSize"
                  type="range"
                  min="10"
                  max="120"
                  class="w-full accent-white"
                  @input="onCaptionSettingsChange"
                />
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <button
                  type="button"
                  class="rounded-lg px-3 py-1.5 text-xs text-white"
                  :class="captionSettings.bold ? 'bg-blue-600' : 'bg-white/15'"
                  @click="captionSettings.bold = !captionSettings.bold; onCaptionSettingsChange()"
                >
                  Negrito
                </button>
                <label class="flex items-center gap-1 text-sm text-white">
                  Cor
                  <input v-model="captionSettings.color" type="color" class="h-6 w-8 cursor-pointer rounded border-0 bg-transparent" />
                </label>
              </div>
              <div v-if="photoCaptionDraft" class="space-y-2">
                <div>
                  <label class="mb-1 block text-sm text-white">Número</label>
                  <input
                    v-model.number="photoCaptionDraft.number"
                    type="number"
                    min="1"
                    max="9999"
                    class="w-full rounded bg-white/20 p-2 text-sm text-white"
                  />
                </div>
                <div>
                  <label class="mb-1 block text-sm text-white">Descrição</label>
                  <textarea
                    v-model="photoCaptionDraft.description"
                    rows="2"
                    maxlength="2000"
                    placeholder="Descrição da foto"
                    class="w-full resize-y rounded bg-white/20 p-2 text-sm text-white placeholder-gray-400"
                  />
                </div>
                <p class="text-center text-[11px] text-sky-200/90">
                  Exemplo: {{ captionPreviewSample }}
                </p>
                <p class="text-[10px] text-white/50">
                  {{
                    photoCaptionApplied
                      ? 'Legenda ativa. Aplique de novo para atualizar.'
                      : 'Confirme para ver a faixa branca na composição.'
                  }}
                </p>
                <button
                  type="button"
                  class="w-full rounded-lg bg-sky-600 py-2.5 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-40"
                  :disabled="!photoCaptionDraftCanApply"
                  @click="confirmPhotoCaption"
                >
                  Aplicar à foto
                </button>
                <button
                  v-if="photoCaptionApplied"
                  type="button"
                  class="w-full rounded-lg bg-red-900/50 py-2 text-xs text-white hover:bg-red-900/70"
                  @click="removePhotoCaption"
                >
                  Remover legenda
                </button>
              </div>
            </div>
            
            <!-- Gama: dois parâmetros + presets (Intervention só expõe gamma() global) -->
            <div v-else-if="activeControl === 'gamma'" class="w-full max-w-xs mt-2 space-y-3">
              <div>
                <label class="block text-white text-sm mb-1">Gama principal — tons médios (0 = neutro)</label>
                <input
                  type="range"
                  v-model.number="gamma"
                  min="-100"
                  max="100"
                  class="w-full"
                  title="Curva principal"
                  @input="scheduleApplyChanges"
                  @change="flushPreview"
                />
                <p class="mt-0.5 text-center text-white/80 text-xs">{{ gamma }}</p>
              </div>
              <div>
                <label class="block text-white text-sm mb-1">Refinamento — 2.ª passagem de gama (mais subtil)</label>
                <input
                  type="range"
                  v-model.number="gammaFine"
                  min="-50"
                  max="50"
                  class="w-full"
                  title="Segunda curva em cima da primeira"
                  @input="scheduleApplyChanges"
                  @change="flushPreview"
                />
                <p class="mt-0.5 text-center text-white/80 text-xs">{{ gammaFine }}</p>
              </div>
              <div>
                <p class="mb-1.5 text-center text-[11px] text-white/60">Atalhos</p>
                <div class="flex flex-wrap justify-center gap-2">
                  <button type="button" class="rounded-lg border border-white/15 bg-white/10 px-2.5 py-1 text-xs text-white hover:bg-white/20" @click="applyGammaPreset('neutral')">Neutro</button>
                  <button type="button" class="rounded-lg border border-white/15 bg-white/10 px-2.5 py-1 text-xs text-white hover:bg-white/20" @click="applyGammaPreset('lift')">Clar. médios</button>
                  <button type="button" class="rounded-lg border border-white/15 bg-white/10 px-2.5 py-1 text-xs text-white hover:bg-white/20" @click="applyGammaPreset('mute')">Esc. médios</button>
                  <button type="button" class="rounded-lg border border-white/15 bg-white/10 px-2.5 py-1 text-xs text-white hover:bg-white/20" @click="applyGammaPreset('punch')">Contraste suave</button>
                  <button type="button" class="rounded-lg border border-white/15 bg-white/10 px-2.5 py-1 text-xs text-white hover:bg-white/20" @click="applyGammaPreset('decode')">Tipo sRGB</button>
                </div>
              </div>
            </div>

            <!-- Marca de água -->
            <div v-else-if="activeControl === 'watermark' && watermarkDraft" class="w-full max-w-xs mt-2 max-h-[50vh] overflow-y-auto space-y-3">
              <div class="flex gap-2">
                <button type="button" class="flex-1 rounded-lg py-2 text-xs text-white transition" :class="watermarkDraft.type === 'text' ? 'bg-blue-600' : 'bg-white/10 hover:bg-white/20'" @click="setWatermarkType('text')">Texto</button>
                <button type="button" class="flex-1 rounded-lg py-2 text-xs text-white transition" :class="watermarkDraft.type === 'image' ? 'bg-blue-600' : 'bg-white/10 hover:bg-white/20'" @click="setWatermarkType('image')">Imagem</button>
              </div>
              <template v-if="watermarkDraft.type === 'text'">
                <div>
                  <p class="mb-1 text-[11px] text-white/60">Conteúdo</p>
                  <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-lg border px-2 py-1 text-xs text-white transition" :class="watermarkDraft.preset === 'custom' ? 'border-sky-400 bg-sky-900/50' : 'border-white/15 bg-white/10 hover:bg-white/20'" @click="setWatermarkPreset('custom')">Personalizado</button>
                    <button type="button" class="rounded-lg border px-2 py-1 text-xs text-white transition" :class="watermarkDraft.preset === 'date' ? 'border-sky-400 bg-sky-900/50' : 'border-white/15 bg-white/10 hover:bg-white/20'" @click="setWatermarkPreset('date')">Data e hora</button>
                    <button type="button" class="rounded-lg border px-2 py-1 text-xs text-white transition" :class="watermarkDraft.preset === 'user' ? 'border-sky-400 bg-sky-900/50' : 'border-white/15 bg-white/10 hover:bg-white/20'" @click="setWatermarkPreset('user')">Identificador</button>
                  </div>
                </div>
                <div v-if="watermarkDraft.preset === 'custom'">
                  <label class="block text-white text-sm mb-1">Texto</label>
                  <input v-model="watermarkDraft.text" type="text" maxlength="200" placeholder="Ex.: Confidencial" class="w-full rounded bg-white/20 p-2 text-sm text-white placeholder-gray-400" />
                </div>
                <p v-else class="text-[11px] text-sky-200/90">Texto: {{ resolveWatermarkText(watermarkDraft) }}</p>
                <div>
                  <label class="block text-white text-sm mb-1">Tamanho: {{ watermarkDraft.size }}% da imagem</label>
                  <input v-model.number="watermarkDraft.size" type="range" min="2" max="12" class="w-full accent-white" />
                </div>
                <div>
                  <label class="block text-white text-sm mb-1">Cor</label>
                  <div class="flex flex-wrap gap-2">
                    <button v-for="color in watermarkTextColors" :key="color" type="button" class="h-6 w-6 rounded-full border border-white" :style="{ backgroundColor: color }" :class="{ 'ring-2 ring-white': watermarkDraft.color === color }" @click="watermarkDraft.color = color" />
                    <input v-model="watermarkDraft.color" type="color" class="h-6 w-8 cursor-pointer rounded border-0 bg-transparent" />
                  </div>
                </div>
              </template>
              <template v-else>
                <button type="button" class="w-full rounded-lg bg-white/15 py-2 text-xs text-white hover:bg-white/25" @click="openWatermarkImagePicker">{{ watermarkDraft.src ? 'Trocar imagem' : 'Escolher logótipo / imagem' }}</button>
                <p v-if="watermarkDraft.src" class="text-center text-[11px] text-emerald-200/90">Imagem carregada</p>
                <div>
                  <label class="block text-white text-sm mb-1">Largura: {{ watermarkDraft.imageScale }}% da foto</label>
                  <input v-model.number="watermarkDraft.imageScale" type="range" min="5" max="50" class="w-full accent-white" />
                </div>
              </template>
              <div>
                <label class="block text-white text-sm mb-1">Posição</label>
                <div class="grid grid-cols-3 gap-1">
                  <button v-for="pos in watermarkPositions" :key="pos.id" type="button" class="rounded border px-1 py-1.5 text-[10px] text-white transition" :class="watermarkDraft.position === pos.id ? 'border-sky-400 bg-sky-900/50' : 'border-white/15 bg-white/10 hover:bg-white/20'" @click="watermarkDraft.position = pos.id">{{ pos.label }}</button>
                </div>
              </div>
              <div>
                <label class="block text-white text-sm mb-1">Opacidade: {{ watermarkDraft.opacity }}%</label>
                <input v-model.number="watermarkDraft.opacity" type="range" min="5" max="100" class="w-full accent-white" />
              </div>
              <div>
                <label class="block text-white text-sm mb-1">Margem: {{ watermarkDraft.margin }} px</label>
                <input v-model.number="watermarkDraft.margin" type="range" min="0" max="80" class="w-full accent-white" />
              </div>
              <p class="text-[10px] text-white/50">
                {{ watermarkApplied ? 'Marca ativa. Aplique de novo para atualizar.' : 'Confirme para ver a marca na imagem.' }}
              </p>
              <button
                type="button"
                class="w-full rounded-lg bg-sky-600 py-2.5 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-40"
                :disabled="!watermarkDraftCanApply"
                @click="confirmWatermark"
              >
                Aplicar marca de água
              </button>
              <button
                v-if="watermarkApplied"
                type="button"
                class="w-full rounded-lg bg-red-900/50 py-2 text-xs text-white hover:bg-red-900/70"
                @click="removeWatermark"
              >
                Remover marca de água
              </button>
            </div>

            <!-- Controles de Filtros -->
            <div v-else class="w-full max-w-xs mt-2">
              <input
                type="range"
                :title="'Ajustar ' + getControlLabel(activeControl) + ' (solte o controlo para atualizar de imediato)'"
                :value="getControlValue(activeControl).value"
                @input="updateControlValue($event.target.value)"
                @change="onControlSliderChange"
                :min="getControlMin(activeControl)"
                :max="getControlMax(activeControl)"
                class="w-full"
              >
              <div v-if="showMaskBrushSizeControl" class="mt-3 border-t border-white/15 pt-3">
                <label
                  class="mb-2 flex cursor-pointer items-center justify-center gap-2 text-xs text-white/90"
                >
                  <input
                    v-model="maskBrushEraseMode"
                    type="checkbox"
                    class="rounded border-white/30"
                  />
                  Borracha (corrigir máscara)
                </label>
                <label class="block text-white text-sm mb-1">
                  Tamanho do pincel: raio {{ effectiveMaskBrushRadius }} px (~{{ Math.round(effectiveMaskBrushRadius * 2) }} px de diâmetro na imagem)
                </label>
                <input
                  type="range"
                  v-model.number="maskBrushRadiusNatural"
                  :min="maskBrushRadiusBounds.min"
                  :max="maskBrushRadiusBounds.max"
                  step="1"
                  class="w-full accent-white"
                  title="Raio da borracha em pixels da imagem original"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue'
import axios from 'axios'
const props = defineProps({
  imageUrl: {
    type: String,
    required: true
  },
  photo: {
    type: Object,
    required: true
  },
  embedded: {
    type: Boolean,
    default: false
  },
  userId: {
    type: [String, Number],
    default: null
  },
  showUseInForm: {
    type: Boolean,
    default: false
  },
  galleryIndex: {
    type: Number,
    default: -1
  },
  galleryTotal: {
    type: Number,
    default: 0
  },
  galleryFoldersEnabled: {
    type: Boolean,
    default: false
  },
  galleryFolders: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'save', 'error', 'useInForm', 'gallery-navigate'])

const isBlankCanvas = computed(() => Boolean(props.photo?.is_blank_canvas))
const showBlankCanvasHint = ref(false)
let blankCanvasHintTimer = null

const dismissBlankCanvasHint = () => {
  showBlankCanvasHint.value = false
  if (blankCanvasHintTimer) {
    clearTimeout(blankCanvasHintTimer)
    blankCanvasHintTimer = null
  }
}

const scheduleBlankCanvasHintHide = () => {
  dismissBlankCanvasHint()
  showBlankCanvasHint.value = true
  blankCanvasHintTimer = setTimeout(() => {
    showBlankCanvasHint.value = false
    blankCanvasHintTimer = null
  }, 5000)
}

watch(
  () => props.photo?.is_blank_canvas,
  (isBlank) => {
    if (isBlank) {
      scheduleBlankCanvasHintHide()
    } else {
      dismissBlankCanvasHint()
    }
  },
  { immediate: true }
)

const brightness = ref(0)
const contrast = ref(0)
const saturation = ref(0)
const gamma = ref(0)
const gammaFine = ref(0)
const saveMode = ref(props.showUseInForm ? 'copy' : 'overwrite')
const saveCopyFolderId = ref(props.photo?.folder_id || 'entrada')
const saveFormat = ref('jpeg')
const saveQuality = ref(85)

const showSaveCopyFolderPicker = computed(
  () => props.galleryFoldersEnabled && saveMode.value === 'copy' && props.galleryFolders.length > 0
)

watch(
  () => props.photo?.folder_id,
  (folderId) => {
    saveCopyFolderId.value = folderId || 'entrada'
  }
)
const showSavePanel = ref(false)
const showUseInFormWarning = ref(false)
const isSaving = ref(false)
const blur = ref(0)
const pixelate = ref(0)
/** Intensidade inicial ao desenhar desfoque (evita slider a 0 sem efeito visível). */
const DEFAULT_BLUR_STRENGTH = 28
/** Tamanho inicial do bloco ao desenhar pixelização. */
const DEFAULT_PIXELATE_BLOCK = 12

const ensureBlurEffectStrength = () => {
  if (blur.value <= 0) {
    blur.value = DEFAULT_BLUR_STRENGTH
  }
}

const ensurePixelateEffectStrength = () => {
  if (pixelate.value <= 0) {
    pixelate.value = DEFAULT_PIXELATE_BLOCK
  }
}
const sharpen = ref(0)
const originalImageUrl = ref(props.imageUrl)
const showingOriginal = ref(false)
const currentImageUrl = ref(props.imageUrl)
const displayImageUrl = computed(() =>
  showingOriginal.value ? originalImageUrl.value : currentImageUrl.value
)
const imageRef = ref(null)
const viewportRef = ref(null)
const VIEW_ZOOM_MIN = 0.5
const VIEW_ZOOM_MAX = 5
const viewZoom = ref(1)
const viewPanX = ref(0)
const viewPanY = ref(0)
const viewPanHandMode = ref(false)
const isViewPanning = ref(false)
const spaceKeyDown = ref(false)
let gallerySwipePointer = null

const canGalleryPrev = computed(() => props.galleryIndex > 0)
const canGalleryNext = computed(
  () => props.galleryIndex >= 0 && props.galleryIndex < props.galleryTotal - 1
)
const galleryPositionLabel = computed(() => {
  if (props.galleryIndex < 0 || props.galleryTotal <= 0) {
    return ''
  }
  return `${props.galleryIndex + 1}/${props.galleryTotal}`
})
const canUseGallerySwipe = computed(
  () =>
    props.galleryTotal > 1 &&
    !zoomLayout.value &&
    !showCrop.value &&
    !drawingTool.value &&
    !zoomDetailMode.value &&
    !activeControl.value &&
    viewZoom.value <= 1.02 &&
    !viewPanHandMode.value &&
    !isViewPanning.value &&
    !spaceKeyDown.value
)

const navigateGallery = (direction) => {
  if (direction === 'prev' && canGalleryPrev.value) {
    emit('gallery-navigate', 'prev')
  } else if (direction === 'next' && canGalleryNext.value) {
    emit('gallery-navigate', 'next')
  }
}

const tryStartGallerySwipe = (clientX, clientY) => {
  if (!canUseGallerySwipe.value) {
    gallerySwipePointer = null
    return
  }
  gallerySwipePointer = { x: clientX, y: clientY }
}

const finishGallerySwipe = (clientX, clientY) => {
  if (!gallerySwipePointer) {
    return
  }
  const dx = clientX - gallerySwipePointer.x
  const dy = clientY - gallerySwipePointer.y
  gallerySwipePointer = null
  if (Math.abs(dx) < 56 || Math.abs(dx) < Math.abs(dy) * 1.25) {
    return
  }
  if (dx < 0) {
    navigateGallery('next')
  } else {
    navigateGallery('prev')
  }
}
let viewPanStart = null
let touchPinchStart = null

const stageTransformStyle = computed(() => ({
  transform: `translate(${viewPanX.value}px, ${viewPanY.value}px) scale(${viewZoom.value})`,
  transformOrigin: '0 0'
}))

const viewZoomPercent = computed(() => Math.round(viewZoom.value * 100))

/** Folha em branco com imagens arrastadas da barra lateral. */
const isCollageComposition = computed(
  () => isBlankCanvas.value && imageOverlays.value.length > 0
)

/** Desfoque/pixelização na folha em branco exige compor overlays no servidor. */
const shouldBakeImageOverlaysInPreview = computed(() => {
  if (!isCollageComposition.value) {
    return false
  }

  return (
    blur.value > 0 ||
    showBlurRegion.value ||
    committedBlurRegion.value ||
    committedBlurMask.value ||
    blurApplyGlobal.value ||
    pixelate.value > 0 ||
    showPixelateRegion.value ||
    committedPixelateRegion.value ||
    committedPixelateMask.value ||
    pixelateApplyGlobal.value
  )
})

const showDrawingsOverlay = computed(
  () => drawings.value.length > 0 && !zoomLayout.value
)

const usesLayoutDrawingSpace = computed(() => Boolean(zoomLayout.value))

const showLayoutDrawingsOverlay = computed(
  () => usesLayoutDrawingSpace.value && layoutDrawings.value.length > 0
)

const canEditZoomCallouts = computed(
  () => Boolean(zoomDetailMode.value) && !drawingTool.value && !showDrawingMenu.value
)

const layoutDrawingSvgViewBox = computed(() => {
  const layout = zoomLayout.value
  if (!layout) {
    return '0 0 1 1'
  }
  return `0 0 ${layout.canvasWidth} ${layout.canvasHeight}`
})

/** Camada por cima da composição enquanto uma ferramenta de desenho está selecionada. */
const isDrawingCaptureActive = computed(() => {
  if (!drawingTool.value || showCrop.value) {
    return false
  }
  if (showBlurRegion.value && blurShapeMode.value === 'rectangle') {
    return false
  }
  if (showPixelateRegion.value && pixelateShapeMode.value === 'rectangle') {
    return false
  }
  return true
})

/** Fotos arrastáveis só fora do modo desenho. */
const canMoveImageOverlays = computed(
  () => imageOverlays.value.length > 0 && !drawingTool.value && !showDrawingMenu.value
)

/** Desenhos arrastáveis sobre a imagem (fora do modo Desenho). */
const canMoveDrawings = computed(
  () =>
    drawings.value.length > 0 &&
    !drawingTool.value &&
    !showDrawingMenu.value &&
    !zoomLayout.value
)

const selectedDrawingIndex = ref(null)
const movingDrawingIndex = ref(null)
const drawingMoveSnapshot = ref(null)
const drawingMoveStartNat = ref({ x: 0, y: 0 })

const imageCursorClass = computed(() => {
  if (viewPanHandMode.value || isViewPanning.value) {
    return ''
  }
  if (
    drawingTool.value &&
    !showCrop.value &&
    !(showBlurRegion.value && blurShapeMode.value === 'rectangle') &&
    !(showPixelateRegion.value && pixelateShapeMode.value === 'rectangle')
  ) {
    return 'cursor-crosshair'
  }
  return ''
})

const showCrop = ref(false)
const cropStart = ref({ x: 0, y: 0 })
const cropSize = ref({ width: 0, height: 0 })
/** Dimensões da imagem (após rotação) antes do crop — referência para o servidor. */
const cropReferenceSize = ref({ width: 0, height: 0 })
/** Área de crop em px da imagem de referência (durante edição). */
const cropNatural = ref({ x: 0, y: 0, width: 0, height: 0 })
/** Crop confirmado (coords na imagem pós-rotação enviada ao servidor). */
const committedCrop = ref(null)
/** Após ativar crop, o preview pode mudar dimensões — repor área total no próximo load. */
const cropPendingReferenceReset = ref(false)
const cropAspectPreset = ref('free')
const cropAspectOptions = [
  { id: 'free', label: 'Livre' },
  { id: '1:1', label: '1:1' },
  { id: '4:3', label: '4:3' },
  { id: '16:9', label: '16:9' }
]
const cropPanGrabNat = ref({ x: 0, y: 0 })
const showBlurRegion = ref(false)
const blurShapeMode = ref('rectangle')
const blurMaskDirty = ref(false)
const isBlurBrushDrawing = ref(false)
const blurStart = ref({ x: 0, y: 0 })
const blurSize = ref({ width: 0, height: 0 })
/** Zona de desfoque por retângulo já confirmada (coords naturais). */
const committedBlurRegion = ref(null)
/** Máscara de pincel de desfoque confirmada (data URL). */
const committedBlurMask = ref(null)
/** Desfoque em toda a imagem (menu «Toda a imagem»). */
const blurApplyGlobal = ref(false)
const showPixelateRegion = ref(false)
const pixelateShapeMode = ref('rectangle')
const pixelateMaskDirty = ref(false)
const isPixelateBrushDrawing = ref(false)
const pixelateStart = ref({ x: 0, y: 0 })
const pixelateSize = ref({ width: 0, height: 0 })
/** Zona de pixelização por retângulo já confirmada (coords naturais). */
const committedPixelateRegion = ref(null)
/** Máscara de pincel de pixelização confirmada (data URL). */
const committedPixelateMask = ref(null)
/** Pixelização em toda a imagem (menu «Toda a imagem»). */
const pixelateApplyGlobal = ref(false)
/** Raio da borracha de máscara (px da imagem natural); partilhado entre desfoque e pixelização. */
const maskBrushRadiusNatural = ref(16)
/** Quando true, o traço remove efeito da máscara (em vez de acrescentar). */
const maskBrushEraseMode = ref(false)
/** Modo borrar fixo durante um traço (clique direito / Alt no início). */
let maskBrushStrokeErase = false
let maskBrushImageKey = ''
/** Desenhos vectoriais (coordenadas em pixels da imagem natural). */
const drawings = ref([])
/** Desenhos na composição de zoom (coordenadas do canvas branco). */
const layoutDrawings = ref([])
const drawingTool = ref(null)
const drawStrokeColor = ref('#FFFF00')
const drawFillColor = ref('#FF000066')
const drawFillEnabled = ref(false)
const drawStrokeWidth = ref(4)
const drawDrag = ref(null)
const penDraftPoints = ref([])
const isPenDrawing = ref(false)
const pathDraftPoints = ref([])
/** Cursor em coords do elemento img — segmento tracejado em polígono/bézier em construção. */
const pathDraftHoverPos = ref(null)
const showDrawingMenu = ref(false)
const showPixelateMenu = ref(false)
const showBlurMenu = ref(false)
const showFilterMenu = ref(false)
const activeFilterPreset = ref(null)

const FILTER_PRESETS = {
  neutral: { brightness: 0, contrast: 0, saturation: 0, gamma: 0, gammaFine: 0, sharpen: 0 },
  bw: { brightness: 0, contrast: 18, saturation: -100, gamma: 8, gammaFine: 0, sharpen: 0 },
  sepia: { brightness: 10, contrast: 22, saturation: -75, gamma: -12, gammaFine: 18, sharpen: 0 },
  document: { brightness: 14, contrast: 45, saturation: -20, gamma: 22, gammaFine: -18, sharpen: 22 },
  vivid: { brightness: 6, contrast: 20, saturation: 58, gamma: 12, gammaFine: -10, sharpen: 8 }
}

const filterPresetList = [
  { id: 'neutral', label: 'Neutro', desc: 'Repor brilho, contraste e cor' },
  { id: 'bw', label: 'P&B', desc: 'Preto e branco' },
  { id: 'sepia', label: 'Sépia', desc: 'Tom quente vintage' },
  { id: 'document', label: 'Documento', desc: 'Alto contraste, texto legível' },
  { id: 'vivid', label: 'Vívido', desc: 'Cores mais intensas' }
]

/** Detalhe ampliado: composição com foto reduzida + recortes posicionáveis. */
const zoomDetailMode = ref(false)
const zoomDetailSelecting = ref(false)
/** Ampliação por defeito para o próximo detalhe (50–400 %). */
const zoomDetailLevel = ref(150)
const selectedZoomCalloutId = ref(null)
const zoomLayout = ref(null)
const zoomCallouts = ref([])
const zoomSelectDrag = ref(null)
const layoutBoardRef = ref(null)
const movingZoomCalloutId = ref(null)
const zoomCalloutMoveGrab = ref({ x: 0, y: 0 })
const resizingZoomCalloutId = ref(null)
const zoomCalloutResizeStart = ref(null)

const imageSize = ref({ width: 0, height: 0 })
/** Incrementado quando sabemos as dimensões naturais — o Vue não observa naturalWidth do <img>. */
const imageNaturalVersion = ref(0)

const maskBrushRadiusBounds = computed(() => {
  imageNaturalVersion.value
  const nw = Math.max(1, imageSize.value.width || imageRef.value?.naturalWidth || 1)
  const nh = Math.max(1, imageSize.value.height || imageRef.value?.naturalHeight || 1)
  const m = Math.min(nw, nh)
  return {
    min: 4,
    max: Math.max(48, Math.min(2500, Math.floor(m * 0.49)))
  }
})

const effectiveMaskBrushRadius = computed(() => {
  imageNaturalVersion.value
  const b = maskBrushRadiusBounds.value
  const v = Number(maskBrushRadiusNatural.value)
  const raw = Number.isFinite(v) ? v : b.min
  return Math.max(b.min, Math.min(b.max, Math.round(raw)))
})

const showMaskBrushSizeControl = computed(
  () =>
    (activeControl.value === 'blur' &&
      showBlurRegion.value &&
      blurShapeMode.value === 'brush') ||
    (activeControl.value === 'pixelate' &&
      showPixelateRegion.value &&
      pixelateShapeMode.value === 'brush')
)

const showControls = ref(true)

/** Margem inferior do viewport para a imagem não ficar sob a barra de ferramentas. */
const viewportBottomPaddingClass = computed(() => {
  if (
    showCrop.value ||
    showBlurRegion.value ||
    showPixelateRegion.value ||
    showPixelateMenu.value ||
    showBlurMenu.value ||
    showFilterMenu.value ||
    zoomDetailMode ||
    zoomLayout.value
  ) {
    return 'pb-40'
  }
  if (activeControl.value === 'text') {
    return 'pb-52'
  }
  if (activeControl.value) {
    return 'pb-32'
  }
  if (showDrawingMenu.value || drawingTool.value) {
    return 'pb-40'
  }
  if (showControls.value) {
    return 'pb-24'
  }
  return ''
})
const activeControl = ref(null)
const flipHorizontal = ref(false)
const flipVertical = ref(false)
const rotation = ref(0)
const resizeKind = ref('crop')
const resizeDirection = ref(null)
const textContent = ref('')
const textSize = ref(24)
const textColor = ref('#FFFFFF')
const textColors = ['#FFFFFF', '#000000', '#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF']
const textBold = ref(false)
const textAngle = ref(0)
const textAlign = ref('left')
const textAlignOptions = [
  { id: 'left', label: 'Esq.' },
  { id: 'center', label: 'Centro' },
  { id: 'right', label: 'Dir.' }
]
const textStrokeEnabled = ref(false)
const textStrokeWidth = ref(2)
const textStrokeColor = ref('#000000')
const textBgEnabled = ref(false)
const textBgColor = ref('#000000')
const textBgOpacity = ref(75)
const textBgPadding = ref(6)
const selectedTextIndex = ref(null)
const textPosition = ref({ x: 0, y: 0 })
const texts = ref([])
const isMovingText = ref(false)
const movingTextIndex = ref(null)
const textOffset = ref({ x: 0, y: 0 })
/** Imagens extra: x,y,width,height em px da imagem natural; src = data URL. */
const imageOverlays = ref([])
const selectedOverlayId = ref(null)

const createDefaultCaptionSettings = () => ({
  prefix: 'Fig.',
  separator: ' — ',
  fontSize: 14,
  bandPadding: 10,
  color: '#000000',
  bold: false
})

const captionSettings = ref(createDefaultCaptionSettings())
const photoCaptionApplied = ref(null)
const photoCaptionDraft = ref(null)

const createDefaultPhotoCaptionDraft = () => ({
  number: 1,
  description: ''
})

const captionStandardPrefixes = ['Fig.', 'Figura', 'Imagem', 'Foto', '']
const captionPrefixPresets = [...captionStandardPrefixes, '__custom__']
const showCustomCaptionPrefix = ref(false)
const captionSeparatorOptions = [
  { id: ' — ', label: '—' },
  { id: ' - ', label: '-' },
  { id: ': ', label: ':' },
  { id: '. ', label: '.' }
]
/** Configuração aplicada na imagem (preview/guardar). */
const watermarkApplied = ref(null)
/** Rascunho enquanto o painel está aberto (não altera a imagem até confirmar). */
const watermarkDraft = ref(null)
const watermarkImageInputRef = ref(null)

const watermarkTextColors = ['#ffffff', '#000000', '#94a3b8', '#fbbf24', '#38bdf8']

const watermarkPositions = [
  { id: 'top-left', label: '↖' },
  { id: 'top-right', label: '↗' },
  { id: 'center', label: '◎' },
  { id: 'bottom-left', label: '↙' },
  { id: 'bottom-right', label: '↘' }
]

const createDefaultWatermark = () => ({
  type: 'text',
  preset: 'date',
  text: '',
  position: 'bottom-right',
  opacity: 45,
  size: 4,
  color: '#ffffff',
  margin: 16,
  src: null,
  imageScale: 18
})
const movingOverlayId = ref(null)
const overlayMoveGrabNat = ref({ x: 0, y: 0 })
const resizingOverlayId = ref(null)
const overlayResizeStart = ref(null)

const isDraggingBlurPan = ref(false)
const blurPanGrab = ref({ x: 0, y: 0 })
let blurPanPreviewRaf = 0
const isDraggingCropPan = ref(false)
let cropPanPreviewRaf = 0
let cropResizePreviewRaf = 0
const isDraggingPixelatePan = ref(false)
const pixelatePanGrab = ref({ x: 0, y: 0 })
let pixelatePanPreviewRaf = 0
let pixelateBrushCanvas = null
let pixelateBrushCtx = null
let pixelBrushMaskW = 0
let pixelBrushMaskH = 0
let pixelBrushStoredNaturalW = 0
let pixelBrushStoredNaturalH = 0
let pixelBrushMaskLast = null
let blurBrushCanvas = null
let blurBrushCtx = null
let blurBrushMaskW = 0
let blurBrushMaskH = 0
let blurBrushStoredNaturalW = 0
let blurBrushStoredNaturalH = 0
let blurBrushMaskLast = null
/** Evita que uma resposta antiga de /preview sobrescreva a imagem (ex.: reset após blur). */
let previewRequestId = 0
/** Agrega pedidos do slider para não disparar rate limit (429) no /preview. */
let previewDebounceTimer = null
const PREVIEW_DEBOUNCE_MS = 400
const MAX_EDIT_HISTORY = 50
const editHistory = ref([])
const editHistoryIndex = ref(-1)
const editHistoryRevision = ref(0)
let editHistoryLock = false
const editHistoryReady = ref(false)
/** Só um POST /preview em voo; pedidos extra ficam coalescidos (evita 429 em arrastar retângulos). */
let previewInFlight = false
let previewPending = false
let previewPendingOptions = null
let pixelateBrushPreviewTimer = null
let blurBrushPreviewTimer = null

const clearBlurBrushPreviewDebounce = () => {
  if (blurBrushPreviewTimer !== null) {
    clearTimeout(blurBrushPreviewTimer)
    blurBrushPreviewTimer = null
  }
}

const clearPixelateBrushPreviewDebounce = () => {
  if (pixelateBrushPreviewTimer !== null) {
    clearTimeout(pixelateBrushPreviewTimer)
    pixelateBrushPreviewTimer = null
  }
}

const clearPreviewDebounceTimer = () => {
  if (previewDebounceTimer !== null) {
    clearTimeout(previewDebounceTimer)
    previewDebounceTimer = null
  }
}

const scheduleApplyChanges = () => {
  clearPreviewDebounceTimer()
  clearPixelateBrushPreviewDebounce()
  clearBlurBrushPreviewDebounce()
  previewDebounceTimer = window.setTimeout(() => {
    previewDebounceTimer = null
    void applyChanges()
  }, PREVIEW_DEBOUNCE_MS)
}

const finishVectorDrawingEdit = () => {
  nextTick(() => recordEditHistory())
}

const bakeDrawingsIntoPreview = async () => {
  await applyChanges({ bakeDrawings: true })
}

const flushPreview = (options = {}) => {
  clearPreviewDebounceTimer()
  clearPixelateBrushPreviewDebounce()
  clearBlurBrushPreviewDebounce()
  return applyChanges(options)
}

const cloneJson = (v) => JSON.parse(JSON.stringify(v))

const captureEditSnapshot = () => ({
  brightness: brightness.value,
  contrast: contrast.value,
  saturation: saturation.value,
  gamma: gamma.value,
  gammaFine: gammaFine.value,
  blur: blur.value,
  pixelate: pixelate.value,
  sharpen: sharpen.value,
  flipHorizontal: flipHorizontal.value,
  flipVertical: flipVertical.value,
  rotation: rotation.value,
  activeFilterPreset: activeFilterPreset.value,
  committedCrop: committedCrop.value ? cloneJson(committedCrop.value) : null,
  cropAspectPreset: cropAspectPreset.value,
  showCrop: showCrop.value,
  showBlurRegion: showBlurRegion.value,
  blurShapeMode: blurShapeMode.value,
  blurStart: { ...blurStart.value },
  blurSize: { ...blurSize.value },
  blurMaskDirty: blurMaskDirty.value,
  blurMaskDataUrl: blurMaskDirty.value ? exportBlurMaskDataUrl() : null,
  committedBlurRegion: committedBlurRegion.value ? cloneJson(committedBlurRegion.value) : null,
  committedBlurMask: committedBlurMask.value,
  blurApplyGlobal: blurApplyGlobal.value,
  showPixelateRegion: showPixelateRegion.value,
  pixelateShapeMode: pixelateShapeMode.value,
  pixelateStart: { ...pixelateStart.value },
  pixelateSize: { ...pixelateSize.value },
  pixelateMaskDirty: pixelateMaskDirty.value,
  pixelateMaskDataUrl: pixelateMaskDirty.value ? exportPixelateMaskDataUrl() : null,
  committedPixelateRegion: committedPixelateRegion.value
    ? cloneJson(committedPixelateRegion.value)
    : null,
  committedPixelateMask: committedPixelateMask.value,
  pixelateApplyGlobal: pixelateApplyGlobal.value,
  maskBrushRadiusNatural: maskBrushRadiusNatural.value,
  drawings: cloneJson(drawings.value),
  layoutDrawings: cloneJson(layoutDrawings.value),
  imageOverlays: cloneJson(imageOverlays.value),
  selectedOverlayId: selectedOverlayId.value,
  captionSettings: cloneJson(captionSettings.value),
  photoCaptionApplied: photoCaptionApplied.value ? cloneJson(photoCaptionApplied.value) : null,
  texts: cloneJson(texts.value),
  watermarkApplied: watermarkApplied.value ? cloneJson(watermarkApplied.value) : null,
  zoomLayout: zoomLayout.value ? cloneJson(zoomLayout.value) : null,
  zoomCallouts: cloneJson(zoomCallouts.value),
  zoomDetailMode: zoomDetailMode.value
})

const snapshotsEqual = (a, b) => {
  if (!a || !b) {
    return false
  }
  return JSON.stringify(a) === JSON.stringify(b)
}

const bumpEditHistoryRevision = () => {
  editHistoryRevision.value++
}

const resetEditHistoryStack = (snap) => {
  editHistory.value = [snap]
  editHistoryIndex.value = 0
  editHistoryReady.value = true
  bumpEditHistoryRevision()
}

const recordEditHistory = () => {
  if (editHistoryLock || !editHistoryReady.value) {
    return
  }
  const snap = captureEditSnapshot()
  const cur = editHistory.value[editHistoryIndex.value]
  if (cur && snapshotsEqual(cur, snap)) {
    return
  }
  const truncated = editHistory.value.slice(0, editHistoryIndex.value + 1)
  truncated.push(snap)
  while (truncated.length > MAX_EDIT_HISTORY) {
    truncated.shift()
  }
  editHistory.value = truncated
  editHistoryIndex.value = truncated.length - 1
  bumpEditHistoryRevision()
}

const canUndoEdit = computed(() => {
  void editHistoryRevision.value
  return editHistoryReady.value && editHistoryIndex.value > 0
})
const canRedoEdit = computed(() => {
  void editHistoryRevision.value
  return (
    editHistoryReady.value &&
    editHistoryIndex.value < editHistory.value.length - 1
  )
})

const bootstrapEditHistory = () => {
  editHistoryLock = false
  resetEditHistoryStack(captureEditSnapshot())
}

const restoreBrushMaskFromDataUrl = (dataUrl, kind, dirty) => {
  return new Promise((resolve) => {
    if (!dirty || !dataUrl) {
      if (kind === 'blur') {
        clearBlurBrushMask()
      } else {
        clearPixelateBrushMask()
      }
      resolve()
      return
    }
    const ensure = kind === 'blur' ? ensureBlurBrushCanvas : ensurePixelateBrushCanvas
    if (!ensure()) {
      resolve()
      return
    }
    const canvas = kind === 'blur' ? blurBrushCanvas : pixelateBrushCanvas
    const ctx = kind === 'blur' ? blurBrushCtx : pixelateBrushCtx
    const img = new window.Image()
    img.onload = () => {
      ctx.fillStyle = '#000000'
      ctx.fillRect(0, 0, canvas.width, canvas.height)
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height)
      if (kind === 'blur') {
        blurMaskDirty.value = true
      } else {
        pixelateMaskDirty.value = true
      }
      resolve()
    }
    img.onerror = () => resolve()
    img.src = dataUrl
  })
}

const restoreEditSnapshot = async (snap) => {
  editHistoryLock = true
  brightness.value = snap.brightness
  contrast.value = snap.contrast
  saturation.value = snap.saturation
  gamma.value = snap.gamma
  gammaFine.value = snap.gammaFine
  blur.value = snap.blur
  pixelate.value = snap.pixelate
  sharpen.value = snap.sharpen
  flipHorizontal.value = snap.flipHorizontal
  flipVertical.value = snap.flipVertical
  rotation.value = snap.rotation
  activeFilterPreset.value = snap.activeFilterPreset
  committedCrop.value = snap.committedCrop ? cloneJson(snap.committedCrop) : null
  cropAspectPreset.value = snap.cropAspectPreset
  showCrop.value = snap.showCrop
  showBlurRegion.value = snap.showBlurRegion
  blurShapeMode.value = snap.blurShapeMode
  blurStart.value = { ...snap.blurStart }
  blurSize.value = { ...snap.blurSize }
  committedBlurRegion.value = snap.committedBlurRegion
    ? cloneJson(snap.committedBlurRegion)
    : null
  committedBlurMask.value = snap.committedBlurMask ?? null
  blurApplyGlobal.value = Boolean(snap.blurApplyGlobal)
  showPixelateRegion.value = snap.showPixelateRegion
  pixelateShapeMode.value = snap.pixelateShapeMode
  pixelateStart.value = { ...snap.pixelateStart }
  pixelateSize.value = { ...snap.pixelateSize }
  committedPixelateRegion.value = snap.committedPixelateRegion
    ? cloneJson(snap.committedPixelateRegion)
    : null
  committedPixelateMask.value = snap.committedPixelateMask ?? null
  pixelateApplyGlobal.value = Boolean(snap.pixelateApplyGlobal)
  maskBrushRadiusNatural.value = snap.maskBrushRadiusNatural
  drawings.value = cloneJson(snap.drawings)
  layoutDrawings.value = cloneJson(snap.layoutDrawings ?? [])
  imageOverlays.value = cloneJson(snap.imageOverlays)
  selectedOverlayId.value = snap.selectedOverlayId ?? imageOverlays.value[0]?.id ?? null
  captionSettings.value = snap.captionSettings
    ? { ...createDefaultCaptionSettings(), ...cloneJson(snap.captionSettings) }
    : createDefaultCaptionSettings()
  showCustomCaptionPrefix.value = !captionStandardPrefixes.includes(captionSettings.value.prefix)
  if (snap.photoCaptionApplied) {
    photoCaptionApplied.value = cloneJson(snap.photoCaptionApplied)
  } else if (snap.photoCaption?.enabled) {
    photoCaptionApplied.value = {
      number: snap.photoCaption.number ?? 1,
      description: snap.photoCaption.description ?? ''
    }
  } else {
    photoCaptionApplied.value = null
  }
  photoCaptionDraft.value = null
  texts.value = cloneJson(snap.texts)
  watermarkApplied.value = snap.watermarkApplied ? cloneJson(snap.watermarkApplied) : null
  watermarkDraft.value = null
  zoomLayout.value = snap.zoomLayout ? cloneJson(snap.zoomLayout) : null
  zoomCallouts.value = cloneJson(snap.zoomCallouts)
  zoomDetailMode.value = snap.zoomDetailMode
  selectedTextIndex.value = null
  await restoreBrushMaskFromDataUrl(snap.blurMaskDataUrl, 'blur', snap.blurMaskDirty)
  await restoreBrushMaskFromDataUrl(snap.pixelateMaskDataUrl, 'pixelate', snap.pixelateMaskDirty)
  editHistoryLock = false
}

const initEditHistoryBaseline = () => {
  if (!editHistoryReady.value) {
    bootstrapEditHistory()
  }
}

const undoEdit = async () => {
  if (!canUndoEdit.value) {
    return
  }
  editHistoryIndex.value -= 1
  await restoreEditSnapshot(editHistory.value[editHistoryIndex.value])
  flushPreview({ skipHistoryRecord: true })
}

const redoEdit = async () => {
  if (!canRedoEdit.value) {
    return
  }
  editHistoryIndex.value += 1
  await restoreEditSnapshot(editHistory.value[editHistoryIndex.value])
  flushPreview({ skipHistoryRecord: true })
}

const isFormFieldTarget = (target) => {
  const tag = target?.tagName
  if (tag === 'TEXTAREA' || tag === 'INPUT' || tag === 'SELECT') {
    return true
  }

  return Boolean(target?.isContentEditable)
}

const onHistoryKeyDown = (e) => {
  if (isFormFieldTarget(e.target)) {
    return
  }
  if (
    (e.key === 'ArrowLeft' || e.key === 'ArrowRight') &&
    !zoomLayout.value &&
    !drawingTool.value &&
    !zoomDetailMode.value &&
    !showCrop.value
  ) {
    if (e.key === 'ArrowLeft' && canGalleryPrev.value) {
      e.preventDefault()
      navigateGallery('prev')
    } else if (e.key === 'ArrowRight' && canGalleryNext.value) {
      e.preventDefault()
      navigateGallery('next')
    }
    return
  }
  if (e.key === 'Delete' || e.key === 'Backspace') {
    if (deleteFocusedDrawing()) {
      e.preventDefault()
    }
    return
  }
  const mod = e.ctrlKey || e.metaKey
  if (!mod) {
    return
  }
  if (e.key === 'z' && !e.shiftKey) {
    e.preventDefault()
    undoEdit()
  } else if (e.key === 'y' || (e.key === 'z' && e.shiftKey) || (e.key === 'Z' && e.shiftKey)) {
    e.preventDefault()
    redoEdit()
  }
}

const queueBlurBrushPreview = () => {
  clearBlurBrushPreviewDebounce()
  blurBrushPreviewTimer = window.setTimeout(() => {
    blurBrushPreviewTimer = null
    flushPreview()
  }, 90)
}

const queuePixelateBrushPreview = () => {
  clearPixelateBrushPreviewDebounce()
  pixelateBrushPreviewTimer = window.setTimeout(() => {
    pixelateBrushPreviewTimer = null
    flushPreview()
  }, 90)
}

const cancelPixelatePanPreviewRaf = () => {
  if (pixelatePanPreviewRaf) {
    cancelAnimationFrame(pixelatePanPreviewRaf)
    pixelatePanPreviewRaf = 0
  }
}

const cancelBlurPanPreviewRaf = () => {
  if (blurPanPreviewRaf) {
    cancelAnimationFrame(blurPanPreviewRaf)
    blurPanPreviewRaf = 0
  }
}

const cancelCropPanPreviewRaf = () => {
  if (cropPanPreviewRaf) {
    cancelAnimationFrame(cropPanPreviewRaf)
    cropPanPreviewRaf = 0
  }
}

const cancelCropResizePreviewRaf = () => {
  if (cropResizePreviewRaf) {
    cancelAnimationFrame(cropResizePreviewRaf)
    cropResizePreviewRaf = 0
  }
}

const cropStyle = computed(() => ({
  left: `${cropStart.value.x}px`,
  top: `${cropStart.value.y}px`,
  width: `${cropSize.value.width}px`,
  height: `${cropSize.value.height}px`
}))

const blurRegionStyle = computed(() => ({
  left: `${blurStart.value.x}px`,
  top: `${blurStart.value.y}px`,
  width: `${blurSize.value.width}px`,
  height: `${blurSize.value.height}px`
}))

const pixelateRegionStyle = computed(() => ({
  left: `${pixelateStart.value.x}px`,
  top: `${pixelateStart.value.y}px`,
  width: `${pixelateSize.value.width}px`,
  height: `${pixelateSize.value.height}px`
}))

const layoutBoardMetrics = computed(() => {
  zoomLayout.value
  imageNaturalVersion.value
  const layout = zoomLayout.value
  const vp = viewportRef.value
  if (!layout || !vp) {
    return null
  }
  const vw = vp.clientWidth
  const vh = vp.clientHeight
  if (!vw || !vh) {
    return null
  }
  const scale = Math.min(vw / layout.canvasWidth, vh / layout.canvasHeight) * 0.9
  return {
    scale,
    width: layout.canvasWidth * scale,
    height: layout.canvasHeight * scale
  }
})

const layoutBoardContainerStyle = computed(() => {
  const m = layoutBoardMetrics.value
  if (!m) {
    return {}
  }
  return { width: `${m.width}px`, height: `${m.height}px` }
})

const layoutBaseImgStyle = computed(() => {
  const layout = zoomLayout.value
  const m = layoutBoardMetrics.value
  if (!layout || !m) {
    return {}
  }
  const b = layout.base
  return {
    left: `${b.x * m.scale}px`,
    top: `${b.y * m.scale}px`,
    width: `${b.width * m.scale}px`,
    height: `${b.height * m.scale}px`
  }
})

const zoomSelectImgStyle = computed(() => {
  const d = zoomSelectDrag.value
  if (!d?.active || d.space !== 'img') {
    return null
  }
  const left = Math.min(d.x0, d.x1)
  const top = Math.min(d.y0, d.y1)
  const w = Math.abs(d.x1 - d.x0)
  const h = Math.abs(d.y1 - d.y0)
  if (w < 2 || h < 2) {
    return null
  }
  return { left: `${left}px`, top: `${top}px`, width: `${w}px`, height: `${h}px` }
})

const zoomSelectBoardStyle = computed(() => {
  const d = zoomSelectDrag.value
  const m = layoutBoardMetrics.value
  if (!d?.active || d.space !== 'layout' || !m) {
    return null
  }
  const left = Math.min(d.x0, d.x1) * m.scale
  const top = Math.min(d.y0, d.y1) * m.scale
  const w = Math.abs(d.x1 - d.x0) * m.scale
  const h = Math.abs(d.y1 - d.y0) * m.scale
  if (w < 2 || h < 2) {
    return null
  }
  return { left: `${left}px`, top: `${top}px`, width: `${w}px`, height: `${h}px` }
})

const selectedZoomCallout = computed(() =>
  zoomCallouts.value.find((c) => c.id === selectedZoomCalloutId.value) ?? null
)

const selectedZoomCalloutIndex = computed(() => {
  if (!selectedZoomCalloutId.value) {
    return -1
  }
  return zoomCallouts.value.findIndex((c) => c.id === selectedZoomCalloutId.value)
})

const selectedCalloutZoomLevel = computed({
  get() {
    const c = selectedZoomCallout.value
    return c?.zoomLevel ?? zoomDetailLevel.value
  },
  set(value) {
    const level = Math.max(50, Math.min(400, Math.round(Number(value) || 150)))
    const c = selectedZoomCallout.value
    if (!c) {
      zoomDetailLevel.value = level
      return
    }
    applyZoomLevelToCallout(c.id, level)
  }
})

const drawingPreviewFill = computed(() => {
  if (drawFillEnabled.value && drawFillColor.value) {
    return drawFillColor.value
  }
  return 'none'
})

const drawingPreviewFillOpacity = computed(() => (drawingPreviewFill.value !== 'none' ? 0.35 : 0))

/** Ponta de seta em coords do ecrã (shaft termina na base do triângulo). */
const arrowHeadLayout = (x0, y0, x1, y1, strokeWidth) => {
  const dx = x1 - x0
  const dy = y1 - y0
  const len = Math.hypot(dx, dy)
  if (len < 1) {
    return null
  }
  const ux = dx / len
  const uy = dy / len
  const headLen = Math.max(8, Math.min(48, strokeWidth * 4))
  const halfWidth = headLen * 0.45
  const shaftX = x1 - ux * headLen
  const shaftY = y1 - uy * headLen
  const px = -uy
  const py = ux
  const leftX = shaftX + px * halfWidth
  const leftY = shaftY + py * halfWidth
  const rightX = shaftX - px * halfWidth
  const rightY = shaftY - py * halfWidth
  return {
    shaftX,
    shaftY,
    headPoints: `${x1},${y1} ${leftX},${leftY} ${rightX},${rightY}`
  }
}

const drawingRubberBand = computed(() => {
  const d = drawDrag.value
  if (!d?.active) {
    return null
  }
  const t = drawingTool.value
  if (!['line', 'arrow', 'rectangle', 'ellipse', 'circle'].includes(t)) {
    return null
  }
  const { x0, y0, x1, y1 } = d
  if (t === 'line') {
    return { tool: 'line', x0, y0, x1, y1 }
  }
  if (t === 'arrow') {
    const head = arrowHeadLayout(x0, y0, x1, y1, drawStrokeWidth.value)
    if (!head) {
      return { tool: 'line', x0, y0, x1, y1 }
    }
    return { tool: 'arrow', x0, y0, x1, y1, ...head }
  }
  const left = Math.min(x0, x1)
  const top = Math.min(y0, y1)
  const w = Math.abs(x1 - x0)
  const h = Math.abs(y1 - y0)
  if (t === 'rectangle') {
    return { tool: 'rectangle', left, top, w, h }
  }
  if (t === 'ellipse') {
    return {
      tool: 'ellipse',
      cx: left + w / 2,
      cy: top + h / 2,
      rx: Math.max(1, w / 2),
      ry: Math.max(1, h / 2)
    }
  }
  if (t === 'circle') {
    const side = Math.max(2, Math.min(w, h))
    return {
      tool: 'circle',
      cx: left + w / 2,
      cy: top + h / 2,
      r: side / 2
    }
  }
  return null
})

const showDrawingLiveOverlay = computed(
  () =>
    !usesLayoutDrawingSpace.value &&
    (!!drawingRubberBand.value ||
      (drawingTool.value === 'pen' && penDraftPoints.value.length > 1) ||
      (drawingTool.value === 'polygon' && pathDraftPoints.value.length > 0) ||
      (drawingTool.value === 'bezier' &&
        pathDraftPoints.value.length > 0 &&
        pathDraftPoints.value.length < 4))
)

const showLayoutDrawingLiveOverlay = computed(
  () =>
    usesLayoutDrawingSpace.value &&
    (!!drawingRubberBand.value ||
      (drawingTool.value === 'pen' && penDraftPoints.value.length > 1) ||
      (drawingTool.value === 'polygon' && pathDraftPoints.value.length > 0) ||
      (drawingTool.value === 'bezier' &&
        pathDraftPoints.value.length > 0 &&
        pathDraftPoints.value.length < 4))
)

const penDraftPointsAttr = computed(() =>
  penDraftPoints.value.map((p) => `${p.x},${p.y}`).join(' ')
)

const polygonDraftPointsAttr = computed(() =>
  pathDraftPoints.value.map((p) => `${p.x},${p.y}`).join(' ')
)

const bezierDraftPathD = computed(() => {
  if (drawingTool.value !== 'bezier' || pathDraftPoints.value.length !== 3 || !pathDraftHoverPos.value) {
    return ''
  }
  const [p0, p1, p2] = pathDraftPoints.value
  const h = pathDraftHoverPos.value
  return `M ${p0.x} ${p0.y} C ${p1.x} ${p1.y} ${p2.x} ${p2.y} ${h.x} ${h.y}`
})

/** object-fit: contain — px no ecrã por cada px natural da imagem. */
const maskBrushDisplayScale = () => {
  const el = imageRef.value
  if (!el?.naturalWidth || !el?.naturalHeight) {
    return 1
  }
  const nw = el.naturalWidth
  const nh = el.naturalHeight
  const rw = el.clientWidth
  const rh = el.clientHeight
  if (!rw || !rh) {
    return 1
  }
  return Math.min(rw / nw, rh / nh)
}

/** Cursor em anel 128×128 alinhado ao raio real do traço na foto visível. */
const buildMaskBrushCursor = (fillHex, rNatPx) => {
  const sw = 2
  const vb = 128
  const c = Math.floor(vb / 2)
  const cap = Math.max(2, c - sw - 1)
  const rDisplay = rNatPx * maskBrushDisplayScale()
  const rr = Math.min(cap, Math.max(1.5, rDisplay))
  const hx = Math.min(c, vb - 1)
  const bust = `s${maskBrushDisplayScale().toFixed(4)}n${rNatPx}`
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${vb}" height="${vb}" viewBox="0 0 ${vb} ${vb}"><!--${bust}--><circle cx="${c}" cy="${c}" r="${rr}" fill="${fillHex}" fill-opacity="0.42" stroke="#fff" stroke-width="${sw}"/></svg>`
  return {
    cursor: `url("data:image/svg+xml,${encodeURIComponent(svg)}") ${hx} ${hx}, crosshair`
  }
}

const maskBrushCursorStyle = computed(() => {
  void maskBrushRadiusNatural.value
  void imageNaturalVersion.value
  const rNat = effectiveMaskBrushRadius.value
  const erase = maskBrushEraseMode.value
  if (
    showBlurRegion.value &&
    blurShapeMode.value === 'brush' &&
    !resizeDirection.value
  ) {
    return buildMaskBrushCursor(erase ? '#888888' : '#c4b5fd', rNat)
  }
  if (
    showPixelateRegion.value &&
    pixelateShapeMode.value === 'brush' &&
    !resizeDirection.value
  ) {
    return buildMaskBrushCursor(erase ? '#888888' : '#ffc864', rNat)
  }
  return {}
})

const clearPixelateBrushMask = () => {
  pixelateMaskDirty.value = false
  pixelBrushMaskLast = null
  if (pixelateBrushCtx && pixelateBrushCanvas) {
    pixelateBrushCtx.fillStyle = '#000000'
    pixelateBrushCtx.fillRect(0, 0, pixelBrushMaskW, pixelBrushMaskH)
  }
}

/**
 * Lado maior do canvas da máscara (px).
 * Até ~18 MP usamos resolução nativa: o raio do slider (px na foto) = raio na máscara 1:1.
 */
const getBrushMaskCanvasMaxSide = (nw, nh) => {
  const longEdge = Math.max(nw, nh)
  const pixels = nw * nh
  if (pixels > 0 && pixels <= 18_000_000) {
    return longEdge
  }
  const cap = 5600
  return Math.min(cap, longEdge)
}

const ensurePixelateBrushCanvas = () => {
  const el = imageRef.value
  if (!el || !el.naturalWidth || !el.naturalHeight) {
    return false
  }
  const nw = el.naturalWidth
  const nh = el.naturalHeight
  const maxSide = getBrushMaskCanvasMaxSide(nw, nh)
  const scale = Math.min(1, maxSide / Math.max(nw, nh))
  const mw = Math.max(1, Math.round(nw * scale))
  const mh = Math.max(1, Math.round(nh * scale))
  if (
    pixelateBrushCanvas &&
    pixelBrushStoredNaturalW === nw &&
    pixelBrushStoredNaturalH === nh &&
    pixelBrushMaskW === mw &&
    pixelBrushMaskH === mh
  ) {
    return true
  }
  pixelateBrushCanvas = document.createElement('canvas')
  pixelateBrushCanvas.width = mw
  pixelateBrushCanvas.height = mh
  pixelateBrushCtx = pixelateBrushCanvas.getContext('2d')
  if (!pixelateBrushCtx) {
    pixelateBrushCanvas = null
    return false
  }
  pixelBrushMaskW = mw
  pixelBrushMaskH = mh
  pixelBrushStoredNaturalW = nw
  pixelBrushStoredNaturalH = nh
  pixelateBrushCtx.fillStyle = '#000000'
  pixelateBrushCtx.fillRect(0, 0, mw, mh)
  pixelateMaskDirty.value = false
  pixelBrushMaskLast = null
  return true
}

const naturalPointToMask = (nx, ny) => {
  const nw = pixelBrushStoredNaturalW || 1
  const nh = pixelBrushStoredNaturalH || 1
  return {
    mx: (Math.max(0, Math.min(nw - 1, nx)) / nw) * pixelBrushMaskW,
    my: (Math.max(0, Math.min(nh - 1, ny)) / nh) * pixelBrushMaskH
  }
}

const maskBrushRadiusOnCanvas = (rNat, storedNaturalW, maskCanvasW) =>
  Math.max(0.25, (rNat / (storedNaturalW || 1)) * maskCanvasW)

const resolveMaskBrushEraseFromEvent = (e) => {
  if (maskBrushEraseMode.value) {
    return true
  }
  if (!e) {
    return false
  }
  if (e.altKey) {
    return true
  }
  if (typeof e.button === 'number' && e.button === 2) {
    return true
  }
  if (typeof e.buttons === 'number' && (e.buttons & 2) === 2) {
    return true
  }
  return false
}

/** Círculos sólidos na máscara (sem stroke antialiased que alarga o efeito). */
const paintMaskBrushSegment = (
  ctx,
  pointToMask,
  nx1,
  ny1,
  nx2,
  ny2,
  rNat,
  storedNaturalW,
  maskCanvasW,
  erase = false
) => {
  const rMask = maskBrushRadiusOnCanvas(rNat, storedNaturalW, maskCanvasW)
  const dx = nx2 - nx1
  const dy = ny2 - ny1
  const dist = Math.hypot(dx, dy)
  const step = Math.max(0.5, rMask * 0.55)
  const n = Math.max(1, Math.ceil(dist / step))
  ctx.save()
  // Apagar = preto sólido na máscara (branco = aplicar efeito). destination-out deixa
  // transparência que o servidor pode ler como branco e manter o efeito.
  ctx.fillStyle = erase ? '#000000' : '#ffffff'
  ctx.globalCompositeOperation = 'source-over'
  for (let i = 0; i <= n; i++) {
    const t = i / n
    const { mx, my } = pointToMask(nx1 + dx * t, ny1 + dy * t)
    ctx.beginPath()
    ctx.arc(mx, my, rMask, 0, Math.PI * 2)
    ctx.fill()
  }
  ctx.restore()
}

const drawPixelateBrushStroke = (nx1, ny1, nx2, ny2) => {
  if (!pixelateBrushCtx) {
    return
  }
  paintMaskBrushSegment(
    pixelateBrushCtx,
    naturalPointToMask,
    nx1,
    ny1,
    nx2,
    ny2,
    effectiveMaskBrushRadius.value,
    pixelBrushStoredNaturalW,
    pixelBrushMaskW,
    maskBrushStrokeErase
  )
  pixelateMaskDirty.value = true
}

const eventToNaturalPoint = (e) => {
  const pos = clientToImgLocal(e)
  return displayPointToNatural(pos.x, pos.y)
}

const handlePixelateBrushMove = (e) => {
  if (!isPixelateBrushDrawing.value || !pixelateBrushCtx) {
    return
  }
  if (e.cancelable && e.type === 'touchmove') {
    e.preventDefault()
  }
  const p = eventToNaturalPoint(e)
  if (pixelBrushMaskLast) {
    drawPixelateBrushStroke(pixelBrushMaskLast.x, pixelBrushMaskLast.y, p.x, p.y)
    queuePixelateBrushPreview()
  }
  pixelBrushMaskLast = { x: p.x, y: p.y }
}

const stopPixelateBrushStroke = () => {
  if (!isPixelateBrushDrawing.value) {
    return
  }
  isPixelateBrushDrawing.value = false
  pixelBrushMaskLast = null
  maskBrushStrokeErase = false
  window.removeEventListener('mousemove', handlePixelateBrushMove)
  window.removeEventListener('mouseup', stopPixelateBrushStroke)
  window.removeEventListener('touchmove', handlePixelateBrushMove, { passive: false })
  window.removeEventListener('touchend', stopPixelateBrushStroke)
  clearPixelateBrushPreviewDebounce()
  flushPreview()
}

const startPixelateBrushStroke = (e) => {
  if (!showPixelateRegion.value || pixelateShapeMode.value !== 'brush' || resizeDirection.value) {
    return
  }
  if (!ensurePixelateBrushCanvas()) {
    return
  }
  e.preventDefault()
  e.stopPropagation()
  maskBrushStrokeErase = resolveMaskBrushEraseFromEvent(e)
  const p = eventToNaturalPoint(e)
  isPixelateBrushDrawing.value = true
  pixelBrushMaskLast = { x: p.x, y: p.y }
  paintMaskBrushSegment(
    pixelateBrushCtx,
    naturalPointToMask,
    p.x,
    p.y,
    p.x,
    p.y,
    effectiveMaskBrushRadius.value,
    pixelBrushStoredNaturalW,
    pixelBrushMaskW,
    maskBrushStrokeErase
  )
  pixelateMaskDirty.value = true
  flushPreview()
  window.addEventListener('mousemove', handlePixelateBrushMove)
  window.addEventListener('mouseup', stopPixelateBrushStroke)
  window.addEventListener('touchmove', handlePixelateBrushMove, { passive: false })
  window.addEventListener('touchend', stopPixelateBrushStroke)
}

/** PNG opaco: fundo preto + traços brancos (evita transparência ser lida como branco no servidor). */
const exportBrushMaskCanvas = (canvas) => {
  if (!canvas) {
    return null
  }
  try {
    const flat = document.createElement('canvas')
    flat.width = canvas.width
    flat.height = canvas.height
    const fctx = flat.getContext('2d', { alpha: false })
    if (!fctx) {
      return canvas.toDataURL('image/png')
    }
    fctx.fillStyle = '#000000'
    fctx.fillRect(0, 0, flat.width, flat.height)
    fctx.drawImage(canvas, 0, 0)
    return flat.toDataURL('image/png')
  } catch {
    return null
  }
}

const exportPixelateMaskDataUrl = () => {
  if (!pixelateBrushCanvas || !pixelateMaskDirty.value) {
    return null
  }
  return exportBrushMaskCanvas(pixelateBrushCanvas)
}

const clearBlurBrushMask = () => {
  blurMaskDirty.value = false
  blurBrushMaskLast = null
  if (blurBrushCtx && blurBrushCanvas) {
    blurBrushCtx.fillStyle = '#000000'
    blurBrushCtx.fillRect(0, 0, blurBrushMaskW, blurBrushMaskH)
  }
}

const ensureBlurBrushCanvas = () => {
  const el = imageRef.value
  if (!el || !el.naturalWidth || !el.naturalHeight) {
    return false
  }
  const nw = el.naturalWidth
  const nh = el.naturalHeight
  const maxSide = getBrushMaskCanvasMaxSide(nw, nh)
  const scale = Math.min(1, maxSide / Math.max(nw, nh))
  const mw = Math.max(1, Math.round(nw * scale))
  const mh = Math.max(1, Math.round(nh * scale))
  if (
    blurBrushCanvas &&
    blurBrushStoredNaturalW === nw &&
    blurBrushStoredNaturalH === nh &&
    blurBrushMaskW === mw &&
    blurBrushMaskH === mh
  ) {
    return true
  }
  blurBrushCanvas = document.createElement('canvas')
  blurBrushCanvas.width = mw
  blurBrushCanvas.height = mh
  blurBrushCtx = blurBrushCanvas.getContext('2d')
  if (!blurBrushCtx) {
    blurBrushCanvas = null
    return false
  }
  blurBrushMaskW = mw
  blurBrushMaskH = mh
  blurBrushStoredNaturalW = nw
  blurBrushStoredNaturalH = nh
  blurBrushCtx.fillStyle = '#000000'
  blurBrushCtx.fillRect(0, 0, mw, mh)
  blurMaskDirty.value = false
  blurBrushMaskLast = null
  return true
}

const naturalPointToBlurMask = (nx, ny) => {
  const nw = blurBrushStoredNaturalW || 1
  const nh = blurBrushStoredNaturalH || 1
  return {
    mx: (Math.max(0, Math.min(nw - 1, nx)) / nw) * blurBrushMaskW,
    my: (Math.max(0, Math.min(nh - 1, ny)) / nh) * blurBrushMaskH
  }
}

const drawBlurBrushStroke = (nx1, ny1, nx2, ny2) => {
  if (!blurBrushCtx) {
    return
  }
  paintMaskBrushSegment(
    blurBrushCtx,
    naturalPointToBlurMask,
    nx1,
    ny1,
    nx2,
    ny2,
    effectiveMaskBrushRadius.value,
    blurBrushStoredNaturalW,
    blurBrushMaskW,
    maskBrushStrokeErase
  )
  blurMaskDirty.value = true
}

const handleBlurBrushMove = (e) => {
  if (!isBlurBrushDrawing.value || !blurBrushCtx) {
    return
  }
  if (e.cancelable && e.type === 'touchmove') {
    e.preventDefault()
  }
  const p = eventToNaturalPoint(e)
  if (blurBrushMaskLast) {
    drawBlurBrushStroke(blurBrushMaskLast.x, blurBrushMaskLast.y, p.x, p.y)
    queueBlurBrushPreview()
  }
  blurBrushMaskLast = { x: p.x, y: p.y }
}

const stopBlurBrushStroke = () => {
  if (!isBlurBrushDrawing.value) {
    return
  }
  isBlurBrushDrawing.value = false
  blurBrushMaskLast = null
  maskBrushStrokeErase = false
  window.removeEventListener('mousemove', handleBlurBrushMove)
  window.removeEventListener('mouseup', stopBlurBrushStroke)
  window.removeEventListener('touchmove', handleBlurBrushMove, { passive: false })
  window.removeEventListener('touchend', stopBlurBrushStroke)
  clearBlurBrushPreviewDebounce()
  flushPreview()
}

const startBlurBrushStroke = (e) => {
  if (!showBlurRegion.value || blurShapeMode.value !== 'brush' || resizeDirection.value) {
    return
  }
  if (!ensureBlurBrushCanvas()) {
    return
  }
  e.preventDefault()
  e.stopPropagation()
  maskBrushStrokeErase = resolveMaskBrushEraseFromEvent(e)
  const p = eventToNaturalPoint(e)
  isBlurBrushDrawing.value = true
  blurBrushMaskLast = { x: p.x, y: p.y }
  paintMaskBrushSegment(
    blurBrushCtx,
    naturalPointToBlurMask,
    p.x,
    p.y,
    p.x,
    p.y,
    effectiveMaskBrushRadius.value,
    blurBrushStoredNaturalW,
    blurBrushMaskW,
    maskBrushStrokeErase
  )
  blurMaskDirty.value = true
  flushPreview()
  window.addEventListener('mousemove', handleBlurBrushMove)
  window.addEventListener('mouseup', stopBlurBrushStroke)
  window.addEventListener('touchmove', handleBlurBrushMove, { passive: false })
  window.addEventListener('touchend', stopBlurBrushStroke)
}

const exportBlurMaskDataUrl = () => {
  if (!blurBrushCanvas || !blurMaskDirty.value) {
    return null
  }
  return exportBrushMaskCanvas(blurBrushCanvas)
}

/** Retângulo da foto visível dentro do elemento img (object-fit: contain), em coords do elemento. */
const getImageOnScreenBounds = () => {
  const el = imageRef.value
  if (!el) {
    return { ox: 0, oy: 0, drawnW: 0, drawnH: 0 }
  }
  const nw = el.naturalWidth
  const nh = el.naturalHeight
  const rw = el.clientWidth
  const rh = el.clientHeight
  if (!nw || !nh) {
    return { ox: 0, oy: 0, drawnW: 0, drawnH: 0 }
  }
  const scale = Math.min(rw / nw, rh / nh)
  const drawnW = nw * scale
  const drawnH = nh * scale
  const ox = (rw - drawnW) / 2
  const oy = (rh - drawnH) / 2
  return { ox, oy, drawnW, drawnH }
}

const syncImageNaturalMetrics = () => {
  const el = imageRef.value
  if (!el?.naturalWidth || !el?.naturalHeight) {
    return
  }
  const nw = el.naturalWidth
  const nh = el.naturalHeight
  imageSize.value = { width: nw, height: nh }
  imageNaturalVersion.value++
  const key = `${nw}x${nh}`
  const b = maskBrushRadiusBounds.value
  if (key !== maskBrushImageKey) {
    maskBrushImageKey = key
    maskBrushRadiusNatural.value = Math.max(b.min, Math.min(b.max, Math.round(Math.min(nw, nh) * 0.022)))
  } else {
    maskBrushRadiusNatural.value = Math.max(b.min, Math.min(b.max, maskBrushRadiusNatural.value))
  }
}

const onImageLoad = () => {
  syncImageNaturalMetrics()
  if (showPixelateRegion.value && pixelateShapeMode.value === 'brush') {
    ensurePixelateBrushCanvas()
  }
  if (showBlurRegion.value && blurShapeMode.value === 'brush') {
    ensureBlurBrushCanvas()
  }
  const el = imageRef.value
  if (!el?.naturalWidth) {
    return
  }
  if (showCrop.value) {
    refreshCropReferenceSizeFromImage()
    if (cropPendingReferenceReset.value || !cropNatural.value.width) {
      initCropNaturalFull()
      cropPendingReferenceReset.value = false
    }
    scheduleCropDisplaySync()
  } else if (!committedCrop.value) {
    refreshCropReferenceSizeFromImage()
    cropNatural.value = { x: 0, y: 0, width: 0, height: 0 }
    cropPendingReferenceReset.value = true
  }
  initEditHistoryBaseline()
}

let cropDisplaySyncRaf = 0
let cropLayoutObserver = null

const cancelCropDisplaySyncRaf = () => {
  if (cropDisplaySyncRaf) {
    cancelAnimationFrame(cropDisplaySyncRaf)
    cropDisplaySyncRaf = 0
  }
}

const trySyncCropDisplayNow = () => {
  const el = imageRef.value
  if (!showCrop.value || !el?.naturalWidth || !el?.naturalHeight) {
    return false
  }
  syncCropDisplayFromNatural()
  return true
}

/** Re-sincroniza o crop após reflow (ex. pb-40) e quando a imagem tiver dimensões naturais. */
const scheduleCropDisplaySync = () => {
  if (!showCrop.value) {
    return
  }
  cancelCropDisplaySyncRaf()
  nextTick(() => {
    cropDisplaySyncRaf = requestAnimationFrame(() => {
      cropDisplaySyncRaf = requestAnimationFrame(() => {
        cropDisplaySyncRaf = 0
        if (cropPendingReferenceReset.value) {
          refreshCropReferenceSizeFromImage()
          initCropNaturalFull()
          cropPendingReferenceReset.value = false
        }
        if (trySyncCropDisplayNow()) {
          return
        }
        const el = imageRef.value
        if (!el) {
          return
        }
        const onReady = () => {
          refreshCropReferenceSizeFromImage()
          initCropNaturalFull()
          cropPendingReferenceReset.value = false
          trySyncCropDisplayNow()
        }
        el.addEventListener('load', onReady, { once: true })
      })
    })
  })
}

const disconnectCropLayoutObserver = () => {
  if (cropLayoutObserver) {
    cropLayoutObserver.disconnect()
    cropLayoutObserver = null
  }
}

const ensureCropLayoutObserver = () => {
  const el = imageRef.value
  if (!el || cropLayoutObserver) {
    return
  }
  cropLayoutObserver = new ResizeObserver(() => {
    if (showCrop.value) {
      trySyncCropDisplayNow()
    }
  })
  cropLayoutObserver.observe(el)
}

const stopCropPan = () => {
  cancelCropPanPreviewRaf()
  document.removeEventListener('mousemove', handleCropPan)
  document.removeEventListener('mouseup', stopCropPan)
  document.removeEventListener('touchmove', handleCropPan)
  document.removeEventListener('touchend', stopCropPan)
  isDraggingCropPan.value = false
}

const startCropPan = (e) => {
  if (!showCrop.value || resizeDirection.value) return
  const el = imageRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const cx = e.touches ? e.touches[0].clientX : e.clientX
  const cy = e.touches ? e.touches[0].clientY : e.clientY
  const x = cx - rect.left
  const y = cy - rect.top
  const pNat = displayPointToCropReference(x, y)
  cropPanGrabNat.value = { x: pNat.x - cropNatural.value.x, y: pNat.y - cropNatural.value.y }
  isDraggingCropPan.value = true
  document.addEventListener('mousemove', handleCropPan)
  document.addEventListener('mouseup', stopCropPan)
  document.addEventListener('touchmove', handleCropPan, { passive: false })
  document.addEventListener('touchend', stopCropPan)
}

const handleCropPan = (e) => {
  if (!isDraggingCropPan.value) return
  if (e.type === 'touchmove' && e.cancelable) {
    e.preventDefault()
  }
  const el = imageRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const cx = e.touches ? e.touches[0].clientX : e.clientX
  const cy = e.touches ? e.touches[0].clientY : e.clientY
  const x = cx - rect.left
  const y = cy - rect.top
  const pNat = displayPointToCropReference(x, y)
  const refW = cropReferenceSize.value.width
  const refH = cropReferenceSize.value.height
  const cn = cropNatural.value
  let nx = pNat.x - cropPanGrabNat.value.x
  let ny = pNat.y - cropPanGrabNat.value.y
  nx = Math.max(0, Math.min(Math.max(0, refW - cn.width), nx))
  ny = Math.max(0, Math.min(Math.max(0, refH - cn.height), ny))
  cropNatural.value = { ...cn, x: nx, y: ny }
  syncCropDisplayFromNatural()
}

const stopBlurPan = () => {
  cancelBlurPanPreviewRaf()
  if (!isDraggingBlurPan.value) return
  isDraggingBlurPan.value = false
  document.removeEventListener('mousemove', handleBlurPan)
  document.removeEventListener('mouseup', stopBlurPan)
  document.removeEventListener('touchmove', handleBlurPan)
  document.removeEventListener('touchend', stopBlurPan)
  applyChanges()
}

const startBlurPan = (e) => {
  if (!showBlurRegion.value || blurShapeMode.value !== 'rectangle' || resizeDirection.value) return
  const el = imageRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const cx = e.touches ? e.touches[0].clientX : e.clientX
  const cy = e.touches ? e.touches[0].clientY : e.clientY
  const x = cx - rect.left
  const y = cy - rect.top
  blurPanGrab.value = { x: x - blurStart.value.x, y: y - blurStart.value.y }
  isDraggingBlurPan.value = true
  document.addEventListener('mousemove', handleBlurPan)
  document.addEventListener('mouseup', stopBlurPan)
  document.addEventListener('touchmove', handleBlurPan, { passive: false })
  document.addEventListener('touchend', stopBlurPan)
}

const handleBlurPan = (e) => {
  if (!isDraggingBlurPan.value) return
  if (e.type === 'touchmove' && e.cancelable) {
    e.preventDefault()
  }
  const el = imageRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const cx = e.touches ? e.touches[0].clientX : e.clientX
  const cy = e.touches ? e.touches[0].clientY : e.clientY
  const x = cx - rect.left
  const y = cy - rect.top
  let nx = x - blurPanGrab.value.x
  let ny = y - blurPanGrab.value.y
  const { ox, oy, drawnW, drawnH } = getImageOnScreenBounds()
  nx = Math.max(ox, Math.min(ox + drawnW - blurSize.value.width, nx))
  ny = Math.max(oy, Math.min(oy + drawnH - blurSize.value.height, ny))
  blurStart.value = { x: nx, y: ny }
  if (!blurPanPreviewRaf) {
    blurPanPreviewRaf = requestAnimationFrame(() => {
      blurPanPreviewRaf = 0
      scheduleApplyChanges()
    })
  }
}

const stopPixelatePan = () => {
  cancelPixelatePanPreviewRaf()
  if (!isDraggingPixelatePan.value) return
  isDraggingPixelatePan.value = false
  document.removeEventListener('mousemove', handlePixelatePan)
  document.removeEventListener('mouseup', stopPixelatePan)
  document.removeEventListener('touchmove', handlePixelatePan)
  document.removeEventListener('touchend', stopPixelatePan)
  applyChanges()
}

const startPixelatePan = (e) => {
  if (!showPixelateRegion.value || pixelateShapeMode.value !== 'rectangle' || resizeDirection.value) return
  const el = imageRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const cx = e.touches ? e.touches[0].clientX : e.clientX
  const cy = e.touches ? e.touches[0].clientY : e.clientY
  const x = cx - rect.left
  const y = cy - rect.top
  pixelatePanGrab.value = { x: x - pixelateStart.value.x, y: y - pixelateStart.value.y }
  isDraggingPixelatePan.value = true
  document.addEventListener('mousemove', handlePixelatePan)
  document.addEventListener('mouseup', stopPixelatePan)
  document.addEventListener('touchmove', handlePixelatePan, { passive: false })
  document.addEventListener('touchend', stopPixelatePan)
}

const handlePixelatePan = (e) => {
  if (!isDraggingPixelatePan.value) return
  if (e.type === 'touchmove' && e.cancelable) {
    e.preventDefault()
  }
  const el = imageRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const cx = e.touches ? e.touches[0].clientX : e.clientX
  const cy = e.touches ? e.touches[0].clientY : e.clientY
  const x = cx - rect.left
  const y = cy - rect.top
  let nx = x - pixelatePanGrab.value.x
  let ny = y - pixelatePanGrab.value.y
  const { ox, oy, drawnW, drawnH } = getImageOnScreenBounds()
  nx = Math.max(ox, Math.min(ox + drawnW - pixelateSize.value.width, nx))
  ny = Math.max(oy, Math.min(oy + drawnH - pixelateSize.value.height, ny))
  pixelateStart.value = { x: nx, y: ny }
  if (!pixelatePanPreviewRaf) {
    pixelatePanPreviewRaf = requestAnimationFrame(() => {
      pixelatePanPreviewRaf = 0
      scheduleApplyChanges()
    })
  }
}

const startResize = (kind, direction) => {
  stopBlurPan()
  stopPixelatePan()
  stopCropPan()
  resizeKind.value = kind
  resizeDirection.value = direction
  document.addEventListener('mousemove', handleResize)
  document.addEventListener('mouseup', stopResize)
  document.addEventListener('touchmove', handleResize, { passive: false })
  document.addEventListener('touchend', stopResize)
}

const handleResize = (e) => {
  if (!resizeDirection.value || !imageRef.value) return
  if (e.type === 'touchmove' && e.cancelable) {
    e.preventDefault()
  }

  const rect = imageRef.value.getBoundingClientRect()
  const sx = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const sy = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top

  if (resizeKind.value === 'crop') {
    applyCropResizeAt(sx, sy)
    return
  }

  const boxStart = resizeKind.value === 'blur' ? blurStart : pixelateStart
  const boxSize = resizeKind.value === 'blur' ? blurSize : pixelateSize

  const { ox, oy, drawnW, drawnH } = getImageOnScreenBounds()
  const minX = ox
  const minY = oy
  const maxX = ox + drawnW
  const maxY = oy + drawnH
  const px = Math.max(minX, Math.min(maxX, sx))
  const py = Math.max(minY, Math.min(maxY, sy))

  const minSize = resizeKind.value === 'crop' ? 48 : 32
  const left = boxStart.value.x
  const top = boxStart.value.y
  const right = left + boxSize.value.width
  const bottom = top + boxSize.value.height

  switch (resizeDirection.value) {
    case 'nw': {
      const nx = Math.max(minX, Math.min(right - minSize, px))
      const ny = Math.max(minY, Math.min(bottom - minSize, py))
      boxStart.value.x = nx
      boxStart.value.y = ny
      boxSize.value.width = right - nx
      boxSize.value.height = bottom - ny
      break
    }
    case 'ne': {
      const nx = Math.max(left + minSize, Math.min(maxX, px))
      const ny = Math.max(minY, Math.min(bottom - minSize, py))
      boxStart.value.y = ny
      boxSize.value.width = nx - left
      boxSize.value.height = bottom - ny
      break
    }
    case 'sw': {
      const nx = Math.max(minX, Math.min(right - minSize, px))
      const ny = Math.max(top + minSize, Math.min(maxY, py))
      boxStart.value.x = nx
      boxSize.value.width = right - nx
      boxSize.value.height = ny - top
      break
    }
    case 'se': {
      const nx = Math.max(left + minSize, Math.min(maxX, px))
      const ny = Math.max(top + minSize, Math.min(maxY, py))
      boxSize.value.width = nx - left
      boxSize.value.height = ny - top
      break
    }
    case 'w': {
      const nx = Math.max(minX, Math.min(right - minSize, px))
      boxStart.value.x = nx
      boxSize.value.width = right - nx
      break
    }
    case 'e': {
      const nx = Math.max(left + minSize, Math.min(maxX, px))
      boxSize.value.width = nx - left
      break
    }
    case 'n': {
      const ny = Math.max(minY, Math.min(bottom - minSize, py))
      boxStart.value.y = ny
      boxSize.value.height = bottom - ny
      break
    }
    case 's': {
      const ny = Math.max(top + minSize, Math.min(maxY, py))
      boxSize.value.height = ny - top
      break
    }
  }
}

const stopResize = () => {
  const had = !!resizeDirection.value
  const wasCrop = resizeKind.value === 'crop'
  resizeDirection.value = null
  cancelCropResizePreviewRaf()
  document.removeEventListener('mousemove', handleResize)
  document.removeEventListener('mouseup', stopResize)
  document.removeEventListener('touchmove', handleResize)
  document.removeEventListener('touchend', stopResize)
  if (had && !wasCrop) {
    applyChanges()
  }
}

const exitCropUi = () => {
  disconnectCropLayoutObserver()
  cancelCropDisplaySyncRaf()
  cancelCropPanPreviewRaf()
  cancelCropResizePreviewRaf()
  stopCropPan()
  showCrop.value = false
  cropStart.value = { x: 0, y: 0 }
  cropSize.value = { width: 0, height: 0 }
}

/** Repõe área de crop a foto inteira e sincroniza o retângulo visível. */
const beginCropSession = () => {
  cropPendingReferenceReset.value = true
  disconnectCropLayoutObserver()
  cropLayoutObserver = null
  if (refreshCropReferenceSizeFromImage()) {
    initCropNaturalFull()
    cropPendingReferenceReset.value = false
    nextTick(() => {
      requestAnimationFrame(() => trySyncCropDisplayNow())
    })
  }
  ensureCropLayoutObserver()
}

const confirmCrop = () => {
  if (!showCrop.value) {
    return
  }
  const cropToApply = captureCropFromDisplay()
  if (!cropToApply) {
    emit('error', 'Ajuste o recorte: a área tem de ser menor que a foto inteira.')
    return
  }
  committedCrop.value = cropToApply
  cropNatural.value = { ...cropToApply }
  exitCropUi()
  applyChanges({ crop: cropToApply })
}

const cancelCrop = () => {
  if (!showCrop.value) {
    return
  }
  exitCropUi()
  cropNatural.value = { x: 0, y: 0, width: 0, height: 0 }
  cropPendingReferenceReset.value = true
  applyChanges({ crop: committedCrop.value ?? null })
}

const toggleCrop = () => {
  closeDrawingMenu()
  if (!showCrop.value) {
    commitPendingEffectEdits()
    blurShapeMode.value = 'rectangle'
    pixelateShapeMode.value = 'rectangle'
    showCrop.value = true
    beginCropSession()
    applyChanges({ crop: null })
    scheduleCropDisplaySync()
    return
  }
  confirmCrop()
}

const commitPixelateBrushMaskIfDirty = () => {
  if (!pixelateMaskDirty.value || !pixelateBrushCanvas) {
    return
  }
  const dataUrl = exportBrushMaskCanvas(pixelateBrushCanvas)
  if (dataUrl) {
    committedPixelateMask.value = dataUrl
  }
}

const commitBlurBrushMaskIfDirty = () => {
  if (!blurMaskDirty.value || !blurBrushCanvas) {
    return
  }
  const dataUrl = exportBrushMaskCanvas(blurBrushCanvas)
  if (dataUrl) {
    committedBlurMask.value = dataUrl
  }
}

/** Confirma pixelização em curso antes de mudar para outra ferramenta de efeito. */
const prepareSwitchFromPixelateTool = () => {
  if (!showPixelateRegion.value) {
    return
  }
  if (pixelateShapeMode.value === 'rectangle') {
    const natural = capturePixelateRegionFromDisplay()
    if (natural) {
      committedPixelateRegion.value = natural
      pixelateApplyGlobal.value = false
    }
    exitPixelateRectangleUi()
    return
  }
  if (pixelateShapeMode.value === 'brush') {
    commitPixelateBrushMaskIfDirty()
    stopPixelateBrushStroke()
    stopPixelatePan()
    showPixelateRegion.value = false
    clearPixelateBrushMask()
    pixelateShapeMode.value = 'rectangle'
  }
}

/** Confirma desfoque em curso antes de mudar para outra ferramenta de efeito. */
const prepareSwitchFromBlurTool = () => {
  if (!showBlurRegion.value) {
    return
  }
  if (blurShapeMode.value === 'rectangle') {
    const natural = captureBlurRegionFromDisplay()
    if (natural) {
      committedBlurRegion.value = natural
      blurApplyGlobal.value = false
    }
    exitBlurRectangleUi()
    return
  }
  if (blurShapeMode.value === 'brush') {
    commitBlurBrushMaskIfDirty()
    stopBlurBrushStroke()
    stopBlurPan()
    showBlurRegion.value = false
    clearBlurBrushMask()
    blurShapeMode.value = 'rectangle'
  }
}

/** Confirma desfoque e pixelização em curso (retângulo ou pincel) antes de mudar de ferramenta. */
const commitPendingEffectEdits = () => {
  prepareSwitchFromBlurTool()
  prepareSwitchFromPixelateTool()
}

const closeEffectOption = (kind) => {
  commitPendingEffectEdits()
  if (kind === 'blur') {
    showBlurMenu.value = false
    if (activeControl.value === 'blur') {
      activeControl.value = null
    }
  } else {
    showPixelateMenu.value = false
    if (activeControl.value === 'pixelate') {
      activeControl.value = null
    }
  }
  applyChanges()
}

const closeBlurOption = () => closeEffectOption('blur')

const closePixelateOption = () => closeEffectOption('pixelate')

const closeActiveControlPanel = () => {
  if (activeControl.value === 'blur') {
    closeBlurOption()
    return
  }
  if (activeControl.value === 'pixelate') {
    closePixelateOption()
    return
  }
  activeControl.value = null
}

const toggleControl = (control) => {
  closeDrawingMenu()
  drawingTool.value = null
  pathDraftPoints.value = []
  drawDrag.value = null
  if (control !== 'blur' && control !== 'pixelate') {
    commitPendingEffectEdits()
  }
  if (activeControl.value === control) {
    if (control === 'blur') {
      closeBlurOption()
      return
    }
    if (control === 'pixelate') {
      closePixelateOption()
      return
    }
    activeControl.value = null
    if (control === 'text') {
      selectedTextIndex.value = null
    }
  } else {
    if (activeControl.value === 'blur') {
      closeBlurOption()
    } else if (activeControl.value === 'pixelate') {
      closePixelateOption()
    }
    activeControl.value = control
    if (control === 'watermark') {
      openWatermarkDraft()
    }
    if (control === 'caption') {
      openPhotoCaptionDraft()
    }
    if (control !== 'text') {
      selectedTextIndex.value = null
    }
  }
}

const toggleFlip = (direction) => {
  closeDrawingMenu()
  commitPendingEffectEdits()
  if (direction === 'horizontal') {
    flipHorizontal.value = !flipHorizontal.value
  } else {
    flipVertical.value = !flipVertical.value
  }
  applyChanges()
}

const rotateImage = () => {
  closeDrawingMenu()
  commitPendingEffectEdits()
  rotation.value = (rotation.value + 90) % 360
  if (showCrop.value && cropNatural.value.width > 0) {
    rotateCropNatural90Cw()
    scheduleCropDisplaySync()
    applyChanges({ crop: null })
    return
  }
  if (committedCrop.value) {
    cropNatural.value = { ...committedCrop.value }
    rotateCropNatural90Cw()
    committedCrop.value = { ...cropNatural.value }
    applyChanges({ crop: committedCrop.value })
    return
  }
  if (showCrop.value) {
    refreshCropReferenceSizeFromImage()
    initCropNaturalFull()
    scheduleCropDisplaySync()
    applyChanges({ crop: null })
    return
  }
  applyChanges()
}

const startMovingText = (e, index) => {
  if (drawingTool.value) return
  if (showCrop.value || showBlurRegion.value || showPixelateRegion.value) return

  e.preventDefault()
  e.stopPropagation()

  const rect = imageRef.value.getBoundingClientRect()
  const clientX = e.touches ? e.touches[0].clientX : e.clientX
  const clientY = e.touches ? e.touches[0].clientY : e.clientY
  const px = clientX - rect.left
  const py = clientY - rect.top
  const t = texts.value[index]
  const lay = naturalTextToDisplayLayout(t)
  textOffset.value = { x: px - lay.left, y: py - lay.top }

  isMovingText.value = true
  movingTextIndex.value = index

  window.addEventListener('mousemove', moveText)
  window.addEventListener('mouseup', stopMovingText)
  window.addEventListener('touchmove', moveText, { passive: false })
  window.addEventListener('touchend', stopMovingText)
}

const moveText = (e) => {
  if (!isMovingText.value || movingTextIndex.value === null) return

  e.preventDefault()
  e.stopPropagation()

  const rect = imageRef.value.getBoundingClientRect()
  const clientX = e.touches ? e.touches[0].clientX : e.clientX
  const clientY = e.touches ? e.touches[0].clientY : e.clientY
  const px = clientX - rect.left
  const py = clientY - rect.top
  const left = px - textOffset.value.x
  const top = py - textOffset.value.y
  const nat = displayPointToNatural(Math.round(left), Math.round(top))

  texts.value[movingTextIndex.value] = {
    ...texts.value[movingTextIndex.value],
    x: nat.x,
    y: nat.y
  }

  scheduleApplyChanges()
}

const stopMovingText = () => {
  window.removeEventListener('mousemove', moveText)
  window.removeEventListener('touchmove', moveText)
  window.removeEventListener('mouseup', stopMovingText)
  window.removeEventListener('touchend', stopMovingText)
  if (isMovingText.value) {
    flushPreview()
  }
  isMovingText.value = false
  movingTextIndex.value = null
}

const removeText = (index) => {
  texts.value.splice(index, 1)
  if (selectedTextIndex.value === index) {
    selectedTextIndex.value = null
  } else if (selectedTextIndex.value !== null && selectedTextIndex.value > index) {
    selectedTextIndex.value -= 1
  }
  applyChanges()
}

const getControlLabel = (control) => {
  const labels = {
    brightness: 'Brilho',
    contrast: 'Contraste',
    saturation: 'Saturação',
    gamma: 'Gama (tons médios)',
    blur: 'Desfoque',
    pixelate: 'Pixelização (tamanho do bloco)',
    sharpen: 'Nitidez (negativo = suavizar)',
    text: 'Adicionar Texto',
    caption: 'Legendas',
    watermark: 'Marca de água'
  }
  return labels[control] || ''
}

const watermarkUserDisplay = () => {
  const fromMeta = props.photo?.user_name || props.photo?.watermark_user
  if (fromMeta && String(fromMeta).trim()) {
    return String(fromMeta).trim()
  }
  const fn = props.photo?.filename || ''
  const base = fn.replace(/^photo_/, '').replace(/\.[^.]+$/i, '')
  return base.slice(0, 48) || 'Utilizador'
}

const resolveWatermarkText = (w) => {
  if (!w || w.type !== 'text') {
    return ''
  }
  if (w.preset === 'date') {
    return new Date().toLocaleString('pt-PT', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  }
  if (w.preset === 'user') {
    return watermarkUserDisplay()
  }
  return (w.text || '').trim()
}

const watermarkConfigToPayload = (w) => {
  if (!w) {
    return null
  }
  const base = {
    enabled: true,
    type: w.type,
    position: w.position,
    opacity: w.opacity,
    margin: w.margin
  }
  if (w.type === 'image') {
    if (!w.src) {
      return null
    }
    return {
      ...base,
      src: w.src,
      image_scale: w.imageScale
    }
  }
  const text = resolveWatermarkText(w)
  if (!text) {
    return null
  }
  return {
    ...base,
    text,
    size: w.size,
    color: w.color
  }
}

const buildWatermarkPayload = () => watermarkConfigToPayload(watermarkApplied.value)

const watermarkDraftCanApply = computed(() => !!watermarkConfigToPayload(watermarkDraft.value))

const cloneWatermarkConfig = (w) => JSON.parse(JSON.stringify(w))

const openWatermarkDraft = () => {
  watermarkDraft.value = watermarkApplied.value
    ? cloneWatermarkConfig(watermarkApplied.value)
    : createDefaultWatermark()
}

const ensureWatermarkDraft = () => {
  if (!watermarkDraft.value) {
    watermarkDraft.value = createDefaultWatermark()
  }
}

const setWatermarkType = (type) => {
  ensureWatermarkDraft()
  watermarkDraft.value.type = type
}

const setWatermarkPreset = (preset) => {
  ensureWatermarkDraft()
  watermarkDraft.value.preset = preset
}

const confirmWatermark = () => {
  if (!watermarkDraftCanApply.value) {
    return
  }
  watermarkApplied.value = cloneWatermarkConfig(watermarkDraft.value)
  activeControl.value = null
  scheduleApplyChanges()
}

const removeWatermark = () => {
  watermarkApplied.value = null
  watermarkDraft.value = createDefaultWatermark()
  activeControl.value = null
  scheduleApplyChanges()
}

const openWatermarkImagePicker = () => {
  ensureWatermarkDraft()
  watermarkImageInputRef.value?.click()
}

const onWatermarkImageInput = async (e) => {
  const input = e.target
  const file = input.files && input.files[0]
  if (!file || !file.type.startsWith('image/')) {
    input.value = ''
    return
  }
  try {
    ensureWatermarkDraft()
    const reader = new FileReader()
    const raw = await new Promise((resolve, reject) => {
      reader.onload = () => resolve(reader.result)
      reader.onerror = () => reject(new Error('Leitura'))
      reader.readAsDataURL(file)
    })
    watermarkDraft.value.type = 'image'
    watermarkDraft.value.src = await shrinkDataUrlForOverlay(String(raw), 1200)
  } catch (err) {
    console.error(err)
  } finally {
    input.value = ''
  }
}

const getControlMin = (control) => {
  return control === 'blur' || control === 'pixelate' ? 0 : -100
}

const getControlMax = (control) => {
  return 100
}

const getControlValue = (control) => {
  const values = {
    brightness: brightness,
    contrast: contrast,
    saturation: saturation,
    gamma: gamma,
    blur: blur,
    pixelate: pixelate,
    sharpen: sharpen
  }
  return values[control] || ref(0)
}

const updateControlValue = (value) => {
  const control = getControlValue(activeControl.value)
  control.value = parseInt(value)
  if (['brightness', 'contrast', 'saturation', 'gamma', 'sharpen'].includes(activeControl.value)) {
    activeFilterPreset.value = null
  }
  scheduleApplyChanges()
}

const onControlSliderChange = () => {
  void flushPreview().then(() => {
    nextTick(() => recordEditHistory())
  })
}

const applyFilterPreset = (id) => {
  const preset = FILTER_PRESETS[id]
  if (!preset) {
    return
  }
  closeDrawingMenu()
  commitPendingEffectEdits()
  activeFilterPreset.value = id === 'neutral' ? null : id
  brightness.value = preset.brightness
  contrast.value = preset.contrast
  saturation.value = preset.saturation
  gamma.value = preset.gamma
  gammaFine.value = preset.gammaFine
  sharpen.value = preset.sharpen ?? 0
  flushPreview()
}

const toggleFilterMenu = () => {
  showFilterMenu.value = !showFilterMenu.value
  if (showFilterMenu.value) {
    commitPendingEffectEdits()
    showBlurMenu.value = false
    showPixelateMenu.value = false
    showDrawingMenu.value = false
    drawingTool.value = null
  }
}

const applyGammaPreset = (name) => {
  const presets = {
    neutral: [0, 0],
    lift: [-35, 18],
    mute: [25, -12],
    punch: [15, -25],
    decode: [12, 8]
  }
  const pair = presets[name]
  if (!pair) {
    return
  }
  commitPendingEffectEdits()
  gamma.value = pair[0]
  gammaFine.value = pair[1]
  activeFilterPreset.value = null
  flushPreview()
}

/** Converte retângulo em coordenadas do elemento img para pixels da imagem real (object-fit: contain). */
const displayRectToNatural = (x, y, w, h) => {
  const el = imageRef.value
  if (!el || !el.naturalWidth) {
    return { x: 0, y: 0, width: 0, height: 0 }
  }
  const nw = el.naturalWidth
  const nh = el.naturalHeight
  const rw = el.clientWidth
  const rh = el.clientHeight
  const scale = Math.min(rw / nw, rh / nh)
  const dw = nw * scale
  const dh = nh * scale
  const ox = (rw - dw) / 2
  const oy = (rh - dh) / 2
  let x1 = Math.round((x - ox) / scale)
  let y1 = Math.round((y - oy) / scale)
  let x2 = Math.round((x + w - ox) / scale)
  let y2 = Math.round((y + h - oy) / scale)
  x1 = Math.max(0, Math.min(nw, x1))
  y1 = Math.max(0, Math.min(nh, y1))
  x2 = Math.max(0, Math.min(nw, x2))
  y2 = Math.max(0, Math.min(nh, y2))
  const rx = Math.min(x1, x2)
  const ry = Math.min(y1, y2)
  return {
    x: rx,
    y: ry,
    width: Math.max(1, Math.abs(x2 - x1)),
    height: Math.max(1, Math.abs(y2 - y1))
  }
}

const displayPointToNatural = (px, py) => {
  const r = displayRectToNatural(px, py, 1, 1)
  return { x: r.x, y: r.y }
}

const committedRegionToDisplayRect = (natural) => {
  if (!natural?.width || !natural?.height) {
    return null
  }
  const d = naturalRectToDisplay(natural.x, natural.y, natural.width, natural.height)
  if (!d.width || !d.height) {
    return null
  }
  return {
    x: d.left,
    y: d.top,
    width: Math.max(1, d.width),
    height: Math.max(1, d.height)
  }
}

const initDefaultEffectRectangle = (startRef, sizeRef) => {
  const { ox, oy, drawnW, drawnH } = getImageOnScreenBounds()
  const frac = 0.34
  const maxBw = Math.max(32, drawnW - 6)
  const maxBh = Math.max(32, drawnH - 6)
  const bw = Math.min(Math.max(48, Math.floor(drawnW * frac)), maxBw)
  const bh = Math.min(Math.max(48, Math.floor(drawnH * frac)), maxBh)
  startRef.value = {
    x: ox + (drawnW - bw) / 2,
    y: oy + (drawnH - bh) / 2
  }
  sizeRef.value = { width: bw, height: bh }
}

const captureBlurRegionFromDisplay = () => {
  if (blurSize.value.width < 1 || blurSize.value.height < 1) {
    return null
  }
  const natural = displayRectToNatural(
    blurStart.value.x,
    blurStart.value.y,
    blurSize.value.width,
    blurSize.value.height
  )
  return natural.width >= 1 && natural.height >= 1 ? natural : null
}

const capturePixelateRegionFromDisplay = () => {
  if (pixelateSize.value.width < 1 || pixelateSize.value.height < 1) {
    return null
  }
  const natural = displayRectToNatural(
    pixelateStart.value.x,
    pixelateStart.value.y,
    pixelateSize.value.width,
    pixelateSize.value.height
  )
  return natural.width >= 1 && natural.height >= 1 ? natural : null
}

const exitBlurRectangleUi = () => {
  stopBlurPan()
  showBlurRegion.value = false
  blurShapeMode.value = 'rectangle'
  blurStart.value = { x: 0, y: 0 }
  blurSize.value = { width: 0, height: 0 }
}

const exitPixelateRectangleUi = () => {
  stopPixelatePan()
  showPixelateRegion.value = false
  pixelateShapeMode.value = 'rectangle'
  pixelateStart.value = { x: 0, y: 0 }
  pixelateSize.value = { width: 0, height: 0 }
}

const openBlurRectangleEditor = () => {
  ensureBlurEffectStrength()
  closeDrawingMenu()
  cancelCropPanPreviewRaf()
  stopCropPan()
  showCrop.value = false
  prepareSwitchFromPixelateTool()
  committedBlurMask.value = null
  blurShapeMode.value = 'rectangle'
  blurApplyGlobal.value = false
  showBlurRegion.value = true
  activeControl.value = 'blur'
  const disp = committedBlurRegion.value
    ? committedRegionToDisplayRect(committedBlurRegion.value)
    : null
  if (disp) {
    blurStart.value = { x: disp.x, y: disp.y }
    blurSize.value = { width: disp.width, height: disp.height }
  } else if (imageRef.value) {
    initDefaultEffectRectangle(blurStart, blurSize)
  }
}

const openPixelateRectangleEditor = () => {
  ensurePixelateEffectStrength()
  closeDrawingMenu()
  cancelCropPanPreviewRaf()
  stopCropPan()
  showCrop.value = false
  prepareSwitchFromBlurTool()
  committedPixelateMask.value = null
  pixelateShapeMode.value = 'rectangle'
  pixelateApplyGlobal.value = false
  showPixelateRegion.value = true
  activeControl.value = 'pixelate'
  const disp = committedPixelateRegion.value
    ? committedRegionToDisplayRect(committedPixelateRegion.value)
    : null
  if (disp) {
    pixelateStart.value = { x: disp.x, y: disp.y }
    pixelateSize.value = { width: disp.width, height: disp.height }
  } else if (imageRef.value) {
    initDefaultEffectRectangle(pixelateStart, pixelateSize)
  }
}

const hasActiveBlurTarget = () =>
  Boolean(
    blurApplyGlobal.value ||
    committedBlurRegion.value ||
    committedBlurMask.value ||
    (showBlurRegion.value &&
      blurShapeMode.value === 'brush' &&
      blurMaskDirty.value) ||
    (showBlurRegion.value && blurShapeMode.value === 'rectangle')
  )

const hasActivePixelateTarget = () =>
  Boolean(
    pixelateApplyGlobal.value ||
    committedPixelateRegion.value ||
    committedPixelateMask.value ||
    (showPixelateRegion.value &&
      pixelateShapeMode.value === 'brush' &&
      pixelateMaskDirty.value) ||
    (showPixelateRegion.value && pixelateShapeMode.value === 'rectangle')
  )

const resolveBlurMaskPayload = () => {
  if (resolveActiveBlurLevel() <= 0) {
    return null
  }
  if (
    showBlurRegion.value &&
    blurShapeMode.value === 'brush' &&
    blurMaskDirty.value
  ) {
    return exportBlurMaskDataUrl()
  }
  return committedBlurMask.value
}

const resolvePixelateMaskPayload = () => {
  if (resolveActivePixelateLevel() <= 0) {
    return null
  }
  if (
    showPixelateRegion.value &&
    pixelateShapeMode.value === 'brush' &&
    pixelateMaskDirty.value
  ) {
    return exportPixelateMaskDataUrl()
  }
  return committedPixelateMask.value
}

const resolveActiveBlurLevel = () =>
  blur.value > 0 && hasActiveBlurTarget() ? blur.value : 0

const resolveActivePixelateLevel = () =>
  pixelate.value > 0 && hasActivePixelateTarget() ? pixelate.value : 0

const resolveBlurRegionPayload = () => {
  if (resolveActiveBlurLevel() <= 0 || blurApplyGlobal.value) {
    return null
  }
  if (showBlurRegion.value && blurShapeMode.value === 'rectangle') {
    return captureBlurRegionFromDisplay()
  }
  return committedBlurRegion.value ? { ...committedBlurRegion.value } : null
}

const resolvePixelateRegionPayload = () => {
  if (resolveActivePixelateLevel() <= 0 || pixelateApplyGlobal.value) {
    return null
  }
  if (showPixelateRegion.value && pixelateShapeMode.value === 'rectangle') {
    return capturePixelateRegionFromDisplay()
  }
  return committedPixelateRegion.value ? { ...committedPixelateRegion.value } : null
}

const refreshCropReferenceSizeFromImage = () => {
  const el = imageRef.value
  if (!el?.naturalWidth || !el?.naturalHeight) {
    return false
  }
  cropReferenceSize.value = { width: el.naturalWidth, height: el.naturalHeight }
  return true
}

const syncCropDisplayFromNatural = () => {
  const el = imageRef.value
  if (!el?.naturalWidth || !el?.naturalHeight) {
    return
  }
  const cn = cropNatural.value
  const refW = cropReferenceSize.value.width
  const refH = cropReferenceSize.value.height
  if (!cn.width || !refW || !refH) {
    return
  }
  const { ox, oy, drawnW, drawnH } = getImageOnScreenBounds()
  if (drawnW < 1 || drawnH < 1) {
    return
  }
  cropStart.value = {
    x: ox + (cn.x / refW) * drawnW,
    y: oy + (cn.y / refH) * drawnH
  }
  cropSize.value = {
    width: Math.max(1, (cn.width / refW) * drawnW),
    height: Math.max(1, (cn.height / refH) * drawnH)
  }
}

const initCropNaturalFull = () => {
  const r = cropReferenceSize.value
  if (!r.width || !r.height) {
    return
  }
  cropNatural.value = { x: 0, y: 0, width: r.width, height: r.height }
}

/** Dimensões da imagem de referência para o crop (sempre a imagem actual no ecrã). */
const getCropReferenceDimensions = () => {
  const el = imageRef.value
  if (el?.naturalWidth && el?.naturalHeight) {
    return { width: el.naturalWidth, height: el.naturalHeight }
  }
  return cropReferenceSize.value
}

const isFullFrameCrop = (cn, rw, rh) => {
  if (!cn || cn.width < 1 || cn.height < 1) {
    return true
  }
  if (!rw || !rh) {
    return false
  }
  const marginX = Math.max(2, Math.round(rw * 0.002))
  const marginY = Math.max(2, Math.round(rh * 0.002))
  return (
    cn.x <= marginX &&
    cn.y <= marginY &&
    cn.width >= rw - marginX * 2 &&
    cn.height >= rh - marginY * 2
  )
}

/** Lê o retângulo branco visível e devolve coords naturais para o servidor. */
const captureCropFromDisplay = () => {
  if (cropSize.value.width < 1 || cropSize.value.height < 1) {
    return null
  }
  const el = imageRef.value
  if (!el?.naturalWidth || !el?.naturalHeight) {
    return null
  }
  const cn = displayRectToNatural(
    cropStart.value.x,
    cropStart.value.y,
    cropSize.value.width,
    cropSize.value.height
  )
  const rw = el.naturalWidth
  const rh = el.naturalHeight
  cropReferenceSize.value = { width: rw, height: rh }
  if (isFullFrameCrop(cn, rw, rh)) {
    return null
  }
  return {
    x: Math.max(0, Math.min(rw - 1, cn.x)),
    y: Math.max(0, Math.min(rh - 1, cn.y)),
    width: Math.max(1, Math.min(rw - cn.x, cn.width)),
    height: Math.max(1, Math.min(rh - cn.y, cn.height))
  }
}

/** Ponto no elemento img → px na imagem de referência (pré-crop). */
const displayPointToCropReference = (px, py) => {
  const refW = cropReferenceSize.value.width
  const refH = cropReferenceSize.value.height
  const { ox, oy, drawnW, drawnH } = getImageOnScreenBounds()
  if (!refW || !refH || drawnW < 1 || drawnH < 1) {
    return { x: 0, y: 0 }
  }
  const u = Math.max(0, Math.min(1, (px - ox) / drawnW))
  const v = Math.max(0, Math.min(1, (py - oy) / drawnH))
  return {
    x: Math.round(u * refW),
    y: Math.round(v * refH)
  }
}

const getCropNaturalPayload = () => {
  if (showCrop.value && cropSize.value.width > 1 && cropSize.value.height > 1) {
    return captureCropFromDisplay()
  }
  if (committedCrop.value) {
    return committedCrop.value
  }
  const cn = cropNatural.value
  const { width: rw, height: rh } = getCropReferenceDimensions()
  if (!rw || !rh || cn.width < 1 || cn.height < 1) {
    return null
  }
  if (isFullFrameCrop(cn, rw, rh)) {
    return null
  }
  const x = Math.max(0, Math.min(rw - 1, Math.round(cn.x)))
  const y = Math.max(0, Math.min(rh - 1, Math.round(cn.y)))
  const width = Math.max(1, Math.min(rw - x, Math.round(cn.width)))
  const height = Math.max(1, Math.min(rh - y, Math.round(cn.height)))
  return { x, y, width, height }
}

const rotateCropNatural90Cw = () => {
  const refW = cropReferenceSize.value.width
  const refH = cropReferenceSize.value.height
  const c = cropNatural.value
  if (!refW || !refH || !c.width) {
    return
  }
  cropReferenceSize.value = { width: refH, height: refW }
  cropNatural.value = {
    x: c.y,
    y: Math.max(0, refW - c.x - c.width),
    width: c.height,
    height: c.width
  }
}

const applyCropResizeAt = (sx, sy) => {
  const refW = cropReferenceSize.value.width
  const refH = cropReferenceSize.value.height
  if (!refW || !refH || !resizeDirection.value) {
    return
  }
  const cn = cropNatural.value
  const minSize = 48
  const p = displayPointToCropReference(sx, sy)
  const left = cn.x
  const top = cn.y
  const right = cn.x + cn.width
  const bottom = cn.y + cn.height

  switch (resizeDirection.value) {
    case 'nw': {
      const nx = Math.max(0, Math.min(right - minSize, p.x))
      const ny = Math.max(0, Math.min(bottom - minSize, p.y))
      cropNatural.value = { x: nx, y: ny, width: right - nx, height: bottom - ny }
      break
    }
    case 'ne': {
      const nx = Math.max(left + minSize, Math.min(refW, p.x))
      const ny = Math.max(0, Math.min(bottom - minSize, p.y))
      cropNatural.value = { x: left, y: ny, width: nx - left, height: bottom - ny }
      break
    }
    case 'sw': {
      const nx = Math.max(0, Math.min(right - minSize, p.x))
      const ny = Math.max(top + minSize, Math.min(refH, p.y))
      cropNatural.value = { x: nx, y: top, width: right - nx, height: ny - top }
      break
    }
    case 'se': {
      const nx = Math.max(left + minSize, Math.min(refW, p.x))
      const ny = Math.max(top + minSize, Math.min(refH, p.y))
      cropNatural.value = { x: left, y: top, width: nx - left, height: ny - top }
      break
    }
    case 'w': {
      const nx = Math.max(0, Math.min(right - minSize, p.x))
      cropNatural.value = { x: nx, y: top, width: right - nx, height: cn.height }
      break
    }
    case 'e': {
      const nx = Math.max(left + minSize, Math.min(refW, p.x))
      cropNatural.value = { x: left, y: top, width: nx - left, height: cn.height }
      break
    }
    case 'n': {
      const ny = Math.max(0, Math.min(bottom - minSize, p.y))
      cropNatural.value = { x: left, y: ny, width: cn.width, height: bottom - ny }
      break
    }
    case 's': {
      const ny = Math.max(top + minSize, Math.min(refH, p.y))
      cropNatural.value = { x: left, y: top, width: cn.width, height: ny - top }
      break
    }
  }
  enforceCropAspectAfterResize()
  syncCropDisplayFromNatural()
}

const cropAspectRatioValue = computed(() => {
  const map = { '1:1': 1, '4:3': 4 / 3, '16:9': 16 / 9 }
  return map[cropAspectPreset.value] ?? null
})

const enforceCropAspectAfterResize = () => {
  const ratio = cropAspectRatioValue.value
  if (!ratio || !resizeDirection.value) {
    return
  }
  const refW = cropReferenceSize.value.width
  const refH = cropReferenceSize.value.height
  const minSize = 48
  const prev = { ...cropNatural.value }
  let { x, y, width, height } = prev

  if (width / height > ratio) {
    width = Math.round(height * ratio)
  } else {
    height = Math.round(width / ratio)
  }
  width = Math.max(minSize, Math.min(refW, width))
  height = Math.max(minSize, Math.min(refH, height))

  const dir = resizeDirection.value
  const fixRight = dir === 'nw' || dir === 'w' || dir === 'sw'
  const fixBottom = dir === 'nw' || dir === 'n' || dir === 'ne'

  if (fixRight) {
    x = prev.x + prev.width - width
  }
  if (fixBottom) {
    y = prev.y + prev.height - height
  }

  x = Math.max(0, Math.min(refW - width, x))
  y = Math.max(0, Math.min(refH - height, y))
  width = Math.min(width, refW - x)
  height = Math.min(height, refH - y)

  cropNatural.value = { x, y, width, height }
}

const fitCropToAspectFromCenter = () => {
  const ratio = cropAspectRatioValue.value
  if (!ratio) {
    return
  }
  const refW = cropReferenceSize.value.width
  const refH = cropReferenceSize.value.height
  if (!refW || !refH || !cropNatural.value.width) {
    return
  }
  let width = cropNatural.value.width
  let height = cropNatural.value.height
  if (width / height > ratio) {
    width = Math.round(height * ratio)
  } else {
    height = Math.round(width / ratio)
  }
  width = Math.min(width, refW)
  height = Math.min(height, refH)
  const x = Math.max(
    0,
    Math.min(refW - width, cropNatural.value.x + (cropNatural.value.width - width) / 2)
  )
  const y = Math.max(
    0,
    Math.min(refH - height, cropNatural.value.y + (cropNatural.value.height - height) / 2)
  )
  cropNatural.value = { x: Math.round(x), y: Math.round(y), width, height }
  syncCropDisplayFromNatural()
}

const setCropAspectPreset = (id) => {
  cropAspectPreset.value = id
  if (showCrop.value && cropNatural.value.width > 0) {
    fitCropToAspectFromCenter()
  }
}

/** Retângulo em coords naturais → estilo em px relativos ao elemento img (object-fit: contain). */
const displayStrokeScale = computed(() => {
  const el = imageRef.value
  if (!el?.naturalWidth || !el.naturalHeight) {
    return 1
  }
  return Math.min(el.clientWidth / el.naturalWidth, el.clientHeight / el.naturalHeight)
})

const naturalPointToDisplay = (nx, ny) => {
  void imageNaturalVersion.value
  const d = naturalRectToDisplay(nx, ny, 0, 0)
  return { x: d.left, y: d.top }
}

const drawingFillDisplay = (d) => {
  const fill = d.fillColor
  if (!fill || fill === 'transparent') {
    return { fill: 'none', fillOpacity: 0 }
  }
  return { fill, fillOpacity: 1 }
}

const expandNaturalBounds = (minX, minY, maxX, maxY, pad) => ({
  x: minX - pad,
  y: minY - pad,
  width: Math.max(1, maxX - minX + pad * 2),
  height: Math.max(1, maxY - minY + pad * 2)
})

const getDrawingNaturalBounds = (d) => {
  const pad = Math.max(6, (d.strokeWidth || 2) * 3)
  const t = d.type
  if (t === 'line' || t === 'arrow') {
    return expandNaturalBounds(
      Math.min(d.x1, d.x2),
      Math.min(d.y1, d.y2),
      Math.max(d.x1, d.x2),
      Math.max(d.y1, d.y2),
      pad
    )
  }
  if (t === 'rectangle') {
    return expandNaturalBounds(d.x, d.y, d.x + d.width, d.y + d.height, pad)
  }
  if (t === 'ellipse') {
    return expandNaturalBounds(
      d.cx - d.width / 2,
      d.cy - d.height / 2,
      d.cx + d.width / 2,
      d.cy + d.height / 2,
      pad
    )
  }
  if (t === 'circle') {
    return expandNaturalBounds(
      d.cx - d.diameter / 2,
      d.cy - d.diameter / 2,
      d.cx + d.diameter / 2,
      d.cy + d.diameter / 2,
      pad
    )
  }
  if (
    (t === 'pen' || t === 'polygon' || t === 'bezier') &&
    Array.isArray(d.points) &&
    d.points.length > 0
  ) {
    let minX = d.points[0].x
    let minY = d.points[0].y
    let maxX = minX
    let maxY = minY
    for (const p of d.points) {
      minX = Math.min(minX, p.x)
      minY = Math.min(minY, p.y)
      maxX = Math.max(maxX, p.x)
      maxY = Math.max(maxY, p.y)
    }
    return expandNaturalBounds(minX, minY, maxX, maxY, pad)
  }
  if (t === 'pixel' || t === 'fill') {
    return expandNaturalBounds(d.x - pad, d.y - pad, d.x + pad, d.y + pad, pad)
  }
  return { x: 0, y: 0, width: 1, height: 1 }
}

const translateDrawingClone = (d, dx, dy) => {
  const c = cloneJson(d)
  const t = c.type
  if (t === 'line' || t === 'arrow') {
    c.x1 += dx
    c.y1 += dy
    c.x2 += dx
    c.y2 += dy
  } else if (t === 'rectangle') {
    c.x += dx
    c.y += dy
  } else if (t === 'ellipse' || t === 'circle') {
    c.cx += dx
    c.cy += dy
  } else if (
    (t === 'pen' || t === 'polygon' || t === 'bezier') &&
    Array.isArray(c.points)
  ) {
    c.points = c.points.map((p) => ({ x: p.x + dx, y: p.y + dy }))
  } else if (t === 'pixel' || t === 'fill') {
    c.x += dx
    c.y += dy
  }
  return c
}

const drawingHitBoxes = computed(() => {
  void imageNaturalVersion.value
  if (!canMoveDrawings.value) {
    return []
  }
  const el = imageRef.value
  const minPx = 28
  return drawings.value.map((d, index) => {
    const b = getDrawingNaturalBounds(d)
    const disp = naturalRectToDisplay(b.x, b.y, b.width, b.height)
    let w = Math.max(minPx, disp.width)
    let h = Math.max(minPx, disp.height)
    let left = disp.left - (w - disp.width) / 2
    let top = disp.top - (h - disp.height) / 2
    if (el?.naturalWidth) {
      const scale = displayStrokeScale.value
      const ox = (el.clientWidth - el.naturalWidth * scale) / 2
      const oy = (el.clientHeight - el.naturalHeight * scale) / 2
      const maxW = el.naturalWidth * scale
      const maxH = el.naturalHeight * scale
      left = Math.max(ox, Math.min(ox + maxW - w, left))
      top = Math.max(oy, Math.min(oy + maxH - h, top))
    }
    return {
      index,
      style: {
        left: `${left}px`,
        top: `${top}px`,
        width: `${w}px`,
        height: `${h}px`
      }
    }
  })
})

const deselectDrawing = () => {
  selectedDrawingIndex.value = null
  movingDrawingIndex.value = null
  drawingMoveSnapshot.value = null
}

const deleteFocusedDrawing = () => {
  if (drawingTool.value || showDrawingMenu.value) {
    return false
  }
  const idx = selectedDrawingIndex.value
  if (idx === null || idx < 0 || idx >= drawings.value.length) {
    return false
  }
  stopDrawingMove()
  drawings.value.splice(idx, 1)
  selectedDrawingIndex.value = null
  recordEditHistory()
  return true
}

const stopDrawingMove = () => {
  window.removeEventListener('mousemove', onDrawingWindowMove)
  window.removeEventListener('mouseup', stopDrawingMove)
  window.removeEventListener('touchmove', onDrawingWindowMove)
  window.removeEventListener('touchend', stopDrawingMove)
  const wasMoving = movingDrawingIndex.value !== null
  if (wasMoving) {
    recordEditHistory()
  }
  movingDrawingIndex.value = null
  drawingMoveSnapshot.value = null
}

const onDrawingWindowMove = (e) => {
  if (movingDrawingIndex.value === null || !drawingMoveSnapshot.value) {
    return
  }
  if (e.type === 'touchmove' && e.cancelable) {
    e.preventDefault()
  }
  const p = clientToImgLocal(e)
  const n = displayPointToNatural(p.x, p.y)
  const dx = n.x - drawingMoveStartNat.value.x
  const dy = n.y - drawingMoveStartNat.value.y
  const idx = movingDrawingIndex.value
  drawings.value[idx] = translateDrawingClone(drawingMoveSnapshot.value, dx, dy)
}

const startDrawingMove = (e, index) => {
  if (!canMoveDrawings.value || !drawings.value[index]) {
    return
  }
  stopOverlayMove()
  stopOverlayResize()
  stopDrawingMove()
  e.preventDefault()
  e.stopPropagation()
  selectedDrawingIndex.value = index
  drawingMoveSnapshot.value = cloneJson(drawings.value[index])
  const p = clientToImgLocal(e)
  drawingMoveStartNat.value = displayPointToNatural(p.x, p.y)
  movingDrawingIndex.value = index
  window.addEventListener('mousemove', onDrawingWindowMove)
  window.addEventListener('mouseup', stopDrawingMove)
  window.addEventListener('touchmove', onDrawingWindowMove, { passive: false })
  window.addEventListener('touchend', stopDrawingMove)
}

const buildDrawingOverlayShapes = (list, mapPoint, strokeScale) => {
  const shapes = []
  for (const d of list) {
    if (!d || !d.type) {
      continue
    }
    const stroke = d.strokeColor || '#000000'
    const strokeWidth = Math.max(1, (d.strokeWidth || 2) * strokeScale)
    const fillProps = drawingFillDisplay(d)
    const t = d.type
    if (t === 'line' || t === 'arrow') {
      const p0 = mapPoint(d.x1, d.y1)
      const p1 = mapPoint(d.x2, d.y2)
      if (t === 'line') {
        shapes.push({ kind: 'line', x1: p0.x, y1: p0.y, x2: p1.x, y2: p1.y, stroke, strokeWidth })
        continue
      }
      const head = arrowHeadLayout(p0.x, p0.y, p1.x, p1.y, strokeWidth / strokeScale)
      if (!head) {
        shapes.push({ kind: 'line', x1: p0.x, y1: p0.y, x2: p1.x, y2: p1.y, stroke, strokeWidth })
      } else {
        shapes.push({
          kind: 'arrow',
          x1: p0.x,
          y1: p0.y,
          shaftX: head.shaftX,
          shaftY: head.shaftY,
          headPoints: head.headPoints,
          stroke,
          strokeWidth
        })
      }
      continue
    }
    if (t === 'rectangle') {
      const r = mapPoint(d.x, d.y)
      const r2 = mapPoint(d.x + d.width, d.y + d.height)
      const rect = {
        left: Math.min(r.x, r2.x),
        top: Math.min(r.y, r2.y),
        width: Math.abs(r2.x - r.x),
        height: Math.abs(r2.y - r.y)
      }
      shapes.push({
        kind: 'rectangle',
        x: rect.left,
        y: rect.top,
        width: rect.width,
        height: rect.height,
        stroke,
        strokeWidth,
        ...fillProps
      })
      continue
    }
    if (t === 'ellipse') {
      const p0 = mapPoint(d.cx - d.width / 2, d.cy - d.height / 2)
      const p1 = mapPoint(d.cx + d.width / 2, d.cy + d.height / 2)
      shapes.push({
        kind: 'ellipse',
        cx: (p0.x + p1.x) / 2,
        cy: (p0.y + p1.y) / 2,
        rx: Math.max(1, Math.abs(p1.x - p0.x) / 2),
        ry: Math.max(1, Math.abs(p1.y - p0.y) / 2),
        stroke,
        strokeWidth,
        ...fillProps
      })
      continue
    }
    if (t === 'circle') {
      const p0 = mapPoint(d.cx - d.diameter / 2, d.cy - d.diameter / 2)
      const p1 = mapPoint(d.cx + d.diameter / 2, d.cy + d.diameter / 2)
      shapes.push({
        kind: 'circle',
        cx: (p0.x + p1.x) / 2,
        cy: (p0.y + p1.y) / 2,
        r: Math.max(1, Math.min(Math.abs(p1.x - p0.x), Math.abs(p1.y - p0.y)) / 2),
        stroke,
        strokeWidth,
        ...fillProps
      })
      continue
    }
    if (t === 'pen' && Array.isArray(d.points) && d.points.length > 1) {
      shapes.push({
        kind: 'pen',
        points: d.points.map((p) => {
          const pt = mapPoint(p.x, p.y)
          return `${pt.x},${pt.y}`
        }).join(' '),
        stroke,
        strokeWidth
      })
      continue
    }
    if (t === 'polygon' && Array.isArray(d.points) && d.points.length > 1) {
      shapes.push({
        kind: 'polygon',
        points: d.points.map((p) => {
          const pt = mapPoint(p.x, p.y)
          return `${pt.x},${pt.y}`
        }).join(' '),
        stroke,
        strokeWidth
      })
      continue
    }
    if (t === 'bezier' && Array.isArray(d.points) && d.points.length >= 4) {
      const pts = d.points.map((p) => mapPoint(p.x, p.y))
      shapes.push({
        kind: 'bezier',
        d: `M ${pts[0].x} ${pts[0].y} C ${pts[1].x} ${pts[1].y} ${pts[2].x} ${pts[2].y} ${pts[3].x} ${pts[3].y}`,
        stroke,
        strokeWidth
      })
      continue
    }
    if (t === 'pixel' || t === 'fill') {
      const p = mapPoint(d.x, d.y)
      shapes.push({ kind: 'pixel', cx: p.x, cy: p.y, stroke })
    }
  }
  return shapes
}

const drawingOverlayShapes = computed(() => {
  void imageNaturalVersion.value
  if (!showDrawingsOverlay.value) {
    return []
  }
  return buildDrawingOverlayShapes(
    drawings.value,
    naturalPointToDisplay,
    displayStrokeScale.value
  )
})

const layoutDrawingOverlayShapes = computed(() => {
  zoomLayout.value
  if (!showLayoutDrawingsOverlay.value) {
    return []
  }
  return buildDrawingOverlayShapes(
    layoutDrawings.value,
    (x, y) => ({ x, y }),
    1
  )
})

const naturalRectToDisplay = (nx, ny, nw, nh) => {
  const el = imageRef.value
  if (!el || !el.naturalWidth) {
    return { left: 0, top: 0, width: 0, height: 0 }
  }
  const scale = Math.min(el.clientWidth / el.naturalWidth, el.clientHeight / el.naturalHeight)
  const ox = (el.clientWidth - el.naturalWidth * scale) / 2
  const oy = (el.clientHeight - el.naturalHeight * scale) / 2
  return {
    left: nx * scale + ox,
    top: ny * scale + oy,
    width: nw * scale,
    height: nh * scale
  }
}

/** Textos guardam x, y, size em pixels da imagem natural (como o backend); isto projecta para o overlay no <img>. */
const naturalTextToDisplayLayout = (text) => {
  void imageNaturalVersion.value
  const el = imageRef.value
  if (!el?.naturalWidth || !el.naturalHeight) {
    return { left: text.x, top: text.y, fontSize: text.size }
  }
  const pos = naturalRectToDisplay(text.x, text.y, 0, 0)
  const scale = Math.min(el.clientWidth / el.naturalWidth, el.clientHeight / el.naturalHeight)
  return {
    left: Math.round(pos.left),
    top: Math.round(pos.top),
    fontSize: Math.max(1, text.size * scale)
  }
}


const buildTextItemFromPanel = (content, x, y) => ({
  content,
  x,
  y,
  size: displayTextSizeToNatural(textSize.value),
  color: textColor.value,
  bold: textBold.value,
  angle: textAngle.value,
  align: textAlign.value,
  stroke_width: textStrokeEnabled.value
    ? Math.max(1, displayTextSizeToNatural(textStrokeWidth.value))
    : 0,
  stroke_color: textStrokeEnabled.value ? textStrokeColor.value : null,
  background_color: textBgEnabled.value ? textBgColor.value : null,
  background_opacity: textBgEnabled.value ? textBgOpacity.value : null,
  background_padding: textBgEnabled.value ? displayTextPaddingToNatural(textBgPadding.value) : 0
})

const loadTextSettingsFromItem = (t) => {
  textContent.value = t.content ?? ''
  textSize.value = naturalTextSizeToDisplay(t.size ?? 24)
  textColor.value = t.color ?? '#FFFFFF'
  textBold.value = !!t.bold
  textAngle.value = t.angle ?? 0
  textAlign.value = t.align || 'left'
  textStrokeEnabled.value = (t.stroke_width ?? 0) > 0
  textStrokeWidth.value =
    t.stroke_width > 0 ? naturalTextSizeToDisplay(t.stroke_width) : 2
  textStrokeColor.value = t.stroke_color || '#000000'
  textBgEnabled.value = Boolean(t.background_color)
  textBgColor.value = t.background_color || '#000000'
  textBgOpacity.value = t.background_opacity ?? 75
  textBgPadding.value = t.background_padding ? naturalTextPaddingToDisplay(t.background_padding) : 6
}

const syncSelectedTextFromPanel = () => {
  const i = selectedTextIndex.value
  if (i === null || !texts.value[i]) {
    return
  }
  texts.value[i] = buildTextItemFromPanel(textContent.value, texts.value[i].x, texts.value[i].y)
}

const onTextPanelInput = () => {
  syncSelectedTextFromPanel()
}

const selectText = (index) => {
  selectedTextIndex.value = index
  loadTextSettingsFromItem(texts.value[index])
}

const duplicateSelectedText = () => {
  const i = selectedTextIndex.value
  if (i === null || !texts.value[i]) {
    return
  }
  const src = texts.value[i]
  texts.value.push({
    ...src,
    x: src.x + Math.max(12, Math.round((src.size || 24) * 0.25)),
    y: src.y + Math.max(12, Math.round((src.size || 24) * 0.25))
  })
  selectedTextIndex.value = texts.value.length - 1
  loadTextSettingsFromItem(texts.value[selectedTextIndex.value])
  recordEditHistory()
}

const textItemOuterStyle = (text) => {
  const lay = naturalTextToDisplayLayout(text)
  return { left: `${lay.left}px`, top: `${lay.top}px` }
}

const hexToRgba = (hex, alpha) => {
  const normalized = String(hex || '#000000').replace('#', '')
  if (normalized.length !== 6) {
    return hex
  }
  const r = parseInt(normalized.slice(0, 2), 16)
  const g = parseInt(normalized.slice(2, 4), 16)
  const b = parseInt(normalized.slice(4, 6), 16)

  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

const textItemInnerStyle = (text) => {
  const lay = naturalTextToDisplayLayout(text)
  const style = {
    fontSize: `${lay.fontSize}px`,
    color: text.color,
    fontWeight: text.bold ? '700' : '400',
    textAlign: text.align || 'left',
    lineHeight: 1.25,
    fontFamily: 'system-ui, -apple-system, "Segoe UI", sans-serif'
  }
  const angle = text.angle ?? 0
  if (angle) {
    style.display = 'inline-block'
    style.transform = `rotate(${angle}deg)`
    style.transformOrigin = 'top left'
  }
  if (text.background_color) {
    const padNat = text.background_padding ?? 0
    const padDisp = padNat > 0 ? naturalTextPaddingToDisplay(padNat) : 0
    style.display = 'inline-block'
    style.backgroundColor = hexToRgba(text.background_color, (text.background_opacity ?? 75) / 100)
    style.padding = `${padDisp}px`
    style.borderRadius = `${Math.max(2, padDisp * 0.35)}px`
  }
  const sw = text.stroke_width ?? 0
  if (sw > 0 && text.stroke_color) {
    const px = Math.max(1, (sw * lay.fontSize) / 24)
    style.webkitTextStroke = `${px}px ${text.stroke_color}`
    style.paintOrder = 'stroke fill'
  }
  return style
}

/** Tamanho escolhido no slider (px no ecrã) → px de fonte na imagem natural. */
const displayTextSizeToNatural = (displayPx) => {
  void imageNaturalVersion.value
  const el = imageRef.value
  if (!el?.naturalWidth) {
    return Math.max(1, Math.round(displayPx))
  }
  const scale = Math.min(el.clientWidth / el.naturalWidth, el.clientHeight / el.naturalHeight)
  return Math.max(1, Math.round(displayPx / scale))
}

const displayTextPaddingToNatural = (displayPx) => displayTextSizeToNatural(displayPx)

const naturalTextPaddingToDisplay = (naturalPx) => naturalTextSizeToDisplay(naturalPx)

const naturalTextSizeToDisplay = (naturalPx) => {
  void imageNaturalVersion.value
  const el = imageRef.value
  if (!el?.naturalWidth) {
    return Math.max(12, Math.round(naturalPx))
  }
  const scale = Math.min(el.clientWidth / el.naturalWidth, el.clientHeight / el.naturalHeight)
  return Math.max(12, Math.round(naturalPx * scale))
}

const overlayBoxStyle = (ov) => {
  const d = naturalRectToDisplay(ov.x, ov.y, ov.width, ov.height)
  return {
    left: `${d.left}px`,
    top: `${d.top}px`,
    width: `${d.width}px`,
    height: `${d.height}px`
  }
}

const imageDisplayScale = () => {
  const el = imageRef.value
  if (!el?.naturalWidth || !el?.naturalHeight) {
    return 1
  }
  const rw = el.clientWidth
  const rh = el.clientHeight
  if (!rw || !rh) {
    return 1
  }
  return Math.min(rw / el.naturalWidth, rh / el.naturalHeight)
}

const formatCaptionText = (number, description) => {
  const prefix = (captionSettings.value.prefix || '').trim()
  const sep = captionSettings.value.separator || ' — '
  const numPart = prefix ? `${prefix} ${number}` : String(number)
  const desc = (description || '').trim()
  return desc ? `${numPart}${sep}${desc}` : numPart
}

const wrapCaptionTextToWidth = (text, fontSize, maxWidth) => {
  if (!text || maxWidth < 1) {
    return text || ''
  }
  const charWidth = Math.max(1, fontSize * 0.55)
  const maxChars = Math.max(1, Math.floor(maxWidth / charWidth))
  const out = []

  for (const paragraph of text.split(/\r\n|\r|\n/)) {
    const trimmed = paragraph.trim()
    if (!trimmed) {
      out.push('')
      continue
    }
    const words = trimmed.split(/\s+/).filter(Boolean)
    let current = ''
    for (const word of words) {
      const trial = current ? `${current} ${word}` : word
      if (trial.length <= maxChars) {
        current = trial
      } else {
        if (current) {
          out.push(current)
        }
        current = word
      }
    }
    if (current) {
      out.push(current)
    }
  }

  return out.join('\n')
}

const captionFontSizeNatural = () =>
  displayTextSizeToNatural(captionSettings.value.fontSize)

const captionBandPaddingNatural = () =>
  displayTextSizeToNatural(captionSettings.value.bandPadding)

const estimateCaptionBandHeightNat = (number, description, widthNat) => {
  if (!widthNat || widthNat < 2) {
    return 0
  }
  const fontSize = captionFontSizeNatural()
  const padding = captionBandPaddingNatural()
  const innerW = Math.max(1, widthNat - padding * 2)
  const content = formatCaptionText(number, description)
  const wrapped = wrapCaptionTextToWidth(content, fontSize, innerW)
  const lines = wrapped.split('\n').filter((l, i, a) => l !== '' || a.length > 1).length || 1
  const textH = lines * fontSize * 1.3
  return Math.max(Math.ceil(fontSize * 2.2), Math.ceil(textH + padding * 2))
}

const photoCaptionBandStyle = computed(() => {
  void captionSettings.value
  void imageNaturalVersion.value
  if (!photoCaptionApplied.value) {
    return { display: 'none' }
  }
  const el = imageRef.value
  if (!el?.naturalWidth || !el?.naturalHeight) {
    return { display: 'none' }
  }
  const bandNat = estimateCaptionBandHeightNat(
    photoCaptionApplied.value.number,
    photoCaptionApplied.value.description,
    el.naturalWidth
  )
  const d = naturalRectToDisplay(0, el.naturalHeight, el.naturalWidth, bandNat)
  return {
    left: `${d.left}px`,
    top: `${d.top}px`,
    width: `${d.width}px`,
    height: `${d.height}px`
  }
})

const photoCaptionTextStyle = computed(() => {
  void imageNaturalVersion.value
  return {
    fontSize: `${Math.max(9, captionSettings.value.fontSize)}px`,
    color: captionSettings.value.color,
    fontWeight: captionSettings.value.bold ? '700' : '400',
    lineHeight: 1.3
  }
})

const hasActiveCaptions = computed(() => photoCaptionApplied.value !== null)

const buildCaptionSettingsPayload = () => ({
  prefix: captionSettings.value.prefix || '',
  separator: captionSettings.value.separator || ' — ',
  font_size: Math.round(captionFontSizeNatural()),
  band_padding: Math.round(captionBandPaddingNatural()),
  color: captionSettings.value.color,
  bold: Boolean(captionSettings.value.bold)
})

const mapImageOverlaysPayload = () =>
  imageOverlays.value.map(({ src, x, y, width, height }) => ({
    src,
    x: Math.round(x),
    y: Math.round(y),
    width: Math.round(width),
    height: Math.round(height)
  }))

const captionPrefixPresetLabel = (preset) => {
  if (preset === '') {
    return '(só n.º)'
  }
  if (preset === '__custom__') {
    return 'Personalizado'
  }
  return preset
}

const isCaptionPrefixPresetActive = (preset) => {
  if (preset === '__custom__') {
    return showCustomCaptionPrefix.value || !captionStandardPrefixes.includes(captionSettings.value.prefix)
  }
  return !showCustomCaptionPrefix.value && captionSettings.value.prefix === preset
}

const setCaptionPrefix = (preset) => {
  if (preset === '__custom__') {
    showCustomCaptionPrefix.value = true
    if (captionStandardPrefixes.includes(captionSettings.value.prefix)) {
      captionSettings.value.prefix = ''
    }
    onCaptionSettingsChange()
    return
  }
  showCustomCaptionPrefix.value = false
  captionSettings.value.prefix = preset
  onCaptionSettingsChange()
}

const captionPreviewSample = computed(() => {
  const n = photoCaptionDraft.value?.number ?? photoCaptionApplied.value?.number ?? 1
  const desc =
    photoCaptionDraft.value?.description ??
    photoCaptionApplied.value?.description ??
    'Descrição da foto'
  return formatCaptionText(n, desc || 'Descrição da foto')
})

const onCaptionSettingsChange = () => {
  recordEditHistory()
}

const clonePhotoCaption = (cap) => JSON.parse(JSON.stringify(cap))

const openPhotoCaptionDraft = () => {
  photoCaptionDraft.value = photoCaptionApplied.value
    ? clonePhotoCaption(photoCaptionApplied.value)
    : createDefaultPhotoCaptionDraft()
}

const ensurePhotoCaptionDraft = () => {
  if (!photoCaptionDraft.value) {
    photoCaptionDraft.value = createDefaultPhotoCaptionDraft()
  }
}

const photoCaptionDraftCanApply = computed(() => {
  const n = Number(photoCaptionDraft.value?.number)
  return Number.isFinite(n) && n >= 1 && n <= 9999
})

const confirmPhotoCaption = () => {
  if (!photoCaptionDraftCanApply.value || !photoCaptionDraft.value) {
    return
  }
  photoCaptionApplied.value = {
    number: Math.max(1, Math.round(photoCaptionDraft.value.number)),
    description: photoCaptionDraft.value.description || ''
  }
  activeControl.value = null
  recordEditHistory()
}

const removePhotoCaption = () => {
  photoCaptionApplied.value = null
  photoCaptionDraft.value = createDefaultPhotoCaptionDraft()
  activeControl.value = null
  recordEditHistory()
}

const shrinkDataUrlForOverlay = (dataUrl, maxSide = 1200) => {
  return new Promise((resolve, reject) => {
    const img = new window.Image()
    img.onload = () => {
      let w = img.naturalWidth
      let h = img.naturalHeight
      if (!w || !h) {
        resolve(dataUrl)
        return
      }
      const r = Math.min(maxSide / w, maxSide / h, 1)
      w = Math.max(1, Math.round(w * r))
      h = Math.max(1, Math.round(h * r))
      const c = document.createElement('canvas')
      c.width = w
      c.height = h
      const ctx = c.getContext('2d')
      if (!ctx) {
        resolve(dataUrl)
        return
      }
      ctx.drawImage(img, 0, 0, w, h)
      resolve(c.toDataURL('image/jpeg', 0.88))
    }
    img.onerror = () => reject(new Error('Falha ao ler a imagem'))
    img.src = dataUrl
  })
}

const addImageOverlayFromDataUrl = (src) => {
  if (!isBlankCanvas.value) {
    return false
  }
  const el = imageRef.value
  if (!el || !el.naturalWidth || imageOverlays.value.length >= 20) {
    return
  }
  const img = new window.Image()
  img.onload = () => {
    if (imageOverlays.value.length >= 20) {
      return
    }
    const iw = el.naturalWidth
    const ih = el.naturalHeight
    const iw0 = img.naturalWidth
    const ih0 = img.naturalHeight
    if (!iw0 || !ih0) {
      return
    }
    const ar = iw0 / ih0
    let nw = Math.round(iw * 0.35)
    let nh = Math.round(nw / ar)
    if (nh > ih * 0.35) {
      nh = Math.round(ih * 0.35)
      nw = Math.round(nh * ar)
    }
    nw = Math.max(8, Math.min(nw, iw))
    nh = Math.max(8, Math.min(nh, ih))
    const stack = imageOverlays.value.length
    const nx = Math.min(
      iw - nw,
      Math.max(0, Math.round((iw - nw) / 2) + (stack % 5) * 28)
    )
    const ny = Math.min(
      ih - nh,
      Math.max(0, Math.round((ih - nh) / 2) + Math.floor(stack / 5) * 28)
    )
    imageOverlays.value.push({
      id: `ov-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
      src,
      x: nx,
      y: ny,
      width: nw,
      height: nh
    })
    selectedOverlayId.value = imageOverlays.value[imageOverlays.value.length - 1].id
    scheduleApplyChanges()
  }
  img.src = src
}

const addImageOverlayFromUrl = async (url) => {
  if (!isBlankCanvas.value) {
    return false
  }
  if (!url || typeof url !== 'string') {
    return false
  }
  try {
    const res = await fetch(url, { credentials: 'same-origin' })
    if (!res.ok) {
      throw new Error('Não foi possível carregar a imagem')
    }
    const blob = await res.blob()
    const raw = await new Promise((resolve, reject) => {
      const reader = new FileReader()
      reader.onload = () => resolve(reader.result)
      reader.onerror = () => reject(new Error('Leitura da imagem'))
      reader.readAsDataURL(blob)
    })
    const src = await shrinkDataUrlForOverlay(String(raw), 1200)
    addImageOverlayFromDataUrl(src)
    return true
  } catch (err) {
    console.error(err)
    emit('error', err?.message || 'Falha ao adicionar imagem à folha')
    return false
  }
}

const removeImageOverlay = (id) => {
  imageOverlays.value = imageOverlays.value.filter((o) => o.id !== id)
  if (selectedOverlayId.value === id) {
    selectedOverlayId.value = imageOverlays.value[0]?.id ?? null
  }
  scheduleApplyChanges()
}

const stopOverlayMove = () => {
  window.removeEventListener('mousemove', onOverlayWindowMove)
  window.removeEventListener('mouseup', stopOverlayMove)
  window.removeEventListener('touchmove', onOverlayWindowMove)
  window.removeEventListener('touchend', stopOverlayMove)
  if (movingOverlayId.value) {
    flushPreview()
  }
  movingOverlayId.value = null
}

const onOverlayWindowMove = (e) => {
  if (!movingOverlayId.value) {
    return
  }
  if (e.type === 'touchmove' && e.cancelable) {
    e.preventDefault()
  }
  const el = imageRef.value
  if (!el || !el.naturalWidth) {
    return
  }
  const ov = imageOverlays.value.find((o) => o.id === movingOverlayId.value)
  if (!ov) {
    return
  }
  const p = clientToImgLocal(e)
  const n = displayPointToNatural(p.x, p.y)
  let nx = n.x - overlayMoveGrabNat.value.x
  let ny = n.y - overlayMoveGrabNat.value.y
  nx = Math.max(0, Math.min(el.naturalWidth - ov.width, nx))
  ny = Math.max(0, Math.min(el.naturalHeight - ov.height, ny))
  ov.x = nx
  ov.y = ny
  scheduleApplyChanges()
}

const startOverlayMove = (e, id) => {
  if (drawingTool.value || showCrop.value || showBlurRegion.value || showPixelateRegion.value || zoomLayout.value) {
    return
  }
  stopOverlayMove()
  stopOverlayResize()
  e.preventDefault()
  e.stopPropagation()
  const ov = imageOverlays.value.find((o) => o.id === id)
  if (!ov) {
    return
  }
  selectedOverlayId.value = id
  const p = clientToImgLocal(e)
  const n = displayPointToNatural(p.x, p.y)
  overlayMoveGrabNat.value = { x: n.x - ov.x, y: n.y - ov.y }
  movingOverlayId.value = id
  window.addEventListener('mousemove', onOverlayWindowMove)
  window.addEventListener('mouseup', stopOverlayMove)
  window.addEventListener('touchmove', onOverlayWindowMove, { passive: false })
  window.addEventListener('touchend', stopOverlayMove)
}

const stopOverlayResize = () => {
  window.removeEventListener('mousemove', onOverlayResizeMove)
  window.removeEventListener('mouseup', stopOverlayResize)
  window.removeEventListener('touchmove', onOverlayResizeMove)
  window.removeEventListener('touchend', stopOverlayResize)
  if (resizingOverlayId.value) {
    flushPreview()
  }
  resizingOverlayId.value = null
  overlayResizeStart.value = null
}

const onOverlayResizeMove = (e) => {
  if (!resizingOverlayId.value || !overlayResizeStart.value) {
    return
  }
  if (e.type === 'touchmove' && e.cancelable) {
    e.preventDefault()
  }
  const el = imageRef.value
  if (!el || !el.naturalWidth) {
    return
  }
  const ov = imageOverlays.value.find((o) => o.id === resizingOverlayId.value)
  if (!ov) {
    return
  }
  const st = overlayResizeStart.value
  const p = clientToImgLocal(e)
  const n = displayPointToNatural(p.x, p.y)
  let nw = Math.max(8, st.w0 + (n.x - st.mx0))
  let nh = Math.max(8, st.h0 + (n.y - st.my0))
  nw = Math.min(nw, el.naturalWidth - ov.x)
  nh = Math.min(nh, el.naturalHeight - ov.y)
  ov.width = nw
  ov.height = nh
  scheduleApplyChanges()
}

const startOverlayResize = (e, id) => {
  if (drawingTool.value || showCrop.value || showBlurRegion.value || showPixelateRegion.value || zoomLayout.value) {
    return
  }
  stopOverlayMove()
  stopOverlayResize()
  e.preventDefault()
  e.stopPropagation()
  const ov = imageOverlays.value.find((o) => o.id === id)
  if (!ov) {
    return
  }
  const p = clientToImgLocal(e)
  const n = displayPointToNatural(p.x, p.y)
  overlayResizeStart.value = { mx0: n.x, my0: n.y, w0: ov.width, h0: ov.height }
  resizingOverlayId.value = id
  window.addEventListener('mousemove', onOverlayResizeMove)
  window.addEventListener('mouseup', stopOverlayResize)
  window.addEventListener('touchmove', onOverlayResizeMove, { passive: false })
  window.addEventListener('touchend', stopOverlayResize)
}

const zoomCalloutBoxStyle = (callout) => {
  const m = layoutBoardMetrics.value
  if (!m) {
    return {}
  }
  return {
    left: `${callout.x * m.scale}px`,
    top: `${callout.y * m.scale}px`,
    width: `${callout.width * m.scale}px`,
    height: `${callout.height * m.scale}px`
  }
}

const buildZoomLayoutPayload = () => {
  if (!zoomLayout.value || zoomCallouts.value.length === 0) {
    return null
  }
  return {
    canvas_width: zoomLayout.value.canvasWidth,
    canvas_height: zoomLayout.value.canvasHeight,
    background: '#ffffff',
    base: { ...zoomLayout.value.base },
    callouts: zoomCallouts.value.map(({ src, x, y, width, height }) => ({
      src,
      x: Math.round(x),
      y: Math.round(y),
      width: Math.round(width),
      height: Math.round(height)
    }))
  }
}

const ensureLayoutFitsCallouts = () => {
  const layout = zoomLayout.value
  if (!layout) {
    return
  }
  let maxBottom = layout.base.y + layout.base.height
  for (const c of zoomCallouts.value) {
    maxBottom = Math.max(maxBottom, c.y + c.height)
  }
  const needed = maxBottom + Math.round(layout.canvasWidth * 0.05)
  if (needed > layout.canvasHeight) {
    layout.canvasHeight = needed
  }
}

const ensureZoomLayout = (natW, natH) => {
  if (zoomLayout.value) {
    return zoomLayout.value
  }
  const pad = Math.max(16, Math.round(natH * 0.05))
  const baseScale = 0.52
  let baseW = Math.round(natW * baseScale)
  let baseH = Math.round(natH * baseScale)
  baseW = Math.max(32, Math.min(natW, baseW))
  baseH = Math.max(32, Math.min(natH, baseH))
  const baseX = Math.round((natW - baseW) / 2)
  const baseY = pad
  zoomLayout.value = {
    canvasWidth: natW,
    canvasHeight: Math.round(baseY + baseH + natH * 0.58),
    base: { x: baseX, y: baseY, width: baseW, height: baseH }
  }
  return zoomLayout.value
}

const extractRegionFromCurrentImage = (sourceRect) => {
  return new Promise((resolve, reject) => {
    const img = new window.Image()
    img.onload = () => {
      const { x, y, width, height } = sourceRect
      const c = document.createElement('canvas')
      c.width = width
      c.height = height
      const ctx = c.getContext('2d')
      if (!ctx) {
        reject(new Error('Canvas'))
        return
      }
      ctx.drawImage(img, x, y, width, height, 0, 0, width, height)
      resolve(c.toDataURL('image/jpeg', 0.9))
    }
    img.onerror = () => reject(new Error('Imagem'))
    img.src = currentImageUrl.value
  })
}

const layoutPointToSourceNatural = (lx, ly) => {
  const layout = zoomLayout.value
  const el = imageRef.value
  if (!layout || !el?.naturalWidth) {
    return { x: 0, y: 0 }
  }
  const b = layout.base
  const u = Math.max(0, Math.min(1, (lx - b.x) / Math.max(1, b.width)))
  const v = Math.max(0, Math.min(1, (ly - b.y) / Math.max(1, b.height)))
  return {
    x: Math.round(u * el.naturalWidth),
    y: Math.round(v * el.naturalHeight)
  }
}

const sourceRectFromLayoutDrag = (drag) => {
  const left = Math.min(drag.x0, drag.x1)
  const top = Math.min(drag.y0, drag.y1)
  const right = Math.max(drag.x0, drag.x1)
  const bottom = Math.max(drag.y0, drag.y1)
  const p0 = layoutPointToSourceNatural(left, top)
  const p1 = layoutPointToSourceNatural(right, bottom)
  const el = imageRef.value
  const nw = el?.naturalWidth || 1
  const nh = el?.naturalHeight || 1
  return {
    x: Math.max(0, Math.min(p0.x, p1.x)),
    y: Math.max(0, Math.min(p0.y, p1.y)),
    width: Math.max(8, Math.min(nw, Math.abs(p1.x - p0.x) || 8)),
    height: Math.max(8, Math.min(nh, Math.abs(p1.y - p0.y) || 8))
  }
}

const clientToLayoutBoard = (e) => {
  const board = layoutBoardRef.value
  const m = layoutBoardMetrics.value
  if (!board || !m?.scale) {
    return { x: 0, y: 0 }
  }
  const rect = board.getBoundingClientRect()
  const cx = e.touches ? e.touches[0].clientX : e.clientX
  const cy = e.touches ? e.touches[0].clientY : e.clientY
  return { x: (cx - rect.left) / m.scale, y: (cy - rect.top) / m.scale }
}

const drawingPointerForEvent = (e) => {
  if (usesLayoutDrawingSpace.value) {
    const p = clientToLayoutBoard(e)
    return {
      draft: { x: p.x, y: p.y },
      stored: { x: Math.round(p.x), y: Math.round(p.y) }
    }
  }
  const p = clientToImgLocal(e)
  return {
    draft: { x: p.x, y: p.y },
    stored: displayPointToNatural(p.x, p.y)
  }
}

const draftPointToStored = (pt) => {
  if (usesLayoutDrawingSpace.value) {
    return { x: Math.round(pt.x), y: Math.round(pt.y) }
  }
  return displayPointToNatural(pt.x, pt.y)
}

const draftRectToStored = (left, top, w, h) => {
  if (usesLayoutDrawingSpace.value) {
    return {
      x: Math.round(left),
      y: Math.round(top),
      width: Math.max(2, Math.round(w)),
      height: Math.max(2, Math.round(h))
    }
  }
  return displayRectToNatural(left, top, w, h)
}

const pushActiveDrawing = (shape) => {
  if (usesLayoutDrawingSpace.value) {
    layoutDrawings.value.push(shape)
  } else {
    drawings.value.push(shape)
  }
}

const deselectZoomCallout = () => {
  selectedZoomCalloutId.value = null
}

const calloutSizeForSourceRect = (sourceRect, layout, natW, natH, levelPercent = zoomDetailLevel.value) => {
  const mul = Math.max(50, Math.min(400, levelPercent)) / 100
  const sx = (layout.base.width / Math.max(1, natW)) * mul
  const sy = (layout.base.height / Math.max(1, natH)) * mul
  let cw = Math.round(sourceRect.width * sx)
  let ch = Math.round(sourceRect.height * sy)
  const maxW = Math.round(layout.canvasWidth * 0.85)
  const maxH = Math.round(layout.canvasHeight * 0.45)
  if (cw > maxW) {
    const r = maxW / cw
    cw = maxW
    ch = Math.round(ch * r)
  }
  if (ch > maxH) {
    const r = maxH / ch
    ch = maxH
    cw = Math.round(cw * r)
  }
  return { width: Math.max(24, cw), height: Math.max(24, ch) }
}

const inferZoomLevelFromCallout = (callout) => {
  const el = imageRef.value
  const layout = zoomLayout.value
  if (!el?.naturalWidth || !layout || !callout?.sourceRect) {
    return callout?.zoomLevel ?? 150
  }
  const baseMulX = layout.base.width / Math.max(1, el.naturalWidth)
  if (baseMulX <= 0) {
    return callout.zoomLevel ?? 150
  }
  const actualMulX = callout.width / Math.max(1, callout.sourceRect.width)
  return Math.round(Math.max(50, Math.min(400, (actualMulX / baseMulX) * 100)))
}

const applyZoomLevelToCallout = (id, levelPercent) => {
  const el = imageRef.value
  const layout = zoomLayout.value
  const callout = zoomCallouts.value.find((c) => c.id === id)
  if (!el?.naturalWidth || !layout || !callout?.sourceRect) {
    return
  }
  const level = Math.max(50, Math.min(400, Math.round(levelPercent)))
  callout.zoomLevel = level
  const cx = callout.x + callout.width / 2
  const cy = callout.y + callout.height / 2
  const { width, height } = calloutSizeForSourceRect(
    callout.sourceRect,
    layout,
    el.naturalWidth,
    el.naturalHeight,
    level
  )
  callout.width = width
  callout.height = height
  callout.x = Math.round(cx - width / 2)
  callout.y = Math.round(cy - height / 2)
  callout.x = Math.max(0, Math.min(layout.canvasWidth - callout.width, callout.x))
  callout.y = Math.max(0, Math.min(layout.canvasHeight - callout.height, callout.y))
  ensureLayoutFitsCallouts()
}

const selectZoomCallout = (id) => {
  if (zoomDetailSelecting.value) {
    return
  }
  selectedZoomCalloutId.value = id
}

const addZoomCalloutFromSourceRect = async (sourceRect, src) => {
  const el = imageRef.value
  if (!el?.naturalWidth) {
    return
  }
  const layout = ensureZoomLayout(el.naturalWidth, el.naturalHeight)
  const { width: cw, height: ch } = calloutSizeForSourceRect(
    sourceRect,
    layout,
    el.naturalWidth,
    el.naturalHeight
  )
  const gap = Math.round(layout.canvasWidth * 0.04)
  const rowY = layout.base.y + layout.base.height + gap
  const idx = zoomCallouts.value.length
  const slots = [
    { x: gap, y: rowY },
    { x: layout.canvasWidth - gap - cw, y: rowY },
    { x: Math.round((layout.canvasWidth - cw) / 2), y: rowY + ch + gap }
  ]
  const pos = slots[idx % slots.length]
  const newId = `zc-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
  const level = Math.max(50, Math.min(400, Math.round(zoomDetailLevel.value)))
  zoomCallouts.value.push({
    id: newId,
    src,
    sourceRect: { ...sourceRect },
    zoomLevel: level,
    x: pos.x,
    y: pos.y,
    width: cw,
    height: ch
  })
  ensureLayoutFitsCallouts()
  zoomDetailSelecting.value = false
  selectedZoomCalloutId.value = null
}

const onWindowZoomSelectMove = (e) => {
  if (!zoomSelectDrag.value?.active) {
    return
  }
  if (e.type === 'touchmove' && e.cancelable) {
    e.preventDefault()
  }
  const p =
    zoomSelectDrag.value.space === 'layout' ? clientToLayoutBoard(e) : clientToImgLocal(e)
  zoomSelectDrag.value = { ...zoomSelectDrag.value, x1: p.x, y1: p.y }
}

const onWindowZoomSelectEnd = async () => {
  window.removeEventListener('mousemove', onWindowZoomSelectMove)
  window.removeEventListener('mouseup', onWindowZoomSelectEnd)
  window.removeEventListener('touchmove', onWindowZoomSelectMove)
  window.removeEventListener('touchend', onWindowZoomSelectEnd)
  const drag = zoomSelectDrag.value
  zoomSelectDrag.value = null
  if (!drag?.active) {
    return
  }
  const w = Math.abs(drag.x1 - drag.x0)
  const h = Math.abs(drag.y1 - drag.y0)
  if (w < 6 || h < 6) {
    return
  }
  const sourceRect =
    drag.space === 'layout'
      ? sourceRectFromLayoutDrag(drag)
      : displayRectToNatural(
          Math.min(drag.x0, drag.x1),
          Math.min(drag.y0, drag.y1),
          w,
          h
        )
  if (sourceRect.width < 8 || sourceRect.height < 8) {
    return
  }
  try {
    const raw = await extractRegionFromCurrentImage(sourceRect)
    const src = await shrinkDataUrlForOverlay(raw, 1400)
    await addZoomCalloutFromSourceRect(sourceRect, src)
  } catch (err) {
    console.error(err)
  }
}

const beginZoomSelectDrag = (space, x, y) => {
  zoomSelectDrag.value = { active: true, space, x0: x, y0: y, x1: x, y1: y }
  window.addEventListener('mousemove', onWindowZoomSelectMove)
  window.addEventListener('mouseup', onWindowZoomSelectEnd)
  window.addEventListener('touchmove', onWindowZoomSelectMove, { passive: false })
  window.addEventListener('touchend', onWindowZoomSelectEnd)
}

const startZoomSelectOnLayout = (e) => {
  if (!zoomDetailMode.value || !zoomDetailSelecting.value || !zoomLayout.value) {
    return
  }
  const p = clientToLayoutBoard(e)
  const b = zoomLayout.value.base
  if (p.x < b.x || p.y < b.y || p.x > b.x + b.width || p.y > b.y + b.height) {
    return
  }
  beginZoomSelectDrag('layout', p.x, p.y)
}

const stopZoomCalloutMove = () => {
  window.removeEventListener('mousemove', onZoomCalloutWindowMove)
  window.removeEventListener('mouseup', stopZoomCalloutMove)
  window.removeEventListener('touchmove', onZoomCalloutWindowMove)
  window.removeEventListener('touchend', stopZoomCalloutMove)
  movingZoomCalloutId.value = null
}

const onZoomCalloutWindowMove = (e) => {
  if (!movingZoomCalloutId.value || !zoomLayout.value) {
    return
  }
  if (e.type === 'touchmove' && e.cancelable) {
    e.preventDefault()
  }
  const callout = zoomCallouts.value.find((c) => c.id === movingZoomCalloutId.value)
  if (!callout) {
    return
  }
  const p = clientToLayoutBoard(e)
  const layout = zoomLayout.value
  let nx = p.x - zoomCalloutMoveGrab.value.x
  let ny = p.y - zoomCalloutMoveGrab.value.y
  nx = Math.max(0, Math.min(layout.canvasWidth - callout.width, nx))
  ny = Math.max(0, Math.min(layout.canvasHeight - callout.height, ny))
  callout.x = Math.round(nx)
  callout.y = Math.round(ny)
  ensureLayoutFitsCallouts()
}

const startZoomCalloutMove = (e, id) => {
  if (zoomDetailSelecting.value) {
    return
  }
  selectZoomCallout(id)
  stopZoomCalloutMove()
  stopZoomCalloutResize()
  e.preventDefault()
  e.stopPropagation()
  const callout = zoomCallouts.value.find((c) => c.id === id)
  if (!callout) {
    return
  }
  const p = clientToLayoutBoard(e)
  zoomCalloutMoveGrab.value = { x: p.x - callout.x, y: p.y - callout.y }
  movingZoomCalloutId.value = id
  window.addEventListener('mousemove', onZoomCalloutWindowMove)
  window.addEventListener('mouseup', stopZoomCalloutMove)
  window.addEventListener('touchmove', onZoomCalloutWindowMove, { passive: false })
  window.addEventListener('touchend', stopZoomCalloutMove)
}

const stopZoomCalloutResize = () => {
  const id = resizingZoomCalloutId.value
  window.removeEventListener('mousemove', onZoomCalloutResizeMove)
  window.removeEventListener('mouseup', stopZoomCalloutResize)
  window.removeEventListener('touchmove', onZoomCalloutResizeMove)
  window.removeEventListener('touchend', stopZoomCalloutResize)
  if (id) {
    const callout = zoomCallouts.value.find((c) => c.id === id)
    if (callout) {
      callout.zoomLevel = inferZoomLevelFromCallout(callout)
    }
  }
  resizingZoomCalloutId.value = null
  zoomCalloutResizeStart.value = null
}

const onZoomCalloutResizeMove = (e) => {
  if (!resizingZoomCalloutId.value || !zoomCalloutResizeStart.value || !zoomLayout.value) {
    return
  }
  if (e.type === 'touchmove' && e.cancelable) {
    e.preventDefault()
  }
  const callout = zoomCallouts.value.find((c) => c.id === resizingZoomCalloutId.value)
  if (!callout) {
    return
  }
  const st = zoomCalloutResizeStart.value
  const p = clientToLayoutBoard(e)
  const layout = zoomLayout.value
  let nw = Math.max(24, st.w0 + (p.x - st.mx0))
  let nh = Math.max(24, st.h0 + (p.y - st.my0))
  nw = Math.min(nw, layout.canvasWidth - callout.x)
  nh = Math.min(nh, layout.canvasHeight - callout.y)
  callout.width = Math.round(nw)
  callout.height = Math.round(nh)
  ensureLayoutFitsCallouts()
}

const startZoomCalloutResize = (e, id) => {
  if (zoomDetailSelecting.value) {
    return
  }
  selectZoomCallout(id)
  stopZoomCalloutMove()
  stopZoomCalloutResize()
  e.preventDefault()
  e.stopPropagation()
  const callout = zoomCallouts.value.find((c) => c.id === id)
  if (!callout) {
    return
  }
  const p = clientToLayoutBoard(e)
  zoomCalloutResizeStart.value = { mx0: p.x, my0: p.y, w0: callout.width, h0: callout.height }
  resizingZoomCalloutId.value = id
  window.addEventListener('mousemove', onZoomCalloutResizeMove)
  window.addEventListener('mouseup', stopZoomCalloutResize)
  window.addEventListener('touchmove', onZoomCalloutResizeMove, { passive: false })
  window.addEventListener('touchend', stopZoomCalloutResize)
}

const removeZoomCallout = (id) => {
  zoomCallouts.value = zoomCallouts.value.filter((c) => c.id !== id)
  if (selectedZoomCalloutId.value === id) {
    selectedZoomCalloutId.value =
      zoomCallouts.value.length > 0 ? zoomCallouts.value[zoomCallouts.value.length - 1].id : null
  }
  if (zoomCallouts.value.length === 0) {
    zoomLayout.value = null
    layoutDrawings.value = []
    zoomDetailSelecting.value = true
    selectedZoomCalloutId.value = null
  }
}

/** Fecha o painel de zoom sem apagar a composição já criada. */
const closeZoomDetailPanel = () => {
  zoomDetailMode.value = false
  zoomDetailSelecting.value = false
  deselectZoomCallout()
  zoomSelectDrag.value = null
  stopZoomCalloutMove()
  stopZoomCalloutResize()
  window.removeEventListener('mousemove', onWindowZoomSelectMove)
  window.removeEventListener('mouseup', onWindowZoomSelectEnd)
  window.removeEventListener('touchmove', onWindowZoomSelectMove)
  window.removeEventListener('touchend', onWindowZoomSelectEnd)
}

const removeAllZoomCallouts = () => {
  zoomCallouts.value = []
  zoomLayout.value = null
  layoutDrawings.value = []
  selectedZoomCalloutId.value = null
  zoomDetailSelecting.value = true
}

const clearZoomDetailState = () => {
  closeZoomDetailPanel()
  zoomDetailLevel.value = 150
  selectedZoomCalloutId.value = null
  zoomLayout.value = null
  zoomCallouts.value = []
  layoutDrawings.value = []
}

const startAnotherZoomCallout = () => {
  zoomDetailSelecting.value = true
}

const toggleZoomDetailMode = () => {
  closeDrawingMenu()
  if (zoomDetailMode.value) {
    closeZoomDetailPanel()
    return
  }
  commitPendingEffectEdits()
  showCrop.value = false
  drawingTool.value = null
  activeControl.value = null
  zoomDetailMode.value = true
  zoomDetailSelecting.value = !zoomLayout.value
}

const drawingStylePayload = () => {
  const o = {
    strokeColor: drawStrokeColor.value,
    strokeWidth: drawStrokeWidth.value
  }
  if (drawFillEnabled.value && drawFillColor.value) {
    o.fillColor = drawFillColor.value
  }
  return o
}

const viewportPointFromEvent = (e) => {
  const vp = viewportRef.value
  if (!vp) {
    return { x: 0, y: 0 }
  }
  const rect = vp.getBoundingClientRect()
  let cx
  let cy
  if (e.touches && e.touches.length > 0) {
    cx = e.touches[0].clientX
    cy = e.touches[0].clientY
  } else if (e.changedTouches && e.changedTouches.length > 0) {
    cx = e.changedTouches[0].clientX
    cy = e.changedTouches[0].clientY
  } else {
    cx = e.clientX
    cy = e.clientY
  }
  return { x: cx - rect.left, y: cy - rect.top }
}

const stagePointFromViewport = (vx, vy) => ({
  x: (vx - viewPanX.value) / viewZoom.value,
  y: (vy - viewPanY.value) / viewZoom.value
})

const clientToImgLocal = (e) => {
  const p = viewportPointFromEvent(e)
  return stagePointFromViewport(p.x, p.y)
}

const clampViewZoom = (z) => Math.max(VIEW_ZOOM_MIN, Math.min(VIEW_ZOOM_MAX, z))

const resetViewTransform = () => {
  viewZoom.value = 1
  viewPanX.value = 0
  viewPanY.value = 0
}

const zoomAtViewportPoint = (vx, vy, newZoom) => {
  const nz = clampViewZoom(newZoom)
  const sx = (vx - viewPanX.value) / viewZoom.value
  const sy = (vy - viewPanY.value) / viewZoom.value
  viewZoom.value = nz
  viewPanX.value = vx - sx * nz
  viewPanY.value = vy - sy * nz
}

const zoomViewIn = () => {
  const vp = viewportRef.value
  if (!vp) {
    viewZoom.value = clampViewZoom(viewZoom.value * 1.2)
    return
  }
  const rect = vp.getBoundingClientRect()
  zoomAtViewportPoint(rect.width / 2, rect.height / 2, viewZoom.value * 1.2)
}

const zoomViewOut = () => {
  const vp = viewportRef.value
  if (!vp) {
    viewZoom.value = clampViewZoom(viewZoom.value / 1.2)
    return
  }
  const rect = vp.getBoundingClientRect()
  zoomAtViewportPoint(rect.width / 2, rect.height / 2, viewZoom.value / 1.2)
}

const onViewportWheel = (e) => {
  const p = viewportPointFromEvent(e)
  const factor = e.deltaY < 0 ? 1.12 : 1 / 1.12
  zoomAtViewportPoint(p.x, p.y, viewZoom.value * factor)
}

const onViewPanMove = (e) => {
  if (!viewPanStart) {
    return
  }
  if (e.cancelable) {
    e.preventDefault()
  }
  const p = viewportPointFromEvent(e)
  viewPanX.value = viewPanStart.panX + (p.x - viewPanStart.x)
  viewPanY.value = viewPanStart.panY + (p.y - viewPanStart.y)
}

const stopViewPan = () => {
  window.removeEventListener('mousemove', onViewPanMove)
  window.removeEventListener('mouseup', stopViewPan)
  window.removeEventListener('touchmove', onViewPanMove)
  window.removeEventListener('touchend', stopViewPan)
  viewPanStart = null
  isViewPanning.value = false
}

const startViewPan = (e) => {
  if (e.cancelable) {
    e.preventDefault()
  }
  stopViewPan()
  const p = viewportPointFromEvent(e)
  viewPanStart = { x: p.x, y: p.y, panX: viewPanX.value, panY: viewPanY.value }
  isViewPanning.value = true
  window.addEventListener('mousemove', onViewPanMove)
  window.addEventListener('mouseup', stopViewPan)
  window.addEventListener('touchmove', onViewPanMove, { passive: false })
  window.addEventListener('touchend', stopViewPan)
}

const onViewportMouseDown = (e) => {
  if (e.button === 1 || (e.button === 0 && (spaceKeyDown.value || viewPanHandMode.value))) {
    e.preventDefault()
    startViewPan(e)
  }
}

const touchDist = (a, b) => Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY)

const touchMidViewport = (a, b) => {
  const vp = viewportRef.value
  if (!vp) {
    return { x: 0, y: 0 }
  }
  const rect = vp.getBoundingClientRect()
  return {
    x: (a.clientX + b.clientX) / 2 - rect.left,
    y: (a.clientY + b.clientY) / 2 - rect.top
  }
}

const onViewportTouchStart = (e) => {
  if (e.touches.length === 2) {
    const mid = touchMidViewport(e.touches[0], e.touches[1])
    touchPinchStart = {
      dist: touchDist(e.touches[0], e.touches[1]),
      zoom: viewZoom.value,
      panX: viewPanX.value,
      panY: viewPanY.value,
      mid,
      stageX: (mid.x - viewPanX.value) / viewZoom.value,
      stageY: (mid.y - viewPanY.value) / viewZoom.value
    }
  } else if (e.touches.length === 1 && viewPanHandMode.value) {
    gallerySwipePointer = null
    startViewPan(e)
  } else if (e.touches.length === 1) {
    tryStartGallerySwipe(e.touches[0].clientX, e.touches[0].clientY)
  }
}

const onViewportTouchMove = (e) => {
  if (e.touches.length !== 2 || !touchPinchStart) {
    return
  }
  const dist = touchDist(e.touches[0], e.touches[1])
  const mid = touchMidViewport(e.touches[0], e.touches[1])
  const nz = clampViewZoom(touchPinchStart.zoom * (dist / touchPinchStart.dist))
  viewZoom.value = nz
  viewPanX.value = mid.x - touchPinchStart.stageX * nz
  viewPanY.value = mid.y - touchPinchStart.stageY * nz
}

const onViewportTouchEnd = (e) => {
  if (!e.touches || e.touches.length < 2) {
    touchPinchStart = null
  }
  if (gallerySwipePointer && e.changedTouches?.length) {
    const touch = e.changedTouches[0]
    finishGallerySwipe(touch.clientX, touch.clientY)
  }
  if (!e.touches || e.touches.length === 0) {
    stopViewPan()
    gallerySwipePointer = null
  }
}

const onSpaceKeyDown = (e) => {
  if (e.code !== 'Space' || e.repeat || isFormFieldTarget(e.target)) {
    return
  }
  spaceKeyDown.value = true
  e.preventDefault()
}

const onSpaceKeyUp = (e) => {
  if (e.code === 'Space') {
    spaceKeyDown.value = false
    stopViewPan()
  }
}

const onDrawingSurfaceMoveDrawingDraft = (e) => {
  const t = drawingTool.value
  const pts = pathDraftPoints.value
  if (t === 'polygon' && pts.length > 0) {
    pathDraftHoverPos.value = drawingPointerForEvent(e).draft
    return
  }
  if (t === 'bezier' && pts.length > 0 && pts.length < 4) {
    if (e.type === 'touchmove' && e.cancelable) {
      e.preventDefault()
    }
    pathDraftHoverPos.value = drawingPointerForEvent(e).draft
    return
  }
  pathDraftHoverPos.value = null
}

const onDrawingSurfaceLeaveDrawingDraft = () => {
  pathDraftHoverPos.value = null
}

const closeDrawingMenu = () => {
  showDrawingMenu.value = false
  showPixelateMenu.value = false
  showBlurMenu.value = false
  showFilterMenu.value = false
}

const drawingToolLabels = {
  pen: 'Caneta',
  line: 'Linha',
  arrow: 'Seta',
  rectangle: 'Retângulo',
  ellipse: 'Elipse',
  circle: 'Círculo',
  polygon: 'Polígono',
  bezier: 'Bézier',
  pixel: 'Píxel',
  fill: 'Preencher',
}

const drawingToolLabel = computed(() => drawingToolLabels[drawingTool.value] ?? 'Desenho')

const stopPenStroke = () => {
  window.removeEventListener('mousemove', onPenStrokeMove)
  window.removeEventListener('mouseup', onPenStrokeEnd)
  window.removeEventListener('touchmove', onPenStrokeMove)
  window.removeEventListener('touchend', onPenStrokeEnd)
  isPenDrawing.value = false
}

const simplifyPenPointsNatural = (points, minDist = 1) => {
  if (points.length < 2) {
    return points
  }
  const out = [points[0]]
  for (let i = 1; i < points.length; i++) {
    const last = out[out.length - 1]
    if (Math.hypot(points[i].x - last.x, points[i].y - last.y) >= minDist) {
      out.push(points[i])
    }
  }
  const end = points[points.length - 1]
  if (end.x !== out[out.length - 1].x || end.y !== out[out.length - 1].y) {
    out.push(end)
  }
  return out
}

const onPenStrokeMove = (e) => {
  if (!isPenDrawing.value) {
    return
  }
  if (e.cancelable && e.type === 'touchmove') {
    e.preventDefault()
  }
  const p = drawingPointerForEvent(e).draft
  const pts = penDraftPoints.value
  const last = pts[pts.length - 1]
  const minDist = usesLayoutDrawingSpace.value ? 1 : 2
  if (last && Math.hypot(p.x - last.x, p.y - last.y) < minDist) {
    return
  }
  penDraftPoints.value = [...pts, p]
}

const onPenStrokeEnd = () => {
  stopPenStroke()
  const nat = simplifyPenPointsNatural(
    penDraftPoints.value.map((pt) => draftPointToStored(pt))
  )
  penDraftPoints.value = []
  if (nat.length < 2) {
    return
  }
  pushActiveDrawing({
    type: 'pen',
    points: nat.map((p) => ({ x: p.x, y: p.y })),
    ...drawingStylePayload()
  })
  finishVectorDrawingEdit()
}

const closeDrawingPanel = () => {
  stopPenStroke()
  stopDrawingMove()
  penDraftPoints.value = []
  drawingTool.value = null
  pathDraftPoints.value = []
  pathDraftHoverPos.value = null
  drawDrag.value = null
  closeDrawingMenu()
}

const toggleDrawingMenu = () => {
  if (showDrawingMenu.value || drawingTool.value) {
    closeDrawingPanel()
    return
  }
  closeZoomDetailPanel()
  commitPendingEffectEdits()
  showDrawingMenu.value = true
  activeControl.value = null
  showPixelateMenu.value = false
  showBlurMenu.value = false
  applyChanges()
}

const togglePixelateMenu = () => {
  if (showPixelateMenu.value) {
    showPixelateMenu.value = false
    return
  }
  if (activeControl.value === 'pixelate' || showPixelateRegion.value) {
    closePixelateOption()
    return
  }
  commitPendingEffectEdits()
  showPixelateMenu.value = true
  showDrawingMenu.value = false
  showBlurMenu.value = false
}

const toggleBlurMenu = () => {
  if (showBlurMenu.value) {
    showBlurMenu.value = false
    return
  }
  if (activeControl.value === 'blur' || showBlurRegion.value) {
    closeBlurOption()
    return
  }
  commitPendingEffectEdits()
  showBlurMenu.value = true
  showDrawingMenu.value = false
  showPixelateMenu.value = false
}

const selectBlurRectangle = () => {
  drawingTool.value = null
  pathDraftPoints.value = []
  drawDrag.value = null
  prepareSwitchFromPixelateTool()
  blurShapeMode.value = 'rectangle'
  blurApplyGlobal.value = false
  clearBlurBrushMask()
  committedBlurMask.value = null
  showBlurMenu.value = false
  if (!showBlurRegion.value) {
    openBlurRectangleEditor()
  } else {
    commitPendingEffectEdits()
    applyChanges()
  }
}

const selectBlurGlobal = () => {
  ensureBlurEffectStrength()
  closeDrawingMenu()
  drawingTool.value = null
  pathDraftPoints.value = []
  drawDrag.value = null
  prepareSwitchFromPixelateTool()
  stopBlurBrushStroke()
  clearBlurBrushMask()
  committedBlurMask.value = null
  blurShapeMode.value = 'rectangle'
  if (showBlurRegion.value) {
    exitBlurRectangleUi()
  }
  committedBlurRegion.value = null
  blurApplyGlobal.value = true
  activeControl.value = 'blur'
  applyChanges()
}

const selectBlurBrush = () => {
  drawingTool.value = null
  pathDraftPoints.value = []
  drawDrag.value = null
  if (!showBlurRegion.value || blurShapeMode.value !== 'brush') {
    ensureBlurEffectStrength()
    prepareSwitchFromPixelateTool()
    committedBlurRegion.value = null
    committedBlurMask.value = null
    blurApplyGlobal.value = false
    blurShapeMode.value = 'brush'
    showBlurRegion.value = true
    activeControl.value = 'blur'
    stopBlurPan()
    clearBlurBrushMask()
    ensureBlurBrushCanvas()
    scheduleApplyChanges()
  } else {
    closeDrawingMenu()
  }
}

const selectPixelateRectangle = () => {
  drawingTool.value = null
  pathDraftPoints.value = []
  drawDrag.value = null
  prepareSwitchFromBlurTool()
  pixelateShapeMode.value = 'rectangle'
  pixelateApplyGlobal.value = false
  clearPixelateBrushMask()
  committedPixelateMask.value = null
  showPixelateMenu.value = false
  if (!showPixelateRegion.value) {
    openPixelateRectangleEditor()
  } else {
    commitPendingEffectEdits()
    applyChanges()
  }
}

const selectPixelateBrush = () => {
  drawingTool.value = null
  pathDraftPoints.value = []
  drawDrag.value = null
  if (!showPixelateRegion.value || pixelateShapeMode.value !== 'brush') {
    ensurePixelateEffectStrength()
    prepareSwitchFromBlurTool()
    committedPixelateRegion.value = null
    committedPixelateMask.value = null
    pixelateApplyGlobal.value = false
    pixelateShapeMode.value = 'brush'
    showPixelateRegion.value = true
    activeControl.value = 'pixelate'
    stopPixelatePan()
    clearPixelateBrushMask()
    ensurePixelateBrushCanvas()
    scheduleApplyChanges()
  } else {
    closeDrawingMenu()
  }
}

const selectPixelateGlobal = () => {
  ensurePixelateEffectStrength()
  closeDrawingMenu()
  drawingTool.value = null
  pathDraftPoints.value = []
  drawDrag.value = null
  prepareSwitchFromBlurTool()
  pixelateShapeMode.value = 'rectangle'
  clearPixelateBrushMask()
  committedPixelateMask.value = null
  if (showPixelateRegion.value) {
    exitPixelateRectangleUi()
  }
  committedPixelateRegion.value = null
  pixelateApplyGlobal.value = true
  activeControl.value = 'pixelate'
  applyChanges()
}

const selectDrawingTool = (tool) => {
  deselectZoomCallout()
  closeZoomDetailPanel()
  commitPendingEffectEdits()
  showPixelateMenu.value = false
  showBlurMenu.value = false
  if (activeControl.value === 'blur' || activeControl.value === 'pixelate') {
    activeControl.value = null
    applyChanges()
  } else {
    activeControl.value = null
  }
  stopDrawingMove()
  stopPenStroke()
  penDraftPoints.value = []
  pathDraftPoints.value = []
  pathDraftHoverPos.value = null
  drawDrag.value = null
  if (drawingTool.value === tool) {
    drawingTool.value = null
  } else {
    drawingTool.value = tool
  }
  closeDrawingMenu()
}

const clearDrawings = () => {
  stopPenStroke()
  stopDrawingMove()
  penDraftPoints.value = []
  drawings.value = []
  layoutDrawings.value = []
  pathDraftPoints.value = []
  pathDraftHoverPos.value = null
  recordEditHistory()
}

const undoLastDrawing = () => {
  if (usesLayoutDrawingSpace.value) {
    layoutDrawings.value.pop()
  } else {
    drawings.value.pop()
  }
  recordEditHistory()
}

const commitPolygonFromPath = () => {
  if (pathDraftPoints.value.length < 3) {
    return
  }
  const pts = pathDraftPoints.value.map((pt) => draftPointToStored(pt))
  pathDraftPoints.value = []
  pathDraftHoverPos.value = null
  pushActiveDrawing({
    type: 'polygon',
    points: pts.map((p) => ({ x: p.x, y: p.y })),
    ...drawingStylePayload()
  })
  finishVectorDrawingEdit()
}

const commitBezierFromPath = () => {
  if (pathDraftPoints.value.length < 4) {
    return
  }
  const pts = pathDraftPoints.value.slice(0, 4).map((pt) => draftPointToStored(pt))
  pathDraftPoints.value = []
  pathDraftHoverPos.value = null
  pushActiveDrawing({
    type: 'bezier',
    points: pts.map((p) => ({ x: p.x, y: p.y })),
    ...drawingStylePayload()
  })
  finishVectorDrawingEdit()
}

const clearPathDraft = () => {
  pathDraftPoints.value = []
  pathDraftHoverPos.value = null
}

const onWindowDrawMove = (e) => {
  if (!drawDrag.value?.active) {
    return
  }
  const p = drawingPointerForEvent(e).draft
  drawDrag.value = { ...drawDrag.value, x1: p.x, y1: p.y }
}

const onWindowDrawEnd = () => {
  window.removeEventListener('mousemove', onWindowDrawMove)
  window.removeEventListener('mouseup', onWindowDrawEnd)
  window.removeEventListener('touchmove', onWindowDrawMove)
  window.removeEventListener('touchend', onWindowDrawEnd)
  if (!drawDrag.value?.active) {
    return
  }
  const { x0, y0, x1, y1 } = drawDrag.value
  drawDrag.value = null
  const t = drawingTool.value
  if (!t || Math.hypot(x1 - x0, y1 - y0) < 4) {
    return
  }
  const left = Math.min(x0, x1)
  const top = Math.min(y0, y1)
  const w = Math.abs(x1 - x0)
  const h = Math.abs(y1 - y0)
  if (t === 'line' || t === 'arrow') {
    const p0 = draftPointToStored({ x: x0, y: y0 })
    const p1 = draftPointToStored({ x: x1, y: y1 })
    pushActiveDrawing({
      type: t,
      x1: p0.x,
      y1: p0.y,
      x2: p1.x,
      y2: p1.y,
      strokeColor: drawStrokeColor.value,
      strokeWidth: drawStrokeWidth.value
    })
    finishVectorDrawingEdit()
    return
  }
  const r = draftRectToStored(left, top, w, h)
  if (r.width < 2 || r.height < 2) {
    return
  }
  if (t === 'rectangle') {
    pushActiveDrawing({
      type: 'rectangle',
      x: r.x,
      y: r.y,
      width: r.width,
      height: r.height,
      ...drawingStylePayload()
    })
  } else if (t === 'ellipse') {
    pushActiveDrawing({
      type: 'ellipse',
      cx: Math.round(r.x + r.width / 2),
      cy: Math.round(r.y + r.height / 2),
      width: Math.max(2, r.width),
      height: Math.max(2, r.height),
      ...drawingStylePayload()
    })
  } else if (t === 'circle') {
    const d = Math.max(2, Math.min(r.width, r.height))
    pushActiveDrawing({
      type: 'circle',
      cx: Math.round(r.x + r.width / 2),
      cy: Math.round(r.y + r.height / 2),
      diameter: d,
      ...drawingStylePayload()
    })
  }
  finishVectorDrawingEdit()
}

const onImageContextMenu = (e) => {
  if (!showMaskBrushSizeControl.value) {
    return
  }
  e.preventDefault()
  if (
    showBlurRegion.value &&
    blurShapeMode.value === 'brush' &&
    !resizeDirection.value
  ) {
    startBlurBrushStroke(e)
    return
  }
  if (
    showPixelateRegion.value &&
    pixelateShapeMode.value === 'brush' &&
    !resizeDirection.value
  ) {
    startPixelateBrushStroke(e)
  }
}

const onImageMouseDown = (e) => {
  if (e.button === 2 && showMaskBrushSizeControl.value) {
    e.preventDefault()
    if (
      showBlurRegion.value &&
      blurShapeMode.value === 'brush' &&
      !resizeDirection.value
    ) {
      startBlurBrushStroke(e)
      return
    }
    if (
      showPixelateRegion.value &&
      pixelateShapeMode.value === 'brush' &&
      !resizeDirection.value
    ) {
      startPixelateBrushStroke(e)
      return
    }
  }
  if (viewPanHandMode.value || spaceKeyDown.value) {
    e.preventDefault()
    startViewPan(e)
    return
  }
  if (zoomDetailMode.value && zoomDetailSelecting.value && !zoomLayout.value) {
    e.preventDefault()
    const pos = clientToImgLocal(e)
    beginZoomSelectDrag('img', pos.x, pos.y)
    return
  }
  if (
    showBlurRegion.value &&
    blurShapeMode.value === 'brush' &&
    !resizeDirection.value
  ) {
    startBlurBrushStroke(e)
    return
  }
  if (
    showPixelateRegion.value &&
    pixelateShapeMode.value === 'brush' &&
    !resizeDirection.value
  ) {
    startPixelateBrushStroke(e)
    return
  }
  if (!drawingTool.value) {
    deselectDrawing()
    return
  }
  onDrawingSurfaceMouseDown(e)
}

const onDrawingSurfaceMouseDown = (e) => {
  if (!drawingTool.value) {
    return
  }
  if (showCrop.value || (showBlurRegion.value && blurShapeMode.value === 'rectangle') || (showPixelateRegion.value && pixelateShapeMode.value === 'rectangle')) {
    return
  }
  e.preventDefault()
  deselectZoomCallout()
  const pos = drawingPointerForEvent(e).draft
  const t = drawingTool.value
  if (t === 'pixel') {
    const p = draftPointToStored(pos)
    pushActiveDrawing({ type: 'pixel', x: p.x, y: p.y, color: drawStrokeColor.value })
    if (!usesLayoutDrawingSpace.value) {
      void bakeDrawingsIntoPreview()
    } else {
      finishVectorDrawingEdit()
    }
    return
  }
  if (t === 'fill') {
    const p = draftPointToStored(pos)
    const col = drawFillEnabled.value && drawFillColor.value ? drawFillColor.value : drawStrokeColor.value
    pushActiveDrawing({ type: 'fill', x: p.x, y: p.y, color: col })
    if (!usesLayoutDrawingSpace.value) {
      void bakeDrawingsIntoPreview()
    } else {
      finishVectorDrawingEdit()
    }
    return
  }
  if (t === 'pen') {
    stopPenStroke()
    penDraftPoints.value = [pos]
    isPenDrawing.value = true
    window.addEventListener('mousemove', onPenStrokeMove)
    window.addEventListener('mouseup', onPenStrokeEnd)
    window.addEventListener('touchmove', onPenStrokeMove, { passive: false })
    window.addEventListener('touchend', onPenStrokeEnd)
    return
  }
  if (t === 'polygon') {
    pathDraftPoints.value = [...pathDraftPoints.value, { x: pos.x, y: pos.y }]
    return
  }
  if (t === 'bezier') {
    if (pathDraftPoints.value.length >= 4) {
      pathDraftPoints.value = []
      pathDraftHoverPos.value = null
    }
    pathDraftPoints.value = [...pathDraftPoints.value, { x: pos.x, y: pos.y }]
    if (pathDraftPoints.value.length >= 4) {
      commitBezierFromPath()
    }
    return
  }
  if (['line', 'arrow', 'rectangle', 'ellipse', 'circle'].includes(t)) {
    drawDrag.value = { active: true, x0: pos.x, y0: pos.y, x1: pos.x, y1: pos.y }
    window.addEventListener('mousemove', onWindowDrawMove)
    window.addEventListener('mouseup', onWindowDrawEnd)
    window.addEventListener('touchmove', onWindowDrawMove, { passive: false })
    window.addEventListener('touchend', onWindowDrawEnd)
  }
}

const onDrawingSurfaceClick = (e) => {
  if (usesLayoutDrawingSpace.value) {
    return
  }
  onImageClickUnified(e)
}

const onDrawingSurfaceTouchStart = (e) => {
  if (usesLayoutDrawingSpace.value) {
    if (!drawingTool.value) {
      return
    }
    if (showCrop.value || (showBlurRegion.value && blurShapeMode.value === 'rectangle') || (showPixelateRegion.value && pixelateShapeMode.value === 'rectangle')) {
      return
    }
    e.preventDefault()
    onDrawingSurfaceMouseDown(e)
    return
  }
  onImageTouchStartUnified(e)
}

const buildEditPayload = (options = {}) => {
  const cropPayload =
    options.crop !== undefined
      ? options.crop
      : showCrop.value
        ? null
        : getCropNaturalPayload()

  const payload = {
    user_id: props.userId,
    image_url: props.photo.filename,
    brightness: brightness.value,
    contrast: contrast.value,
    saturation: saturation.value / 100,
    filter_preset: activeFilterPreset.value || null,
    gamma: gamma.value,
    gamma_fine: gammaFine.value,
    blur: resolveActiveBlurLevel(),
    blur_brush:
      resolveActiveBlurLevel() > 0 &&
      Boolean(
        (showBlurRegion.value && blurShapeMode.value === 'brush') ||
          committedBlurMask.value
      ),
    blur_mask: resolveBlurMaskPayload(),
    blur_region: resolveBlurRegionPayload(),
    pixelate: resolveActivePixelateLevel(),
    pixelate_brush:
      resolveActivePixelateLevel() > 0 &&
      Boolean(
        (showPixelateRegion.value && pixelateShapeMode.value === 'brush') ||
          committedPixelateMask.value
      ),
    pixelate_mask: resolvePixelateMaskPayload(),
    pixelate_region: resolvePixelateRegionPayload(),
    sharpen: sharpen.value,
    flip_horizontal: flipHorizontal.value,
    flip_vertical: flipVertical.value,
    rotation: rotation.value,
    crop: cropPayload,
    texts: texts.value,
    drawings:
      options.includeSaveFields || options.bakeDrawings ? drawings.value : [],
    image_overlays:
      options.includeSaveFields || shouldBakeImageOverlaysInPreview.value
        ? mapImageOverlaysPayload()
        : [],
    caption_settings: buildCaptionSettingsPayload(),
    watermark: buildWatermarkPayload()
  }

  if (options.includeSaveFields) {
    payload.photo_caption = {
      enabled: photoCaptionApplied.value !== null,
      number: Math.max(1, Math.round(photoCaptionApplied.value?.number || 1)),
      description: photoCaptionApplied.value?.description || ''
    }
    payload.zoom_layout = buildZoomLayoutPayload()
    payload.layout_drawings = layoutDrawings.value
    payload.save_mode = saveMode.value
    if (props.galleryFoldersEnabled && saveMode.value === 'copy') {
      payload.save_copy_folder_id = saveCopyFolderId.value || 'entrada'
    }
    payload.output_format = saveFormat.value
    payload.output_quality = saveQuality.value
  }

  return payload
}

const applyChanges = async (options = {}) => {
  if (previewInFlight) {
    previewPending = true
    previewPendingOptions = { ...previewPendingOptions, ...options }
    return
  }
  previewInFlight = true
  const requestId = ++previewRequestId
  try {
    const response = await axios.post('/api/image/preview', buildEditPayload(options))

    if (requestId !== previewRequestId) {
      return
    }

    if (response.data.success) {
      currentImageUrl.value = response.data.image_data
      if (options.bakeDrawings) {
        drawings.value = []
        layoutDrawings.value = []
        selectedDrawingIndex.value = null
      }
      if (showCrop.value) {
        scheduleCropDisplaySync()
      }
      if (options.resetHistory) {
        resetEditHistoryStack(captureEditSnapshot())
      } else if (!options.skipHistoryRecord) {
        recordEditHistory()
      }
    } else {
      throw new Error(response.data.message || 'Erro ao aplicar edições')
    }
  } catch (error) {
    if (requestId !== previewRequestId) {
      return
    }
    const status = error.response?.status
    if (status === 429) {
      previewPending = false
      clearPreviewDebounceTimer()
      previewDebounceTimer = window.setTimeout(() => {
        previewDebounceTimer = null
        applyChanges(options)
      }, 2000)
      return
    }
    console.error('Erro ao aplicar edições:', error)
    emit('error', error.message)
  } finally {
    previewInFlight = false
    if (previewPending) {
      previewPending = false
      const pending = previewPendingOptions
      previewPendingOptions = null
      const opts = { ...(pending || {}) }
      delete opts.skipHistoryRecord
      window.setTimeout(() => applyChanges(opts), 0)
    }
  }
}

/** Repõe camadas e ajustes depois de gravar (já incorporados no ficheiro). */
const syncStateAfterSave = (url) => {
  stopBlurPan()
  stopPixelatePan()
  stopBlurBrushStroke()
  stopPixelateBrushStroke()
  stopCropPan()
  stopOverlayMove()
  stopOverlayResize()
  stopDrawingMove()
  stopPenStroke()
  stopMovingText()
  brightness.value = 0
  contrast.value = 0
  saturation.value = 0
  gamma.value = 0
  gammaFine.value = 0
  blur.value = 0
  pixelate.value = 0
  sharpen.value = 0
  maskBrushImageKey = ''
  maskBrushEraseMode.value = false
  cropAspectPreset.value = 'free'
  showingOriginal.value = false
  maskBrushRadiusNatural.value = 16
  flipHorizontal.value = false
  flipVertical.value = false
  rotation.value = 0
  showCrop.value = false
  cropStart.value = { x: 0, y: 0 }
  cropSize.value = { width: 0, height: 0 }
  cropNatural.value = { x: 0, y: 0, width: 0, height: 0 }
  committedCrop.value = null
  cropPendingReferenceReset.value = false
  cropReferenceSize.value = { width: 0, height: 0 }
  showBlurRegion.value = false
  blurStart.value = { x: 0, y: 0 }
  blurSize.value = { width: 0, height: 0 }
  blurShapeMode.value = 'rectangle'
  committedBlurRegion.value = null
  committedBlurMask.value = null
  blurApplyGlobal.value = false
  clearBlurBrushMask()
  showPixelateRegion.value = false
  pixelateShapeMode.value = 'rectangle'
  clearPixelateBrushMask()
  pixelateStart.value = { x: 0, y: 0 }
  pixelateSize.value = { width: 0, height: 0 }
  committedPixelateRegion.value = null
  committedPixelateMask.value = null
  pixelateApplyGlobal.value = false
  drawings.value = []
  layoutDrawings.value = []
  drawingTool.value = null
  penDraftPoints.value = []
  pathDraftPoints.value = []
  pathDraftHoverPos.value = null
  drawDrag.value = null
  showDrawingMenu.value = false
  showPixelateMenu.value = false
  showBlurMenu.value = false
  showFilterMenu.value = false
  activeFilterPreset.value = null
  activeControl.value = null
  clearZoomDetailState()
  texts.value = []
  selectedTextIndex.value = null
  textContent.value = ''
  textBold.value = false
  textAngle.value = 0
  textAlign.value = 'left'
  textStrokeEnabled.value = false
  textStrokeWidth.value = 2
  textStrokeColor.value = '#000000'
  textBgEnabled.value = false
  textBgColor.value = '#000000'
  textBgOpacity.value = 75
  textBgPadding.value = 6
  imageOverlays.value = []
  selectedOverlayId.value = null
  captionSettings.value = createDefaultCaptionSettings()
  showCustomCaptionPrefix.value = false
  photoCaptionApplied.value = null
  photoCaptionDraft.value = null
  watermarkApplied.value = null
  watermarkDraft.value = null
  showSavePanel.value = false
  resetViewTransform()

  if (typeof url === 'string' && url !== '') {
    currentImageUrl.value = url
    originalImageUrl.value = url
  }

  nextTick(() => {
    syncImageNaturalMetrics()
    resetEditHistoryStack(captureEditSnapshot())
    editHistoryReady.value = true
  })
}

const resetFilters = () => {
  clearPreviewDebounceTimer()
  clearPixelateBrushPreviewDebounce()
  clearBlurBrushPreviewDebounce()
  cancelBlurPanPreviewRaf()
  cancelPixelatePanPreviewRaf()
  cancelCropPanPreviewRaf()
  window.removeEventListener('mousemove', onWindowDrawMove)
  window.removeEventListener('mouseup', onWindowDrawEnd)
  window.removeEventListener('touchmove', onWindowDrawMove)
  window.removeEventListener('touchend', onWindowDrawEnd)
  stopViewPan()
  resetViewTransform()
  viewPanHandMode.value = false
  saveMode.value = props.showUseInForm ? 'copy' : 'overwrite'
  saveCopyFolderId.value = props.photo?.folder_id || 'entrada'
  saveFormat.value = 'jpeg'
  saveQuality.value = 85
  editHistoryReady.value = false
  syncStateAfterSave(props.imageUrl)
  applyChanges({ resetHistory: true })
}

const closeSavePanel = () => {
  showSavePanel.value = false
}

const closeUseInFormWarning = () => {
  showUseInFormWarning.value = false
}

const emitUseInForm = () => {
  if (!props.photo?.filename) {
    emit('error', 'Imagem inválida')
    return
  }
  emit('useInForm', {
    filename: props.photo.filename,
    url: props.imageUrl,
    is_blank_canvas: Boolean(props.photo.is_blank_canvas)
  })
}

const requestUseInForm = () => {
  if (hasChanges.value) {
    showUseInFormWarning.value = true
    showSavePanel.value = false
    return
  }
  emitUseInForm()
}

const useInFormWithoutSaving = () => {
  closeUseInFormWarning()
  emitUseInForm()
}

const saveAndUseInForm = async () => {
  if (isSaving.value) {
    return
  }
  if (!hasChanges.value) {
    closeUseInFormWarning()
    emitUseInForm()
    return
  }
  const saved = await saveImage({ useInForm: true })
  if (saved) {
    closeUseInFormWarning()
  }
}

const toggleSavePanel = () => {
  showSavePanel.value = !showSavePanel.value
  if (showSavePanel.value) {
    showUseInFormWarning.value = false
  }
}

const confirmSave = async () => {
  if (isSaving.value) {
    return
  }
  await saveImage()
}

const saveImage = async (options = {}) => {
    clearPreviewDebounceTimer()
    previewRequestId++
    previewPending = false
    previewPendingOptions = null
    isSaving.value = true
    let saved = null
    try {
        const response = await axios.post(
          '/api/image/edit',
          buildEditPayload({ includeSaveFields: true, crop: getCropNaturalPayload() })
        )

        if (response.data.success) {
            syncStateAfterSave(response.data.url)
            saved = {
              filename: response.data.image_url,
              url: response.data.url,
              saveMode: response.data.save_mode || saveMode.value,
              saveCopyFolderId:
                response.data.save_copy_folder_id ??
                (saveMode.value === 'copy' ? saveCopyFolderId.value : null)
            }
            emit('save', {
              ...saved,
              useInForm: Boolean(options.useInForm)
            })
        } else {
            throw new Error(response.data.message || 'Erro ao salvar imagem')
        }
    } catch (error) {
        console.error('Erro ao salvar imagem:', error)
        emit('error', error.message)
    } finally {
        isSaving.value = false
    }
    return saved
}

const hasChanges = computed(() => {
  return brightness.value !== 0 || 
         contrast.value !== 0 || 
         saturation.value !== 0 || 
         gamma.value !== 0 ||
         gammaFine.value !== 0 ||
         blur.value !== 0 ||
         pixelate.value !== 0 ||
         sharpen.value !== 0 ||
         flipHorizontal.value ||
         flipVertical.value ||
         rotation.value !== 0 ||
         committedCrop.value !== null ||
         committedBlurRegion.value !== null ||
         committedBlurMask.value !== null ||
         blurApplyGlobal.value ||
         committedPixelateRegion.value !== null ||
         committedPixelateMask.value !== null ||
         pixelateApplyGlobal.value ||
         showCrop.value ||
         showBlurRegion.value ||
         blurMaskDirty.value ||
         showPixelateRegion.value ||
         pixelateMaskDirty.value ||
         texts.value.length > 0 ||
         drawings.value.length > 0 ||
         layoutDrawings.value.length > 0 ||
         imageOverlays.value.length > 0 ||
         photoCaptionApplied.value !== null ||
         watermarkApplied.value ||
         zoomCallouts.value.length > 0
})

watch(hasChanges, (changed) => {
  if (!changed) {
    showSavePanel.value = false
    showUseInFormWarning.value = false
  }
})

const positionText = (e) => {
  if (drawingTool.value) {
    return
  }
  if (showCrop.value || showBlurRegion.value || showPixelateRegion.value) return
  if (activeControl.value !== 'text') return

  if (!textContent.value.trim()) return
  
  const pos = clientToImgLocal(e)
  const pNat = displayPointToNatural(Math.round(pos.x), Math.round(pos.y))

  selectedTextIndex.value = null
  texts.value.push(buildTextItemFromPanel(textContent.value.trim(), pNat.x, pNat.y))
  textContent.value = ''
  void applyChanges().then(() => {
    nextTick(() => recordEditHistory())
  })
}

const onImageClickUnified = (e) => {
  if (drawingTool.value || viewPanHandMode.value || isViewPanning.value) {
    return
  }
  positionText(e)
}

const onImageTouchStartUnified = (e) => {
  if (
    showBlurRegion.value &&
    blurShapeMode.value === 'brush' &&
    !resizeDirection.value
  ) {
    e.preventDefault()
    startBlurBrushStroke(e)
    return
  }
  if (
    showPixelateRegion.value &&
    pixelateShapeMode.value === 'brush' &&
    !resizeDirection.value
  ) {
    e.preventDefault()
    startPixelateBrushStroke(e)
    return
  }
  if (viewPanHandMode.value) {
    e.preventDefault()
    startViewPan(e)
    return
  }
  if (drawingTool.value) {
    if (showCrop.value || (showBlurRegion.value && blurShapeMode.value === 'rectangle') || (showPixelateRegion.value && pixelateShapeMode.value === 'rectangle')) {
      return
    }
    e.preventDefault()
    onImageMouseDown(e)
    return
  }
  positionText(e)
}

watch(
  () => props.photo?.filename,
  (filename, previousFilename) => {
    if (!filename || !props.imageUrl) {
      return
    }
    if (previousFilename !== undefined && filename === previousFilename) {
      return
    }
    currentImageUrl.value = props.imageUrl
    originalImageUrl.value = props.imageUrl
    resetViewTransform()
    editHistoryReady.value = false
    editHistoryIndex.value = -1
    editHistory.value = []
    nextTick(() => bootstrapEditHistory())
  },
  { immediate: true }
)

watch(
  () => props.imageUrl,
  (url, previousUrl) => {
    if (!url || previousUrl === undefined || url === previousUrl) {
      return
    }
    if (props.photo?.filename) {
      currentImageUrl.value = url
      originalImageUrl.value = url
      resetViewTransform()
      nextTick(() => syncImageNaturalMetrics())
    }
  }
)

onMounted(() => {
  currentImageUrl.value = props.imageUrl
  window.addEventListener('keydown', onSpaceKeyDown)
  window.addEventListener('keyup', onSpaceKeyUp)
  window.addEventListener('keydown', onHistoryKeyDown)
  nextTick(() => {
    syncImageNaturalMetrics()
    if (!editHistoryReady.value) {
      bootstrapEditHistory()
    }
  })
})

watch(currentImageUrl, () => {
  nextTick(() => {
    syncImageNaturalMetrics()
    if (showCrop.value) {
      scheduleCropDisplaySync()
    }
  })
})

watch(showCrop, (active) => {
  if (active) {
    nextTick(() => ensureCropLayoutObserver())
  } else {
    disconnectCropLayoutObserver()
    cancelCropDisplaySyncRaf()
  }
})

watch(viewportBottomPaddingClass, () => {
  nextTick(() => {
    if (showCrop.value) {
      scheduleCropDisplaySync()
    }
  })
})

onUnmounted(() => {
  dismissBlankCanvasHint()
  clearPreviewDebounceTimer()
  clearPixelateBrushPreviewDebounce()
  clearBlurBrushPreviewDebounce()
  stopViewPan()
  window.removeEventListener('keydown', onSpaceKeyDown)
  window.removeEventListener('keyup', onSpaceKeyUp)
  window.removeEventListener('keydown', onHistoryKeyDown)
  window.removeEventListener('mousemove', onWindowDrawMove)
  window.removeEventListener('mouseup', onWindowDrawEnd)
  window.removeEventListener('touchmove', onWindowDrawMove)
  window.removeEventListener('touchend', onWindowDrawEnd)
  stopPenStroke()
  stopMovingText()
  stopPixelateBrushStroke()
  stopBlurBrushStroke()
  stopOverlayMove()
  stopOverlayResize()
  stopDrawingMove()
  stopCropPan()
  disconnectCropLayoutObserver()
  cancelCropDisplaySyncRaf()
  clearPreviewDebounceTimer()
})

defineExpose({
  addImageOverlayFromUrl,
  hasUnsavedChanges: () => hasChanges.value
})
</script>

<style scoped>
.editor-view-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.zoom-callout-img,
.zoom-layout-base-img {
  object-fit: fill;
}
</style>
