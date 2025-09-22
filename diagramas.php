<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: /inventario_ips/VISTAS/view_login.php");
    exit();
}

require_once '../../CONTROLADOR/PlanoController.php';
require_once '../../CONTROLADOR/NodoController.php';
require_once '../../CONTROLADOR/ConexionController.php';
require_once __DIR__ . '/../../CONTROLADOR/ConexionBaseDeDatosPG.php';

$db = new ConexionBaseDeDatosPG();
$planoCtrl = new PlanoController($db->getConexion());
$nodoCtrl = new NodoController($db->getConexion());
$conexionCtrl = new ConexionController($db->getConexion());

// Obtener lista de planos para el selector
$planos = $planoCtrl->listar();

// Plano dinámico por GET idplano (si no viene, usar el primero)

$idplano = isset($_GET['idplano']) ? intval($_GET['idplano']) : ($planos[4]['idplano'] ?? 7);
$plano = $planoCtrl->obtenerPorId($idplano);
if (!$plano) {
    die("Plano id $idplano no encontrado.");
}

$nodos = $nodoCtrl->listar($plano['idplano']);
$conexiones = $conexionCtrl->listar($plano['idplano']);

require_once '../BarraNavegacion.php';
$barra = new BarraNavegacion();
function resolvePlanoImageUrl(?string $imagen_url): string {
    if (empty($imagen_url)) {
        return '';
    }

    $imagen_url = trim($imagen_url);

    // Si es URL absoluta (http/https) -> devolver tal cual
    if (preg_match('#^https?://#i', $imagen_url)) {
        return $imagen_url;
    }

    // Ajusta según tu servidor
    $WWW_ROOT = '/var/www/html';       // raíz física del servidor web
    $BASE_WEB = '/inventario_ips';     // prefijo web donde vive la app

    // 1) Si la ruta empieza con la raíz física, transforma a ruta web
    if (strpos($imagen_url, $WWW_ROOT) === 0) {
        $relative = substr($imagen_url, strlen($WWW_ROOT)); // ej. /inventario_ips/DATA/...
        $relative = '/' . ltrim($relative, '/');
    } else {
        // forzar que quede con una barra inicial para normalizar
        $relative = '/' . ltrim($imagen_url, '/');
    }

    // 2) Evitar duplicados de BASE_WEB: colapsar repeticiones "/inventario_ips/inventario_ips/..." -> "/inventario_ips/..."
    $base_no_slash = trim($BASE_WEB, '/');
    $relative = preg_replace('#(/' . preg_quote($base_no_slash, '#') . ')+#', '/' . $base_no_slash, $relative);

    // 3) Si relative no contiene el base (p. ej. era "DATA/DIAGRAMA/..."), agregarlo
    if (strpos($relative, '/' . $base_no_slash) !== 0) {
        // evitar duplicar si relative ya contiene inventario_ips en otra posición
        $relative = '/' . $base_no_slash . '/' . ltrim($relative, '/');
    }

    // 4) Codificar cada segmento (escapa espacios y caracteres especiales sin transformar '/')
    $parts = explode('/', ltrim($relative, '/'));
    $parts = array_map('rawurlencode', $parts);
    $url = '/' . implode('/', $parts);

    return $url;
}
// Uso en la vista
$imagen_src = resolvePlanoImageUrl($plano['imagen_url'] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Diagrama - <?= htmlspecialchars($plano['nombre']) ?></title>

<link href="/inventario_ips/CSS/css/bootstrap.min.css" rel="stylesheet">
<link href="/inventario_ips/CSS/cvsecurity.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<!-- mxGraph -->
<script src="/inventario_ips/SCRIPT/MXGRAPH/mxClient.js"></script>
<link rel="stylesheet" href="/inventario_ips/SCRIPT/MXGRAPH/css/common.css">

<style>
#graphContainer { 
    width:100%; 
    height:78vh; 
    border:1px solid #ddd; 
    background:#fff; 
    position: relative;
    overflow: hidden;
}

.cvsecurity-diagrama-header { 
    display:flex; 
    justify-content:space-between; 
    align-items:center; 
    margin-bottom:0.5rem; 
    flex-wrap: wrap;
    gap: 10px;
}

#panelLateral { 
    width:300px; 
    max-height:78vh; 
    overflow:auto; 
    border-left:1px solid #ddd; 
    background:#fff;
    display: flex;
    flex-direction: column;
}

#panelPropiedades { 
    flex: 1; 
    padding: 10px;
    overflow-y: auto;
}

#panelObjetos { 
    border-top: 1px solid #eee;
    padding: 10px;
    max-height: 200px;
    overflow-y: auto;
}

