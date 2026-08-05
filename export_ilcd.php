<?php
// export_ecospold_v1.php - Exportador EcoSpold v1 OFICIAL según especificación ecoinvent
// Basado en: Documentation EcoSpold 1.3 (26. October 2008)
// Namespace: http://www.EcoInvent.org/EcoSpold01

ini_set('display_errors', 0);
error_reporting(0);
set_time_limit(300);
ini_set('memory_limit', '512M');

ob_start();
session_start();

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    ob_end_clean();
    http_response_code(500);
    exit("Error de configuración.");
}

$config = require $configPath;
$mysqli = @new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
if ($mysqli->connect_errno) {
    ob_end_clean();
    http_response_code(500);
    exit("Error de conexión.");
}
$mysqli->set_charset('utf8mb4');

$process_uuid = $_GET['uuid'] ?? null;
if (!$process_uuid) {
    ob_end_clean();
    http_response_code(400);
    exit("UUID no proporcionado.");
}

// ============ FUNCIONES AUXILIARES ============

function xmlEscape($text) {
    return htmlspecialchars($text ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    if (empty($date)) return date('Y-m-d');
    $timestamp = strtotime($date);
    return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
}

// ============ OBTENER DATOS COMPLETOS ============

// Proceso principal
$stmt = $mysqli->prepare("SELECT p.*, l.name as location_name, l.code as location_code
                          FROM processes p
                          LEFT JOIN locations l ON l.uuid = p.location_uuid
                          WHERE p.uuid = ?");
$stmt->bind_param('s', $process_uuid);
$stmt->execute();
$proceso = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$proceso) {
    ob_end_clean();
    http_response_code(404);
    exit("Proceso no encontrado.");
}

// Documentación
$stmt = $mysqli->prepare("SELECT * FROM process_documentation WHERE process_uuid = ?");
$stmt->bind_param('s', $process_uuid);
$stmt->execute();
$documentacion = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Actores
$stmt = $mysqli->prepare("SELECT pa.role, a.* FROM process_actors pa
                          JOIN actors a ON a.uuid = pa.actor_uuid
                          WHERE pa.process_uuid = ?");
$stmt->bind_param('s', $process_uuid);
$stmt->execute();
$actores_result = $stmt->get_result();
$actores = [];
$actor_numbers = [];
$actor_counter = 1;
while ($row = $actores_result->fetch_assoc()) {
    $actores[] = $row;
    $actor_numbers[$row['uuid']] = $actor_counter++;
}
$stmt->close();

// Inputs
$stmt = $mysqli->prepare("SELECT pi.*, f.name as flow_name, f.uuid as flow_uuid, f.cas,
                          u.name as unit_name
                          FROM process_inputs pi
                          LEFT JOIN flows f ON f.uuid = pi.flow_uuid
                          LEFT JOIN units u ON u.uuid = pi.unit_uuid
                          WHERE pi.process_uuid = ?
                          ORDER BY pi.internal_id ASC");
$stmt->bind_param('s', $process_uuid);
$stmt->execute();
$inputs_result = $stmt->get_result();
$inputs = [];
while ($row = $inputs_result->fetch_assoc()) $inputs[] = $row;
$stmt->close();

// Outputs
$stmt = $mysqli->prepare("SELECT po.*, f.name as flow_name, f.uuid as flow_uuid, f.cas,
                          u.name as unit_name
                          FROM process_outputs po
                          LEFT JOIN flows f ON f.uuid = po.flow_uuid
                          LEFT JOIN units u ON u.uuid = po.unit_uuid
                          WHERE po.process_uuid = ?
                          ORDER BY po.internal_id ASC");
$stmt->bind_param('s', $process_uuid);
$stmt->execute();
$outputs_result = $stmt->get_result();
$outputs = [];
while ($row = $outputs_result->fetch_assoc()) $outputs[] = $row;
$stmt->close();

$mysqli->close();

// ============ GENERAR XML ECOSPOLD v1 OFICIAL ============

$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

// Root element con namespace OFICIAL de ecoinvent
$ecoSpold = $xml->createElement('ecoSpold');
$ecoSpold->setAttribute('xmlns', 'http://www.EcoInvent.org/EcoSpold01');
$ecoSpold->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
$ecoSpold->setAttribute('xsi:schemaLocation', 'http://www.EcoInvent.org/EcoSpold01 EcoSpold01Dataset.xsd');
$xml->appendChild($ecoSpold);

// ===== DATASET =====
$dataset = $xml->createElement('dataset');
$dataset->setAttribute('number', '1');
$dataset->setAttribute('internalSchemaVersion', '1.0');
$dataset->setAttribute('generator', 'CREAA Export Tool');
$dataset->setAttribute('timestamp', date('Y-m-d\TH:i:s'));
$ecoSpold->appendChild($dataset);

// ===== META INFORMATION =====
$metaInformation = $xml->createElement('metaInformation');
$dataset->appendChild($metaInformation);

// ===== 1. PROCESS INFORMATION =====
$processInformation = $xml->createElement('processInformation');
$metaInformation->appendChild($processInformation);

// 1.1 ReferenceFunction (REQUIRED)
$referenceFunction = $xml->createElement('referenceFunction');
$processInformation->appendChild($referenceFunction);

$datasetRelatesToProduct = $xml->createElement('datasetRelatesToProduct', 'true');
$referenceFunction->appendChild($datasetRelatesToProduct);

$name = $xml->createElement('name', xmlEscape($proceso['name']));
$name->setAttribute('xml:lang', 'en');
$referenceFunction->appendChild($name);

if (!empty($proceso['name'])) {
    $localName = $xml->createElement('localName', xmlEscape($proceso['name']));
    $localName->setAttribute('xml:lang', 'es');
    $referenceFunction->appendChild($localName);
}

$infrastructureProcess = $xml->createElement('infrastructureProcess', 'false');
$referenceFunction->appendChild($infrastructureProcess);

// Reference amount
$referenceAmount = 1.0;
foreach ($outputs as $output) {
    if ($output['is_reference']) {
        $referenceAmount = floatval($output['amount'] ?? 1.0);
        break;
    }
}
$amount = $xml->createElement('amount', $referenceAmount);
$referenceFunction->appendChild($amount);

// Reference unit
$referenceUnit = 'kg';
foreach ($outputs as $output) {
    if ($output['is_reference']) {
        $referenceUnit = $output['unit_name'] ?? 'kg';
        break;
    }
}
$unit = $xml->createElement('unit', xmlEscape($referenceUnit));
$referenceFunction->appendChild($unit);

// Category
if (!empty($proceso['category'])) {
    $parts = explode('/', $proceso['category']);
    $category = $xml->createElement('category', xmlEscape($parts[0]));
    $category->setAttribute('xml:lang', 'en');
    $referenceFunction->appendChild($category);
    
    if (count($parts) > 1) {
        $subCategory = $xml->createElement('subCategory', xmlEscape($parts[1]));
        $subCategory->setAttribute('xml:lang', 'en');
        $referenceFunction->appendChild($subCategory);
    }
}

// General comment
if (!empty($proceso['description'])) {
    $generalComment = $xml->createElement('generalComment', xmlEscape($proceso['description']));
    $generalComment->setAttribute('xml:lang', 'en');
    $referenceFunction->appendChild($generalComment);
}

// CAS Number
$refCAS = '';
foreach ($outputs as $output) {
    if ($output['is_reference'] && !empty($output['cas'])) {
        $refCAS = $output['cas'];
        break;
    }
}
if ($refCAS) {
    $CASNumber = $xml->createElement('CASNumber', $refCAS);
    $referenceFunction->appendChild($CASNumber);
}

// 1.2 Geography (REQUIRED)
$geography = $xml->createElement('geography');
$processInformation->appendChild($geography);

$location = $xml->createElement('location', $proceso['location_code'] ?? 'GLO');
$geography->appendChild($location);

if (!empty($proceso['geo_desc'])) {
    $text = $xml->createElement('text', xmlEscape($proceso['geo_desc']));
    $text->setAttribute('xml:lang', 'en');
    $geography->appendChild($text);
}

// 1.3 Technology
$technology = $xml->createElement('technology');
$processInformation->appendChild($technology);

if (!empty($proceso['tech_desc'])) {
    $techText = $xml->createElement('text', xmlEscape($proceso['tech_desc']));
    $techText->setAttribute('xml:lang', 'en');
    $technology->appendChild($techText);
}

// 1.4 TimePeriod (REQUIRED)
$timePeriod = $xml->createElement('timePeriod');
$processInformation->appendChild($timePeriod);

$startDate = $xml->createElement('startDate', formatDate($proceso['valid_from'] ?? null));
$timePeriod->appendChild($startDate);

$endDate = $xml->createElement('endDate', formatDate($proceso['valid_until'] ?? null));
$timePeriod->appendChild($endDate);

$dataValidForEntirePeriod = $xml->createElement('dataValidForEntirePeriod', 'true');
$timePeriod->appendChild($dataValidForEntirePeriod);

if (!empty($proceso['time_desc'])) {
    $timeText = $xml->createElement('text', xmlEscape($proceso['time_desc']));
    $timeText->setAttribute('xml:lang', 'en');
    $timePeriod->appendChild($timeText);
}

// 1.5 DataSetInformation (REQUIRED)
$dataSetInformation = $xml->createElement('dataSetInformation');
$processInformation->appendChild($dataSetInformation);

$type = $xml->createElement('type', '1');
$dataSetInformation->appendChild($type);

$impactAssessmentResult = $xml->createElement('impactAssessmentResult', 'false');
$dataSetInformation->appendChild($impactAssessmentResult);

$timestamp = $xml->createElement('timestamp', date('Y-m-d\TH:i:s'));
$dataSetInformation->appendChild($timestamp);

$version = $xml->createElement('version', $proceso['version'] ?? '1.0');
$dataSetInformation->appendChild($version);

$internalVersion = $xml->createElement('internalVersion', '1.0');
$dataSetInformation->appendChild($internalVersion);

// ===== 2. MODELLING AND VALIDATION =====
$modellingAndValidation = $xml->createElement('modellingAndValidation');
$metaInformation->appendChild($modellingAndValidation);

// 2.1 Representativeness
$representativeness = $xml->createElement('representativeness');
$modellingAndValidation->appendChild($representativeness);

$percent = $xml->createElement('percent', '100');
$representativeness->appendChild($percent);

if (!empty($documentacion['ds_data_completeness'])) {
    $reprText = $xml->createElement('text', xmlEscape($documentacion['ds_data_completeness']));
    $reprText->setAttribute('xml:lang', 'en');
    $representativeness->appendChild($reprText);
}

// 2.2 Sources (REQUIRED)
$sources = $xml->createElement('sources');
$modellingAndValidation->appendChild($sources);

$source = $xml->createElement('source');
$source->setAttribute('number', '1');
$sources->appendChild($source);

$sourceType = $xml->createElement('sourceType', '0');
$source->appendChild($sourceType);

$firstAuthor = $xml->createElement('firstAuthor', 'CREAA');
$source->appendChild($firstAuthor);

$sourceYear = $xml->createElement('year', date('Y'));
$source->appendChild($sourceYear);

$sourceTitle = $xml->createElement('title', xmlEscape($proceso['name']));
$sourceTitle->setAttribute('xml:lang', 'en');
$source->appendChild($sourceTitle);

// ===== 3. ADMINISTRATIVE INFORMATION =====
$administrativeInformation = $xml->createElement('administrativeInformation');
$metaInformation->appendChild($administrativeInformation);

// 3.1 DataEntryBy (REQUIRED)
$dataEntryBy = $xml->createElement('dataEntryBy');
$administrativeInformation->appendChild($dataEntryBy);

$dataEntryPerson = 1;
foreach ($actores as $actor) {
    if (in_array(strtolower($actor['role'] ?? ''), ['owner', 'data owner', 'documentor'])) {
        $dataEntryPerson = $actor_numbers[$actor['uuid']];
        break;
    }
}
$person = $xml->createElement('person', $dataEntryPerson);
$person->setAttribute('number', $dataEntryPerson);
$dataEntryBy->appendChild($person);

$qualityNetwork = $xml->createElement('qualityNetwork', '0');
$dataEntryBy->appendChild($qualityNetwork);

// 3.2 DataGeneratorAndPublication (REQUIRED)
$dataGeneratorAndPublication = $xml->createElement('dataGeneratorAndPublication');
$administrativeInformation->appendChild($dataGeneratorAndPublication);

$generatorPerson = 1;
foreach ($actores as $actor) {
    if (in_array(strtolower($actor['role'] ?? ''), ['generator', 'data generator', 'author'])) {
        $generatorPerson = $actor_numbers[$actor['uuid']];
        break;
    }
}
$generatorPersonEl = $xml->createElement('person', $generatorPerson);
$generatorPersonEl->setAttribute('number', $generatorPerson);
$dataGeneratorAndPublication->appendChild($generatorPersonEl);

$dataPublishedIn = $xml->createElement('dataPublishedIn', '0');
$dataGeneratorAndPublication->appendChild($dataPublishedIn);

$referenceToPublishedSource = $xml->createElement('referenceToPublishedSource', '1');
$referenceToPublishedSource->setAttribute('number', '1');
$dataGeneratorAndPublication->appendChild($referenceToPublishedSource);

if (isset($documentacion['copyright_flag'])) {
    $copyright = $xml->createElement('copyright', $documentacion['copyright_flag'] ? 'true' : 'false');
    $dataGeneratorAndPublication->appendChild($copyright);
}

$accessRestrictedTo = $xml->createElement('accessRestrictedTo', '0');
$dataGeneratorAndPublication->appendChild($accessRestrictedTo);

// 3.3 Person (REQUIRED)
$persons = $xml->createElement('person');
$administrativeInformation->appendChild($persons);

foreach ($actores as $actor) {
    $personEl = $xml->createElement('person');
    $personEl->setAttribute('number', $actor_numbers[$actor['uuid']]);
    $persons->appendChild($personEl);
    
    $personName = $xml->createElement('name', xmlEscape($actor['name']));
    $personEl->appendChild($personName);
    
    $address = $xml->createElement('address', xmlEscape($actor['address'] ?? ''));
    $address->setAttribute('xml:lang', 'en');
    $personEl->appendChild($address);
    
    if (!empty($actor['email'])) {
        $email = $xml->createElement('email', xmlEscape($actor['email']));
        $personEl->appendChild($email);
    }
    
    $countryCode = $xml->createElement('countryCode', 'MX');
    $personEl->appendChild($countryCode);
}

// Si no hay actores, agregar uno dummy
if (empty($actores)) {
    $personEl = $xml->createElement('person');
    $personEl->setAttribute('number', '1');
    $persons->appendChild($personEl);
    
    $personName = $xml->createElement('name', 'CREAA');
    $personEl->appendChild($personName);
    
    $address = $xml->createElement('address', 'Mexico');
    $address->setAttribute('xml:lang', 'en');
    $personEl->appendChild($address);
    
    $countryCode = $xml->createElement('countryCode', 'MX');
    $personEl->appendChild($countryCode);
}

// ===== FLOW DATA =====
$flowData = $xml->createElement('flowData');
$dataset->appendChild($flowData);

// ===== EXCHANGES =====
$exchangeId = 1;

// Inputs
foreach ($inputs as $input) {
    $exchange = $xml->createElement('exchange');
    $exchange->setAttribute('number', $exchangeId);
    $flowData->appendChild($exchange);
    
    $refToSource = $xml->createElement('referenceToSource', '1');
    $refToSource->setAttribute('number', '1');
    $exchange->appendChild($refToSource);
    
    $inputGroup = $xml->createElement('inputGroup', '4');
    $exchange->appendChild($inputGroup);
    
    $exchangeName = $xml->createElement('name', xmlEscape($input['flow_name']));
    $exchangeName->setAttribute('xml:lang', 'en');
    $exchange->appendChild($exchangeName);
    
    $meanValue = $xml->createElement('meanValue', floatval($input['amount'] ?? 0));
    $exchange->appendChild($meanValue);
    
    $exchangeUnit = $xml->createElement('unit', xmlEscape($input['unit_name'] ?? 'kg'));
    $exchange->appendChild($exchangeUnit);
    
    if (!empty($input['description'])) {
        $exchangeComment = $xml->createElement('generalComment', xmlEscape($input['description']));
        $exchangeComment->setAttribute('xml:lang', 'en');
        $exchange->appendChild($exchangeComment);
    }
    
    $exchangeId++;
}

// Outputs
foreach ($outputs as $output) {
    $exchange = $xml->createElement('exchange');
    $exchange->setAttribute('number', $exchangeId);
    $flowData->appendChild($exchange);
    
    $refToSource = $xml->createElement('referenceToSource', '1');
    $refToSource->setAttribute('number', '1');
    $exchange->appendChild($refToSource);
    
    if ($output['is_reference']) {
        $outputGroup = $xml->createElement('outputGroup', '0');
    } else {
        $outputGroup = $xml->createElement('outputGroup', '4');
    }
    $exchange->appendChild($outputGroup);
    
    $exchangeName = $xml->createElement('name', xmlEscape($output['flow_name']));
    $exchangeName->setAttribute('xml:lang', 'en');
    $exchange->appendChild($exchangeName);
    
    $meanValue = $xml->createElement('meanValue', floatval($output['amount'] ?? 0));
    $exchange->appendChild($meanValue);
    
    $exchangeUnit = $xml->createElement('unit', xmlEscape($output['unit_name'] ?? 'kg'));
    $exchange->appendChild($exchangeUnit);
    
    if (!empty($output['description'])) {
        $exchangeComment = $xml->createElement('generalComment', xmlEscape($output['description']));
        $exchangeComment->setAttribute('xml:lang', 'en');
        $exchange->appendChild($exchangeComment);
    }
    
    $exchangeId++;
}

// ============ LIMPIAR BUFFER Y ENVIAR ============

ob_end_clean();

$filename = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower($proceso['name']));
$filename = trim($filename, '_') . '_' . ($proceso['location_code'] ?? 'GLO') . '_ecospold_v1.xml';

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

echo $xml->saveXML();
exit;
?>
