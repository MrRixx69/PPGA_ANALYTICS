<?php

$raizProjeto = dirname(__DIR__, 2);

require_once
    $raizProjeto
    . DIRECTORY_SEPARATOR
    . 'config'
    . DIRECTORY_SEPARATOR
    . 'conexao.php';


// ========================================================
// DOCENTES ATIVOS
// ========================================================

$stmtDocentes = $pdo->query("
    SELECT
        id,
        nome
    FROM docentes
    WHERE ativo = 1
    ORDER BY nome
");

$docentes = $stmtDocentes->fetchAll(PDO::FETCH_ASSOC);

$idsDocentesDisponiveis = array_map(
    static fn(array $docente): int => (int) $docente['id'],
    $docentes
);


// ========================================================
// QUADRIÊNIO ATIVO
// ========================================================

$stmtQuadrienio = $pdo->query("
    SELECT
        id,
        ano_inicio,
        ano_fim,
        descricao
    FROM quadrienios
    WHERE ativo = 1
    ORDER BY ano_inicio DESC
    LIMIT 1
");

$quadrienioAtivo = $stmtQuadrienio->fetch(PDO::FETCH_ASSOC);


// ========================================================
// ANOS DISPONÍVEIS
// ========================================================

$stmtAnos = $pdo->query("
    SELECT DISTINCT ano
    FROM producoes
    WHERE ano IS NOT NULL
    ORDER BY ano DESC
");

$anosDisponiveis = $stmtAnos->fetchAll(PDO::FETCH_COLUMN);


// ========================================================
// FILTROS
// ========================================================

$docenteId = isset($_GET['docente'])
    ? (int) $_GET['docente']
    : 0;

if (
    $docenteId <= 0
    || !in_array($docenteId, $idsDocentesDisponiveis, true)
) {
    $docenteId = $idsDocentesDisponiveis[0] ?? 0;
}

$periodoFiltro = isset($_GET['periodo'])
    ? trim((string) $_GET['periodo'])
    : ($quadrienioAtivo ? 'quadrienio' : '');

$anoInicioPersonalizado = isset($_GET['ano_inicio'])
    ? (int) $_GET['ano_inicio']
    : 0;

$anoFimPersonalizado = isset($_GET['ano_fim'])
    ? (int) $_GET['ano_fim']
    : 0;

$anoAtual = (int) date('Y');
$anoExato = 0;
$anoInicio = 0;
$anoFim = 0;

if ($periodoFiltro !== '' && ctype_digit($periodoFiltro)) {
    $anoExato = (int) $periodoFiltro;
}
elseif ($periodoFiltro === 'ultimos_2') {
    $anoInicio = $anoAtual - 1;
    $anoFim = $anoAtual;
}
elseif ($periodoFiltro === 'ultimos_3') {
    $anoInicio = $anoAtual - 2;
    $anoFim = $anoAtual;
}
elseif ($periodoFiltro === 'ultimos_4') {
    $anoInicio = $anoAtual - 3;
    $anoFim = $anoAtual;
}
elseif ($periodoFiltro === 'quadrienio' && $quadrienioAtivo) {
    $anoInicio = (int) $quadrienioAtivo['ano_inicio'];
    $anoFim = (int) $quadrienioAtivo['ano_fim'];
}
elseif ($periodoFiltro === 'personalizado') {
    $anoInicio = $anoInicioPersonalizado;
    $anoFim = $anoFimPersonalizado;

    if (
        $anoInicio > 0
        && $anoFim > 0
        && $anoInicio > $anoFim
    ) {
        [$anoInicio, $anoFim] = [$anoFim, $anoInicio];
    }
}


// ========================================================
// FUNÇÃO DE FILTRO DE PERÍODO
// ========================================================

function montarFiltroPeriodoRelatorio(
    string $alias,
    int $anoExato,
    int $anoInicio,
    int $anoFim,
    string $sufixo
): array {
    $condicoes = [];
    $parametros = [];

    if ($anoExato > 0) {
        $chave = ':ano_' . $sufixo;
        $condicoes[] = "$alias.ano = $chave";
        $parametros[$chave] = $anoExato;
    }
    else {
        if ($anoInicio > 0) {
            $chave = ':ano_inicio_' . $sufixo;
            $condicoes[] = "$alias.ano >= $chave";
            $parametros[$chave] = $anoInicio;
        }

        if ($anoFim > 0) {
            $chave = ':ano_fim_' . $sufixo;
            $condicoes[] = "$alias.ano <= $chave";
            $parametros[$chave] = $anoFim;
        }
    }

    return [
        'sql' => $condicoes,
        'parametros' => $parametros
    ];
}


// ========================================================
// DADOS DO DOCENTE
// ========================================================

$docente = null;

if ($docenteId > 0) {
    $stmtDocente = $pdo->prepare("
        SELECT
            id,
            nome,
            email,
            lattes_id,
            lattes_url
        FROM docentes
        WHERE id = :id
        LIMIT 1
    ");

    $stmtDocente->execute([
        ':id' => $docenteId
    ]);

    $docente = $stmtDocente->fetch(PDO::FETCH_ASSOC);
}


// ========================================================
// RESUMO GERAL
// ========================================================

$resumo = [
    'total_producoes' => 0,
    'producoes_avaliadas' => 0,
    'pontuacao' => 0,
    'com_discentes' => 0,
    'internacionais' => 0,
    'coautoria_internacional' => 0,
    'MB' => 0,
    'B' => 0,
    'R' => 0,
    'F' => 0,
    'NC' => 0
];

if ($docenteId > 0) {
    $filtroResumo = montarFiltroPeriodoRelatorio(
        'p',
        $anoExato,
        $anoInicio,
        $anoFim,
        'resumo'
    );

    $whereResumo = [
        'pd.docente_id = :docente_resumo'
    ];

    $whereResumo = array_merge(
        $whereResumo,
        $filtroResumo['sql']
    );

    $parametrosResumo = [
        ':docente_resumo' => $docenteId
    ];

    $parametrosResumo = array_merge(
        $parametrosResumo,
        $filtroResumo['parametros']
    );

    if ($quadrienioAtivo) {
        $joinAvaliacaoResumo = "
            LEFT JOIN avaliacoes_producao ap
                ON ap.producao_id = p.id
                AND ap.quadrienio_id = :quadrienio_resumo
            LEFT JOIN classificacoes_capes c
                ON c.id = ap.classificacao_id
        ";

        $parametrosResumo[':quadrienio_resumo'] =
            (int) $quadrienioAtivo['id'];
    }
    else {
        $joinAvaliacaoResumo = "
            LEFT JOIN avaliacoes_producao ap
                ON 1 = 0
            LEFT JOIN classificacoes_capes c
                ON 1 = 0
        ";
    }

    $sqlWhereResumo = implode(' AND ', $whereResumo);

    $stmtResumo = $pdo->prepare("
        SELECT
            COUNT(DISTINCT p.id) AS total_producoes,

            COUNT(
                DISTINCT CASE
                    WHEN ap.id IS NOT NULL THEN p.id
                END
            ) AS producoes_avaliadas,

            COALESCE(
                SUM(ap.pontuacao_interna),
                0
            ) AS pontuacao,

            COUNT(
                DISTINCT CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM producoes_discentes pdisc
                        WHERE pdisc.producao_id = p.id
                    )
                    THEN p.id
                END
            ) AS com_discentes,

            COUNT(
                DISTINCT CASE
                    WHEN p.internacional = 1 THEN p.id
                END
            ) AS internacionais,

            COUNT(
                DISTINCT CASE
                    WHEN p.coautoria_internacional = 1 THEN p.id
                END
            ) AS coautoria_internacional,

            COUNT(
                DISTINCT CASE
                    WHEN c.codigo = 'MB' THEN p.id
                END
            ) AS MB,

            COUNT(
                DISTINCT CASE
                    WHEN c.codigo = 'B' THEN p.id
                END
            ) AS B,

            COUNT(
                DISTINCT CASE
                    WHEN c.codigo = 'R' THEN p.id
                END
            ) AS R,

            COUNT(
                DISTINCT CASE
                    WHEN c.codigo = 'F' THEN p.id
                END
            ) AS F,

            COUNT(
                DISTINCT CASE
                    WHEN c.codigo = 'NC' THEN p.id
                END
            ) AS NC

        FROM producoes p

        INNER JOIN producoes_docentes pd
            ON pd.producao_id = p.id

        $joinAvaliacaoResumo

        WHERE $sqlWhereResumo
    ");

    $stmtResumo->execute($parametrosResumo);

    $dadosResumo = $stmtResumo->fetch(PDO::FETCH_ASSOC);

    if ($dadosResumo) {
        foreach ($resumo as $chave => $valorPadrao) {
            if ($chave === 'pontuacao') {
                $resumo[$chave] = (float) ($dadosResumo[$chave] ?? 0);
            }
            else {
                $resumo[$chave] = (int) ($dadosResumo[$chave] ?? 0);
            }
        }
    }
}


// ========================================================
// PRODUÇÕES POR ANO
// ========================================================

$producoesPorAno = [];

if ($docenteId > 0) {
    $filtroAno = montarFiltroPeriodoRelatorio(
        'p',
        $anoExato,
        $anoInicio,
        $anoFim,
        'grafico_ano'
    );

    $whereAno = [
        'pd.docente_id = :docente_grafico_ano'
    ];

    $whereAno = array_merge(
        $whereAno,
        $filtroAno['sql']
    );

    $parametrosAno = [
        ':docente_grafico_ano' => $docenteId
    ];

    $parametrosAno = array_merge(
        $parametrosAno,
        $filtroAno['parametros']
    );

    $stmtAno = $pdo->prepare("
        SELECT
            p.ano,
            COUNT(DISTINCT p.id) AS quantidade
        FROM producoes p
        INNER JOIN producoes_docentes pd
            ON pd.producao_id = p.id
        WHERE " . implode(' AND ', $whereAno) . "
        GROUP BY p.ano
        ORDER BY p.ano ASC
    ");

    $stmtAno->execute($parametrosAno);

    $producoesPorAno = $stmtAno->fetchAll(PDO::FETCH_ASSOC);
}


// ========================================================
// PRODUÇÕES POR TIPO
// ========================================================

$producoesPorTipo = [];

if ($docenteId > 0) {
    $filtroTipo = montarFiltroPeriodoRelatorio(
        'p',
        $anoExato,
        $anoInicio,
        $anoFim,
        'grafico_tipo'
    );

    $whereTipo = [
        'pd.docente_id = :docente_grafico_tipo'
    ];

    $whereTipo = array_merge(
        $whereTipo,
        $filtroTipo['sql']
    );

    $parametrosTipo = [
        ':docente_grafico_tipo' => $docenteId
    ];

    $parametrosTipo = array_merge(
        $parametrosTipo,
        $filtroTipo['parametros']
    );

    $stmtTipo = $pdo->prepare("
        SELECT
            tp.nome,
            COUNT(DISTINCT p.id) AS quantidade
        FROM producoes p
        INNER JOIN producoes_docentes pd
            ON pd.producao_id = p.id
        INNER JOIN tipos_producao tp
            ON tp.id = p.tipo_producao_id
        WHERE " . implode(' AND ', $whereTipo) . "
        GROUP BY tp.id, tp.nome
        ORDER BY quantidade DESC, tp.nome ASC
    ");

    $stmtTipo->execute($parametrosTipo);

    $producoesPorTipo = $stmtTipo->fetchAll(PDO::FETCH_ASSOC);
}


// ========================================================
// LISTA DETALHADA DE PRODUÇÕES
// ========================================================

$producoes = [];

if ($docenteId > 0) {
    $filtroLista = montarFiltroPeriodoRelatorio(
        'p',
        $anoExato,
        $anoInicio,
        $anoFim,
        'lista'
    );

    $whereLista = [
        'pd.docente_id = :docente_lista'
    ];

    $whereLista = array_merge(
        $whereLista,
        $filtroLista['sql']
    );

    $parametrosLista = [
        ':docente_lista' => $docenteId
    ];

    $parametrosLista = array_merge(
        $parametrosLista,
        $filtroLista['parametros']
    );

    if ($quadrienioAtivo) {
        $joinAvaliacaoLista = "
            LEFT JOIN avaliacoes_producao ap
                ON ap.producao_id = p.id
                AND ap.quadrienio_id = :quadrienio_lista
            LEFT JOIN classificacoes_capes c
                ON c.id = ap.classificacao_id
        ";

        $parametrosLista[':quadrienio_lista'] =
            (int) $quadrienioAtivo['id'];
    }
    else {
        $joinAvaliacaoLista = "
            LEFT JOIN avaliacoes_producao ap
                ON 1 = 0
            LEFT JOIN classificacoes_capes c
                ON 1 = 0
        ";
    }

    $stmtLista = $pdo->prepare("
        SELECT
            p.id,
            p.titulo,
            p.ano,
            p.doi,
            p.issn,
            p.internacional,
            p.coautoria_internacional,
            tp.nome AS tipo,
            per.nome AS periodico,
            c.codigo AS classificacao,
            COALESCE(ap.pontuacao_interna, 0) AS pontos,
            (
                SELECT GROUP_CONCAT(
                    DISTINCT dis.nome
                    ORDER BY dis.nome
                    SEPARATOR ', '
                )
                FROM producoes_discentes pdisc
                INNER JOIN discentes dis
                    ON dis.id = pdisc.discente_id
                WHERE pdisc.producao_id = p.id
            ) AS discentes
        FROM producoes p
        INNER JOIN producoes_docentes pd
            ON pd.producao_id = p.id
        INNER JOIN tipos_producao tp
            ON tp.id = p.tipo_producao_id
        LEFT JOIN periodicos per
            ON per.id = p.periodico_id
        $joinAvaliacaoLista
        WHERE " . implode(' AND ', $whereLista) . "
        ORDER BY p.ano DESC, p.titulo ASC
    ");

    $stmtLista->execute($parametrosLista);

    $producoes = $stmtLista->fetchAll(PDO::FETCH_ASSOC);
}


// ========================================================
// TEXTO DO PERÍODO
// ========================================================

$textoPeriodo = 'Todo o histórico';

if ($anoExato > 0) {
    $textoPeriodo = (string) $anoExato;
}
elseif ($anoInicio > 0 && $anoFim > 0) {
    $textoPeriodo = $anoInicio . '–' . $anoFim;
}
elseif ($anoInicio > 0) {
    $textoPeriodo = 'A partir de ' . $anoInicio;
}
elseif ($anoFim > 0) {
    $textoPeriodo = 'Até ' . $anoFim;
}


// ========================================================
// QUERY STRING PARA EXPORTAÇÃO
// ========================================================

$parametrosExportacao = [
    'docente' => $docenteId,
    'periodo' => $periodoFiltro
];

if ($anoInicioPersonalizado > 0) {
    $parametrosExportacao['ano_inicio'] = $anoInicioPersonalizado;
}

if ($anoFimPersonalizado > 0) {
    $parametrosExportacao['ano_fim'] = $anoFimPersonalizado;
}

$urlCsv = 'exportar_csv.php?' . http_build_query($parametrosExportacao);
$urlPdf = 'exportar_pdf.php?' . http_build_query($parametrosExportacao);
$urlExcel = 'exportar_excel.php?' . http_build_query($parametrosExportacao);
$urlWord = 'exportar_word.php?' . http_build_query($parametrosExportacao);

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
        Relatório Individual - PPGA Analytics
    </title>

    <link
        rel="stylesheet"
        href="../../assets/css/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/relatorios.css"
    >
</head>

<body>

<div class="dashboard">

    <header class="dashboard-header">
        <div>
            <h1>
                Relatório Individual
            </h1>

            <p>
                Produção científica e indicadores do docente
            </p>
        </div>

        <div class="relatorios-acoes-topo">
            <a
                href="<?= htmlspecialchars($urlPdf) ?>"
                class="botao botao-exportar"
            >
                Exportar PDF
            </a>

            <a
                href="<?= htmlspecialchars($urlExcel) ?>"
                class="botao botao-exportar"
            >
                Exportar Excel
            </a>

            <a
                href="<?= htmlspecialchars($urlWord) ?>"
                class="botao botao-exportar"
            >
                Exportar Word
            </a>

            <a
                href="<?= htmlspecialchars($urlCsv) ?>"
                class="botao botao-exportar"
            >
                Exportar CSV
            </a>

            <a
                href="index.php"
                class="botao botao-secundario"
            >
                Relatórios
            </a>

            <a
                href="../dashboard/index.php"
                class="botao botao-secundario"
            >
                Dashboard
            </a>
        </div>
    </header>


    <section class="painel-filtros">
        <form
            method="GET"
            action=""
            id="formRelatorioDocente"
        >
            <div class="campo-filtro campo-docente-relatorio">
                <label for="docente">
                    Docente
                </label>

                <select
                    name="docente"
                    id="docente"
                    required
                >
                    <?php foreach ($docentes as $itemDocente): ?>
                        <option
                            value="<?= (int) $itemDocente['id'] ?>"
                            <?= $docenteId === (int) $itemDocente['id']
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= htmlspecialchars($itemDocente['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>


            <div class="campo-filtro">
                <label for="periodo">
                    Período
                </label>

                <select
                    name="periodo"
                    id="periodo"
                >
                    <option
                        value=""
                        <?= $periodoFiltro === '' ? 'selected' : '' ?>
                    >
                        Todo o histórico
                    </option>

                    <option
                        value="ultimos_2"
                        <?= $periodoFiltro === 'ultimos_2' ? 'selected' : '' ?>
                    >
                        Últimos 2 anos
                    </option>

                    <option
                        value="ultimos_3"
                        <?= $periodoFiltro === 'ultimos_3' ? 'selected' : '' ?>
                    >
                        Últimos 3 anos
                    </option>

                    <option
                        value="ultimos_4"
                        <?= $periodoFiltro === 'ultimos_4' ? 'selected' : '' ?>
                    >
                        Últimos 4 anos
                    </option>

                    <?php if ($quadrienioAtivo): ?>
                        <option
                            value="quadrienio"
                            <?= $periodoFiltro === 'quadrienio' ? 'selected' : '' ?>
                        >
                            Quadriênio CAPES
                            (<?= (int) $quadrienioAtivo['ano_inicio'] ?>-<?= (int) $quadrienioAtivo['ano_fim'] ?>)
                        </option>
                    <?php endif; ?>

                    <option
                        value="personalizado"
                        <?= $periodoFiltro === 'personalizado' ? 'selected' : '' ?>
                    >
                        Intervalo personalizado
                    </option>

                    <optgroup label="Ano específico">
                        <?php foreach ($anosDisponiveis as $ano): ?>
                            <option
                                value="<?= (int) $ano ?>"
                                <?= $periodoFiltro === (string) (int) $ano
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= (int) $ano ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>


            <div
                class="campo-filtro campo-periodo-personalizado"
                id="campoAnoInicio"
            >
                <label for="ano_inicio">
                    Ano inicial
                </label>

                <input
                    type="number"
                    name="ano_inicio"
                    id="ano_inicio"
                    min="1900"
                    max="2100"
                    value="<?= $anoInicioPersonalizado > 0
                        ? (int) $anoInicioPersonalizado
                        : ''
                    ?>"
                >
            </div>


            <div
                class="campo-filtro campo-periodo-personalizado"
                id="campoAnoFim"
            >
                <label for="ano_fim">
                    Ano final
                </label>

                <input
                    type="number"
                    name="ano_fim"
                    id="ano_fim"
                    min="1900"
                    max="2100"
                    value="<?= $anoFimPersonalizado > 0
                        ? (int) $anoFimPersonalizado
                        : ''
                    ?>"
                >
            </div>


            <div class="acoes-filtro">
                <button
                    type="submit"
                    class="botao"
                >
                    Atualizar
                </button>
            </div>
        </form>
    </section>


    <?php if (!$docente): ?>
        <section class="painel">
            <div class="sem-dados">
                Nenhum docente disponível para gerar o relatório.
            </div>
        </section>
    <?php else: ?>

        <section class="painel painel-identificacao-relatorio">
            <div class="identificacao-relatorio">
                <div>
                    <span class="etiqueta-relatorio">
                        Docente
                    </span>

                    <h2>
                        <?= htmlspecialchars($docente['nome']) ?>
                    </h2>

                    <p>
                        Período analisado:
                        <strong><?= htmlspecialchars($textoPeriodo) ?></strong>
                    </p>
                </div>

                <div class="dados-docente-relatorio">
                    <?php if (!empty($docente['email'])): ?>
                        <span>
                            <?= htmlspecialchars($docente['email']) ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($docente['lattes_id'])): ?>
                        <span>
                            Lattes: <?= htmlspecialchars($docente['lattes_id']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </section>


        <section class="cards cards-relatorio-principal">
            <article class="card">
                <span class="card-titulo">
                    Produções
                </span>

                <strong class="card-numero">
                    <?= $resumo['total_producoes'] ?>
                </strong>

                <span class="card-descricao">
                    No período selecionado
                </span>
            </article>

            <article class="card">
                <span class="card-titulo">
                    Produções avaliadas
                </span>

                <strong class="card-numero">
                    <?= $resumo['producoes_avaliadas'] ?>
                </strong>

                <span class="card-descricao">
                    No quadriênio CAPES ativo
                </span>
            </article>

            <article class="card">
                <span class="card-titulo">
                    Pontuação CAPES
                </span>

                <strong class="card-numero">
                    <?= number_format(
                        (float) $resumo['pontuacao'],
                        0,
                        ',',
                        '.'
                    ) ?>
                </strong>

                <span class="card-descricao">
                    Pontuação interna acumulada
                </span>
            </article>

            <article class="card">
                <span class="card-titulo">
                    Com discentes
                </span>

                <strong class="card-numero">
                    <?= $resumo['com_discentes'] ?>
                </strong>

                <span class="card-descricao">
                    Produções com participação discente
                </span>
            </article>

            <article class="card">
                <span class="card-titulo">
                    Internacionais
                </span>

                <strong class="card-numero">
                    <?= $resumo['internacionais'] ?>
                </strong>

                <span class="card-descricao">
                    Marcadas como internacionais
                </span>
            </article>

            <article class="card">
                <span class="card-titulo">
                    Coautoria internacional
                </span>

                <strong class="card-numero">
                    <?= $resumo['coautoria_internacional'] ?>
                </strong>

                <span class="card-descricao">
                    Produções com coautoria internacional
                </span>
            </article>
        </section>


        <section class="painel painel-classificacoes-relatorio">
            <div class="painel-cabecalho">
                <h2>
                    Classificações CAPES
                </h2>

                <p>
                    Distribuição das produções avaliadas no quadriênio CAPES ativo.
                </p>
            </div>

            <div class="cards cards-capes cards-capes-relatorio">
                <?php foreach (['MB', 'B', 'R', 'F', 'NC'] as $codigo): ?>
                    <article class="card">
                        <span class="card-titulo">
                            <?= $codigo ?>
                        </span>

                        <strong class="card-numero">
                            <?= $resumo[$codigo] ?>
                        </strong>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>


        <section class="graficos graficos-relatorio">
            <article class="painel">
                <div class="painel-cabecalho">
                    <h2>
                        Produções por ano
                    </h2>

                    <p>
                        Evolução da produção no período selecionado.
                    </p>
                </div>

                <div class="grafico-container">
                    <canvas id="graficoProducoesAno"></canvas>
                </div>
            </article>


            <article class="painel">
                <div class="painel-cabecalho">
                    <h2>
                        Produções por tipo
                    </h2>

                    <p>
                        Distribuição das produções por categoria.
                    </p>
                </div>

                <div class="grafico-container">
                    <canvas id="graficoProducoesTipo"></canvas>
                </div>
            </article>
        </section>


        <section class="painel painel-producoes-relatorio">
            <div class="painel-cabecalho cabecalho-producoes-relatorio">
                <div>
                    <h2>
                        Produções detalhadas
                    </h2>

                    <p>
                        <?= count($producoes) ?> registro(s) no período analisado.
                    </p>
                </div>

                <div class="acoes-exportacao-relatorio">
                    <a
                        href="<?= htmlspecialchars($urlPdf) ?>"
                        class="botao botao-exportar"
                    >
                        Exportar PDF
                    </a>

                    <a
                        href="<?= htmlspecialchars($urlExcel) ?>"
                        class="botao botao-exportar"
                    >
                        Exportar Excel
                    </a>

                    <a
                        href="<?= htmlspecialchars($urlWord) ?>"
                        class="botao botao-exportar"
                    >
                        Exportar Word
                    </a>

                    <a
                        href="<?= htmlspecialchars($urlCsv) ?>"
                        class="botao botao-exportar"
                    >
                        Exportar CSV
                    </a>
                </div>
            </div>

            <div class="tabela-container">
                <table>
                    <thead>
                        <tr>
                            <th>Produção</th>
                            <th>Ano</th>
                            <th>Tipo</th>
                            <th>Periódico</th>
                            <th>Discente(s)</th>
                            <th>CAPES</th>
                            <th>Pontos</th>
                            <th>Internacional</th>
                            <th>Acesso</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($producoes) === 0): ?>
                            <tr>
                                <td
                                    colspan="9"
                                    class="sem-dados"
                                >
                                    Nenhuma produção encontrada para o período selecionado.
                                </td>
                            </tr>
                        <?php else: ?>

                            <?php foreach ($producoes as $producao): ?>
                                <tr>
                                    <td class="coluna-titulo-relatorio">
                                        <strong>
                                            <?= htmlspecialchars($producao['titulo']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= (int) $producao['ano'] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($producao['tipo']) ?>
                                    </td>

                                    <td>
                                        <?= !empty($producao['periodico'])
                                            ? htmlspecialchars($producao['periodico'])
                                            : '—'
                                        ?>
                                    </td>

                                    <td>
                                        <?= !empty($producao['discentes'])
                                            ? htmlspecialchars($producao['discentes'])
                                            : '—'
                                        ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= !empty($producao['classificacao'])
                                                ? htmlspecialchars($producao['classificacao'])
                                                : '—'
                                            ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= !empty($producao['classificacao'])
                                            ? number_format(
                                                (float) $producao['pontos'],
                                                0,
                                                ',',
                                                '.'
                                            )
                                            : '—'
                                        ?>
                                    </td>

                                    <td>
                                        <?= ((int) $producao['internacional'] === 1
                                            || (int) $producao['coautoria_internacional'] === 1)
                                            ? 'Sim'
                                            : 'Não'
                                        ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($producao['doi'])): ?>
                                            <a
                                                class="botao botao-tabela-relatorio"
                                                href="https://doi.org/<?= urlencode($producao['doi']) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                DOI
                                            </a>
                                        <?php else: ?>
                                            <span class="sem-link">
                                                —
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>


        <section class="painel nota-metodologica-relatorio">
            <strong>
                Nota metodológica
            </strong>

            <p>
                Os indicadores CAPES apresentados são gerenciais e utilizam as avaliações
                cadastradas no quadriênio ativo. O PPGA Analytics não gera nota oficial da CAPES.
            </p>
        </section>

    <?php endif; ?>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/chart.js"
></script>

<script>
const labelsAno = <?= json_encode(
    array_map(
        static fn(array $item): int => (int) $item['ano'],
        $producoesPorAno
    ),
    JSON_UNESCAPED_UNICODE
) ?>;

const dadosAno = <?= json_encode(
    array_map(
        static fn(array $item): int => (int) $item['quantidade'],
        $producoesPorAno
    ),
    JSON_UNESCAPED_UNICODE
) ?>;

const labelsTipo = <?= json_encode(
    array_map(
        static fn(array $item): string => (string) $item['nome'],
        $producoesPorTipo
    ),
    JSON_UNESCAPED_UNICODE
) ?>;

const dadosTipo = <?= json_encode(
    array_map(
        static fn(array $item): int => (int) $item['quantidade'],
        $producoesPorTipo
    ),
    JSON_UNESCAPED_UNICODE
) ?>;

const canvasAno = document.getElementById('graficoProducoesAno');

if (canvasAno && typeof Chart !== 'undefined') {
    new Chart(canvasAno, {
        type: 'line',
        data: {
            labels: labelsAno,
            datasets: [
                {
                    label: 'Produções',
                    data: dadosAno,
                    tension: 0.25,
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

const canvasTipo = document.getElementById('graficoProducoesTipo');

if (canvasTipo && typeof Chart !== 'undefined') {
    new Chart(canvasTipo, {
        type: 'bar',
        data: {
            labels: labelsTipo,
            datasets: [
                {
                    label: 'Produções',
                    data: dadosTipo
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

const periodo = document.getElementById('periodo');
const campoAnoInicio = document.getElementById('campoAnoInicio');
const campoAnoFim = document.getElementById('campoAnoFim');

function atualizarCamposPersonalizados() {
    const exibir = periodo && periodo.value === 'personalizado';

    if (campoAnoInicio) {
        campoAnoInicio.style.display = exibir ? 'flex' : 'none';
    }

    if (campoAnoFim) {
        campoAnoFim.style.display = exibir ? 'flex' : 'none';
    }
}

if (periodo) {
    periodo.addEventListener('change', atualizarCamposPersonalizados);
    atualizarCamposPersonalizados();
}
</script>

</body>
</html>