.nodo-item{ 
    padding:6px; 
    margin-bottom:4px; 
    border-radius:4px; 
    cursor:pointer;
    border: 1px solid #dee2e6;
    font-size: 12px;
}
.nodo-item:hover{ background:#f8f9fa; }
.nodo-item.active{ background:#0d6efd; color:#fff; }

.controls { gap:6px; display:inline-flex; flex-wrap: wrap; }

.property-group {
    margin-bottom: 15px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 10px;
}

.property-group h6 {
    margin-bottom: 10px;
    color: #495057;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 5px;
}

.form-group-sm {
    margin-bottom: 8px;
}

.form-group-sm label {
    font-size: 12px;
    margin-bottom: 2px;
    font-weight: 500;
}

.toolbar {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 5px;
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.toolbar-group {
    display: flex;
    gap: 2px;
    align-items: center;
    padding: 2px 5px;
    border-right: 1px solid #dee2e6;
}

.toolbar-group:last-child {
    border-right: none;
}

.toolbar-btn {
    padding: 4px 8px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
}

.toolbar-btn:hover {
    background: #e9ecef;
}

.toolbar-btn.active {
    background: #0d6efd;
    color: white;
    border-color: #0d6efd;
}

.color-preview {
    width: 20px;
    height: 20px;
    border: 1px solid #ccc;
    border-radius: 3px;
    display: inline-block;
    vertical-align: middle;
    margin-right: 5px;
}

#imagenFondo {
    position: absolute;
    top: 0;
    left: 0;
    z-index: -1;
    opacity: 0.3;
    pointer-events: none;
}
</style>
</head>
<body>

<div id="header-container" style="position:fixed; top:0; left:240px; right:0; z-index:1030; width:calc(100% - 240px);">
    <?php $barra->render($_SESSION['nombre'] ?? '', $_SESSION['usuario'] ?? ''); ?>
</div>

<div class="cvsecurity-main-container d-flex" style="margin-top:70px; margin-left:240px;">
    <div style="flex:1; display:flex; flex-direction:column;">
        <div class="cvsecurity-diagrama-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <select id="selectorPlano" class="form-select form-select-sm" style="width:200px;">
                    <?php foreach($planos as $p): ?>
                        <option value="<?= $p['idplano'] ?>" <?= $p['idplano'] == $idplano ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-primary" id="btnNuevoPlano">
                    <i class="bi bi-plus-circle"></i> Nuevo Plano
                </button>
            </div>
            <div class="toolbar">
                <button id="btnInsertarEquipo">Insertar equipo de red</button>
            </div>
            <div class="controls">
                <div class="toolbar">
                    <div class="toolbar-group">
                        <select id="toolForma" class="form-select form-select-sm">
                            <option value="rectangle">Rectángulo</option>
                            <option value="ellipse">Elipse</option>
                            <option value="rhombus">Rombo</option>
                            <option value="triangle">Triángulo</option>
                            <option value="cylinder">Cilindro</option>
                        </select>
                    </div>
                    
                    <div class="toolbar-group">
                        <button class="toolbar-btn" title="Conectar" id="btnConnect">
                            <i class="bi bi-arrow-left-right"></i>
                        </button>
                        <button class="toolbar-btn" title="Texto" id="btnText">
                            <i class="bi bi-fonts"></i>
                        </button>
                    </div>
                    
                    <div class="toolbar-group">
                        <input type="color" id="colorPicker" title="Color de relleno" style="width:30px; height:30px;">
                        <input type="color" id="borderColorPicker" title="Color de borde" style="width:30px; height:30px;">
                    </div>
                    
                    <div class="toolbar-group">
                        <button class="btn btn-sm btn-outline-primary" id="btnZoomIn" title="Zoom In">
                            <i class="bi bi-zoom-in"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary" id="btnZoomOut" title="Zoom Out">
                            <i class="bi bi-zoom-out"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" id="btnReset" title="Reset Zoom">
                            <i class="bi bi-arrows-angle-expand"></i>
                        </button>
                    </div>
                    
                    <div class="toolbar-group">
                        <button class="btn btn-sm btn-success" id="btnGuardar" title="Guardar">
                            <i class="bi bi-save"></i> Guardar
                        </button>
                        <button class="btn btn-sm btn-danger" id="btnEliminar" title="Eliminar selección">
                            <i class="bi bi-trash"></i> 
                        </button>
                        <button id="btnImagenFondo" class="btn btn-secondary">
                            Imagen de Fondo
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenedor del gráfico con imagen de fondo -->
        <div id="graphContainer" style="position:relative;">
    <?php if ($imagen_src !== ''): ?>
        <img id="imagenFondo"
             src="<?= htmlspecialchars($imagen_src, ENT_QUOTES, 'UTF-8') ?>"
             style="position:absolute; top:0; left:0; width:100%; height:100%; object-fit:contain; opacity:0.35; pointer-events:none; z-index:0;">
    <?php else: ?>
        <img id="imagenFondo" src="" style="display:none;">
    <?php endif; ?>
    <!-- el contenido del grafo irá aquí (mxGraph) y debe tener z-index > 0 -->
</div>
    </div>

    <!-- Panel lateral de propiedades -->
    <aside id="panelLateral">
        <div id="panelPropiedades">
            <h6>Propiedades del Elemento</h6>
            <div id="propiedadesContenido">
                <div class="alert alert-info" id="sinSeleccion">
                    Seleccione un elemento para editar sus propiedades
                </div>
                
                <!-- Propiedades de Nodo (oculto por defecto) -->
                <div id="propiedadesNodo" style="display:none;">
                    <div class="property-group">
                        <h6>Texto</h6>
                        <div class="form-group-sm">
                            <label>Contenido:</label>
                            <input type="text" id="propTexto" class="form-control form-control-sm" 
                                   placeholder="Texto del nodo">
                        </div>
                        <div class="form-group-sm">
                            <label>Tamaño fuente:</label>
                            <input type="number" id="propFontSize" class="form-control form-control-sm" 
                                   min="8" max="72" value="12">
                        </div>
                        <div class="form-group-sm">
                            <label>Color texto:</label>
                            <input type="color" id="propFontColor" class="form-control form-control-sm">
                        </div>
                    </div>
                    
                    <div class="property-group">
                        <h6>Apariencia</h6>
                        <div class="form-group-sm">
                            <label>Color fondo:</label>
                            <input type="color" id="propFillColor" class="form-control form-control-sm">
                        </div>
                        <div class="form-group-sm">
                            <label>Color borde:</label>
                            <input type="color" id="propStrokeColor" class="form-control form-control-sm">
                        </div>
                        <div class="form-group-sm">
                            <label>Grosor borde:</label>
                            <input type="number" id="propStrokeWidth" class="form-control form-control-sm" 
                                   min="1" max="10" value="1">
                        </div>
                    </div>
                    
                    <div class="property-group">
                        <h6>Posición y Tamaño</h6>
                        <div class="row">
                            <div class="col-6">
                                <label>X:</label>
                                <input type="number" id="propX" class="form-control form-control-sm">
                            </div>
                            <div class="col-6">
                                <label>Y:</label>
                                <input type="number" id="propY" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <label>Ancho:</label>
                                <input type="number" id="propWidth" class="form-control form-control-sm">
                            </div>
                            <div class="col-6">
                                <label>Alto:</label>
                                <input type="number" id="propHeight" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="form-group-sm mt-2">
                            <label>Rotación:</label>
                            <input type="number" id="propRotacion" class="form-control form-control-sm" 
                                   min="0" max="360" value="0">
                        </div>
                    </div>
                    
                    <button class="btn btn-sm btn-primary w-100" id="btnAplicarPropiedades">
                        Aplicar Cambios
                    </button>
                </div>
                
                <!-- Propiedades de Conexión (oculto por defecto) -->
                <div id="propiedadesConexion" style="display:none;">
                    <div class="property-group">
                        <h6>Texto</h6>
                        <div class="form-group-sm">
                            <label>Etiqueta:</label>
                            <input type="text" id="propEtiquetaConexion" class="form-control form-control-sm">
                        </div>
                    </div>
                    
                    <div class="property-group">
                        <h6>Estilo</h6>
                        <div class="form-group-sm">
                            <label>Color línea:</label>
                            <input type="color" id="propColorConexion" class="form-control form-control-sm">
                        </div>
                        <div class="form-group-sm">
                            <label>Grosor línea:</label>
                            <input type="number" id="propGrosorConexion" class="form-control form-control-sm" 
                                   min="1" max="10" value="2">
                        </div>
                        <div class="form-group-sm">
                            <label>Estilo línea:</label>
                            <select id="propEstiloConexion" class="form-select form-select-sm">
                                <option value="solid">Sólida</option>
                                <option value="dashed">Discontinua</option>
                                <option value="dotted">Punteada</option>
                            </select>
                        </div>
                    </div>
                    
                    <button class="btn btn-sm btn-primary w-100" id="btnAplicarPropiedadesConexion">
                        Aplicar Cambios
                    </button>
                </div>
            </div>
        </div>
        
        <div id="panelObjetos">
            <h6>Objetos en el Plano</h6>
            <div id="listaNodos">
                <?php foreach($nodos as $nodo): ?>
                    <div class="nodo-item" data-id="<?= $nodo['idnodo'] ?>">
                        <small><?= htmlspecialchars($nodo['tipo_nodo'] . ' #' . $nodo['idnodo']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>
</div>

<!-- Modal para nuevo plano -->
<div class="modal fade" id="modalNuevoPlano" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crear Nuevo Plano</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNuevoPlano">
                    <div class="mb-3">
                        <label class="form-label">Nombre del plano:</label>
                        <input type="text" class="form-control" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción:</label>
                        <textarea class="form-control" name="descripcion" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL de imagen de fondo (opcional):</label>
                        <input type="text" class="form-control" name="imagen_url" 
                               placeholder="https://ejemplo.com/imagen.jpg">
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <label class="form-label">Ancho:</label>
                            <input type="number" class="form-control" name="ancho" value="1000" min="100">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Alto:</label>
                            <input type="number" class="form-control" name="alto" value="800" min="100">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnCrearPlano">Crear</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para cargar imagen de fondo -->
<div class="modal fade" id="modalImagenFondo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configurar Imagen de Fondo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formImagenFondo">
                    <div class="mb-3">
                        <label class="form-label">URL de la imagen:</label>
                        <input type="text" class="form-control" id="urlImagenFondo" 
                               value="<?= htmlspecialchars($plano['imagen_url'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Opacidad:</label>
                        <input type="range" class="form-range" id="opacidadImagen" min="0.1" max="1" step="0.1" value="0.3">
                        <span id="valorOpacidad">0.3</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Escala:</label>
                        <input type="range" class="form-range" id="escalaImagen" min="0.1" max="2" step="0.1" value="1">
                        <span id="valorEscala">1</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnAplicarImagen">Aplicar</button>
            </div>
        </div>
    </div>
</div>
<div id="modalEquipos" style="display:none; position:fixed; top:20%; left:30%; background:#fff; padding:20px; border:1px solid #ccc; z-index:1000;">
    <h3>Buscar equipo de red</h3>
    <input type="text" id="buscarEquipo" placeholder="Escriba el nombre...">
    <div id="listaEquipos" style="max-height:200px; overflow-y:auto; margin-top:10px;"></div>
    <button onclick="cerrarModalEquipos()">Cerrar</button>
</div>
<script src="/inventario_ips/SCRIPT/js/bootstrap.bundle.min.js"></script>
<script>
    // Variables globales
let graph, parent, currentPlanoId = <?= $plano['idplano'] ?>;
let nodosMap = new Map();
let celdaSeleccionada = null;

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    if (!mxClient.isBrowserSupported()) {
        alert('Tu navegador no soporta mxGraph');
        return;
    }
    
    inicializarGraph();
    configurarEventos();
    configurarToolbar();
});

function inicializarGraph() {
    const container = document.getElementById('graphContainer');
    graph = new mxGraph(container);
    parent = graph.getDefaultParent();

    // Configurar opciones del gráfico
    graph.setPanning(true);
    graph.setConnectable(true);
    graph.setCellsMovable(true);
    graph.setCellsResizable(true);
    graph.setCellsEditable(true);
    graph.setMultigraph(false);
    graph.setAllowDanglingEdges(false);
    graph.setDropEnabled(false);

    // Configurar estilos por defecto
    const style = graph.getStylesheet().getDefaultVertexStyle();
    style[mxConstants.STYLE_FONTSIZE] = 12;
    style[mxConstants.STYLE_FONTCOLOR] = '#000000';
    style[mxConstants.STYLE_STROKECOLOR] = '#000000';
    style[mxConstants.STYLE_FILLCOLOR] = '#ffffff';
    style[mxConstants.STYLE_GRADIENTCOLOR] = '#ffffff';
    style[mxConstants.STYLE_STROKEWIDTH] = 1;
    style[mxConstants.STYLE_ROUNDED] = true;
    style[mxConstants.STYLE_SHADOW] = false;

    // Estilo para aristas
    const edgeStyle = graph.getStylesheet().getDefaultEdgeStyle();
    edgeStyle[mxConstants.STYLE_EDGE] = mxEdgeStyle.ElbowConnector;
    edgeStyle[mxConstants.STYLE_STROKECOLOR] = '#000000';
    edgeStyle[mxConstants.STYLE_STROKEWIDTH] = 2;
    edgeStyle[mxConstants.STYLE_ROUNDED] = true;
    edgeStyle[mxConstants.STYLE_ENDARROW] = mxConstants.ARROW_CLASSIC;

    // Cargar datos del plano actual
    cargarDatosPlano(currentPlanoId);

    // Eventos de selección
    graph.getSelectionModel().addListener(mxEvent.CHANGE, function(sender, evt) {
        actualizarPanelPropiedades();
    });

    // Evento de doble clic para crear nodos
    graph.addListener(mxEvent.DOUBLE_CLICK, function(sender, evt) {
        const cell = evt.getProperty('cell');
        if (!cell) {
            const pt = graph.getPointForEvent(evt.getProperty('event'));
            crearNodoEnPosicion(pt.x, pt.y);
        }
    });

    // Soporte para teclado (Delete/Suppr)
    mxEvent.addListener(document.body, 'keydown', function(evt) {
        if (evt.keyCode === 46 ) { // Delete o Backspace
            eliminarSeleccion();
            mxEvent.consume(evt);
        }
    });
}

function configurarEventos() {
    // Selector de plano
    document.getElementById('selectorPlano').addEventListener('change', function() {
        const nuevoId = parseInt(this.value);
        if (nuevoId !== currentPlanoId) {
            if (confirm('¿Guardar cambios antes de cambiar de plano?')) {
                guardarDiagrama().then(() => {
                    cargarPlano(nuevoId);
                });
            } else {
                cargarPlano(nuevoId);
            }
        }
    });

    // Botón nuevo plano
    document.getElementById('btnNuevoPlano').addEventListener('click', function() {
        new bootstrap.Modal(document.getElementById('modalNuevoPlano')).show();
    });

    // Crear plano
    document.getElementById('btnCrearPlano').addEventListener('click', crearNuevoPlano);

    // Controles de zoom
    document.getElementById('btnZoomIn').addEventListener('click', () => graph.zoomIn());
    document.getElementById('btnZoomOut').addEventListener('click', () => graph.zoomOut());
    document.getElementById('btnReset').addEventListener('click', () => {
        graph.zoomTo(1);
        graph.center();
    });

    // Guardar y eliminar
    document.getElementById('btnGuardar').addEventListener('click', guardarDiagrama);
    document.getElementById('btnEliminar').addEventListener('click', eliminarSeleccion);

    // Aplicar propiedades
    document.getElementById('btnAplicarPropiedades').addEventListener('click', aplicarPropiedadesNodo);
    document.getElementById('btnAplicarPropiedadesConexion').addEventListener('click', aplicarPropiedadesConexion);

    // Configurar imagen de fondo
    document.getElementById('btnAplicarImagen').addEventListener('click', aplicarImagenFondo);

    // Cambios en propiedades en tiempo real
    ['propTexto', 'propFontSize', 'propFontColor', 'propFillColor', 'propStrokeColor', 
     'propStrokeWidth', 'propX', 'propY', 'propWidth', 'propHeight', 'propRotacion'].forEach(id => {
        document.getElementById(id).addEventListener('change', aplicarPropiedadesNodo);
    });

    ['propEtiquetaConexion', 'propColorConexion', 'propGrosorConexion', 'propEstiloConexion'].forEach(id => {
        document.getElementById(id).addEventListener('change', aplicarPropiedadesConexion);
    });
}

// ... (continuará con las funciones restantes en la siguiente parte)
// Continuación del script anterior - funciones completas
function configurarToolbar() {
    // Selector de forma
    document.getElementById('toolForma').addEventListener('change', function() {
        graph.setDefaultVertexStyle(`shape=${this.value}`);
    });

    // Modo conexión
    document.getElementById('btnConnect').addEventListener('click', function() {
        graph.setConnectable(!graph.isConnectable());
        this.classList.toggle('active', graph.isConnectable());
    });

    // Modo texto
    document.getElementById('btnText').addEventListener('click', function() {
        // Crear un nodo de texto especial
        const cell = graph.getSelectionCell();
        if (cell && graph.getModel().isVertex(cell)) {
            graph.startEditingAtCell(cell);
        }
    });

    // Selectores de color
    document.getElementById('colorPicker').addEventListener('change', function() {
        if (celdaSeleccionada) {
            graph.setCellStyles(mxConstants.STYLE_FILLCOLOR, this.value, [celdaSeleccionada]);
        } else {
            graph.setDefaultVertexStyle(`fillColor=${this.value}`);
        }
    });

    document.getElementById('borderColorPicker').addEventListener('change', function() {
        if (celdaSeleccionada) {
            graph.setCellStyles(mxConstants.STYLE_STROKECOLOR, this.value, [celdaSeleccionada]);
        } else {
            graph.setDefaultVertexStyle(`strokeColor=${this.value}`);
        }
    });
}

function cargarPlano(idplano) {
    fetch(`diagrama.php?idplano=${idplano}`)
        .then(response => response.json())
        .then(data => {
            currentPlanoId = idplano;
            limpiarDiagrama();
            cargarNodos(data.nodos);
            cargarConexiones(data.aristas);
            actualizarInfoPlano();
        })
        .catch(error => console.error('Error cargando plano:', error));
       
}

function cargarDatosPlano(idplano) {
    // Usar los datos ya cargados en PHP para evitar otra petición
   const nodosData = <?= json_encode($nodos ?? []) ?>;
const conexionesData = <?= json_encode($conexiones ?? []) ?>;
    
    cargarNodos(nodosData);
    cargarConexiones(conexionesData);
}

function cargarNodos(nodos) {
     if (!Array.isArray(nodos)) {
        console.warn("⚠️ cargarNodos: nodos no es un array", nodos);
        return; // no hacemos nada
    }
    graph.getModel().beginUpdate();
    try {
        nodos.forEach(nodo => {
            const style = construirEstiloNodo(nodo);
            const vertex = graph.insertVertex(
                parent, 
                nodo.idnodo.toString(), 
                nodo.tipo_nodo, 
                nodo.x, 
                nodo.y, 
                nodo.width, 
                nodo.height, 
                style
            );
            nodosMap.set(nodo.idnodo, vertex);
            agregarNodoALista(nodo.idnodo, nodo.tipo_nodo);
        });
    } finally {
        graph.getModel().endUpdate();
    }
}

function cargarConexiones(conexiones) {
    graph.getModel().beginUpdate();
    try {
        conexiones.forEach(conexion => {
            const origen = nodosMap.get(conexion.nodo_origen);
            const destino = nodosMap.get(conexion.nodo_destino);
            
            if (origen && destino) {
                const style = construirEstiloConexion(conexion);
                graph.insertEdge(
                    parent,
                    conexion.idconexion.toString(),
                    conexion.etiqueta || '',
                    origen,
                    destino,
                    style
                );
            }
        });
    } finally {
        graph.getModel().endUpdate();
    }
}

function construirEstiloNodo(nodo) {
    const metadata = nodo.metadata ? JSON.parse(nodo.metadata) : {};
    return [
        `fillColor=${metadata.fillColor || '#e3f2fd'}`,
        `strokeColor=${metadata.strokeColor || '#2196f3'}`,
        `fontSize=${metadata.fontSize || 12}`,
        `fontColor=${metadata.fontColor || '#000000'}`,
        `strokeWidth=${metadata.strokeWidth || 1}`,
        `shape=${metadata.shape || 'rectangle'}`,
        `rounded=${metadata.rounded !== undefined ? metadata.rounded : true}`
    ].join(';');
}

function construirEstiloConexion(conexion) {
    return [
        `strokeColor=${conexion.color || '#000000'}`,
        `strokeWidth=${conexion.strokeWidth || 2}`,
        `endArrow=${conexion.endArrow || 'classic'}`,
        `rounded=true`
    ].join(';');
}

function crearNodoEnPosicion(x, y) {
    const forma = document.getElementById('toolForma').value;
    const fillColor = document.getElementById('colorPicker').value;
    const strokeColor = document.getElementById('borderColorPicker').value;
    
    graph.getModel().beginUpdate();
    try {
        const vertex = graph.insertVertex(
            parent,
            null,
            'Nuevo Nodo',
            x - 40, // Centrar en el punto de clic
            y - 20,
            80,
            40,
            `shape=${forma};fillColor=${fillColor};strokeColor=${strokeColor}`
        );
        
        // Agregar a la lista lateral
        agregarNodoALista(vertex.getId(), 'Nuevo Nodo');
    } finally {
        graph.getModel().endUpdate();
    }
}

function agregarNodoALista(id, etiqueta) {
    const lista = document.getElementById('listaNodos');
    const div = document.createElement('div');
    div.className = 'nodo-item';
    div.dataset.id = id;
    div.innerHTML = `<small>${etiqueta} #${id}</small>`;
    
    div.addEventListener('click', () => {
        const cell = graph.getModel().getCell(id);
        if (cell) {
            graph.setSelectionCell(cell);
        }
    });
    
    lista.appendChild(div);
}

function actualizarPanelPropiedades() {
    const selection = graph.getSelectionCells();
    celdaSeleccionada = selection.length === 1 ? selection[0] : null;
    
    document.getElementById('sinSeleccion').style.display = celdaSeleccionada ? 'none' : 'block';
    document.getElementById('propiedadesNodo').style.display = 
        (celdaSeleccionada && graph.getModel().isVertex(celdaSeleccionada)) ? 'block' : 'none';
    document.getElementById('propiedadesConexion').style.display = 
        (celdaSeleccionada && graph.getModel().isEdge(celdaSeleccionada)) ? 'block' : 'none';
    
    if (celdaSeleccionada) {
        if (graph.getModel().isVertex(celdaSeleccionada)) {
            cargarPropiedadesNodo(celdaSeleccionada);
        } else if (graph.getModel().isEdge(celdaSeleccionada)) {
            cargarPropiedadesConexion(celdaSeleccionada);
        }
    }
}

function cargarPropiedadesNodo(cell) {
    const geometry = cell.getGeometry();
    const style = cell.getStyle();
    
    document.getElementById('propTexto').value = cell.getValue() || '';
    document.getElementById('propX').value = Math.round(geometry.x);
    document.getElementById('propY').value = Math.round(geometry.y);
    document.getElementById('propWidth').value = Math.round(geometry.width);
    document.getElementById('propHeight').value = Math.round(geometry.height);
    
    // Extraer valores del estilo
    const fillColor = mxUtils.getValue(style, mxConstants.STYLE_FILLCOLOR, '#ffffff');
    const strokeColor = mxUtils.getValue(style, mxConstants.STYLE_STROKECOLOR, '#000000');
    const fontSize = mxUtils.getValue(style, mxConstants.STYLE_FONTSIZE, 12);
    const fontColor = mxUtils.getValue(style, mxConstants.STYLE_FONTCOLOR, '#000000');
    const strokeWidth = mxUtils.getValue(style, mxConstants.STYLE_STROKEWIDTH, 1);
    
    document.getElementById('propFillColor').value = fillColor;
    document.getElementById('propStrokeColor').value = strokeColor;
    document.getElementById('propFontSize').value = fontSize;
    document.getElementById('propFontColor').value = fontColor;
    document.getElementById('propStrokeWidth').value = strokeWidth;
}

function cargarPropiedadesConexion(cell) {
    const style = cell.getStyle();
    
    document.getElementById('propEtiquetaConexion').value = cell.getValue() || '';
    
    const strokeColor = mxUtils.getValue(style, mxConstants.STYLE_STROKECOLOR, '#000000');
    const strokeWidth = mxUtils.getValue(style, mxConstants.STYLE_STROKEWIDTH, 2);
    
    document.getElementById('propColorConexion').value = strokeColor;
    document.getElementById('propGrosorConexion').value = strokeWidth;
}

function aplicarPropiedadesNodo() {
    if (!celdaSeleccionada || !graph.getModel().isVertex(celdaSeleccionada)) return;
    
    graph.getModel().beginUpdate();
    try {
        // Actualizar geometría
        const geometry = celdaSeleccionada.getGeometry().clone();
        geometry.x = parseInt(document.getElementById('propX').value);
        geometry.y = parseInt(document.getElementById('propY').value);
        geometry.width = parseInt(document.getElementById('propWidth').value);
        geometry.height = parseInt(document.getElementById('propHeight').value);
        graph.getModel().setGeometry(celdaSeleccionada, geometry);
        
        // Actualizar texto
        graph.getModel().setValue(celdaSeleccionada, document.getElementById('propTexto').value);
        
        // Actualizar estilo
        const newStyle = {
            [mxConstants.STYLE_FONTSIZE]: document.getElementById('propFontSize').value,
            [mxConstants.STYLE_FONTCOLOR]: document.getElementById('propFontColor').value,
            [mxConstants.STYLE_FILLCOLOR]: document.getElementById('propFillColor').value,
            [mxConstants.STYLE_STROKECOLOR]: document.getElementById('propStrokeColor').value,
            [mxConstants.STYLE_STROKEWIDTH]: document.getElementById('propStrokeWidth').value
        };
        
        // Aplicar cada propiedad de estilo individualmente
        Object.keys(newStyle).forEach(styleKey => {
            graph.setCellStyles(styleKey, newStyle[styleKey], [celdaSeleccionada]);
        });
        
        // Actualizar rotación si existe
        const rotacion = document.getElementById('propRotacion').value;
        if (rotacion !== undefined) {
            graph.setCellStyles(mxConstants.STYLE_ROTATION, rotacion, [celdaSeleccionada]);
        }
        
    } finally {
        graph.getModel().endUpdate();
    }
    
    // Actualizar la lista lateral
    actualizarListaNodos();
}

function aplicarPropiedadesConexion() {
    if (!celdaSeleccionada || !graph.getModel().isEdge(celdaSeleccionada)) return;
    
    graph.getModel().beginUpdate();
    try {
        // Actualizar etiqueta
        graph.getModel().setValue(celdaSeleccionada, document.getElementById('propEtiquetaConexion').value);
        
        // Actualizar estilo
        const strokeColor = document.getElementById('propColorConexion').value;
        const strokeWidth = document.getElementById('propGrosorConexion').value;
        const estiloLinea = document.getElementById('propEstiloConexion').value;
        
        graph.setCellStyles(mxConstants.STYLE_STROKECOLOR, strokeColor, [celdaSeleccionada]);
        graph.setCellStyles(mxConstants.STYLE_STROKEWIDTH, strokeWidth, [celdaSeleccionada]);
        
        // Aplicar estilo de línea
        let dashPattern = '';
        switch(estiloLinea) {
            case 'dashed':
                dashPattern = '5 5';
                break;
            case 'dotted':
                dashPattern = '1 5';
                break;
            default:
                dashPattern = '';
        }
        graph.setCellStyles(mxConstants.STYLE_DASHED, dashPattern ? '1' : '0', [celdaSeleccionada]);
        graph.setCellStyles(mxConstants.STYLE_DASH_PATTERN, dashPattern, [celdaSeleccionada]);
        
    } finally {
        graph.getModel().endUpdate();
    }
}

function eliminarSeleccion() {
    const selection = graph.getSelectionCells();
    if (selection.length === 0) {
        alert('Seleccione uno o más elementos para eliminar');
        return;
    }
    
    if (!confirm(`¿Eliminar ${selection.length} elemento(s) seleccionado(s)?`)) {
        return;
    }
    
    const nodosAEliminar = [];
    const conexionesAEliminar = [];
    
    selection.forEach(cell => {
        if (graph.getModel().isVertex(cell)) {
            nodosAEliminar.push(parseInt(cell.getId()));
            // Remover de la lista lateral
            const elementoLista = document.querySelector(`.nodo-item[data-id="${cell.getId()}"]`);
            if (elementoLista) elementoLista.remove();
        } else if (graph.getModel().isEdge(cell)) {
            conexionesAEliminar.push(parseInt(cell.getId()));
        }
    });
    
    // Eliminar visualmente
    graph.removeCells(selection);
    
    // Enviar petición para eliminar en BD
    if (nodosAEliminar.length > 0 || conexionesAEliminar.length > 0) {
        fetch('eliminar_diagrama.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                nodos: nodosAEliminar,
                aristas: conexionesAEliminar
            })
        })
        .then(response => response.json())
        .then(result => {
            if (!result.ok) {
                alert('Error al eliminar: ' + result.mensaje);
            }
        })
        .catch(error => {
            console.error('Error eliminando elementos:', error);
            alert('Error al eliminar elementos');
        });
    }
}

