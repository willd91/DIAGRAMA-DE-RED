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
    if (!$payload || !isset($payload['idplano'])) {
        echo json_encode(['ok' => false, 'mensaje' => 'Payload inválido']);
        exit;
    }

    $db = new ConexionBaseDeDatosPG();
    $pdo = $db->getConexion();
    $nodoCtrl = new NodoController($pdo);
    $conexionCtrl = new ConexionController($pdo);

    $idplano = (int)$payload['idplano'];
    $nodos = $payload['nodos'] ?? [];
    $aristas = $payload['aristas'] ?? [];

    $pdo->beginTransaction();

    // Eliminar conexiones y nodos del plano (más sencillo y seguro)
    // Primero eliminar conexiones que referencian nodos del plano
    $stmt = $pdo->prepare("DELETE FROM conexion_nodos WHERE nodo_origen IN (SELECT idnodo FROM nodo_plano WHERE idplano = ?) OR nodo_destino IN (SELECT idnodo FROM nodo_plano WHERE idplano = ?)");
    $stmt->execute([$idplano, $idplano]);

    // Luego eliminar nodos del plano
    $stmt = $pdo->prepare("DELETE FROM nodo_plano WHERE idplano = ?");
    $stmt->execute([$idplano]);

    // Insertar nodos y crear un mapa frontendId -> nuevoIdBD
    $map = [];
    foreach ($nodos as $n) {
        // Algunos campos del frontend vienen con claves: id, tipo_nodo (o label)
        $frontendId = $n['id'] ?? ($n['idnodo'] ?? null);
        $tipo = $n['tipo_nodo'] ?? ($n['label'] ?? 'Nodo');
        $x = isset($n['x']) ? (int)$n['x'] : 0;
        $y = isset($n['y']) ? (int)$n['y'] : 0;
        $width = isset($n['width']) ? (int)$n['width'] : 80;
        $height = isset($n['height']) ? (int)$n['height'] : 40;

        // Usamos insertar para respetar tu SP crear_nodo
        $newId = $nodoCtrl->insertar($idplano, $tipo, $x, $y, null, null, $width, $height, 0, []);
        // mapear
        if ($frontendId !== null) $map[$frontendId] = $newId;
    }

    // Insertar aristas usando mapeo
    foreach ($aristas as $a) {
        $orig = $a['origen'] ?? $a['from'] ?? null;
        $dest = $a['destino'] ?? $a['to'] ?? null;
        // mapear a BD ids si vienen frontend ids
        $bdOrig = isset($map[$orig]) ? $map[$orig] : (int)$orig;
        $bdDest = isset($map[$dest]) ? $map[$dest] : (int)$dest;
        $etiqueta = $a['etiqueta'] ?? ($a['label'] ?? '');
        $color = $a['color'] ?? ($a['style'] ?? '#000000');
        if ($bdOrig && $bdDest) {
            $conexionCtrl->insertar($bdOrig, $bdDest, 'cable', $etiqueta, $color);
        }
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'mensaje' => 'Diagrama guardado correctamente']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
}
