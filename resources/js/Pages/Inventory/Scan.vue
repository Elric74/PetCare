<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onUnmounted } from 'vue'
import { BrowserMultiFormatReader, DecodeHintType, BarcodeFormat } from '@zxing/library'
import { useToast } from 'vue-toastification'

const videoRef = ref(null)
const scanning = ref(false)
const result = ref(null)
const toast = useToast()
let codeReader = null
let scanningTimeout = null
let audioCtx = null
let lastScannedCode = null // Track last scanned code to prevent duplicates
let lastScanTime = 0 // Timestamp of last scan

// State management for workflow
const workflow = ref({
  stage: 'initial', // initial, found, ask_cnk, ask_manual_name, success
  gtin: null,
  raw: null,
  medicament: null,
  unknownId: null,
  cnkMode: false, // true if scanning CNK
})

const formData = ref({
  commercialName: '',
  dosage: '',
})

const beep = () => {
  try {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)()
    const duration = 0.08
    const oscillator = audioCtx.createOscillator()
    const gainNode = audioCtx.createGain()
    oscillator.type = 'square'
    oscillator.frequency.setValueAtTime(1200, audioCtx.currentTime)
    gainNode.gain.setValueAtTime(0.001, audioCtx.currentTime)
    gainNode.gain.exponentialRampToValueAtTime(0.2, audioCtx.currentTime + 0.01)
    gainNode.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + duration)
    oscillator.connect(gainNode)
    gainNode.connect(audioCtx.destination)
    oscillator.start()
    oscillator.stop(audioCtx.currentTime + duration)
  } catch (e) {
    // Ignore
  }
}

const startScan = async () => {
  try {
    scanning.value = true
    
    const hints = new Map()
    hints.set(DecodeHintType.POSSIBLE_FORMATS, [
      BarcodeFormat.DATA_MATRIX,
      BarcodeFormat.QR_CODE,
      BarcodeFormat.CODE_128,
      BarcodeFormat.EAN_13,
      BarcodeFormat.EAN_8
    ])
    hints.set(DecodeHintType.TRY_HARDER, true)
    
    codeReader = new BrowserMultiFormatReader(hints)
    
    const devices = await navigator.mediaDevices.enumerateDevices()
    const videoDevices = devices.filter(device => device.kind === 'videoinput')
    const selectedDeviceId = videoDevices.length > 0 ? videoDevices[0].deviceId : undefined
    
    codeReader.decodeFromVideoDevice(selectedDeviceId, videoRef.value, (resultCode, error) => {
      if (resultCode) {
        const scannedText = resultCode.getText()
        const now = Date.now()
        
        // Prevent duplicate scans within 2 seconds
        if (scannedText === lastScannedCode && (now - lastScanTime) < 2000) {
          console.log('Duplicate scan ignored:', scannedText)
          return
        }
        
        lastScannedCode = scannedText
        lastScanTime = now
        result.value = scannedText
        
        console.log('Decoded:', result.value)
        beep()
        stopScan()
        
        if (workflow.value.cnkMode) {
          submitCnk()
        } else {
          submitInitialScan()
        }
      }
    })
  } catch (e) {
    toast.error('Webcam unavailable: ' + e.message)
    scanning.value = false
  }
}

const stopScan = () => {
  scanning.value = false
  if (scanningTimeout) {
    clearTimeout(scanningTimeout)
    scanningTimeout = null
  }
  if (codeReader) {
    codeReader.reset()
    codeReader = null
  }
}

const submitInitialScan = async () => {
  if (!result.value) return
  try {
    const resp = await axios.post(route('inventaire-medoc.scan'), {
      raw: result.value,
      barcode: result.value
    })
    const data = resp.data
    
    if (data.status === 'found') {
      workflow.value.stage = 'found'
      workflow.value.gtin = data.gtin
      workflow.value.raw = result.value
      workflow.value.medicament = data.medicament
      toast.success(`Médicament trouvé: ${data.medicament.nom}`)
    } else if (data.status === 'not_found') {
      workflow.value.stage = 'ask_cnk'
      workflow.value.gtin = data.gtin
      workflow.value.raw = data.raw
      toast.info('Médicament non trouvé. Scannez le CNK ou un autre code.')
    }
    
    result.value = null
  } catch (e) {
    toast.error('Erreur: ' + (e.response?.data?.message || e.message))
    result.value = null
  }
}

