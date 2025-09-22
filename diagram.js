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
        if (evt.keyCode === 46 || evt.keyCode === 8) { // Delete o Backspace
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
    fetch(`cargar_diagrama.php?idplano=${idplano}`)
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
    const nodosData = <?= json_encode($nodos) ?>;
    const conexionesData = <?= json_encode($conexiones) ?>;
    
    cargarNodos(nodosData);
    cargarConexiones(conexionesData);
}

function cargarNodos(nodos) {
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
                </script>
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

// Llamar esta función después de inicializar el gráfico
agregarBotonesExportacion();
        