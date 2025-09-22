<?php
class PlanoController
{
    private PDO $conn;

    public function __construct(PDO $conexion)
    {
        $this->conn = $conexion;
    }

    public function insertar(string $nombre, ?string $descripcion = null, ?string $imagen_url = null, ?int $idsede = null, int $ancho = 1000, int $alto = 800): int
    {
        $sql = 'SELECT crear_plano(:p_nombre, :p_descripcion, :p_imagen_url, :p_idsede, :p_ancho, :p_alto) AS idplano';
        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(':p_nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':p_descripcion', $descripcion, $descripcion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':p_imagen_url', $imagen_url, $imagen_url === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':p_idsede', $idsede, $idsede === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':p_ancho', $ancho, PDO::PARAM_INT);
        $stmt->bindParam(':p_alto', $alto, PDO::PARAM_INT);

        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function actualizar(int $idplano, ?string $nombre = null, ?string $descripcion = null, ?string $imagen_url = null, ?int $idsede = null, int $ancho = 1000, int $alto = 800): void
    {
        $sql = 'SELECT actualizar_plano(:p_id, :p_nombre, :p_descripcion, :p_imagen_url, :p_idsede, :p_ancho, :p_alto)';
        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(':p_id', $idplano, PDO::PARAM_INT);
        $stmt->bindValue(':p_nombre', $nombre, $nombre === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':p_descripcion', $descripcion, $descripcion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':p_imagen_url', $imagen_url, $imagen_url === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':p_idsede', $idsede, $idsede === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':p_ancho', $ancho, PDO::PARAM_INT);
        $stmt->bindParam(':p_alto', $alto, PDO::PARAM_INT);

        $stmt->execute();
    }

    public function eliminar(int $idplano): void
    {
        $sql = 'SELECT eliminar_plano(:p_id)';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':p_id', $idplano, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function listar(): array
    {
        $sql = "SELECT * FROM plano";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $idplano): ?array
    {
        $sql = "SELECT * FROM obtener_plano(:p_id)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':p_id', $idplano, PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ?: null;
    }
}
