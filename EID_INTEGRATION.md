# Intégration eID Belge - PetCare

## Installation

### 1. Middleware eID
Télécharge et installe le middleware Belgium eID depuis :
https://eid.belgium.be/fr/telechargements

Après installation, redémarre l'ordinateur.

### 2. Vérification
Ouvre le "eID Viewer" depuis le menu Démarrer pour vérifier que :
- Le lecteur est détecté
- La carte peut être lue
- Les données s'affichent correctement

## Utilisation

### Page de test
Pour tester la lecture eID :

1. Démarre le serveur Laravel :
```powershell
cd c:\xampp\htdocs\PetCare
php artisan serve
```

2. Ouvre dans le navigateur :
```
http://localhost:8000/eid-reader-debug.html
```

3. Clique sur "1. Vérifier Middleware"
4. Si détecté, clique sur "3. Lire via Middleware (Backend)"
5. Ouvre le eID Viewer et insère ta carte
6. Entre ton code PIN quand demandé
7. Les données apparaîtront automatiquement

### API Endpoints

Le service expose 4 endpoints :

- `GET /eid/check` - Vérifie si le middleware est installé
- `GET /eid/read-identity` - Lit uniquement les données d'identité
- `GET /eid/read-photo` - Lit uniquement la photo
- `GET /eid/read-all` - Lit données + photo

Exemple avec curl :
```powershell
curl http://localhost:8000/eid/read-all
```

## Architecture

### Backend (Laravel)
- **Service** : `App\Services\EidReaderService`
  - Gère la communication avec le middleware eID
  - Utilise PowerShell pour exécuter les commandes
  - Parse les données XML/JSON retournées

- **Controller** : `App\Http\Controllers\EidReaderController`
  - Expose l'API REST
  - Retourne les données au format JSON

### Frontend
- **Page de test** : `/public/eid-reader-debug.html`
  - Interface de débogage
  - Affiche les logs en temps réel
  - Visualise les données extraites

### PowerShell Scripts
Les scripts sont générés automatiquement dans :
- `resources/scripts/read-eid.ps1` - Lecture des données
- `resources/scripts/read-eid-photo.ps1` - Lecture de la photo

## Données extraites

- Nom et prénom
- Date et lieu de naissance
- Numéro national
- Adresse complète (rue, code postal, ville)
- Sexe
- Nationalité
- Numéro de carte
- Date de validité
- Photo d'identité (format JPEG base64)

## Prochaines étapes

### Intégration dans le formulaire Client

Pour intégrer dans `/clients/create` ou `/clients/edit` :

1. Ajouter un bouton "Lire eID" dans le formulaire
2. Appeler l'API `/eid/read-all` via axios
3. Remplir automatiquement les champs du formulaire
4. Gérer l'upload de la photo

Exemple de code Vue.js :

```javascript
const readEid = async () => {
  try {
    const response = await axios.get('/eid/read-all')
    if (response.data.success) {
      // Remplir le formulaire
      form.nom = response.data.data.nom
      form.prenom = response.data.data.prenom
      form.date_naissance = response.data.data.date_naissance
      // ... autres champs
      
      // Gérer la photo
      if (response.data.photo) {
        form.photo = response.data.photo
      }
      
      toast.success('Données eID chargées')
    }
  } catch (error) {
    toast.error('Erreur lecture eID')
  }
}
```

## Limitations

- L'utilisateur doit ouvrir manuellement le eID Viewer
- Le code PIN doit être entré dans le eID Viewer
- Fonctionne uniquement sous Windows
- Nécessite PowerShell

## Troubleshooting

### "Middleware non détecté"
- Vérifie que le middleware eID est installé
- Redémarre l'ordinateur après installation
- Vérifie que le service "Belgium eID" est démarré

### "Impossible de lire la carte"
- Ouvre le eID Viewer manuellement
- Insère la carte et entre le PIN
- Vérifie que le lecteur est bien branché
- Teste avec le eID Viewer d'abord

### "Failed to fetch"
- Assure-toi que le serveur Laravel est démarré
- Vérifie l'URL : http://localhost:8000
- Regarde les logs Laravel : `tail -f storage/logs/laravel.log`
