<?php

require_once '../../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

function idsPositivosUnicos(array $valores): array
{
    $ids = array_map('intval', $valores);
    $ids = array_filter($ids, static fn ($id) => $id > 0);
    return array_values(array_unique($ids));
}

$id = (int) ($_POST['id'] ?? 0);
$nome = trim((string) ($_POST['nome'] ?? ''));
$issn = trim((string) ($_POST['issn'] ?? ''));
$eissn = trim((string) ($_POST['eissn'] ?? ''));
$pais = trim((string) ($_POST['pais'] ?? ''));
$indexadoresRecebidos = idsPositivosUnicos((array) ($_POST['indexadores'] ?? []));

if ($id <= 0) {
    die('Periódico inválido.');
}

if ($nome === '') {
    die('O nome do periódico é obrigatório.');
}

$stmt = $pdo->prepare("
    SELECT id
    FROM periodicos
    WHERE id = :id
");
$stmt->execute([':id' => $id]);

if (!$stmt->fetchColumn()) {
    die('Periódico não encontrado.');
}

$indexadoresValidos = [];

if ($indexadoresRecebidos) {
    $placeholders = implode(',', array_fill(0, count($indexadoresRecebidos), '?'));

    $stmt = $pdo->prepare("
        SELECT id
        FROM indexadores
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($indexadoresRecebidos);

    $indexadoresValidos = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE periodicos
        SET
            nome = :nome,
            issn = :issn,
            eissn = :eissn,
            pais = :pais
        WHERE id = :id
    ");

    $stmt->execute([
        ':nome' => $nome,
        ':issn' => $issn !== '' ? $issn : null,
        ':eissn' => $eissn !== '' ? $eissn : null,
        ':pais' => $pais !== '' ? $pais : null,
        ':id' => $id
    ]);

    $stmt = $pdo->prepare("
        DELETE FROM periodicos_indexadores
        WHERE periodico_id = :periodico_id
    ");
    $stmt->execute([':periodico_id' => $id]);

    if ($indexadoresValidos) {
        $stmtIndexador = $pdo->prepare("
            INSERT INTO periodicos_indexadores (
                periodico_id,
                indexador_id
            )
            VALUES (
                :periodico_id,
                :indexador_id
            )
        ");

        foreach ($indexadoresValidos as $indexadorId) {
            $stmtIndexador->execute([
                ':periodico_id' => $id,
                ':indexador_id' => $indexadorId
            ]);
        }
    }

    $pdo->commit();

    header('Location: editar.php?id=' . $id . '&salvo=1');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getCode() === '23000') {
        die('Não foi possível salvar: o ISSN informado já está associado a outro periódico.');
    }

    die('Erro ao atualizar periódico: ' . $e->getMessage());
}