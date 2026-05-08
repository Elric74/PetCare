import axios from 'axios'
import * as cheerio from 'cheerio'
import dotenv from 'dotenv'
import fs from 'fs'
import path from 'path'
import FormData from 'form-data'
import { CookieJar } from 'tough-cookie'
import { wrapper } from 'axios-cookiejar-support'

dotenv.config()

const ZOOLYX_EMAIL = process.env.ZOOLYX_EMAIL
const ZOOLYX_PASSWORD = process.env.ZOOLYX_PASSWORD
const ZOOLYX_URL = process.env.ZOOLYX_URL || 'https://www2.zoolyx.be/myzoolyx/veterinary/report/index'
const PETCARE_API_URL = process.env.PETCARE_API_URL || 'http://localhost:8000'
const PETCARE_API_TOKEN = process.env.PETCARE_API_TOKEN || ''
const DOWNLOAD_DIR = process.env.DOWNLOAD_DIR || 'downloads'

const ensureDir = (p) => {
  if (!fs.existsSync(p)) fs.mkdirSync(p, { recursive: true })
}

const client = axios.create({
  baseURL: PETCARE_API_URL,
  headers: PETCARE_API_TOKEN ? { Authorization: `Bearer ${PETCARE_API_TOKEN}` } : {},
  maxRedirects: 5,
  timeout: 30000
})

// Parse date from format like "01/12/2025" to "2025-12-01"
function parseZoolyxDate(dateStr) {
  if (!dateStr) return ''
  const match = dateStr.match(/(\d{2})\/(\d{2})\/(\d{4})/)
  if (match) {
    const [_, day, month, year] = match
    return `${year}-${month}-${day}`
  }
  return dateStr
}

// Extract report ID from URL
function extractReportId(url) {
  const match = url.match(/\/report\/view\/(\d+)/)
  return match ? match[1] : null
}

