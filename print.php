<?php
// Fonction pour obtenir le compteur journalier
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
            file_put_contents($counterFile, $today . '|' . $count);
        } else {
            // Incrémenter le compteur
            $count = intval($count) + 1;
            file_put_contents($counterFile, $today . '|' . $count);
        }
    } else {
        // Créer le fichier de compteur
        $count = 1;
        file_put_contents($counterFile, $today . '|' . $count);
    }
    
    return str_pad($count, 4, '0', STR_PAD_LEFT);
}

// Fonction pour obtenir le code et nom du fournisseur
function getSupplierInfo() {
    $configFile = 'config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (isset($config['supplier']) && is_array($config['supplier'])) {
            // Récupérer le premier (et unique) fournisseur
            $supplierCode = key($config['supplier']);
            $supplierName = $config['supplier'][$supplierCode];
            return ['code' => $supplierCode, 'name' => $supplierName];
        }
    }
    return ['code' => '00', 'name' => 'Unknown'];
}

if (isset($_POST['code_pcba']) && trim($_POST['code_pcba']) !== '') {
    $codeScanne = trim($_POST['code_pcba']);
    
    // Obtenir le code et nom du fournisseur depuis le fichier JSON
    $supplierInfo = getSupplierInfo();
    $supplierCode = $supplierInfo['code'];
    $supplierName = $supplierInfo['name'];
    
    // Obtenir le compteur journalier
    $dailyCounter = getDailyCounter();

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
    $ezpl .= "XRB0,30,4,5,15\r\n";     // DataMatrix: position (0,15), type 4, taille module 3, 15 caractères
    $ezpl .= $codeScanne . "\r\n";    // Injection directe de la valeur scannée
    // Ajouter le code du fournisseur sous le datamatrix (plus petit et plus bas)
    $ezpl .= "AC,5,55,0,0,0,0," . $supplierCode . "\r\n";  // Code fournisseur (ex: 85)
    $ezpl .= "E\r\n";                 // Ordre final d'impression

    // Chemin réseau vers l'imprimante partagée sous Windows
    // Assurez-vous que le nom correspond exactement à celui donné dans le panneau de configuration
    $printerPath = "\\\\localhost\\smtprinter"; 

    // Envoi direct du flux brut à l'imprimante
    if (file_put_contents($printerPath, $ezpl)) {
        // Retourner les informations en JSON pour l'affichage dans l'UI
        echo json_encode([
            'success' => true,
            'supplier' => $supplierName,
            'supplierCode' => $supplierCode,
            'counter' => $dailyCounter
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Impossible de communiquer avec l\'imprimante Godex.']);
    }
} else {
    http_response_code(400);
    echo "Erreur : Aucun code PCBA reçu.";
}
?>