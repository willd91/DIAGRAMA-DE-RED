<?php
class NodoController
{
    private PDO $conn;

    public function __construct(PDO $conexion)
    {
        $this->conn = $conexion;
    }

    public function insertar(int $idplano, string $tipo_nodo, int $x, int $y, ?int $iddispositivo = null, ?int $idpuntored = null, int $width = 80, int $height = 40, int $rotacion = 0, array $metadata = []): int
    {
        $sql = 'SELECT crear_nodo(:p_idplano, :p_iddispositivo, :p_idpuntored, :p_tipo_nodo, :p_x, :p_y, :p_width, :p_height, :p_rotacion, :p_metadata) AS idnodo';
        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(':p_idplano', $idplano, PDO::PARAM_INT);
        $stmt->bindValue(':p_iddispositivo', $iddispositivo, $iddispositivo === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':p_idpuntored', $idpuntored, $idpuntored === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':p_tipo_nodo', $tipo_nodo, PDO::PARAM_STR);
        $stmt->bindParam(':p_x', $x, PDO::PARAM_INT);
        $stmt->bindParam(':p_y', $y, PDO::PARAM_INT);
        $stmt->bindParam(':p_width', $width, PDO::PARAM_INT);
        $stmt->bindParam(':p_height', $height, PDO::PARAM_INT);
        $stmt->bindParam(':p_rotacion', $rotacion, PDO::PARAM_INT);
        $stmt->bindValue(':p_metadata', json_encode($metadata), PDO::PARAM_STR);

        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function actualizar(int $idnodo, int $x, int $y, int $width = 80, int $height = 40, int $rotacion = 0, array $metadata = []): void
    {
        $sql = 'SELECT actualizar_nodo(:p_idnodo, :p_x, :p_y, :p_width, :p_height, :p_rotacion, :p_metadata)';
        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(':p_idnodo', $idnodo, PDO::PARAM_INT);
        $stmt->bindParam(':p_x', $x, PDO::PARAM_INT);
        $stmt->bindParam(':p_y', $y, PDO::PARAM_INT);
        $stmt->bindParam(':p_width', $width, PDO::PARAM_INT);
        $stmt->bindParam(':p_height', $height, PDO::PARAM_INT);
        $stmt->bindParam(':p_rotacion', $rotacion, PDO::PARAM_INT);
        $stmt->bindValue(':p_metadata', json_encode($metadata), PDO::PARAM_STR);

        $stmt->execute();
    }

    public function eliminar(int $idnodo): void
    {
        $sql = 'SELECT eliminar_nodo(:p_idnodo)';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':p_idnodo', $idnodo, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function listar(?int $idplano = null): array
    {
        $sql = 'SELECT * FROM nodo_plano';
        if ($idplano !== null) {
            $sql .= ' WHERE idplano = :p_idplano';
        }
        $stmt = $this->conn->prepare($sql);
        if ($idplano !== null) {
            $stmt->bindParam(':p_idplano', $idplano, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $idnodo): ?array
    {
        $sql = 'SELECT * FROM nodo_plano WHERE idnodo = :p_idnodo';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':p_idnodo', $idnodo, PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ?: null;
    }
        /**
     * Guarda un nodo: inserta si no tiene id, actualiza si ya existe.
     */
    public function guardar(array $data): int
    {
        if (!empty($data['idnodo'])) {
            // 🔄 Actualizar
            $this->actualizar(
                (int)$data['idnodo'],
                (int)$data['x'],
                (int)$data['y'],
                $data['width'] ?? 80,
                $data['height'] ?? 40,
                $data['rotacion'] ?? 0,
                $data['metadata'] ?? []
            );
            return (int)$data['idnodo'];
        } else {
            // ➕ Insertar
            return $this->insertar(
                (int)$data['idplano'],
                $data['tipo_nodo'] ?? 'Nodo',
                (int)$data['x'],
                (int)$data['y'],
                $data['iddispositivo'] ?? null,
                $data['idpuntored'] ?? null,
                $data['width'] ?? 80,
                $data['height'] ?? 40,
                $data['rotacion'] ?? 0,
                $data['metadata'] ?? []
            );
        }
    }

}
