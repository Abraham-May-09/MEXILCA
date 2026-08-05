<?php
require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $process_uuid = $_POST['process_uuid'] ?? null;
    $dq_scores = $_POST['dataQualityIndicators'] ?? '';
    $dq_details = $_POST['dataQualityDetails'] ?? '';

    if (!$process_uuid) {
      throw new Exception("Falta process_uuid");
    }

    if (empty($dq_scores)) {
      throw new Exception("Falta dataQualityIndicators");
    }

    $scores = explode(',', $dq_scores);
    $details = !empty($dq_details) ? json_decode($dq_details, true) : [];

    $indicators = [
      'Flow Reliability',
      'Temporal Correlation',
      'Geographical Correlation',
      'Technological Correlation',
      'Data Collection Methods'
    ];

    if (count($scores) !== 5) {
      throw new Exception("Se esperan 5 scores, recibidos: " . count($scores));
    }

    $conn->begin_transaction();

    // 1. Actualizar dq_data_quality en processes
    $stmt = $conn->prepare("UPDATE processes SET dq_data_quality = ? WHERE uuid = ?");
    $stmt->bind_param("ss", $dq_scores, $process_uuid);
    $stmt->execute();

    // 2. Borrar indicadores previos
    $stmt = $conn->prepare("DELETE FROM process_dq_indicators WHERE process_uuid = ?");
    $stmt->bind_param("s", $process_uuid);
    $stmt->execute();

    // 3. Insertar TODOS los scores (25 registros: 5 indicadores × 5 scores)
    $stmt = $conn->prepare("
      INSERT INTO process_dq_indicators 
      (process_uuid, indicator_type, score_level, description, is_selected)
      VALUES (?, ?, ?, ?, ?)
    ");

    $total_inserted = 0;
    
    foreach ($indicators as $index => $indicator) {
      $selected_score = (int)$scores[$index];
      
      // Insertar los 5 scores para este indicador
      for ($score_level = 1; $score_level <= 5; $score_level++) {
        // Marcar como seleccionado solo si coincide con el score elegido
        $is_selected = ($score_level === $selected_score) ? 1 : 0;
        
        // Buscar descripción para este score_level
        $description = null;
        if (isset($details[$indicator])) {
          $scoreKey = (string)$score_level;
          if (isset($details[$indicator][$scoreKey])) {
            $description = $details[$indicator][$scoreKey];
          }
        }
        
        $stmt->bind_param("ssisi", 
          $process_uuid, 
          $indicator, 
          $score_level, 
          $description, 
          $is_selected
        );
        
        if (!$stmt->execute()) {
          throw new Exception("Error al guardar '$indicator' score $score_level: " . $stmt->error);
        }
        $total_inserted++;
      }
    }

    $conn->commit();
    echo "✓ Data Quality guardado exitosamente ($total_inserted registros completos)";
  } catch (Exception $e) {
    if (isset($conn)) {
      $conn->rollback();
    }
    http_response_code(500);
    echo "ERROR: " . $e->getMessage();
  }
} else {
  http_response_code(405);
  echo "Método no permitido";
}
?>
