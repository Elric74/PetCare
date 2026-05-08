<?php
// Étape 1 : Charger la page de connexion
$loginPageUrl = 'https://www2.zoolyx.be/myzoolyx/login';
$loginActionUrl = 'https://www2.zoolyx.be/myzoolyx/login_check';
$targetUrl = 'https://www2.zoolyx.be/myzoolyx/veterinary/report/index?page=1';

// Informations d'identification
$username = 'vt.nath.staffe@gmail.com';
$password = '!Formule1';

// Initialisation cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginPageUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, '/volume2/web/vms/manuel-modif/cookie.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, '/volume2/web/vms/manuel-modif/cookie.txt');

$html = curl_exec($ch);

// Extraire le jeton CSRF
preg_match('/<input type="hidden" name="_csrf_token" value="([^"]+)"/', $html, $matches);
if (isset($matches[1])) {
    $csrfToken = $matches[1];
    echo "Jeton CSRF récupéré : $csrfToken\n";
} else {
    die("Impossible de récupérer le jeton CSRF !");
}

// Étape 2 : Soumettre le formulaire de connexion
curl_setopt($ch, CURLOPT_URL, $loginActionUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    '_username' => $username,
    '_password' => $password,
    '_csrf_token' => $csrfToken,
    '_remember_me' => 'on'
]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Autoriser les redirections
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'Referer: https://www2.zoolyx.be/myzoolyx/login'
]);

$response = curl_exec($ch);

// Étape 3 : Charger la page protégée
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_POST, false);

$protectedPage = curl_exec($ch);
curl_close($ch);
 //echo $protectedPage; // Afficher ou traiter la page protégée
 ?>
 <?php



// Charger la page protégée après l'authentification
//$protectedPage = '<Ton HTML ici>'; // Le contenu HTML provenant de la page

// Utiliser preg_match pour extraire les informations de la première ligne
//preg_match_all(
  //  '/<a href="\/myzoolyx\/report\/view\/([^"]+)"[^>]*>.*?<div class="col-xs-8 text-wrap">\s*([^<]+).*?<div class="col-sm-12 col-md-6 text-wrap">\s*([^<]+).*?<div class="hidden-sm col-md-6 text-wrap">\s*([^<]*)/s',
  //  $protectedPage,
  //  $matches  , PREG_SET_ORDER
// );

     // Vérifier si des correspondances ont été trouvées
//if (empty($matches)) {
  //  die("Aucune donnée trouvée dans le tableau !");
// }

  // Regex pour extraire les données
preg_match_all(
    // '/<a href="\/myzoolyx\/report\/view\/([^"]+)"[^>]*>.*?<img [^>]*alt="([^"]+)"[^>]*>.*?<div class="col-xs-8 text-wrap">\s*([^<]+).*?<div class="col-xs-6 col-sm-3 col-md-3 text-wrap">\s*([^<]+).*?<div class="col-sm-12 col-md-6 text-wrap">\s*([^<]+)/s',
      '/<a href="\/myzoolyx\/report\/view\/([^"]+)"[^>]*>.*?<img [^>]*alt="([^"]+)"[^>]*>.*?<div class="col-xs-8 text-wrap">\s*([^<]+).*?<div class="col-xs-6 col-sm-3 col-md-3 text-wrap">\s*([^<]+).*?<div class="col-sm-12 col-md-6 text-wrap">\s*([^<]+).*?<span class="report_completed">\s*([^<]+)<\/span>/s',
      $protectedPage,
    $matches,
    PREG_SET_ORDER
);







// Étape 2 : Connexion à la base de données
$servername = "localhost"; // Adresse du serveur
$username = "root"; // Nom d'utilisateur MySQL
$password = "!!Keys1974!!"; // Mot de passe MySQL
$dbname = "vms"; // Nom de la base de données

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Échec de la connexion à la base de données : " . $conn->connect_error);
}
echo "Connexion à la base de données réussie.\n";


// Préparer la requête d'insertion
$sqlInsert = "INSERT INTO Prises (nom_animal, ref_interne, url_globale, proprio, ref_zoolyx, type, datecompleted ) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmtInsert = $conn->prepare($sqlInsert);

foreach ($matches as $match) {
    // Extraire les données nécessaires
    $uniqueId = $match[1];        // Identifiant unique (ex. : 6wWOBxaE)
    $type = $match[2];            // Type d'animal (ex. : dog)
    $animalName = trim($match[3]); // Nom de l'animal (ex. : Rose)
    $owner = isset($match[4]) ? trim($match[4]) : ''; // Propriétaire (ex. : Marie Aline Duhamel)
    $refzoolyx = trim($match[5]); // Référence zoolyx (ex. : www+1PHVFE0)
    $datecompleted = isset($match[6]) ? trim($match[6]) : ""; // Date completed (ex. : 2024-06-10) 
    $extraField = isset($match[7]) ? trim($match[7]) : '10'; // Extrafield (vide ou non)
    $uniqueUrl = 'https://www2.zoolyx.be/myzoolyx/report/view/' . $uniqueId;

    // Étape 1 : Vérifier si `refzoolyx` existe déjà dans la table
    $sqlCheck = "SELECT COUNT(*) FROM Prises WHERE ref_zoolyx = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("s", $refzoolyx);
    $stmtCheck->execute();
    $stmtCheck->bind_result($count);
    $stmtCheck->fetch();
    $stmtCheck->close();

    if ($count > 0) {
        echo "Refzoolyx déjà présent : $refzoolyx\n";
        continue; // Passer à la prochaine entrée
    }

    // Étape 2 : Insérer l'enregistrement si `refzoolyx` n'existe pas
    $stmtInsert->bind_param("sssssss", $animalName, $extraField, $uniqueUrl, $owner, $refzoolyx, $type, $datecompleted);
    if ($stmtInsert->execute()) {
        echo "Données insérées : Nom = $animalName, Référence interne = $extraField, URL = $uniqueUrl, Propriétaire = $owner, Référence zoolyx = $refzoolyx, Type = $type , Date rapport complet  =     $datecompleted \n";
    } else {
        echo "Erreur lors de l'insertion de : $animalName\n";
    }
}

// Fermer la connexion après toutes les opérations
$stmtInsert->close();
$conn->close();



echo "Processus terminé : Toutes les entrées ont été traitées.\n";

 // Fermer la connexion après toutes les opérations
//$stmtInsert->close();
//$conn->close();

echo "Processus terminé : Toutes les entrées ont été traitées.\n";


               ?>

