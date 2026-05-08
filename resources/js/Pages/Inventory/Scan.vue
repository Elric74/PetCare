<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { useToast } from 'vue-toastification'

const scanning = ref(false)
const result = ref(null)
const toast = useToast()
let audioCtx = null
let lastScannedCode = null // Track last scanned code to prevent duplicates
let lastScanTime = 0 // Timestamp of last scan

// Selected year for inventory
const anneeInventaire = ref(new Date().getFullYear())
const anneesDisponibles = [2025, 2026, 2027, 2028, 2029, 2030]

// Multiplier system
const multiplier = ref(1)
const multiplierCodes = {
  'MULT01': 1, 'MULT02': 2, 'MULT03': 3, 'MULT04': 4, 'MULT05': 5,
  'MULT06': 6, 'MULT07': 7, 'MULT08': 8, 'MULT09': 9, 'MULT10': 10,
  'MULT11': 11, 'MULT12': 12, 'MULT13': 13, 'MULT14': 14, 'MULT15': 15
}
const showMultiplierCodes = ref(false)

// Barcode scanner buffer
let barcodeBuffer = ''
let barcodeTimeout = null

// State management for workflow
const workflow = ref({
  stage: 'initial', // initial, found, ask_cnk, ask_datamatrix, ask_manual_name, ask_quantity, success
  gtin: null,
  raw: null,
  medicament: null,
  unknownId: null,
  cnkMode: false, // true if scanning CNK
  scannedCode: null, // Code scanné initialement (CNK ou autre)
})

// Quantity input for partial boxes
const partialQuantity = ref('')

// Generate barcodes for quantity popup
const generateQuantityBarcodes = async () => {
  await new Promise(resolve => setTimeout(resolve, 100))
  const JsBarcode = (await import('jsbarcode')).default
  
  // Generate FULLBOX barcode
  const fullboxSvg = document.querySelector('svg[data-code="FULLBOX"]')
  if (fullboxSvg) {
    try {
      JsBarcode(fullboxSvg, 'FULLBOX', {
        format: 'CODE128',
        width: 2,
        height: 50,
        displayValue: false,
        margin: 5
      })
    } catch (e) {
      console.error('Error generating FULLBOX barcode', e)
    }
  }
}

