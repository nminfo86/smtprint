<?php
// INDISPENSABLE : Indiquer que ce fichier répond toujours en JSON
header('Content-Type: application/json');

// Fonction pour obtenir le compteur journalier (et l'incrémenter)
function getDailyCounter() {
    $counterFile = 'counter.txt';
    $today = date('Y-m-d');
    
    // Lire le fichier de compteur
    if (file_exists($counterFile)) {
        $data = file_get_contents($counterFile);
        list($date, $count) = explode('|', $data);
        
        // Si c'est un nouveau jour, réinitialiser le compteur
        if ($date !== $today) {
            $count = 1;
        } else {
            // Incrémenter le compteur
            $count = intval($count) + 1;
        }
    } else {
        // Créer le fichier de compteur pour la première fois
        $count = 1;
    }
    
    // Sauvegarder la nouvelle valeur
    file_put_contents($counterFile, $today . '|' . $count);
    
    return str_pad($count, 4, '0', STR_PAD_LEFT);
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
                'supplierCode' => ['x' => 10, 'y' => 50],
                'date' => ['x' => 5, 'y' => 70]
            ];
            return [
                'code' => $supplierCode, 
                'name' => $supplierName,
                'positions' => $positions
            ];
        }
    }
    return [
        'code' => '00', 
        'name' => 'Unknown',
        'positions' => [
            'datamatrix' => ['x' => 5, 'y' => 10],
            'supplierCode' => ['x' => 10, 'y' => 50],
            'date' => ['x' => 5, 'y' => 70]
        ]
    ];
}

// DÉBUT DU TRAITEMENT PRINCIPAL
if (isset($_POST['code_pcba']) && trim($_POST['code_pcba']) !== '') {
    $codeScanne = trim($_POST['code_pcba']);
    
    // Obtenir le code et nom du fournisseur et les positions
    $supplierInfo = getSupplierInfo();
    $supplierCode = $supplierInfo['code'];
    $supplierName = $supplierInfo['name'];
    $positions = $supplierInfo['positions'];
    
    // Construction du flux de commandes EZPL
    $ezpl = "^XSETCUT,DOUBLECUT,0\r\n";
    $ezpl .= "^Q15,3\r\n";
    $ezpl .= "^W10\r\n";
    $ezpl .= "^H8\r\n";
    $ezpl .= "^P1\r\n";
    $ezpl .= "^S4\r\n";
    $ezpl .= "^AD\r\n";
    $ezpl .= "^C1\r\n";
    $ezpl .= "^R4\r\n";
    $ezpl .= "~Q+0\r\n";
    $ezpl .= "^O0\r\n";
    $ezpl .= "^D0\r\n";
    $ezpl .= "^E18\r\n";
    $ezpl .= "~R255\r\n";
    $ezpl .= "^L\r\n";
    $ezpl .= "Dy2-me-dd\r\n";
    $ezpl .= "Th:m:s\r\n";
    
    // Position et configuration du DataMatrix
    $ezpl .= "XRB" . $positions['datamatrix']['x'] . "," . $positions['datamatrix']['y'] . ",3,0,15\r\n";  
    $ezpl .= $codeScanne . "\r\n"; 
    
    // Ajouter le code du fournisseur
    $ezpl .= "AA," . $positions['supplierCode']['x'] . "," . $positions['supplierCode']['y'] . ",0,0,0,0," . $supplierCode . "\r\n";
    
    // Ajouter la date DD/MM/YY
    $ezpl .= "AA," . $positions['date']['x'] . "," . $positions['date']['y'] . ",0,0,0,0," . date('d/m/y') . "\r\n";
    
    $ezpl .= "E\r\n";

    // Chemin réseau
    $printerPath = "\\\\localhost\\smtprinter"; 

    // Envoi direct du flux brut à l'imprimante
    if (file_put_contents($printerPath, $ezpl)) {
        
        // SUCCÈS : On incrémente le compteur SEULEMENT si l'impression a marché
        $dailyCounter = getDailyCounter();

        // Retourner les informations en JSON pour l'UI
        echo json_encode([
            'success' => true,
            'supplier' => $supplierName,
            'supplierCode' => $supplierCode,
            'counter' => $dailyCounter
        ]);
        
    } else {
        // ERREUR D'IMPRESSION
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Impossible de communiquer avec l\'imprimante Godex.'
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