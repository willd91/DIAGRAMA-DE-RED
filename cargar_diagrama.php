<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../CONTROLADOR/ConexionBaseDeDatosPG.php';
require_once '../CONTROLADOR/PlanoController.php';
require_once '../CONTROLADOR/NodoController.php';
require_once '../CONTROLADOR/ConexionController.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = new ConexionBaseDeDatosPG();
    $conn = $db->getConexion();

    $idplano = isset($_GET['idplano']) ? (int) $_GET['idplano'] : 0;

    $planoCtrl = new PlanoController($conn);
    $nodoCtrl  = new NodoController($conn);
    $conexCtrl = new ConexionController($conn);

    // Validar que exista el plano
    $plano = $planoCtrl->obtenerPorId($idplano);
    if (!$plano) {
        throw new Exception("Plano con ID $idplano no encontrado");
    }

    $nodos   = $nodoCtrl->listar($idplano);
    $aristas = $conexCtrl->listar($idplano);

    echo json_encode([
        'nodos'   => $nodos,
        'aristas' => $aristas
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => true,
        'message' => $e->getMessage()
    ]);
}