const formData = ref({
  commercialName: '',
  forme: '',
  dosage: '',
  manualCnk: '',
  barcode1: '',
  barcode2: '',
  barcode3: '',
  barcode4: '',
  barcode5: '',
  barcode6: '',
  lot_number: '',
  expiry_date: '',
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

// Handle barcode scanner input (Netum Bluetooth scanner)
const handleKeyEvent = (event) => {
  // Only process on keydown to avoid duplicates
  if (event.type !== 'keydown') return

  // LOG EVERYTHING for debugging
  console.log('🔍 Key event:', {
    type: event.type,
    key: event.key,
    code: event.code,
    keyCode: event.keyCode,
    char: event.char,
    target: event.target.tagName,
    buffer: barcodeBuffer,
    timestamp: new Date().toISOString()
  })

  // Ignore if user is typing in an input field
  if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') {
    console.log('⚠️ Ignored: User typing in input field')
    return
  }

  // Clear timeout on each key press
  if (barcodeTimeout) {
    clearTimeout(barcodeTimeout)
  }

  // Enter key signals end of barcode scan
  if (event.key === 'Enter' || event.keyCode === 13) {
    console.log('✅ Enter detected! Buffer content:', barcodeBuffer)
    event.preventDefault() // Prevent default Enter behavior
    if (barcodeBuffer.trim().length > 0) {
      console.log('📦 Processing scan:', barcodeBuffer.trim())
      processScan(barcodeBuffer.trim())
      barcodeBuffer = ''
    } else {
      console.log('⚠️ Buffer is empty, nothing to process')
    }
    return
  }

  // Ignore special keys
  if (event.key.length > 1 && event.key !== 'Enter') {
    console.log('⚠️ Special key ignored:', event.key)
    return
  }

  // Add character to buffer
  const char = event.key
  barcodeBuffer += char
  console.log('➕ Added to buffer. Current buffer:', barcodeBuffer)

  // Auto-clear buffer after 150ms of no input
  barcodeTimeout = setTimeout(() => {
    console.log('⏰ Buffer timeout - clearing buffer:', barcodeBuffer)
    barcodeBuffer = ''
  }, 150)
}

const processScan = (scannedText) => {
  console.log('🎯 Processing scan:', scannedText, 'CNK Mode:', workflow.value.cnkMode, 'Stage:', workflow.value.stage)
  const now = Date.now()
  
  // Check if it's the FULLBOX code (for full box validation)
  if (scannedText === 'FULLBOX' && workflow.value.stage === 'ask_quantity') {
    submitFullBox()
    return
  }
  
  // Check if it's the CONFIRM code (for quantity validation)
  if (scannedText === 'CONFIRM' && workflow.value.stage === 'ask_quantity') {
    submitPartialQuantity()
    return
  }
  
  // Check if it's a multiplier code
  if (multiplierCodes[scannedText]) {
    multiplier.value = multiplierCodes[scannedText]
    toast.info(`Multiplicateur activé: ×${multiplier.value}`, { duration: 3000 })
    beep()
    console.log('🔢 Multiplier set to:', multiplier.value)
    return
  }
  
  // Prevent duplicate scans within 2 seconds
  if (scannedText === lastScannedCode && (now - lastScanTime) < 2000) {
    console.log('⛔ Duplicate scan ignored:', scannedText)
    return
  }
  
  lastScannedCode = scannedText
  lastScanTime = now
  result.value = scannedText
  
  console.log('✅ Scan accepted:', result.value, 'Multiplier:', multiplier.value)
  beep()
  
  // Check workflow stage
  if (workflow.value.stage === 'ask_datamatrix') {
    console.log('📦 DataMatrix mode - submitting DataMatrix')
    submitDataMatrix()
  } else if (workflow.value.cnkMode || workflow.value.stage === 'ask_cnk') {
    console.log('🔵 CNK mode active - submitting CNK')
    submitCnk()
  } else {
    console.log('🟢 Normal mode - submitting initial scan')
    submitInitialScan()
  }
}

const startScan = () => {
  scanning.value = true
  barcodeBuffer = ''
  console.log('🟢 Scanner activated. CNK Mode:', workflow.value.cnkMode, 'Stage:', workflow.value.stage)
  toast.info('Scanner prêt. Scannez un code-barres.')
}

const stopScan = () => {
  scanning.value = false
  barcodeBuffer = ''
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
      // Check if unite is not 1 - ask for quantity
      if (data.medicament.unite && data.medicament.unite > 1) {
        workflow.value.stage = 'ask_quantity'
        workflow.value.gtin = data.gtin
        workflow.value.raw = result.value
        workflow.value.medicament = data.medicament
        workflow.value.barcode_type = data.barcode_type
        partialQuantity.value = ''
        toast.info(`${data.medicament.nom} - Boîte complète ou quantité partielle ?`)
        result.value = null
        return
      }
      
      // Enregistrer automatiquement sans confirmation
      workflow.value.gtin = data.gtin
      workflow.value.raw = result.value
      workflow.value.medicament = data.medicament
      
      // Save N times according to multiplier
      const mult = multiplier.value
      for (let i = 0; i < mult; i++) {
        await axios.post(route('inventaire-medoc.confirm-found'), {
          raw: result.value,
          barcode: data.gtin,
          barcode_type: data.barcode_type,
          medicament_id: data.medicament.id,
          annee_inventaire: anneeInventaire.value
        })
      }
      
      // Afficher le succès brièvement
      workflow.value.stage = 'success'
      const message = mult > 1 
        ? `✓ ${data.medicament.nom} × ${mult}` 
        : `✓ ${data.medicament.nom}`
      toast.success(message)
      
      // Reset multiplier
      multiplier.value = 1
      
      // Réinitialiser rapidement pour scanner le suivant
      setTimeout(resetWorkflow, 1000)
    } else if (data.status === 'found_external') {
      // Médicament trouvé via API externe - demander confirmation et saisie lot/expiry
      workflow.value.stage = 'confirm_external'
      workflow.value.gtin = data.gtin
      workflow.value.raw = result.value
      workflow.value.medicament = data.medicament
      workflow.value.barcode_type = data.barcode_type
      workflow.value.source = data.source
      
      // Initialize form fields for lot and expiry
      formData.value.lot_number = ''
      formData.value.expiry_date = ''
      
      toast.info(`✓ ${data.medicament.nom} (trouvé via ${data.source})`, { duration: 2000 })
      result.value = null
    } else if (data.status === 'no_datamatrix') {
      // Médicament trouvé mais n'a pas de DataMatrix - aller directement à l'entrée manuelle
      workflow.value.stage = 'manual_datamatrix_entry'
      workflow.value.medicament = data.medicament
      workflow.value.scannedCode = data.scanned_code
      workflow.value.barcode_type = data.barcode_type
      
      // Initialize form fields for manual entry
      formData.value.lot_number = ''
      formData.value.expiry_date = ''
      
      toast.info(`${data.medicament.nom} - ce produit n'a pas de DataMatrix, saisie manuelle requise.`)
      result.value = null
    } else if (data.status === 'ask_datamatrix') {
      // Médicament trouvé mais code scanné n'est pas un DataMatrix
      // Demander de scanner le DataMatrix pour avoir lot/expiry/serial
      workflow.value.stage = 'ask_datamatrix'
      workflow.value.medicament = data.medicament
      workflow.value.scannedCode = data.scanned_code
      toast.warning(`${data.medicament.nom} trouvé ! Scannez maintenant le DataMatrix.`)
      
      // Keep scanner active and ready for DataMatrix
      result.value = null
      console.log('📦 Waiting for DataMatrix scan. Scanner remains active.')
    } else if (data.status === 'not_found') {
      // Show confirmation popup before asking for CNK
      workflow.value.stage = 'confirm_not_found'
      workflow.value.gtin = data.gtin
      workflow.value.raw = data.raw
      workflow.value.scannedCode = result.value
      
      // Don't show toast here, will show in popup
      result.value = null
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
  let scannedCode = result.value
  result.value = null
  
  console.log('🔵 CNK scanned:', scannedCode, 'Length:', scannedCode.length)
  
  // Validate CNK format - must contain at least 7 digits, truncate if longer
  if (!/^\d+$/.test(scannedCode)) {
    toast.warning(`Code invalide: "${scannedCode}". Le CNK doit contenir uniquement des chiffres.`)
    setTimeout(() => {
      workflow.value.cnkMode = true
      startScan()
    }, 1500)
    return
  }
  
  if (scannedCode.length < 7) {
    toast.warning(`Code invalide: "${scannedCode}". Le CNK doit contenir au moins 7 chiffres.`)
    setTimeout(() => {
      workflow.value.cnkMode = true
      startScan()
    }, 1500)
    return
  }
  
  // Truncate to first 7 digits if longer
  if (scannedCode.length > 7) {
    const originalCode = scannedCode
    scannedCode = scannedCode.substring(0, 7)
    console.log(`✂️ CNK truncated from ${originalCode} to ${scannedCode}`)
    toast.info(`CNK tronqué: ${originalCode} → ${scannedCode}`)
  }
  
  console.log('✅ CNK valid:', scannedCode)
  
  // Link CNK with GTIN directly
  try {
    const resp = await axios.post(route('inventaire-medoc.scan-cnk'), {
      raw: workflow.value.raw,  // DataMatrix original complet
      barcode: scannedCode,     // CNK scanné
      gtin: workflow.value.gtin,
      annee_inventaire: anneeInventaire.value
    })
    
    workflow.value.stage = 'success'
    workflow.value.medicament = resp.data.medicament
    toast.success(resp.data.message)
    
    setTimeout(resetWorkflow, 2000)
  } catch (e) {
    if (e.response?.status === 404) {
      toast.error(`CNK "${scannedCode}" non trouvé dans la base de données.`)
      setTimeout(() => {
        workflow.value.cnkMode = true
        startScan()
      }, 1500)
    } else {
      toast.error('Erreur: ' + (e.response?.data?.message || e.message))
    }
  }
}

const submitDataMatrix = async () => {
  if (!result.value) return
  
  const scannedCode = result.value.trim()
  result.value = null  // Clear immediately to prevent re-processing
  
  console.log('📦 submitDataMatrix called', {
    raw: scannedCode,
    medicament_id: workflow.value.medicament?.id,
    initial_code: workflow.value.scannedCode
  })
  
  toast.info(`DataMatrix scanné: ${scannedCode.substring(0, 30)}...`)
  
  try {
    const resp = await axios.post(route('inventaire-medoc.confirm-found'), {
      medicament_id: workflow.value.medicament.id,
      raw: scannedCode,  // Le DataMatrix complet scanné
      barcode: workflow.value.scannedCode,  // Le code CNK/barcode initial qui a déclenché ask_datamatrix
      annee_inventaire: anneeInventaire.value
    })
    
    workflow.value.stage = 'success'
    workflow.value.medicament = resp.data.medicament
    toast.success(resp.data.message)
    
    setTimeout(resetWorkflow, 2000)
  } catch (e) {
    toast.error('Erreur DataMatrix: ' + (e.response?.data?.message || e.message))
    setTimeout(resetWorkflow, 1500)
  }
}

const confirmNotFound = () => {
  // User confirmed medication not found, proceed to ask CNK
  workflow.value.stage = 'ask_cnk'
  toast.info('Scannez le CNK ou un autre code-barres.')
}

const cancelNotFound = () => {
  // User cancelled, reset workflow
  toast.info('Scan annulé.')
  resetWorkflow()
}

const createManualMedication = () => {
  // User wants to create medication manually
  workflow.value.stage = 'create_manual_medication'
  
  // Pre-fill GTIN if available
  formData.value.barcode1 = workflow.value.gtin || ''
  formData.value.commercialName = ''
  formData.value.dosage = ''
  formData.value.forme = ''
  formData.value.lot_number = ''
  formData.value.expiry_date = ''
  
  toast.info('Créez la fiche médicament avec les informations complètes.')
}

const openManualDataMatrixEntry = () => {
  // User wants to enter lot/expiry manually (no DataMatrix available)
  workflow.value.stage = 'manual_datamatrix_entry'
  formData.value.lot_number = ''
  formData.value.expiry_date = ''
  toast.info('Saisissez manuellement le lot et la date d\'expiration.')
}

const submitManualDataMatrixEntry = async () => {
  // Validate input
  if (!formData.value.lot_number || !formData.value.expiry_date) {
    toast.error('Veuillez renseigner le lot et la date d\'expiration.')
    return
  }
  
  try {
    const resp = await axios.post(route('inventaire-medoc.confirm-found'), {
      medicament_id: workflow.value.medicament.id,
      raw: workflow.value.raw || workflow.value.scannedCode,
      barcode: workflow.value.scannedCode,
      barcode_type: 'manual_entry',
      lot_number: formData.value.lot_number,
      expiry_date: formData.value.expiry_date,
      annee_inventaire: anneeInventaire.value
    })
    
    workflow.value.stage = 'success'
    toast.success(`✓ ${workflow.value.medicament.nom} enregistré`)
    
    setTimeout(resetWorkflow, 1500)
  } catch (e) {
    toast.error('Erreur: ' + (e.response?.data?.message || e.message))
  }
}

const confirmExternal = async () => {
  // Validate input
  if (!formData.value.lot_number || !formData.value.expiry_date) {
    toast.error('Veuillez renseigner le lot et la date d\'expiration.')
    return
  }
  
  try {
    const resp = await axios.post(route('inventaire-medoc.confirm-found'), {
      raw: workflow.value.raw,
      barcode: workflow.value.gtin,
      barcode_type: workflow.value.barcode_type,
      medicament_id: null, // Pas d'ID local pour médicament externe
      commercial_name: workflow.value.medicament.nom,
      dosage: workflow.value.medicament.dosage,
      lot_number: formData.value.lot_number,
      expiry_date: formData.value.expiry_date,
      annee_inventaire: anneeInventaire.value
    })
    
    workflow.value.stage = 'success'
    toast.success(`✓ ${workflow.value.medicament.nom} enregistré (source: ${workflow.value.source})`)
    
    setTimeout(resetWorkflow, 1500)
  } catch (e) {
    toast.error('Erreur: ' + (e.response?.data?.message || e.message))
  }
}

const cancelExternal = () => {
  // User cancelled external medication
  toast.info('Enregistrement annulé.')
  resetWorkflow()
}

const submitManualMedicationCreate = async () => {
  // Validate required fields
  if (!formData.value.commercialName || !formData.value.lot_number || !formData.value.expiry_date) {
    toast.error('Veuillez renseigner au minimum le nom commercial, le lot et la date d\'expiration.')
    return
  }
  
  try {
    // Direct save to inventory with manual data
    const resp = await axios.post(route('inventaire-medoc.confirm-found'), {
      raw: workflow.value.raw || formData.value.barcode1,
      barcode: workflow.value.gtin || formData.value.barcode1,
      barcode_type: 'manual',
      medicament_id: null,
      commercial_name: formData.value.commercialName,
      dosage: formData.value.dosage,
      lot_number: formData.value.lot_number,
      expiry_date: formData.value.expiry_date,
      annee_inventaire: anneeInventaire.value
    })
    
    workflow.value.stage = 'success'
    toast.success(`✓ ${formData.value.commercialName} créé et enregistré`)
    
    setTimeout(resetWorkflow, 1500)
  } catch (e) {
    toast.error('Erreur: ' + (e.response?.data?.message || e.message))
  }
}

const handleAskCnk = () => {
  workflow.value.cnkMode = true
  formData.value.manualCnk = ''
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
    formData.value.forme = ''
    formData.value.dosage = ''
    toast.info('Veuillez saisir la dénomination commerciale.')
  } catch (e) {
    toast.error('Erreur: ' + (e.response?.data?.message || e.message))
  }
}