async function guardarDiagrama() {
    const vertices = graph.getChildVertices(parent);
    const edges = graph.getChildEdges(parent);
    
    // Preparar datos de nodos
    const nodosData = vertices.map(vertex => {
        const geometry = vertex.getGeometry();
        const style = vertex.getStyle();
        const metadata = {
            fillColor: mxUtils.getValue(style, mxConstants.STYLE_FILLCOLOR, '#ffffff'),
            strokeColor: mxUtils.getValue(style, mxConstants.STYLE_STROKECOLOR, '#000000'),
            fontSize: mxUtils.getValue(style, mxConstants.STYLE_FONTSIZE, 12),
            fontColor: mxUtils.getValue(style, mxConstants.STYLE_FONTCOLOR, '#000000'),
            strokeWidth: mxUtils.getValue(style, mxConstants.STYLE_STROKEWIDTH, 1),
            shape: mxUtils.getValue(style, mxConstants.STYLE_SHAPE, 'rectangle'),
            rounded: mxUtils.getValue(style, mxConstants.STYLE_ROUNDED, true)
        };
        
        return {
            id: vertex.getId(),
            tipo_nodo: vertex.getValue() || 'Nodo',
            x: Math.round(geometry.x),
            y: Math.round(geometry.y),
            width: Math.round(geometry.width),
            height: Math.round(geometry.height),
            rotacion: mxUtils.getValue(style, mxConstants.STYLE_ROTATION, 0),
            metadata: metadata
        };
    });
    
    // Preparar datos de conexiones
    const conexionesData = edges.map(edge => {
        const style = edge.getStyle();
        return {
            id: edge.getId(),
            origen: edge.source ? edge.source.getId() : null,
            destino: edge.target ? edge.target.getId() : null,
            etiqueta: edge.getValue() || '',
            color: mxUtils.getValue(style, mxConstants.STYLE_STROKECOLOR, '#000000'),
            strokeWidth: mxUtils.getValue(style, mxConstants.STYLE_STROKEWIDTH, 2),
            estiloLinea: mxUtils.getValue(style, mxConstants.STYLE_DASHED, '0') === '1' ? 'dashed' : 'solid'
        };
    });
    
    try {
        const response = await fetch('guardar_diagrama.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                idplano: currentPlanoId,
                nodos: nodosData,
                aristas: conexionesData
            })
        });
        
        const result = await response.json();
        
        if (result.ok) {
            // Actualizar IDs si es necesario (para nuevos elementos)
            if (result.nuevosIds) {
                actualizarIdsElementos(result.nuevosIds);
            }
            
            alert('Diagrama guardado correctamente');
            return true;
        } else {
            alert('Error al guardar: ' + result.mensaje);
            return false;
        }
    } catch (error) {
        console.error('Error guardando diagrama:', error);
        alert('Error al guardar el diagrama');
        return false;
    }
}

