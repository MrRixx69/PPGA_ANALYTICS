<?php

require_once '../../config/conexao.php';

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Produção inválida.');
}

$stmt = $pdo->prepare("
    SELECT
        p.*,
        tp.nome AS tipo,
        i.nome AS instituicao_nome,
        i.sigla AS instituicao_sigla,
        pe.nome AS periodico_nome,
        pe.issn AS periodico_issn,
        pe.eissn AS periodico_eissn,
        pe.pais AS periodico_pais
    FROM producoes p
    INNER JOIN tipos_producao tp
        ON tp.id = p.tipo_producao_id
    LEFT JOIN instituicoes i
        ON i.id = p.instituicao_id
    LEFT JOIN periodicos pe
        ON pe.id = p.periodico_id
    WHERE p.id = :id
");
$stmt->execute([':id' => $id]);
$producao = $stmt->fetch();

if (!$producao) {
    die('Produção não encontrada.');
}

$stmt = $pdo->prepare("
    SELECT
        d.id,
        d.nome,
        pd.ordem_autoria
    FROM producoes_docentes pd
    INNER JOIN docentes d
        ON d.id = pd.docente_id
    WHERE pd.producao_id = :producao_id
    ORDER BY pd.ordem_autoria, d.nome
");
$stmt->execute([':producao_id' => $id]);
$docentes = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT
        d.id,
        d.nome,
        d.egresso,
        pd.ordem_autoria
    FROM producoes_discentes pd
    INNER JOIN discentes d
        ON d.id = pd.discente_id
    WHERE pd.producao_id = :producao_id
    ORDER BY pd.ordem_autoria, d.nome
");
$stmt->execute([':producao_id' => $id]);
$participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT l.id, l.nome
    FROM producoes_linhas pl
    INNER JOIN linhas_pesquisa l
        ON l.id = pl.linha_id
    WHERE pl.producao_id = :producao_id
    ORDER BY l.nome
");
$stmt->execute([':producao_id' => $id]);
$linhas = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.nome,
        p.situacao,
        p.data_inicio,
        p.data_fim
    FROM producoes_projetos pp
    INNER JOIN projetos p
        ON p.id = pp.projeto_id
    WHERE pp.producao_id = :producao_id
    ORDER BY p.data_inicio DESC, p.nome
");
$stmt->execute([':producao_id' => $id]);
$projetos = $stmt->fetchAll();

$indexadores = [];

if (!empty($producao['periodico_id'])) {
    $stmt = $pdo->prepare("
        SELECT i.id, i.nome
        FROM periodicos_indexadores pi
        INNER JOIN indexadores i
            ON i.id = pi.indexador_id
        WHERE pi.periodico_id = :periodico_id
        ORDER BY i.nome
    ");
    $stmt->execute([
        ':periodico_id' => (int) $producao['periodico_id']
    ]);
    $indexadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$issnExibicao = trim((string) ($producao['issn'] ?? ''));

if ($issnExibicao === '') {
    $issnExibicao = trim((string) ($producao['periodico_issn'] ?? ''));
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produção - PPGA Analytics</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/metadados_producao.css">
    <link rel="stylesheet" href="../../assets/css/periodicos.css">
</head>
<body>

<div class="container">

    <div class="page-header">
        <div>
            <h1><?= htmlspecialchars($producao['titulo']) ?></h1>
            <p>Informações da produção científica</p>
        </div>

        <div>
            <a href="index.php" class="btn">Voltar</a>
            <a href="editar.php?id=<?= (int) $producao['id'] ?>" class="btn btn-primary">Editar</a>
        </div>
    </div>

    <div class="form-card form-card-wide">

        <div class="metadata-section">
            <h2>Dados da produção</h2>

            <p><strong>Ano:</strong> <?= htmlspecialchars($producao['ano']) ?></p>
            <br>

            <p><strong>Tipo:</strong> <?= htmlspecialchars($producao['tipo']) ?></p>
            <br>

            <p>
                <strong>Instituição:</strong>
                <?= !empty($producao['instituicao_nome'])
                    ? htmlspecialchars($producao['instituicao_sigla'] ?: $producao['instituicao_nome'])
                    : '-' ?>
            </p>
            <br>

            <p>
                <strong>DOI:</strong>
                <?php if (!empty($producao['doi'])): ?>
                    <a
                        href="https://doi.org/<?= htmlspecialchars(ltrim((string) $producao['doi'], '/')) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <?= htmlspecialchars($producao['doi']) ?>
                    </a>
                <?php else: ?>
                    -
                <?php endif; ?>
            </p>
            <br>

            <p><strong>ISSN:</strong> <?= $issnExibicao !== '' ? htmlspecialchars($issnExibicao) : '-' ?></p>
            <br>

            <p>
                <strong>Periódico:</strong>
                <?= !empty($producao['periodico_nome'])
                    ? htmlspecialchars($producao['periodico_nome'])
                    : '-' ?>
            </p>

            <?php if (!empty($producao['periodico_eissn'])): ?>
                <br>
                <p><strong>eISSN:</strong> <?= htmlspecialchars($producao['periodico_eissn']) ?></p>
            <?php endif; ?>

            <?php if (!empty($producao['periodico_pais'])): ?>
                <br>
                <p><strong>País do periódico:</strong> <?= htmlspecialchars($producao['periodico_pais']) ?></p>
            <?php endif; ?>
            <br>

            <p><strong>Internacional:</strong> <?= $producao['internacional'] ? 'Sim' : 'Não' ?></p>
            <br>

            <p>
                <strong>Coautoria internacional:</strong>
                <?= $producao['coautoria_internacional'] ? 'Sim' : 'Não' ?>
            </p>
            <br>

            <p><strong>Status:</strong> <?= htmlspecialchars($producao['status'] ?? '-') ?></p>
            <br>

            <p><strong>Origem:</strong> <?= htmlspecialchars($producao['origem']) ?></p>
        </div>

        <div class="metadata-section">
            <h2>Indexação do periódico</h2>

            <?php if ($indexadores): ?>
                <div class="detail-list">
                    <?php foreach ($indexadores as $indexador): ?>
                        <span class="detail-tag"><?= htmlspecialchars($indexador['nome']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!empty($producao['periodico_id'])): ?>
                <p class="helper-text">Nenhuma indexação informada para este periódico.</p>
                <a
                    href="../periodicos/editar.php?id=<?= (int) $producao['periodico_id'] ?>"
                    class="btn btn-small"
                >
                    Gerenciar indexação
                </a>
            <?php else: ?>
                <p class="helper-text">Esta produção não possui periódico vinculado.</p>
            <?php endif; ?>
        </div>

        <div class="metadata-section">
            <h2>Docentes / autores</h2>

            <?php if ($docentes): ?>
                <ul>
                    <?php foreach ($docentes as $docente): ?>
                        <li><?= htmlspecialchars($docente['nome']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>-</p>
            <?php endif; ?>
        </div>

        <div class="metadata-section">
            <h2>Discentes e egressos</h2>

            <?php if ($participantes): ?>
                <div class="detail-list">
                    <?php foreach ($participantes as $participante): ?>
                        <span class="detail-tag">
                            <?= htmlspecialchars($participante['nome']) ?>
                            · <?= (int) $participante['egresso'] === 1 ? 'Egresso' : 'Discente' ?>
                        </span>
                    <?php endforeach; ?>
                </div>

                <br>

                <a
                    href="../avaliacoes/discentes.php?producao_id=<?= (int) $producao['id'] ?>"
                    class="btn btn-small"
                >
                    Gerenciar participantes
                </a>
            <?php else: ?>
                <p class="helper-text">Nenhum discente ou egresso vinculado.</p>

                <a
                    href="../avaliacoes/discentes.php?producao_id=<?= (int) $producao['id'] ?>"
                    class="btn btn-small"
                >
                    Vincular participantes
                </a>
            <?php endif; ?>
        </div>

        <div class="metadata-section">
            <h2>Linha(s) de pesquisa</h2>

            <?php if ($linhas): ?>
                <div class="detail-list">
                    <?php foreach ($linhas as $linha): ?>
                        <span class="detail-tag"><?= htmlspecialchars($linha['nome']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="helper-text">Nenhuma linha de pesquisa vinculada.</p>
            <?php endif; ?>
        </div>

        <div class="metadata-section">
            <h2>Projeto(s) relacionado(s)</h2>

            <?php if ($projetos): ?>
                <ul class="project-list">
                    <?php foreach ($projetos as $projeto): ?>
                        <li>
                            <strong><?= htmlspecialchars($projeto['nome']) ?></strong>
                            <div class="project-meta">
                                <?= htmlspecialchars($projeto['situacao']) ?>
                                <?php if (!empty($projeto['data_inicio'])): ?>
                                    · início <?= date('d/m/Y', strtotime($projeto['data_inicio'])) ?>
                                <?php endif; ?>
                                <?php if (!empty($projeto['data_fim'])): ?>
                                    · fim <?= date('d/m/Y', strtotime($projeto['data_fim'])) ?>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="helper-text">Nenhum projeto relacionado.</p>
            <?php endif; ?>
        </div>

    </div>

</div>

</body>
</html>