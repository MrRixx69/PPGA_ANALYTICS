<?php

require_once '../../config/conexao.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: index.php');
    exit;

}


$id = (int) ($_POST['id'] ?? 0);

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$lattes_id = trim($_POST['lattes_id'] ?? '');
$lattes_url = trim($_POST['lattes_url'] ?? '');

$areas = $_POST['areas'] ?? [];
$linhas = $_POST['linhas'] ?? [];


$areas = array_map('intval', $areas);
$linhas = array_map('intval', $linhas);


if ($id <= 0) {

    die('Docente inválido.');

}


if ($nome === '') {

    die('O nome é obrigatório.');

}


try {

    $pdo->beginTransaction();

    $sql = "
        UPDATE docentes

        SET
            nome = :nome,
            email = :email,
            telefone = :telefone,
            lattes_id = :lattes_id,
            lattes_url = :lattes_url

        WHERE id = :id
    ";

    $stmt = $pdo->prepare($sql);


    $stmt->execute([

        ':nome' => $nome,

        ':email' =>
            $email !== ''
                ? $email
                : null,

        ':telefone' =>
            $telefone !== ''
                ? $telefone
                : null,

        ':lattes_id' =>
            $lattes_id !== ''
                ? $lattes_id
                : null,

        ':lattes_url' =>
            $lattes_url !== ''
                ? $lattes_url
                : null,

        ':id' => $id

    ]);

    $sqlDeleteAreas = "
        DELETE FROM docentes_areas
        WHERE docente_id = :docente_id
    ";

    $stmtDeleteAreas =
        $pdo->prepare($sqlDeleteAreas);

    $stmtDeleteAreas->execute([
        ':docente_id' => $id
    ]);

    $sqlArea = "
        INSERT INTO docentes_areas (
            docente_id,
            area_id
        )
        VALUES (
            :docente_id,
            :area_id
        )
    ";

    $stmtArea = $pdo->prepare($sqlArea);


    foreach ($areas as $area_id) {

        $stmtArea->execute([

            ':docente_id' => $id,
            ':area_id' => $area_id

        ]);

    }

    $sqlDeleteLinhas = "
        DELETE FROM docentes_linhas
        WHERE docente_id = :docente_id
    ";

    $stmtDeleteLinhas =
        $pdo->prepare($sqlDeleteLinhas);

    $stmtDeleteLinhas->execute([
        ':docente_id' => $id
    ]);

    $sqlLinha = "
        INSERT INTO docentes_linhas (
            docente_id,
            linha_id
        )
        VALUES (
            :docente_id,
            :linha_id
        )
    ";

    $stmtLinha = $pdo->prepare($sqlLinha);


    foreach ($linhas as $linha_id) {

        $stmtLinha->execute([

            ':docente_id' => $id,
            ':linha_id' => $linha_id

        ]);

    }


    $pdo->commit();


    header(
        'Location: visualizar.php?id=' . $id
    );

    exit;


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    die(
        'Erro ao atualizar docente: '
        . $e->getMessage()
    );

}