<?php
session_start();
require_once 'conexion.php';

$uuid = $_POST['uuid'] ?? '';
$type = $_POST['type'] ?? '';

// Detectar tipo si no viene explícito
if (empty($type)) {
    if (isset($_POST['resourcename'])) {
        $type = 'input';
    } elseif (isset($_POST['nameoftheemission'])) {
        $type = 'output';
    }
}

if (empty($uuid)) {
    echo "Error: UUID requerido";
    exit;
}

if ($type === 'input') {
    $stmt = $conn->prepare("UPDATE process_inputs SET 
        resourcename=?, category=?, quantity=?, unit=?, datasource=?, commentary=? 
        WHERE uuid=?");
    
    $resourcename = $_POST['resourcename'] ?? '';
    $category = $_POST['category'] ?? '';
    $quantity = $_POST['quantity'] ?? 0;
    $unit = $_POST['unit'] ?? '';
    $datasource = $_POST['datasource'] ?? '';
    $commentary = $_POST['commentary'] ?? '';
    
    $stmt->bind_param("ssdssss", 
        $resourcename, 
        $category, 
        $quantity, 
        $unit, 
        $datasource, 
        $commentary, 
        $uuid
    );
} else {
    $stmt = $conn->prepare("UPDATE process_outputs SET 
        nameoftheemission=?, typeofemission=?, category=?, compartment=?, subcompartment=?, quantity=?, unit=?, commentary=? 
        WHERE uuid=?");
    
    $nameoftheemission = $_POST['nameoftheemission'] ?? '';
    $typeofemission = $_POST['typeofemission'] ?? '';
    $category = $_POST['category'] ?? '';
    $compartment = $_POST['compartment'] ?? '';
    $subcompartment = $_POST['subcompartment'] ?? '';
    $quantity = $_POST['quantity'] ?? 0;
    $unit = $_POST['unit'] ?? '';
    $commentary = $_POST['commentary'] ?? '';
    
    $stmt->bind_param("sssssdsss", 
        $nameoftheemission, 
        $typeofemission, 
        $category, 
        $compartment, 
        $subcompartment, 
        $quantity, 
        $unit, 
        $commentary, 
        $uuid
    );
}

if ($stmt->execute()) {
    echo "Flujo actualizado exitosamente";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
