<?php

require_once '../../config/conexao.php';

$id = (int) ($_POST['id'] ?? 0);
$nome = trim((string) ($_POST['nome'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$lattesId = trim((string) ($_POST['lattes_id'] ?? ''));
$lattesUrl = trim((string) ($_POST['lattes_url'] ?? ''));
$ativo = isset($_POST['ativo']) ? 1 : 0;
$egresso = isset($_POST['egresso']) ? 1 : 0;

if ($id <= 0) {
    die('Participante inválido.');
}

if ($nome === '') {
    die('O nome é obrigatório.');
}

$email = $email !== '' ? $email : null;
$lattesId = $lattesId !== '' ? $lattesId : null;
$lattesUrl = $lattesUrl !== '' ? $lattesUrl : null;

if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('E-mail inválido.');
}

if ($lattesUrl !== null && !filter_var($lattesUrl, FILTER_VALIDATE_URL)) {
    die('URL do Lattes inválida.');
}

try {
    $stmt = $pdo->prepare("
        UPDATE discentes
        SET
            nome = :nome,
            email = :email,
            lattes_id = :lattes_id,
            lattes_url = :lattes_url,
            ativo = :ativo,
            egresso = :egresso
        WHERE id = :id
    ");

    $stmt->execute([
        ':nome' => $nome,
        ':email' => $email,
        ':lattes_id' => $lattesId,
        ':lattes_url' => $lattesUrl,
        ':ativo' => $ativo,
        ':egresso' => $egresso,
        ':id' => $id,
    ]);

    header('Location: index.php?salvo=1');
    exit;
} catch (PDOException $erro) {
    if ((string) $erro->getCode() === '23000') {
        die('Não foi possível salvar. Verifique se o ID Lattes já está cadastrado para outro participante.');
    }

    throw $erro;
}