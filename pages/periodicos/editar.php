<?php

require_once '../../config/conexao.php';
require_once '../../includes/capes_area27.php';


function e(
    $valor
): string {

    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );

}


$id =
    (int) (
        $_GET['id']
        ?? 0
    );


if ($id <= 0) {

    die(
        'Periódico inválido.'
    );

}


$stmt =
    $pdo->prepare(
        "
            SELECT
                id,
                nome,
                issn,
                eissn,
                pais

            FROM periodicos

            WHERE id = :id
        "
    );


$stmt->execute([

    ':id' =>
        $id

]);


$periodico =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$periodico) {

    die(
        'Periódico não encontrado.'
    );

}


$indexadores =
    $pdo->query(
        "
            SELECT
                id,
                nome

            FROM indexadores

            ORDER BY nome
        "
    )->fetchAll(
        PDO::FETCH_ASSOC
    );


$stmt =
    $pdo->prepare(
        "
            SELECT
                indexador_id

            FROM periodicos_indexadores

            WHERE periodico_id = :periodico_id
        "
    );


$stmt->execute([

    ':periodico_id' =>
        $id

]);


$indexadoresSelecionados =
    array_map(
        'intval',
        $stmt->fetchAll(
            PDO::FETCH_COLUMN
        )
    );


$stmt =
    $pdo->prepare(
        "
            SELECT COUNT(*)
            FROM producoes
            WHERE periodico_id = :periodico_id
        "
    );


$stmt->execute([

    ':periodico_id' =>
        $id

]);


$quantidadeProducoes =
    (int) $stmt->fetchColumn();


// ========================================================
// INDICADORES CAPES ÁREA 27
// ========================================================

$indicadoresAtuais =
    buscarIndicadoresAtuaisPeriodicoArea27(
        $pdo,
        $id
    );

$calculoArea27 =
    calcularClassificacaoPeriodicoArea27(
        $indicadoresAtuais
    );

$historicoIndicadores = [];

