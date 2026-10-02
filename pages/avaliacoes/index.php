<?php

// ========================================================
// CONFIGURAÇÃO
// ========================================================

$raizProjeto = dirname(
    __DIR__,
    2
);

require_once
    $raizProjeto
    . DIRECTORY_SEPARATOR
    . 'config'
    . DIRECTORY_SEPARATOR
    . 'conexao.php';


// ========================================================
// FUNÇÃO PARA ESCAPAR HTML
// ========================================================

function e($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}


// ========================================================
// QUADRIÊNIO ATIVO
// ========================================================

$stmtQuadrienio = $pdo->query("
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

$quadrienio =
    $stmtQuadrienio->fetch(
        PDO::FETCH_ASSOC
    );

if (!$quadrienio) {

    die(
        'Nenhum quadriênio ativo foi encontrado.'
    );
}

$quadrienioId =
    (int) $quadrienio['id'];


// ========================================================
// FILTROS
// ========================================================

$docenteFiltro =
    isset($_GET['docente'])
        ? (int) $_GET['docente']
        : 0;

$anoFiltro =
    isset($_GET['ano'])
        ? (int) $_GET['ano']
        : 0;

$periodicoFiltro =
    isset($_GET['periodico'])
        ? (int) $_GET['periodico']
        : 0;

$classificacaoFiltro =
    isset($_GET['classificacao'])
        ? (int) $_GET['classificacao']
        : 0;

$statusFiltro =
    isset($_GET['status'])
        ? trim($_GET['status'])
        : '';


// ========================================================
// DOCENTES PARA O FILTRO
// ========================================================

$stmt = $pdo->query("
    SELECT
        id,
        nome
    FROM docentes
    WHERE ativo = 1
    ORDER BY nome
");

$docentes =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// ========================================================
// ANOS PARA O FILTRO
// ========================================================

$stmt = $pdo->query("
    SELECT DISTINCT
        ano
    FROM producoes
    WHERE ano IS NOT NULL
    ORDER BY ano DESC
");

$anos =
    $stmt->fetchAll(
        PDO::FETCH_COLUMN
    );


// ========================================================
// PERIÓDICOS PARA O FILTRO
// ========================================================

$stmt = $pdo->query("
    SELECT DISTINCT
        pe.id,
        pe.nome
    FROM periodicos pe
    INNER JOIN producoes p
        ON p.periodico_id = pe.id
    ORDER BY pe.nome
");

$periodicos =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// ========================================================
// CLASSIFICAÇÕES DO QUADRIÊNIO
// ========================================================

$stmtClassificacoes =
    $pdo->prepare("
        SELECT
            c.id,
            c.codigo,
            c.descricao,
            qc.pontos
        FROM quadrienios_classificacoes qc

        INNER JOIN classificacoes_capes c
            ON c.id = qc.classificacao_id

        WHERE qc.quadrienio_id = :quadrienio_id

        ORDER BY
            qc.pontos DESC,
            c.codigo
    ");

$stmtClassificacoes->execute([
    ':quadrienio_id' =>
        $quadrienioId
]);

$classificacoes =
    $stmtClassificacoes->fetchAll(
        PDO::FETCH_ASSOC
    );


// ========================================================
// CONSTRUÇÃO DOS FILTROS
// ========================================================

$where = [];

$parametros = [
    ':quadrienio_id' =>
        $quadrienioId
];


// DOCENTE

if ($docenteFiltro > 0) {

    $where[] = "
        EXISTS (
            SELECT 1
            FROM producoes_docentes pd_filtro
            WHERE
                pd_filtro.producao_id = p.id
                AND pd_filtro.docente_id = :docente_id
        )
    ";

    $parametros[
        ':docente_id'
    ] = $docenteFiltro;
}


// ANO

if ($anoFiltro > 0) {

    $where[] =
        "p.ano = :ano";

    $parametros[
        ':ano'
    ] = $anoFiltro;
}


// PERIÓDICO

if ($periodicoFiltro > 0) {

    $where[] =
        "p.periodico_id = :periodico_id";

    $parametros[
        ':periodico_id'
    ] = $periodicoFiltro;
}


// CLASSIFICAÇÃO

if ($classificacaoFiltro > 0) {

    $where[] =
        "ap.classificacao_id = :classificacao_id";

    $parametros[
        ':classificacao_id'
    ] = $classificacaoFiltro;
}


// STATUS

if ($statusFiltro === 'avaliado') {

    $where[] =
        "ap.id IS NOT NULL";
}

elseif (
    $statusFiltro === 'pendente'
) {

    $where[] =
        "ap.id IS NULL";
}


// ========================================================
// WHERE FINAL
// ========================================================

$sqlWhere = '';

if (count($where) > 0) {

    $sqlWhere =
        ' WHERE '
        . implode(
            ' AND ',
            $where
        );
}


// ========================================================
// RESUMO
// ========================================================

$sqlResumo = "
    SELECT

        COUNT(p.id)
            AS total,

        SUM(
            CASE
                WHEN ap.id IS NOT NULL
                THEN 1
                ELSE 0
            END
        )
            AS avaliadas,

        SUM(
            CASE
                WHEN ap.id IS NULL
                THEN 1
                ELSE 0
            END
        )
            AS pendentes,

        COALESCE(
            SUM(
                COALESCE(
                    ap.pontuacao_interna,
                    0
                )
            ),
            0
        )
            AS pontos

    FROM producoes p

    LEFT JOIN avaliacoes_producao ap
        ON ap.producao_id = p.id
        AND ap.quadrienio_id = :quadrienio_id

    $sqlWhere
";

$stmtResumo =
    $pdo->prepare(
        $sqlResumo
    );

$stmtResumo->execute(
    $parametros
);

$resumo =
    $stmtResumo->fetch(
        PDO::FETCH_ASSOC
    );

$totalProducoes =
    (int) (
        $resumo['total']
        ?? 0
    );

$totalAvaliadas =
    (int) (
        $resumo['avaliadas']
        ?? 0
    );

$totalPendentes =
    (int) (
        $resumo['pendentes']
        ?? 0
    );

$totalPontos =
    (float) (
        $resumo['pontos']
        ?? 0
    );


// ========================================================
// LISTAGEM DAS PRODUÇÕES
// ========================================================

$sqlProducoes = "
    SELECT

        p.id,
        p.titulo,
        p.ano,

        tp.nome
            AS tipo_producao,

        pe.nome
            AS periodico,

        ap.id
            AS avaliacao_id,

        ap.classificacao_id,

        ap.pontuacao_interna,

        ap.status
            AS status_avaliacao,

        ap.observacao,

        c.codigo
            AS classificacao,

        (
            SELECT
                GROUP_CONCAT(
                    DISTINCT d_autor.nome
                    ORDER BY d_autor.nome
                    SEPARATOR ', '
                )

            FROM producoes_docentes pd_autor

            INNER JOIN docentes d_autor
                ON d_autor.id =
                    pd_autor.docente_id

            WHERE
                pd_autor.producao_id =
                    p.id
        )
            AS docentes

    FROM producoes p

    LEFT JOIN tipos_producao tp
        ON tp.id =
            p.tipo_producao_id

    LEFT JOIN periodicos pe
        ON pe.id =
            p.periodico_id

    LEFT JOIN avaliacoes_producao ap
        ON ap.producao_id =
            p.id

        AND ap.quadrienio_id =
            :quadrienio_id

    LEFT JOIN classificacoes_capes c
        ON c.id =
            ap.classificacao_id

    $sqlWhere

    ORDER BY
        p.ano DESC,
        p.titulo ASC
";

$stmtProducoes =
    $pdo->prepare(
        $sqlProducoes
    );

$stmtProducoes->execute(
    $parametros
);

$producoes =
    $stmtProducoes->fetchAll(
        PDO::FETCH_ASSOC
    );

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
        Avaliações - PPGA Analytics
    </title>

    <link rel="stylesheet" href="../../assets/css/avaliacoes.css">

</head>

<body>

<div class="container">

    <!-- ======================================== -->
    <!-- CABEÇALHO -->
    <!-- ======================================== -->

    <div class="cabecalho">

        <div>

            <h1>
                Avaliação das Produções
            </h1>

            <p>
                Classificação e pontuação
                das produções científicas
            </p>

        </div>

        <div class="cabecalho-acoes">

            <div class="quadrienio">

                <strong>
                    Quadriênio ativo:
                </strong>

                <?= e(
                    $quadrienio[
                        'ano_inicio'
                    ]
                ) ?>

                -

                <?= e(
                    $quadrienio[
                        'ano_fim'
                    ]
                ) ?>

            </div>

            <a
                href="../dashboard/index.php"
                class="botao botao-secundario"
            >
                Voltar à Dashboard
            </a>

        </div>

    </div>


    <!-- ======================================== -->
    <!-- FILTROS -->
    <!-- ======================================== -->

    <div class="filtros">

        <form
            method="GET"
            action=""
        >

            <!-- DOCENTE -->

            <div class="campo">

                <label for="docente">
                    Docente
                </label>

                <select
                    name="docente"
                    id="docente"
                >

                    <option value="0">
                        Todos
                    </option>

                    <?php foreach (
                        $docentes
                        as $docente
                    ): ?>

                        <option

                            value="<?=
                                (int)
                                $docente['id']
                            ?>"

                            <?=
                                $docenteFiltro
                                ===
                                (int)
                                $docente['id']
                                    ? 'selected'
                                    : ''
                            ?>

                        >

                            <?= e(
                                $docente[
                                    'nome'
                                ]
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- ANO -->

            <div class="campo">

                <label for="ano">
                    Ano
                </label>

                <select
                    name="ano"
                    id="ano"
                >

                    <option value="0">
                        Todos
                    </option>

                    <?php foreach (
                        $anos
                        as $ano
                    ): ?>

                        <option

                            value="<?=
                                (int) $ano
                            ?>"

                            <?=
                                $anoFiltro
                                ===
                                (int) $ano
                                    ? 'selected'
                                    : ''
                            ?>

                        >

                            <?= (int) $ano ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- PERIÓDICO -->

            <div class="campo campo-periodico">

                <label for="periodico">
                    Periódico
                </label>

                <select
                    name="periodico"
                    id="periodico"
                >

                    <option value="0">
                        Todos
                    </option>

                    <?php foreach (
                        $periodicos
                        as $periodico
                    ): ?>

                        <option
                            value="<?= (int) $periodico['id'] ?>"
                            <?= $periodicoFiltro === (int) $periodico['id']
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= e($periodico['nome']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- CLASSIFICAÇÃO -->

            <div class="campo">

                <label for="classificacao">
                    Classificação
                </label>

                <select
                    name="classificacao"
                    id="classificacao"
                >

                    <option value="0">
                        Todas
                    </option>

                    <?php foreach (
                        $classificacoes
                        as $classificacao
                    ): ?>

                        <option

                            value="<?=
                                (int)
                                $classificacao['id']
                            ?>"

                            <?=
                                $classificacaoFiltro
                                ===
                                (int)
                                $classificacao['id']
                                    ? 'selected'
                                    : ''
                            ?>

                        >

                            <?= e(
                                $classificacao[
                                    'codigo'
                                ]
                            ) ?>

                            -

                            <?= number_format(
                                (float)
                                $classificacao[
                                    'pontos'
                                ],
                                0,
                                ',',
                                '.'
                            ) ?>

                            pts

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- STATUS -->

            <div class="campo">

                <label for="status">
                    Status
                </label>

                <select
                    name="status"
                    id="status"
                >

                    <option value="">
                        Todos
                    </option>

                    <option
                        value="avaliado"

                        <?=
                            $statusFiltro
                            === 'avaliado'
                                ? 'selected'
                                : ''
                        ?>
                    >
                        Avaliadas
                    </option>

                    <option
                        value="pendente"

                        <?=
                            $statusFiltro
                            === 'pendente'
                                ? 'selected'
                                : ''
                        ?>
                    >
                        Pendentes
                    </option>

                </select>

            </div>


            <!-- BOTÕES -->

            <div class="filtro-acoes">

                <button type="submit">
                    Filtrar
                </button>

                <a
                    href="index.php"
                    class="botao botao-secundario"
                >
                    Limpar
                </a>

            </div>

        </form>

    </div>


    <!-- ======================================== -->
    <!-- CARDS -->
    <!-- ======================================== -->

    <div class="cards">

        <div class="card">

            <span>
                Produções
            </span>

            <strong>
                <?= $totalProducoes ?>
            </strong>

        </div>


        <div class="card">

            <span>
                Avaliadas
            </span>

            <strong>
                <?= $totalAvaliadas ?>
            </strong>

        </div>


        <div class="card">

            <span>
                Pendentes
            </span>

            <strong>
                <?= $totalPendentes ?>
            </strong>

        </div>


        <div class="card">

            <span>
                Pontuação
            </span>

            <strong>

                <?= number_format(
                    $totalPontos,
                    0,
                    ',',
                    '.'
                ) ?>

                pts

            </strong>

        </div>

    </div>


    <!-- ======================================== -->
    <!-- TABELA -->
    <!-- ======================================== -->

    <div class="tabela-container">

        <table>

            <thead>

                <tr>

                    <th>
                        Produção
                    </th>

                    <th>
                        Docente
                    </th>

                    <th>
                        Tipo
                    </th>

                    <th>
                        Periódico
                    </th>

                    <th>
                        Ano
                    </th>

                    <th>
                        Classificação
                    </th>

                    <th>
                        Pontos
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Ação
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php if (
                count($producoes) === 0
            ): ?>

                <tr>

                    <td
                        colspan="9"
                        class="sem-dados"
                    >
                        Nenhuma produção encontrada.
                    </td>

                </tr>

            <?php else: ?>

                <?php foreach (
                    $producoes
                    as $producao
                ): ?>

                    <?php

                    $avaliada =
                        !empty(
                            $producao[
                                'avaliacao_id'
                            ]
                        );

                    ?>

                    <tr>

                        <td class="titulo">

                            <?= e(
                                $producao[
                                    'titulo'
                                ]
                            ) ?>

                        </td>


                        <td class="docentes">

                            <?= e(
                                $producao[
                                    'docentes'
                                ]
                                ??
                                'Não informado'
                            ) ?>

                        </td>


                        <td>

                            <?= e(
                                $producao[
                                    'tipo_producao'
                                ]
                                ??
                                'Não informado'
                            ) ?>

                        </td>


                        <td class="periodico-col">

                            <?= e(
                                $producao[
                                    'periodico'
                                ]
                                ??
                                'Não informado'
                            ) ?>

                        </td>


                        <td>

                            <?= e(
                                $producao[
                                    'ano'
                                ]
                            ) ?>

                        </td>


                        <td class="classificacao">

                            <?php if (
                                $avaliada
                            ): ?>

                                <?= e(
                                    $producao[
                                        'classificacao'
                                    ]
                                    ??
                                    'NC'
                                ) ?>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <td class="pontos">

                            <?php if (
                                $avaliada
                            ): ?>

                                <?= number_format(
                                    (float)
                                    $producao[
                                        'pontuacao_interna'
                                    ],
                                    0,
                                    ',',
                                    '.'
                                ) ?>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                $avaliada
                            ): ?>

                                <span
                                    class="status avaliado"
                                >
                                    <?= e(
                                        $producao[
                                            'status_avaliacao'
                                        ]
                                        ??
                                        'AVALIADO'
                                    ) ?>
                                </span>

                            <?php else: ?>

                                <span
                                    class="status pendente"
                                >
                                    PENDENTE
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <a

                                class="botao"

                                href="avaliar.php?producao_id=<?=
                                    (int)
                                    $producao['id']
                                ?>&quadrienio_id=<?=
                                    $quadrienioId
                                ?>"

                            >

                                <?=
                                    $avaliada
                                        ? 'Editar'
                                        : 'Avaliar'
                                ?>

                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>

</html>