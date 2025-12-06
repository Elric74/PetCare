# Zoolyx Scraper

Automates login to Zoolyx portal and retrieves report metadata + PDFs, then posts to PetCare.

## Setup

1. Install Node deps:
```
npm install
```
2. Create `.env` from `.env.example` and fill credentials.
3. Run:
```
npm run scrape
```

## Env vars
- `ZOOLYX_EMAIL`, `ZOOLYX_PASSWORD`: portal creds
- `ZOOLYX_URL`: report list URL
- `PETCARE_API_URL`: Laravel app base URL (e.g., http://localhost:8000)
- `PETCARE_API_TOKEN`: Bearer token if protected API
- `DOWNLOAD_DIR`: relative folder for PDFs

## Output
- Downloads PDFs under `downloads/`
- Posts JSON and file to `POST /api/lab-reports` in PetCare.
