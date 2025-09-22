<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once '../CONTROLADOR/ConexionBaseDeDatosPG.php';
require_once '../CONTROLADOR/PlanoController.php';

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    
    if (!$payload || !isset($payload['idplano'])) {
        throw new Exception('Datos incompletos');
    }
    
    $db = new ConexionBaseDeDatosPG();
    $planoCtrl = new PlanoController($db->getConexion());
    
    $planoCtrl->actualizar(
        $payload['idplano'],
        $payload['nombre'] ?? null,
        $payload['descripcion'] ?? null,
        $payload['imagen_url'] ?? null,
        null, // idsede
        $payload['ancho'] ?? null,
        $payload['alto'] ?? null
    );
    
    echo json_encode(['ok' => true, 'mensaje' => 'Plano actualizado correctamente']);
    
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
}
?>