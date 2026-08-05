<?php
// export_excel_COMPLETO.php - Excel con 15 PESTAÑAS (SOLO datos del proceso)
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(180);
ini_set('memory_limit', '256M');

session_start();

// Verificar vendor
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("ERROR: PhpSpreadsheet no instalado. Ejecuta install_phpspreadsheet_COMPLETE.php primero.");
}

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// Config
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

// ============ OBTENER DATOS (SOLO RELACIONADOS AL PROCESO) ============

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

$stmt = $mysqli->prepare("SELECT * FROM process_documentation WHERE process_uuid = ?");
$stmt->bind_param('s', $process_uuid);
$stmt->execute();
$documentacion = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Actors - SOLO del proceso
$stmt = $mysqli->prepare("SELECT pa.role, a.* FROM process_actors pa
                          JOIN actors a ON a.uuid = pa.actor_uuid
                          WHERE pa.process_uuid = ?");
$stmt->bind_param('s', $process_uuid);
$stmt->execute();
$actores_result = $stmt->get_result();
$actores = [];
while ($row = $actores_result->fetch_assoc()) $actores[] = $row;
$stmt->close();

// Inputs
$stmt = $mysqli->prepare("SELECT pi.*, f.name as flow_name, 
                          CONCAT(f.category, '/', IFNULL(p.norma_isic, '')) as flow_category,
                          u.name as unit_name, loc.name as location_name
                          FROM process_inputs pi
                          LEFT JOIN flows f ON f.uuid = pi.flow_uuid
                          LEFT JOIN units u ON u.uuid = pi.unit_uuid
                          LEFT JOIN locations loc ON loc.uuid = pi.location_uuid
                          LEFT JOIN processes p ON p.uuid = pi.process_uuid
                          WHERE pi.process_uuid = ?
                          ORDER BY pi.internal_id ASC");
$stmt->bind_param('s', $process_uuid);
$stmt->execute();
$inputs_result = $stmt->get_result();
$inputs = [];
while ($row = $inputs_result->fetch_assoc()) $inputs[] = $row;
$stmt->close();

// Outputs
$stmt = $mysqli->prepare("SELECT po.*, f.name as flow_name,
                          CONCAT(f.category, '/', IFNULL(p.norma_isic, '')) as flow_category,
                          u.name as unit_name, loc.name as location_name
                          FROM process_outputs po
                          LEFT JOIN flows f ON f.uuid = po.flow_uuid
                          LEFT JOIN units u ON u.uuid = po.unit_uuid
                          LEFT JOIN locations loc ON loc.uuid = po.location_uuid
                          LEFT JOIN processes p ON p.uuid = po.process_uuid
                          WHERE po.process_uuid = ?
                          ORDER BY po.internal_id ASC");
$stmt->bind_param('s', $process_uuid);
$stmt->execute();
$outputs_result = $stmt->get_result();
$outputs = [];
while ($row = $outputs_result->fetch_assoc()) $outputs[] = $row;
$stmt->close();

// Flows - SOLO los usados en inputs/outputs
$flow_uuids = [];
foreach ($inputs as $input) {
    if (!empty($input['flow_uuid'])) $flow_uuids[] = $input['flow_uuid'];
}
foreach ($outputs as $output) {
    if (!empty($output['flow_uuid'])) $flow_uuids[] = $output['flow_uuid'];
}
$flow_uuids = array_unique($flow_uuids);

$flows = [];
if (!empty($flow_uuids)) {
    $placeholders = implode(',', array_fill(0, count($flow_uuids), '?'));
    $stmt = $mysqli->prepare("SELECT f.*, CONCAT(f.category, '/', IFNULL(p.norma_isic, '')) as category_with_isic 
                              FROM flows f
                              LEFT JOIN processes p ON p.uuid = ?
                              WHERE f.uuid IN ($placeholders)");
    $params = array_merge([$process_uuid], $flow_uuids);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $flows[] = $row;
    $stmt->close();
}

// Flow properties - SOLO las usadas por los flows del proceso
$flowProperties = [];
if (!empty($flows)) {
    $fp_uuids = [];
    foreach ($flows as $flow) {
        if (isset($flow['flow_property_uuid']) && $flow['flow_property_uuid']) {
            $fp_uuids[] = $flow['flow_property_uuid'];
        }
    }
    $fp_uuids = array_unique($fp_uuids);
    
    if (!empty($fp_uuids)) {
        $placeholders = implode(',', array_fill(0, count($fp_uuids), '?'));
        $stmt = $mysqli->prepare("SELECT fp.*, ug.name as unit_group_name FROM flow_properties fp LEFT JOIN unit_groups ug ON ug.uuid = fp.unit_group_uuid WHERE fp.uuid IN ($placeholders)");
        $stmt->bind_param(str_repeat('s', count($fp_uuids)), ...$fp_uuids);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $flowProperties[] = $row;
        $stmt->close();
    }
}

// Units y Unit groups - SOLO los usados en el proceso
$unit_uuids = [];
foreach ($inputs as $input) {
    if (!empty($input['unit_uuid'])) $unit_uuids[] = $input['unit_uuid'];
}
foreach ($outputs as $output) {
    if (!empty($output['unit_uuid'])) $unit_uuids[] = $output['unit_uuid'];
}
$unit_uuids = array_unique($unit_uuids);

$units = [];
$unitGroups = [];
if (!empty($unit_uuids)) {
    $placeholders = implode(',', array_fill(0, count($unit_uuids), '?'));
    $stmt = $mysqli->prepare("SELECT u.*, ug.name as unit_group_name, ug.uuid as unit_group_uuid FROM units u LEFT JOIN unit_groups ug ON ug.uuid = u.unit_group_uuid WHERE u.uuid IN ($placeholders)");
    $stmt->bind_param(str_repeat('s', count($unit_uuids)), ...$unit_uuids);
    $stmt->execute();
    $result = $stmt->get_result();
    $ug_uuids_temp = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
        if (!empty($row['unit_group_uuid']) && !in_array($row['unit_group_uuid'], $ug_uuids_temp)) {
            $ug_uuids_temp[] = $row['unit_group_uuid'];
        }
    }
    $stmt->close();
    
    // Obtener datos completos de unit groups
    if (!empty($ug_uuids_temp)) {
        $placeholders = implode(',', array_fill(0, count($ug_uuids_temp), '?'));
        $stmt = $mysqli->prepare("SELECT * FROM unit_groups WHERE uuid IN ($placeholders)");
        $stmt->bind_param(str_repeat('s', count($ug_uuids_temp)), ...$ug_uuids_temp);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $unitGroups[] = $row;
        $stmt->close();
    }
}

// Currencies - vacío por defecto
$currencies = [];

// Locations - SOLO la ubicación del proceso y las de inputs/outputs
$location_uuids = [];
if (!empty($proceso['location_uuid'])) $location_uuids[] = $proceso['location_uuid'];
foreach ($inputs as $input) {
    if (!empty($input['location_uuid'])) $location_uuids[] = $input['location_uuid'];
}
foreach ($outputs as $output) {
    if (!empty($output['location_uuid'])) $location_uuids[] = $output['location_uuid'];
}
$location_uuids = array_unique($location_uuids);

$locations = [];
if (!empty($location_uuids)) {
    $placeholders = implode(',', array_fill(0, count($location_uuids), '?'));
    $stmt = $mysqli->prepare("SELECT * FROM locations WHERE uuid IN ($placeholders)");
    $stmt->bind_param(str_repeat('s', count($location_uuids)), ...$location_uuids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $locations[] = $row;
    $stmt->close();
}

// Actors completo
$actoresCompleto = $actores;

// Sources - SOLO las del proceso
$sources = [];
if (isset($documentacion['source_uuid']) && $documentacion['source_uuid']) {
    $stmt = $mysqli->prepare("SELECT * FROM sources WHERE uuid = ?");
    $stmt->bind_param('s', $documentacion['source_uuid']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $sources[] = $row;
    $stmt->close();
}

$mysqli->close();

// Actores por rol
$dataOwner = '';
$dataGenerator = '';
$dataDocumentor = '';
foreach ($actores as $actor) {
    $role = strtolower($actor['role'] ?? '');
    if (in_array($role, ['owner', 'data owner'])) $dataOwner = $actor['name'];
    if (in_array($role, ['generator', 'data generator'])) $dataGenerator = $actor['name'];
    if (in_array($role, ['documentor', 'data documentor'])) $dataDocumentor = $actor['name'];
}
// ============ CREAR SPREADSHEET ============

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

// ========== SHEET 1: General information ==========
$sheet1 = new Worksheet($spreadsheet, 'General information');
$spreadsheet->addSheet($sheet1);

$row = 1;
$sheet1->setCellValue('A' . $row, 'General information');
$sheet1->getStyle('A' . $row)->getFont()->setBold(true);
$row++;
$sheet1->setCellValue('A' . $row, 'UUID');
$sheet1->setCellValue('B' . $row, $proceso['uuid']);
$row++;
$sheet1->setCellValue('A' . $row, 'Name');
$sheet1->setCellValue('B' . $row, $proceso['name']);
$row++;
$sheet1->setCellValue('A' . $row, 'Category');
// Construir el valor como category/norma_isic
$category_value = ($proceso['category'] ?? '');
if (!empty($proceso['norma_isic'])) {
    $category_value .= '/' . $proceso['norma_isic'];
}
$sheet1->setCellValue('B' . $row, $category_value);
$row++;
$sheet1->setCellValue('A' . $row, 'Description');
$sheet1->setCellValue('B' . $row, $proceso['description'] ?? '');
$row++;
$sheet1->setCellValue('A' . $row, 'Version');
$sheet1->setCellValue('B' . $row, $proceso['version'] ?? '00.00.001');
$row++;
$sheet1->setCellValue('A' . $row, 'Last change');
$sheet1->setCellValue('B' . $row, $proceso['last_change'] ?? $proceso['created_at'] ?? '');
$row++;
$sheet1->setCellValue('A' . $row, 'Tags');
$row += 2;
$sheet1->setCellValue('A' . $row, 'Time');
$sheet1->getStyle('A' . $row)->getFont()->setBold(true);
$row++;
$sheet1->setCellValue('A' . $row, 'Valid from');
$sheet1->setCellValue('B' . $row, $proceso['valid_from'] ?? '');
$row++;
$sheet1->setCellValue('A' . $row, 'Valid until');
$sheet1->setCellValue('B' . $row, $proceso['valid_until'] ?? '');
$row++;
$sheet1->setCellValue('A' . $row, 'Description');
$sheet1->setCellValue('B' . $row, $proceso['time_desc'] ?? '');
$row += 2;
$sheet1->setCellValue('A' . $row, 'Geography');
$sheet1->getStyle('A' . $row)->getFont()->setBold(true);
$row++;
$sheet1->setCellValue('A' . $row, 'Location');
$sheet1->setCellValue('B' . $row, $proceso['location_name'] ?? '');
$row++;
$sheet1->setCellValue('A' . $row, 'Description');
$sheet1->setCellValue('B' . $row, $proceso['geo_desc'] ?? '');
$row += 2;
$sheet1->setCellValue('A' . $row, 'Technology');
$sheet1->getStyle('A' . $row)->getFont()->setBold(true);
$row++;
$sheet1->setCellValue('A' . $row, 'Description');
$sheet1->setCellValue('B' . $row, $proceso['tech_desc'] ?? '');
$row += 2;
$sheet1->setCellValue('A' . $row, 'Data quality');
$sheet1->getStyle('A' . $row)->getFont()->setBold(true);
$row++;
$sheet1->setCellValue('A' . $row, 'Process schema');
$row++;
$sheet1->setCellValue('A' . $row, 'Data quality entry');
$row++;
$sheet1->setCellValue('A' . $row, 'Flow schema');
$row++;
$sheet1->setCellValue('A' . $row, 'Social schema');
$sheet1->getColumnDimension('A')->setWidth(25);
$sheet1->getColumnDimension('B')->setWidth(60);

// ========== SHEET 2: Inputs ==========
$sheet2 = new Worksheet($spreadsheet, 'Inputs');
$spreadsheet->addSheet($sheet2);

$headers = ['Is reference?', 'Flow', 'Category', 'Amount', 'Unit', 'Costs/Revenues', 'Currency', 
            'Uncertainty', '(G)Mean | Mode', 'SD | GSD', 'Minimum', 'Maximum', 'Is avoided?', 
            'Provider', 'Data quality entry', 'Location', 'Description'];

for ($col = 0; $col < count($headers); $col++) {
    $sheet2->setCellValueByColumnAndRow($col + 1, 1, $headers[$col]);
    $sheet2->getStyleByColumnAndRow($col + 1, 1)->getFont()->setBold(true);
}

$row = 2;
foreach ($inputs as $input) {
    $sheet2->setCellValue('A' . $row, $input['is_reference'] ? 'x' : '');
    $sheet2->setCellValue('B' . $row, $input['flow_name'] ?? '');
    $sheet2->setCellValue('C' . $row, $input['flow_category'] ?? '');
    $sheet2->setCellValue('D' . $row, $input['amount'] ?? 0);
    $sheet2->setCellValue('E' . $row, $input['unit_name'] ?? '');
    $sheet2->setCellValue('P' . $row, $input['location_name'] ?? '');
    $sheet2->setCellValue('Q' . $row, $input['description'] ?? '');
    $row++;
}

foreach (range('A', 'Q') as $col) {
    $sheet2->getColumnDimension($col)->setAutoSize(true);
}

// ========== SHEET 3: Outputs ==========
$sheet3 = new Worksheet($spreadsheet, 'Outputs');
$spreadsheet->addSheet($sheet3);

for ($col = 0; $col < count($headers); $col++) {
    $sheet3->setCellValueByColumnAndRow($col + 1, 1, $headers[$col]);
    $sheet3->getStyleByColumnAndRow($col + 1, 1)->getFont()->setBold(true);
}

$row = 2;
foreach ($outputs as $output) {
    $sheet3->setCellValue('A' . $row, $output['is_reference'] ? 'x' : '');
    $sheet3->setCellValue('B' . $row, $output['flow_name'] ?? '');
    $sheet3->setCellValue('C' . $row, $output['flow_category'] ?? '');
    $sheet3->setCellValue('D' . $row, $output['amount'] ?? 0);
    $sheet3->setCellValue('E' . $row, $output['unit_name'] ?? '');
    $sheet3->setCellValue('P' . $row, $output['location_name'] ?? '');
    $sheet3->setCellValue('Q' . $row, $output['description'] ?? '');
    $row++;
}

foreach (range('A', 'Q') as $col) {
    $sheet3->getColumnDimension($col)->setAutoSize(true);
}
// ========== SHEET 4: Allocation ==========
$sheet4 = new Worksheet($spreadsheet, 'Allocation');
$spreadsheet->addSheet($sheet4);
$sheet4->setCellValue('A1', 'Default allocation method');
$sheet4->setCellValue('B1', 'none');
$sheet4->getColumnDimension('A')->setWidth(30);

// ========== SHEET 5: Parameters ==========
$sheet5 = new Worksheet($spreadsheet, 'Parameters');
$spreadsheet->addSheet($sheet5);
$sheet5->setCellValue('A1', 'Global input parameters');
$sheet5->getStyle('A1')->getFont()->setBold(true);
$sheet5->setCellValue('A2', 'Name');
$sheet5->setCellValue('B2', 'Value');
$sheet5->setCellValue('C2', 'Uncertainty');
$sheet5->setCellValue('A4', 'Global calculated parameters');
$sheet5->getStyle('A4')->getFont()->setBold(true);
$sheet5->setCellValue('A5', 'Name');
$sheet5->setCellValue('B5', 'Formula');
$sheet5->setCellValue('C5', 'Description');
$sheet5->setCellValue('A7', 'Input parameters');
$sheet5->getStyle('A7')->getFont()->setBold(true);
$sheet5->setCellValue('A10', 'Calculated parameters');
$sheet5->getStyle('A10')->getFont()->setBold(true);
foreach (range('A', 'C') as $col) {
    $sheet5->getColumnDimension($col)->setAutoSize(true);
}

// ========== SHEET 6: Documentation ==========
$sheet6 = new Worksheet($spreadsheet, 'Documentation');
$spreadsheet->addSheet($sheet6);

$row = 1;
$sheet6->setCellValue('A' . $row, 'LCI method');
$sheet6->getStyle('A' . $row)->getFont()->setBold(true);
$row++;
$sheet6->setCellValue('A' . $row, 'Process type');
$sheet6->setCellValue('B' . $row, 'Unit process');
$row++;
$sheet6->setCellValue('A' . $row, 'LCI method');
$sheet6->setCellValue('B' . $row, $documentacion['lci_method'] ?? '');
$row++;
$sheet6->setCellValue('A' . $row, 'Modeling constants');
$sheet6->setCellValue('B' . $row, $documentacion['modeling_constants'] ?? '');
$row += 2;
$sheet6->setCellValue('A' . $row, 'Data source information');
$sheet6->getStyle('A' . $row)->getFont()->setBold(true);
$row++;
$sheet6->setCellValue('A' . $row, 'Data completeness');
$sheet6->setCellValue('B' . $row, $documentacion['ds_data_completeness'] ?? '');
$row++;
$sheet6->setCellValue('A' . $row, 'Data selection');
$sheet6->setCellValue('B' . $row, $documentacion['ds_data_selection'] ?? '');
$row++;
$sheet6->setCellValue('A' . $row, 'Data treatment');
$sheet6->setCellValue('B' . $row, $documentacion['ds_data_treatment'] ?? '');
$row++;
$sheet6->setCellValue('A' . $row, 'Sampling procedure');
$sheet6->setCellValue('B' . $row, $documentacion['ds_sampling_procedure'] ?? '');
$row++;
$sheet6->setCellValue('A' . $row, 'Data collection period');
$sheet6->setCellValue('B' . $row, $documentacion['ds_collection_period'] ?? '');
$row++;
$sheet6->setCellValue('A' . $row, 'Use advice');
$sheet6->setCellValue('B' . $row, $documentacion['ds_use_advice'] ?? '');
$row += 2;
$sheet6->setCellValue('A' . $row, 'Completeness');
$sheet6->getStyle('A' . $row)->getFont()->setBold(true);
$row++;
$sheet6->setCellValue('B' . $row, $documentacion['completeness_text'] ?? '');
$row += 2;
$sheet6->setCellValue('A' . $row, 'Sources');
$sheet6->getStyle('A' . $row)->getFont()->setBold(true);
$row++;
$sheet6->setCellValue('B' . $row, $documentacion['sources_text'] ?? '');
$row += 2;
$sheet6->setCellValue('A' . $row, 'Administrative information');
$sheet6->getStyle('A' . $row)->getFont()->setBold(true);
$row++;
$sheet6->setCellValue('A' . $row, 'Project');
$sheet6->setCellValue('B' . $row, $documentacion['project'] ?? '');
$row++;
$sheet6->setCellValue('A' . $row, 'Intended application');
$sheet6->setCellValue('B' . $row, $documentacion['intended_application'] ?? '');
$row++;
$sheet6->setCellValue('A' . $row, 'Data set owner');
$sheet6->setCellValue('B' . $row, $dataOwner);
$row++;
$sheet6->setCellValue('A' . $row, 'Data set generator');
$sheet6->setCellValue('B' . $row, $dataGenerator);
$row++;
$sheet6->setCellValue('A' . $row, 'Data set documentor');
$sheet6->setCellValue('B' . $row, $dataDocumentor);
$row++;
$sheet6->setCellValue('A' . $row, 'Publication');
$row++;
$sheet6->setCellValue('A' . $row, 'Creation date');
$sheet6->setCellValue('B' . $row, $documentacion['creation_date'] ?? '');
$row++;
$sheet6->setCellValue('A' . $row, 'Copyright');
$sheet6->setCellValue('B' . $row, isset($documentacion['copyright_flag']) && $documentacion['copyright_flag'] ? 'True' : 'False');
$row++;
$sheet6->setCellValue('A' . $row, 'Access and use restrictions');
$sheet6->setCellValue('B' . $row, $documentacion['access_use_restrictions'] ?? '');
$sheet6->getColumnDimension('A')->setWidth(30);
$sheet6->getColumnDimension('B')->setWidth(60);
// ========== SHEET 7: Flows ==========
$sheet7 = new Worksheet($spreadsheet, 'Flows');
$spreadsheet->addSheet($sheet7);

$flowHeaders = ['UUID', 'Name', 'Description', 'Category', 'Version', 'Last change', 'Type', 'CAS', 'Formula', 'Location', 'Reference flow property'];
for ($col = 0; $col < count($flowHeaders); $col++) {
    $sheet7->setCellValueByColumnAndRow($col + 1, 1, $flowHeaders[$col]);
    $sheet7->getStyleByColumnAndRow($col + 1, 1)->getFont()->setBold(true);
}

$row = 2;
foreach ($flows as $flow) {
    $sheet7->setCellValue('A' . $row, $flow['uuid']);
    $sheet7->setCellValue('B' . $row, $flow['name']);
    $sheet7->setCellValue('C' . $row, $flow['description'] ?? '');
    $sheet7->setCellValue('D' . $row, $flow['category_with_isic'] ?? '');  // ← CAMBIO
    $sheet7->setCellValue('E' . $row, $flow['version'] ?? '00.00.000');
    $sheet7->setCellValue('F' . $row, $flow['last_change'] ?? '');
    $sheet7->setCellValue('G' . $row, $flow['flow_type'] ?? '');
    $sheet7->setCellValue('H' . $row, $flow['cas'] ?? '');
    $sheet7->setCellValue('I' . $row, $flow['formula'] ?? '');
    $sheet7->setCellValue('J' . $row, '');
    $sheet7->setCellValue('K' . $row, '');
    $row++;
}

foreach (range('A', 'K') as $col) {
    $sheet7->getColumnDimension($col)->setAutoSize(true);
}

// ========== SHEET 8: Flow properties ==========
$sheet8 = new Worksheet($spreadsheet, 'Flow properties');
$spreadsheet->addSheet($sheet8);

$fpHeaders = ['UUID', 'Name', 'Description', 'Category', 'Version', 'Last change', 'Type', 'Unit group'];
for ($col = 0; $col < count($fpHeaders); $col++) {
    $sheet8->setCellValueByColumnAndRow($col + 1, 1, $fpHeaders[$col]);
    $sheet8->getStyleByColumnAndRow($col + 1, 1)->getFont()->setBold(true);
}

$row = 2;
foreach ($flowProperties as $fp) {
    $sheet8->setCellValue('A' . $row, $fp['uuid']);
    $sheet8->setCellValue('B' . $row, $fp['name']);
    $sheet8->setCellValue('C' . $row, $fp['description'] ?? '');
    $sheet8->setCellValue('D' . $row, $fp['category'] ?? '');
    $sheet8->setCellValue('E' . $row, $fp['version'] ?? '00.00.000');
    $sheet8->setCellValue('F' . $row, $fp['last_change'] ?? '');
    $sheet8->setCellValue('G' . $row, $fp['flow_property_type'] ?? '');
    $sheet8->setCellValue('H' . $row, $fp['unit_group_name'] ?? '');
    $row++;
}

foreach (range('A', 'H') as $col) {
    $sheet8->getColumnDimension($col)->setAutoSize(true);
}

// ========== SHEET 9: Unit groups ==========
$sheet9 = new Worksheet($spreadsheet, 'Unit groups');
$spreadsheet->addSheet($sheet9);

$ugHeaders = ['UUID', 'Name', 'Description', 'Category', 'Version', 'Last change', 'Reference unit', 'Default flow property'];
for ($col = 0; $col < count($ugHeaders); $col++) {
    $sheet9->setCellValueByColumnAndRow($col + 1, 1, $ugHeaders[$col]);
    $sheet9->getStyleByColumnAndRow($col + 1, 1)->getFont()->setBold(true);
}

$row = 2;
foreach ($unitGroups as $ug) {
    $sheet9->setCellValue('A' . $row, $ug['uuid']);
    $sheet9->setCellValue('B' . $row, $ug['name']);
    $sheet9->setCellValue('C' . $row, $ug['description'] ?? '');
    $sheet9->setCellValue('D' . $row, $ug['category'] ?? '');
    $sheet9->setCellValue('E' . $row, $ug['version'] ?? '00.00.000');
    $sheet9->setCellValue('F' . $row, $ug['last_change'] ?? '');
    $sheet9->setCellValue('G' . $row, '');
    $sheet9->setCellValue('H' . $row, '');
    $row++;
}

foreach (range('A', 'H') as $col) {
    $sheet9->getColumnDimension($col)->setAutoSize(true);
}

// ========== SHEET 10: Units ==========
$sheet10 = new Worksheet($spreadsheet, 'Units');
$spreadsheet->addSheet($sheet10);

$unitHeaders = ['Name', 'Description', 'Conversion factor', 'Is reference unit?', 'Synonyms', 'Unit group'];
for ($col = 0; $col < count($unitHeaders); $col++) {
    $sheet10->setCellValueByColumnAndRow($col + 1, 1, $unitHeaders[$col]);
    $sheet10->getStyleByColumnAndRow($col + 1, 1)->getFont()->setBold(true);
}

$row = 2;
foreach ($units as $unit) {
    $sheet10->setCellValue('A' . $row, $unit['name']);
    $sheet10->setCellValue('B' . $row, $unit['description'] ?? '');
    $sheet10->setCellValue('C' . $row, $unit['conversion_factor'] ?? 1.0);
    $sheet10->setCellValue('D' . $row, isset($unit['is_ref_unit']) && $unit['is_ref_unit'] ? 'x' : '');
    $sheet10->setCellValue('E' . $row, $unit['synonyms'] ?? '');
    $sheet10->setCellValue('F' . $row, $unit['unit_group_name'] ?? '');
    $row++;
}

foreach (range('A', 'F') as $col) {
    $sheet10->getColumnDimension($col)->setAutoSize(true);
}
// ========== SHEET 11: Currencies ==========
$sheet11 = new Worksheet($spreadsheet, 'Currencies');
$spreadsheet->addSheet($sheet11);

$currencyHeaders = ['UUID', 'Name', 'Description', 'Category', 'Version', 'Last change', 'Code', 'Conversion factor', 'Reference currency'];
for ($col = 0; $col < count($currencyHeaders); $col++) {
    $sheet11->setCellValueByColumnAndRow($col + 1, 1, $currencyHeaders[$col]);
    $sheet11->getStyleByColumnAndRow($col + 1, 1)->getFont()->setBold(true);
}

$row = 2;
foreach ($currencies as $currency) {
    $sheet11->setCellValue('A' . $row, $currency['uuid']);
    $sheet11->setCellValue('B' . $row, $currency['name']);
    $sheet11->setCellValue('C' . $row, $currency['description'] ?? '');
    $sheet11->setCellValue('D' . $row, $currency['category'] ?? '');
    $sheet11->setCellValue('E' . $row, $currency['version'] ?? '00.00.000');
    $sheet11->setCellValue('F' . $row, $currency['last_change'] ?? '');
    $sheet11->setCellValue('G' . $row, $currency['code']);
    $sheet11->setCellValue('H' . $row, $currency['conversion_factor'] ?? 1.0);
    $sheet11->setCellValue('I' . $row, '');
    $row++;
}

foreach (range('A', 'I') as $col) {
    $sheet11->getColumnDimension($col)->setAutoSize(true);
}

// ========== SHEET 12: Locations ==========
$sheet12 = new Worksheet($spreadsheet, 'Locations');
$spreadsheet->addSheet($sheet12);

$locHeaders = ['UUID', 'Name', 'Description', 'Category', 'Version', 'Last change', 'Code', 'Latitude', 'Longitude'];
for ($col = 0; $col < count($locHeaders); $col++) {
    $sheet12->setCellValueByColumnAndRow($col + 1, 1, $locHeaders[$col]);
    $sheet12->getStyleByColumnAndRow($col + 1, 1)->getFont()->setBold(true);
}

$row = 2;
foreach ($locations as $location) {
    $sheet12->setCellValue('A' . $row, $location['uuid']);
    $sheet12->setCellValue('B' . $row, $location['name']);
    $sheet12->setCellValue('C' . $row, $location['description'] ?? '');
    $sheet12->setCellValue('D' . $row, $location['category'] ?? '');
    $sheet12->setCellValue('E' . $row, $location['version'] ?? '00.00.000');
    $sheet12->setCellValue('F' . $row, $location['last_change'] ?? '');
    $sheet12->setCellValue('G' . $row, $location['code']);
    $sheet12->setCellValue('H' . $row, $location['latitude'] ?? '');
    $sheet12->setCellValue('I' . $row, $location['longitude'] ?? '');
    $row++;
}

foreach (range('A', 'I') as $col) {
    $sheet12->getColumnDimension($col)->setAutoSize(true);
}

// ========== SHEET 13: Actors ==========
$sheet13 = new Worksheet($spreadsheet, 'Actors');
$spreadsheet->addSheet($sheet13);

$actorHeaders = ['UUID', 'Name', 'Description', 'Category', 'Version', 'Last change', 'Address', 'City', 'Zip code', 'Country', 'Email', 'Telefax', 'Telephone', 'Website'];
for ($col = 0; $col < count($actorHeaders); $col++) {
    $sheet13->setCellValueByColumnAndRow($col + 1, 1, $actorHeaders[$col]);
    $sheet13->getStyleByColumnAndRow($col + 1, 1)->getFont()->setBold(true);
}

$row = 2;
foreach ($actoresCompleto as $actor) {
    $sheet13->setCellValue('A' . $row, $actor['uuid']);
    $sheet13->setCellValue('B' . $row, $actor['name']);
    $sheet13->setCellValue('C' . $row, $actor['description'] ?? '');
    $sheet13->setCellValue('D' . $row, $actor['category'] ?? '');
    $sheet13->setCellValue('E' . $row, $actor['version'] ?? '00.00.000');
    $sheet13->setCellValue('F' . $row, $actor['last_change'] ?? '');
    $sheet13->setCellValue('G' . $row, $actor['address'] ?? '');
    $sheet13->setCellValue('H' . $row, $actor['city'] ?? '');
    $sheet13->setCellValue('I' . $row, $actor['zip_code'] ?? '');
    $sheet13->setCellValue('J' . $row, $actor['country'] ?? '');
    $sheet13->setCellValue('K' . $row, $actor['email'] ?? '');
    $sheet13->setCellValue('L' . $row, $actor['telefax'] ?? '');
    $sheet13->setCellValue('M' . $row, $actor['telephone'] ?? '');
    $sheet13->setCellValue('N' . $row, $actor['website'] ?? '');
    $row++;
}

foreach (range('A', 'N') as $col) {
    $sheet13->getColumnDimension($col)->setAutoSize(true);
}

// ========== SHEET 14: Sources ==========
$sheet14 = new Worksheet($spreadsheet, 'Sources');
$spreadsheet->addSheet($sheet14);

$sourceHeaders = ['UUID', 'Name', 'Description', 'Category', 'Version', 'Last change', 'Text reference', 'Year', 'URL'];
for ($col = 0; $col < count($sourceHeaders); $col++) {
    $sheet14->setCellValueByColumnAndRow($col + 1, 1, $sourceHeaders[$col]);
    $sheet14->getStyleByColumnAndRow($col + 1, 1)->getFont()->setBold(true);
}

$row = 2;
foreach ($sources as $source) {
    $sheet14->setCellValue('A' . $row, $source['uuid']);
    $sheet14->setCellValue('B' . $row, $source['name']);
    $sheet14->setCellValue('C' . $row, $source['description'] ?? '');
    $sheet14->setCellValue('D' . $row, $source['category'] ?? '');
    $sheet14->setCellValue('E' . $row, $source['version'] ?? '00.00.000');
    $sheet14->setCellValue('F' . $row, $source['last_change'] ?? '');
    $sheet14->setCellValue('G' . $row, $source['text_reference'] ?? '');
    $sheet14->setCellValue('H' . $row, $source['year'] ?? '');
    $sheet14->setCellValue('I' . $row, $source['url'] ?? '');
    $row++;
}

foreach (range('A', 'I') as $col) {
    $sheet14->getColumnDimension($col)->setAutoSize(true);
}

// ========== SHEET 15: Providers ==========
$sheet15 = new Worksheet($spreadsheet, 'Providers');
$spreadsheet->addSheet($sheet15);

$sheet15->setCellValue('A1', 'Flow');
$sheet15->setCellValue('B1', 'Provider');
$sheet15->getStyle('A1')->getFont()->setBold(true);
$sheet15->getStyle('B1')->getFont()->setBold(true);
$sheet15->getColumnDimension('A')->setAutoSize(true);
$sheet15->getColumnDimension('B')->setAutoSize(true);

// ===== ABRIR EXCEL DESDE LA PAGINA 0 =====
$spreadsheet->setActiveSheetIndex(0);

// ============ GENERAR Y DESCARGAR ============

if (ob_get_contents()) ob_end_clean();

$filename = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower($proceso['name']));
$filename = trim($filename, '_') . '_' . ($proceso['location_code'] ?? 'MX') . '.xlsx';

$tempFile = __DIR__ . '/temp_excel.xlsx';

try {
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);
    
    if (!file_exists($tempFile)) {
        throw new Exception("No se pudo crear el archivo temporal");
    }
    
    $size = filesize($tempFile);
    
    if ($size == 0) {
        unlink($tempFile);
        throw new Exception("El archivo generado está vacío");
    }
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Content-Length: ' . $size);
    
    readfile($tempFile);
    unlink($tempFile);
    exit;
    
} catch (Exception $e) {
    if (file_exists($tempFile)) unlink($tempFile);
    die("ERROR al generar Excel: " . $e->getMessage());
}
?>
