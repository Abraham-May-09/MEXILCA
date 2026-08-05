<?php
// export_jsonld.php - VERSIÓN DEFINITIVA CON NOMBRES EXACTOS SEGÚN OLCA-SCHEMA + norma_isic
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    error_log("FATAL: config.php no encontrado");
    http_response_code(500);
    exit("Error de configuración.");
}

$config = require $configPath;

$mysqli = @new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
if ($mysqli->connect_errno) {
    error_log("MySQL connect error: " . $mysqli->connect_error);
    http_response_code(500);
    exit("Error de conexión a base de datos.");
}
$mysqli->set_charset('utf8mb4');

$process_uuid = $_GET['uuid'] ?? null;

if (!$process_uuid) {
    http_response_code(400);
    exit("UUID de proceso no proporcionado.");
}

// ============ FUNCIONES ============

function obtenerProceso($mysqli, $uuid) {
    $stmt = $mysqli->prepare("SELECT p.*, l.name as location_name, l.uuid as location_uuid, l.category as location_category
                              FROM processes p
                              LEFT JOIN locations l ON l.uuid = p.location_uuid
                              WHERE p.uuid = ?");
    if (!$stmt) return null;
    $stmt->bind_param('s', $uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

function obtenerDocumentacion($mysqli, $process_uuid) {
    $stmt = $mysqli->prepare("SELECT * FROM process_documentation WHERE process_uuid = ?");
    if (!$stmt) return null;
    $stmt->bind_param('s', $process_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

function obtenerActoresDelProceso($mysqli, $process_uuid) {
    $query = "SELECT pa.role, pa.note, a.uuid, a.name, a.email, a.website, a.address, a.city, a.country
              FROM process_actors pa
              JOIN actors a ON a.uuid = pa.actor_uuid
              WHERE pa.process_uuid = ?";
    $stmt = $mysqli->prepare($query);
    if (!$stmt) return [];
    $stmt->bind_param('s', $process_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function obtenerInputs($mysqli, $process_uuid) {
    $query = "SELECT pi.*,
              f.uuid as flow_uuid, f.name as flow_name, 
              CONCAT(f.category, '/', IFNULL(p.norma_isic, '')) as flow_category,
              f.flow_type as flow_type,
              u.uuid as unit_uuid, u.name as unit_name,
              ug.uuid as unit_group_uuid,
              fp.uuid as flow_property_uuid, fp.name as flow_property_name, fp.category as flow_property_category,
              loc.uuid as location_uuid, loc.name as location_name, loc.category as location_category
              FROM process_inputs pi
              LEFT JOIN flows f ON f.uuid = pi.flow_uuid
              LEFT JOIN units u ON u.uuid = pi.unit_uuid
              LEFT JOIN unit_groups ug ON ug.uuid = u.unit_group_uuid
              LEFT JOIN flow_properties fp ON fp.uuid = pi.flow_property_uuid
              LEFT JOIN locations loc ON loc.uuid = pi.location_uuid
              LEFT JOIN processes p ON p.uuid = pi.process_uuid
              WHERE pi.process_uuid = ?
              ORDER BY pi.internal_id ASC";

    $stmt = $mysqli->prepare($query);
    if (!$stmt) return [];
    $stmt->bind_param('s', $process_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function obtenerOutputs($mysqli, $process_uuid) {
    $query = "SELECT po.*,
              f.uuid as flow_uuid, f.name as flow_name,
              CONCAT(f.category, '/', IFNULL(p.norma_isic, '')) as flow_category,
              f.flow_type as flow_type,
              u.uuid as unit_uuid, u.name as unit_name,
              ug.uuid as unit_group_uuid,
              fp.uuid as flow_property_uuid, fp.name as flow_property_name, fp.category as flow_property_category,
              loc.uuid as location_uuid, loc.name as location_name, loc.category as location_category
              FROM process_outputs po
              LEFT JOIN flows f ON f.uuid = po.flow_uuid
              LEFT JOIN units u ON u.uuid = po.unit_uuid
              LEFT JOIN unit_groups ug ON ug.uuid = u.unit_group_uuid
              LEFT JOIN flow_properties fp ON fp.uuid = po.flow_property_uuid
              LEFT JOIN locations loc ON loc.uuid = po.location_uuid
              LEFT JOIN processes p ON p.uuid = po.process_uuid
              WHERE po.process_uuid = ?
              ORDER BY po.internal_id ASC";

    $stmt = $mysqli->prepare($query);
    if (!$stmt) return [];
    $stmt->bind_param('s', $process_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function obtenerFlow($mysqli, $flow_uuid, $process_uuid = null) {
    $query = "SELECT f.*, 
                      f.reference_flow_property_uuid,
                      f.reference_flow_property_name";
    
    if ($process_uuid) {
        $query .= ", CONCAT(f.category, '/', IFNULL(p.norma_isic, '')) as category_with_isic";
    }
    
    $query .= " FROM flows f";
    
    if ($process_uuid) {
        $query .= " LEFT JOIN processes p ON p.uuid = ?";
    }
    
    $query .= " WHERE f.uuid = ?";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) return null;
    
    if ($process_uuid) {
        $stmt->bind_param('ss', $process_uuid, $flow_uuid);
    } else {
        $stmt->bind_param('s', $flow_uuid);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

function obtenerFlowProperty($mysqli, $fp_uuid) {
    $stmt = $mysqli->prepare("SELECT fp.*,
                              ug.uuid as unit_group_uuid,
                              ug.name as unit_group_name
                              FROM flow_properties fp
                              LEFT JOIN unit_groups ug ON ug.uuid = fp.unit_group_uuid
                              WHERE fp.uuid = ?");
    if (!$stmt) return null;
    $stmt->bind_param('s', $fp_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

function obtenerUnitGroup($mysqli, $ug_uuid) {
    $stmt = $mysqli->prepare("SELECT * FROM unit_groups WHERE uuid = ?");
    if (!$stmt) return null;
    $stmt->bind_param('s', $ug_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

function obtenerUnitsDeGrupo($mysqli, $ug_uuid) {
    $stmt = $mysqli->prepare("SELECT * FROM units WHERE unit_group_uuid = ? ORDER BY name ASC");
    if (!$stmt) return [];
    $stmt->bind_param('s', $ug_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function obtenerLocation($mysqli, $loc_uuid) {
    $stmt = $mysqli->prepare("SELECT * FROM locations WHERE uuid = ?");
    if (!$stmt) return null;
    $stmt->bind_param('s', $loc_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

function obtenerActor($mysqli, $actor_uuid) {
    $stmt = $mysqli->prepare("SELECT * FROM actors WHERE uuid = ?");
    if (!$stmt) return null;
    $stmt->bind_param('s', $actor_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

// ============ OBTENER DATOS ============

$proceso = obtenerProceso($mysqli, $process_uuid);

if (!$proceso) {
    http_response_code(404);
    exit("Proceso no encontrado.");
}

$documentacion = obtenerDocumentacion($mysqli, $process_uuid);
$actores = obtenerActoresDelProceso($mysqli, $process_uuid);
$inputs = obtenerInputs($mysqli, $process_uuid);
$outputs = obtenerOutputs($mysqli, $process_uuid);

$flowsToExport = [];
$flowPropertiesToExport = [];
$unitGroupsToExport = [];
$locationsToExport = [];
$actorsToExport = [];

// ============ CONSTRUIR PROCESO ============

$lastChange = $proceso['last_change'] ?? $proceso['created_at'] ?? date('Y-m-d\TH:i:s');
if (strpos($lastChange, 'T') === false) {
    $lastChange = str_replace(' ', 'T', $lastChange);
}
$lastChange = rtrim($lastChange, 'Z') . 'Z';

$processData = [
    '@context' => 'http://greendelta.github.io/olca-schema/context.jsonld',
    '@type' => 'Process',
    '@id' => $proceso['uuid'],
    'name' => $proceso['name'] ?? '',
    'version' => $proceso['version'] ?? '00.00.001',
    'lastChange' => $lastChange
];

if (!empty($proceso['description'])) {
    $processData['description'] = $proceso['description'];
}

if (!empty($proceso['category'])) {
    // Construir category/norma_isic
    $category_value = $proceso['category'];
    if (!empty($proceso['norma_isic'])) {
        $category_value .= '/' . $proceso['norma_isic'];
    }
    $processData['category'] = $category_value;
}

if (!empty($proceso['location_uuid'])) {
    $processData['location'] = [
        '@type' => 'Location',
        '@id' => $proceso['location_uuid'],
        'name' => $proceso['location_name'] ?? ''
    ];
    if (!empty($proceso['location_category'])) {
        $processData['location']['category'] = $proceso['location_category'];
    }
    $locationsToExport[$proceso['location_uuid']] = true;
}

$processData['processType'] = !empty($proceso['process_type']) ? $proceso['process_type'] : 'UNIT_PROCESS';

// ============ PROCESS DOCUMENTATION ============
$processDoc = [];

if (!empty($documentacion['creation_date'])) {
    $creationDate = $documentacion['creation_date'];
    if (strpos($creationDate, 'T') === false) {
        $creationDate = str_replace(' ', 'T', $creationDate);
    }
    $processDoc['creationDate'] = $creationDate;
}

if (!empty($proceso['valid_from'])) {
    $validFrom = $proceso['valid_from'];
    if (strpos($validFrom, 'T') === false && strlen($validFrom) > 10) {
        $validFrom = str_replace(' ', 'T', $validFrom);
    } else if (strlen($validFrom) == 10) {
        $validFrom = $validFrom . 'T00:00:00';
    }
    $processDoc['validFrom'] = $validFrom;
}

if (!empty($proceso['valid_until'])) {
    $validUntil = $proceso['valid_until'];
    if (strpos($validUntil, 'T') === false && strlen($validUntil) > 10) {
        $validUntil = str_replace(' ', 'T', $validUntil);
    } else if (strlen($validUntil) == 10) {
        $validUntil = $validUntil . 'T00:00:00';
    }
    $processDoc['validUntil'] = $validUntil;
}

if (!empty($proceso['time_desc'])) {
    $processDoc['timeDescription'] = trim($proceso['time_desc']);
}

if (!empty($proceso['tech_desc'])) {
    $processDoc['technologyDescription'] = trim($proceso['tech_desc']);
}

if (!empty($proceso['geo_desc'])) {
    $processDoc['geographyDescription'] = trim($proceso['geo_desc']);
}

foreach ($actores as $actor_rel) {
    $role = strtolower(trim($actor_rel['role'] ?? ''));

    if (!empty($actor_rel['uuid'])) {
        if (in_array($role, ['owner', 'data owner', 'data_owner', 'documentor', 'data documentor'])) {
            $processDoc['dataDocumentor'] = [
                '@type' => 'Actor',
                '@id' => $actor_rel['uuid'],
                'name' => $actor_rel['name']
            ];
            $actorsToExport[$actor_rel['uuid']] = true;
        }

        if (in_array($role, ['generator', 'data generator', 'data_generator', 'author', 'modeller'])) {
            $processDoc['dataGenerator'] = [
                '@type' => 'Actor',
                '@id' => $actor_rel['uuid'],
                'name' => $actor_rel['name']
            ];
            $actorsToExport[$actor_rel['uuid']] = true;
        }

        if (in_array($role, ['reviewer', 'review'])) {
            $processDoc['reviewer'] = [
                '@type' => 'Actor',
                '@id' => $actor_rel['uuid'],
                'name' => $actor_rel['name']
            ];
            $actorsToExport[$actor_rel['uuid']] = true;
        }
    }
}

if (isset($documentacion['copyright_flag']) && $documentacion['copyright_flag'] !== null) {
    $processDoc['copyright'] = (bool)$documentacion['copyright_flag'];
}

if (!empty($documentacion['project'])) {
    $processDoc['projectDescription'] = trim($documentacion['project']);
}

if (!empty($documentacion['intended_application'])) {
    $processDoc['intendedApplication'] = trim($documentacion['intended_application']);
}

if (!empty($documentacion['lci_method'])) {
    $processDoc['inventoryMethodDescription'] = trim($documentacion['lci_method']);
}

if (!empty($documentacion['modeling_constants'])) {
    $processDoc['modelingConstantsDescription'] = trim($documentacion['modeling_constants']);
}

if (!empty($documentacion['ds_data_completeness'])) {
    $processDoc['dataCompletenessDescription'] = trim($documentacion['ds_data_completeness']);
}

if (!empty($documentacion['completeness_text'])) {
    $processDoc['completenessDescription'] = trim($documentacion['completeness_text']);
}

if (!empty($documentacion['ds_data_selection'])) {
    $processDoc['dataSelectionDescription'] = trim($documentacion['ds_data_selection']);
}

if (!empty($documentacion['ds_data_treatment'])) {
    $processDoc['dataTreatmentDescription'] = trim($documentacion['ds_data_treatment']);
}

if (!empty($documentacion['ds_sampling_procedure'])) {
    $processDoc['samplingDescription'] = trim($documentacion['ds_sampling_procedure']);
}

if (!empty($documentacion['ds_collection_period'])) {
    $processDoc['dataCollectionDescription'] = trim($documentacion['ds_collection_period']);
}

if (!empty($documentacion['ds_use_advice'])) {
    $processDoc['useAdvice'] = trim($documentacion['ds_use_advice']);
}

if (!empty($documentacion['access_use_restrictions'])) {
    $processDoc['restrictionsDescription'] = trim($documentacion['access_use_restrictions']);
}

if (!empty($documentacion['sources_text'])) {
    $processDoc['sources'] = trim($documentacion['sources_text']);
}

if (!empty($processDoc)) {
    $processData['processDocumentation'] = $processDoc;
}

// ============ EXCHANGES ============
$exchanges = [];

foreach ($inputs as $input) {
    $exchange = [
        'internalId' => intval($input['internal_id'] ?? 0),
        'input' => true,
        'quantitativeReference' => (bool)($input['is_reference'] ?? false),
        'amount' => floatval($input['amount'] ?? 0)
    ];

    if (!empty($input['flow_uuid'])) {
        $exchange['flow'] = [
            '@type' => 'Flow',
            '@id' => $input['flow_uuid'],
            'name' => $input['flow_name'] ?? ''
        ];
        if (!empty($input['flow_category'])) {
            $exchange['flow']['category'] = $input['flow_category'];
        }
        $flowsToExport[$input['flow_uuid']] = true;
    }

    if (!empty($input['flow_property_uuid'])) {
        $exchange['flowProperty'] = [
            '@type' => 'FlowProperty',
            '@id' => $input['flow_property_uuid'],
            'name' => $input['flow_property_name'] ?? ''
        ];
        $flowPropertiesToExport[$input['flow_property_uuid']] = true;
    }

    if (!empty($input['unit_uuid'])) {
        $exchange['unit'] = [
            '@type' => 'Unit',
            '@id' => $input['unit_uuid'],
            'name' => $input['unit_name'] ?? ''
        ];
    }

    if (!empty($input['unit_group_uuid'])) {
        $unitGroupsToExport[$input['unit_group_uuid']] = true;
    }

    if (!empty($input['location_uuid'])) {
        $exchange['location'] = [
            '@type' => 'Location',
            '@id' => $input['location_uuid'],
            'name' => $input['location_name'] ?? ''
        ];
        $locationsToExport[$input['location_uuid']] = true;
    }

    if (!empty($input['description'])) {
        $exchange['description'] = $input['description'];
    }

    $exchanges[] = $exchange;
}

foreach ($outputs as $output) {
    $exchange = [
        'internalId' => intval($output['internal_id'] ?? 0),
        'input' => false,
        'quantitativeReference' => (bool)($output['is_reference'] ?? false),
        'amount' => floatval($output['amount'] ?? 0)
    ];

    if (!empty($output['flow_uuid'])) {
        $exchange['flow'] = [
            '@type' => 'Flow',
            '@id' => $output['flow_uuid'],
            'name' => $output['flow_name'] ?? ''
        ];
        if (!empty($output['flow_category'])) {
            $exchange['flow']['category'] = $output['flow_category'];
        }
        $flowsToExport[$output['flow_uuid']] = true;
    }

    if (!empty($output['flow_property_uuid'])) {
        $exchange['flowProperty'] = [
            '@type' => 'FlowProperty',
            '@id' => $output['flow_property_uuid'],
            'name' => $output['flow_property_name'] ?? ''
        ];
        $flowPropertiesToExport[$output['flow_property_uuid']] = true;
    }

    if (!empty($output['unit_uuid'])) {
        $exchange['unit'] = [
            '@type' => 'Unit',
            '@id' => $output['unit_uuid'],
            'name' => $output['unit_name'] ?? ''
        ];
    }

    if (!empty($output['unit_group_uuid'])) {
        $unitGroupsToExport[$output['unit_group_uuid']] = true;
    }

    if (!empty($output['location_uuid'])) {
        $exchange['location'] = [
            '@type' => 'Location',
            '@id' => $output['location_uuid'],
            'name' => $output['location_name'] ?? ''
        ];
        $locationsToExport[$output['location_uuid']] = true;
    }

    if (!empty($output['description'])) {
        $exchange['description'] = $output['description'];
    }

    $exchanges[] = $exchange;
}

$processData['exchanges'] = $exchanges;

// ============ EXPORTAR FLOWS ============
$flowsData = [];
foreach (array_keys($flowsToExport) as $flow_uuid) {
    $flow = obtenerFlow($mysqli, $flow_uuid, $process_uuid);
    if ($flow) {
        $flowData = [
            '@type' => 'Flow',
            '@id' => $flow['uuid'],
            'name' => $flow['name'] ?? '',
            'version' => $flow['version'] ?? '00.00.001',
            'lastChange' => !empty($flow['last_change']) ? str_replace(' ', 'T', $flow['last_change']) : date('Y-m-d\TH:i:s')
        ];

        if (!empty($flow['description'])) {
            $flowData['description'] = $flow['description'];
        }

        if (!empty($flow['category_with_isic'])) {
            $flowData['category'] = $flow['category_with_isic'];
        } elseif (!empty($flow['category'])) {
            $flowData['category'] = $flow['category'];
        }

        if (!empty($flow['flow_type'])) {
            $flowData['flowType'] = $flow['flow_type'];
        }

        if (!empty($flow['reference_flow_property_uuid'])) {
            $flowData['flowProperties'] = [[
                '@type' => 'FlowPropertyFactor',
                'referenceFlowProperty' => true,
                'conversionFactor' => 1.0,
                'flowProperty' => [
                    '@type' => 'FlowProperty',
                    '@id' => $flow['reference_flow_property_uuid'],
                    'name' => $flow['reference_flow_property_name'] ?? ''
                ]
            ]];
            $flowPropertiesToExport[$flow['reference_flow_property_uuid']] = true;
        }

        $flowsData[] = $flowData;
    }
}

// ============ EXPORTAR FLOW PROPERTIES ============
$flowPropertiesData = [];
foreach (array_keys($flowPropertiesToExport) as $fp_uuid) {
    $fp = obtenerFlowProperty($mysqli, $fp_uuid);
    if ($fp) {
        $fpData = [
            '@type' => 'FlowProperty',
            '@id' => $fp['uuid'],
            'name' => $fp['name'] ?? '',
            'version' => $fp['version'] ?? '00.00.001',
            'lastChange' => !empty($fp['last_change']) ? str_replace(' ', 'T', $fp['last_change']) : date('Y-m-d\TH:i:s')
        ];

        if (!empty($fp['description'])) {
            $fpData['description'] = $fp['description'];
        }

        if (!empty($fp['unit_group_uuid'])) {
            $fpData['unitGroup'] = [
                '@type' => 'UnitGroup',
                '@id' => $fp['unit_group_uuid'],
                'name' => $fp['unit_group_name'] ?? ''
            ];
            $unitGroupsToExport[$fp['unit_group_uuid']] = true;
        }

        $flowPropertiesData[] = $fpData;
    }
}

// ============ EXPORTAR UNIT GROUPS ============
$unitGroupsData = [];
foreach (array_keys($unitGroupsToExport) as $ug_uuid) {
    $ug = obtenerUnitGroup($mysqli, $ug_uuid);
    if ($ug) {
        $ugData = [
            '@type' => 'UnitGroup',
            '@id' => $ug['uuid'],
            'name' => $ug['name'] ?? '',
            'version' => $ug['version'] ?? '00.00.001',
            'lastChange' => !empty($ug['last_change']) ? str_replace(' ', 'T', $ug['last_change']) : date('Y-m-d\TH:i:s')
        ];

        if (!empty($ug['description'])) {
            $ugData['description'] = $ug['description'];
        }

        $units = obtenerUnitsDeGrupo($mysqli, $ug_uuid);
        $unitsArray = [];
        foreach ($units as $unit) {
            $unitData = [
                '@type' => 'Unit',
                '@id' => $unit['uuid'],
                'name' => $unit['name'] ?? '',
                'conversionFactor' => floatval($unit['conversion_factor'] ?? 1.0),
                'referenceUnit' => (bool)($unit['is_ref_unit'] ?? false)
            ];

            if (!empty($unit['description'])) {
                $unitData['description'] = $unit['description'];
            }

            if (!empty($unit['synonyms'])) {
                $unitData['synonyms'] = explode(';', $unit['synonyms']);
            }

            $unitsArray[] = $unitData;
        }

        if (!empty($unitsArray)) {
            $ugData['units'] = $unitsArray;
        }

        $unitGroupsData[] = $ugData;
    }
}

// ============ EXPORTAR LOCATIONS ============
$locationsData = [];
foreach (array_keys($locationsToExport) as $loc_uuid) {
    $loc = obtenerLocation($mysqli, $loc_uuid);
    if ($loc) {
        $locData = [
            '@type' => 'Location',
            '@id' => $loc['uuid'],
            'name' => $loc['name'] ?? '',
            'version' => $loc['version'] ?? '00.00.001',
            'lastChange' => !empty($loc['last_change']) ? str_replace(' ', 'T', $loc['last_change']) : date('Y-m-d\TH:i:s')
        ];

        if (!empty($loc['description'])) {
            $locData['description'] = $loc['description'];
        }

        if (!empty($loc['code'])) {
            $locData['code'] = $loc['code'];
        }

        if (!empty($loc['latitude'])) {
            $locData['latitude'] = floatval($loc['latitude']);
        }

        if (!empty($loc['longitude'])) {
            $locData['longitude'] = floatval($loc['longitude']);
        }

        $locationsData[] = $locData;
    }
}

// ============ EXPORTAR ACTORS ============
$actorsData = [];
foreach (array_keys($actorsToExport) as $actor_uuid) {
    $actor = obtenerActor($mysqli, $actor_uuid);
    if ($actor) {
        $actorData = [
            '@type' => 'Actor',
            '@id' => $actor['uuid'],
            'name' => $actor['name'] ?? '',
            'version' => $actor['version'] ?? '00.00.001',
            'lastChange' => !empty($actor['last_change']) ? str_replace(' ', 'T', $actor['last_change']) : date('Y-m-d\TH:i:s')
        ];

        if (!empty($actor['description'])) {
            $actorData['description'] = $actor['description'];
        }

        if (!empty($actor['email'])) {
            $actorData['email'] = $actor['email'];
        }

        if (!empty($actor['website'])) {
            $actorData['website'] = $actor['website'];
        }

        if (!empty($actor['address'])) {
            $actorData['address'] = $actor['address'];
        }

        if (!empty($actor['city'])) {
            $actorData['city'] = $actor['city'];
        }

        if (!empty($actor['country'])) {
            $actorData['country'] = $actor['country'];
        }

        if (!empty($actor['telephone'])) {
            $actorData['telefax'] = $actor['telephone'];
        }

        $actorsData[] = $actorData;
    }
}

// ============ CREAR ZIP ============

$tempDir = sys_get_temp_dir() . '/openlca_export_' . uniqid();
mkdir($tempDir);
mkdir($tempDir . '/processes');
mkdir($tempDir . '/flows');
mkdir($tempDir . '/flow_properties');
mkdir($tempDir . '/unit_groups');
mkdir($tempDir . '/locations');
if (!empty($actorsData)) {
    mkdir($tempDir . '/actors');
}

$schemaInfo = [
    'olcaSchemaVersion' => '2.0.0',
    'createdWith' => 'CREAA Export Tool',
    'creationDate' => date('Y-m-d\TH:i:s')
];
file_put_contents($tempDir . '/olca-schema.json', json_encode($schemaInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

file_put_contents($tempDir . '/processes/' . $proceso['uuid'] . '.json', json_encode($processData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

foreach ($flowsData as $flowData) {
    file_put_contents($tempDir . '/flows/' . $flowData['@id'] . '.json', json_encode($flowData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

foreach ($flowPropertiesData as $fpData) {
    file_put_contents($tempDir . '/flow_properties/' . $fpData['@id'] . '.json', json_encode($fpData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

foreach ($unitGroupsData as $ugData) {
    file_put_contents($tempDir . '/unit_groups/' . $ugData['@id'] . '.json', json_encode($ugData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

foreach ($locationsData as $locData) {
    file_put_contents($tempDir . '/locations/' . $locData['@id'] . '.json', json_encode($locData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

foreach ($actorsData as $actorData) {
    file_put_contents($tempDir . '/actors/' . $actorData['@id'] . '.json', json_encode($actorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

$zipFilename = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower($proceso['name']));
$zipFilename = trim($zipFilename, '_') . '.zip';
$zipPath = $tempDir . '/' . $zipFilename;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
    exit("No se pudo crear el archivo ZIP");
}

$zip->addFile($tempDir . '/olca-schema.json', 'olca-schema.json');
$zip->addFile($tempDir . '/processes/' . $proceso['uuid'] . '.json', 'processes/' . $proceso['uuid'] . '.json');

foreach ($flowsData as $flowData) {
    $zip->addFile($tempDir . '/flows/' . $flowData['@id'] . '.json', 'flows/' . $flowData['@id'] . '.json');
}

foreach ($flowPropertiesData as $fpData) {
    $zip->addFile($tempDir . '/flow_properties/' . $fpData['@id'] . '.json', 'flow_properties/' . $fpData['@id'] . '.json');
}

foreach ($unitGroupsData as $ugData) {
    $zip->addFile($tempDir . '/unit_groups/' . $ugData['@id'] . '.json', 'unit_groups/' . $ugData['@id'] . '.json');
}

foreach ($locationsData as $locData) {
    $zip->addFile($tempDir . '/locations/' . $locData['@id'] . '.json', 'locations/' . $locData['@id'] . '.json');
}

foreach ($actorsData as $actorData) {
    $zip->addFile($tempDir . '/actors/' . $actorData['@id'] . '.json', 'actors/' . $actorData['@id'] . '.json');
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);

function deleteDirectory($dir) {
    if (!file_exists($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

deleteDirectory($tempDir);

$mysqli->close();
exit;
?>