const submitCnk = async () => {
  if (!result.value) return
  
  // Store the scanned code to prevent re-reading
  const scannedCode = result.value
  result.value = null
  
  // Validate CNK format (should be 7-8 digits)
  if (!/^\d{7,8}$/.test(scannedCode)) {
    toast.warning(`Code invalide: "${scannedCode}". Le CNK doit contenir 7-8 chiffres.`)
    setTimeout(() => {
      workflow.value.cnkMode = true
      startScan()
    }, 1000)
    return
  }
  
  try {
    const resp = await axios.post(route('inventaire-medoc.scan-cnk'), {
      raw: scannedCode,
      barcode: scannedCode,
      gtin: workflow.value.gtin
    })
    
    workflow.value.stage = 'success'
    workflow.value.medicament = resp.data.medicament
    toast.success(resp.data.message)
    
    setTimeout(resetWorkflow, 2000)
  } catch (e) {
    if (e.response?.status === 404) {
      toast.warning(`CNK "${scannedCode}" non trouvé. Scannez un autre code ou cliquez "Autre code".`)
      // Restart scanning for another attempt
      setTimeout(() => {
        workflow.value.cnkMode = true
        startScan()
      }, 1000)
    } else {
      toast.error('Erreur: ' + (e.response?.data?.message || e.message))
      workflow.value.cnkMode = false
    }
  }
}

const handleAskCnk = () => {
  workflow.value.cnkMode = true
  startScan()
}

const handleNonCnk = async () => {
  try {
    const resp = await axios.post(route('inventaire-medoc.save-unknown'), {
      gtin: workflow.value.gtin,
      raw: workflow.value.raw
    })
    
    workflow.value.unknownId = resp.data.unknown_id
    workflow.value.stage = 'ask_manual_name'
    formData.value.commercialName = ''
    formData.value.dosage = ''
    toast.info('Veuillez saisir la dénomination commerciale.')
  } catch (e) {
    toast.error('Erreur: ' + (e.response?.data?.message || e.message))
  }
}

const submitManualName = async () => {
  if (!formData.value.commercialName) {
    toast.warning('Veuillez saisir la dénomination commerciale.')
    return
  }
  
  try {
    const resp = await axios.post(route('inventaire-medoc.finalize-unknown'), {
      unknown_id: workflow.value.unknownId,
      gtin: workflow.value.gtin,
      commercial_name: formData.value.commercialName,
      dosage: formData.value.dosage || null
    })
    
    workflow.value.stage = 'success'
    toast.success('Inventaire enregistré')
    
    setTimeout(resetWorkflow, 2000)
  } catch (e) {
    toast.error('Erreur: ' + (e.response?.data?.message || e.message))
  }
}

const confirmFound = async () => {
  try {
    const resp = await axios.post(route('inventaire-medoc.confirm-found'), {
      barcode: workflow.value.gtin,
      raw: workflow.value.raw
    })
    
    workflow.value.stage = 'success'
    toast.success('Inventaire enregistré')
    
    setTimeout(resetWorkflow, 2000)
  } catch (e) {
    toast.error('Erreur: ' + (e.response?.data?.message || e.message))
  }
}

const resetWorkflow = () => {
  workflow.value = {
    stage: 'initial',
    gtin: null,
    raw: null,
    medicament: null,
    unknownId: null,
    cnkMode: false,
  }
  result.value = null
  formData.value = {
    commercialName: '',
    dosage: '',
  }
  stopScan()
}

onUnmounted(() => {
  stopScan()
  try {
    if (audioCtx) {
      audioCtx.close()
      audioCtx = null
    }
  } catch (e) {}
})
</script>

