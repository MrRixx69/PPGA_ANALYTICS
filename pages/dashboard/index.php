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
// FILTROS
// ========================================================

$docenteFiltro = isset($_GET['docente'])
    ? (int) $_GET['docente']
    : 0;

$tipoFiltro = isset($_GET['tipo'])
    ? (int) $_GET['tipo']
    : 0;

$periodoFiltro = isset($_GET['periodo'])
    ? trim((string) $_GET['periodo'])
    : '';

// Compatibilidade com URLs antigas que ainda usam ?ano=YYYY.
if (
    $periodoFiltro === ''
    && isset($_GET['ano'])
    && (int) $_GET['ano'] > 0
) {
    $periodoFiltro =
        (string) (int) $_GET['ano'];
}

$anoInicioPersonalizado = isset($_GET['ano_inicio'])
    ? (int) $_GET['ano_inicio']
    : 0;

$anoFimPersonalizado = isset($_GET['ano_fim'])
    ? (int) $_GET['ano_fim']
    : 0;

$classificacaoFiltro = isset($_GET['classificacao'])
    ? (int) $_GET['classificacao']
    : 0;

$comDiscenteFiltro = isset($_GET['com_discente'])
    ? trim((string) $_GET['com_discente'])
    : '';

if (!in_array(
    $comDiscenteFiltro,
    ['', 'sim', 'nao'],
    true
)) {
    $comDiscenteFiltro = '';
}

$linhaFiltro = isset($_GET['linha'])
    ? (int) $_GET['linha']
    : 0;

$projetoFiltro = isset($_GET['projeto'])
    ? (int) $_GET['projeto']
    : 0;

$periodicoFiltro = isset($_GET['periodico'])
    ? (int) $_GET['periodico']
    : 0;

$indexadorFiltro = isset($_GET['indexador'])
    ? (int) $_GET['indexador']
    : 0;

$internacionalizacaoFiltro = isset($_GET['internacionalizacao'])
    ? trim((string) $_GET['internacionalizacao'])
    : '';

if (!in_array(
    $internacionalizacaoFiltro,
    ['', 'sim', 'nao', 'coautoria'],
    true
)) {
    $internacionalizacaoFiltro = '';
}

$anoFiltro = 0;
$anoInicioFiltro = 0;
$anoFimFiltro = 0;


// ========================================================
// OPÇÕES DOS FILTROS
// ========================================================

// DOCENTES

