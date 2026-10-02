<?php

require_once '../../config/conexao.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: index.php');
    exit;

}


$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$lattes_id = trim($_POST['lattes_id'] ?? '');
$lattes_url = trim($_POST['lattes_url'] ?? '');


$areas = $_POST['areas'] ?? [];
$linhas = $_POST['linhas'] ?? [];


$areas = array_map('intval', $areas);
$linhas = array_map('intval', $linhas);


if ($nome === '') {

    die("O nome do docente é obrigatório.");

}


try {

    $pdo->beginTransaction();

    $sql = "
        INSERT INTO docentes (
            nome,
            email,
            telefone,
            lattes_id,
            lattes_url
        )
        VALUES (
            :nome,
            :email,
            :telefone,
            :lattes_id,
            :lattes_url
        )
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
                : null

    ]);


    $docente_id = $pdo->lastInsertId();

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

            ':docente_id' => $docente_id,
            ':area_id' => $area_id

        ]);

    }

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

            ':docente_id' => $docente_id,
            ':linha_id' => $linha_id

        ]);

    }


    $pdo->commit();


    header('Location: index.php');
    exit;


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    die(
        'Erro ao cadastrar docente: '
        . $e->getMessage()
    );

}

?>