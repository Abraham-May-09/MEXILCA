<?php
require 'conexion.php';

$process_uuid = $_POST['process_uuid'];
$parameter_name = $_POST['parameter_name'];
$type_of_parameter = $_POST['type_of_parameter'];
$default_value = $_POST['default_value'];
$unit = $_POST['unit'];
$description = $_POST['description'];
$uncertainty = $_POST['uncertainty'];
$value_range = $_POST['value_range'];
$formula = $_POST['formula'];

$sql = "INSERT INTO parameters (
    process_uuid, parameter_name, type_of_parameter, default_value, unit, description, uncertainty, value_range, formula
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssssss", 
    $process_uuid, $parameter_name, $type_of_parameter, $default_value, $unit, $description, $uncertainty, $value_range, $formula
);

if ($stmt->execute()) {
    echo "Parámetro guardado correctamente";
} else {
    echo "Error: " . $stmt->error;
}
?>