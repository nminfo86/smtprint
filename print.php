<?php
// INDISPENSABLE : Indiquer que ce fichier répond toujours en JSON
ini_set('display_errors', 0); // Ne jamais afficher les erreurs PHP en HTML (corrompt le JSON)
error_reporting(E_ALL);       // Les enregistrer dans les logs serveur uniquement
header('Content-Type: application/json');

// Incrémente et retourne les 3 compteurs dans un seul fichier
// Format counter.txt : date|daily|month_key|monthly|year_key|annual
function getCounters() {
    $counterFile = 'counter.txt';
    $today     = date('Y-m-d');
    $thisMonth = date('Y-m');
    $thisYear  = date('Y');

    if (file_exists($counterFile)) {
        $parts     = explode('|', file_get_contents($counterFile));
        $lastDate  = $parts[0] ?? $today;
        $daily     = intval($parts[1] ?? 0);
        $lastMonth = $parts[2] ?? $thisMonth;
        $monthly   = intval($parts[3] ?? 0);
        $lastYear  = $parts[4] ?? $thisYear;
        $annual    = intval($parts[5] ?? 0);

        $daily   = ($lastDate  !== $today)     ? 1 : $daily   + 1;
        $monthly = ($lastMonth !== $thisMonth) ? 1 : $monthly + 1;
        $annual  = ($lastYear  !== $thisYear)  ? 1 : $annual  + 1;
    } else {
        $daily = $monthly = $annual = 1;
    }

    file_put_contents($counterFile,
        $today . '|' . $daily . '|' . $thisMonth . '|' . $monthly . '|' . $thisYear . '|' . $annual
    );

    return [
        'counter'        => str_pad($daily,   4, '0', STR_PAD_LEFT),
        'monthlyCounter' => str_pad($monthly, 5, '0', STR_PAD_LEFT),
        'annualCounter'  => str_pad($annual,  6, '0', STR_PAD_LEFT),
    ];
}

// Fonction pour obtenir le code et nom du fournisseur et les positions
function getSupplierInfo() {
    $configFile = 'config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (isset($config['supplier']) && is_array($config['supplier'])) {
            $supplierCode = key($config['supplier']);
            $supplierName = $config['supplier'][$supplierCode];
            $positions = isset($config['positions']) ? $config['positions'] : [
                'datamatrix' => ['x' => 5, 'y' => 10],
                'number' => ['x' => 30, 'y' => 10],
                'supplierCode' => ['x' => 10, 'y' => 50],
                'date' => ['x' => 5, 'y' => 70]
            ];
            $printerName = isset($config['printerName']) ? $config['printerName'] : 'smtprinter';
            return [
                'code' => $supplierCode, 
                'name' => $supplierName,
                'positions' => $positions,
                'printerName' => $printerName
            ];
        }
    }
    return [
        'code' => '00', 
        'name' => 'Unknown',
        'positions' => [
            'datamatrix' => ['x' => 5, 'y' => 10],
            'number' => ['x' => 30, 'y' => 10],
            'supplierCode' => ['x' => 10, 'y' => 50],
            'date' => ['x' => 5, 'y' => 70]
        ],
        'printerName' => 'smtprinter'
    ];
}

