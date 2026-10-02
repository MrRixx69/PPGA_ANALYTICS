<?php

$raizProjeto = dirname(__DIR__, 2);

require_once
    $raizProjeto
    . DIRECTORY_SEPARATOR
    . 'config'
    . DIRECTORY_SEPARATOR
    . 'conexao.php';


// ========================================================
// FILTROS RECEBIDOS
// ========================================================

$docenteId = isset($_GET['docente'])
    ? (int) $_GET['docente']
    : 0;

if ($docenteId <= 0) {
    http_response_code(400);
    exit('Docente inválido.');
}

$stmtDocente = $pdo->prepare("
    SELECT
        id,
        nome
    FROM docentes
    WHERE id = :id
    LIMIT 1
");

$stmtDocente->execute([
    ':id' => $docenteId
]);

$docente = $stmtDocente->fetch(PDO::FETCH_ASSOC);

if (!$docente) {
    http_response_code(404);
    exit('Docente não encontrado.');
}


// ========================================================
// QUADRIÊNIO ATIVO
// ========================================================

$stmtQuadrienio = $pdo->query("
    SELECT
        id,
        ano_inicio,
        ano_fim
    FROM quadrienios
    WHERE ativo = 1
    ORDER BY ano_inicio DESC
    LIMIT 1
");

$quadrienioAtivo = $stmtQuadrienio->fetch(PDO::FETCH_ASSOC);


// ========================================================
// PERÍODO
// ========================================================

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
// CONSULTA
// ========================================================

$where = [
    'pd.docente_id = :docente_id'
];

$parametros = [
    ':docente_id' => $docenteId
];

if ($anoExato > 0) {
    $where[] = 'p.ano = :ano';
    $parametros[':ano'] = $anoExato;
}
else {
    if ($anoInicio > 0) {
        $where[] = 'p.ano >= :ano_inicio';
        $parametros[':ano_inicio'] = $anoInicio;
    }

    if ($anoFim > 0) {
        $where[] = 'p.ano <= :ano_fim';
        $parametros[':ano_fim'] = $anoFim;
    }
}

if ($quadrienioAtivo) {
    $joinAvaliacao = "
        LEFT JOIN avaliacoes_producao ap
            ON ap.producao_id = p.id
            AND ap.quadrienio_id = :quadrienio_id
        LEFT JOIN classificacoes_capes c
            ON c.id = ap.classificacao_id
    ";

    $parametros[':quadrienio_id'] =
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

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.titulo,
        p.ano,
        tp.nome AS tipo,
        per.nome AS periodico,
        COALESCE(p.issn, per.issn) AS issn,
        p.doi,
        CASE
            WHEN p.internacional = 1 THEN 'Sim'
            ELSE 'Não'
        END AS internacional,
        CASE
            WHEN p.coautoria_internacional = 1 THEN 'Sim'
            ELSE 'Não'
        END AS coautoria_internacional,
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
        ) AS discentes,
        c.codigo AS classificacao,
        ap.pontuacao_interna AS pontos
    FROM producoes p
    INNER JOIN producoes_docentes pd
        ON pd.producao_id = p.id
    INNER JOIN tipos_producao tp
        ON tp.id = p.tipo_producao_id
    LEFT JOIN periodicos per
        ON per.id = p.periodico_id
    $joinAvaliacao
    WHERE " . implode(' AND ', $where) . "
    ORDER BY p.ano DESC, p.titulo ASC
");

$stmt->execute($parametros);

$producoes = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ========================================================
// NOME DO ARQUIVO
// ========================================================

$nomeSeguro = preg_replace(
    '/[^a-z0-9]+/i',
    '_',
    iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $docente['nome'])
        ?: 'docente'
);

$nomeSeguro = trim((string) $nomeSeguro, '_');

$arquivo = 'relatorio_' . strtolower($nomeSeguro) . '.csv';


// ========================================================
// SAÍDA CSV
// ========================================================

header('Content-Type: text/csv; charset=UTF-8');
header(
    'Content-Disposition: attachment; filename="'
    . $arquivo
    . '"'
);
header('Pragma: no-cache');
header('Expires: 0');

$saida = fopen('php://output', 'w');

// BOM UTF-8 para abrir corretamente no Excel.
fwrite($saida, "\xEF\xBB\xBF");

fputcsv(
    $saida,
    [
        'Docente',
        $docente['nome']
    ],
    ';'
);

fputcsv(
    $saida,
    [],
    ';'
);

fputcsv(
    $saida,
    [
        'Título',
        'Ano',
        'Tipo',
        'Periódico',
        'ISSN',
        'DOI',
        'Discentes',
        'Classificação CAPES',
        'Pontos',
        'Internacional',
        'Coautoria internacional'
    ],
    ';'
);

foreach ($producoes as $producao) {
    fputcsv(
        $saida,
        [
            $producao['titulo'],
            $producao['ano'],
            $producao['tipo'],
            $producao['periodico'] ?? '',
            $producao['issn'] ?? '',
            $producao['doi'] ?? '',
            $producao['discentes'] ?? '',
            $producao['classificacao'] ?? '',
            $producao['pontos'] ?? '',
            $producao['internacional'],
            $producao['coautoria_internacional']
        ],
        ';'
    );
}

fclose($saida);
exit;