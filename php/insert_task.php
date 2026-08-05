<?php
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $process_uuid = $_POST['process_uuid'] ?? '';
        $task_description = $_POST['task_description'] ?? $_POST['task-description'] ?? '';
        $context_of_use = $_POST['context_of_use'] ?? $_POST['task-purpose'] ?? '';
        $limitations_of_use = $_POST['limitations_of_use'] ?? $_POST['task-limitations'] ?? '';
        $relationship_with_other_datasets = $_POST['relationship_with_other_datasets'] ?? $_POST['task-related-datasets'] ?? '';

        if (empty($process_uuid)) {
            throw new Exception("process_uuid es requerido");
        }

        if (empty($task_description)) {
            throw new Exception("task_description es requerido");
        }

        // Según tu esquema, la información de "tareas" o "propósito" del dataset
        // se guarda en process_documentation en los campos:
        // - intended_application (propósito/contexto de uso)
        // - project (descripción del proyecto/tarea)
        // - completeness_text (limitaciones y relaciones)

        // Preparar el texto de completeness
        $completeness_text = "";
        if (!empty($limitations_of_use)) {
            $completeness_text .= "Limitaciones: " . $limitations_of_use . "\n";
        }
        if (!empty($relationship_with_other_datasets)) {
            $completeness_text .= "Relaciones: " . $relationship_with_other_datasets;
        }

        // Actualizar o insertar en process_documentation
        $stmt = $conn->prepare("
            INSERT INTO process_documentation (
                process_uuid, 
                intended_application,
                project,
                completeness_text,
                created_at
            ) VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                intended_application = VALUES(intended_application),
                project = VALUES(project),
                completeness_text = VALUES(completeness_text),
                updated_at = NOW()
        ");
        
        $stmt->bind_param("ssss", 
            $process_uuid, 
            $context_of_use,
            $task_description,
            $completeness_text
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al guardar tarea: " . $stmt->error);
        }

        echo "Tarea guardada correctamente";

    } catch (Exception $e) {
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
} else {
    http_response_code(405);
    echo "Método no permitido";
}
?>
