<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Cargar TCPDF
require_once(__DIR__ . '/../tcpdf/tcpdf.php');

// Verificar UUID
$uuid = $_GET['uuid'] ?? null;
if (!$uuid) {
    die('UUID no proporcionado');
}

// Conexión a base de datos
$config = require __DIR__ . '/../config.php';
$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
$mysqli->set_charset('utf8mb4');

// ========== CONSULTA 1: Información General del Proceso ==========
$sql = "SELECT 
            p.name, 
            p.category,
            p.norma_isic,
            p.process_type, 
            p.tech_desc,
            l.name as location,
            pd.creation_date
        FROM processes p
        LEFT JOIN locations l ON l.uuid = p.location_uuid
        LEFT JOIN process_documentation pd ON pd.process_uuid = p.uuid
        WHERE p.uuid = ?";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$process = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$process) {
    die('Proceso no encontrado');
}


// ========== CONSULTA 2: Flujo de Referencia ==========
$sql = "SELECT f.name as flow_name, po.amount, u.name as unit_name
        FROM process_outputs po
        LEFT JOIN flows f ON f.uuid = po.flow_uuid
        LEFT JOIN units u ON u.uuid = po.unit_uuid
        WHERE po.process_uuid = ? AND po.is_reference = 1
        LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$refFlow = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ========== CONSULTA 3: Entradas (Inputs) ==========
$sql = "SELECT 
            f.name as flow_name,
            CASE 
                WHEN f.flow_type = 'PRODUCT_FLOW' THEN 'Product'
                WHEN f.flow_type = 'ELEMENTARY_FLOW' THEN 'Elementary'
                ELSE 'Other'
            END as flow_type,
            pi.amount,
            u.name as unit_name,
            pi.provider_name
        FROM process_inputs pi
        LEFT JOIN flows f ON f.uuid = pi.flow_uuid
        LEFT JOIN units u ON u.uuid = pi.unit_uuid
        WHERE pi.process_uuid = ?
        ORDER BY f.flow_type, f.name";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$inputs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ========== CONSULTA 4: Salidas - Productos ==========
$sql = "SELECT f.name as flow_name, po.amount, u.name as unit_name
        FROM process_outputs po
        LEFT JOIN flows f ON f.uuid = po.flow_uuid
        LEFT JOIN units u ON u.uuid = po.unit_uuid
        WHERE po.process_uuid = ? AND f.flow_type = 'PRODUCT_FLOW'
        ORDER BY po.is_reference DESC, f.name";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$outputs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ========== CONSULTA 5: Salidas - Emisiones (Elementary Flows) ==========
$sql = "SELECT 
            f.name as flow_name,
            CONCAT(f.category, '/', IFNULL(proc.norma_isic, '')) as category,
            po.amount,
            u.name as unit_name
        FROM process_outputs po
        LEFT JOIN flows f ON f.uuid = po.flow_uuid
        LEFT JOIN units u ON u.uuid = po.unit_uuid
        LEFT JOIN processes proc ON proc.uuid = po.process_uuid
        WHERE po.process_uuid = ? AND f.flow_type = 'ELEMENTARY_FLOW'
        ORDER BY f.category, f.name";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$emissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ========== CONSULTA 6: Data Quality (OPCIONAL) ==========
$sql = "SELECT 
            indicator_type,
            score_level,
            selected_score,
            is_selected
        FROM process_dq_indicators 
        WHERE process_uuid = ? AND is_selected = 1
        ORDER BY indicator_type";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$dqResult = $stmt->get_result();
$dataQuality = [];
while ($row = $dqResult->fetch_assoc()) {
    $dataQuality[] = $row;
}
$stmt->close();

// ========== CONSULTA 7: Propietario y Contacto ==========
$sql = "SELECT a.name, a.email
        FROM process_actors pa
        LEFT JOIN actors a ON a.uuid = pa.actor_uuid
        WHERE pa.process_uuid = ? AND pa.role = 'owner'
        LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$ownerResult = $stmt->get_result();
$owner = $ownerResult->num_rows > 0 ? $ownerResult->fetch_assoc() : null;
$stmt->close();

// ========== CONSULTA 8: License y Access ==========
$sql = "SELECT copyright_flag, access_use_restrictions 
        FROM process_documentation 
        WHERE process_uuid = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $uuid);
$stmt->execute();
$docResult = $stmt->get_result();
$documentation = $docResult->num_rows > 0 ? $docResult->fetch_assoc() : null;
$stmt->close();

$mysqli->close();

// =============== GENERAR PDF CON TCPDF ===============

