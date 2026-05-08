# Configuration API Médicaments FR

## 📋 À propos

L'API Médicaments FR permet d'interroger la **Base de Données Publique des Médicaments (BDPM)** française pour obtenir les informations sur les médicaments non présents dans votre base locale.

- **Site officiel** : https://api-medicaments.fr
- **Offre gratuite** : 100 requêtes/jour (Starter)
- **Données** : BDPM officielle mise à jour quotidiennement

---

## 🔑 Obtenir une clé API

### 1. Créer un compte

1. Allez sur https://api-medicaments.fr
2. Cliquez sur **"Inscription"** (dans la section Tarifs - Starter)
3. Remplissez le formulaire d'inscription
4. Validez votre email

### 2. Récupérer votre clé API

1. Connectez-vous à votre compte
2. Accédez à votre tableau de bord
3. Copiez votre **API Key**

### 3. Configurer dans PetCare

1. Ouvrez le fichier `.env` à la racine du projet
2. Trouvez la ligne `API_MEDICAMENTS_KEY=`
3. Collez votre clé API :
   ```env
   API_MEDICAMENTS_KEY=votre_cle_api_ici
   ```
4. Sauvegardez le fichier

### 4. Tester

Scannez un code DataMatrix de médicament français non présent dans votre base locale. Le système interrogera automatiquement l'API.

---

## 🔍 Fonctionnement

Quand vous scannez un code-barres sur `/inventory/scan` :

1. **Recherche locale** : Le système cherche d'abord dans votre table `medicaments`
2. **API Externe** : Si non trouvé → interroge l'API Médicaments FR
3. **Enregistrement** : Si trouvé via l'API → enregistre avec `source: 'api_medicaments_fr'`

---

## 📊 Limites

| Offre    | Requêtes/jour | Prix/mois | Utilisateurs |
|----------|---------------|-----------|--------------|
| Starter  | 100           | Gratuit   | 1            |
| Pro      | 3 000         | 19.90€    | 3            |
| Business | 50 000+       | 69.90€    | 10           |

**Recommandation** : L'offre gratuite (Starter) est suffisante pour un usage vétérinaire (quelques scans par jour).

---

## 🐛 Dépannage

### L'API ne fonctionne pas

1. Vérifiez que la clé API est bien configurée dans `.env`
2. Vérifiez les logs Laravel : `storage/logs/laravel.log`
3. Cherchez les lignes contenant `API Medicaments`

### Quota dépassé

Si vous dépassez 100 requêtes/jour :
- Les logs montreront une erreur HTTP 429 (Too Many Requests)
- Passez à l'offre Pro (3000 requêtes/jour)
- Ou attendez le lendemain (quota réinitialisé à minuit)

### Médicament non trouvé

L'API couvre uniquement les **médicaments français** de la BDPM. Les médicaments vétérinaires belges ne seront pas trouvés.

Pour les médicaments belges, utilisez plutôt le système de liaison CNK existant.

---

## 📝 Exemples d'utilisation

### Recherche par nom
```bash
curl -H "Authorization: Bearer VOTRE_CLE" \
  "https://api-medicaments.fr/api/v1/medicaments?nom=doliprane"
```

### Recherche par CIP
```bash
curl -H "Authorization: Bearer VOTRE_CLE" \
  "https://api-medicaments.fr/api/v1/medicaments/cip/3400936404335"
```

### Recherche par CIS
```bash
curl -H "Authorization: Bearer VOTRE_CLE" \
  "https://api-medicaments.fr/api/v1/medicaments/cis/69388890"
```

---

## ✅ Configuration complète

Votre fichier `.env` devrait contenir :

```env
# API Médicaments FR (BDPM) - https://api-medicaments.fr
# Offre Starter gratuite: 100 requêtes/jour
# Inscrivez-vous sur https://api-medicaments.fr pour obtenir votre clé
API_MEDICAMENTS_KEY=votre_cle_api_ici_12345abcdef
```

Une fois configuré, le système utilisera automatiquement l'API quand un médicament n'est pas trouvé localement.
