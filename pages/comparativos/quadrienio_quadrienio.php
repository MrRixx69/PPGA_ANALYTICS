<?php

$raizProjeto = dirname(__DIR__, 2);

require_once
    $raizProjeto
    . DIRECTORY_SEPARATOR
    . 'config'
    . DIRECTORY_SEPARATOR
    . 'conexao.php';


// ========================================================
// QUADRIÊNIO ATIVO E QUADRIÊNIOS CADASTRADOS
// ========================================================

$stmtQuadrienioAtivo = $pdo->query("
    SELECT
        id,
        ano_inicio,
        ano_fim,
        descricao,
        ativo
    FROM quadrienios
    WHERE ativo = 1
    ORDER BY ano_inicio DESC
    LIMIT 1
");

$quadrienioAtivo = $stmtQuadrienioAtivo->fetch(PDO::FETCH_ASSOC);

$stmtQuadrienios = $pdo->query("
    SELECT
        id,
        ano_inicio,
        ano_fim,
        descricao,
        ativo
    FROM quadrienios
    ORDER BY ano_inicio DESC
");

$quadrieniosCadastrados = $stmtQuadrienios->fetchAll(PDO::FETCH_ASSOC);

$mapaQuadrieniosCadastrados = [];

foreach ($quadrieniosCadastrados as $quadrienio) {
    $chave =
        (int) $quadrienio['ano_inicio']
        . '-'
        . (int) $quadrienio['ano_fim'];

    $mapaQuadrieniosCadastrados[$chave] = $quadrienio;
}


// ========================================================
// INTERVALO HISTÓRICO DISPONÍVEL
// ========================================================

$stmtLimites = $pdo->query("
    SELECT
        MIN(ano) AS ano_minimo,
        MAX(ano) AS ano_maximo
    FROM producoes
    WHERE ano IS NOT NULL
");

$limites = $stmtLimites->fetch(PDO::FETCH_ASSOC) ?: [];

$anoMinimo = (int) ($limites['ano_minimo'] ?? 0);
$anoMaximo = (int) ($limites['ano_maximo'] ?? 0);


// ========================================================
// GERAÇÃO DOS PERÍODOS DE 4 ANOS
// ========================================================

$periodos = [];

if ($quadrienioAtivo) {
    $inicioReferencia = (int) $quadrienioAtivo['ano_inicio'];
    $fimReferencia = (int) $quadrienioAtivo['ano_fim'];
}
else {
    $fimReferencia = $anoMaximo > 0
        ? $anoMaximo
        : (int) date('Y');

    $inicioReferencia = $fimReferencia - 3;
}

// Gera o período de referência e os anteriores, sempre em blocos de 4 anos.
$inicio = $inicioReferencia;
$seguranca = 0;

while ($seguranca < 40) {
    $fim = $inicio + 3;
    $chave = $inicio . '-' . $fim;

    $periodos[$chave] = [
        'chave' => $chave,
        'ano_inicio' => $inicio,
        'ano_fim' => $fim,
        'quadrienio_id' => isset($mapaQuadrieniosCadastrados[$chave])
            ? (int) $mapaQuadrieniosCadastrados[$chave]['id']
            : null,
        'descricao' => $mapaQuadrieniosCadastrados[$chave]['descricao']
            ?? ('Período ' . $chave),
        'ativo' => isset($mapaQuadrieniosCadastrados[$chave])
            ? (int) $mapaQuadrieniosCadastrados[$chave]['ativo']
            : 0
    ];

    if ($anoMinimo <= 0 || $inicio <= $anoMinimo) {
        break;
    }

    $inicio -= 4;
    $seguranca++;
}

// Inclui algum quadriênio cadastrado que eventualmente não esteja alinhado
// ao quadriênio ativo.
foreach ($quadrieniosCadastrados as $quadrienio) {
    $inicio = (int) $quadrienio['ano_inicio'];
    $fim = (int) $quadrienio['ano_fim'];
    $chave = $inicio . '-' . $fim;

    if (!isset($periodos[$chave])) {
        $periodos[$chave] = [
            'chave' => $chave,
            'ano_inicio' => $inicio,
            'ano_fim' => $fim,
            'quadrienio_id' => (int) $quadrienio['id'],
            'descricao' => $quadrienio['descricao']
                ?: ('Quadriênio ' . $chave),
            'ativo' => (int) $quadrienio['ativo']
        ];
    }
}

uasort(
    $periodos,
    static function (array $a, array $b): int {
        return $b['ano_inicio'] <=> $a['ano_inicio'];
    }
);

$chavesPeriodos = array_keys($periodos);


// ========================================================
// FILTROS
// ========================================================

$periodoAChave = isset($_GET['periodo_a'])
    ? trim((string) $_GET['periodo_a'])
    : '';

$periodoBChave = isset($_GET['periodo_b'])
    ? trim((string) $_GET['periodo_b'])
    : '';

if (!isset($periodos[$periodoBChave])) {
    $periodoBChave = $chavesPeriodos[0] ?? '';
}

if (
    !isset($periodos[$periodoAChave])
    || $periodoAChave === $periodoBChave
) {
    $periodoAChave = $chavesPeriodos[1] ?? ($chavesPeriodos[0] ?? '');

    if ($periodoAChave === $periodoBChave) {
        foreach ($chavesPeriodos as $chaveDisponivel) {
            if ($chaveDisponivel !== $periodoBChave) {
                $periodoAChave = $chaveDisponivel;
                break;
            }
        }
    }
}

$periodoA = $periodos[$periodoAChave] ?? null;
$periodoB = $periodos[$periodoBChave] ?? null;


// ========================================================
// FUNÇÕES AUXILIARES
// ========================================================

function buscarResumoQuadrienioComparativo(
    PDO $pdo,
    array $periodo,
    string $sufixo
): array {
    $anoInicio = (int) $periodo['ano_inicio'];
    $anoFim = (int) $periodo['ano_fim'];
    $quadrienioId = $periodo['quadrienio_id'];

    $parametros = [
        ':ano_inicio_' . $sufixo => $anoInicio,
        ':ano_fim_' . $sufixo => $anoFim,
        ':ano_inicio_doc_' . $sufixo => $anoInicio,
        ':ano_fim_doc_' . $sufixo => $anoFim
    ];

    if ($quadrienioId !== null) {
        $joinAvaliacao = "
            LEFT JOIN avaliacoes_producao ap
                ON ap.producao_id = p.id
                AND ap.quadrienio_id = :quadrienio_$sufixo

            LEFT JOIN classificacoes_capes c
                ON c.id = ap.classificacao_id
        ";

        $parametros[':quadrienio_' . $sufixo] =
            (int) $quadrienioId;
    }
    else {
        $joinAvaliacao = "
            LEFT JOIN avaliacoes_producao ap
                ON 1 = 0

            LEFT JOIN classificacoes_capes c
                ON 1 = 0
        ";
    }

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

            COUNT(
                DISTINCT CASE
                    WHEN c.codigo = 'MB' THEN p.id
                END
            ) AS mb,

            COUNT(
                DISTINCT CASE
                    WHEN c.codigo = 'B' THEN p.id
                END
            ) AS b,

            COUNT(
                DISTINCT CASE
                    WHEN c.codigo = 'R' THEN p.id
                END
            ) AS r,

            COUNT(
                DISTINCT CASE
                    WHEN c.codigo = 'F' THEN p.id
                END
            ) AS f,

            COUNT(
                DISTINCT CASE
                    WHEN c.codigo = 'NC' THEN p.id
                END
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
                    WHEN
                        p.internacional = 1
                        OR p.coautoria_internacional = 1
                    THEN p.id
                END
            ) AS producoes_internacionais,

            (
                SELECT COUNT(DISTINCT pd2.docente_id)
                FROM producoes p2
                INNER JOIN producoes_docentes pd2
                    ON pd2.producao_id = p2.id
                INNER JOIN docentes d2
                    ON d2.id = pd2.docente_id
                    AND d2.ativo = 1
                WHERE
                    p2.ano BETWEEN
                        :ano_inicio_doc_$sufixo
                        AND :ano_fim_doc_$sufixo
            ) AS docentes_com_producao

        FROM producoes p

        $joinAvaliacao

        WHERE
            p.ano BETWEEN
                :ano_inicio_$sufixo
                AND :ano_fim_$sufixo
    ");

    $stmt->execute($parametros);

    $resumo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total_producoes' => (int) ($resumo['total_producoes'] ?? 0),
        'producoes_avaliadas' =>
            (int) ($resumo['producoes_avaliadas'] ?? 0),
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
        'docentes_com_producao' =>
            (int) ($resumo['docentes_com_producao'] ?? 0),
        'capes_disponivel' => $quadrienioId !== null
    ];
}


function buscarProducoesPorAnoQuadrienio(
    PDO $pdo,
    array $periodo,
    string $sufixo
): array {
    $stmt = $pdo->prepare("
        SELECT
            ano,
            COUNT(DISTINCT id) AS quantidade
        FROM producoes
        WHERE
            ano BETWEEN
                :ano_inicio_$sufixo
                AND :ano_fim_$sufixo
        GROUP BY ano
        ORDER BY ano
    ");

    $stmt->execute([
        ':ano_inicio_' . $sufixo => (int) $periodo['ano_inicio'],
        ':ano_fim_' . $sufixo => (int) $periodo['ano_fim']
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function buscarTiposQuadrienioComparativo(
    PDO $pdo,
    array $periodo,
    string $sufixo
): array {
    $stmt = $pdo->prepare("
        SELECT
            tp.nome,
            COUNT(DISTINCT p.id) AS quantidade

        FROM producoes p

        INNER JOIN tipos_producao tp
            ON tp.id = p.tipo_producao_id

        WHERE
            p.ano BETWEEN
                :ano_inicio_$sufixo
                AND :ano_fim_$sufixo

        GROUP BY
            tp.id,
            tp.nome

        ORDER BY
            quantidade DESC,
            tp.nome
    ");

    $stmt->execute([
        ':ano_inicio_' . $sufixo => (int) $periodo['ano_inicio'],
        ':ano_fim_' . $sufixo => (int) $periodo['ano_fim']
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function buscarDocentesQuadrienioComparativo(
    PDO $pdo,
    array $periodo,
    string $sufixo
): array {
    $stmt = $pdo->prepare("
        SELECT
            d.id,
            d.nome,
            COUNT(DISTINCT p.id) AS producoes

        FROM docentes d

        LEFT JOIN producoes_docentes pd
            ON pd.docente_id = d.id

        LEFT JOIN producoes p
            ON p.id = pd.producao_id
            AND p.ano BETWEEN
                :ano_inicio_$sufixo
                AND :ano_fim_$sufixo

        WHERE d.ativo = 1

        GROUP BY
            d.id,
            d.nome

        ORDER BY
            producoes DESC,
            d.nome
    ");

    $stmt->execute([
        ':ano_inicio_' . $sufixo => (int) $periodo['ano_inicio'],
        ':ano_fim_' . $sufixo => (int) $periodo['ano_fim']
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function formatarNumeroQuadrienio(float $valor): string
{
    $casas = abs($valor - round($valor)) < 0.00001
        ? 0
        : 1;

    return number_format(
        $valor,
        $casas,
        ',',
        '.'
    );
}


function calcularVariacaoQuadrienio(
    float $valorA,
    float $valorB
): ?float {
    if (abs($valorA) < 0.00001) {
        return null;
    }

    return (($valorB - $valorA) / abs($valorA)) * 100;
}


function formatarVariacaoQuadrienio(?float $variacao): string
{
    if ($variacao === null) {
        return '—';
    }

    $sinal = $variacao > 0 ? '+' : '';

    return
        $sinal
        . number_format($variacao, 1, ',', '.')
        . '%';
}


function classeDiferencaQuadrienio(float $diferenca): string
{
    if ($diferenca > 0.00001) {
        return 'diferenca-positiva';
    }

    if ($diferenca < -0.00001) {
        return 'diferenca-negativa';
    }

    return 'diferenca-neutra';
}


// ========================================================
// DADOS DA COMPARAÇÃO
// ========================================================

$temComparacao = $periodoA !== null
    && $periodoB !== null
    && $periodoAChave !== $periodoBChave;

$resumoPeriodoA = [];
$resumoPeriodoB = [];
$producoesAnoA = [];
$producoesAnoB = [];
$tiposPeriodoA = [];
$tiposPeriodoB = [];
$docentesPeriodoA = [];
$docentesPeriodoB = [];

if ($temComparacao) {
    $resumoPeriodoA = buscarResumoQuadrienioComparativo(
        $pdo,
        $periodoA,
        'a'
    );

    $resumoPeriodoB = buscarResumoQuadrienioComparativo(
        $pdo,
        $periodoB,
        'b'
    );

    $producoesAnoA = buscarProducoesPorAnoQuadrienio(
        $pdo,
        $periodoA,
        'ano_a'
    );

    $producoesAnoB = buscarProducoesPorAnoQuadrienio(
        $pdo,
        $periodoB,
        'ano_b'
    );

    $tiposPeriodoA = buscarTiposQuadrienioComparativo(
        $pdo,
        $periodoA,
        'tipo_a'
    );

    $tiposPeriodoB = buscarTiposQuadrienioComparativo(
        $pdo,
        $periodoB,
        'tipo_b'
    );

    $docentesPeriodoA = buscarDocentesQuadrienioComparativo(
        $pdo,
        $periodoA,
        'docente_a'
    );

    $docentesPeriodoB = buscarDocentesQuadrienioComparativo(
        $pdo,
        $periodoB,
        'docente_b'
    );
}


// ========================================================
// NORMALIZAÇÃO DOS GRÁFICOS
// ========================================================

$labelsPosicaoAno = ['1º ano', '2º ano', '3º ano', '4º ano'];
$dadosAnuaisA = [0, 0, 0, 0];
$dadosAnuaisB = [0, 0, 0, 0];

if ($temComparacao) {
    foreach ($producoesAnoA as $registro) {
        $posicao =
            (int) $registro['ano']
            - (int) $periodoA['ano_inicio'];

        if ($posicao >= 0 && $posicao <= 3) {
            $dadosAnuaisA[$posicao] =
                (int) $registro['quantidade'];
        }
    }

    foreach ($producoesAnoB as $registro) {
        $posicao =
            (int) $registro['ano']
            - (int) $periodoB['ano_inicio'];

        if ($posicao >= 0 && $posicao <= 3) {
            $dadosAnuaisB[$posicao] =
                (int) $registro['quantidade'];
        }
    }
}

$mapaTiposA = [];
foreach ($tiposPeriodoA as $registro) {
    $mapaTiposA[(string) $registro['nome']] =
        (int) $registro['quantidade'];
}

$mapaTiposB = [];
foreach ($tiposPeriodoB as $registro) {
    $mapaTiposB[(string) $registro['nome']] =
        (int) $registro['quantidade'];
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
    static function (string $tipoA, string $tipoB) use (
        $mapaTiposA,
        $mapaTiposB
    ): int {
        $totalA =
            ($mapaTiposA[$tipoA] ?? 0)
            + ($mapaTiposB[$tipoA] ?? 0);

        $totalB =
            ($mapaTiposA[$tipoB] ?? 0)
            + ($mapaTiposB[$tipoB] ?? 0);

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

$mapaDocentesA = [];
foreach ($docentesPeriodoA as $registro) {
    $mapaDocentesA[(string) $registro['nome']] =
        (int) $registro['producoes'];
}

$mapaDocentesB = [];
foreach ($docentesPeriodoB as $registro) {
    $mapaDocentesB[(string) $registro['nome']] =
        (int) $registro['producoes'];
}

$labelsDocentes = array_values(
    array_unique(
        array_merge(
            array_keys($mapaDocentesA),
            array_keys($mapaDocentesB)
        )
    )
);

usort(
    $labelsDocentes,
    static function (string $nomeA, string $nomeB) use (
        $mapaDocentesA,
        $mapaDocentesB
    ): int {
        $totalA =
            ($mapaDocentesA[$nomeA] ?? 0)
            + ($mapaDocentesB[$nomeA] ?? 0);

        $totalB =
            ($mapaDocentesA[$nomeB] ?? 0)
            + ($mapaDocentesB[$nomeB] ?? 0);

        if ($totalA === $totalB) {
            return strcmp($nomeA, $nomeB);
        }

        return $totalB <=> $totalA;
    }
);

$dadosDocentesA = [];
$dadosDocentesB = [];

foreach ($labelsDocentes as $nomeDocente) {
    $dadosDocentesA[] = $mapaDocentesA[$nomeDocente] ?? 0;
    $dadosDocentesB[] = $mapaDocentesB[$nomeDocente] ?? 0;
}


// ========================================================
// MÉTRICAS DA TABELA
// ========================================================

$metricas = [
    [
        'label' => 'Produções',
        'chave' => 'total_producoes',
        'capes' => false
    ],
    [
        'label' => 'Produções avaliadas',
        'chave' => 'producoes_avaliadas',
        'capes' => true
    ],
    [
        'label' => 'Pontuação CAPES',
        'chave' => 'pontuacao',
        'capes' => true
    ],
    ['label' => 'MB', 'chave' => 'mb', 'capes' => true],
    ['label' => 'B', 'chave' => 'b', 'capes' => true],
    ['label' => 'R', 'chave' => 'r', 'capes' => true],
    ['label' => 'F', 'chave' => 'f', 'capes' => true],
    ['label' => 'NC', 'chave' => 'nc', 'capes' => true],
    [
        'label' => 'Com discentes',
        'chave' => 'producoes_com_discentes',
        'capes' => false
    ],
    [
        'label' => 'Internacionais',
        'chave' => 'producoes_internacionais',
        'capes' => false
    ],
    [
        'label' => 'Docentes com produção',
        'chave' => 'docentes_com_producao',
        'capes' => false
    ]
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
        Quadriênio × Quadriênio - PPGA Analytics
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
                Quadriênio × Quadriênio
                <?php if ($temComparacao): ?>
                    · <?= htmlspecialchars($periodoAChave) ?>
                    → <?= htmlspecialchars($periodoBChave) ?>
                <?php endif; ?>
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
                href="linha_linha.php"
                class="botao"
            >
                Linha × Linha
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
            id="formQuadrienioQuadrienio"
        >
            <div class="campo-filtro campo-quadrienio-comparativo">
                <label for="periodo_a">
                    Quadriênio A
                </label>

                <select
                    name="periodo_a"
                    id="periodo_a"
                    required
                >
                    <?php foreach ($periodos as $chave => $periodo): ?>
                        <option
                            value="<?= htmlspecialchars($chave) ?>"
                            <?= $periodoAChave === $chave
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= htmlspecialchars($chave) ?>
                            <?= $periodo['quadrienio_id'] !== null
                                ? ' · CAPES cadastrado'
                                : ''
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>


            <div class="campo-filtro campo-quadrienio-comparativo">
                <label for="periodo_b">
                    Quadriênio B
                </label>

                <select
                    name="periodo_b"
                    id="periodo_b"
                    required
                >
                    <?php foreach ($periodos as $chave => $periodo): ?>
                        <option
                            value="<?= htmlspecialchars($chave) ?>"
                            <?= $periodoBChave === $chave
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= htmlspecialchars($chave) ?>
                            <?= $periodo['quadrienio_id'] !== null
                                ? ' · CAPES cadastrado'
                                : ''
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>


            <button
                type="submit"
                class="botao"
            >
                Comparar
            </button>

            <a
                href="quadrienio_quadrienio.php"
                class="botao botao-secundario"
            >
                Limpar
            </a>
        </form>
    </section>


    <?php if (count($periodos) < 2): ?>
        <section class="painel aviso-comparativo">
            <strong>
                Ainda não existem períodos suficientes para comparação.
            </strong>

            <p>
                O sistema precisa de pelo menos dois blocos de quatro anos.
            </p>
        </section>

    <?php elseif ($temComparacao): ?>

        <?php
            $capesDisponivelA =
                (bool) $resumoPeriodoA['capes_disponivel'];

            $capesDisponivelB =
                (bool) $resumoPeriodoB['capes_disponivel'];
        ?>

        <?php if (!$capesDisponivelA || !$capesDisponivelB): ?>
            <section class="painel aviso-comparativo aviso-comparativo-atencao">
                <strong>
                    Comparação histórica parcial da CAPES
                </strong>

                <p>
                    Seu banco possui avaliação CAPES cadastrada apenas para os
                    períodos registrados na tabela <code>quadrienios</code>.
                    Para períodos sem cadastro, produção, discentes,
                    internacionalização e docentes são comparados normalmente,
                    mas pontuação e MB/B/R/F/NC aparecem como “—” para não
                    transformar ausência de histórico em nota zero.
                </p>
            </section>
        <?php endif; ?>


        <section class="comparativo-identidades">
            <article class="painel comparativo-docente-card">
                <span class="comparativo-etiqueta">
                    QUADRIÊNIO A
                </span>

                <strong class="comparativo-nome comparativo-quadrienio-numero">
                    <?= htmlspecialchars($periodoAChave) ?>
                </strong>

                <span class="status-quadrienio <?= $capesDisponivelA ? 'status-capes-ok' : 'status-capes-pendente' ?>">
                    <?= $capesDisponivelA
                        ? 'CAPES cadastrado'
                        : 'Sem avaliação CAPES histórica'
                    ?>
                </span>

                <div class="comparativo-resumo-card">
                    <div>
                        <span>Produções</span>
                        <strong>
                            <?= (int) $resumoPeriodoA['total_producoes'] ?>
                        </strong>
                    </div>

                    <div>
                        <span>Pontos CAPES</span>
                        <strong>
                            <?= $capesDisponivelA
                                ? formatarNumeroQuadrienio(
                                    (float) $resumoPeriodoA['pontuacao']
                                )
                                : '—'
                            ?>
                        </strong>
                    </div>
                </div>
            </article>


            <div class="comparativo-versus">
                ×
            </div>


            <article class="painel comparativo-docente-card">
                <span class="comparativo-etiqueta">
                    QUADRIÊNIO B
                </span>

                <strong class="comparativo-nome comparativo-quadrienio-numero">
                    <?= htmlspecialchars($periodoBChave) ?>
                </strong>

                <span class="status-quadrienio <?= $capesDisponivelB ? 'status-capes-ok' : 'status-capes-pendente' ?>">
                    <?= $capesDisponivelB
                        ? 'CAPES cadastrado'
                        : 'Sem avaliação CAPES histórica'
                    ?>
                </span>

                <div class="comparativo-resumo-card">
                    <div>
                        <span>Produções</span>
                        <strong>
                            <?= (int) $resumoPeriodoB['total_producoes'] ?>
                        </strong>
                    </div>

                    <div>
                        <span>Pontos CAPES</span>
                        <strong>
                            <?= $capesDisponivelB
                                ? formatarNumeroQuadrienio(
                                    (float) $resumoPeriodoB['pontuacao']
                                )
                                : '—'
                            ?>
                        </strong>
                    </div>
                </div>
            </article>
        </section>


        <section class="cards cards-ano-comparativo">
            <article class="card">
                <span class="card-titulo">
                    Diferença de produções
                </span>

                <?php
                    $difProducao =
                        (float) $resumoPeriodoB['total_producoes']
                        - (float) $resumoPeriodoA['total_producoes'];
                ?>

                <strong class="card-numero">
                    <?= $difProducao > 0 ? '+' : '' ?><?= formatarNumeroQuadrienio($difProducao) ?>
                </strong>

                <span class="card-descricao">
                    B em relação a A
                </span>
            </article>


            <article class="card">
                <span class="card-titulo">
                    Variação de produções
                </span>

                <strong class="card-numero">
                    <?= htmlspecialchars(
                        formatarVariacaoQuadrienio(
                            calcularVariacaoQuadrienio(
                                (float) $resumoPeriodoA['total_producoes'],
                                (float) $resumoPeriodoB['total_producoes']
                            )
                        )
                    ) ?>
                </strong>

                <span class="card-descricao">
                    Evolução entre quadriênios
                </span>
            </article>


            <article class="card">
                <span class="card-titulo">
                    Com discentes
                </span>

                <strong class="card-numero">
                    <?= (int) $resumoPeriodoB['producoes_com_discentes'] ?>
                </strong>

                <span class="card-descricao">
                    <?= htmlspecialchars($periodoBChave) ?>
                </span>
            </article>


            <article class="card">
                <span class="card-titulo">
                    Internacionais
                </span>

                <strong class="card-numero">
                    <?= (int) $resumoPeriodoB['producoes_internacionais'] ?>
                </strong>

                <span class="card-descricao">
                    <?= htmlspecialchars($periodoBChave) ?>
                </span>
            </article>
        </section>


        <section class="painel painel-comparacao">
            <div class="painel-cabecalho">
                <h2>
                    Quadriênio × quadriênio
                </h2>

                <p>
                    Indicadores institucionais e evolução histórica entre os dois períodos.
                </p>
            </div>

            <div class="tabela-container">
                <table class="tabela-comparativo tabela-ano-comparativo">
                    <thead>
                        <tr>
                            <th>Indicador</th>
                            <th><?= htmlspecialchars($periodoAChave) ?></th>
                            <th><?= htmlspecialchars($periodoBChave) ?></th>
                            <th>Diferença</th>
                            <th>Variação %</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($metricas as $metrica): ?>
                            <?php
                                $chave = $metrica['chave'];
                                $metricaCapes = (bool) $metrica['capes'];

                                $valorDisponivelA =
                                    !$metricaCapes || $capesDisponivelA;

                                $valorDisponivelB =
                                    !$metricaCapes || $capesDisponivelB;
                            ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?= htmlspecialchars($metrica['label']) ?>
                                    </strong>
                                </td>

                                <?php if ($valorDisponivelA): ?>
                                    <td>
                                        <?= formatarNumeroQuadrienio(
                                            (float) $resumoPeriodoA[$chave]
                                        ) ?>
                                    </td>
                                <?php else: ?>
                                    <td class="valor-indisponivel">—</td>
                                <?php endif; ?>

                                <?php if ($valorDisponivelB): ?>
                                    <td>
                                        <?= formatarNumeroQuadrienio(
                                            (float) $resumoPeriodoB[$chave]
                                        ) ?>
                                    </td>
                                <?php else: ?>
                                    <td class="valor-indisponivel">—</td>
                                <?php endif; ?>

                                <?php if ($valorDisponivelA && $valorDisponivelB): ?>
                                    <?php
                                        $valorA =
                                            (float) $resumoPeriodoA[$chave];

                                        $valorB =
                                            (float) $resumoPeriodoB[$chave];

                                        $diferenca = $valorB - $valorA;

                                        $variacao = calcularVariacaoQuadrienio(
                                            $valorA,
                                            $valorB
                                        );
                                    ?>

                                    <td>
                                        <span class="diferenca-comparativo <?= classeDiferencaQuadrienio($diferenca) ?>">
                                            <?= $diferenca > 0 ? '+' : '' ?><?= formatarNumeroQuadrienio($diferenca) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            formatarVariacaoQuadrienio(
                                                $variacao
                                            )
                                        ) ?>
                                    </td>
                                <?php else: ?>
                                    <td class="valor-indisponivel">—</td>
                                    <td class="valor-indisponivel">—</td>
                                <?php endif; ?>
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
                        Evolução dentro do quadriênio
                    </h2>

                    <p>
                        Compara 1º, 2º, 3º e 4º ano de cada período.
                    </p>
                </div>

                <div class="grafico-container">
                    <canvas id="graficoEvolucaoQuadrienios"></canvas>
                </div>
            </article>


            <article class="painel">
                <div class="painel-cabecalho">
                    <h2>
                        Produções por docente
                    </h2>

                    <p>
                        Distribuição da produção dos docentes ativos em cada período.
                    </p>
                </div>

                <div class="grafico-container">
                    <canvas id="graficoDocentesQuadrienios"></canvas>
                </div>
            </article>
        </section>


        <section class="painel painel-tipos-comparativo">
            <div class="painel-cabecalho">
                <h2>
                    Produções por tipo
                </h2>

                <p>
                    Comparação da composição da produção científica entre os quadriênios.
                </p>
            </div>

            <div class="grafico-container grafico-tipos-comparativo">
                <canvas id="graficoTiposQuadrienios"></canvas>
            </div>
        </section>

    <?php endif; ?>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/chart.js"
></script>

<script>

<?php if ($temComparacao): ?>

const periodoA = <?= json_encode($periodoAChave) ?>;
const periodoB = <?= json_encode($periodoBChave) ?>;

const labelsPosicaoAno = <?= json_encode(
    $labelsPosicaoAno,
    JSON_UNESCAPED_UNICODE
) ?>;

const dadosAnuaisA = <?= json_encode($dadosAnuaisA) ?>;
const dadosAnuaisB = <?= json_encode($dadosAnuaisB) ?>;

const labelsTipos = <?= json_encode(
    $labelsTipos,
    JSON_UNESCAPED_UNICODE
) ?>;

const dadosTiposA = <?= json_encode($dadosTiposA) ?>;
const dadosTiposB = <?= json_encode($dadosTiposB) ?>;

const labelsDocentes = <?= json_encode(
    $labelsDocentes,
    JSON_UNESCAPED_UNICODE
) ?>;

const dadosDocentesA = <?= json_encode($dadosDocentesA) ?>;
const dadosDocentesB = <?= json_encode($dadosDocentesB) ?>;

const canvasEvolucao = document.getElementById(
    'graficoEvolucaoQuadrienios'
);

if (canvasEvolucao) {
    new Chart(canvasEvolucao, {
        type: 'line',
        data: {
            labels: labelsPosicaoAno,
            datasets: [
                {
                    label: periodoA,
                    data: dadosAnuaisA,
                    tension: 0.2
                },
                {
                    label: periodoB,
                    data: dadosAnuaisB,
                    tension: 0.2
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

const canvasDocentes = document.getElementById(
    'graficoDocentesQuadrienios'
);

if (canvasDocentes) {
    new Chart(canvasDocentes, {
        type: 'bar',
        data: {
            labels: labelsDocentes,
            datasets: [
                {
                    label: periodoA,
                    data: dadosDocentesA
                },
                {
                    label: periodoB,
                    data: dadosDocentesB
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });
}

const canvasTipos = document.getElementById(
    'graficoTiposQuadrienios'
);

if (canvasTipos) {
    new Chart(canvasTipos, {
        type: 'bar',
        data: {
            labels: labelsTipos,
            datasets: [
                {
                    label: periodoA,
                    data: dadosTiposA
                },
                {
                    label: periodoB,
                    data: dadosTiposB
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });
}

<?php endif; ?>

</script>

</body>
</html>