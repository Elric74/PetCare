import { chromium } from 'playwright'
import dotenv from 'dotenv'
import fs from 'fs'
import path from 'path'
import axios from 'axios'
import FormData from 'form-data'

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
  headers: PETCARE_API_TOKEN ? { Authorization: `Bearer ${PETCARE_API_TOKEN}` } : {}
})

async function postReportToPetCare(payload, filePath) {
  const form = new FormData()
  form.append('owner_first_name', payload.owner_first_name || '')
  form.append('owner_last_name', payload.owner_last_name || '')
  form.append('pet_name', payload.pet_name || '')
  form.append('pet_gender', payload.pet_gender || '')
  form.append('reception_date', payload.reception_date || '')
  form.append('updated_date', payload.updated_date || '')
  form.append('source', 'zoolyx')

  if (filePath && fs.existsSync(filePath)) {
    let filename = path.basename(filePath)
    if (!filename.toLowerCase().endsWith('.pdf')) {
      filename = filename + '.pdf'
    }
    form.append('pdf', fs.createReadStream(filePath), {
      filename,
      contentType: 'application/pdf'
    })
  }

  const url = '/api/lab-reports'
  const headers = form.getHeaders()

  const res = await client.post(url, form, { headers })
  console.log('Posted to PetCare:', res.data)
}