function actualizarIdsElementos(nuevosIds) {
    // Actualizar IDs de elementos nuevos en el mapa y la lista
    nuevosIds.forEach(({ viejoId, nuevoId, tipo }) => {
        if (tipo === 'nodo') {
            const cell = graph.getModel().getCell(viejoId);
            if (cell) {
                graph.getModel().setId(cell, nuevoId.toString());
                nodosMap.delete(parseInt(viejoId));
                nodosMap.set(nuevoId, cell);
                
                // Actualizar lista lateral
                const elementoLista = document.querySelector(`.nodo-item[data-id="${viejoId}"]`);
                if (elementoLista) {
                    elementoLista.dataset.id = nuevoId;
                    elementoLista.innerHTML = `<small>${cell.getValue()} #${nuevoId}</small>`;
                }
            }
        }
    });
}

function crearNuevoPlano() {
    const form = document.getElementById('formNuevoPlano');
    const formData = new FormData(form);
    
    const datosPlano = {
        nombre: formData.get('nombre'),
        descripcion: formData.get('descripcion'),
        imagen_url: formData.get('imagen_url'),
        ancho: parseInt(formData.get('ancho')),
        alto: parseInt(formData.get('alto'))
    };
    
    fetch('crear_plano.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(datosPlano)
    })
    .then(response => response.json())
    .then(result => {
        if (result.ok) {
            // Cerrar modal
            bootstrap.Modal.getInstance(document.getElementById('modalNuevoPlano')).hide();
            
            // Recargar lista de planos y cambiar al nuevo
            location.href = `diagramas.php?idplano=${result.idplano}`;
        } else {
            alert('Error creando plano: ' + result.mensaje);
        }
    })
    .catch(error => {
        console.error('Error creando plano:', error);
        alert('Error al crear el plano');
    });
}