class MYPDF extends TCPDF {
    public function Header() {
        $this->Image(__DIR__ . '/../images/UNAM.png', 15, 10, 30, 0, 'PNG');
        $this->SetFont('helvetica', 'B', 16);
        $this->SetXY(50, 15);
        $this->Cell(110, 10, 'Life Cycle Inventory Dataset', 0, 0, 'C');
        $this->SetLineStyle(array('width' => 0.5, 'color' => array(0, 128, 0)));
        $this->Line(15, 32, 195, 32);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator('Ciclo de Vida - UNAM');
$pdf->SetAuthor('UNAM CREAA');
$pdf->SetTitle($process['name']);
$pdf->SetSubject('Life Cycle Inventory');

$pdf->SetMargins(15, 38, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->SetFont('helvetica', '', 10);

$pdf->AddPage();

// ========== 1. INFORMACIÓN GENERAL DEL PROCESO ==========
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(0, 100, 0);
$pdf->Cell(0, 8, 'Información General del Proceso', 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 10);
$html = '<table cellpadding="4" border="0" style="width:100%;">
    <tr>
        <td width="35%" style="background-color:#e8f5e9;"><strong>Process Name:</strong></td>
        <td width="65%">' . htmlspecialchars($process['name'] ?? 'N/A') . '</td>
    </tr>
    <tr>
        <td style="background-color:#e8f5e9;"><strong>Category:</strong></td>
        <td>' . htmlspecialchars(($process['category'] ?? 'N/A') . (!empty($process['norma_isic']) ? '/' . $process['norma_isic'] : '')) . '</td>
    </tr>
    <tr>
        <td style="background-color:#e8f5e9;"><strong>Process Type:</strong></td>
        <td>' . htmlspecialchars($process['process_type'] ?? 'N/A') . '</td>
    </tr>
    <tr>
        <td style="background-color:#e8f5e9;"><strong>Location (Geography):</strong></td>
        <td>' . htmlspecialchars($process['location'] ?? 'N/A') . '</td>
    </tr>
    <tr>
        <td style="background-color:#e8f5e9;"><strong>Reference Year:</strong></td>
        <td>' . ($process['creation_date'] ? date('Y', strtotime($process['creation_date'])) : 'N/A') . '</td>
    </tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Ln(3);

// Technology Description (short)
if (!empty($process['tech_desc'])) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Technology Description:', 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 5, substr($process['tech_desc'], 0, 500), 0, 'L');
    $pdf->Ln(3);
}

// ========== 2. FLUJO DE REFERENCIA ==========
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(0, 100, 0);
$pdf->Cell(0, 8, 'Flujo de Referencia', 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

if ($refFlow) {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(255, 248, 225);
    $pdf->Cell(90, 6, htmlspecialchars($refFlow['flow_name']), 1, 0, 'L', true);
    $pdf->Cell(45, 6, number_format($refFlow['amount'], 4), 1, 0, 'R', true);
    $pdf->Cell(45, 6, htmlspecialchars($refFlow['unit_name'] ?? 'N/A'), 1, 1, 'L', true);
} else {
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->Cell(0, 6, 'No reference flow defined', 0, 1);
}
$pdf->Ln(5);

// ========== 3. ENTRADAS (INPUTS) ==========
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(0, 100, 0);
$pdf->Cell(0, 8, 'Entradas', 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

if (!empty($inputs)) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(70, 6, 'Name', 1, 0, 'L', true);
    $pdf->Cell(30, 6, 'Type', 1, 0, 'C', true);
    $pdf->Cell(40, 6, 'Amount', 1, 0, 'R', true);
    $pdf->Cell(40, 6, 'Unit', 1, 1, 'L', true);
    
    $pdf->SetFont('helvetica', '', 8);
    foreach ($inputs as $input) {
        $pdf->Cell(70, 5, htmlspecialchars(substr($input['flow_name'], 0, 45)), 1, 0, 'L');
        $pdf->Cell(30, 5, htmlspecialchars($input['flow_type']), 1, 0, 'C');
        $pdf->Cell(40, 5, number_format($input['amount'], 6), 1, 0, 'R');
        $pdf->Cell(40, 5, htmlspecialchars($input['unit_name'] ?? 'N/A'), 1, 1, 'L');
        
        // Provider/Source (opcional)
        if (!empty($input['provider_name'])) {
            $pdf->SetFont('helvetica', 'I', 7);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(10, 4, '', 0, 0);
            $pdf->Cell(170, 4, 'Provider: ' . htmlspecialchars(substr($input['provider_name'], 0, 80)), 0, 1);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 8);
        }
    }
} else {
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->Cell(0, 6, 'No inputs defined', 0, 1);
}
$pdf->Ln(5);

// ========== 4. SALIDAS - PRODUCTOS ==========
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(0, 100, 0);
$pdf->Cell(0, 8, 'Salidas', 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

if (!empty($outputs)) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(90, 6, 'Name', 1, 0, 'L', true);
    $pdf->Cell(45, 6, 'Amount', 1, 0, 'R', true);
    $pdf->Cell(45, 6, 'Unit', 1, 1, 'L', true);
    
    $pdf->SetFont('helvetica', '', 8);
    foreach ($outputs as $output) {
        $pdf->Cell(90, 5, htmlspecialchars(substr($output['flow_name'], 0, 50)), 1, 0, 'L');
        $pdf->Cell(45, 5, number_format($output['amount'], 6), 1, 0, 'R');
        $pdf->Cell(45, 5, htmlspecialchars($output['unit_name'] ?? 'N/A'), 1, 1, 'L');
    }
} else {
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->Cell(0, 6, 'No product outputs defined', 0, 1);
}
$pdf->Ln(5);

// ========== 5. SALIDAS - EMISIONES (ELEMENTARY FLOWS) ==========
if (!empty($emissions)) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(0, 100, 0);
    $pdf->Cell(0, 8, 'Emisiones (Flujos Elementales)', 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(2);
    
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(65, 6, 'Name', 1, 0, 'L', true);
    $pdf->Cell(55, 6, 'Compartment/Sub-compartment', 1, 0, 'L', true);
    $pdf->Cell(30, 6, 'Amount', 1, 0, 'R', true);
    $pdf->Cell(30, 6, 'Unit', 1, 1, 'L', true);
    
    $pdf->SetFont('helvetica', '', 8);
    foreach ($emissions as $emission) {
        $pdf->Cell(65, 5, htmlspecialchars(substr($emission['flow_name'], 0, 35)), 1, 0, 'L');
        $pdf->Cell(55, 5, htmlspecialchars($emission['category'] ?? 'N/A'), 1, 0, 'L');
        $pdf->Cell(30, 5, number_format($emission['amount'], 8), 1, 0, 'R');
        $pdf->Cell(30, 5, htmlspecialchars($emission['unit_name'] ?? 'N/A'), 1, 1, 'L');
    }
    $pdf->Ln(5);
}

// ========== 6. DATA QUALITY & REVIEW (OPCIONAL) ==========
if (!empty($dataQuality)) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(0, 100, 0);
    $pdf->Cell(0, 8, 'Calidad y revisión de datos', 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(2);
    
    $pdf->SetFont('helvetica', '', 10);
    
    // Construir resumen de Data Quality (formato: 1/1/1/1/1)
    $dqScores = [];
    foreach ($dataQuality as $dq) {
        $dqScores[] = $dq['selected_score'] ?? $dq['score_level'];
    }
    $dqSummary = !empty($dqScores) ? implode('/', $dqScores) : 'Not assessed';
    
    $pdf->Cell(50, 6, 'Data Quality:', 0, 0, 'L');
    $pdf->Cell(0, 6, $dqSummary, 0, 1, 'L');
    
    // Review Status (fijo por ahora, puedes agregar campo en la BD)
    $pdf->Cell(50, 6, 'Review Status:', 0, 0, 'L');
    $pdf->Cell(0, 6, 'Not reviewed', 0, 1, 'L');
    
    $pdf->Ln(3);
}

// ========== 7. PROPIEDAD (OWNERSHIP & ACCESS) ==========
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(0, 100, 0);
$pdf->Cell(0, 8, 'Propiedad', 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 10);

// Data Owner
$pdf->Cell(50, 6, 'Data Owner:', 0, 0, 'L');
$pdf->Cell(0, 6, $owner ? htmlspecialchars($owner['name']) : 'N/A', 0, 1, 'L');

// License
$license = ($documentation && $documentation['copyright_flag']) ? 'Proprietary' : 'Public Domain / CC-BY-4.0';
$pdf->Cell(50, 6, 'License:', 0, 0, 'L');
$pdf->Cell(0, 6, $license, 0, 1, 'L');

// Access Conditions
$accessConditions = $documentation && $documentation['access_use_restrictions'] ? htmlspecialchars($documentation['access_use_restrictions']) : 'Public';
$pdf->Cell(50, 6, 'Access Conditions:', 0, 0, 'L');
$pdf->Cell(0, 6, $accessConditions, 0, 1, 'L');

// Contact Information
$pdf->Cell(50, 6, 'Contact Information:', 0, 0, 'L');
$pdf->Cell(0, 6, $owner ? htmlspecialchars($owner['email']) : 'N/A', 0, 1, 'L');

// Pie de página
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell(0, 4, 'Generated by Ciclo de Vida System - UNAM CREAA | ' . date('Y-m-d H:i:s'), 0, 'C');

// Salida del PDF
$filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $process['name']) . '.pdf';
$pdf->Output($filename, 'D');
?>
