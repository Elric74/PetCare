# Guide : Certificat SSL valide avec DuckDNS + Let's Encrypt

## ✅ SOLUTION RECOMMANDÉE : DuckDNS + Let's Encrypt

### Étape 1 : Créer un compte DuckDNS

1. Va sur **https://www.duckdns.org**
2. Clique sur **sign in with google** (ou GitHub/Reddit/Twitter)
3. Une fois connecté, tu arrives sur ton tableau de bord

### Étape 2 : Créer un sous-domaine

1. Dans le champ **sub domain**, tape : `petcare-nathalie` (ou le nom que tu veux)
2. Ton domaine sera : **petcare-nathalie.duckdns.org**
3. Clique sur **add domain**

### Étape 3 : Configurer l'IP

1. Dans le champ **current ip**, DuckDNS détecte automatiquement ton IP publique
2. Si ce n'est pas le cas, va sur https://whatismyip.com pour la trouver
3. Entre ton IP publique dans le champ
4. Clique sur **update ip**

⚠️ **IMPORTANT** : Note ton **token** (en haut de la page DuckDNS), tu en auras besoin !

### Étape 4 : Ouvrir le port 443 sur ta box internet

1. Connecte-toi à l'interface de ta box (généralement http://192.168.1.1)
2. Va dans **NAT/PAT** ou **Redirection de ports**
3. Crée une règle :
   - **Port externe** : 443
   - **Port interne** : 888 (ou le port de ton DSM)
   - **IP locale** : 192.168.0.10 (IP de ton NAS)
   - **Protocole** : TCP

Si tu utilises le port 888 pour DSM, tu peux aussi rediriger :
   - **Port externe** : 888
   - **Port interne** : 888
   - **IP locale** : 192.168.0.10

### Étape 5 : Configurer DDNS sur le NAS Synology