function aplicarImagenFondo() {
    const urlImagen = document.getElementById('urlImagenFondo').value;
    const opacidad = document.getElementById('opacidadImagen').value;
    const escala = document.getElementById('escalaImagen').value;
    
    const imgFondo = document.getElementById('imagenFondo');
    
    if (urlImagen) {
        imgFondo.src = urlImagen;
        imgFondo.style.display = 'block';
        imgFondo.style.opacity = opacidad;
        imgFondo.style.transform = `scale(${escala})`;
        
        // Guardar en el plano actual
        fetch('actualizar_plano.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                idplano: currentPlanoId,
                imagen_url: urlImagen
            })
        });
    } else {
        imgFondo.style.display = 'none';
    }
    
    // Cerrar modal
    bootstrap.Modal.getInstance(document.getElementById('modalImagenFondo')).hide();
}

function limpiarDiagrama() {
    graph.getModel().beginUpdate();
    try {
        graph.removeCells(graph.getChildCells(parent, true, true));
        nodosMap.clear();
        
        // Limpiar lista lateral
        document.getElementById('listaNodos').innerHTML = '';
    } finally {
        graph.getModel().endUpdate();
    }
}

function actualizarListaNodos() {
    const lista = document.getElementById('listaNodos');
    lista.innerHTML = '';
    
    const vertices = graph.getChildVertices(parent);
    vertices.forEach(vertex => {
        agregarNodoALista(vertex.getId(), vertex.getValue() || 'Nodo');
    });
}