if (tabelaExisteCapes(
    $pdo,
    'periodicos_indicadores'
)) {

    $stmtHistorico =
        $pdo->prepare("
            SELECT
                base,
                ano,
                edicao,
                categoria,
                valor,
                percentual,
                classe_candidata,
                status,
                coletado_em,
                revisado_por,
                vigente
            FROM periodicos_indicadores
            WHERE periodico_id = :periodico_id
            ORDER BY coletado_em DESC, id DESC
            LIMIT 30
        ");

    $stmtHistorico->execute([
        ':periodico_id' => $id
    ]);

    $historicoIndicadores =
        $stmtHistorico->fetchAll(
            PDO::FETCH_ASSOC
        );
}


function indicadorAtual(
    array $indicadores,
    string $base
): array {

    return $indicadores[$base] ?? [];
}

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Gerenciar Periódico - PPGA Analytics
    </title>

    <link
        rel="stylesheet"
        href="../../assets/css/periodicos.css"
    >

</head>

<body>

<main class="periodicos-page">

    <header class="periodicos-header">

        <div>

            <h1>
                Gerenciar periódico
            </h1>

            <p>
                <?= e(
                    $periodico['nome']
                ) ?>
            </p>

        </div>

        <div class="header-actions">

            <a
                href="index.php"
                class="botao botao-secundario"
            >
                Voltar aos periódicos
            </a>

            <a
                href="../dashboard/index.php"
                class="botao"
            >
                Dashboard
            </a>

        </div>

    </header>


    <section class="edicao-layout">

        <form
            action="atualizar.php"
            method="POST"
            class="painel form-card"
        >

            <input
                type="hidden"
                name="id"
                value="<?= (int) $periodico['id'] ?>"
            >

            <div class="form-intro">

                <h2>
                    Dados do periódico
                </h2>

                <p>
                    Atualize os metadados institucionais
                    e as bases de indexação deste periódico.
                </p>

            </div>


            <div class="form-grid">

                <div class="form-group form-group-full">

                    <label
                        for="nome"
                        class="titulo-campo"
                    >
                        Nome do periódico
                        <span class="campo-obrigatorio">
                            *
                        </span>
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        value="<?= e(
                            $periodico['nome']
                        ) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label
                        for="issn"
                        class="titulo-campo"
                    >
                        ISSN
                    </label>

                    <input
                        type="text"
                        id="issn"
                        name="issn"
                        maxlength="20"
                        value="<?= e(
                            $periodico['issn']
                            ?? ''
                        ) ?>"
                        placeholder="0000-0000"
                    >

                </div>

                <div class="form-group">

                    <label
                        for="eissn"
                        class="titulo-campo"
                    >
                        eISSN
                    </label>

                    <input
                        type="text"
                        id="eissn"
                        name="eissn"
                        maxlength="20"
                        value="<?= e(
                            $periodico['eissn']
                            ?? ''
                        ) ?>"
                        placeholder="0000-0000"
                    >

                </div>

                <div class="form-group">

                    <label
                        for="pais"
                        class="titulo-campo"
                    >
                        País
                    </label>

                    <input
                        type="text"
                        id="pais"
                        name="pais"
                        maxlength="100"
                        value="<?= e(
                            $periodico['pais']
                            ?? ''
                        ) ?>"
                    >

                </div>

            </div>


            <div class="indexacao-bloco">

                <h2>
                    Indexação
                </h2>

                <p>
                    Marque as bases em que este periódico está indexado.
                    Essa informação será herdada pelas produções vinculadas.
                </p>

                <?php if ($indexadores): ?>

                    <div class="indexador-opcoes">

                        <?php foreach (
                            $indexadores
                            as $indexador
                        ): ?>

                            <label class="indexador-opcao">

                                <input
                                    type="checkbox"
                                    name="indexadores[]"
                                    value="<?= (int) $indexador['id'] ?>"
                                    <?= in_array(
                                        (int) $indexador['id'],
                                        $indexadoresSelecionados,
                                        true
                                    )
                                        ? 'checked'
                                        : ''
                                    ?>
                                >

                                <span>

                                    <?= e(
                                        $indexador['nome']
                                    ) ?>

                                </span>

                            </label>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <p>
                        Nenhum indexador cadastrado no banco.
                    </p>

                <?php endif; ?>

            </div>


            <div class="nota">

                <strong>
                    Importante:
                </strong>

                a indexação é um metadado institucional.
                Marcar uma base aqui não consulta Scopus,
                Web of Science ou SciELO automaticamente.

            </div>


            <div class="form-actions">

                <a
                    href="index.php"
                    class="botao botao-secundario"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="botao"
                >
                    Salvar periódico
                </button>

            </div>

        </form>


        <aside class="painel lateral-card">

            <h2>
                Uso no sistema
            </h2>

            <p>
                As produções associadas a este periódico
                recebem automaticamente seus dados de indexação.
            </p>

            <strong class="lateral-destaque">
                <?= $quantidadeProducoes ?>
            </strong>

            <span class="lateral-legenda">
                produção(ões) vinculada(s)
            </span>


            <div class="lateral-lista">

                <div class="lateral-item">

                    <span class="lateral-item-numero">
                        1
                    </span>

                    <div>

                        <strong>
                            Metadados
                        </strong>

                        <span>
                            Nome, ISSN, eISSN e país
                            ficam centralizados no periódico.
                        </span>

                    </div>

                </div>

                <div class="lateral-item">

                    <span class="lateral-item-numero">
                        2
                    </span>

                    <div>

                        <strong>
                            Indexação
                        </strong>

                        <span>
                            Scopus, Web of Science e SciELO
                            podem ser marcados institucionalmente.
                        </span>

                    </div>

                </div>

                <div class="lateral-item">

                    <span class="lateral-item-numero">
                        3
                    </span>

                    <div>

                        <strong>
                            Filtros
                        </strong>

                        <span>
                            A dashboard pode filtrar produções
                            pelo periódico ou pela base de indexação.
                        </span>

                    </div>

                </div>

            </div>

        </aside>

    </section>


    <section class="painel indicadores-capes-painel">

        <div class="painel-cabecalho">

            <div>
                <h2>Indicadores CAPES · Área 27</h2>
                <p>
                    Cadastre as evidências da revista em ABDC, ABS, JCR,
                    SJR, SPELL e SciELO Brasil. O sistema calcula uma
                    classificação preliminar; a confirmação oficial acontece
                    no módulo de Avaliações CAPES.
                </p>
            </div>

            <div class="resultado-area27">

                <span>Classe preliminar</span>

                <strong>
                    <?= e(
                        $calculoArea27['classe_final']
                        ?? 'Pendente'
                    ) ?>
                </strong>

                <small>
                    <?php if (
                        $calculoArea27['pontos_padrao']
                        !== null
                    ): ?>
                        <?= number_format(
                            (float) $calculoArea27['pontos_padrao'],
                            0,
                            ',',
                            '.'
                        ) ?> pts
                    <?php else: ?>
                        sem pontuação confirmável
                    <?php endif; ?>
                </small>

            </div>

        </div>


        <?php if (
            !tabelaExisteCapes(
                $pdo,
                'periodicos_indicadores'
            )
        ): ?>

            <div class="nota nota-alerta">
                Execute primeiro a migração
                <strong>database/migrations/05_indicadores_periodicos_area27.sql</strong>.
            </div>

        <?php else: ?>

            <?php

            $abdc = indicadorAtual(
                $indicadoresAtuais,
                'ABDC'
            );

            $abs = indicadorAtual(
                $indicadoresAtuais,
                'ABS'
            );

            $jcr = indicadorAtual(
                $indicadoresAtuais,
                'JCR'
            );

            $sjr = indicadorAtual(
                $indicadoresAtuais,
                'SJR'
            );

            $spell = indicadorAtual(
                $indicadoresAtuais,
                'SPELL'
            );

            $scielo = indicadorAtual(
                $indicadoresAtuais,
                'SCIELO'
            );

            ?>

            <form
                action="salvar_indicadores.php"
                method="POST"
                class="indicadores-form"
            >

                <input
                    type="hidden"
                    name="periodico_id"
                    value="<?= $id ?>"
                >

                <div class="indicadores-grid">

                    <article class="indicador-card">
                        <div class="indicador-card-topo">
                            <div>
                                <span class="indicador-base">ABDC</span>
                                <small>A*/A→MB · B→B · C→R</small>
                            </div>
                            <span class="indicador-status-resumo">
                                <?= e($abdc['classe_candidata'] ?? '—') ?>
                            </span>
                        </div>

                        <div class="indicador-campos">
                            <div class="form-group">
                                <label class="titulo-campo">Nota</label>
                                <select name="indicadores[ABDC][valor]">
                                    <option value="">Não informado</option>
                                    <?php foreach (['A*', 'A', 'B', 'C'] as $valor): ?>
                                        <option value="<?= e($valor) ?>" <?= ($abdc['valor'] ?? '') === $valor ? 'selected' : '' ?>>
                                            <?= e($valor) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="titulo-campo">Edição</label>
                                <input type="text" name="indicadores[ABDC][edicao]" value="<?= e($abdc['edicao'] ?? '') ?>" placeholder="Ex.: 2022">
                            </div>
                        </div>

                        <?php $baseFormulario = 'ABDC'; $indicadorComum = $abdc; ?>
                        <?php include __DIR__ . '/partials/campos_indicador_comum.php'; ?>
                    </article>


                    <article class="indicador-card">
                        <div class="indicador-card-topo">
                            <div>
                                <span class="indicador-base">ABS</span>
                                <small>2 ou mais→MB · 1→B</small>
                            </div>
                            <span class="indicador-status-resumo">
                                <?= e($abs['classe_candidata'] ?? '—') ?>
                            </span>
                        </div>

                        <div class="indicador-campos">
                            <div class="form-group">
                                <label class="titulo-campo">Nota</label>
                                <select name="indicadores[ABS][valor]">
                                    <option value="">Não informado</option>
                                    <?php foreach (['4*', '4', '3', '2', '1'] as $valor): ?>
                                        <option value="<?= e($valor) ?>" <?= ($abs['valor'] ?? '') === $valor ? 'selected' : '' ?>>
                                            <?= e($valor) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="titulo-campo">Edição</label>
                                <input type="text" name="indicadores[ABS][edicao]" value="<?= e($abs['edicao'] ?? '') ?>" placeholder="Ex.: AJG 2024">
                            </div>
                        </div>

                        <?php $baseFormulario = 'ABS'; $indicadorComum = $abs; ?>
                        <?php include __DIR__ . '/partials/campos_indicador_comum.php'; ?>
                    </article>


                    <article class="indicador-card">
                        <div class="indicador-card-topo">
                            <div>
                                <span class="indicador-base">JCR</span>
                                <small>Q1→MB · Q2→B · Q3→R · Q4→F</small>
                            </div>
                            <span class="indicador-status-resumo">
                                <?= e($jcr['classe_candidata'] ?? '—') ?>
                            </span>
                        </div>

                        <div class="indicador-campos indicador-campos-3">
                            <div class="form-group">
                                <label class="titulo-campo">Quartil</label>
                                <select name="indicadores[JCR][valor]">
                                    <option value="">Não informado</option>
                                    <?php foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $valor): ?>
                                        <option value="<?= $valor ?>" <?= ($jcr['valor'] ?? '') === $valor ? 'selected' : '' ?>><?= $valor ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="titulo-campo">Ano</label>
                                <input type="number" min="2000" max="2100" name="indicadores[JCR][ano]" value="<?= e($jcr['ano'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="titulo-campo">Categoria</label>
                                <input type="text" name="indicadores[JCR][categoria]" value="<?= e($jcr['categoria'] ?? '') ?>">
                            </div>
                        </div>

                        <?php $baseFormulario = 'JCR'; $indicadorComum = $jcr; ?>
                        <?php include __DIR__ . '/partials/campos_indicador_comum.php'; ?>
                    </article>


                    <article class="indicador-card">
                        <div class="indicador-card-topo">
                            <div>
                                <span class="indicador-base">SJR</span>
                                <small>Q1→MB · Q2→B · Q3→R · Q4→F</small>
                            </div>
                            <span class="indicador-status-resumo">
                                <?= e($sjr['classe_candidata'] ?? '—') ?>
                            </span>
                        </div>

                        <div class="indicador-campos indicador-campos-3">
                            <div class="form-group">
                                <label class="titulo-campo">Quartil</label>
                                <select name="indicadores[SJR][valor]">
                                    <option value="">Não informado</option>
                                    <?php foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $valor): ?>
                                        <option value="<?= $valor ?>" <?= ($sjr['valor'] ?? '') === $valor ? 'selected' : '' ?>><?= $valor ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="titulo-campo">Ano</label>
                                <input type="number" min="2000" max="2100" name="indicadores[SJR][ano]" value="<?= e($sjr['ano'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="titulo-campo">Categoria</label>
                                <input type="text" name="indicadores[SJR][categoria]" value="<?= e($sjr['categoria'] ?? '') ?>">
                            </div>
                        </div>

                        <?php $baseFormulario = 'SJR'; $indicadorComum = $sjr; ?>
                        <?php include __DIR__ . '/partials/campos_indicador_comum.php'; ?>
                    </article>


                    <article class="indicador-card">
                        <div class="indicador-card-topo">
                            <div>
                                <span class="indicador-base">SPELL</span>
                                <small>posição percentual na coleção</small>
                            </div>
                            <span class="indicador-status-resumo">
                                <?= e($spell['classe_candidata'] ?? '—') ?>
                            </span>
                        </div>

                        <div class="indicador-campos">
                            <div class="form-group">
                                <label class="titulo-campo">Posição percentual</label>
                                <input type="number" min="0" max="100" step="0.01" name="indicadores[SPELL][percentual]" value="<?= e($spell['percentual'] ?? '') ?>" placeholder="Ex.: 8.50">
                            </div>
                            <div class="form-group">
                                <label class="titulo-campo">Ano</label>
                                <input type="number" min="2000" max="2100" name="indicadores[SPELL][ano]" value="<?= e($spell['ano'] ?? '') ?>">
                            </div>
                        </div>

                        <?php $baseFormulario = 'SPELL'; $indicadorComum = $spell; ?>
                        <?php include __DIR__ . '/partials/campos_indicador_comum.php'; ?>
                    </article>


                    <article class="indicador-card">
                        <div class="indicador-card-topo">
                            <div>
                                <span class="indicador-base">SciELO Brasil</span>
                                <small>pode elevar F→R ou R→B</small>
                            </div>
                            <span class="indicador-status-resumo">
                                <?= (($scielo['valor'] ?? '') === 'SIM' && ($scielo['status'] ?? '') === 'CONFIRMADO') ? 'Confirmado' : '—' ?>
                            </span>
                        </div>

                        <div class="indicador-campos">
                            <div class="form-group">
                                <label class="titulo-campo">Coleção Brasil</label>
                                <select name="indicadores[SCIELO][valor]">
                                    <option value="">Não verificado</option>
                                    <option value="SIM" <?= ($scielo['valor'] ?? '') === 'SIM' ? 'selected' : '' ?>>Sim</option>
                                    <option value="NAO" <?= ($scielo['valor'] ?? '') === 'NAO' ? 'selected' : '' ?>>Não</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="titulo-campo">Ano da verificação</label>
                                <input type="number" min="2000" max="2100" name="indicadores[SCIELO][ano]" value="<?= e($scielo['ano'] ?? '') ?>">
                            </div>
                        </div>

                        <?php $baseFormulario = 'SCIELO'; $indicadorComum = $scielo; ?>
                        <?php include __DIR__ . '/partials/campos_indicador_comum.php'; ?>
                    </article>

                </div>


                <div class="form-actions indicadores-actions">
                    <a href="index.php" class="botao botao-secundario">
                        Voltar aos periódicos
                    </a>
                    <button type="submit" class="botao">
                        Salvar indicadores CAPES
                    </button>
                </div>

            </form>

        <?php endif; ?>

    </section>


    <?php if ($historicoIndicadores): ?>

        <section class="painel">

            <div class="painel-cabecalho">
                <div>
                    <h2>Histórico de evidências</h2>
                    <p>
                        As alterações não sobrescrevem a evidência anterior.
                        A linha marcada como atual é a usada no cálculo preliminar.
                    </p>
                </div>
            </div>

            <div class="tabela-container tabela-historico-indicadores">
                <table>
                    <thead>
                        <tr>
                            <th>Base</th>
                            <th>Valor</th>
                            <th>Ano / edição</th>
                            <th>Classe candidata</th>
                            <th>Status</th>
                            <th>Revisor</th>
                            <th>Coletado em</th>
                            <th>Versão</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historicoIndicadores as $linha): ?>
                            <tr>
                                <td><strong><?= e($linha['base']) ?></strong></td>
                                <td>
                                    <?php if ($linha['base'] === 'SPELL' && $linha['percentual'] !== null): ?>
                                        Top <?= e($linha['percentual']) ?>%
                                    <?php else: ?>
                                        <?= e($linha['valor'] ?? '—') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e($linha['ano'] ?? '') ?>
                                    <?= !empty($linha['edicao']) ? ' · ' . e($linha['edicao']) : '' ?>
                                </td>
                                <td><?= e($linha['classe_candidata'] ?? '—') ?></td>
                                <td><?= e($linha['status']) ?></td>
                                <td><?= e($linha['revisado_por'] ?? '—') ?></td>
                                <td><?= e($linha['coletado_em']) ?></td>
                                <td>
                                    <?php if ((int) $linha['vigente'] === 1): ?>
                                        <span class="tag-atual">Atual</span>
                                    <?php else: ?>
                                        <span class="texto-secundario">Histórica</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </section>

    <?php endif; ?>

</main>

</body>

</html>