# Zoolyx Scraper (HTTP Version)

Version simplifiée utilisant des requêtes HTTP au lieu de Playwright/Chromium.

## Installation

```bash
npm install
```

## Configuration

Créer un fichier `.env` avec :

```env
ZOOLYX_EMAIL=votre@email.com
ZOOLYX_PASSWORD=votre_mot_de_passe
ZOOLYX_URL=https://www2.zoolyx.be/myzoolyx/veterinary/report/index
PETCARE_API_URL=http://192.168.1.10:888
DOWNLOAD_DIR=downloads
```

## Utilisation

```bash
npm run scrape
```

## Fonctionnement

1. **Connexion HTTP** : Utilise axios pour se connecter à Zoolyx
2. **Parsing HTML** : Utilise cheerio pour extraire les données des rapports
3. **Téléchargement** : Télécharge les PDFs via requêtes HTTP
4. **Import** : Poste les données vers l'API PetCare

## Avantages

- ✅ Pas besoin d'installer Chromium ou ses dépendances
- ✅ Plus léger et rapide
- ✅ Fonctionne sur n'importe quel système avec Node.js
- ✅ Plus simple à déboguer

## Limitations

- Moins robuste face aux changements de structure HTML de Zoolyx
- Ne gère pas le JavaScript côté client complexe
- Limité aux 5 premiers rapports pour les tests

## Dépannage

Si la connexion échoue :
1. Vérifier les credentials dans `.env`
2. Vérifier que l'URL de Zoolyx est correcte
3. Vérifier que PetCare est accessible

Si les PDFs ne se téléchargent pas :
- Le sélecteur CSS pour les liens PDF peut avoir changé
- Modifier `downloadReport()` pour adapter les sélecteurs
