<?php
/**
 * Privacy Laws API
 * Endpoints for accessing privacy laws by country
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? null;
$tld = strtolower($_GET['tld'] ?? '');

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function loadCountriesIndex() {
    $csvPath = __DIR__ . '/countries/privacy_laws_master.csv';
    if (!file_exists($csvPath)) {
        return sendResponse(['error' => 'CSV file not found'], 404);
    }

    $countries = [];
    $file = fopen($csvPath, 'r');
    $headers = fgetcsv($file);

    while (($row = fgetcsv($file)) !== false) {
        $data = array_combine($headers, $row);
        $code = trim($data['country_code']);

        if (!isset($countries[$code])) {
            $countries[$code] = [
                'code' => $code,
                'name' => trim($data['country_name']),
                'tld' => strtolower($code),
                'lawCount' => 0
            ];
        }
        $countries[$code]['lawCount']++;
    }

    fclose($file);
    return array_values($countries);
}

function loadCountryLaws($tld) {
    $jsonPath = __DIR__ . "/countries/{$tld}/laws.json";
    if (!file_exists($jsonPath)) {
        return sendResponse(['error' => "Country data not found for TLD: {$tld}"], 404);
    }

    $content = file_get_contents($jsonPath);
    return json_decode($content, true);
}

function loadCountryInfo($tld) {
    $infoPath = __DIR__ . "/countries/{$tld}/info.json";
    if (!file_exists($infoPath)) {
        return sendResponse(['error' => "Country info not found for TLD: {$tld}"], 404);
    }

    $content = file_get_contents($infoPath);
    return json_decode($content, true);
}

function loadCountryCSV($tld) {
    $csvPath = __DIR__ . "/countries/{$tld}/laws.csv";
    if (!file_exists($csvPath)) {
        return sendResponse(['error' => "Country CSV not found for TLD: {$tld}"], 404);
    }

    return file_get_contents($csvPath);
}

// Route requests
if ($action === 'countries') {
    $countries = loadCountriesIndex();
    sendResponse(['data' => $countries]);
}
elseif ($action === 'country') {
    if (!$tld) {
        sendResponse(['error' => 'Missing TLD parameter'], 400);
    }

    $laws = loadCountryLaws($tld);
    if (!is_array($laws)) {
        sendResponse(['error' => 'Invalid country data'], 500);
    }

    sendResponse(['data' => $laws]);
}
elseif ($action === 'country_info') {
    if (!$tld) {
        sendResponse(['error' => 'Missing TLD parameter'], 400);
    }

    $info = loadCountryInfo($tld);
    if (!is_array($info)) {
        sendResponse(['error' => 'Invalid country info'], 500);
    }

    sendResponse(['data' => $info]);
}
elseif ($action === 'country_csv') {
    if (!$tld) {
        sendResponse(['error' => 'Missing TLD parameter'], 400);
    }

    header('Content-Type: text/csv');
    echo loadCountryCSV($tld);
    exit;
}
else {
    sendResponse(['error' => 'Unknown action: ' . ($action ?? 'none')], 400);
}
?>
