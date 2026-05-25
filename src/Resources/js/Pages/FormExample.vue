<script setup>
import { ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import CameraFormModal from '../Components/CameraFormModal.vue'

defineProps({
  userId: {
    type: [Number, String],
    required: true
  }
})

const formTitle = ref('Relatório de inspeção')
const formNotes = ref('')
const formImageFilename = ref('')
const formImageUrl = ref('')
const formImagePreviewKey = ref(0)
const showEditor = ref(false)

const applyImageToForm = (payload) => {
  if (payload?.filename) {
    formImageFilename.value = payload.filename
  }
  if (payload?.url) {
    formImageUrl.value = payload.url
    formImagePreviewKey.value += 1
  }
}
</script>

<template>
  <Head title="Formulário — exemplo com editor" />
  <div class="min-h-screen bg-slate-100 px-4 py-10">
    <div class="mx-auto max-w-lg">
      <h1 class="text-2xl font-semibold text-slate-900">Formulário com editor em popup</h1>
      <p class="mt-2 text-sm text-slate-600">
        <Link href="/camera" class="text-blue-600 underline">/camera</Link>
        — galeria completa; aqui só o modal do pacote.
      </p>
      <form class="mt-8 space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Título</label>
          <input v-model="formTitle" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
        </div>
        <div>
          <span class="mb-2 block text-sm font-medium text-slate-700">Imagem</span>
          <img
            v-if="formImageUrl"
            :key="formImagePreviewKey"
            :src="formImageUrl"
            alt=""
            class="mb-3 max-h-48 w-full object-contain"
          />
          <button
            type="button"
            class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-500"
            @click="showEditor = true"
          >
            Abrir editor de imagens
          </button>
        </div>
      </form>
    </div>
  </div>

  <CameraFormModal
    v-model:open="showEditor"
    :user-id="userId"
    :initial-filename="formImageFilename || undefined"
    :subtitle="formImageFilename || undefined"
    @use-in-form="applyImageToForm"
  />
</template>