<template>
  <AppLayout title="Scan Médicament">
    <template #header>
      <h2 class="text-lg font-semibold leading-6 text-gray-900">Inventaire Médicaments — Scan</h2>
    </template>
    <div class="max-w-2xl mx-auto bg-white p-5 rounded-md space-y-4">
      <!-- Stage: Initial Scan -->
      <div v-if="workflow.stage === 'initial'" class="space-y-4">
        <div class="relative bg-black rounded-lg overflow-hidden" style="aspect-ratio: 4 / 3; width: 100%;">
          <video ref="videoRef" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover;" />
        </div>
        <div class="flex gap-2">
          <button @click="startScan" :disabled="scanning" class="flex-1 rounded-lg border border-green-700 bg-green-700 px-4 py-2 text-center text-sm font-medium text-white disabled:bg-green-300">
            Démarrer
          </button>
          <button @click="stopScan" :disabled="!scanning" class="flex-1 rounded-lg border border-gray-400 bg-gray-100 px-3 py-2 text-center text-sm font-medium text-gray-700 disabled:bg-gray-200">
            Arrêter
          </button>
        </div>
        <div v-if="scanning" class="text-xs text-gray-500">Scan en cours...</div>
        <div v-if="result" class="text-sm text-gray-700 break-all">Code scanné: {{ result }}</div>
      </div>
      
      <!-- Stage: Medicament Found -->
      <div v-if="workflow.stage === 'found'" class="space-y-4 text-center">
        <div class="bg-green-50 p-4 rounded-lg">
          <h3 class="text-lg font-semibold text-green-900">Médicament trouvé ✓</h3>
          <p class="text-green-800 mt-2 font-medium">{{ workflow.medicament.nom }}</p>
          <p class="text-green-700 text-sm">{{ workflow.medicament.dosage }}</p>
        </div>
        <button @click="confirmFound" class="w-full rounded-lg bg-green-600 text-white py-2 font-medium hover:bg-green-700">
          Confirmer et enregistrer
        </button>
        <button @click="resetWorkflow" class="w-full rounded-lg border border-gray-300 bg-white text-gray-700 py-2 font-medium hover:bg-gray-50">
          Nouveau scan
        </button>
      </div>
      
      <!-- Stage: Ask CNK -->
      <div v-if="workflow.stage === 'ask_cnk'" class="space-y-4">
        <div class="bg-yellow-50 p-4 rounded-lg text-center">
          <h3 class="text-lg font-semibold text-yellow-900">Médicament non trouvé</h3>
          <p class="text-yellow-800 mt-2">GTIN détecté: <span class="font-mono font-bold">{{ workflow.gtin }}</span></p>
          <p class="text-yellow-700 text-sm mt-1">Scannez le CNK pour lier le médicament, ou un autre code-barres.</p>
        </div>
        
        <div class="relative bg-black rounded-lg overflow-hidden" style="aspect-ratio: 4 / 3; width: 100%;">
          <video ref="videoRef" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover;" />
        </div>
        
        <div class="grid grid-cols-2 gap-2">
          <button @click="handleAskCnk" :disabled="scanning" class="rounded-lg bg-blue-600 text-white py-2 font-medium hover:bg-blue-700 disabled:bg-blue-300">
            {{ scanning ? 'En cours...' : 'Scanner CNK' }}
          </button>
          <button @click="stopScan" :disabled="!scanning" class="rounded-lg border border-gray-300 bg-white text-gray-700 py-2 font-medium hover:bg-gray-50">
            Arrêter
          </button>
        </div>
        
        <button @click="handleNonCnk" class="w-full rounded-lg border-2 border-orange-500 text-orange-600 py-2 font-medium hover:bg-orange-50">
          Autre code (pas CNK)
        </button>
        
        <button @click="resetWorkflow" class="w-full rounded-lg border border-gray-300 bg-white text-gray-700 py-2 font-medium hover:bg-gray-50">
          Annuler
        </button>
      </div>
      
      <!-- Stage: Ask Manual Name -->
      <div v-if="workflow.stage === 'ask_manual_name'" class="space-y-4">
        <div class="bg-blue-50 p-4 rounded-lg">
          <h3 class="text-lg font-semibold text-blue-900">Saisie manuelle</h3>
          <p class="text-blue-800 mt-2 text-sm">GTIN: <span class="font-mono font-bold">{{ workflow.gtin }}</span></p>
          <p class="text-blue-700 text-sm mt-1">Veuillez saisir les informations du médicament.</p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Dénomination commerciale *</label>
          <input
            v-model="formData.commercialName"
            type="text"
            placeholder="Ex: Ibuprofen 400mg"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Dosage (optionnel)</label>
          <input
            v-model="formData.dosage"
            type="text"
            placeholder="Ex: 400mg"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        
        <button @click="submitManualName" class="w-full rounded-lg bg-blue-600 text-white py-2 font-medium hover:bg-blue-700">
          Enregistrer
        </button>
        
        <button @click="resetWorkflow" class="w-full rounded-lg border border-gray-300 bg-white text-gray-700 py-2 font-medium hover:bg-gray-50">
          Annuler
        </button>
      </div>
      
      <!-- Stage: Success -->
      <div v-if="workflow.stage === 'success'" class="space-y-4 text-center">
        <div class="bg-green-50 p-8 rounded-lg">
          <div class="text-5xl mb-2">✓</div>
          <h3 class="text-lg font-semibold text-green-900">Enregistrement réussi !</h3>
          <p class="text-green-800 mt-2 font-medium" v-if="workflow.medicament">
            {{ workflow.medicament.nom }}
          </p>
        </div>
        <p class="text-sm text-gray-600">Retour au scan dans quelques secondes...</p>
      </div>
    </div>
  </AppLayout>
</template>