$stmt = $pdo->query("
    SELECT
        id,
        nome
    FROM docentes
    ORDER BY nome
");

$docentes =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// ANOS

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


// TIPOS DE PRODUÇÃO

$stmt = $pdo->query("
    SELECT
        id,
        nome
    FROM tipos_producao
    ORDER BY nome
");

$tipos =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// LINHAS DE PESQUISA

$stmt = $pdo->query("
    SELECT
        id,
        nome
    FROM linhas_pesquisa
    ORDER BY nome
");

$linhasPesquisa =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// PROJETOS

$stmt = $pdo->query("
    SELECT
        id,
        nome
    FROM projetos
    ORDER BY nome
");

$projetosFiltro =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// PERIÓDICOS

$stmt = $pdo->query("
    SELECT
        id,
        nome
    FROM periodicos
    ORDER BY nome
");

$periodicosFiltro =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// INDEXADORES

$stmt = $pdo->query("
    SELECT
        id,
        nome
    FROM indexadores
    ORDER BY nome
");

$indexadoresFiltro =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// ========================================================
// QUADRIÊNIO ATIVO
// ========================================================

$stmt = $pdo->query("
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

$quadrienioAtivo =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


// ========================================================
// CLASSIFICAÇÕES DISPONÍVEIS NO QUADRIÊNIO
// ========================================================

$classificacoesFiltro = [];

if ($quadrienioAtivo) {

    $stmtClassificacoesFiltro =
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
                c.codigo ASC
        ");

    $stmtClassificacoesFiltro->execute([
        ':quadrienio_id' =>
            (int) $quadrienioAtivo['id']
    ]);

    $classificacoesFiltro =
        $stmtClassificacoesFiltro->fetchAll(
            PDO::FETCH_ASSOC
        );
}


// ========================================================
// NORMALIZAÇÃO DO FILTRO DE PERÍODO
// ========================================================

$anoAtual = (int) date('Y');

if (
    $periodoFiltro !== ''
    && ctype_digit($periodoFiltro)
) {

    $anoFiltro =
        (int) $periodoFiltro;
}
elseif ($periodoFiltro === 'ultimos_2') {

    $anoInicioFiltro =
        $anoAtual - 1;

    $anoFimFiltro =
        $anoAtual;
}
elseif ($periodoFiltro === 'ultimos_3') {

    $anoInicioFiltro =
        $anoAtual - 2;

    $anoFimFiltro =
        $anoAtual;
}
elseif ($periodoFiltro === 'ultimos_4') {

    $anoInicioFiltro =
        $anoAtual - 3;

    $anoFimFiltro =
        $anoAtual;
}
elseif (
    $periodoFiltro === 'quadrienio'
    && $quadrienioAtivo
) {

    $anoInicioFiltro =
        (int) $quadrienioAtivo['ano_inicio'];

    $anoFimFiltro =
        (int) $quadrienioAtivo['ano_fim'];
}
elseif ($periodoFiltro === 'personalizado') {

    $anoInicioFiltro =
        $anoInicioPersonalizado;

    $anoFimFiltro =
        $anoFimPersonalizado;

    if (
        $anoInicioFiltro > 0
        && $anoFimFiltro > 0
        && $anoInicioFiltro > $anoFimFiltro
    ) {

        [
            $anoInicioFiltro,
            $anoFimFiltro
        ] = [
            $anoFimFiltro,
            $anoInicioFiltro
        ];
    }
}


// ========================================================
// WHERE DINÂMICO DAS PRODUÇÕES
// ========================================================

$whereProducoes = [];
$parametrosProducoes = [];

if ($docenteFiltro > 0) {

    $whereProducoes[] = "
        EXISTS (
            SELECT 1
            FROM producoes_docentes pd_filtro
            WHERE
                pd_filtro.producao_id = p.id
                AND pd_filtro.docente_id = :docente
        )
    ";

    $parametrosProducoes[
        ':docente'
    ] = $docenteFiltro;
}


if ($anoFiltro > 0) {

    $whereProducoes[] =
        "p.ano = :ano";

    $parametrosProducoes[
        ':ano'
    ] = $anoFiltro;
}
elseif (
    $anoInicioFiltro > 0
    || $anoFimFiltro > 0
) {

    if ($anoInicioFiltro > 0) {

        $whereProducoes[] =
            "p.ano >= :ano_inicio";

        $parametrosProducoes[
            ':ano_inicio'
        ] = $anoInicioFiltro;
    }

    if ($anoFimFiltro > 0) {

        $whereProducoes[] =
            "p.ano <= :ano_fim";

        $parametrosProducoes[
            ':ano_fim'
        ] = $anoFimFiltro;
    }
}


if ($tipoFiltro > 0) {

    $whereProducoes[] =
        "p.tipo_producao_id = :tipo";

    $parametrosProducoes[
        ':tipo'
    ] = $tipoFiltro;
}


if (
    $classificacaoFiltro > 0
    && $quadrienioAtivo
) {

    $whereProducoes[] = "
        EXISTS (
            SELECT 1
            FROM avaliacoes_producao ap_filtro_classificacao
            WHERE
                ap_filtro_classificacao.producao_id = p.id
                AND ap_filtro_classificacao.quadrienio_id =
                    :quadrienio_classificacao_producoes
                AND ap_filtro_classificacao.classificacao_id =
                    :classificacao_producoes
        )
    ";

    $parametrosProducoes[
        ':quadrienio_classificacao_producoes'
    ] = (int) $quadrienioAtivo['id'];

    $parametrosProducoes[
        ':classificacao_producoes'
    ] = $classificacaoFiltro;
}


if ($comDiscenteFiltro === 'sim') {

    $whereProducoes[] = "
        EXISTS (
            SELECT 1
            FROM producoes_discentes pd_disc_filtro
            WHERE pd_disc_filtro.producao_id = p.id
        )
    ";
}
elseif ($comDiscenteFiltro === 'nao') {

    $whereProducoes[] = "
        NOT EXISTS (
            SELECT 1
            FROM producoes_discentes pd_disc_filtro
            WHERE pd_disc_filtro.producao_id = p.id
        )
    ";
}


if ($linhaFiltro > 0) {

    $whereProducoes[] = "
        EXISTS (
            SELECT 1
            FROM producoes_linhas pl_filtro
            WHERE
                pl_filtro.producao_id = p.id
                AND pl_filtro.linha_id = :linha_producoes
        )
    ";

    $parametrosProducoes[':linha_producoes'] =
        $linhaFiltro;
}


if ($projetoFiltro > 0) {

    $whereProducoes[] = "
        EXISTS (
            SELECT 1
            FROM producoes_projetos pp_filtro
            WHERE
                pp_filtro.producao_id = p.id
                AND pp_filtro.projeto_id = :projeto_producoes
        )
    ";

    $parametrosProducoes[':projeto_producoes'] =
        $projetoFiltro;
}


if ($periodicoFiltro > 0) {

    $whereProducoes[] =
        "p.periodico_id = :periodico_producoes";

    $parametrosProducoes[':periodico_producoes'] =
        $periodicoFiltro;
}


if ($indexadorFiltro > 0) {

    $whereProducoes[] = "
        EXISTS (
            SELECT 1
            FROM periodicos_indexadores pi_filtro
            WHERE
                pi_filtro.periodico_id = p.periodico_id
                AND pi_filtro.indexador_id = :indexador_producoes
        )
    ";

    $parametrosProducoes[':indexador_producoes'] =
        $indexadorFiltro;
}


if ($internacionalizacaoFiltro === 'sim') {

    $whereProducoes[] =
        "(p.internacional = 1 OR p.coautoria_internacional = 1)";
}
elseif ($internacionalizacaoFiltro === 'nao') {

    $whereProducoes[] =
        "(p.internacional = 0 AND p.coautoria_internacional = 0)";
}
elseif ($internacionalizacaoFiltro === 'coautoria') {

    $whereProducoes[] =
        "p.coautoria_internacional = 1";
}



$sqlWhereProducoes = '';

if (
    count(
        $whereProducoes
    ) > 0
) {

    $sqlWhereProducoes =
        ' WHERE '
        . implode(
            ' AND ',
            $whereProducoes
        );
}


// ========================================================
// CARD - DOCENTES
// ========================================================

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM docentes
");

$totalDocentes =
    (int)
    $stmt->fetchColumn();


// ========================================================
// CARD - PRODUÇÕES
// ========================================================

$sql = "
    SELECT COUNT(*)
    FROM producoes p
    $sqlWhereProducoes
";

$stmt =
    $pdo->prepare(
        $sql
    );

$stmt->execute(
    $parametrosProducoes
);

$totalProducoes =
    (int)
    $stmt->fetchColumn();


// ========================================================
// CARD - PROJETOS
// ========================================================

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM projetos
");

$totalProjetos =
    (int)
    $stmt->fetchColumn();


// ========================================================
// CARD - ORIENTAÇÕES
// ========================================================

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM orientacoes
");

$totalOrientacoes =
    (int)
    $stmt->fetchColumn();


// ========================================================
// GRÁFICO - PRODUÇÕES POR ANO
// ========================================================

$sql = "
    SELECT
        p.ano,
        COUNT(*) AS quantidade

    FROM producoes p

    $sqlWhereProducoes

    GROUP BY
        p.ano

    ORDER BY
        p.ano
";

$stmt =
    $pdo->prepare(
        $sql
    );

$stmt->execute(
    $parametrosProducoes
);

$producoesPorAno =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


$graficoAnos = [];
$graficoAnosQuantidade = [];

foreach (
    $producoesPorAno
    as $registro
) {

    $graficoAnos[] =
        (string)
        $registro['ano'];

    $graficoAnosQuantidade[] =
        (int)
        $registro['quantidade'];
}


// ========================================================
// GRÁFICO - PRODUÇÕES POR TIPO
// ========================================================

$sql = "
    SELECT
        tp.nome,
        COUNT(*) AS quantidade

    FROM producoes p

    INNER JOIN tipos_producao tp
        ON tp.id = p.tipo_producao_id

    $sqlWhereProducoes

    GROUP BY
        tp.id,
        tp.nome

    ORDER BY
        quantidade DESC
";

$stmt =
    $pdo->prepare(
        $sql
    );

$stmt->execute(
    $parametrosProducoes
);

$producoesPorTipo =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


$graficoTipos = [];
$graficoTiposQuantidade = [];

foreach (
    $producoesPorTipo
    as $registro
) {

    $graficoTipos[] =
        $registro['nome'];

    $graficoTiposQuantidade[] =
        (int)
        $registro['quantidade'];
}


// ========================================================
// RESUMO DAS CLASSIFICAÇÕES CAPES
// ========================================================

$resumoClassificacoes = [
    'MB' => 0,
    'B'  => 0,
    'R'  => 0,
    'F'  => 0,
    'NC' => 0
];

$totalPontosAvaliados = 0;
$totalProducoesAvaliadas = 0;


// ========================================================
// FILTROS DO RESUMO CAPES
// ========================================================

$whereCapes = [
    "ap.quadrienio_id = :quadrienio_capes"
];

$parametrosCapes = [];

if ($quadrienioAtivo) {

    $parametrosCapes[
        ':quadrienio_capes'
    ] =
        (int)
        $quadrienioAtivo['id'];


    // ====================================================
    // FILTRO POR DOCENTE
    // ====================================================

    if ($docenteFiltro > 0) {

        $whereCapes[] = "
            EXISTS (
                SELECT 1

                FROM producoes_docentes pd_capes

                WHERE
                    pd_capes.producao_id = p.id

                    AND pd_capes.docente_id =
                        :docente_capes
            )
        ";

        $parametrosCapes[
            ':docente_capes'
        ] =
            $docenteFiltro;
    }


    // ====================================================
    // FILTRO POR ANO
    // ====================================================

    if ($anoFiltro > 0) {

        $whereCapes[] =
            "p.ano = :ano_capes";

        $parametrosCapes[
            ':ano_capes'
        ] =
            $anoFiltro;
    }
    elseif (
        $anoInicioFiltro > 0
        || $anoFimFiltro > 0
    ) {

        if ($anoInicioFiltro > 0) {

            $whereCapes[] =
                "p.ano >= :ano_inicio_capes";

            $parametrosCapes[
                ':ano_inicio_capes'
            ] = $anoInicioFiltro;
        }

        if ($anoFimFiltro > 0) {

            $whereCapes[] =
                "p.ano <= :ano_fim_capes";

            $parametrosCapes[
                ':ano_fim_capes'
            ] = $anoFimFiltro;
        }
    }


    // ====================================================
    // FILTRO POR TIPO DE PRODUÇÃO
    // ====================================================

    if ($tipoFiltro > 0) {

        $whereCapes[] =
            "p.tipo_producao_id = :tipo_capes";

        $parametrosCapes[
            ':tipo_capes'
        ] =
            $tipoFiltro;
    }


    // ====================================================
    // FILTRO POR CLASSIFICAÇÃO
    // ====================================================

    if ($classificacaoFiltro > 0) {

        $whereCapes[] =
            "ap.classificacao_id = :classificacao_capes";

        $parametrosCapes[
            ':classificacao_capes'
        ] =
            $classificacaoFiltro;
    }


    // ====================================================
    // FILTRO POR DISCENTE
    // ====================================================

    if ($comDiscenteFiltro === 'sim') {

        $whereCapes[] = "
            EXISTS (
                SELECT 1
                FROM producoes_discentes pd_disc_capes
                WHERE pd_disc_capes.producao_id = p.id
            )
        ";
    }
    elseif ($comDiscenteFiltro === 'nao') {

        $whereCapes[] = "
            NOT EXISTS (
                SELECT 1
                FROM producoes_discentes pd_disc_capes
                WHERE pd_disc_capes.producao_id = p.id
            )
        ";
    }


    // ====================================================
    // FILTRO POR LINHA DE PESQUISA
    // ====================================================

    if ($linhaFiltro > 0) {

        $whereCapes[] = "
            EXISTS (
                SELECT 1
                FROM producoes_linhas pl_capes
                WHERE
                    pl_capes.producao_id = p.id
                    AND pl_capes.linha_id = :linha_capes
            )
        ";

        $parametrosCapes[':linha_capes'] =
            $linhaFiltro;
    }


    // ====================================================
    // FILTRO POR PROJETO
    // ====================================================

    if ($projetoFiltro > 0) {

        $whereCapes[] = "
            EXISTS (
                SELECT 1
                FROM producoes_projetos pp_capes
                WHERE
                    pp_capes.producao_id = p.id
                    AND pp_capes.projeto_id = :projeto_capes
            )
        ";

        $parametrosCapes[':projeto_capes'] =
            $projetoFiltro;
    }


    if ($periodicoFiltro > 0) {

        $whereCapes[] =
            "p.periodico_id = :periodico_capes";

        $parametrosCapes[':periodico_capes'] =
            $periodicoFiltro;
    }


    if ($indexadorFiltro > 0) {

        $whereCapes[] = "
            EXISTS (
                SELECT 1
                FROM periodicos_indexadores pi_capes
                WHERE
                    pi_capes.periodico_id = p.periodico_id
                    AND pi_capes.indexador_id = :indexador_capes
            )
        ";

        $parametrosCapes[':indexador_capes'] =
            $indexadorFiltro;
    }


    if ($internacionalizacaoFiltro === 'sim') {

        $whereCapes[] =
            "(p.internacional = 1 OR p.coautoria_internacional = 1)";
    }
    elseif ($internacionalizacaoFiltro === 'nao') {

        $whereCapes[] =
            "(p.internacional = 0 AND p.coautoria_internacional = 0)";
    }
    elseif ($internacionalizacaoFiltro === 'coautoria') {

        $whereCapes[] =
            "p.coautoria_internacional = 1";
    }


    // ====================================================
    // WHERE FINAL
    // ====================================================

    $sqlWhereCapes =
        implode(
            ' AND ',
            $whereCapes
        );


    // ====================================================
    // BUSCA RESUMO
    // ====================================================

    $stmtResumoCapes =
        $pdo->prepare("
            SELECT
                c.codigo,

                COALESCE(
                    dados.quantidade,
                    0
                ) AS quantidade,

                COALESCE(
                    dados.pontos,
                    0
                ) AS pontos

            FROM classificacoes_capes c

            LEFT JOIN
            (
                SELECT
                    ap.classificacao_id,

                    COUNT(
                        ap.id
                    ) AS quantidade,

                    COALESCE(
                        SUM(
                            ap.pontuacao_interna
                        ),
                        0
                    ) AS pontos

                FROM avaliacoes_producao ap

                INNER JOIN producoes p
                    ON p.id =
                        ap.producao_id

                WHERE
                    $sqlWhereCapes

                GROUP BY
                    ap.classificacao_id

            ) dados

                ON dados.classificacao_id =
                    c.id

            WHERE
                c.codigo IN (
                    'MB',
                    'B',
                    'R',
                    'F',
                    'NC'
                )

            ORDER BY
                c.id
        ");


    $stmtResumoCapes->execute(
        $parametrosCapes
    );


    $dadosClassificacoes =
        $stmtResumoCapes->fetchAll(
            PDO::FETCH_ASSOC
        );


    // ====================================================
    // MONTA OS TOTAIS
    // ====================================================

    foreach (
        $dadosClassificacoes
        as $item
    ) {

        $codigo =
            $item['codigo'];


        $resumoClassificacoes[
            $codigo
        ] =
            (int)
            $item['quantidade'];


        $totalProducoesAvaliadas +=
            (int)
            $item['quantidade'];


        $totalPontosAvaliados +=
            (float)
            $item['pontos'];
    }
}

// ========================================================
// RANKING - PONTUAÇÃO DOS DOCENTES
// ========================================================

$rankingDocentes = [];

if ($quadrienioAtivo) {

    $whereRanking = [
        "d.ativo = 1"
    ];

    $parametrosRanking = [
        ':quadrienio_ranking' =>
            (int) $quadrienioAtivo['id']
    ];


    // ====================================================
    // FILTRO POR DOCENTE
    // ====================================================

    if ($docenteFiltro > 0) {

        $whereRanking[] =
            "d.id = :docente_ranking";

        $parametrosRanking[
            ':docente_ranking'
        ] =
            $docenteFiltro;
    }


    // ====================================================
    // FILTRO POR ANO
    // ====================================================

    if ($anoFiltro > 0) {

        $whereRanking[] =
            "p_rank.ano = :ano_ranking";

        $parametrosRanking[
            ':ano_ranking'
        ] =
            $anoFiltro;
    }
    elseif (
        $anoInicioFiltro > 0
        || $anoFimFiltro > 0
    ) {

        if ($anoInicioFiltro > 0) {

            $whereRanking[] =
                "p_rank.ano >= :ano_inicio_ranking";

            $parametrosRanking[
                ':ano_inicio_ranking'
            ] = $anoInicioFiltro;
        }

        if ($anoFimFiltro > 0) {

            $whereRanking[] =
                "p_rank.ano <= :ano_fim_ranking";

            $parametrosRanking[
                ':ano_fim_ranking'
            ] = $anoFimFiltro;
        }
    }


    // ====================================================
    // FILTRO POR TIPO
    // ====================================================

    if ($tipoFiltro > 0) {

        $whereRanking[] =
            "p_rank.tipo_producao_id = :tipo_ranking";

        $parametrosRanking[
            ':tipo_ranking'
        ] =
            $tipoFiltro;
    }


    if ($classificacaoFiltro > 0) {

        $whereRanking[] =
            "ap.classificacao_id = :classificacao_ranking";

        $parametrosRanking[
            ':classificacao_ranking'
        ] =
            $classificacaoFiltro;
    }


    if ($comDiscenteFiltro === 'sim') {

        $whereRanking[] = "
            EXISTS (
                SELECT 1
                FROM producoes_discentes pd_disc_ranking
                WHERE pd_disc_ranking.producao_id = p_rank.id
            )
        ";
    }
    elseif ($comDiscenteFiltro === 'nao') {

        $whereRanking[] = "
            NOT EXISTS (
                SELECT 1
                FROM producoes_discentes pd_disc_ranking
                WHERE pd_disc_ranking.producao_id = p_rank.id
            )
        ";
    }


    if ($linhaFiltro > 0) {

        $whereRanking[] = "
            EXISTS (
                SELECT 1
                FROM producoes_linhas pl_ranking
                WHERE
                    pl_ranking.producao_id = p_rank.id
                    AND pl_ranking.linha_id = :linha_ranking
            )
        ";

        $parametrosRanking[':linha_ranking'] =
            $linhaFiltro;
    }


    if ($projetoFiltro > 0) {

        $whereRanking[] = "
            EXISTS (
                SELECT 1
                FROM producoes_projetos pp_ranking
                WHERE
                    pp_ranking.producao_id = p_rank.id
                    AND pp_ranking.projeto_id = :projeto_ranking
            )
        ";

        $parametrosRanking[':projeto_ranking'] =
            $projetoFiltro;
    }


    if ($periodicoFiltro > 0) {

        $whereRanking[] =
            "p_rank.periodico_id = :periodico_ranking";

        $parametrosRanking[':periodico_ranking'] =
            $periodicoFiltro;
    }


    if ($indexadorFiltro > 0) {

        $whereRanking[] = "
            EXISTS (
                SELECT 1
                FROM periodicos_indexadores pi_ranking
                WHERE
                    pi_ranking.periodico_id = p_rank.periodico_id
                    AND pi_ranking.indexador_id = :indexador_ranking
            )
        ";

        $parametrosRanking[':indexador_ranking'] =
            $indexadorFiltro;
    }


    if ($internacionalizacaoFiltro === 'sim') {

        $whereRanking[] =
            "(p_rank.internacional = 1 OR p_rank.coautoria_internacional = 1)";
    }
    elseif ($internacionalizacaoFiltro === 'nao') {

        $whereRanking[] =
            "(p_rank.internacional = 0 AND p_rank.coautoria_internacional = 0)";
    }
    elseif ($internacionalizacaoFiltro === 'coautoria') {

        $whereRanking[] =
            "p_rank.coautoria_internacional = 1";
    }


    $sqlWhereRanking =
        implode(
            ' AND ',
            $whereRanking
        );


    // ====================================================
    // CONSULTA
    // ====================================================

    $stmtRanking =
        $pdo->prepare("
            SELECT
                d.id,
                d.nome,

                COUNT(
                    DISTINCT ap.producao_id
                ) AS producoes_avaliadas,

                COALESCE(
                    SUM(
                        ap.pontuacao_interna
                    ),
                    0
                ) AS pontuacao

            FROM docentes d

            LEFT JOIN producoes_docentes pd_rank
                ON pd_rank.docente_id =
                    d.id

            LEFT JOIN producoes p_rank
                ON p_rank.id =
                    pd_rank.producao_id

            LEFT JOIN avaliacoes_producao ap
                ON ap.producao_id =
                    p_rank.id

                AND ap.quadrienio_id =
                    :quadrienio_ranking

            WHERE
                $sqlWhereRanking

            GROUP BY
                d.id,
                d.nome

            ORDER BY
                pontuacao DESC,
                producoes_avaliadas DESC,
                d.nome ASC

            LIMIT 10
        ");


    $stmtRanking->execute(
        $parametrosRanking
    );


    $rankingDocentes =
        $stmtRanking->fetchAll(
            PDO::FETCH_ASSOC
        );
}

// ========================================================
// PRODUÇÕES AVALIADAS - CAPES
// ========================================================

$producoesAvaliadasCapes = [];

if ($quadrienioAtivo) {

    $whereAvaliadas = [
        "ap.quadrienio_id = :quadrienio_avaliadas"
    ];

    $parametrosAvaliadas = [
        ':quadrienio_avaliadas' =>
            (int) $quadrienioAtivo['id']
    ];


    // DOCENTE

    if ($docenteFiltro > 0) {

        $whereAvaliadas[] = "
            EXISTS (
                SELECT 1
                FROM producoes_docentes pd_filtro_av

                WHERE
                    pd_filtro_av.producao_id = p.id

                    AND pd_filtro_av.docente_id =
                        :docente_avaliadas
            )
        ";

        $parametrosAvaliadas[
            ':docente_avaliadas'
        ] =
            $docenteFiltro;
    }


    // ANO

    if ($anoFiltro > 0) {

        $whereAvaliadas[] =
            "p.ano = :ano_avaliadas";

        $parametrosAvaliadas[
            ':ano_avaliadas'
        ] =
            $anoFiltro;
    }
    elseif (
        $anoInicioFiltro > 0
        || $anoFimFiltro > 0
    ) {

        if ($anoInicioFiltro > 0) {

            $whereAvaliadas[] =
                "p.ano >= :ano_inicio_avaliadas";

            $parametrosAvaliadas[
                ':ano_inicio_avaliadas'
            ] = $anoInicioFiltro;
        }

        if ($anoFimFiltro > 0) {

            $whereAvaliadas[] =
                "p.ano <= :ano_fim_avaliadas";

            $parametrosAvaliadas[
                ':ano_fim_avaliadas'
            ] = $anoFimFiltro;
        }
    }


    // TIPO

    if ($tipoFiltro > 0) {

        $whereAvaliadas[] =
            "p.tipo_producao_id = :tipo_avaliadas";

        $parametrosAvaliadas[
            ':tipo_avaliadas'
        ] =
            $tipoFiltro;
    }


    // CLASSIFICAÇÃO

    if ($classificacaoFiltro > 0) {

        $whereAvaliadas[] =
            "ap.classificacao_id = :classificacao_avaliadas";

        $parametrosAvaliadas[
            ':classificacao_avaliadas'
        ] =
            $classificacaoFiltro;
    }


    // DISCENTE

    if ($comDiscenteFiltro === 'sim') {

        $whereAvaliadas[] = "
            EXISTS (
                SELECT 1
                FROM producoes_discentes pd_disc_avaliadas
                WHERE pd_disc_avaliadas.producao_id = p.id
            )
        ";
    }
    elseif ($comDiscenteFiltro === 'nao') {

        $whereAvaliadas[] = "
            NOT EXISTS (
                SELECT 1
                FROM producoes_discentes pd_disc_avaliadas
                WHERE pd_disc_avaliadas.producao_id = p.id
            )
        ";
    }


    // LINHA DE PESQUISA

    if ($linhaFiltro > 0) {

        $whereAvaliadas[] = "
            EXISTS (
                SELECT 1
                FROM producoes_linhas pl_avaliadas
                WHERE
                    pl_avaliadas.producao_id = p.id
                    AND pl_avaliadas.linha_id = :linha_avaliadas
            )
        ";

        $parametrosAvaliadas[':linha_avaliadas'] =
            $linhaFiltro;
    }


    // PROJETO

    if ($projetoFiltro > 0) {

        $whereAvaliadas[] = "
            EXISTS (
                SELECT 1
                FROM producoes_projetos pp_avaliadas
                WHERE
                    pp_avaliadas.producao_id = p.id
                    AND pp_avaliadas.projeto_id = :projeto_avaliadas
            )
        ";

        $parametrosAvaliadas[':projeto_avaliadas'] =
            $projetoFiltro;
    }


    if ($periodicoFiltro > 0) {

        $whereAvaliadas[] =
            "p.periodico_id = :periodico_avaliadas";

        $parametrosAvaliadas[':periodico_avaliadas'] =
            $periodicoFiltro;
    }


    if ($indexadorFiltro > 0) {

        $whereAvaliadas[] = "
            EXISTS (
                SELECT 1
                FROM periodicos_indexadores pi_avaliadas
                WHERE
                    pi_avaliadas.periodico_id = p.periodico_id
                    AND pi_avaliadas.indexador_id = :indexador_avaliadas
            )
        ";

        $parametrosAvaliadas[':indexador_avaliadas'] =
            $indexadorFiltro;
    }


    if ($internacionalizacaoFiltro === 'sim') {

        $whereAvaliadas[] =
            "(p.internacional = 1 OR p.coautoria_internacional = 1)";
    }
    elseif ($internacionalizacaoFiltro === 'nao') {

        $whereAvaliadas[] =
            "(p.internacional = 0 AND p.coautoria_internacional = 0)";
    }
    elseif ($internacionalizacaoFiltro === 'coautoria') {

        $whereAvaliadas[] =
            "p.coautoria_internacional = 1";
    }


    $sqlWhereAvaliadas =
        implode(
            ' AND ',
            $whereAvaliadas
        );


    $stmtAvaliadas =
        $pdo->prepare("
            SELECT
                p.id,
                p.titulo,
                p.ano,
                p.doi,

                tp.nome AS tipo,

                c.codigo AS classificacao,

                ap.pontuacao_interna AS pontos,

                (
                    SELECT
                        GROUP_CONCAT(
                            DISTINCT d_av.nome
                            ORDER BY d_av.nome
                            SEPARATOR ', '
                        )

                    FROM producoes_docentes pd_av

                    INNER JOIN docentes d_av
                        ON d_av.id =
                            pd_av.docente_id

                    WHERE
                        pd_av.producao_id =
                            p.id
                ) AS docentes,

                (
                    SELECT
                        GROUP_CONCAT(
                            DISTINCT disc.nome
                            ORDER BY
                                COALESCE(
                                    pd_disc.ordem_autoria,
                                    999999
                                ),
                                disc.nome
                            SEPARATOR ', '
                        )

                    FROM producoes_discentes pd_disc

                    INNER JOIN discentes disc
                        ON disc.id =
                            pd_disc.discente_id

                    WHERE
                        pd_disc.producao_id =
                            p.id

                ) AS discentes

            FROM avaliacoes_producao ap

            INNER JOIN producoes p
                ON p.id =
                    ap.producao_id

            LEFT JOIN tipos_producao tp
                ON tp.id =
                    p.tipo_producao_id

            LEFT JOIN classificacoes_capes c
                ON c.id =
                    ap.classificacao_id

            WHERE
                $sqlWhereAvaliadas

            ORDER BY
                ap.pontuacao_interna DESC,
                p.ano DESC,
                p.titulo ASC

            LIMIT 20
        ");


    $stmtAvaliadas->execute(
        $parametrosAvaliadas
    );


    $producoesAvaliadasCapes =
        $stmtAvaliadas->fetchAll(
            PDO::FETCH_ASSOC
        );
}

// ========================================================
// ÚLTIMAS PRODUÇÕES
// ========================================================

$sql = "
    SELECT
        p.id,
        p.titulo,
        p.ano,
        tp.nome AS tipo

    FROM producoes p

    LEFT JOIN tipos_producao tp
        ON tp.id = p.tipo_producao_id

    $sqlWhereProducoes

    ORDER BY
        p.ano DESC,
        p.id DESC

    LIMIT 10
";

$stmt =
    $pdo->prepare(
        $sql
    );

$stmt->execute(
    $parametrosProducoes
);

$ultimasProducoes =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// ========================================================
// ÚLTIMAS IMPORTAÇÕES LATTES
// ========================================================

$stmt = $pdo->query("
    SELECT
        cl.id,
        cl.nome_arquivo,
        cl.status,
        cl.quantidade_registros,
        cl.data_importacao,
        d.nome AS docente

    FROM curriculos_lattes cl

    INNER JOIN docentes d
        ON d.id = cl.docente_id

    ORDER BY
        cl.data_importacao DESC,
        cl.id DESC

    LIMIT 5
");

$ultimasImportacoes =
    $stmt->fetchAll(
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
        Dashboard - PPGA Analytics
    </title>

    <link
        rel="stylesheet"
        href="../../assets/css/dashboard.css"
    >

</head>

<body>

<div class="dashboard">

    <!-- ========================================= -->
    <!-- CABEÇALHO -->
    <!-- ========================================= -->

    <header class="dashboard-header">

        <div>

            <h1>
                PPGA Analytics
            </h1>

            <p>
                Dashboard de Produção Científica
            </p>

        </div>

        <div class="dashboard-acoes">

            <a
                href="../../index.php"
                class="botao botao-secundario"
            >
                Página inicial
            </a>

            <a
                href="../avaliacoes/index.php"
                class="botao botao-avaliacoes"
            >
                Avaliações CAPES
            </a>

            <a
                href="../comparativos/index.php"
                class="botao"
            >
                Comparativos
            </a>

            <a
                href="../relatorios/index.php"
                class="botao"
            >
                Relatórios
            </a>

            <a
                href="../periodicos/index.php"
                class="botao"
            >
                Periódicos
            </a>

            <a
                href="../discentes/index.php"
                class="botao"
            >
                Discentes/Egressos
            </a>

            <a
                href="../lattes/index.php"
                class="botao"
            >
                Currículos Lattes
            </a>

            <a
                href="../docentes/index.php"
                class="botao botao-secundario"
            >
                Docentes
            </a>

        </div>

    </header>


    <!-- ========================================= -->
    <!-- FILTROS -->
    <!-- ========================================= -->

    <section class="painel-filtros">

        <form
            method="GET"
            action=""
        >

            <div class="campo-filtro">

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
                            value="<?= (int) $docente['id'] ?>"
                            <?= (
                                $docenteFiltro
                                ===
                                (int) $docente['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= htmlspecialchars(
                                $docente['nome']
                            ) ?>
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

                    <option value=""
                        <?= $periodoFiltro === '' ? 'selected' : '' ?>
                    >
                        Todos
                    </option>

                    <option value="ultimos_2"
                        <?= $periodoFiltro === 'ultimos_2' ? 'selected' : '' ?>
                    >
                        Últimos 2 anos
                    </option>

                    <option value="ultimos_3"
                        <?= $periodoFiltro === 'ultimos_3' ? 'selected' : '' ?>
                    >
                        Últimos 3 anos
                    </option>

                    <option value="ultimos_4"
                        <?= $periodoFiltro === 'ultimos_4' ? 'selected' : '' ?>
                    >
                        Últimos 4 anos
                    </option>

                    <?php if ($quadrienioAtivo): ?>

                        <option value="quadrienio"
                            <?= $periodoFiltro === 'quadrienio' ? 'selected' : '' ?>
                        >
                            Quadriênio CAPES
                            (<?= (int) $quadrienioAtivo['ano_inicio'] ?>-<?= (int) $quadrienioAtivo['ano_fim'] ?>)
                        </option>

                    <?php endif; ?>

                    <option value="personalizado"
                        <?= $periodoFiltro === 'personalizado' ? 'selected' : '' ?>
                    >
                        Intervalo personalizado
                    </option>

                    <optgroup label="Ano específico">

                        <?php foreach (
                            $anos
                            as $ano
                        ): ?>

                            <option
                                value="<?= (int) $ano ?>"
                                <?= (
                                    $periodoFiltro
                                    ===
                                    (string) (int) $ano
                                )
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


            <div class="campo-filtro">

                <label for="tipo">
                    Tipo de produção
                </label>

                <select
                    name="tipo"
                    id="tipo"
                >

                    <option value="0">
                        Todos
                    </option>

                    <?php foreach (
                        $tipos
                        as $tipo
                    ): ?>

                        <option
                            value="<?= (int) $tipo['id'] ?>"
                            <?= (
                                $tipoFiltro
                                ===
                                (int) $tipo['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= htmlspecialchars(
                                $tipo['nome']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="campo-filtro">

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
                        $classificacoesFiltro
                        as $classificacao
                    ): ?>

                        <option
                            value="<?= (int) $classificacao['id'] ?>"
                            <?= (
                                $classificacaoFiltro
                                ===
                                (int) $classificacao['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= htmlspecialchars(
                                $classificacao['codigo']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="campo-filtro">

                <label for="com_discente">
                    Com discente
                </label>

                <select
                    name="com_discente"
                    id="com_discente"
                >

                    <option value=""
                        <?= $comDiscenteFiltro === '' ? 'selected' : '' ?>
                    >
                        Todos
                    </option>

                    <option value="sim"
                        <?= $comDiscenteFiltro === 'sim' ? 'selected' : '' ?>
                    >
                        Sim
                    </option>

                    <option value="nao"
                        <?= $comDiscenteFiltro === 'nao' ? 'selected' : '' ?>
                    >
                        Não
                    </option>

                </select>

            </div>


            <div class="campo-filtro">

                <label for="linha">
                    Linha de pesquisa
                </label>

                <select
                    name="linha"
                    id="linha"
                >

                    <option value="0">
                        Todas
                    </option>

                    <?php foreach (
                        $linhasPesquisa
                        as $linha
                    ): ?>

                        <option
                            value="<?= (int) $linha['id'] ?>"
                            <?= (
                                $linhaFiltro
                                ===
                                (int) $linha['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= htmlspecialchars(
                                $linha['nome']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="campo-filtro">

                <label for="projeto">
                    Projeto
                </label>

                <select
                    name="projeto"
                    id="projeto"
                >

                    <option value="0">
                        Todos
                    </option>

                    <?php foreach (
                        $projetosFiltro
                        as $projeto
                    ): ?>

                        <option
                            value="<?= (int) $projeto['id'] ?>"
                            <?= (
                                $projetoFiltro
                                ===
                                (int) $projeto['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= htmlspecialchars(
                                $projeto['nome']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>



            <div class="campo-filtro">

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
                        $periodicosFiltro
                        as $periodico
                    ): ?>

                        <option
                            value="<?= (int) $periodico['id'] ?>"
                            <?= (
                                $periodicoFiltro
                                ===
                                (int) $periodico['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= htmlspecialchars(
                                $periodico['nome']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="campo-filtro">

                <label for="indexador">
                    Indexação
                </label>

                <select
                    name="indexador"
                    id="indexador"
                >

                    <option value="0">
                        Todas
                    </option>

                    <?php foreach (
                        $indexadoresFiltro
                        as $indexador
                    ): ?>

                        <option
                            value="<?= (int) $indexador['id'] ?>"
                            <?= (
                                $indexadorFiltro
                                ===
                                (int) $indexador['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= htmlspecialchars(
                                $indexador['nome']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="campo-filtro">

                <label for="internacionalizacao">
                    Internacionalização
                </label>

                <select
                    name="internacionalizacao"
                    id="internacionalizacao"
                >

                    <option value=""
                        <?= $internacionalizacaoFiltro === '' ? 'selected' : '' ?>
                    >
                        Todas
                    </option>

                    <option value="sim"
                        <?= $internacionalizacaoFiltro === 'sim' ? 'selected' : '' ?>
                    >
                        Com internacionalização
                    </option>

                    <option value="coautoria"
                        <?= $internacionalizacaoFiltro === 'coautoria' ? 'selected' : '' ?>
                    >
                        Com coautoria internacional
                    </option>

                    <option value="nao"
                        <?= $internacionalizacaoFiltro === 'nao' ? 'selected' : '' ?>
                    >
                        Sem internacionalização
                    </option>

                </select>

            </div>


            <div class="acoes-filtro">

                <button
                    type="submit"
                    class="botao"
                >
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

    </section>


    <!-- ========================================= -->
    <!-- CARDS -->
    <!-- ========================================= -->

    <section class="cards">

        <article class="card">

            <span class="card-titulo">
                Docentes
            </span>

            <strong class="card-numero">
                <?= $totalDocentes ?>
            </strong>

            <span class="card-descricao">
                cadastrados
            </span>

        </article>


        <article class="card">

            <span class="card-titulo">
                Produções
            </span>

            <strong class="card-numero">
                <?= $totalProducoes ?>
            </strong>

            <span class="card-descricao">
                encontradas
            </span>

        </article>


        <article class="card">

            <span class="card-titulo">
                Projetos
            </span>

            <strong class="card-numero">
                <?= $totalProjetos ?>
            </strong>

            <span class="card-descricao">
                de pesquisa
            </span>

        </article>


        <article class="card">

            <span class="card-titulo">
                Orientações
            </span>

            <strong class="card-numero">
                <?= $totalOrientacoes ?>
            </strong>

            <span class="card-descricao">
                registradas
            </span>

        </article>

    </section>

    <section class="painel">

    <div class="painel-cabecalho">

        <h2>
            Avaliação CAPES
        </h2>

        <?php if ($quadrienioAtivo): ?>

            <p>
                Quadriênio
                <?= (int) $quadrienioAtivo['ano_inicio'] ?>
                -
                <?= (int) $quadrienioAtivo['ano_fim'] ?>
            </p>

        <?php endif; ?>

    </div>

    <section class="painel painel-avaliadas">

    <div class="painel-cabecalho">

        <h2>
            Produções Avaliadas
        </h2>

        <p>
            Classificação e pontuação no quadriênio ativo
        </p>

    </div>


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
                        Aluno(s)
                    </th>

                    <th>
                        Tipo
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
                        Acesso
                    </th>

                    <th>
                        Alunos
                    </th>

                </tr>


            </thead>


            <tbody>

            <?php if (
                count(
                    $producoesAvaliadasCapes
                ) === 0
            ): ?>

                <tr>

                    <td
                        colspan="9"
                        class="sem-dados"
                    >
                        Nenhuma produção avaliada encontrada.
                    </td>

                </tr>

            <?php else: ?>

                <?php foreach (
                    $producoesAvaliadasCapes
                    as $producao
                ): ?>

                    <tr>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $producao['titulo']
                                ) ?>
                            </strong>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $producao['docentes']
                                ?? 'Não informado'
                            ) ?>

                        </td>

                        <td>

                            <?php if (
                                !empty(
                                    $producao['discentes']
                                )
                            ): ?>

                                <?= htmlspecialchars(
                                    $producao['discentes']
                                ) ?>

                            <?php else: ?>

                                <span class="sem-link">
                                    —
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $producao['tipo']
                                ?? 'Não informado'
                            ) ?>

                        </td>


                        <td>
                            <?= (int) $producao['ano'] ?>
                        </td>


                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $producao[
                                        'classificacao'
                                    ]
                                    ?? 'NC'
                                ) ?>
                            </strong>

                        </td>


                        <td>

                            <strong>

                                <?= number_format(
                                    (float)
                                    $producao['pontos'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>

                            </strong>

                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $producao['doi']
                                )
                            ): ?>

                                <a
                                    class="botao"
                                    href="https://doi.org/<?= urlencode(
                                        $producao['doi']
                                    ) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Abrir
                                </a>

                            <?php else: ?>

                                <span class="sem-link">
                                    —
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <a
                                class="botao"
                                href="../avaliacoes/discentes.php?producao_id=<?= (int) $producao['id'] ?>"
                            >
                                Gerenciar
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


    <div class="cards cards-capes">

        <article class="card">

            <span class="card-titulo">
                MB
            </span>

            <strong class="card-numero">
                <?= $resumoClassificacoes['MB'] ?>
            </strong>

            <span class="card-descricao">
                8 pontos cada
            </span>

        </article>


        <article class="card">

            <span class="card-titulo">
                B
            </span>

            <strong class="card-numero">
                <?= $resumoClassificacoes['B'] ?>
            </strong>

            <span class="card-descricao">
                4 pontos cada
            </span>

        </article>


        <article class="card">

            <span class="card-titulo">
                R
            </span>

            <strong class="card-numero">
                <?= $resumoClassificacoes['R'] ?>
            </strong>

            <span class="card-descricao">
                2 pontos cada
            </span>

        </article>


        <article class="card">

            <span class="card-titulo">
                F
            </span>

            <strong class="card-numero">
                <?= $resumoClassificacoes['F'] ?>
            </strong>

            <span class="card-descricao">
                1 ponto cada
            </span>

        </article>


        <article class="card">

            <span class="card-titulo">
                NC
            </span>

            <strong class="card-numero">
                <?= $resumoClassificacoes['NC'] ?>
            </strong>

            <span class="card-descricao">
                0 ponto
            </span>

        </article>


        <article class="card">

            <span class="card-titulo">
                Avaliadas
            </span>

            <strong class="card-numero">
                <?= $totalProducoesAvaliadas ?>
            </strong>

            <span class="card-descricao">
                produções
            </span>

        </article>


        <article class="card">

            <span class="card-titulo">
                Pontuação total
            </span>

            <strong class="card-numero">

                <?= number_format(
                    $totalPontosAvaliados,
                    0,
                    ',',
                    '.'
                ) ?>

            </strong>

            <span class="card-descricao">
                pontos
            </span>

        </article>

    </div>

</section>


    <!-- ========================================= -->
    <!-- GRÁFICOS -->
    <!-- ========================================= -->

    <section class="graficos">

        <article class="painel">

            <div class="painel-cabecalho">

                <h2>
                    Produções por ano
                </h2>

            </div>

            <div class="grafico-container">

                <canvas
                    id="graficoProducoesAno"
                ></canvas>

            </div>

        </article>


        <article class="painel">

            <div class="painel-cabecalho">

                <h2>
                    Produções por tipo
                </h2>

            </div>

            <div class="grafico-container">

                <canvas
                    id="graficoProducoesTipo"
                ></canvas>

            </div>

        </article>

    </section>


    <!-- ========================================= -->
    <!-- RANKING -->
    <!-- ========================================= -->

    <section class="conteudo-duplo">

        <article class="painel">

            <div class="painel-cabecalho">

                <h2>
                    Ranking por Pontuação
                </h2>

                <?php if ($quadrienioAtivo): ?>

                    <p>
                        Quadriênio
                        <?= (int) $quadrienioAtivo['ano_inicio'] ?>
                        -
                        <?= (int) $quadrienioAtivo['ano_fim'] ?>
                    </p>

                <?php endif; ?>

            </div>

            <div class="tabela-container">

                <table>

                   <tbody>

<?php if (
    count($rankingDocentes) === 0
): ?>

    <tr>

        <td
            colspan="4"
            class="sem-dados"
        >
            Nenhum docente encontrado.
        </td>

    </tr>

<?php else: ?>

                        <?php

                            $posicao = 1;

                            foreach (
                                $rankingDocentes
                                as $docente
                            ):

                        ?>

                            <tr>

                                <td>
                                    <?= $posicao ?>º
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $docente['nome']
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int)
                                        $docente[
                                            'producoes_avaliadas'
                                        ]
                                    ?>
                                </td>

                                <td>

                                    <strong>

                                        <?= number_format(
                                            (float)
                                            $docente[
                                                'pontuacao'
                                            ],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>

                                        pts

                                    </strong>

                                </td>

                            </tr>

                        <?php

                            $posicao++;

                            endforeach;

                        ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </article>


        <!-- ===================================== -->
        <!-- IMPORTAÇÕES -->
        <!-- ===================================== -->

        <article class="painel">

            <div class="painel-cabecalho">

                <h2>
                    Últimas importações
                </h2>

            </div>

            <div class="lista-importacoes">

                <?php foreach (
                    $ultimasImportacoes
                    as $importacao
                ): ?>

                    <a
                        class="importacao"
                        href="../lattes/visualizar.php?id=<?= (int) $importacao['id'] ?>"
                    >

                        <div>

                            <strong>
                                <?= htmlspecialchars(
                                    $importacao[
                                        'docente'
                                    ]
                                ) ?>
                            </strong>

                            <span>
                                <?= htmlspecialchars(
                                    $importacao[
                                        'nome_arquivo'
                                    ]
                                ) ?>
                            </span>

                        </div>


                        <div class="importacao-dados">

                            <span>
                                <?= (int)
                                    $importacao[
                                        'quantidade_registros'
                                    ]
                                ?>
                                registros
                            </span>

                            <span
                                class="status status-<?= strtolower(
                                    htmlspecialchars(
                                        $importacao[
                                            'status'
                                        ]
                                    )
                                ) ?>"
                            >
                                <?= htmlspecialchars(
                                    $importacao[
                                        'status'
                                    ]
                                ) ?>
                            </span>

                        </div>

                    </a>

                <?php endforeach; ?>

            </div>

        </article>

    </section>


    <!-- ========================================= -->
    <!-- ÚLTIMAS PRODUÇÕES -->
    <!-- ========================================= -->

    <section class="painel">

        <div class="painel-cabecalho">

            <h2>
                Últimas produções
            </h2>

        </div>

        <div class="tabela-container">

            <table>

                <thead>

                    <tr>

                        <th>
                            Título
                        </th>

                        <th>
                            Tipo
                        </th>

                        <th>
                            Ano
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (
                    count(
                        $ultimasProducoes
                    ) === 0
                ): ?>

                    <tr>

                        <td
                            colspan="3"
                            class="sem-dados"
                        >
                            Nenhuma produção encontrada.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $ultimasProducoes
                        as $producao
                    ): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $producao[
                                        'titulo'
                                    ]
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $producao[
                                        'tipo'
                                    ]
                                    ?? 'Não informado'
                                ) ?>
                            </td>

                            <td>
                                <?= (int)
                                    $producao[
                                        'ano'
                                    ]
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</div>


<!-- ===================================================== -->
<!-- CHART.JS -->
<!-- ===================================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/chart.js"
></script>


<script>

// ========================================================
// FILTRO - INTERVALO PERSONALIZADO
// ========================================================

const selectPeriodo =
    document.getElementById(
        'periodo'
    );

const camposPeriodoPersonalizado =
    document.querySelectorAll(
        '.campo-periodo-personalizado'
    );

function atualizarCamposPeriodo() {

    const exibir =
        selectPeriodo
        && selectPeriodo.value === 'personalizado';

    camposPeriodoPersonalizado.forEach(
        (campo) => {
            campo.style.display =
                exibir
                    ? 'flex'
                    : 'none';
        }
    );
}

if (selectPeriodo) {

    atualizarCamposPeriodo();

    selectPeriodo.addEventListener(
        'change',
        atualizarCamposPeriodo
    );
}


const anos =
    <?= json_encode(
        $graficoAnos,
        JSON_UNESCAPED_UNICODE
    ) ?>;

const quantidadesAno =
    <?= json_encode(
        $graficoAnosQuantidade
    ) ?>;


const tipos =
    <?= json_encode(
        $graficoTipos,
        JSON_UNESCAPED_UNICODE
    ) ?>;

const quantidadesTipo =
    <?= json_encode(
        $graficoTiposQuantidade
    ) ?>;


// ========================================================
// GRÁFICO POR ANO
// ========================================================

const canvasAno =
    document.getElementById(
        'graficoProducoesAno'
    );

new Chart(
    canvasAno,
    {
        type: 'line',

        data: {

            labels: anos,

            datasets: [
                {
                    label:
                        'Produções',

                    data:
                        quantidadesAno,

                    tension:
                        0.25,

                    fill:
                        true
                }
            ]
        },

        options: {

            responsive:
                true,

            maintainAspectRatio:
                false,

            plugins: {

                legend: {
                    display:
                        false
                }
            },

            scales: {

                y: {

                    beginAtZero:
                        true,

                    ticks: {

                        precision:
                            0
                    }
                }
            }
        }
    }
);


// ========================================================
// GRÁFICO POR TIPO
// ========================================================

const canvasTipo =
    document.getElementById(
        'graficoProducoesTipo'
    );

new Chart(
    canvasTipo,
    {
        type:
            'bar',

        data: {

            labels:
                tipos,

            datasets: [
                {
                    label:
                        'Produções',

                    data:
                        quantidadesTipo
                }
            ]
        },

        options: {

            responsive:
                true,

            maintainAspectRatio:
                false,

            indexAxis:
                'y',

            plugins: {

                legend: {
                    display:
                        false
                }
            },

            scales: {

                x: {

                    beginAtZero:
                        true,

                    ticks: {

                        precision:
                            0
                    }
                }
            }
        }
    }
);

</script>

</body>

</html>