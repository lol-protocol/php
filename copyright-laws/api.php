<?php
/**
 * Copyright Laws API
 * Endpoints for accessing copyright laws by jurisdiction
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

function loadJurisdictionsIndex() {
    $csvPath = __DIR__ . '/jurisdictions/copyright_laws_master.csv';
    if (!file_exists($csvPath)) {
        return sendResponse(['error' => 'CSV file not found'], 404);
    }

    $jurisdictions = [];
    $file = fopen($csvPath, 'r');
    $headers = fgetcsv($file);

    while (($row = fgetcsv($file)) !== false) {
        $data = array_combine($headers, $row);
        $code = trim($data['country_code']);

        if (!isset($jurisdictions[$code])) {
            $jurisdictions[$code] = [
                'code' => $code,
                'name' => trim($data['country_name']),
                'tld' => strtolower($code),
                'lawCount' => 0
            ];
        }
        $jurisdictions[$code]['lawCount']++;
    }

    fclose($file);
    return array_values($jurisdictions);
}

function loadJurisdictionLaws($tld) {
    $jsonPath = __DIR__ . "/jurisdictions/{$tld}/laws.json";
    if (!file_exists($jsonPath)) {
        return sendResponse(['error' => "Jurisdiction data not found for TLD: {$tld}"], 404);
    }

    $content = file_get_contents($jsonPath);
    return json_decode($content, true);
}

function loadJurisdictionInfo($tld) {
    $infoPath = __DIR__ . "/jurisdictions/{$tld}/info.json";
    if (!file_exists($infoPath)) {
        return sendResponse(['error' => "Jurisdiction info not found for TLD: {$tld}"], 404);
    }

    $content = file_get_contents($infoPath);
    return json_decode($content, true);
}

function loadJurisdictionCSV($tld) {
    $csvPath = __DIR__ . "/jurisdictions/{$tld}/laws.csv";
    if (!file_exists($csvPath)) {
        return sendResponse(['error' => "Jurisdiction CSV not found for TLD: {$tld}"], 404);
    }

    return file_get_contents($csvPath);
}

// Route requests
if ($action === 'jurisdictions') {
    $jurisdictions = loadJurisdictionsIndex();
    sendResponse(['data' => $jurisdictions]);
}
elseif ($action === 'jurisdiction') {
    if (!$tld) {
        sendResponse(['error' => 'Missing TLD parameter'], 400);
    }

    $laws = loadJurisdictionLaws($tld);
    if (!is_array($laws)) {
        sendResponse(['error' => 'Invalid jurisdiction data'], 500);
    }

    sendResponse(['data' => $laws]);
}
elseif ($action === 'jurisdiction_info') {
    if (!$tld) {
        sendResponse(['error' => 'Missing TLD parameter'], 400);
    }

    $info = loadJurisdictionInfo($tld);
    if (!is_array($info)) {
        sendResponse(['error' => 'Invalid jurisdiction info'], 500);
    }

    sendResponse(['data' => $info]);
}
elseif ($action === 'jurisdiction_csv') {
    if (!$tld) {
        sendResponse(['error' => 'Missing TLD parameter'], 400);
    }

    header('Content-Type: text/csv');
    echo loadJurisdictionCSV($tld);
    exit;
}
else {
    sendResponse(['error' => 'Unknown action: ' . ($action ?? 'none')], 400);
}
?>