// DÉBUT DU TRAITEMENT PRINCIPAL
if (isset($_POST['code_pcba']) && trim($_POST['code_pcba']) !== '') {
    $codeScanne = trim($_POST['code_pcba']);
    
    // Vérifier que le code contient uniquement des chiffres
    if (!ctype_digit($codeScanne)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Le code doit contenir uniquement des chiffres. Vérifiez la langue de saisie.'
        ]);
        exit;
    }
    
    // Obtenir le code et nom du fournisseur et les positions
    $supplierInfo = getSupplierInfo();
    $supplierCode = $supplierInfo['code'];
    $supplierName = $supplierInfo['name'];
    $positions = $supplierInfo['positions'];
    $printerName = $supplierInfo['printerName'];
    
    // Construction du flux de commandes EZPL
    $ezpl = "^XSETCUT,DOUBLECUT,0\r\n"; // Mode de découpe : double coupe activée
    $ezpl .= "^Q15,3\r\n";              // *** TAILLE ÉTIQUETTE (HAUTEUR) *** : longueur = 15 mm, espace inter-étiquette (gap) = 3 mm
    $ezpl .= "^W25\r\n";               // *** TAILLE ÉTIQUETTE (LARGEUR) ***  : largeur = 25 mm (→ étiquette 25 × 15 mm)
    $ezpl .= "^H8\r\n";               // Vitesse de la tête d'impression (head speed) = 8
    $ezpl .= "^P1\r\n";               // Nombre de copies à imprimer = 1
    $ezpl .= "^S4\r\n";               // Vitesse d'impression = 4 (ips)
    $ezpl .= "^AD\r\n";               // Sélection de la police de caractères : police D
    $ezpl .= "^C1\r\n";               // Mode de découpe : coupe automatique activée (1)
    $ezpl .= "^R4\r\n";               // Type de ruban (ribbon) = 4
    $ezpl .= "~Q+0\r\n";              // Décalage de position verticale (offset) = 0
    $ezpl .= "^O0\r\n";               // Orientation de l'impression = 0 (normal, sans rotation)
    $ezpl .= "^D0\r\n";               // Densité d'impression (darkness) = 0 (valeur par défaut)
    $ezpl .= "^E18\r\n";              // Énergie d'impression (print energy) = 18
    $ezpl .= "~R255\r\n";             // Point de référence (reference point) = 255
    $ezpl .= "^L\r\n";                // Début du format de l'étiquette (Label start)
    $ezpl .= "Dy2-me-dd\r\n";         // Format de la date interne : AA-MM-JJ (ex : 26-06-24)
    $ezpl .= "Th:m:s\r\n";            // Format de l'heure interne : HH:MM:SS
    
    // Ajouter le numéro du datamatrix en clair
    $ezpl .= "AA," . $positions['number']['x'] . "," . $positions['number']['y'] . ",0,0,0,0," . $codeScanne . "\r\n";
    
    // Position et configuration du DataMatrix
    $ezpl .= "XRB" . $positions['datamatrix']['x'] . "," . $positions['datamatrix']['y'] . ",4,0,15\r\n";  
    $ezpl .= $codeScanne . "\r\n"; 
    
    // Ajouter le code du fournisseur
    $ezpl .= "AA," . $positions['supplierCode']['x'] . "," . $positions['supplierCode']['y'] . ",0,0,0,0," . $supplierCode . "\r\n";
    
    // Ajouter la date DD/MM/YY
    $ezpl .= "AA," . $positions['date']['x'] . "," . $positions['date']['y'] . ",0,0,0,0," . date('d/m/y') . "\r\n";
    
    $ezpl .= "E\r\n";

    // Débogage : conserver le dernier flux EZPL envoyé pour vérifier visuellement son contenu
    @file_put_contents('last_label.ezpl.txt', $ezpl);

    // Chemin réseau
    $printerPath = "\\\\localhost\\" . $printerName; 

    // Envoi direct du flux brut à l'imprimante
    if (@file_put_contents($printerPath, $ezpl)) { // @ supprime le warning HTML, error_get_last() le capture
        
        // SUCCÈS : On incrémente le compteur SEULEMENT si l'impression a marché
        $counters = getCounters();

        // Retourner les informations en JSON pour l'UI
        echo json_encode([
            'success' => true,
            'supplier' => $supplierName,
            'supplierCode' => $supplierCode,
            'counter' => $counters['counter'],
            'monthlyCounter' => $counters['monthlyCounter'],
            'annualCounter' => $counters['annualCounter']
        ]);
        
    } else {
        // ERREUR D'IMPRESSION
        $lastError = error_get_last();
        $errorDetail = $lastError ? $lastError['message'] : 'Erreur inconnue';
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Impossible de communiquer avec l\'imprimante Godex.',
            'detail' => $errorDetail,
            'printerPath' => $printerPath
        ]);
    }
} else {
    // ERREUR DE DONNÉES : Renvoyer du JSON, pas du texte brut
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Aucun code PCBA reçu.'
    ]);
}
?>