async function loginToZoolyx() {
  try {
    console.log('🔐 Tentative de connexion à Zoolyx...')

    // Créer une session axios avec cookies
    const jar = new CookieJar()
    const session = wrapper(axios.create({
      jar,
      maxRedirects: 5,
      timeout: 30000,
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language': 'fr,en-US;q=0.7,en;q=0.3',
        'Accept-Encoding': 'gzip, deflate, br',
        'DNT': '1',
        'Connection': 'keep-alive',
        'Upgrade-Insecure-Requests': '1'
      }
    }))

    // Étape 1 : Charger la page de login pour récupérer le token CSRF
    console.log('📄 Chargement de la page de login...')
    const loginPageResponse = await session.get('https://www2.zoolyx.be/myzoolyx/login')
    const $login = cheerio.load(loginPageResponse.data)

    // Extraire le token CSRF (comme dans le PHP)
    const csrfToken = $login('input[name="_csrf_token"]').val()

    if (!csrfToken) {
      throw new Error('❌ Impossible de récupérer le token CSRF')
    }

    console.log('🔑 Token CSRF trouvé:', csrfToken.substring(0, 10) + '...')

    // Étape 2 : Soumettre le formulaire de connexion (comme dans le PHP)
    console.log('📤 Envoi des identifiants...')
    console.log('👤 Utilisateur:', ZOOLYX_EMAIL)
    console.log('🔑 Mot de passe (masqué):', ZOOLYX_PASSWORD.replace(/./g, '*'))

    const loginData = new URLSearchParams({
      '_username': ZOOLYX_EMAIL,
      '_password': ZOOLYX_PASSWORD,
      '_csrf_token': csrfToken,
      '_remember_me': 'on'
    })

    console.log('📋 Données de login:', loginData.toString().replace(ZOOLYX_PASSWORD, '***'))

    const loginResponse = await session.post('https://www2.zoolyx.be/myzoolyx/login_check', loginData.toString(), {
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Referer': 'https://www2.zoolyx.be/myzoolyx/login',
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language': 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
        'Accept-Encoding': 'gzip, deflate, br',
        'DNT': '1',
        'Connection': 'keep-alive',
        'Upgrade-Insecure-Requests': '1',
        'Sec-Fetch-Dest': 'document',
        'Sec-Fetch-Mode': 'navigate',
        'Sec-Fetch-Site': 'same-origin',
        'Cache-Control': 'max-age=0',
        'Origin': 'https://www2.zoolyx.be'
      },
      maxRedirects: 5 // Laisser axios suivre les redirections comme cURL
    })

    console.log('🔍 Statut de la réponse de login:', loginResponse.status)
    console.log('🔍 URL finale après login:', loginResponse.request?.res?.responseUrl || 'N/A')

    // Comme dans le PHP : après le login, aller directement sur la page des rapports
    console.log('📄 Accès à la page des rapports...')
    const reportCheck = await session.get(ZOOLYX_URL)

    console.log('🔍 Statut de la page rapports:', reportCheck.status)
    console.log('🔍 URL finale rapports:', reportCheck.request?.res?.responseUrl || 'N/A')

    // Vérifier si on est sur une page de login (contient des éléments de formulaire)
    const $report = cheerio.load(reportCheck.data)
    const hasLoginForm = $report('form[action*="login"]').length > 0
    const hasLoginInput = $report('input[name="_username"], input[name="email"]').length > 0
    const hasCsrfToken = $report('input[name="_csrf_token"]').length > 0

    if (hasLoginForm || hasLoginInput || hasCsrfToken) {
      console.log('🚫 Toujours sur la page de login - authentification échouée')
      console.log('🔍 Titre de la page:', $report('title').text())
      console.log('🔍 Contient des erreurs:', $report('.error, .alert-danger, .invalid-feedback').text())
      throw new Error('❌ Login failed - still on login page')
    }

    if (reportCheck.data.includes('myzoolyx_row') || reportCheck.data.includes('report') ||
        reportCheck.data.includes('Rapport') || reportCheck.data.includes('report_completed') ||
        reportCheck.data.includes('veterinary/report') || reportCheck.data.includes('table') ||
        reportCheck.data.includes('tbody')) {
      console.log('✅ Connexion réussie!')
      return session
    } else {
      console.log('⚠️ Réponse reçue mais contenu inattendu')
      console.log('🔍 Titre de la page:', $report('title').text())
      console.log('🔍 Contient "login":', reportCheck.data.includes('login'))
      console.log('🔍 Contient "error":', reportCheck.data.includes('error'))
      console.log('🔍 Contient "redirect":', reportCheck.data.includes('redirect'))
      console.log('🔍 Longueur du contenu:', reportCheck.data.length)
      console.log('Contenu (aperçu):', reportCheck.data.substring(0, 1000))
      throw new Error('❌ Login failed - contenu inattendu')
    }

  } catch (error) {
    console.error('❌ Erreur lors du login:', error.message)
    if (error.response) {
      console.error('Status:', error.response.status)
      console.error('Headers:', JSON.stringify(error.response.headers, null, 2))
    }
    throw error
  }
}

async function scrapeReports(session) {
  try {
    console.log('📋 Récupération de la liste des rapports...')

    const response = await session.get(ZOOLYX_URL)
    const $ = cheerio.load(response.data)

    const reports = []

    // Parser le tableau des rapports (adapter les sélecteurs selon le HTML réel)
    $('li.myzoolyx_row').each((index, row) => {
      const $row = $(row)
      const link = $row.find('a[href*="/myzoolyx/report/view/"]')

      if (link.length > 0) {
        const url = link.attr('href')
        const reportId = extractReportId(url)

        if (reportId) {
          // Extraire les données de la ligne
          const animalDiv = $row.find('.col-xs-6.col-sm-3.col-md-2')
          const ownerDiv = $row.find('.col-xs-6.col-sm-3.col-md-3')
          const datesDiv = $row.find('.col-sm-4.col-md-3')

          let pet_name = animalDiv.find('.text-wrap').text().trim()
          let pet_species = 'unknown'
          const imgSrc = animalDiv.find('img').attr('src') || ''
          if (imgSrc.includes('/dog.png')) pet_species = 'dog'
          else if (imgSrc.includes('/cat.png')) pet_species = 'cat'

          const owner_full = ownerDiv.text().trim()
          let owner_first_name = ''
          let owner_last_name = ''
          if (owner_full) {
            const parts = owner_full.split(/\s+/)
            if (parts.length > 1) {
              owner_first_name = parts.slice(0, -1).join(' ')
              owner_last_name = parts.slice(-1)[0]
            } else {
              owner_last_name = owner_full
            }
          }

          let reception_date = ''
          let updated_date = ''
          const dateCols = datesDiv.find('.col-sm-6')
          if (dateCols.length >= 2) {
            reception_date = $(dateCols[0]).text().trim()
            updated_date = $(dateCols[1]).text().trim()
          }

          const report = {
            id: reportId,
            url: `https://www2.zoolyx.be${url}`,
            pet_name,
            pet_species,
            owner_first_name,
            owner_last_name,
            reception_date,
            updated_date
          }

          reports.push(report)
        }
      }
    })

    console.log(`📊 Trouvé ${reports.length} rapports`)
    return reports

  } catch (error) {
    console.error('❌ Erreur lors du scraping:', error.message)
    throw error
  }
}

