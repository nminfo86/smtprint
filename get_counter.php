<?php
// Récupérer le compteur actuel sans l'incrémenter
function getCurrentCounter() {
    $counterFile = 'counter.txt';
    $today = date('Y-m-d');
    
    if (file_exists($counterFile)) {
        $data = file_get_contents($counterFile);
        list($date, $count) = explode('|', $data);
        
        // Si c'est un nouveau jour, le compteur est à 0
        if ($date !== $today) {
            return '0000';
        } else {
            return str_pad($count, 4, '0', STR_PAD_LEFT);
        }
    }
    
    return '0000';
}

// Récupérer les infos du fournisseur
function getSupplierInfo() {
    $configFile = 'config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (isset($config['supplier']) && is_array($config['supplier'])) {
            $supplierCode = key($config['supplier']);
            $supplierName = $config['supplier'][$supplierCode];
            return ['code' => $supplierCode, 'name' => $supplierName];
        }
    }
    return ['code' => '00', 'name' => 'Unknown'];
}

$counter = getCurrentCounter();
$supplierInfo = getSupplierInfo();

header('Content-Type: application/json');
echo json_encode([
    'counter' => $counter,
    'supplierCode' => $supplierInfo['code'],
    'supplier' => $supplierInfo['name']
]);
?>
