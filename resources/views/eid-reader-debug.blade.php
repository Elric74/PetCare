<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Lecteur eID Belgique - Debug</title>
  <style>
    body { 
      font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; 
      padding: 20px; 
      color:#111;
      max-width: 1000px;
      margin: 0 auto;
    }
    h1 { font-size:20px; margin-bottom:12px; color:#1f2937 }
    h2 { font-size:16px; margin-top:20px; margin-bottom:8px; color:#374151 }
    .info-box { 
      background:#eff6ff; 
      border:1px solid #3b82f6; 
      border-radius:8px; 
      padding:12px; 
      margin-bottom:16px;
      font-size:14px;
    }
    .warning-box { 
      background:#fef3c7; 
      border:1px solid #f59e0b; 
      border-radius:8px; 
      padding:12px; 
      margin-bottom:16px;
      font-size:14px;
    }
    .controls { 
      display:flex; 
      gap:8px; 
      flex-wrap:wrap; 
      margin-bottom:16px;
      padding:12px;
      background:#f9fafb;
      border-radius:8px;
    }
    button { 
      padding:10px 16px; 
      border-radius:6px; 
      border:1px solid #d1d5db; 
      background:#fff; 
      cursor:pointer;
      font-size:14px;
      transition: all 0.2s;
    }
    button:hover { background:#f3f4f6; }
    button.primary { 
      background:#2563eb; 
      color:#fff; 
      border-color:#1d4ed8;
      font-weight:500;
    }
    button.primary:hover { background:#1d4ed8; }
    button:disabled { 
      opacity:0.5; 
      cursor:not-allowed;
    }
    .log { 
      height:250px; 
      overflow:auto; 
      border:1px solid #e5e7eb; 
      padding:12px; 
      background:#fafafa; 
      border-radius:6px; 
      font-family:monospace; 
      font-size:12px;
      margin-bottom:16px;
    }
    .log-entry { margin-bottom:4px; }
    .log-time { color:#6b7280; }
    .log-error { color:#dc2626; }
    .log-success { color:#16a34a; }
    .log-info { color:#2563eb; }
    
    .data-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 12px;
      margin-top: 12px;
    }
    .data-card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 12px;
    }
    .data-label {
      font-size: 12px;
      color: #6b7280;
      margin-bottom: 4px;
      font-weight: 500;
    }
    .data-value {
      font-size: 14px;
      color: #111827;
      word-break: break-word;
    }
    .photo-container {
      text-align: center;
      padding: 12px;
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
    }
    .photo-container img {
      max-width: 200px;
      border-radius: 6px;
      border: 1px solid #d1d5db;
    }
    
    .method-card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 12px;
    }
    .method-title {
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 8px;
    }
    .method-status {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 500;
    }
    .status-available { background: #d1fae5; color: #065f46; }
    .status-unavailable { background: #fee2e2; color: #991b1b; }
    .small { font-size:13px; color:#6b7280; line-height:1.5 }
  </style>
</head>
<body>
  <h1>🇧🇪 Lecteur eID Belgique - Page de Debug</h1>
  
  <div class="info-box">
    <strong>Objectif :</strong> Tester la lecture de la carte d'identité électronique belge pour remplir automatiquement les données client.
  </div>

  <div class="warning-box">
    <strong>⚠️ Prérequis :</strong>
    <ul style="margin:8px 0 0 20px; padding:0">
      <li>Middleware Belgium eID installé (<a href="https://eid.belgium.be" target="_blank">eid.belgium.be</a>)</li>
      <li>Lecteur de carte branché et carte insérée</li>
      <li>Navigateur compatible (Chrome/Edge recommandé)</li>
      <li>Accès via <code>http://localhost</code> pour les APIs sécurisées</li>
    </ul>
  </div>

  <h2>Méthodes disponibles</h2>
  <div id="methodsStatus"></div>

  <h2>Contrôles</h2>
  <div class="controls">
    <button id="checkMiddleware" class="primary">1. Vérifier Middleware</button>
    <button id="readSmartCard" disabled>2. Lire via Smart Card API</button>
    <button id="readWebExtension" disabled>3. Lire via Extension</button>
    <button id="readNFC" disabled>4. Lire via NFC</button>
    <button id="clearLog">Effacer logs</button>
  </div>

  <h2>Logs</h2>
  <div id="log" class="log"></div>

  <h2>Données extraites</h2>
  <div id="dataContainer" style="display:none">
    <div class="data-grid" id="dataGrid"></div>
    <div class="photo-container" id="photoContainer" style="display:none">
      <div class="data-label">Photo</div>
      <img id="photoImg" src="" alt="Photo eID" />
    </div>
    <button id="fillForm" class="primary" style="margin-top:16px">Remplir le formulaire client</button>
  </div>

  <h2>Notes techniques</h2>
  <div class="small">
    <p><strong>Méthodes de lecture :</strong></p>
    <ul style="margin-left:20px">
      <li><strong>Middleware eID :</strong> Service local sur port 15000 (requis)</li>
      <li><strong>Smart Card API :</strong> API expérimentale Chrome (nécessite flag)</li>
      <li><strong>Extension navigateur :</strong> Extension officielle eID (à installer)</li>
      <li><strong>Web NFC :</strong> Pour cartes compatibles NFC (rare pour eID)</li>
    </ul>
    <p><strong>Données disponibles :</strong> Nom, prénom, date de naissance, adresse, numéro national, photo, etc.</p>
  </div>

<script>
const logEl = document.getElementById('log')
const dataContainer = document.getElementById('dataContainer')
const dataGrid = document.getElementById('dataGrid')
const photoContainer = document.getElementById('photoContainer')
const photoImg = document.getElementById('photoImg')
const methodsStatus = document.getElementById('methodsStatus')

let extractedData = {}

function log(message, type = 'info') {
  const now = new Date().toISOString().slice(11, 19)
  const div = document.createElement('div')
  div.className = 'log-entry'
  
  const timeSpan = document.createElement('span')
  timeSpan.className = 'log-time'
  timeSpan.textContent = `[${now}] `
  
  const msgSpan = document.createElement('span')
  msgSpan.className = `log-${type}`
  msgSpan.textContent = message
  
  div.appendChild(timeSpan)
  div.appendChild(msgSpan)
  logEl.appendChild(div)
  logEl.scrollTop = logEl.scrollHeight
  console.log(`[${type}]`, message)
}

function checkAPIsAvailability() {
  const methods = []
  
  // Smart Card API (experimental)
  const smartCardAvailable = 'smartCard' in navigator
  methods.push({
    name: 'Smart Card API',
    available: smartCardAvailable,
    description: 'API expérimentale Chrome pour lecteurs de cartes'
  })
  
  // Web NFC
  const nfcAvailable = 'nfc' in navigator || 'NDEFReader' in window
  methods.push({
    name: 'Web NFC',
    available: nfcAvailable,
    description: 'Pour cartes avec puce NFC'
  })
  
  // Extension check (simplified)
  methods.push({
    name: 'Extension eID',
    available: false,
    description: 'Extension officielle Belgium eID (vérification manuelle requise)'
  })
  
  // Middleware check (will test via fetch)
  methods.push({
    name: 'Middleware eID',
    available: null,
    description: 'Service local Belgium eID (port 15000) - cliquez sur "Vérifier Middleware"'
  })
  
  methodsStatus.innerHTML = methods.map(m => `
    <div class="method-card">
      <div class="method-title">${m.name}</div>
      <span class="method-status ${m.available === true ? 'status-available' : m.available === false ? 'status-unavailable' : ''}">${
        m.available === true ? '✓ Disponible' : 
        m.available === false ? '✗ Non disponible' : 
        '? À vérifier'
      }</span>
      <div class="small" style="margin-top:8px">${m.description}</div>
    </div>
  `).join('')
  
  // Enable/disable buttons
  document.getElementById('readSmartCard').disabled = !smartCardAvailable
  document.getElementById('readNFC').disabled = !nfcAvailable
}

async function checkMiddleware() {
  log('Vérification du middleware Belgium eID...', 'info')
  
  try {
    // Use Laravel backend API
    const response = await fetch('/eid/check', {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    })
    
    const data = await response.json()
    
    if (data.installed) {
      log('✓ ' + data.message, 'success')
      document.getElementById('readWebExtension').disabled = false
      document.getElementById('readWebExtension').textContent = '3. Lire via Middleware (Backend)'
      return true
    } else {
      log('✗ ' + data.message, 'error')
      return false
    }
  } catch (error) {
    log('✗ Erreur de connexion au backend Laravel', 'error')
    log('Détails: ' + error.message, 'error')
    log('Assurez-vous que le serveur Laravel est démarré', 'info')
    return false
  }
}

async function readViaMiddleware() {
  log('Lecture via middleware eID (backend Laravel)...', 'info')
  log('⚠️ Assurez-vous que le eID Viewer est ouvert et la carte insérée', 'info')
  
  try {
    // Call Laravel backend API
    const response = await fetch('/eid/read-all', {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    })
    
    const result = await response.json()
    
    if (!result.success) {
      log('✗ ' + result.message, 'error')
      log('', 'info')
      log('Vérifiez que:', 'info')
      log('1. Le lecteur de carte est branché', 'info')
      log('2. La carte eID est insérée', 'info')
      log('3. Le eID Viewer est ouvert', 'info')
      log('4. Vous avez entré votre code PIN dans le eID Viewer', 'info')
      return
    }
    
    log('✓ ' + result.message, 'success')
    
    // Store extracted data
    extractedData = result.data
    
    // Display photo if available
    if (result.photo) {
      photoImg.src = 'data:image/jpeg;base64,' + result.photo
      photoContainer.style.display = 'block'
      log('✓ Photo chargée', 'success')
    } else {
      log('Photo non disponible', 'info')
    }
    
    // Display data
    displayData()
    
  } catch (error) {
    log('Erreur lors de la lecture: ' + error.message, 'error')
    log('Assurez-vous que le serveur Laravel est démarré', 'error')
  }
}

async function readViaSmartCard() {
  log('Tentative de lecture via Smart Card API...', 'info')
  
  try {
    if (!('smartCard' in navigator)) {
      log('Smart Card API non supportée', 'error')
      return
    }
    
    // Request smart card context
    const context = await navigator.smartCard.establishContext()
    const readers = await context.listReaders()
    
    if (readers.length === 0) {
      log('Aucun lecteur détecté', 'error')
      return
    }
    
    log('Lecteurs: ' + readers.join(', '), 'info')
    
    // Connect to first reader
    const connection = await context.connect(readers[0])
    
    // eID specific APDUs would go here
    log('Connexion établie. Lecture des données eID...', 'info')
    
    // This would require specific APDU commands for Belgian eID
    // which are complex and require the middleware
    
    log('⚠️ Cette méthode nécessite l\'implémentation des commandes APDU eID', 'info')
    
  } catch (error) {
    log('Erreur Smart Card: ' + error.message, 'error')
  }
}

async function readViaNFC() {
  log('Tentative de lecture via NFC...', 'info')
  
  try {
    if (!('NDEFReader' in window)) {
      log('Web NFC non supporté', 'error')
      return
    }
    
    const ndef = new NDEFReader()
    await ndef.scan()
    
    log('Scanner NFC activé. Approchez la carte...', 'info')
    
    ndef.addEventListener('reading', ({ message, serialNumber }) => {
      log(`Carte détectée: ${serialNumber}`, 'success')
      
      // L'eID belge n'expose généralement pas de données via NFC NDEF
      log('⚠️ L\'eID belge ne supporte pas NDEF. Utilisez le middleware.', 'info')
    })
    
  } catch (error) {
    log('Erreur NFC: ' + error.message, 'error')
  }
}

function displayData() {
  dataGrid.innerHTML = Object.entries(extractedData)
    .map(([key, value]) => `
      <div class="data-card">
        <div class="data-label">${key.replace(/_/g, ' ').toUpperCase()}</div>
        <div class="data-value">${value || '-'}</div>
      </div>
    `).join('')
  
  dataContainer.style.display = 'block'
  log('Données prêtes à être transférées', 'success')
}

function fillClientForm() {
  log('Préparation des données pour le formulaire client...', 'info')
  
  // Store data in localStorage for retrieval by main app
  localStorage.setItem('eid_data', JSON.stringify(extractedData))
  
  log('✓ Données stockées dans localStorage (clé: "eid_data")', 'success')
  log('Vous pouvez maintenant récupérer ces données depuis votre formulaire client', 'info')
  
  // If we want to open the client form in the same window
  // window.location.href = '/clients/create'
  
  // Or open in new tab
  // window.open('/clients/create', '_blank')
  
  alert('Données prêtes! Elles sont stockées dans localStorage avec la clé "eid_data".\n\nPour les récupérer dans votre formulaire:\nconst data = JSON.parse(localStorage.getItem("eid_data"))')
}

// Event listeners
document.getElementById('checkMiddleware').addEventListener('click', checkMiddleware)
document.getElementById('readSmartCard').addEventListener('click', readViaSmartCard)
document.getElementById('readWebExtension').addEventListener('click', readViaMiddleware)
document.getElementById('readNFC').addEventListener('click', readViaNFC)
document.getElementById('clearLog').addEventListener('click', () => { logEl.innerHTML = '' })
document.getElementById('fillForm').addEventListener('click', fillClientForm)

// Initialize
checkAPIsAvailability()
log('Page chargée. Cliquez sur "Vérifier Middleware" pour commencer', 'info')
</script>
</body>
</html>
