<?php
// Lit les 3 compteurs sans les incrémenter (1 seul fichier)
// Format counter.txt : date|daily|month_key|monthly|year_key|annual
function readCounters() {
    $counterFile = 'counter.txt';
    $today     = date('Y-m-d');
    $thisMonth = date('Y-m');
    $thisYear  = date('Y');

    if (file_exists($counterFile)) {
        $parts   = explode('|', file_get_contents($counterFile));
        $daily   = ($parts[0] === $today)     ? intval($parts[1] ?? 0) : 0;
        $monthly = ($parts[2] === $thisMonth) ? intval($parts[3] ?? 0) : 0;
        $annual  = ($parts[4] === $thisYear)  ? intval($parts[5] ?? 0) : 0;
    } else {
        $daily = $monthly = $annual = 0;
    }

    return [
        'counter'        => str_pad($daily,   4, '0', STR_PAD_LEFT),
        'monthlyCounter' => str_pad($monthly, 5, '0', STR_PAD_LEFT),
        'annualCounter'  => str_pad($annual,  6, '0', STR_PAD_LEFT),
    ];
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

$counters     = readCounters();
$supplierInfo = getSupplierInfo();

header('Content-Type: application/json');
echo json_encode([
    'counter'        => $counters['counter'],
    'monthlyCounter' => $counters['monthlyCounter'],
    'annualCounter'  => $counters['annualCounter'],
    'supplierCode'   => $supplierInfo['code'],
    'supplier'       => $supplierInfo['name']
]);
?>