function actualizarInfoPlano() {
    // Actualizar información del plano en la interfaz
    fetch(`obtener_plano.php?idplano=${currentPlanoId}`)
        .then(response => response.json())
        .then(plano => {
            document.title = `Diagrama - ${plano.nombre}`;
            // Actualizar otros elementos de la interfaz si es necesario
        })
        .catch(error => console.error('Error obteniendo info del plano:', error));
}

// Configurar arrastrar y soltar para imágenes
function configurarDragAndDrop() {
    const container = document.getElementById('graphContainer');
    
    container.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        container.style.backgroundColor = '#f0f8ff';
    });
    
    container.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        container.style.backgroundColor = '';
    });
    
    container.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        container.style.backgroundColor = '';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            if (file.type.startsWith('image/')) {
                // Crear un nodo con la imagen
                const reader = new FileReader();
                reader.onload = function(e) {
                    const pt = graph.getPointForEvent(e);
                    crearNodoImagen(pt.x, pt.y, e.target.result);
                };
                reader.readAsDataURL(file);
            }
        }
    });
}

function crearNodoImagen(x, y, srcImagen) {
    graph.getModel().beginUpdate();
    try {
        const vertex = graph.insertVertex(
            parent,
            null,
            '',
            x - 50,
            y - 50,
            100,
            100,
            `shape=image;image=${srcImagen};fillColor=transparent;strokeColor=#000000;`
        );
        
        agregarNodoALista(vertex.getId(), 'Imagen');
    } finally {
        graph.getModel().endUpdate();
    }
}

