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

// Fonction pour obtenir le code et nom du fournisseur, les positions et les réglages du lot
function getSupplierInfo() {
    $configFile = 'config.json';
    $defaultPositions = [
        'datamatrix'   => ['x' => 5,  'y' => 10],
        'number'       => ['x' => 30, 'y' => 10],
        'supplierCode' => ['x' => 10, 'y' => 50],
        'date'         => ['x' => 5,  'y' => 70]
    ];

    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (isset($config['supplier']) && is_array($config['supplier'])) {
            $supplierCode = key($config['supplier']);
            $supplierName = $config['supplier'][$supplierCode];
            $positions    = isset($config['positions']) ? array_merge($defaultPositions, $config['positions']) : $defaultPositions;
            $printerName  = isset($config['printerName']) ? $config['printerName'] : 'smtprinter';
            $maxRange     = isset($config['batchMaxRange']) ? intval($config['batchMaxRange']) : 500;
            $delayMs      = isset($config['batchDelayMs']) ? intval($config['batchDelayMs']) : 1000;
            return [
                'code' => $supplierCode,
                'name' => $supplierName,
                'positions' => $positions,
                'printerName' => $printerName,
                'maxRange' => $maxRange,
                'delayMs' => $delayMs
            ];
        }
    }
    return [
        'code' => '00',
        'name' => 'Unknown',
        'positions' => $defaultPositions,
        'printerName' => 'smtprinter',
        'maxRange' => 500,
        'delayMs' => 1000
    ];
}

// Construit le flux EZPL pour une étiquette (datamatrix + numéro lisible + fournisseur + date)
function buildEzpl($code, $supplierCode, $positions) {
    $ezpl  = "^XSETCUT,DOUBLECUT,0\r\n";
    $ezpl .= "^Q15,3\r\n";
    $ezpl .= "^W25\r\n";
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

    // Numéro du datamatrix en clair
    $ezpl .= "AA," . $positions['number']['x'] . "," . $positions['number']['y'] . ",0,0,0,0," . $code . "\r\n";

    // DataMatrix
    $ezpl .= "XRB" . $positions['datamatrix']['x'] . "," . $positions['datamatrix']['y'] . ",4,0,15\r\n";
    $ezpl .= $code . "\r\n";

     // Code fournisseur
    $ezpl .= "AA," . $positions['supplierCode']['x'] . "," . $positions['supplierCode']['y'] . ",0,0,0,0," . "-" . "\r\n";

    // Code fournisseur
    $ezpl .= "AA," . $positions['supplierCode']['x'] . "," . $positions['supplierCode']['y'] . ",0,0,0,0," . $supplierCode . "\r\n";

    // Date DD/MM/YY
    $ezpl .= "AA," . $positions['date']['x'] . "," . $positions['date']['y'] . ",0,0,0,0," . date('d/m/y') . "\r\n";

    $ezpl .= "E\r\n";
    return $ezpl;
}

// Débogage : conserver le dernier flux EZPL généré (dernier code de la plage) pour vérifier visuellement son contenu
function dumpLastEzpl($ezpl) {
    @file_put_contents('last_label.ezpl.txt', $ezpl);
}

// DÉBUT DU TRAITEMENT PRINCIPAL
$startRaw = isset($_POST['start']) ? trim($_POST['start']) : '';
$endRaw   = isset($_POST['end'])   ? trim($_POST['end'])   : '';

if ($startRaw === '' || $endRaw === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Veuillez saisir un code de début et de fin.']);
    exit;
}

if (!ctype_digit($startRaw) || !ctype_digit($endRaw)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Les codes doivent contenir uniquement des chiffres. Vérifiez la langue de saisie.']);
    exit;
}

$start = intval($startRaw);
$end   = intval($endRaw);
$width = max(strlen($startRaw), strlen($endRaw));

if ($end < $start) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Le code de fin doit être supérieur ou égal au code de début.']);
    exit;
}

$supplierInfo = getSupplierInfo();
$total = $end - $start + 1;

if ($total > $supplierInfo['maxRange']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Plage trop grande (' . $total . ' étiquettes). Maximum autorisé : ' . $supplierInfo['maxRange'] . '.'
    ]);
    exit;
}

$supplierCode = $supplierInfo['code'];
$supplierName = $supplierInfo['name'];
$positions    = $supplierInfo['positions'];
$printerName  = $supplierInfo['printerName'];
$printerPath  = "\\\\localhost\\" . $printerName;
$delayMs      = $supplierInfo['delayMs'];

$printed = [];
$failed  = [];
$counters = null;

for ($i = $start; $i <= $end; $i++) {
    $code = str_pad((string)$i, $width, '0', STR_PAD_LEFT);
    $ezpl = buildEzpl($code, $supplierCode, $positions);
    dumpLastEzpl($ezpl);

    if (@file_put_contents($printerPath, $ezpl)) {
        $counters = getCounters();
        $printed[] = $code;
    } else {
        $lastError = error_get_last();
        $failed[] = ['code' => $code, 'detail' => $lastError ? $lastError['message'] : 'Erreur inconnue'];
        break; // On arrête le lot dès qu'une étiquette échoue
    }

    if ($delayMs > 0) {
        usleep($delayMs * 1000);
    }
}

if (empty($failed)) {
    echo json_encode([
        'success' => true,
        'supplier' => $supplierName,
        'supplierCode' => $supplierCode,
        'printedCount' => count($printed),
        'printedCodes' => $printed,
        'counter' => $counters['counter'],
        'monthlyCounter' => $counters['monthlyCounter'],
        'annualCounter' => $counters['annualCounter']
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Impossible de communiquer avec l\'imprimante Godex.',
        'printedCount' => count($printed),
        'printedCodes' => $printed,
        'failed' => $failed,
        'printerPath' => $printerPath,
        'counter' => $counters['counter'] ?? null,
        'monthlyCounter' => $counters['monthlyCounter'] ?? null,
        'annualCounter' => $counters['annualCounter'] ?? null
    ]);
}
?>
