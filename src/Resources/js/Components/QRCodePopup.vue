<template>
    <div v-if="show" class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <!-- Cabeçalho -->
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Upload de Mobile</h3>
                <button
                    @click="$emit('close')"
                    class="text-gray-400 hover:text-gray-500 focus:outline-none"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Conteúdo -->
            <div class="space-y-2 text-center text-sm text-gray-600">
                <p>Digitalize o QR Code com a câmara do telemóvel.</p>
                <p>Pode fechar este popup — as fotos aparecem na galeria em tempo real (Reverb).</p>
            </div>
            <div class="flex justify-center items-center p-4 bg-white rounded-lg">
                <img
                    v-if="isDataUrl"
                    :src="qrCode"
                    alt="QR Code"
                    class="w-full max-w-xs"
                />
                <div v-else v-html="qrCode" class="w-full max-w-xs" style="padding-left: 10px"></div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    show: {
        type: Boolean,
        required: true
    },
    qrCode: {
        type: String,
        required: true
    }
})

const isDataUrl = computed(() =>
  typeof props.qrCode === 'string' && props.qrCode.startsWith('data:image/')
)

defineEmits(['close'])
</script>
