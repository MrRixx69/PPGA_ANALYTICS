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
$titulo = trim($_POST['titulo'] ?? '');
$ano = (int) ($_POST['ano'] ?? 0);
$tipoProducaoId = (int) ($_POST['tipo_producao_id'] ?? 0);
$instituicaoId = (int) ($_POST['instituicao_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$internacional = isset($_POST['internacional']) ? 1 : 0;
$coautoriaInternacional = isset($_POST['coautoria_internacional']) ? 1 : 0;

$docentes = idsPositivosUnicos((array) ($_POST['docentes'] ?? []));
$linhas = idsPositivosUnicos((array) ($_POST['linhas'] ?? []));
$projetos = idsPositivosUnicos((array) ($_POST['projetos'] ?? []));

if ($id <= 0) {
    die('Produção inválida.');
}

if ($titulo === '') {
    die('O título é obrigatório.');
}

if ($ano <= 0) {
    die('O ano é obrigatório.');
}

if ($tipoProducaoId <= 0) {
    die('Selecione um tipo de produção.');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE producoes
        SET
            titulo = :titulo,
            ano = :ano,
            tipo_producao_id = :tipo_producao_id,
            instituicao_id = :instituicao_id,
            internacional = :internacional,
            coautoria_internacional = :coautoria_internacional,
            status = :status
        WHERE id = :id
    ");

    $stmt->execute([
        ':titulo' => $titulo,
        ':ano' => $ano,
        ':tipo_producao_id' => $tipoProducaoId,
        ':instituicao_id' => $instituicaoId > 0 ? $instituicaoId : null,
        ':internacional' => $internacional,
        ':coautoria_internacional' => $coautoriaInternacional,
        ':status' => $status !== '' ? $status : null,
        ':id' => $id
    ]);

    $stmt = $pdo->prepare("
        DELETE FROM producoes_docentes
        WHERE producao_id = :producao_id
    ");
    $stmt->execute([':producao_id' => $id]);

    if ($docentes) {
        $stmtDocente = $pdo->prepare("
            INSERT INTO producoes_docentes (
                producao_id,
                docente_id,
                ordem_autoria
            )
            VALUES (
                :producao_id,
                :docente_id,
                NULL
            )
        ");

        foreach ($docentes as $docenteId) {
            $stmtDocente->execute([
                ':producao_id' => $id,
                ':docente_id' => $docenteId
            ]);
        }
    }

    $stmt = $pdo->prepare("
        DELETE FROM producoes_linhas
        WHERE producao_id = :producao_id
    ");
    $stmt->execute([':producao_id' => $id]);

    if ($linhas) {
        $stmtLinha = $pdo->prepare("
            INSERT INTO producoes_linhas (
                producao_id,
                linha_id
            )
            VALUES (
                :producao_id,
                :linha_id
            )
        ");

        foreach ($linhas as $linhaId) {
            $stmtLinha->execute([
                ':producao_id' => $id,
                ':linha_id' => $linhaId
            ]);
        }
    }

    $stmt = $pdo->prepare("
        DELETE FROM producoes_projetos
        WHERE producao_id = :producao_id
    ");
    $stmt->execute([':producao_id' => $id]);

    if ($projetos) {
        $stmtProjeto = $pdo->prepare("
            INSERT INTO producoes_projetos (
                producao_id,
                projeto_id
            )
            VALUES (
                :producao_id,
                :projeto_id
            )
        ");

        foreach ($projetos as $projetoId) {
            $stmtProjeto->execute([
                ':producao_id' => $id,
                ':projeto_id' => $projetoId
            ]);
        }
    }

    $pdo->commit();

    header('Location: visualizar.php?id=' . $id);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die('Erro ao atualizar produção: ' . $e->getMessage());
}