async function downloadReport(session, report) {
  try {
    console.log(`⬇️ Téléchargement du rapport ${report.id}...`)

    const response = await session.get(report.url, {
      responseType: 'arraybuffer',
      timeout: 60000
    })

    // Chercher un lien PDF dans la page
    const $ = cheerio.load(response.data)
    const pdfLink = $('a[href$=".pdf"]').first()

    if (pdfLink.length > 0) {
      const pdfUrl = pdfLink.attr('href')
      console.log('🔗 PDF trouvé:', pdfUrl)

      const pdfResponse = await session.get(`https://www2.zoolyx.be${pdfUrl}`, {
        responseType: 'arraybuffer',
        timeout: 60000
      })

      const filename = `${report.pet_name.replace(/[^a-zA-Z0-9]/g, '_')}_${report.id}.pdf`
      const filepath = path.join(DOWNLOAD_DIR, filename)

      fs.writeFileSync(filepath, Buffer.from(pdfResponse.data))
      console.log(`💾 Rapport sauvegardé: ${filepath}`)
      return filepath
    } else {
      console.log('⚠️ Aucun lien PDF trouvé sur la page')
      return null
    }

  } catch (error) {
    console.error(`❌ Erreur téléchargement rapport ${report.id}:`, error.message)
    return null
  }
}

async function postReportToPetCare(report, filePath) {
  const form = new FormData()
  form.append('zoolyx_report_id', report.id)
  form.append('pet_name', report.pet_name)
  form.append('pet_species', report.pet_species)
  form.append('owner_first_name', report.owner_first_name)
  form.append('owner_last_name', report.owner_last_name)
  form.append('reception_date', parseZoolyxDate(report.reception_date))
  form.append('updated_date', parseZoolyxDate(report.updated_date))

  if (filePath && fs.existsSync(filePath)) {
    form.append('pdf_file', fs.createReadStream(filePath), {
      filename: path.basename(filePath),
      contentType: 'application/pdf'
    })
  }

  const url = '/api/lab-reports'
  const headers = form.getHeaders()

  const res = await client.post(url, form, { headers })
  return res.data
}

async function scrape() {
  if (!ZOOLYX_EMAIL || !ZOOLYX_PASSWORD) {
    console.error('❌ ZOOLYX_EMAIL et ZOOLYX_PASSWORD requis dans .env')
    process.exit(1)
  }

  ensureDir(path.resolve(DOWNLOAD_DIR))

  try {
    const session = await loginToZoolyx()
    const reports = await scrapeReports(session)

    console.log(`🚀 Traitement de ${Math.min(reports.length, 5)} rapports (limité pour test)...`)

    for (const report of reports.slice(0, 5)) { // Limiter à 5 premiers pour test
      try {
        console.log(`\n📄 Traitement: ${report.pet_name} (${report.id})`)

        const filePath = await downloadReport(session, report)

        if (filePath) {
          const result = await postReportToPetCare(report, filePath)
          if (result.status === 'duplicate') {
            console.log('⚠️ Ignoré (doublon):', result.message)
          } else {
            console.log('✅ Envoyé à PetCare:', result.message)
          }
        } else {
          console.log('⚠️ PDF non trouvé, envoi des métadonnées seulement')
          await postReportToPetCare(report, null)
        }

      } catch (error) {
        console.error(`❌ Erreur traitement rapport ${report.id}:`, error.message)
      }
    }

    console.log('\n🎉 Scraping terminé!')

  } catch (error) {
    console.error('💥 Erreur générale:', error.message)
    process.exit(1)
  }
}

scrape()
