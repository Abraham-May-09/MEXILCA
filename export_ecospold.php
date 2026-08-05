<?php
session_start();
require_once 'php_actions/check_download_permission.php';

// ✅ VERIFICAR PERMISO DE DESCARGA
if (!can_user_download()) {
    http_response_code(403);
    die('⚠️ No tienes permisos para descargar datasets. Contacta a un administrador.');
}

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    exit("Error de configuración.");
}

$config = require $configPath;

$mysqli = @new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
if ($mysqli->connect_errno) {
    http_response_code(500);
    exit("Error de conexión.");
}
$mysqli->set_charset('utf8mb4');

$process_uuid = $_GET['uuid'] ?? null;
if (!$process_uuid) {
    http_response_code(400);
    exit("UUID no proporcionado.");
}

// ============ OBTENER DATOS ============

$stmt = $mysqli->prepare("SELECT p.*, l.name as location_name, l.code as location_code
                          FROM processes p
                          LEFT JOIN locations l ON l.uuid = p.location_uuid
                          WHERE p.uuid = ?");
$stmt->bind_param('s', $process_uuid);
$stmt->execute();
$proceso = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$proceso) {
    http_response_code(404);
    exit("Proceso no encontrado.");
}

// Inputs
$stmt = $mysqli->prepare("SELECT pi.*, f.name as flow_name, f.uuid as flow_uuid,
                          u.name as unit_name, u.uuid as unit_uuid
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
$stmt = $mysqli->prepare("SELECT po.*, f.name as flow_name, f.uuid as flow_uuid,
                          u.name as unit_name, u.uuid as unit_uuid
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

// ============ FUNCIONES AUXILIARES ============

function xmlEscape($text) {
    return htmlspecialchars($text ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    if (empty($date)) return date('Y-m-d');
    $timestamp = strtotime($date);
    return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
}

function formatDateTime($date) {
    if (empty($date)) return date('Y-m-d\\TH:i:s');
    $timestamp = strtotime($date);
    return $timestamp ? date('Y-m-d\\TH:i:s', $timestamp) : date('Y-m-d\\TH:i:s');
}

// ============ GENERAR XML SEGÚN FORMATO OFICIAL ECOINVENT ============

$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

// Root element con todos los namespaces
$ecoSpold = $xml->createElement('ecoSpold');
$ecoSpold->setAttribute('xmlns', 'http://www.EcoInvent.org/EcoSpold02');
$ecoSpold->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
$xml->appendChild($ecoSpold);

// childActivityDataset
$childActivityDataset = $xml->createElement('childActivityDataset');
$ecoSpold->appendChild($childActivityDataset);

// ===== Activity Description =====
$activityDescription = $xml->createElement('activityDescription');
$childActivityDataset->appendChild($activityDescription);

// Activity con TODOS los atributos obligatorios
$activity = $xml->createElement('activity');
$activity->setAttribute('specialActivityType', '0');
$activity->setAttribute('id', $proceso['uuid']);
$activity->setAttribute('activityNameId', $proceso['uuid']);
$activity->setAttribute('parentActivityId', $proceso['uuid']); // Mismo UUID si no tiene padre
$activity->setAttribute('inheritanceDepth', '0');
$activity->setAttribute('type', '1'); // 1 = unit process
$activityDescription->appendChild($activity);

// 1. Activity Name (OBLIGATORIO)
$activityName = $xml->createElement('activityName', xmlEscape($proceso['name']));
$activityName->setAttribute('xml:lang', 'en');
$activity->appendChild($activityName);

// 2. General Comment (OBLIGATORIO) - ANTES de classification
if (!empty($proceso['description'])) {
    $generalComment = $xml->createElement('generalComment');
    $text = $xml->createElement('text', xmlEscape($proceso['description']));
    $text->setAttribute('xml:lang', 'en');
    $text->setAttribute('index', '0');
    $generalComment->appendChild($text);
    $activity->appendChild($generalComment);
}

// 3. Classification (OBLIGATORIO)
$classification = $xml->createElement('classification');
$classification->setAttribute('classificationId', uniqid());
$classificationSystem = $xml->createElement('classificationSystem', 'ISIC rev.4 ecoinvent');
$classificationSystem->setAttribute('xml:lang', 'en');
$classification->appendChild($classificationSystem);
$classificationValue = $xml->createElement('classificationValue', xmlEscape($proceso['category'] ?? 'Manufacturing'));
$classificationValue->setAttribute('xml:lang', 'en');
$classification->appendChild($classificationValue);
$activity->appendChild($classification);

// 4. Geography (OBLIGATORIO)
$geography = $xml->createElement('geography');
$geography->setAttribute('geographyId', $proceso['location_uuid'] ?? '34dbbff8-88ce-11de-ad60-0019e336be3a');
$shortName = $xml->createElement('shortname', xmlEscape($proceso['location_code'] ?? 'GLO'));
$shortName->setAttribute('xml:lang', 'en');
$geography->appendChild($shortName);
$activity->appendChild($geography);

// 5. Technology (OBLIGATORIO)
$technology = $xml->createElement('technology');
$technology->setAttribute('technologyLevel', '3');
if (!empty($proceso['tech_desc'])) {
    $techComment = $xml->createElement('comment');
    $techText = $xml->createElement('text', xmlEscape($proceso['tech_desc']));
    $techText->setAttribute('xml:lang', 'en');
    $techText->setAttribute('index', '0');
    $techComment->appendChild($techText);
    $technology->appendChild($techComment);
}
$activity->appendChild($technology);

// 6. Time Period (OBLIGATORIO)
$timePeriod = $xml->createElement('timePeriod');
$timePeriod->setAttribute('startDate', formatDate($proceso['valid_from'] ?? '2020-01-01'));
$timePeriod->setAttribute('endDate', formatDate($proceso['valid_until'] ?? '2030-12-31'));
$timePeriod->setAttribute('isDataValidForEntirePeriod', 'true');
$activity->appendChild($timePeriod);

// 7. macroEconomicScenario (OBLIGATORIO según ecoinvent)
$macroEconomicScenario = $xml->createElement('macroEconomicScenario');
$macroEconomicScenario->setAttribute('macroEconomicScenarioId', 'd9f57f0a-a01f-42eb-a57b-8f18d6635801');
$macroName = $xml->createElement('name', 'Business-as-Usual');
$macroName->setAttribute('xml:lang', 'en');
$macroEconomicScenario->appendChild($macroName);
$activity->appendChild($macroEconomicScenario);

// ===== Flow Data =====
$flowData = $xml->createElement('flowData');
$childActivityDataset->appendChild($flowData);

// Inputs
foreach ($inputs as $input) {
    $exchange = $xml->createElement('intermediateExchange');
    $exchange->setAttribute('id', $input['flow_uuid'] ?? uniqid());
    $exchange->setAttribute('unitId', $input['unit_uuid'] ?? uniqid());
    $exchange->setAttribute('amount', $input['amount'] ?? 0);
    $exchange->setAttribute('intermediateExchangeId', $input['flow_uuid'] ?? uniqid());

    // inputGroup PRIMERO (orden correcto según ecoinvent)
    $inputGroup = $xml->createElement('inputGroup', '5'); // 5 = from technosphere
    $exchange->appendChild($inputGroup);

    // name
    $name = $xml->createElement('name', xmlEscape($input['flow_name'] ?? 'Unknown'));
    $name->setAttribute('xml:lang', 'en');
    $exchange->appendChild($name);

    // unitName
    $unitName = $xml->createElement('unitName', xmlEscape($input['unit_name'] ?? 'kg'));
    $unitName->setAttribute('xml:lang', 'en');
    $exchange->appendChild($unitName);

    // comment si existe
    if (!empty($input['description'])) {
        $comment = $xml->createElement('comment');
        $text = $xml->createElement('text', xmlEscape($input['description']));
        $text->setAttribute('xml:lang', 'en');
        $text->setAttribute('index', '0');
        $comment->appendChild($text);
        $exchange->appendChild($comment);
    }

    $flowData->appendChild($exchange);
}

// Outputs
foreach ($outputs as $output) {
    $exchange = $xml->createElement('intermediateExchange');
    $exchange->setAttribute('id', $output['flow_uuid'] ?? uniqid());
    $exchange->setAttribute('unitId', $output['unit_uuid'] ?? uniqid());
    $exchange->setAttribute('amount', $output['amount'] ?? 0);

    // Si es producto de referencia
    if ($output['is_reference']) {
        $exchange->setAttribute('isCalculatedAmount', 'false');
    }

    $exchange->setAttribute('intermediateExchangeId', $output['flow_uuid'] ?? uniqid());

    // outputGroup PRIMERO
    if ($output['is_reference']) {
        $outputGroup = $xml->createElement('outputGroup', '0'); // 0 = reference product
    } else {
        $outputGroup = $xml->createElement('outputGroup', '2'); // 2 = by-product
    }
    $exchange->appendChild($outputGroup);

    // name
    $name = $xml->createElement('name', xmlEscape($output['flow_name'] ?? 'Unknown'));
    $name->setAttribute('xml:lang', 'en');
    $exchange->appendChild($name);

    // unitName
    $unitName = $xml->createElement('unitName', xmlEscape($output['unit_name'] ?? 'kg'));
    $unitName->setAttribute('xml:lang', 'en');
    $exchange->appendChild($unitName);

    // comment si existe
    if (!empty($output['description'])) {
        $comment = $xml->createElement('comment');
        $text = $xml->createElement('text', xmlEscape($output['description']));
        $text->setAttribute('xml:lang', 'en');
        $text->setAttribute('index', '0');
        $comment->appendChild($text);
        $exchange->appendChild($comment);
    }

    $flowData->appendChild($exchange);
}

// ===== modellingAndValidation =====
$modellingAndValidation = $xml->createElement('modellingAndValidation');
$childActivityDataset->appendChild($modellingAndValidation);

$representativeness = $xml->createElement('representativeness');
$systemModelId = $xml->createElement('systemModelId', '290c1f85-4cc4-4fa1-b0c8-2cb7f4276dce');
$representativeness->appendChild($systemModelId);
$systemModelName = $xml->createElement('systemModelName', 'Allocation, cut-off by classification');
$systemModelName->setAttribute('xml:lang', 'en');
$representativeness->appendChild($systemModelName);
$modellingAndValidation->appendChild($representativeness);

// ===== Administrative Information =====
$adminInfo = $xml->createElement('administrativeInformation');
$childActivityDataset->appendChild($adminInfo);

// Data Entry By (OBLIGATORIO)
$dataEntryByEl = $xml->createElement('dataEntryBy');
$dataEntryByEl->setAttribute('personId', '00000000-0000-0000-0000-000000000001');
$dataEntryByEl->setAttribute('isActiveAuthor', 'false');
$dataEntryByEl->setAttribute('personName', 'System Administrator');
$dataEntryByEl->setAttribute('personEmail', 'admin@example.com');
$adminInfo->appendChild($dataEntryByEl);

// Data Generator And Publication (OBLIGATORIO)
$dataGenPub = $xml->createElement('dataGeneratorAndPublication');
$dataGenPub->setAttribute('personId', '00000000-0000-0000-0000-000000000001');
$dataGenPub->setAttribute('personName', 'System Administrator');
$dataGenPub->setAttribute('personEmail', 'admin@example.com');
$dataGenPub->setAttribute('dataPublishedIn', '0');
$dataGenPub->setAttribute('publishedSourceId', '00000000-0000-0000-0000-000000000002');
$dataGenPub->setAttribute('publishedSourceYear', date('Y'));
$dataGenPub->setAttribute('isCopyrightProtected', 'false');
$dataGenPub->setAttribute('accessRestrictedTo', '0');
$adminInfo->appendChild($dataGenPub);

// File Attributes (OBLIGATORIO)
$fileAttributes = $xml->createElement('fileAttributes');
$fileAttributes->setAttribute('majorRelease', '3');
$fileAttributes->setAttribute('minorRelease', '0');
$fileAttributes->setAttribute('majorRevision', '0');
$fileAttributes->setAttribute('minorRevision', '0');
$fileAttributes->setAttribute('internalSchemaVersion', '2.0.12');
$fileAttributes->setAttribute('defaultLanguage', 'en');
$fileAttributes->setAttribute('creationTimestamp', formatDateTime(null));
$fileAttributes->setAttribute('lastEditTimestamp', formatDateTime(null));
$fileAttributes->setAttribute('fileGenerator', 'Custom LCA System');
$fileAttributes->setAttribute('fileTimestamp', formatDateTime(null));
$fileAttributes->setAttribute('contextId', uniqid());

$contextName = $xml->createElement('contextName', 'ecoinvent');
$contextName->setAttribute('xml:lang', 'en');
$fileAttributes->appendChild($contextName);

$adminInfo->appendChild($fileAttributes);

// ============ DESCARGAR ARCHIVO ============

$filename = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower($proceso['name']));
$filename = trim($filename, '_') . '_' . ($proceso['location_code'] ?? 'GLO') . '.spold';

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo $xml->saveXML();
exit;
?>