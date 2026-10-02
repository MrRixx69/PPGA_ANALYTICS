<?php

$raizProjeto = dirname(__DIR__, 2);

require_once
    $raizProjeto
    . DIRECTORY_SEPARATOR
    . 'config'
    . DIRECTORY_SEPARATOR
    . 'conexao.php';


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

$anos = array_map(
    'intval',
    $stmtAnos->fetchAll(PDO::FETCH_COLUMN)
);


// ========================================================
// FILTROS
// ========================================================

$anoA = isset($_GET['ano_a'])
    ? (int) $_GET['ano_a']
    : 0;

$anoB = isset($_GET['ano_b'])
    ? (int) $_GET['ano_b']
    : 0;

if (!in_array($anoA, $anos, true)) {
    $anoA = $anos[1] ?? ($anos[0] ?? 0);
}

if (
    !in_array($anoB, $anos, true)
    || $anoB === $anoA
) {
    $anoB = $anos[0] ?? 0;

    if ($anoB === $anoA) {
        foreach ($anos as $anoDisponivel) {
            if ($anoDisponivel !== $anoA) {
                $anoB = $anoDisponivel;
                break;
            }
        }
    }
}


// ========================================================
// FUNÇÕES AUXILIARES
// ========================================================

function buscarResumoAnoComparativo(
    PDO $pdo,
    int $ano,
    ?array $quadrienioAtivo,
    string $sufixo
): array {
    if ($quadrienioAtivo) {
        $joinAvaliacao = "
            LEFT JOIN avaliacoes_producao ap
                ON ap.producao_id = p.id
                AND ap.quadrienio_id = :quadrienio_$sufixo
            LEFT JOIN classificacoes_capes c
                ON c.id = ap.classificacao_id
        ";

        $parametros = [
            ':ano_' . $sufixo => $ano,
            ':quadrienio_' . $sufixo =>
                (int) $quadrienioAtivo['id']
        ];
    }
    else {
        $joinAvaliacao = "
            LEFT JOIN avaliacoes_producao ap
                ON 1 = 0
            LEFT JOIN classificacoes_capes c
                ON 1 = 0
        ";

        $parametros = [
            ':ano_' . $sufixo => $ano
        ];
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
                WHERE p2.ano = :ano_docentes_$sufixo
            ) AS docentes_com_producao

        FROM producoes p

        $joinAvaliacao

        WHERE p.ano = :ano_$sufixo
    ");

    $parametros[':ano_docentes_' . $sufixo] = $ano;

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
            (int) ($resumo['docentes_com_producao'] ?? 0)
    ];
}


