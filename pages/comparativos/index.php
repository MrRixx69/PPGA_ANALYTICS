<?php

$raizProjeto = dirname(__DIR__, 2);

require_once
    $raizProjeto
    . DIRECTORY_SEPARATOR
    . 'config'
    . DIRECTORY_SEPARATOR
    . 'conexao.php';


// ========================================================
// LINHAS DE PESQUISA DISPONÍVEIS
// ========================================================

$stmtLinhas = $pdo->query("
    SELECT
        id,
        nome,
        descricao
    FROM linhas_pesquisa
    ORDER BY nome
");

$linhas = $stmtLinhas->fetchAll(PDO::FETCH_ASSOC);


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

$anos = $stmtAnos->fetchAll(PDO::FETCH_COLUMN);


// ========================================================
// FILTROS
// ========================================================

$linhaAId = isset($_GET['linha_a'])
    ? (int) $_GET['linha_a']
    : 0;

$linhaBId = isset($_GET['linha_b'])
    ? (int) $_GET['linha_b']
    : 0;

$idsLinhasDisponiveis = array_map(
    static fn(array $linha): int => (int) $linha['id'],
    $linhas
);

if (
    $linhaAId <= 0
    || !in_array($linhaAId, $idsLinhasDisponiveis, true)
) {
    $linhaAId = $idsLinhasDisponiveis[0] ?? 0;
}

if (
    $linhaBId <= 0
    || !in_array($linhaBId, $idsLinhasDisponiveis, true)
    || $linhaBId === $linhaAId
) {
    $linhaBId = 0;

    foreach ($idsLinhasDisponiveis as $idDisponivel) {
        if ($idDisponivel !== $linhaAId) {
            $linhaBId = $idDisponivel;
            break;
        }
    }
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
// FUNÇÕES AUXILIARES
// ========================================================

function montarFiltroPeriodoLinha(
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


function buscarResumoLinha(
    PDO $pdo,
    int $linhaId,
    ?array $quadrienioAtivo,
    int $anoExato,
    int $anoInicio,
    int $anoFim,
    string $sufixo
): array {
    $filtroPeriodo = montarFiltroPeriodoLinha(
        'p',
        $anoExato,
        $anoInicio,
        $anoFim,
        $sufixo
    );

    $where = [
        'pl.linha_id = :linha_' . $sufixo
    ];

    $where = array_merge($where, $filtroPeriodo['sql']);

    $parametros = [
        ':linha_' . $sufixo => $linhaId
    ];

    $parametros = array_merge(
        $parametros,
        $filtroPeriodo['parametros']
    );

    if ($quadrienioAtivo) {
        $joinAvaliacao = "
            LEFT JOIN avaliacoes_producao ap
                ON ap.producao_id = p.id
                AND ap.quadrienio_id = :quadrienio_$sufixo
            LEFT JOIN classificacoes_capes c
                ON c.id = ap.classificacao_id
        ";

        $parametros[':quadrienio_' . $sufixo] =
            (int) $quadrienioAtivo['id'];
    }
    else {
        $joinAvaliacao = "
            LEFT JOIN avaliacoes_producao ap
                ON 1 = 0
            LEFT JOIN classificacoes_capes c
                ON 1 = 0
        ";
    }

    $sqlWhere = implode(' AND ', $where);

    $stmt = $pdo->prepare("
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

            COALESCE(
                SUM(CASE WHEN c.codigo = 'MB' THEN 1 ELSE 0 END),
                0
            ) AS mb,

            COALESCE(
                SUM(CASE WHEN c.codigo = 'B' THEN 1 ELSE 0 END),
                0
            ) AS b,

            COALESCE(
                SUM(CASE WHEN c.codigo = 'R' THEN 1 ELSE 0 END),
                0
            ) AS r,

            COALESCE(
                SUM(CASE WHEN c.codigo = 'F' THEN 1 ELSE 0 END),
                0
            ) AS f,

            COALESCE(
                SUM(CASE WHEN c.codigo = 'NC' THEN 1 ELSE 0 END),
                0
            ) AS nc,

            COUNT(
                DISTINCT CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM producoes_discentes pdisc
                        WHERE pdisc.producao_id = p.id
                    ) THEN p.id
                END
            ) AS producoes_com_discentes,

            COUNT(
                DISTINCT CASE
                    WHEN p.internacional = 1
                    THEN p.id
                END
            ) AS producoes_internacionais,

            COUNT(
                DISTINCT CASE
                    WHEN p.coautoria_internacional = 1
                    THEN p.id
                END
            ) AS coautorias_internacionais

        FROM producoes p

        INNER JOIN producoes_linhas pl
            ON pl.producao_id = p.id

        $joinAvaliacao

        WHERE $sqlWhere
    ");

    $stmt->execute($parametros);

    $resumo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total_producoes' => (int) ($resumo['total_producoes'] ?? 0),
        'producoes_avaliadas' => (int) ($resumo['producoes_avaliadas'] ?? 0),
        'pontuacao' => (float) ($resumo['pontuacao'] ?? 0),
        'mb' => (int) ($resumo['mb'] ?? 0),
        'b' => (int) ($resumo['b'] ?? 0),
        'r' => (int) ($resumo['r'] ?? 0),
        'f' => (int) ($resumo['f'] ?? 0),
        'nc' => (int) ($resumo['nc'] ?? 0),
        'producoes_com_discentes' =>
            (int) ($resumo['producoes_com_discentes'] ?? 0),
        'producoes_internacionais' =>
            (int) ($resumo['producoes_internacionais'] ?? 0),
        'coautorias_internacionais' =>
            (int) ($resumo['coautorias_internacionais'] ?? 0)
    ];
}


function buscarProducaoPorAnoLinha(
    PDO $pdo,
    int $linhaId,
    ?array $quadrienioAtivo,
    int $anoExato,
    int $anoInicio,
    int $anoFim,
    string $sufixo
): array {
    $filtroPeriodo = montarFiltroPeriodoLinha(
        'p',
        $anoExato,
        $anoInicio,
        $anoFim,
        $sufixo
    );

    $where = [
        'pl.linha_id = :linha_' . $sufixo
    ];

    $where = array_merge($where, $filtroPeriodo['sql']);

    $parametros = [
        ':linha_' . $sufixo => $linhaId
    ];

    $parametros = array_merge(
        $parametros,
        $filtroPeriodo['parametros']
    );

    if ($quadrienioAtivo) {
        $joinAvaliacao = "
            LEFT JOIN avaliacoes_producao ap
                ON ap.producao_id = p.id
                AND ap.quadrienio_id = :quadrienio_$sufixo
        ";

        $parametros[':quadrienio_' . $sufixo] =
            (int) $quadrienioAtivo['id'];
    }
    else {
        $joinAvaliacao = "
            LEFT JOIN avaliacoes_producao ap
                ON 1 = 0
        ";
    }

    $sqlWhere = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            p.ano,
            COUNT(DISTINCT p.id) AS quantidade,
            COALESCE(SUM(ap.pontuacao_interna), 0) AS pontos

        FROM producoes p

        INNER JOIN producoes_linhas pl
            ON pl.producao_id = p.id

        $joinAvaliacao

        WHERE $sqlWhere

        GROUP BY p.ano
        ORDER BY p.ano
    ");

    $stmt->execute($parametros);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function buscarProducaoPorTipoLinha(
    PDO $pdo,
    int $linhaId,
    int $anoExato,
    int $anoInicio,
    int $anoFim,
    string $sufixo
): array {
    $filtroPeriodo = montarFiltroPeriodoLinha(
        'p',
        $anoExato,
        $anoInicio,
        $anoFim,
        $sufixo
    );

    $where = [
        'pl.linha_id = :linha_' . $sufixo
    ];

    $where = array_merge($where, $filtroPeriodo['sql']);

    $parametros = [
        ':linha_' . $sufixo => $linhaId
    ];

    $parametros = array_merge(
        $parametros,
        $filtroPeriodo['parametros']
    );

    $sqlWhere = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            tp.nome,
            COUNT(DISTINCT p.id) AS quantidade

        FROM producoes p

        INNER JOIN producoes_linhas pl
            ON pl.producao_id = p.id

        INNER JOIN tipos_producao tp
            ON tp.id = p.tipo_producao_id

        WHERE $sqlWhere

        GROUP BY tp.id, tp.nome
        ORDER BY quantidade DESC, tp.nome
    ");

    $stmt->execute($parametros);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function buscarNomeLinha(array $linhas, int $linhaId): string
{
    foreach ($linhas as $linha) {
        if ((int) $linha['id'] === $linhaId) {
            return (string) $linha['nome'];
        }
    }

    return 'Linha de pesquisa';
}


function formatarDiferencaLinha(
    float $valorA,
    float $valorB,
    bool $decimal = false
): string {
    $diferenca = $valorA - $valorB;
    $sinal = $diferenca > 0 ? '+' : '';

    if ($decimal) {
        return $sinal . number_format($diferenca, 0, ',', '.');
    }

    return $sinal . (string) (int) $diferenca;
}


// ========================================================
// DADOS DA COMPARAÇÃO
// ========================================================

$nomeLinhaA = buscarNomeLinha($linhas, $linhaAId);
$nomeLinhaB = buscarNomeLinha($linhas, $linhaBId);

$resumoA = buscarResumoLinha(
    $pdo,
    $linhaAId,
    $quadrienioAtivo ?: null,
    $anoExato,
    $anoInicio,
    $anoFim,
    'a'
);

$resumoB = buscarResumoLinha(
    $pdo,
    $linhaBId,
    $quadrienioAtivo ?: null,
    $anoExato,
    $anoInicio,
    $anoFim,
    'b'
);

$porAnoA = buscarProducaoPorAnoLinha(
    $pdo,
    $linhaAId,
    $quadrienioAtivo ?: null,
    $anoExato,
    $anoInicio,
    $anoFim,
    'ano_a'
);

$porAnoB = buscarProducaoPorAnoLinha(
    $pdo,
    $linhaBId,
    $quadrienioAtivo ?: null,
    $anoExato,
    $anoInicio,
    $anoFim,
    'ano_b'
);

$porTipoA = buscarProducaoPorTipoLinha(
    $pdo,
    $linhaAId,
    $anoExato,
    $anoInicio,
    $anoFim,
    'tipo_a'
);

$porTipoB = buscarProducaoPorTipoLinha(
    $pdo,
    $linhaBId,
    $anoExato,
    $anoInicio,
    $anoFim,
    'tipo_b'
);


// ========================================================
// NORMALIZAÇÃO DOS GRÁFICOS
// ========================================================

$mapaAnosA = [];
$mapaPontosA = [];

foreach ($porAnoA as $registro) {
    $ano = (string) $registro['ano'];
    $mapaAnosA[$ano] = (int) $registro['quantidade'];
    $mapaPontosA[$ano] = (float) $registro['pontos'];
}

$mapaAnosB = [];
$mapaPontosB = [];

foreach ($porAnoB as $registro) {
    $ano = (string) $registro['ano'];
    $mapaAnosB[$ano] = (int) $registro['quantidade'];
    $mapaPontosB[$ano] = (float) $registro['pontos'];
}

$labelsAnos = array_values(
    array_unique(
        array_merge(
            array_keys($mapaAnosA),
            array_keys($mapaAnosB)
        )
    )
);

sort($labelsAnos, SORT_NUMERIC);

$dadosProducoesAnoA = [];
$dadosProducoesAnoB = [];
$dadosPontosAnoA = [];
$dadosPontosAnoB = [];

foreach ($labelsAnos as $ano) {
    $dadosProducoesAnoA[] = $mapaAnosA[$ano] ?? 0;
    $dadosProducoesAnoB[] = $mapaAnosB[$ano] ?? 0;
    $dadosPontosAnoA[] = $mapaPontosA[$ano] ?? 0;
    $dadosPontosAnoB[] = $mapaPontosB[$ano] ?? 0;
}

$mapaTiposA = [];
foreach ($porTipoA as $registro) {
    $mapaTiposA[$registro['nome']] = (int) $registro['quantidade'];
}

$mapaTiposB = [];
foreach ($porTipoB as $registro) {
    $mapaTiposB[$registro['nome']] = (int) $registro['quantidade'];
}

$labelsTipos = array_values(
    array_unique(
        array_merge(
            array_keys($mapaTiposA),
            array_keys($mapaTiposB)
        )
    )
);

usort(
    $labelsTipos,
    static function (string $tipoA, string $tipoB) use ($mapaTiposA, $mapaTiposB): int {
        $totalA = ($mapaTiposA[$tipoA] ?? 0) + ($mapaTiposB[$tipoA] ?? 0);
        $totalB = ($mapaTiposA[$tipoB] ?? 0) + ($mapaTiposB[$tipoB] ?? 0);

        if ($totalA === $totalB) {
            return strcmp($tipoA, $tipoB);
        }

        return $totalB <=> $totalA;
    }
);

$dadosTiposA = [];
$dadosTiposB = [];

foreach ($labelsTipos as $tipo) {
    $dadosTiposA[] = $mapaTiposA[$tipo] ?? 0;
    $dadosTiposB[] = $mapaTiposB[$tipo] ?? 0;
}


// ========================================================
// RÓTULO DO PERÍODO
// ========================================================

if ($anoExato > 0) {
    $rotuloPeriodo = (string) $anoExato;
}
elseif ($anoInicio > 0 && $anoFim > 0) {
    $rotuloPeriodo = $anoInicio . '–' . $anoFim;
}
elseif ($anoInicio > 0) {
    $rotuloPeriodo = 'A partir de ' . $anoInicio;
}
elseif ($anoFim > 0) {
    $rotuloPeriodo = 'Até ' . $anoFim;
}
else {
    $rotuloPeriodo = 'Todo o histórico';
}

$metricas = [
    ['label' => 'Produções', 'chave' => 'total_producoes', 'decimal' => false],
    ['label' => 'Produções avaliadas', 'chave' => 'producoes_avaliadas', 'decimal' => false],
    ['label' => 'Pontuação CAPES', 'chave' => 'pontuacao', 'decimal' => true],
    ['label' => 'MB', 'chave' => 'mb', 'decimal' => false],
    ['label' => 'B', 'chave' => 'b', 'decimal' => false],
    ['label' => 'R', 'chave' => 'r', 'decimal' => false],
    ['label' => 'F', 'chave' => 'f', 'decimal' => false],
    ['label' => 'NC', 'chave' => 'nc', 'decimal' => false],
    ['label' => 'Com discentes', 'chave' => 'producoes_com_discentes', 'decimal' => false],
    ['label' => 'Internacionais', 'chave' => 'producoes_internacionais', 'decimal' => false],
    ['label' => 'Coautoria internacional', 'chave' => 'coautorias_internacionais', 'decimal' => false]
];

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
        Linha × Linha - PPGA Analytics
    </title>

    <link
        rel="stylesheet"
        href="../../assets/css/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/comparativos.css"
    >
</head>
<body>

<div class="dashboard">

    <header class="dashboard-header">
        <div>
            <h1>
                Comparativos
            </h1>

            <p>
                Linha × Linha · <?= htmlspecialchars($rotuloPeriodo) ?>
            </p>
        </div>

        <div class="comparativos-acoes-topo">
            <a
                href="index.php"
                class="botao"
            >
                Docente × Docente
            </a>

            <a
                href="media_programa.php"
                class="botao"
            >
                Docente × Média do Programa
            </a>

            <a
                href="ano_ano.php"
                class="botao"
            >
                Ano × Ano
            </a>

            <a
                href="quadrienio_quadrienio.php"
                class="botao"
            >
                Quadriênio × Quadriênio
            </a>

            <a
                href="../dashboard/index.php"
                class="botao botao-secundario"
            >
                Voltar à Dashboard
            </a>
        </div>
    </header>


    <section class="painel-filtros">
        <form
            method="GET"
            action=""
            id="formComparativoLinha"
        >
            <div class="campo-filtro campo-linha-comparativo">
                <label for="linha_a">
                    Linha A
                </label>

                <select
                    name="linha_a"
                    id="linha_a"
                    required
                >
                    <?php foreach ($linhas as $linha): ?>
                        <option
                            value="<?= (int) $linha['id'] ?>"
                            <?= $linhaAId === (int) $linha['id']
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= htmlspecialchars($linha['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>


            <div class="campo-filtro campo-linha-comparativo">
                <label for="linha_b">
                    Linha B
                </label>

                <select
                    name="linha_b"
                    id="linha_b"
                    required
                >
                    <?php foreach ($linhas as $linha): ?>
                        <option
                            value="<?= (int) $linha['id'] ?>"
                            <?= $linhaBId === (int) $linha['id']
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= htmlspecialchars($linha['nome']) ?>
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
                        <?php foreach ($anos as $ano): ?>
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
                    Comparar
                </button>

                <a
                    href="linha_linha.php"
                    class="botao botao-secundario"
                >
                    Limpar
                </a>
            </div>
        </form>
    </section>


    <?php if (count($linhas) < 2): ?>
        <section class="painel">
            <div class="sem-dados">
                É necessário ter pelo menos duas linhas de pesquisa cadastradas para gerar o comparativo.
            </div>
        </section>
    <?php else: ?>

        <section class="painel aviso-vinculos-linha">
            <p>
                O comparativo considera apenas produções vinculadas às linhas de pesquisa.
                Se uma mesma produção estiver associada a mais de uma linha, ela é contabilizada integralmente em cada linha vinculada.
            </p>
        </section>


        <section class="comparativo-identidades">
            <article class="card comparativo-docente-card">
                <span class="comparativo-etiqueta">
                    Linha A
                </span>

                <strong class="comparativo-nome comparativo-linha-nome">
                    <?= htmlspecialchars($nomeLinhaA) ?>
                </strong>

                <div class="comparativo-resumo-card">
                    <div>
                        <span>Produções</span>
                        <strong><?= $resumoA['total_producoes'] ?></strong>
                    </div>

                    <div>
                        <span>Pontos CAPES</span>
                        <strong>
                            <?= number_format($resumoA['pontuacao'], 0, ',', '.') ?>
                        </strong>
                    </div>
                </div>
            </article>


            <div class="comparativo-versus">
                ×
            </div>


            <article class="card comparativo-docente-card">
                <span class="comparativo-etiqueta">
                    Linha B
                </span>

                <strong class="comparativo-nome comparativo-linha-nome">
                    <?= htmlspecialchars($nomeLinhaB) ?>
                </strong>

                <div class="comparativo-resumo-card">
                    <div>
                        <span>Produções</span>
                        <strong><?= $resumoB['total_producoes'] ?></strong>
                    </div>

                    <div>
                        <span>Pontos CAPES</span>
                        <strong>
                            <?= number_format($resumoB['pontuacao'], 0, ',', '.') ?>
                        </strong>
                    </div>
                </div>
            </article>
        </section>


        <section class="painel painel-comparacao">
            <div class="painel-cabecalho">
                <h2>
                    Indicadores por linha de pesquisa
                </h2>

                <p>
                    A pontuação e as classificações usam o quadriênio CAPES ativo.
                </p>
            </div>

            <div class="tabela-container">
                <table class="tabela-comparativo">
                    <thead>
                        <tr>
                            <th>Indicador</th>
                            <th><?= htmlspecialchars($nomeLinhaA) ?></th>
                            <th><?= htmlspecialchars($nomeLinhaB) ?></th>
                            <th>Diferença A − B</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($metricas as $metrica): ?>
                            <?php
                                $chave = $metrica['chave'];
                                $decimal = (bool) $metrica['decimal'];
                                $valorA = (float) $resumoA[$chave];
                                $valorB = (float) $resumoB[$chave];
                            ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?= htmlspecialchars($metrica['label']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= number_format(
                                        $valorA,
                                        0,
                                        ',',
                                        '.'
                                    ) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        $valorB,
                                        0,
                                        ',',
                                        '.'
                                    ) ?>
                                </td>

                                <td>
                                    <span class="diferenca-comparativo <?=
                                        $valorA > $valorB
                                            ? 'diferenca-positiva'
                                            : ($valorA < $valorB
                                                ? 'diferenca-negativa'
                                                : 'diferenca-neutra')
                                    ?>">
                                        <?= htmlspecialchars(
                                            formatarDiferencaLinha(
                                                $valorA,
                                                $valorB,
                                                $decimal
                                            )
                                        ) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>


        <section class="graficos">
            <article class="painel">
                <div class="painel-cabecalho">
                    <h2>
                        Produções por ano
                    </h2>

                    <p>
                        Evolução das produções vinculadas a cada linha.
                    </p>
                </div>

                <div class="grafico-container">
                    <canvas id="graficoLinhaProducoes"></canvas>
                </div>
            </article>


            <article class="painel">
                <div class="painel-cabecalho">
                    <h2>
                        Pontuação CAPES por ano
                    </h2>

                    <p>
                        Pontuação das produções avaliadas vinculadas a cada linha.
                    </p>
                </div>

                <div class="grafico-container">
                    <canvas id="graficoLinhaPontos"></canvas>
                </div>
            </article>
        </section>


        <section class="painel painel-tipos-comparativo">
            <div class="painel-cabecalho">
                <h2>
                    Produções por tipo
                </h2>

                <p>
                    Distribuição dos tipos de produção em cada linha de pesquisa.
                </p>
            </div>

            <div class="grafico-container grafico-tipos-comparativo">
                <canvas id="graficoLinhaTipos"></canvas>
            </div>
        </section>

    <?php endif; ?>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/chart.js"
></script>

<script>

const selectPeriodo = document.getElementById('periodo');
const camposPeriodoPersonalizado = document.querySelectorAll(
    '.campo-periodo-personalizado'
);

function atualizarCamposPeriodo() {
    const exibir =
        selectPeriodo
        && selectPeriodo.value === 'personalizado';

    camposPeriodoPersonalizado.forEach((campo) => {
        campo.style.display = exibir ? 'flex' : 'none';
    });
}

if (selectPeriodo) {
    atualizarCamposPeriodo();
    selectPeriodo.addEventListener('change', atualizarCamposPeriodo);
}

const selectLinhaA = document.getElementById('linha_a');
const selectLinhaB = document.getElementById('linha_b');

function impedirMesmaLinha() {
    if (!selectLinhaA || !selectLinhaB) {
        return;
    }

    Array.from(selectLinhaB.options).forEach((option) => {
        option.disabled = option.value === selectLinhaA.value;
    });

    if (selectLinhaB.value === selectLinhaA.value) {
        const alternativa = Array.from(selectLinhaB.options).find(
            (option) => !option.disabled
        );

        if (alternativa) {
            selectLinhaB.value = alternativa.value;
        }
    }
}

if (selectLinhaA && selectLinhaB) {
    impedirMesmaLinha();
    selectLinhaA.addEventListener('change', impedirMesmaLinha);
}

<?php if (count($linhas) >= 2): ?>

const labelsAnos = <?= json_encode(
    $labelsAnos,
    JSON_UNESCAPED_UNICODE
) ?>;

const producoesAnoA = <?= json_encode($dadosProducoesAnoA) ?>;
const producoesAnoB = <?= json_encode($dadosProducoesAnoB) ?>;
const pontosAnoA = <?= json_encode($dadosPontosAnoA) ?>;
const pontosAnoB = <?= json_encode($dadosPontosAnoB) ?>;

const labelsTipos = <?= json_encode(
    $labelsTipos,
    JSON_UNESCAPED_UNICODE
) ?>;

const tiposA = <?= json_encode($dadosTiposA) ?>;
const tiposB = <?= json_encode($dadosTiposB) ?>;

const nomeLinhaA = <?= json_encode(
    $nomeLinhaA,
    JSON_UNESCAPED_UNICODE
) ?>;

const nomeLinhaB = <?= json_encode(
    $nomeLinhaB,
    JSON_UNESCAPED_UNICODE
) ?>;

const canvasProducoes = document.getElementById(
    'graficoLinhaProducoes'
);

if (canvasProducoes) {
    new Chart(canvasProducoes, {
        type: 'line',
        data: {
            labels: labelsAnos,
            datasets: [
                {
                    label: nomeLinhaA,
                    data: producoesAnoA,
                    tension: 0.25,
                    fill: false
                },
                {
                    label: nomeLinhaB,
                    data: producoesAnoB,
                    tension: 0.25,
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
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

const canvasPontos = document.getElementById(
    'graficoLinhaPontos'
);

if (canvasPontos) {
    new Chart(canvasPontos, {
        type: 'bar',
        data: {
            labels: labelsAnos,
            datasets: [
                {
                    label: nomeLinhaA,
                    data: pontosAnoA
                },
                {
                    label: nomeLinhaB,
                    data: pontosAnoB
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

const canvasTipos = document.getElementById(
    'graficoLinhaTipos'
);

if (canvasTipos) {
    new Chart(canvasTipos, {
        type: 'bar',
        data: {
            labels: labelsTipos,
            datasets: [
                {
                    label: nomeLinhaA,
                    data: tiposA
                },
                {
                    label: nomeLinhaB,
                    data: tiposB
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
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

<?php endif; ?>

</script>

</body>
</html>