const handleManualAdd = () => {
  workflow.value.stage = 'ask_manual_name'
  formData.value.commercialName = ''
  formData.value.forme = ''
  formData.value.barcode2 = ''
  formData.value.barcode3 = ''
  formData.value.barcode4 = ''
  formData.value.barcode5 = ''
  formData.value.barcode6 = ''
  toast.info('Créez une fiche médicament pour ce GTIN.')
}

const submitManualMedicament = async () => {
  if (!formData.value.commercialName) {
    toast.warning('Veuillez saisir la dénomination commerciale.')
    return
  }
  
  try {
    // Créer le médicament dans la table medicaments
    const medResp = await axios.post(route('medicaments.store'), {
      barcode1: workflow.value.gtin,
      barcode2: formData.value.barcode2 || null,
      barcode3: formData.value.barcode3 || null,
      barcode4: formData.value.barcode4 || null,
      barcode5: formData.value.barcode5 || null,
      barcode6: formData.value.barcode6 || null,
      nom: formData.value.commercialName,
      forme_pharmaceutique: formData.value.forme || null,
    })
    
    // Enregistrer dans l'inventaire
    await axios.post(route('inventaire-medoc.confirm-found'), {
      raw: workflow.value.raw,
      barcode: workflow.value.gtin,
      barcode_type: 'barcode1',
      medicament_id: medResp.data.id,
      annee_inventaire: anneeInventaire.value
    })
    
    workflow.value.stage = 'success'
    workflow.value.medicament = { nom: formData.value.commercialName }
    toast.success('Médicament créé et inventaire enregistré')
    
    setTimeout(resetWorkflow, 2000)
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
  partialQuantity.value = ''
  formData.value = {
    commercialName: '',
    forme: '',
    dosage: '',
    manualCnk: '',
    barcode2: '',
    barcode3: '',
    barcode4: '',
    barcode5: '',
    barcode6: '',
  }
  // Relancer le scan automatiquement
  startScan()
}

// Submit full box (when unite > 1)
const submitFullBox = async () => {
  try {
    const mult = multiplier.value
    for (let i = 0; i < mult; i++) {
      await axios.post(route('inventaire-medoc.confirm-found'), {
        raw: workflow.value.raw,
        barcode: workflow.value.gtin,
        barcode_type: workflow.value.barcode_type,
        medicament_id: workflow.value.medicament.id,
        annee_inventaire: anneeInventaire.value,
        quantity: workflow.value.medicament.unite // Full box
      })
    }
    
    workflow.value.stage = 'success'
    const message = mult > 1 
      ? `✓ ${workflow.value.medicament.nom} (${workflow.value.medicament.unite} unités) × ${mult}` 
      : `✓ ${workflow.value.medicament.nom} (${workflow.value.medicament.unite} unités)`
    toast.success(message)
    
    multiplier.value = 1
    setTimeout(resetWorkflow, 1000)
  } catch (error) {
    toast.error('Erreur lors de l\'enregistrement')
    console.error(error)
  }
}

// Submit partial quantity (when unite > 1)
const submitPartialQuantity = async () => {
  const qty = parseInt(partialQuantity.value)
  if (!qty || qty < 1 || qty > workflow.value.medicament.unite) {
    toast.error(`Veuillez entrer un nombre entre 1 et ${workflow.value.medicament.unite}`)
    return
  }
  
  try {
    const mult = multiplier.value
    for (let i = 0; i < mult; i++) {
      await axios.post(route('inventaire-medoc.confirm-found'), {
        raw: workflow.value.raw,
        barcode: workflow.value.gtin,
        barcode_type: workflow.value.barcode_type,
        medicament_id: workflow.value.medicament.id,
        annee_inventaire: anneeInventaire.value,
        quantity: qty
      })
    }
    
    workflow.value.stage = 'success'
    const message = mult > 1 
      ? `✓ ${workflow.value.medicament.nom} (${qty} unités) × ${mult}` 
      : `✓ ${workflow.value.medicament.nom} (${qty} unités)`
    toast.success(message)
    
    multiplier.value = 1
    setTimeout(resetWorkflow, 1000)
  } catch (error) {
    toast.error('Erreur lors de l\'enregistrement')
    console.error(error)
  }
}

// Generate barcodes for multiplier codes
const generateBarcodes = async () => {
  // Wait for next tick to ensure DOM is ready
  await new Promise(resolve => setTimeout(resolve, 100))
  
  // Import JsBarcode dynamically
  const JsBarcode = (await import('jsbarcode')).default
  
  // Generate Code128 barcodes for each multiplier code
  Object.keys(multiplierCodes).forEach((code) => {
    const svg = document.querySelector(`svg[data-code="${code}"]`)
    if (svg) {
      try {
        JsBarcode(svg, code, {
          format: 'CODE128',
          width: 1.5,
          height: 40,
          displayValue: false,
          margin: 2
        })
      } catch (e) {
        console.error('Error generating barcode for', code, e)
      }
    }
  })
}

// Lifecycle hooks
onMounted(() => {
  console.log('🚀 Scanner page mounted - Adding keyboard listeners')
  window.addEventListener('keydown', handleKeyEvent)
  window.addEventListener('keypress', handleKeyEvent)
  window.addEventListener('keyup', handleKeyEvent)
  console.log('✅ Keyboard listeners added (keydown, keypress, keyup)')
  startScan() // Auto-start scanning when page loads
  
  // Generate barcodes when codes are shown
  const unwatch = watch(showMultiplierCodes, (show) => {
    if (show) {
      generateBarcodes()
    }
  })
  
  // Generate FULLBOX barcode when ask_quantity stage is reached
  const unwatchStage = watch(() => workflow.value.stage, (newStage) => {
    if (newStage === 'ask_quantity') {
      generateQuantityBarcodes()
    }
  })
})

onUnmounted(() => {
  console.log('👋 Scanner page unmounting - Removing keyboard listeners')
  window.removeEventListener('keydown', handleKeyEvent)
  window.removeEventListener('keypress', handleKeyEvent)
  window.removeEventListener('keyup', handleKeyEvent)
  stopScan()
  if (barcodeTimeout) {
    clearTimeout(barcodeTimeout)
  }
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
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold leading-6 text-gray-900">Inventaire Médicaments — Scan</h2>
        <div class="flex items-center gap-2">
          <label for="annee" class="text-sm font-medium text-gray-700">Année:</label>
          <select 
            id="annee"
            v-model="anneeInventaire" 
            class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
          >
            <option v-for="annee in anneesDisponibles" :key="annee" :value="annee">
              {{ annee }}
            </option>
          </select>
        </div>
      </div>
    </template>
    
    <!-- Multiplier Display & Controls (Full Width) -->
    <div v-if="workflow.stage === 'initial'" class="bg-gradient-to-r from-purple-50 to-pink-50 border-2 border-purple-300 rounded-lg p-4 mb-4">
      <div class="flex items-center justify-between max-w-2xl mx-auto">
        <div class="flex items-center gap-3">
          <div class="text-3xl">🔢</div>
          <div>
            <div class="text-sm font-medium text-purple-900">Multiplicateur actif</div>
            <div class="text-2xl font-bold text-purple-700">× {{ multiplier }}</div>
          </div>
        </div>
        <button 
          @click="showMultiplierCodes = !showMultiplierCodes"
          class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition"
        >
          {{ showMultiplierCodes ? 'Masquer codes' : 'Afficher codes' }}
        </button>
      </div>
      
      <!-- Multiplier Codes Grid (Full Width) -->
      <div v-if="showMultiplierCodes" class="mt-4 grid grid-cols-5 md:grid-cols-8 lg:grid-cols-10 xl:grid-cols-15 gap-2 px-4">
        <div 
          v-for="(value, code) in multiplierCodes" 
          :key="code"
          class="bg-white border border-purple-300 rounded p-1.5 text-center hover:bg-purple-50 transition flex flex-col items-center"
        >
          <svg :data-code="code" width="60" height="25" class="mb-0.5"></svg>
          <div class="text-xs font-bold text-purple-900">×{{ value }}</div>
        </div>
      </div>
      
      <div v-if="showMultiplierCodes" class="mt-3 text-xs text-purple-700 text-center">
        💡 Scannez un code multiplicateur avant de scanner un médicament pour l'ajouter plusieurs fois
      </div>
    </div>
    
    <div class="max-w-2xl mx-auto bg-white p-5 rounded-md space-y-4">
      
      <!-- Stage: Initial Scan -->
      <div v-if="workflow.stage === 'initial'" class="space-y-4">
        <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-8 text-center">
          <div class="text-6xl mb-4">📱</div>
          <h3 class="text-xl font-semibold text-blue-900 mb-2">Scanner Bluetooth Actif</h3>
          <p class="text-blue-700">Utilisez votre scanner Netum pour scanner un code-barres</p>
          <div v-if="scanning" class="mt-4">
            <span class="inline-block px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
              ✓ Prêt à scanner
            </span>
          </div>
        </div>
        
        <div v-if="result" class="bg-gray-100 p-4 rounded-lg">
          <p class="text-xs text-gray-500 mb-1">Code scanné:</p>
          <p class="text-sm text-gray-700 break-all font-mono">{{ result }}</p>
        </div>

        <div class="flex gap-2">
          <button @click="startScan" :disabled="scanning" class="flex-1 rounded-lg border border-green-700 bg-green-700 px-4 py-2 text-center text-sm font-medium text-white disabled:bg-green-300">
            {{ scanning ? 'Scanner actif' : 'Activer le scanner' }}
          </button>
          <button @click="stopScan" :disabled="!scanning" class="flex-1 rounded-lg border border-gray-400 bg-gray-100 px-3 py-2 text-center text-sm font-medium text-gray-700 disabled:bg-gray-200">
            Désactiver
          </button>
        </div>
      </div>
      
      <!-- Stage: Ask Quantity -->
      <div v-if="workflow.stage === 'ask_quantity'" class="space-y-4">
        <div class="bg-blue-50 border-2 border-blue-300 p-6 rounded-lg">
          <div class="text-5xl text-center mb-4">📦</div>
          <h3 class="text-xl font-semibold text-blue-900 text-center mb-2">{{ workflow.medicament.nom }}</h3>
          <p class="text-blue-700 text-center text-sm mb-4">Unités par boîte: {{ workflow.medicament.unite }}</p>
          
          <div class="space-y-4">
            <!-- Full Box Button with Barcode -->
            <div class="bg-white border-2 border-green-500 rounded-lg p-4">
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                  <span class="text-2xl">✓</span>
                  <div>
                    <div class="font-semibold text-green-800">Boîte complète</div>
                    <div class="text-sm text-green-600">{{ workflow.medicament.unite }} unités</div>
                  </div>
                </div>
                <button 
                  @click="submitFullBox" 
                  class="px-6 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 transition text-sm"
                >
                  Valider
                </button>
              </div>
              <!-- Barcode for Full Box -->
              <div class="bg-gray-50 border border-gray-200 rounded p-3 text-center">
                <svg data-code="FULLBOX" class="mx-auto mb-1"></svg>
                <p class="text-xs text-gray-600 font-medium">Scannez ce code pour valider</p>
              </div>
            </div>
            
            <!-- Partial Quantity Section -->
            <div class="bg-white border-2 border-yellow-400 rounded-lg p-4">
              <div class="font-semibold text-yellow-800 mb-3 flex items-center gap-2">
                <span>⚠️</span>
                <span>Boîte entamée</span>
              </div>
              <div class="space-y-2">
                <input 
                  v-model="partialQuantity" 
                  type="number" 
                  :min="1" 
                  :max="workflow.medicament.unite"
                  placeholder="Nombre d'unités restantes"
                  class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm"
                  @keypress.enter="submitPartialQuantity"
                />
                
                <button 
                  @click="submitPartialQuantity" 
                  class="w-full bg-yellow-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-yellow-700 transition"
                >
                  Valider la quantité partielle
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <button @click="resetWorkflow" class="w-full rounded-lg border border-gray-300 bg-white text-gray-700 py-2 font-medium hover:bg-gray-50">
          Annuler
        </button>
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
      
      <!-- Stage: Confirm Not Found -->
      <div v-if="workflow.stage === 'confirm_not_found'" class="space-y-4">
        <div class="bg-red-50 border-2 border-red-300 p-6 rounded-lg text-center">
          <div class="text-6xl mb-4">❌</div>
          <h3 class="text-xl font-bold text-red-900 mb-2">Médicament non trouvé</h3>
          <p class="text-red-800 font-medium mb-2">GTIN: <span class="font-mono text-lg">{{ workflow.gtin }}</span></p>
          <p class="text-red-700 text-sm mb-4">Ce code n'existe pas dans la base de données locale ni dans les bases externes.</p>
          
          <div class="bg-white border border-red-200 rounded-lg p-4 mt-4">
            <p class="text-gray-700 text-sm mb-2">Code scanné :</p>
            <p class="text-gray-900 font-mono text-xs break-all">{{ workflow.scannedCode }}</p>
          </div>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h4 class="font-semibold text-blue-900 mb-2">Que souhaitez-vous faire ?</h4>
          <p class="text-blue-800 text-sm">Choisissez l'une des options suivantes :</p>
        </div>
        
        <div class="space-y-3">
          <button @click="confirmNotFound" class="w-full rounded-lg bg-blue-600 text-white py-3 px-4 font-medium hover:bg-blue-700 shadow-md flex items-center justify-center gap-2">
            <span class="text-xl">📱</span>
            <span>Scanner le CNK</span>
          </button>
          
          <button @click="createManualMedication" class="w-full rounded-lg bg-purple-600 text-white py-3 px-4 font-medium hover:bg-purple-700 shadow-md flex items-center justify-center gap-2">
            <span class="text-xl">✏️</span>
            <span>Créer le médicament manuellement</span>
          </button>
          
          <button @click="cancelNotFound" class="w-full rounded-lg border-2 border-gray-300 bg-white text-gray-700 py-3 px-4 font-medium hover:bg-gray-50 flex items-center justify-center gap-2">
            <span class="text-xl">✕</span>
            <span>Annuler</span>
          </button>
        </div>
      </div>

      <!-- Stage: Confirm External (API) -->
      <div v-if="workflow.stage === 'confirm_external'" class="space-y-4">
        <div class="bg-green-50 border-2 border-green-300 p-6 rounded-lg">
          <div class="text-center mb-4">
            <div class="text-6xl mb-3">🌍</div>
            <h3 class="text-xl font-bold text-green-900 mb-2">Médicament trouvé (API externe)</h3>
            <p class="text-green-800 text-sm mb-1">Source: <span class="font-semibold">{{ workflow.source }}</span></p>
          </div>
          
          <div class="bg-white border border-green-200 rounded-lg p-4 space-y-2">
            <div>
              <p class="text-gray-600 text-xs uppercase font-semibold">Nom commercial</p>
              <p class="text-gray-900 font-bold text-lg">{{ workflow.medicament?.nom }}</p>
            </div>
            <div v-if="workflow.medicament?.dosage">
              <p class="text-gray-600 text-xs uppercase font-semibold">Dosage</p>
              <p class="text-gray-900">{{ workflow.medicament.dosage }}</p>
            </div>
            <div>
              <p class="text-gray-600 text-xs uppercase font-semibold">GTIN</p>
              <p class="text-gray-900 font-mono text-sm">{{ workflow.gtin }}</p>
            </div>
          </div>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h4 class="font-semibold text-blue-900 mb-3">📝 Informations complémentaires requises</h4>
          <p class="text-blue-800 text-sm mb-4">Veuillez saisir manuellement le numéro de lot et la date d'expiration.</p>
          
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Numéro de lot *</label>
              <input 
                v-model="formData.lot_number" 
                type="text" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Ex: 24111170"
                @keyup.enter="confirmExternal"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date d'expiration *</label>
              <input 
                v-model="formData.expiry_date" 
                type="date" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @keyup.enter="confirmExternal"
              />
            </div>
          </div>
        </div>
        
        <div class="grid grid-cols-2 gap-3">
          <button 
            @click="confirmExternal" 
            class="rounded-lg bg-green-600 text-white py-3 px-4 font-medium hover:bg-green-700 shadow-md disabled:bg-gray-300 disabled:cursor-not-allowed"
            :disabled="!formData.lot_number || !formData.expiry_date"
          >
            ✓ Confirmer et enregistrer
          </button>
          <button @click="cancelExternal" class="rounded-lg border-2 border-gray-300 bg-white text-gray-700 py-3 px-4 font-medium hover:bg-gray-50">
            ✕ Annuler
          </button>
        </div>
      </div>

      <!-- Stage: Create Manual Medication -->
      <div v-if="workflow.stage === 'create_manual_medication'" class="space-y-4">
        <div class="bg-purple-50 border-2 border-purple-300 p-6 rounded-lg text-center">
          <div class="text-6xl mb-3">✏️</div>
          <h3 class="text-xl font-bold text-purple-900 mb-2">Création manuelle du médicament</h3>
          <p class="text-purple-800 text-sm">Renseignez les informations du médicament pour l'ajouter à l'inventaire</p>
        </div>
        
        <div class="bg-white border border-gray-200 rounded-lg p-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">GTIN / Code-barres *</label>
            <input 
              v-model="formData.barcode1" 
              type="text" 
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-gray-50"
              placeholder="Ex: 05414736044613"
              readonly
            />
            <p class="text-xs text-gray-500 mt-1">Code scanné (non modifiable)</p>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom commercial *</label>
            <input 
              v-model="formData.commercialName" 
              type="text" 
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
              placeholder="Ex: Royal Canin Veterinary Diet"
            />
          </div>
          
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Dosage</label>
              <input 
                v-model="formData.dosage" 
                type="text" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                placeholder="Ex: 2kg"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Forme</label>
              <input 
                v-model="formData.forme" 
                type="text" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                placeholder="Ex: Sac"
              />
            </div>
          </div>
          
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Numéro de lot *</label>
              <input 
                v-model="formData.lot_number" 
                type="text" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                placeholder="Ex: 24111170"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date d'expiration *</label>
              <input 
                v-model="formData.expiry_date" 
                type="date" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
              />
            </div>
          </div>
        </div>
        
        <div class="grid grid-cols-2 gap-3">
          <button 
            @click="submitManualMedicationCreate" 
            class="rounded-lg bg-purple-600 text-white py-3 px-4 font-medium hover:bg-purple-700 shadow-md disabled:bg-gray-300 disabled:cursor-not-allowed"
            :disabled="!formData.commercialName || !formData.lot_number || !formData.expiry_date"
          >
            ✓ Créer et enregistrer
          </button>
          <button @click="resetWorkflow" class="rounded-lg border-2 border-gray-300 bg-white text-gray-700 py-3 px-4 font-medium hover:bg-gray-50">
            ✕ Annuler
          </button>
        </div>
      </div>

      <!-- Stage: Ask DataMatrix -->
      <div v-if="workflow.stage === 'ask_datamatrix'" class="space-y-4">
        <div class="bg-yellow-50 p-4 rounded-lg text-center">
          <h3 class="text-lg font-semibold text-yellow-900">⚠️ DataMatrix requis</h3>
          <p class="text-yellow-800 mt-2 font-medium">{{ workflow.medicament?.nom }}</p>
          <p class="text-yellow-700 text-sm mt-1">Code scanné: <span class="font-mono">{{ workflow.scannedCode }}</span></p>
          <p class="text-yellow-700 text-sm mt-2">Ce médicament possède un DataMatrix. Veuillez le scanner pour obtenir le lot et la date de péremption.</p>
        </div>
        
        <!-- Scanner DataMatrix -->
        <div class="bg-purple-50 border-2 border-purple-300 rounded-lg p-6 text-center">
          <div class="text-5xl mb-3">📦</div>
          <h3 class="text-lg font-semibold text-purple-900 mb-2">Scanner le DataMatrix</h3>
          <p class="text-purple-700 text-sm">Scannez le code DataMatrix 2D sur l'emballage</p>
          <div v-if="scanning" class="mt-3">
            <span class="inline-block px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium animate-pulse">
              ✓ En attente du scan DataMatrix
            </span>
          </div>
        </div>
        
        <div class="grid grid-cols-2 gap-2">
          <button @click="startScan" :disabled="scanning" class="rounded-lg bg-purple-600 text-white py-2 font-medium hover:bg-purple-700 disabled:bg-purple-300">
            {{ scanning ? 'En attente...' : 'Activer scanner' }}
          </button>
          <button @click="stopScan" :disabled="!scanning" class="rounded-lg border border-gray-300 bg-white text-gray-700 py-2 font-medium hover:bg-gray-50">
            Désactiver
          </button>
        </div>
        
        <!-- OR Divider -->
        <div class="relative">
          <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-gray-300"></div>
          </div>
          <div class="relative flex justify-center text-sm">
            <span class="px-2 bg-white text-gray-500">OU</span>
          </div>
        </div>
        
        <!-- Entrée manuelle -->
        <button @click="openManualDataMatrixEntry" class="w-full rounded-lg bg-orange-600 text-white py-3 px-4 font-medium hover:bg-orange-700 shadow-md flex items-center justify-center gap-2">
          <span class="text-xl">✏️</span>
          <span>Entrée manuelle (pas de DataMatrix)</span>
        </button>
        
        <!-- Annuler -->
        <button @click="resetWorkflow" class="w-full rounded-lg border border-gray-300 bg-white text-gray-700 py-2 font-medium hover:bg-gray-50">
          Annuler et recommencer
        </button>
      </div>

      <!-- Stage: Manual DataMatrix Entry -->
      <div v-if="workflow.stage === 'manual_datamatrix_entry'" class="space-y-4">
        <div class="bg-orange-50 border-2 border-orange-300 p-6 rounded-lg text-center">
          <div class="text-6xl mb-3">✏️</div>
          <h3 class="text-xl font-bold text-orange-900 mb-2">Entrée manuelle</h3>
          <p class="text-orange-800 text-sm mb-2">Pas de DataMatrix disponible sur ce produit</p>
        </div>
        
        <div class="bg-white border border-gray-200 rounded-lg p-4">
          <div class="mb-4">
            <p class="text-gray-600 text-xs uppercase font-semibold">Médicament</p>
            <p class="text-gray-900 font-bold text-lg">{{ workflow.medicament?.nom }}</p>
            <p class="text-gray-600 text-sm">Code: <span class="font-mono">{{ workflow.scannedCode }}</span></p>
          </div>
          
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Numéro de lot *</label>
              <input 
                v-model="formData.lot_number" 
                type="text" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                placeholder="Ex: 24111170"
                @keyup.enter="submitManualDataMatrixEntry"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date d'expiration *</label>
              <input 
                v-model="formData.expiry_date" 
                type="date" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                @keyup.enter="submitManualDataMatrixEntry"
              />
            </div>
          </div>
        </div>
        
        <div class="grid grid-cols-2 gap-3">
          <button 
            @click="submitManualDataMatrixEntry" 
            class="rounded-lg bg-orange-600 text-white py-3 px-4 font-medium hover:bg-orange-700 shadow-md disabled:bg-gray-300 disabled:cursor-not-allowed"
            :disabled="!formData.lot_number || !formData.expiry_date"
          >
            ✓ Confirmer et enregistrer
          </button>
          <button @click="resetWorkflow" class="rounded-lg border-2 border-gray-300 bg-white text-gray-700 py-3 px-4 font-medium hover:bg-gray-50">
            ✕ Annuler
          </button>
        </div>
      </div>

      <!-- Stage: Ask CNK -->
      <div v-if="workflow.stage === 'ask_cnk'" class="space-y-4">
        <div class="bg-yellow-50 p-4 rounded-lg text-center">
          <h3 class="text-lg font-semibold text-yellow-900">Médicament non trouvé</h3>
          <p class="text-yellow-800 mt-2">GTIN détecté: <span class="font-mono font-bold">{{ workflow.gtin }}</span></p>
          <p class="text-yellow-700 text-sm mt-1">Scannez le code-barres CNK (7 chiffres) pour lier le médicament.</p>
        </div>
        
        <!-- Scanner CNK -->
        <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-6 text-center">
          <div class="text-5xl mb-3">📱</div>
          <h3 class="text-lg font-semibold text-blue-900 mb-2">Scanner le CNK</h3>
          <p class="text-blue-700 text-sm">Scannez le code-barres CNK complet (7 chiffres) avec votre scanner Netum</p>
          <div v-if="scanning" class="mt-3">
            <span class="inline-block px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium animate-pulse">
              ✓ En attente du scan CNK (7 chiffres)
            </span>
          </div>
        </div>
        
        <div class="grid grid-cols-2 gap-2">
          <button @click="handleAskCnk" :disabled="scanning" class="rounded-lg bg-blue-600 text-white py-2 font-medium hover:bg-blue-700 disabled:bg-blue-300">
            {{ scanning ? 'En attente...' : 'Activer scanner CNK' }}
          </button>
          <button @click="stopScan" :disabled="!scanning" class="rounded-lg border border-gray-300 bg-white text-gray-700 py-2 font-medium hover:bg-gray-50">
            Désactiver
          </button>
        </div>
        
        <!-- OR Divider -->
        <div class="relative">
          <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-gray-300"></div>
          </div>
          <div class="relative flex justify-center text-sm">
            <span class="px-2 bg-white text-gray-500">OU</span>
          </div>
        </div>
        
        <button @click="handleNonCnk" class="w-full rounded-lg border-2 border-orange-500 text-orange-600 py-2 font-medium hover:bg-orange-50">
          Autre code (pas CNK)
        </button>
        
        <button @click="handleManualAdd" class="w-full rounded-lg border-2 border-purple-500 bg-purple-50 text-purple-700 py-2 font-medium hover:bg-purple-100">
          📝 Ajout manuel du médicament
        </button>
        
        <button @click="resetWorkflow" class="w-full rounded-lg border border-gray-300 bg-white text-gray-700 py-2 font-medium hover:bg-gray-50">
          Annuler
        </button>
      </div>
      
      
      <!-- Stage: Ask Manual Name -->
      <div v-if="workflow.stage === 'ask_manual_name'" class="space-y-4">
        <div class="bg-purple-50 p-4 rounded-lg">
          <h3 class="text-lg font-semibold text-purple-900">📝 Ajout manuel d'un médicament</h3>
          <p class="text-purple-800 mt-2 text-sm">GTIN: <span class="font-mono font-bold">{{ workflow.gtin }}</span></p>
          <p class="text-purple-700 text-sm mt-1">Créez une fiche médicament dans la base de données.</p>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Dénomination commerciale *</label>
          <input
            v-model="formData.commercialName"
            type="text"
            placeholder="Ex: Ibuprofen 400mg"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"
          />
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Forme pharmaceutique (optionnel)</label>
          <input
            v-model="formData.forme"
            type="text"
            placeholder="Ex: Comprimé, Solution injectable"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"
          />
        </div>
        
        <div class="border-t pt-3">
          <h4 class="text-sm font-semibold text-gray-700 mb-2">Codes alternatifs (optionnel)</h4>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-600 mb-1">CNK (Code 2)</label>
              <input
                v-model="formData.barcode2"
                type="text"
                placeholder="7 chiffres"
                class="w-full rounded border border-gray-300 px-2 py-1 text-sm font-mono"
              />
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Code alternatif 3</label>
              <input
                v-model="formData.barcode3"
                type="text"
                class="w-full rounded border border-gray-300 px-2 py-1 text-sm font-mono"
              />
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Code alternatif 4</label>
              <input
                v-model="formData.barcode4"
                type="text"
                class="w-full rounded border border-gray-300 px-2 py-1 text-sm font-mono"
              />
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Code alternatif 5</label>
              <input
                v-model="formData.barcode5"
                type="text"
                class="w-full rounded border border-gray-300 px-2 py-1 text-sm font-mono"
              />
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Code alternatif 6</label>
              <input
                v-model="formData.barcode6"
                type="text"
                class="w-full rounded border border-gray-300 px-2 py-1 text-sm font-mono"
              />
            </div>
          </div>
        </div>
        
        <button @click="submitManualMedicament" :disabled="!formData.commercialName" class="w-full rounded-lg bg-purple-600 text-white py-2 font-medium hover:bg-purple-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
          Créer le médicament et enregistrer l'inventaire
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