function buscarTiposPorAnoComparativo(
    PDO $pdo,
    int $ano,
    string $sufixo
): array {
    $stmt = $pdo->prepare("
        SELECT
            tp.nome,
            COUNT(DISTINCT p.id) AS quantidade

        FROM producoes p

        INNER JOIN tipos_producao tp
            ON tp.id = p.tipo_producao_id

        WHERE p.ano = :ano_$sufixo

        GROUP BY
            tp.id,
            tp.nome

        ORDER BY
            quantidade DESC,
            tp.nome
    ");

    $stmt->execute([
        ':ano_' . $sufixo => $ano
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function buscarClassificacoesPorAnoComparativo(
    PDO $pdo,
    int $ano,
    ?array $quadrienioAtivo,
    string $sufixo
): array {
    if (!$quadrienioAtivo) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT
            c.codigo,
            COUNT(DISTINCT p.id) AS quantidade

        FROM classificacoes_capes c

        LEFT JOIN avaliacoes_producao ap
            ON ap.classificacao_id = c.id
            AND ap.quadrienio_id = :quadrienio_$sufixo

        LEFT JOIN producoes p
            ON p.id = ap.producao_id
            AND p.ano = :ano_$sufixo

        GROUP BY
            c.id,
            c.codigo

        ORDER BY
            FIELD(c.codigo, 'MB', 'B', 'R', 'F', 'NC'),
            c.codigo
    ");

    $stmt->execute([
        ':quadrienio_' . $sufixo =>
            (int) $quadrienioAtivo['id'],
        ':ano_' . $sufixo => $ano
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function buscarDocentesPorAnoComparativo(
    PDO $pdo,
    int $ano,
    ?array $quadrienioAtivo,
    string $sufixo
): array {
    if ($quadrienioAtivo) {
        $joinAvaliacao = "
            LEFT JOIN avaliacoes_producao ap
                ON ap.producao_id = p.id
                AND ap.quadrienio_id = :quadrienio_$sufixo
        ";

        $parametros = [
            ':ano_' . $sufixo => $ano,
            ':quadrienio_' . $sufixo =>
                (int) $quadrienioAtivo['id']
        ];
    }
    else {
        $joinAvaliacao = "
            LEFT JOIN avaliacoes_producao ap
                ON 1 = 0
        ";

        $parametros = [
            ':ano_' . $sufixo => $ano
        ];
    }

    $stmt = $pdo->prepare("
        SELECT
            d.id,
            d.nome,
            COUNT(DISTINCT p.id) AS producoes,
            COALESCE(SUM(ap.pontuacao_interna), 0) AS pontos

        FROM docentes d

        LEFT JOIN producoes_docentes pd
            ON pd.docente_id = d.id

        LEFT JOIN producoes p
            ON p.id = pd.producao_id
            AND p.ano = :ano_$sufixo

        $joinAvaliacao

        WHERE d.ativo = 1

        GROUP BY
            d.id,
            d.nome

        ORDER BY
            producoes DESC,
            pontos DESC,
            d.nome
    ");

    $stmt->execute($parametros);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function formatarNumeroAnoComparativo(float $valor): string
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


function calcularVariacaoAnoComparativo(
    float $valorA,
    float $valorB
): ?float {
    if (abs($valorA) < 0.00001) {
        return null;
    }

    return (($valorB - $valorA) / abs($valorA)) * 100;
}


function formatarVariacaoAnoComparativo(?float $variacao): string
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


function classeDiferencaAnoComparativo(float $diferenca): string
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

$resumoAnoA = buscarResumoAnoComparativo(
    $pdo,
    $anoA,
    $quadrienioAtivo ?: null,
    'a'
);

$resumoAnoB = buscarResumoAnoComparativo(
    $pdo,
    $anoB,
    $quadrienioAtivo ?: null,
    'b'
);

$tiposAnoA = buscarTiposPorAnoComparativo(
    $pdo,
    $anoA,
    'tipo_a'
);

$tiposAnoB = buscarTiposPorAnoComparativo(
    $pdo,
    $anoB,
    'tipo_b'
);

$classificacoesAnoA = buscarClassificacoesPorAnoComparativo(
    $pdo,
    $anoA,
    $quadrienioAtivo ?: null,
    'classe_a'
);

$classificacoesAnoB = buscarClassificacoesPorAnoComparativo(
    $pdo,
    $anoB,
    $quadrienioAtivo ?: null,
    'classe_b'
);

$docentesAnoA = buscarDocentesPorAnoComparativo(
    $pdo,
    $anoA,
    $quadrienioAtivo ?: null,
    'docente_a'
);

$docentesAnoB = buscarDocentesPorAnoComparativo(
    $pdo,
    $anoB,
    $quadrienioAtivo ?: null,
    'docente_b'
);


// ========================================================
// NORMALIZAÇÃO DOS GRÁFICOS
// ========================================================

$mapaTipoA = [];
foreach ($tiposAnoA as $registro) {
    $mapaTipoA[(string) $registro['nome']] =
        (int) $registro['quantidade'];
}

$mapaTipoB = [];
foreach ($tiposAnoB as $registro) {
    $mapaTipoB[(string) $registro['nome']] =
        (int) $registro['quantidade'];
}

$labelsTipos = array_values(
    array_unique(
        array_merge(
            array_keys($mapaTipoA),
            array_keys($mapaTipoB)
        )
    )
);

usort(
    $labelsTipos,
    static function (string $tipoA, string $tipoB) use (
        $mapaTipoA,
        $mapaTipoB
    ): int {
        $totalA =
            ($mapaTipoA[$tipoA] ?? 0)
            + ($mapaTipoB[$tipoA] ?? 0);

        $totalB =
            ($mapaTipoA[$tipoB] ?? 0)
            + ($mapaTipoB[$tipoB] ?? 0);

        if ($totalA === $totalB) {
            return strcmp($tipoA, $tipoB);
        }

        return $totalB <=> $totalA;
    }
);

$dadosTiposA = [];
$dadosTiposB = [];

foreach ($labelsTipos as $tipo) {
    $dadosTiposA[] = $mapaTipoA[$tipo] ?? 0;
    $dadosTiposB[] = $mapaTipoB[$tipo] ?? 0;
}

$ordemClassificacoes = ['MB', 'B', 'R', 'F', 'NC'];

$mapaClassificacaoA = array_fill_keys(
    $ordemClassificacoes,
    0
);

foreach ($classificacoesAnoA as $registro) {
    $codigo = (string) $registro['codigo'];

    if (array_key_exists($codigo, $mapaClassificacaoA)) {
        $mapaClassificacaoA[$codigo] =
            (int) $registro['quantidade'];
    }
}

$mapaClassificacaoB = array_fill_keys(
    $ordemClassificacoes,
    0
);

foreach ($classificacoesAnoB as $registro) {
    $codigo = (string) $registro['codigo'];

    if (array_key_exists($codigo, $mapaClassificacaoB)) {
        $mapaClassificacaoB[$codigo] =
            (int) $registro['quantidade'];
    }
}

$dadosClassificacoesA = array_values($mapaClassificacaoA);
$dadosClassificacoesB = array_values($mapaClassificacaoB);

$mapaDocentesA = [];
foreach ($docentesAnoA as $registro) {
    $mapaDocentesA[(string) $registro['nome']] = [
        'producoes' => (int) $registro['producoes'],
        'pontos' => (float) $registro['pontos']
    ];
}

$mapaDocentesB = [];
foreach ($docentesAnoB as $registro) {
    $mapaDocentesB[(string) $registro['nome']] = [
        'producoes' => (int) $registro['producoes'],
        'pontos' => (float) $registro['pontos']
    ];
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
            ($mapaDocentesA[$nomeA]['producoes'] ?? 0)
            + ($mapaDocentesB[$nomeA]['producoes'] ?? 0);

        $totalB =
            ($mapaDocentesA[$nomeB]['producoes'] ?? 0)
            + ($mapaDocentesB[$nomeB]['producoes'] ?? 0);

        if ($totalA === $totalB) {
            return strcmp($nomeA, $nomeB);
        }

        return $totalB <=> $totalA;
    }
);

$dadosDocentesA = [];
$dadosDocentesB = [];

foreach ($labelsDocentes as $nomeDocente) {
    $dadosDocentesA[] =
        $mapaDocentesA[$nomeDocente]['producoes'] ?? 0;

    $dadosDocentesB[] =
        $mapaDocentesB[$nomeDocente]['producoes'] ?? 0;
}


// ========================================================
// MÉTRICAS DA TABELA
// ========================================================

$metricas = [
    ['label' => 'Produções', 'chave' => 'total_producoes'],
    ['label' => 'Produções avaliadas', 'chave' => 'producoes_avaliadas'],
    ['label' => 'Pontuação CAPES', 'chave' => 'pontuacao'],
    ['label' => 'MB', 'chave' => 'mb'],
    ['label' => 'B', 'chave' => 'b'],
    ['label' => 'R', 'chave' => 'r'],
    ['label' => 'F', 'chave' => 'f'],
    ['label' => 'NC', 'chave' => 'nc'],
    ['label' => 'Com discentes', 'chave' => 'producoes_com_discentes'],
    ['label' => 'Internacionais', 'chave' => 'producoes_internacionais'],
    ['label' => 'Docentes com produção', 'chave' => 'docentes_com_producao']
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
        Ano × Ano - PPGA Analytics
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
                Ano × Ano · <?= (int) $anoA ?> → <?= (int) $anoB ?>
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
                href="quadrienio_quadrienio.php"
                class="botao"
            >
                Quadriênio × Quadriênio
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
            id="formAnoAno"
        >
            <div class="campo-filtro campo-ano-comparativo">
                <label for="ano_a">
                    Ano A
                </label>

                <select
                    name="ano_a"
                    id="ano_a"
                    required
                >
                    <?php foreach ($anos as $ano): ?>
                        <option
                            value="<?= (int) $ano ?>"
                            <?= $anoA === (int) $ano
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= (int) $ano ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>


            <div class="campo-filtro campo-ano-comparativo">
                <label for="ano_b">
                    Ano B
                </label>

                <select
                    name="ano_b"
                    id="ano_b"
                    required
                >
                    <?php foreach ($anos as $ano): ?>
                        <option
                            value="<?= (int) $ano ?>"
                            <?= $anoB === (int) $ano
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= (int) $ano ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>


            <div class="acoes-filtro">
                <button
                    type="submit"
                    class="botao"
                >
                    Comparar
                </button>

                <a
                    href="ano_ano.php"
                    class="botao botao-secundario"
                >
                    Limpar
                </a>
            </div>
        </form>
    </section>


    <?php if (count($anos) < 2): ?>
        <section class="painel">
            <div class="sem-dados">
                É necessário possuir produções em pelo menos dois anos diferentes para gerar este comparativo.
            </div>
        </section>
    <?php else: ?>

        <section class="comparativo-identidades">
            <article class="card comparativo-docente-card comparativo-ano-card">
                <span class="comparativo-etiqueta">
                    Ano A
                </span>

                <strong class="comparativo-nome comparativo-ano-numero">
                    <?= (int) $anoA ?>
                </strong>

                <div class="comparativo-resumo-card">
                    <div>
                        <span>Produções</span>
                        <strong><?= $resumoAnoA['total_producoes'] ?></strong>
                    </div>

                    <div>
                        <span>Pontos CAPES</span>
                        <strong>
                            <?= formatarNumeroAnoComparativo(
                                $resumoAnoA['pontuacao']
                            ) ?>
                        </strong>
                    </div>
                </div>
            </article>


            <div class="comparativo-versus">
                ×
            </div>


            <article class="card comparativo-docente-card comparativo-ano-card">
                <span class="comparativo-etiqueta">
                    Ano B
                </span>

                <strong class="comparativo-nome comparativo-ano-numero">
                    <?= (int) $anoB ?>
                </strong>

                <div class="comparativo-resumo-card">
                    <div>
                        <span>Produções</span>
                        <strong><?= $resumoAnoB['total_producoes'] ?></strong>
                    </div>

                    <div>
                        <span>Pontos CAPES</span>
                        <strong>
                            <?= formatarNumeroAnoComparativo(
                                $resumoAnoB['pontuacao']
                            ) ?>
                        </strong>
                    </div>
                </div>
            </article>
        </section>


        <section class="cards cards-ano-comparativo">
            <?php
                $cardsVariacao = [
                    [
                        'titulo' => 'Variação de produções',
                        'chave' => 'total_producoes',
                        'descricao' => 'Quantidade total de produções'
                    ],
                    [
                        'titulo' => 'Variação de pontuação',
                        'chave' => 'pontuacao',
                        'descricao' => 'Pontuação CAPES'
                    ],
                    [
                        'titulo' => 'Produções com discentes',
                        'chave' => 'producoes_com_discentes',
                        'descricao' => 'Participação discente'
                    ],
                    [
                        'titulo' => 'Docentes com produção',
                        'chave' => 'docentes_com_producao',
                        'descricao' => 'Docentes ativos com produção no ano'
                    ]
                ];
            ?>

            <?php foreach ($cardsVariacao as $card): ?>
                <?php
                    $chave = $card['chave'];
                    $valorA = (float) $resumoAnoA[$chave];
                    $valorB = (float) $resumoAnoB[$chave];
                    $variacao = calcularVariacaoAnoComparativo(
                        $valorA,
                        $valorB
                    );
                ?>

                <article class="card">
                    <span class="card-titulo">
                        <?= htmlspecialchars($card['titulo']) ?>
                    </span>

                    <strong class="card-numero">
                        <?= htmlspecialchars(
                            formatarVariacaoAnoComparativo($variacao)
                        ) ?>
                    </strong>

                    <span class="card-descricao">
                        <?= htmlspecialchars($card['descricao']) ?>
                    </span>
                </article>
            <?php endforeach; ?>
        </section>


        <section class="painel painel-comparacao">
            <div class="painel-cabecalho">
                <h2>
                    <?= (int) $anoA ?> × <?= (int) $anoB ?>
                </h2>

                <p>
                    A diferença considera <?= (int) $anoB ?> menos <?= (int) $anoA ?>. Pontuação e classificações usam o quadriênio CAPES ativo.
                </p>
            </div>

            <div class="tabela-container">
                <table class="tabela-comparativo tabela-ano-comparativo">
                    <thead>
                        <tr>
                            <th>Indicador</th>
                            <th><?= (int) $anoA ?></th>
                            <th><?= (int) $anoB ?></th>
                            <th>Diferença</th>
                            <th>Variação %</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($metricas as $metrica): ?>
                            <?php
                                $chave = $metrica['chave'];
                                $valorA = (float) $resumoAnoA[$chave];
                                $valorB = (float) $resumoAnoB[$chave];
                                $diferenca = $valorB - $valorA;
                                $variacao = calcularVariacaoAnoComparativo(
                                    $valorA,
                                    $valorB
                                );
                            ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?= htmlspecialchars($metrica['label']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= formatarNumeroAnoComparativo($valorA) ?>
                                </td>

                                <td>
                                    <?= formatarNumeroAnoComparativo($valorB) ?>
                                </td>

                                <td>
                                    <span class="diferenca-comparativo <?= classeDiferencaAnoComparativo($diferenca) ?>">
                                        <?= $diferenca > 0 ? '+' : '' ?><?= formatarNumeroAnoComparativo($diferenca) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        formatarVariacaoAnoComparativo(
                                            $variacao
                                        )
                                    ) ?>
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
                        Classificação CAPES
                    </h2>

                    <p>
                        Distribuição MB/B/R/F/NC entre os dois anos selecionados.
                    </p>
                </div>

                <div class="grafico-container">
                    <canvas id="graficoClassificacoesAno"></canvas>
                </div>
            </article>


            <article class="painel">
                <div class="painel-cabecalho">
                    <h2>
                        Produções por docente
                    </h2>

                    <p>
                        Quantidade de produções dos docentes ativos em cada ano.
                    </p>
                </div>

                <div class="grafico-container">
                    <canvas id="graficoDocentesAno"></canvas>
                </div>
            </article>
        </section>


        <section class="painel painel-tipos-comparativo">
            <div class="painel-cabecalho">
                <h2>
                    Produções por tipo
                </h2>

                <p>
                    Comparação da distribuição das produções por categoria.
                </p>
            </div>

            <div class="grafico-container grafico-tipos-comparativo">
                <canvas id="graficoTiposAno"></canvas>
            </div>
        </section>

    <?php endif; ?>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/chart.js"
></script>

<script>

<?php if (count($anos) >= 2): ?>

const anoA = <?= json_encode((string) $anoA) ?>;
const anoB = <?= json_encode((string) $anoB) ?>;

const labelsTipos = <?= json_encode(
    $labelsTipos,
    JSON_UNESCAPED_UNICODE
) ?>;

const tiposAnoA = <?= json_encode($dadosTiposA) ?>;
const tiposAnoB = <?= json_encode($dadosTiposB) ?>;

const labelsClassificacoes = <?= json_encode(
    $ordemClassificacoes,
    JSON_UNESCAPED_UNICODE
) ?>;

const classificacoesAnoA = <?= json_encode(
    $dadosClassificacoesA
) ?>;

const classificacoesAnoB = <?= json_encode(
    $dadosClassificacoesB
) ?>;

const labelsDocentes = <?= json_encode(
    $labelsDocentes,
    JSON_UNESCAPED_UNICODE
) ?>;

const docentesAnoA = <?= json_encode($dadosDocentesA) ?>;
const docentesAnoB = <?= json_encode($dadosDocentesB) ?>;

const canvasClassificacoes = document.getElementById(
    'graficoClassificacoesAno'
);

if (canvasClassificacoes) {
    new Chart(canvasClassificacoes, {
        type: 'bar',
        data: {
            labels: labelsClassificacoes,
            datasets: [
                {
                    label: anoA,
                    data: classificacoesAnoA
                },
                {
                    label: anoB,
                    data: classificacoesAnoB
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
    'graficoDocentesAno'
);

if (canvasDocentes) {
    new Chart(canvasDocentes, {
        type: 'bar',
        data: {
            labels: labelsDocentes,
            datasets: [
                {
                    label: anoA,
                    data: docentesAnoA
                },
                {
                    label: anoB,
                    data: docentesAnoB
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
    'graficoTiposAno'
);

if (canvasTipos) {
    new Chart(canvasTipos, {
        type: 'bar',
        data: {
            labels: labelsTipos,
            datasets: [
                {
                    label: anoA,
                    data: tiposAnoA
                },
                {
                    label: anoB,
                    data: tiposAnoB
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