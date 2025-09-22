<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once '../CONTROLADOR/ConexionBaseDeDatosPG.php';
require_once '../CONTROLADOR/PlanoController.php';

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    
    if (!$payload || !isset($payload['nombre'])) {
        throw new Exception('Datos incompletos');
    }
    
    $db = new ConexionBaseDeDatosPG();
    $planoCtrl = new PlanoController($db->getConexion());
    
    $idplano = $planoCtrl->insertar(
        $payload['nombre'],
        $payload['descripcion'] ?? null,
        $payload['imagen_url'] ?? null,
        null, // idsede (puedes ajustar según tu necesidad)
        $payload['ancho'] ?? 1000,
        $payload['alto'] ?? 800
    );
    
    echo json_encode(['ok' => true, 'idplano' => $idplano, 'mensaje' => 'Plano creado correctamente']);
    
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
}
?>