async function scrape() {
  if (!ZOOLYX_EMAIL || !ZOOLYX_PASSWORD) {
    console.error('Missing ZOOLYX_EMAIL or ZOOLYX_PASSWORD in .env')
    process.exit(1)
  }

  ensureDir(path.resolve(DOWNLOAD_DIR))

  const browser = await chromium.launch({ headless: true })
  const context = await browser.newContext({ acceptDownloads: true })
  const page = await context.newPage()

  console.log('Navigating to', ZOOLYX_URL)
  await page.goto(ZOOLYX_URL, { waitUntil: 'domcontentloaded' })

  // Detect login form; skip if already authenticated
  const loginFormPresent = await page.locator('input[type="password"]').first().isVisible().catch(() => false)
  if (loginFormPresent) {
    console.log('Login form detected, filling credentials...')
    
    // Wait for form to be ready
    await page.waitForTimeout(1000)
    
    // Find and fill email - try by placeholder first
    const emailInput = page.locator('input[type="email"], input[placeholder*="Email"], input[placeholder*="email"]').first()
    await emailInput.waitFor({ state: 'visible', timeout: 5000 })
    await emailInput.fill(ZOOLYX_EMAIL)
    console.log('Email filled')

    // Find and fill password
    const pwdInput = page.locator('input[type="password"]').first()
    await pwdInput.waitFor({ state: 'visible', timeout: 5000 })
    await pwdInput.fill(ZOOLYX_PASSWORD)
    console.log('Password filled')

    // Check "Stay logged in" checkbox if present
    const stayLoggedIn = page.locator('input[type="checkbox"]').first()
    if (await stayLoggedIn.isVisible().catch(() => false)) {
      await stayLoggedIn.check()
      console.log('Stay logged in checked')
    }

    // Click submit button - look for "Aanmelden" (Dutch for login)
    const submitBtn = page.locator('button:has-text("Aanmelden"), input[type="submit"]').first()
    await submitBtn.waitFor({ state: 'visible', timeout: 5000 })
    await submitBtn.click()
    console.log('Login submitted, waiting for navigation...')
    
    // Wait for navigation to complete
    await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {})
    await page.waitForTimeout(2000)
  } else {
    console.log('Already authenticated or no login form found')
  }

  // Take screenshot to debug what page we're on
  await page.screenshot({ path: 'debug_after_login.png' })
  console.log('Screenshot saved: debug_after_login.png')

  // Wait for navigation or content load
  await page.waitForLoadState('domcontentloaded')
  
  // Try multiple selectors with longer timeout and fallback
  let reportSectionFound = false
  const possibleSelectors = [
    'table',
    '.report-list',
    '.reports-table',
    '[class*="report"]',
    '[id*="report"]',
    'main',
    '#content',
    '.content'
  ]
  
  for (const selector of possibleSelectors) {
    const found = await page.locator(selector).first().isVisible({ timeout: 5000 }).catch(() => false)
    if (found) {
      console.log(`Found element with selector: ${selector}`)
      reportSectionFound = true
      break
    }
  }

  if (!reportSectionFound) {
    console.error('No report section found. Current URL:', page.url())
    const bodyText = await page.locator('body').textContent()
    console.log('Page content preview:', bodyText.substring(0, 500))
  }

  // Wait for content to be fully loaded
  await page.waitForTimeout(3000)
  console.log('Page loaded, extracting reports...')
  
  // Find all report rows - each is an <li class="myzoolyx_row"> with a link
  const reportRows = await page.$$('li.myzoolyx_row a[href*="/myzoolyx/report/view/"]')
  console.log(`Found ${reportRows.length} reports to process\n`)

  if (reportRows.length === 0) {
    console.log('No reports found')
    await browser.close()
    return
  }

  for (let i = 0; i < reportRows.length; i++) {
    console.log(`\n--- Processing report ${i + 1}/${reportRows.length} ---`)
    
    // Navigate to list page if not there (in case of back navigation)
    if (!page.url().includes('/veterinary/report/index')) {
      await page.goto(ZOOLYX_URL)
      await page.waitForTimeout(2000)
    }
    
    // Re-fetch rows after navigation
    const currentRows = await page.$$('li.myzoolyx_row a[href*="/myzoolyx/report/view/"]')
    const reportLink = currentRows[i]
    
    // Extract data from the row before clicking
    const rowContainer = await reportLink.evaluateHandle(el => el.closest('li.myzoolyx_row'))
    const rowText = await page.evaluate(el => el.textContent, rowContainer)
    
    // Extract structured data from the row
    const animalDiv = await rowContainer.$('.col-xs-6.col-sm-3.col-md-2')
    const ownerDiv = await rowContainer.$('.col-xs-6.col-sm-3.col-md-3')
    const datesDiv = await rowContainer.$('.col-sm-4.col-md-3')
    
    let pet_name = ''
    let pet_species = ''
    if (animalDiv) {
      pet_name = (await animalDiv.$eval('.text-wrap', el => el.textContent)).trim()
      const imgSrc = await animalDiv.$eval('img', el => el.src).catch(() => '')
      if (imgSrc.includes('/dog.png')) pet_species = 'dog'
      else if (imgSrc.includes('/cat.png')) pet_species = 'cat'
    }
    
    const owner_full = ownerDiv ? (await ownerDiv.textContent()).trim() : ''
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
    if (datesDiv) {
      const dateCols = await datesDiv.$$('.col-sm-6')
      if (dateCols.length >= 2) {
        reception_date = (await dateCols[0].textContent()).trim()
        updated_date = (await dateCols[1].textContent()).trim()
      }
    }
    
    console.log('Animal:', pet_name, `(${pet_species})`)
    console.log('Propriétaire:', owner_full)
    console.log('Réception:', reception_date)
    console.log('Mise à jour:', updated_date)
    
    // Click to open detail page
    await reportLink.click()
    await page.waitForLoadState('networkidle').catch(() => {})
    await page.waitForTimeout(2000)
    
    // Try to find and download PDF
    let savedPath = ''
    const pdfLink = await page.$('a[href$=".pdf"], a:has-text("PDF"), a:has-text("Télécharger"), a[href*="download"]').catch(() => null)
    
    if (pdfLink) {
      try {
        const [download] = await Promise.all([
          page.waitForEvent('download', { timeout: 10000 }),
          pdfLink.click()
        ])
        const fileName = (await download.suggestedFilename()) || `report_${pet_name}_${Date.now()}.pdf`
        savedPath = path.resolve(DOWNLOAD_DIR, fileName)
        await download.saveAs(savedPath)
        console.log('✅ Downloaded PDF:', fileName)
      } catch (err) {
        console.error('❌ Error downloading PDF:', err.message)
      }
    } else {
      console.log('⚠️  No PDF link found')
    }
    
    const payload = {
      pet_gender: pet_species,
      pet_name,
      owner_first_name,
      owner_last_name,
      owner_name: owner_full,
      reception_date,
      updated_date
    }
    
    // Post to PetCare
    try {
      await postReportToPetCare(payload, savedPath)
      console.log('✅ Posted to PetCare')
    } catch (err) {
      console.error('❌ Error posting:', err.response?.data || err.message)
    }
    
    // Go back to list
    await page.goBack()
    await page.waitForTimeout(1500)
  }

  console.log(`\n🎉 Finished processing ${reportRows.length} reports`)

  await browser.close()
}

scrape().catch(err => { console.error(err); process.exit(1) })
