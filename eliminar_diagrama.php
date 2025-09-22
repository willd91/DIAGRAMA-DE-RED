<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once '../../CONTROLADOR/ConexionBaseDeDatosPG.php';
require_once '../../CONTROLADOR/NodoController.php';
require_once '../../CONTROLADOR/ConexionController.php';

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload) {
        echo json_encode(['ok' => false, 'mensaje' => 'Payload inválido']);
        exit;
    }

    $db = new ConexionBaseDeDatosPG();
    $pdo = $db->getConexion();
    $nodoCtrl = new NodoController($pdo);
    $conexionCtrl = new ConexionController($pdo);

    $nodos = $payload['nodos'] ?? [];
    $aristas = $payload['aristas'] ?? [];

    // Primero conexiones
    foreach ($aristas as $idc) {
        $idcInt = (int)$idc;
        if ($idcInt > 0) $conexionCtrl->eliminar($idcInt);
    }

    // Luego nodos (las FK con ON DELETE CASCADE o SP deben limpiar conexiones)
    foreach ($nodos as $idn) {
        $idnInt = (int)$idn;
        if ($idnInt > 0) $nodoCtrl->eliminar($idnInt);
    }

    echo json_encode(['ok' => true, 'mensaje' => 'Elementos eliminados correctamente']);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
}
