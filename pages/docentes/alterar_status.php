<?php

require_once '../../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    die('Docente inválido.');
}

$sql = "
    UPDATE docentes
    SET ativo = IF(ativo = 1, 0, 1)
    WHERE id = :id
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':id' => $id
]);

header('Location: index.php');
exit;