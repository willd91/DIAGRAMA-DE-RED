<?php
class ConexionController
{
    private PDO $conn;

    public function __construct(PDO $conexion)
    {
        $this->conn = $conexion;
    }

    public function insertar(int $nodo_origen, int $nodo_destino, ?string $tipo_conexion = 'cable', ?string $etiqueta = null, string $color = '#000000'): int
    {
        $sql = 'SELECT crear_conexion(:p_origen, :p_destino, :p_tipo, :p_etiqueta, :p_color) AS idconexion';
        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(':p_origen', $nodo_origen, PDO::PARAM_INT);
        $stmt->bindParam(':p_destino', $nodo_destino, PDO::PARAM_INT);
        $stmt->bindValue(':p_tipo', $tipo_conexion, PDO::PARAM_STR);
        $stmt->bindValue(':p_etiqueta', $etiqueta, $etiqueta === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':p_color', $color, PDO::PARAM_STR);

        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function eliminar(int $idconexion): void
    {
        $sql = 'SELECT eliminar_conexion(:p_id)';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':p_id', $idconexion, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function listar(?int $idplano = null): array
    {
        $sql = 'SELECT * FROM conexion_nodos';
        if ($idplano !== null) {
            $sql .= ' WHERE nodo_origen IN (SELECT idnodo FROM nodo_plano WHERE idplano = :p_idplano)';
        }
        $stmt = $this->conn->prepare($sql);
        if ($idplano !== null) {
            $stmt->bindParam(':p_idplano', $idplano, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
     /**
     * Guarda una conexión: inserta si no tiene id, actualiza si ya existe.
     */
    public function guardar(array $data): int
    {
        if (!empty($data['idconexion'])) {
            // 🔄 Actualizar
            $this->actualizar(
                (int)$data['idconexion'],
                $data['tipo_conexion'] ?? 'cable',
                $data['etiqueta'] ?? null,
                $data['color'] ?? '#000000'
            );
            return (int)$data['idconexion'];
        } else {
            // ➕ Insertar
            return $this->insertar(
                (int)$data['nodo_origen'],
                (int)$data['nodo_destino'],
                $data['tipo_conexion'] ?? 'cable',
                $data['etiqueta'] ?? null,
                $data['color'] ?? '#000000'
            );
        }
    }
    public function actualizar(
    int $idconexion,
    ?string $tipo_conexion = 'cable',
    ?string $etiqueta = null,
    string $color = '#000000'
): void {
    $sql = 'SELECT actualizar_conexion(:p_id, :p_tipo, :p_etiqueta, :p_color)';
    $stmt = $this->conn->prepare($sql);

    $stmt->bindParam(':p_id', $idconexion, PDO::PARAM_INT);
    $stmt->bindValue(':p_tipo', $tipo_conexion, PDO::PARAM_STR);
    $stmt->bindValue(':p_etiqueta', $etiqueta, $etiqueta === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindParam(':p_color', $color, PDO::PARAM_STR);

    $stmt->execute();
}

}