1. Ouvre **DSM** (https://192.168.0.10:5001)
2. Va dans **Panneau de configuration** → **Connectivité externe** → **DDNS**
3. Clique sur **Ajouter**
4. Configure :
   - **Fournisseur de services** : Sélectionne **DuckDNS**
   - **Nom d'hôte** : `petcare-nathalie.duckdns.org` (ton sous-domaine complet)
   - **Nom d'utilisateur/Email** : laisse vide ou mets n'importe quoi
   - **Mot de passe/Clé** : colle ton **token** DuckDNS
5. Clique sur **OK**
6. Vérifie que le statut est **Normal** (peut prendre 1-2 minutes)

### Étape 6 : Obtenir le certificat Let's Encrypt

1. Dans DSM, va dans **Panneau de configuration** → **Sécurité** → **Certificat**
2. Clique sur **Ajouter** → **Ajouter un nouveau certificat** → **Obtenir un certificat auprès de Let's Encrypt**
3. Remplis :
   - **Nom de domaine** : `petcare-nathalie.duckdns.org`
   - **Email** : ton adresse email
   - **Nom de domaine alternatif** : laisse vide
4. Clique sur **Appliquer**

⏳ Le processus prend 1-2 minutes. Let's Encrypt va vérifier que tu contrôles le domaine.

### Étape 7 : Assigner le certificat aux services

1. Une fois le certificat obtenu, clique sur **Configurer** (dans l'interface des certificats)
2. Assigne le certificat Let's Encrypt à :
   - ✅ **System default**
   - ✅ **DSM Desktop Service**
   - ✅ **Web Station** (si présent)
   - ✅ Tous les autres services que tu utilises
3. Clique sur **OK**

### Étape 8 : Redémarrer les services

Dans DSM, redémarre les services web :
- Va dans **Panneau de configuration** → **Services d'applications** → **Web Station**
- Arrête et redémarre le service

Ou redémarre le NAS complètement.

### Étape 9 : Tester l'accès

**Depuis l'extérieur (4G/5G ou autre réseau) :**
- ✅ `https://petcare-nathalie.duckdns.org:888`

**Depuis ton réseau local :**
- ✅ `https://192.168.0.10:888` (toujours fonctionnel)
- ✅ `https://petcare-nathalie.duckdns.org:888` (fonctionnera aussi)

**Sur iPhone/iPad :**
- Ouvre Safari
- Va sur `https://petcare-nathalie.duckdns.org:888`
- ✅ **Pas d'alerte SSL !** Certificat valide !

### 🔄 Renouvellement automatique

Let's Encrypt renouvelle automatiquement le certificat tous les 90 jours. Synology s'en occupe tout seul.

---

## Dépannage

### ❌ "Le certificat n'a pas pu être obtenu"
- Vérifie que le port 80 OU 443 est bien redirigé vers ton NAS
- Attends 5 minutes que le DNS se propage
- Vérifie que DuckDNS a bien ton IP publique à jour

### ❌ "Impossible d'accéder au site"
- Vérifie la redirection de port sur ta box
- Teste depuis ton téléphone en 4G (pas en WiFi)

### ❌ "Le site ne charge pas depuis l'iPhone"
- Assure-toi d'utiliser **https://** (pas http://)
- Vérifie que le port 888 est bien ouvert

---

# ALTERNATIVE : Certificat auto-signé (si DuckDNS ne fonctionne pas)

## Étape 1 : Créer un certificat avec SAN (Subject Alternative Name)

Sur ton PC Windows (dans PowerShell) :

```powershell
# Créer le certificat
$cert = New-SelfSignedCertificate `
    -Subject "CN=PetCare NAS" `
    -DnsName "192.168.0.10", "petcare.local" `
    -CertStoreLocation "Cert:\LocalMachine\My" `
    -KeyExportPolicy Exportable `
    -KeySpec Signature `
    -KeyLength 2048 `
    -KeyAlgorithm RSA `
    -HashAlgorithm SHA256 `
    -NotAfter (Get-Date).AddYears(10)

# Exporter le certificat
$password = ConvertTo-SecureString -String "VotreMdp123" -Force -AsPlainText
Export-PfxCertificate -Cert $cert -FilePath "C:\PetCare-Certificate.pfx" -Password $password
Export-Certificate -Cert $cert -FilePath "C:\PetCare-Certificate.crt"
```

## Étape 2 : Installer sur le NAS

1. Copie `PetCare-Certificate.pfx` sur le NAS
2. DSM → Panneau de configuration → Sécurité → Certificat
3. Ajouter → Importer un certificat
4. Sélectionne le fichier .pfx et entre le mot de passe

## Étape 3 : Installer le certificat sur les appareils

### Sur iPhone/iPad :
1. Envoie-toi `PetCare-Certificate.crt` par email ou AirDrop
2. Ouvre le fichier → Profil téléchargé
3. Réglages → Général → VPN et gestion de l'appareil
4. Installe le profil
5. **IMPORTANT** : Réglages → Général → Informations → Réglages des certificats
6. Active **"Autorisation totale"** pour le certificat

### Sur Android :
1. Copie `PetCare-Certificate.crt` sur le téléphone
2. Paramètres → Sécurité → Chiffrement et identifiants → Installer un certificat
3. Choisis "Certificat CA" et sélectionne le fichier

### Sur PC Windows :
Double-clic sur `PetCare-Certificate.crt` → Installer le certificat → Ordinateur local → 
Placer dans "Autorités de certification racines de confiance"

## Étape 4 : Configurer le hosts file (optionnel)

Au lieu d'utiliser l'IP, utilise un nom :

**Sur PC :** Édite `C:\Windows\System32\drivers\etc\hosts`
```
192.168.0.10    petcare.local
```

**Sur iPhone/iPad :** Impossible sans jailbreak, utilise l'IP

## Résultat
Accède à https://192.168.0.10:888 ou https://petcare.local:888 sans alerte SSL

---

## ⚠️ Limitation
Les certificats auto-signés nécessitent d'être installés sur CHAQUE appareil.
Pour une solution universelle sans installation, utilise Let's Encrypt + DuckDNS.