// Configurar atajos de teclado adicionales
function configurarAtajosTeclado() {
    document.addEventListener('keydown', function(e) {
        // Ctrl+S para guardar
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            guardarDiagrama();
        }
        
        // Ctrl+Z para deshacer
        if (e.ctrlKey && e.key === 'z') {
            e.preventDefault();
            graph.undo();
        }
        
        // Ctrl+Y para rehacer
        if (e.ctrlKey && e.key === 'y') {
            e.preventDefault();
            graph.redo();
        }
        
        // Ctrl+A para seleccionar todo
        if (e.ctrlKey && e.key === 'a') {
            e.preventDefault();
            graph.selectAll();
        }
    });
}

// Inicializar funciones adicionales cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    configurarDragAndDrop();
    configurarAtajosTeclado();
    
    // Configurar controles de opacidad y escala
    document.getElementById('opacidadImagen').addEventListener('input', function() {
        document.getElementById('valorOpacidad').textContent = this.value;
    });
    
    document.getElementById('escalaImagen').addEventListener('input', function() {
        document.getElementById('valorEscala').textContent = this.value;
    });
    
    // Botón para abrir modal de imagen de fondo
    document.getElementById('btnImagenFondo').addEventListener('click', function() {
        new bootstrap.Modal(document.getElementById('modalImagenFondo')).show();
    });
});

// Función para exportar el diagrama como imagen
function exportarComoImagen() {
    const background = graph.background;
    graph.background = '#ffffff'; // Fondo blanco para exportar
    
    const bounds = graph.getGraphBounds();
    const img = graph.getImage(null, '#ffffff', 1, bounds, true);
    
    graph.background = background; // Restaurar fondo original
    
    const link = document.createElement('a');
    link.download = `diagrama_${currentPlanoId}_${new Date().getTime()}.png`;
    link.href = img;
    link.click();
}

// Función para imprimir el diagrama
function imprimirDiagrama() {
    const originalBackground = graph.background;
    graph.background = '#ffffff';
    
    const printWindow = window.open('', '_blank');
    const img = graph.getImage(null, '#ffffff', 1, graph.getGraphBounds(), true);
    
    printWindow.document.write(`
        <html>
            <head><title>Imprimir Diagrama</title></head>
            <body style="margin:0;text-align:center;">
                <img src="${img}" style="max-width:100%;height:auto;">
                <script>
                    window.onload = function() { window.print(); }
                <\/script>
            </body>
        </html>
    `);
    
    graph.background = originalBackground;
    printWindow.document.close();
}

// Agregar botones de exportación e impresión al toolbar
function agregarBotonesExportacion() {
    const toolbar = document.querySelector('.toolbar');
    
    const exportGroup = document.createElement('div');
    exportGroup.className = 'toolbar-group';
    exportGroup.innerHTML = `
        <button class="toolbar-btn" title="Exportar como imagen" id="btnExportar">
            <i class="bi bi-download"></i>
        </button>
        <button class="toolbar-btn" title="Imprimir" id="btnImprimir">
            <i class="bi bi-printer"></i>
        </button>
    `;
    
    toolbar.appendChild(exportGroup);
    
    document.getElementById('btnExportar').addEventListener('click', exportarComoImagen);
    document.getElementById('btnImprimir').addEventListener('click', imprimirDiagrama);
}
document.getElementById("btnInsertarEquipo").addEventListener("click", function() {
    document.getElementById("modalEquipos").style.display = "block";
});

// cerrar modal
function cerrarModalEquipos() {
    document.getElementById("modalEquipos").style.display = "none";
}

// buscar en la base de datos con AJAX
document.getElementById("buscarEquipo").addEventListener("keyup", function() {
    const query = this.value;

    fetch(`/inventario_ips/CONTROLADOR/DispositivoController.php?action=buscar&nombre=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
            const lista = document.getElementById("listaEquipos");
            lista.innerHTML = "";
            data.forEach(eq => {
                const item = document.createElement("div");
                item.textContent = eq.nombre + " (ID: " + eq.id + ")";
                item.style.cursor = "pointer";
                item.onclick = () => insertarEquipoEnDiagrama(eq);
                lista.appendChild(item);
            });
        })
        .catch(err => console.error(err));
});

// insertar nodo en el diagrama
function insertarEquipoEnDiagrama(eq) {
    const parent = graph.getDefaultParent();
    graph.getModel().beginUpdate();
    try {
        const v1 = graph.insertVertex(
            parent,
            null,
            eq.nombre, // etiqueta visible
            100, 100, 120, 40,
            "shape=rectangle;fillColor=#d0e9ff;strokeColor=#339;"
        );
        // guardamos ID interno como propiedad
        v1.customId = eq.id;
    } finally {
        graph.getModel().endUpdate();
    }
    cerrarModalEquipos();
}

// Llamar esta función después de inicializar el gráfico
agregarBotonesExportacion();
       
</script>
</body>
</html>
