<?php

$raizProjeto = dirname(__DIR__, 2);

require_once
    $raizProjeto
    . DIRECTORY_SEPARATOR
    . 'config'
    . DIRECTORY_SEPARATOR
    . 'conexao.php';


// ========================================================
// FUNÇÕES AUXILIARES
// ========================================================

function montarFiltroPeriodoExcel(
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

function textoXmlExcel(mixed $valor): string
{
    $texto = (string) ($valor ?? '');

    // Remove caracteres de controle que não são válidos em XML 1.0.
    $texto = preg_replace(
        '/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u',
        '',
        $texto
    ) ?? '';

    return htmlspecialchars(
        $texto,
        ENT_QUOTES | ENT_XML1,
        'UTF-8'
    );
}

function colunaExcel(int $numero): string
{
    $resultado = '';

    while ($numero > 0) {
        $numero--;
        $resultado = chr(65 + ($numero % 26)) . $resultado;
        $numero = intdiv($numero, 26);
    }

    return $resultado;
}

function celulaExcel(
    string $referencia,
    mixed $valor,
    int $estilo = 0,
    bool $numerico = false
): string {
    $atributoEstilo = $estilo > 0
        ? ' s="' . $estilo . '"'
        : '';

    if ($valor === null || $valor === '') {
        return '<c r="' . $referencia . '"' . $atributoEstilo . '/>';
    }

    if ($numerico && is_numeric($valor)) {
        return '<c r="' . $referencia . '"' . $atributoEstilo . '>'
            . '<v>' . (0 + $valor) . '</v>'
            . '</c>';
    }

    return '<c r="' . $referencia . '" t="inlineStr"' . $atributoEstilo . '>'
        . '<is><t xml:space="preserve">'
        . textoXmlExcel($valor)
        . '</t></is></c>';
}

function linhaExcel(
    int $numeroLinha,
    array $valores,
    array $estilos = [],
    array $numericos = [],
    ?float $altura = null
): string {
    $atributos = ' r="' . $numeroLinha . '"';

    if ($altura !== null) {
        $atributos .= ' ht="' . $altura . '" customHeight="1"';
    }

    $xml = '<row' . $atributos . '>';

    foreach ($valores as $indice => $valor) {
        $coluna = colunaExcel($indice + 1);
        $referencia = $coluna . $numeroLinha;
        $estilo = (int) ($estilos[$indice] ?? 0);
        $numerico = (bool) ($numericos[$indice] ?? false);

        $xml .= celulaExcel(
            $referencia,
            $valor,
            $estilo,
            $numerico
        );
    }

    return $xml . '</row>';
}

function planilhaExcelXml(
    array $linhas,
    array $larguras,
    int $congelarAteLinha = 0,
    ?string $filtroAutomatico = null,
    array $mesclas = []
): string {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet '
        . 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

    $xml .= '<sheetViews><sheetView workbookViewId="0">';

    if ($congelarAteLinha > 0) {
        $topo = $congelarAteLinha + 1;
        $xml .= '<pane ySplit="' . $congelarAteLinha . '" '
            . 'topLeftCell="A' . $topo . '" '
            . 'activePane="bottomLeft" state="frozen"/>';
    }

    $xml .= '</sheetView></sheetViews>';
    $xml .= '<sheetFormatPr defaultRowHeight="15"/>';

    if ($larguras !== []) {
        $xml .= '<cols>';

        foreach ($larguras as $indice => $largura) {
            $coluna = $indice + 1;
            $xml .= '<col min="' . $coluna . '" max="' . $coluna . '" '
                . 'width="' . (float) $largura . '" customWidth="1"/>';
        }

        $xml .= '</cols>';
    }

    $xml .= '<sheetData>';

    foreach ($linhas as $linha) {
        $xml .= $linha;
    }

    $xml .= '</sheetData>';

    if ($filtroAutomatico !== null && $filtroAutomatico !== '') {
        $xml .= '<autoFilter ref="' . textoXmlExcel($filtroAutomatico) . '"/>';
    }

    if ($mesclas !== []) {
        $xml .= '<mergeCells count="' . count($mesclas) . '">';

        foreach ($mesclas as $mescla) {
            $xml .= '<mergeCell ref="' . textoXmlExcel($mescla) . '"/>';
        }

        $xml .= '</mergeCells>';
    }

    $xml .= '<pageMargins '
        . 'left="0.35" right="0.35" top="0.55" bottom="0.55" '
        . 'header="0.2" footer="0.2"/>';

    $xml .= '</worksheet>';

    return $xml;
}

function adicionarArquivoAoZip(
    object $zip,
    bool $usaZipArchive,
    string $caminho,
    string $conteudo
): void {
    if ($usaZipArchive) {
        /** @var ZipArchive $zip */
        $zip->addFromString($caminho, $conteudo);
        return;
    }

    /** @var PharData $zip */
    $zip[$caminho] = $conteudo;
}


// ========================================================
// DOCENTE
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
        ano_fim,
        descricao
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
// RESUMO
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

$filtroResumo = montarFiltroPeriodoExcel(
    'p',
    $anoExato,
    $anoInicio,
    $anoFim,
    'resumo'
);

$whereResumo = array_merge(
    ['pd.docente_id = :docente_resumo'],
    $filtroResumo['sql']
);

$parametrosResumo = array_merge(
    [':docente_resumo' => $docenteId],
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

$stmtResumo = $pdo->prepare("
    SELECT
        COUNT(DISTINCT p.id) AS total_producoes,
        COUNT(
            DISTINCT CASE
                WHEN ap.id IS NOT NULL THEN p.id
            END
        ) AS producoes_avaliadas,
        COALESCE(SUM(ap.pontuacao_interna), 0) AS pontuacao,
        COUNT(
            DISTINCT CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM producoes_discentes pdisc
                    WHERE pdisc.producao_id = p.id
                ) THEN p.id
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
        COUNT(DISTINCT CASE WHEN c.codigo = 'MB' THEN p.id END) AS MB,
        COUNT(DISTINCT CASE WHEN c.codigo = 'B' THEN p.id END) AS B,
        COUNT(DISTINCT CASE WHEN c.codigo = 'R' THEN p.id END) AS R,
        COUNT(DISTINCT CASE WHEN c.codigo = 'F' THEN p.id END) AS F,
        COUNT(DISTINCT CASE WHEN c.codigo = 'NC' THEN p.id END) AS NC
    FROM producoes p
    INNER JOIN producoes_docentes pd
        ON pd.producao_id = p.id
    $joinAvaliacaoResumo
    WHERE " . implode(' AND ', $whereResumo)
);

$stmtResumo->execute($parametrosResumo);
$dadosResumo = $stmtResumo->fetch(PDO::FETCH_ASSOC);

if ($dadosResumo) {
    foreach ($resumo as $chave => $valorPadrao) {
        $resumo[$chave] = $chave === 'pontuacao'
            ? (float) ($dadosResumo[$chave] ?? 0)
            : (int) ($dadosResumo[$chave] ?? 0);
    }
}


// ========================================================
// PRODUÇÕES POR ANO
// ========================================================

$filtroAno = montarFiltroPeriodoExcel(
    'p',
    $anoExato,
    $anoInicio,
    $anoFim,
    'ano'
);

$whereAno = array_merge(
    ['pd.docente_id = :docente_ano'],
    $filtroAno['sql']
);

$parametrosAno = array_merge(
    [':docente_ano' => $docenteId],
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


// ========================================================
// PRODUÇÕES POR TIPO
// ========================================================

$filtroTipo = montarFiltroPeriodoExcel(
    'p',
    $anoExato,
    $anoInicio,
    $anoFim,
    'tipo'
);

$whereTipo = array_merge(
    ['pd.docente_id = :docente_tipo'],
    $filtroTipo['sql']
);

$parametrosTipo = array_merge(
    [':docente_tipo' => $docenteId],
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


// ========================================================
// PRODUÇÕES DETALHADAS
// ========================================================

$filtroLista = montarFiltroPeriodoExcel(
    'p',
    $anoExato,
    $anoInicio,
    $anoFim,
    'lista'
);

$whereLista = array_merge(
    ['pd.docente_id = :docente_lista'],
    $filtroLista['sql']
);

$parametrosLista = array_merge(
    [':docente_lista' => $docenteId],
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
        COALESCE(ap.pontuacao_interna, 0) AS pontos
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


// ========================================================
// CONTEÚDO DAS PLANILHAS
// ========================================================

$linhasResumo = [];
$linhasResumo[] = linhaExcel(
    1,
    ['PPGA Analytics — Relatório Individual do Docente'],
    [1],
    [],
    26
);
$linhasResumo[] = linhaExcel(2, ['']);
$linhasResumo[] = linhaExcel(3, ['Docente', $docente['nome']], [3, 0]);
$linhasResumo[] = linhaExcel(4, ['Período', $textoPeriodo], [3, 0]);
$linhasResumo[] = linhaExcel(5, ['E-mail', $docente['email'] ?? ''], [3, 0]);
$linhasResumo[] = linhaExcel(6, ['Lattes', $docente['lattes_id'] ?? ''], [3, 0]);
$linhasResumo[] = linhaExcel(7, ['Gerado em', date('d/m/Y H:i')], [3, 0]);
$linhasResumo[] = linhaExcel(8, ['']);
$linhasResumo[] = linhaExcel(9, ['Indicadores'], [2], [], 22);
$linhasResumo[] = linhaExcel(10, ['Produções', $resumo['total_producoes']], [3, 4], [false, true]);
$linhasResumo[] = linhaExcel(11, ['Produções avaliadas', $resumo['producoes_avaliadas']], [3, 4], [false, true]);
$linhasResumo[] = linhaExcel(12, ['Pontuação CAPES', $resumo['pontuacao']], [3, 5], [false, true]);
$linhasResumo[] = linhaExcel(13, ['Com discentes', $resumo['com_discentes']], [3, 4], [false, true]);
$linhasResumo[] = linhaExcel(14, ['Internacionais', $resumo['internacionais']], [3, 4], [false, true]);
$linhasResumo[] = linhaExcel(15, ['Coautoria internacional', $resumo['coautoria_internacional']], [3, 4], [false, true]);
$linhasResumo[] = linhaExcel(16, ['']);
$linhasResumo[] = linhaExcel(17, ['Classificações CAPES'], [2], [], 22);
$linhasResumo[] = linhaExcel(18, ['MB', $resumo['MB']], [3, 4], [false, true]);
$linhasResumo[] = linhaExcel(19, ['B', $resumo['B']], [3, 4], [false, true]);
$linhasResumo[] = linhaExcel(20, ['R', $resumo['R']], [3, 4], [false, true]);
$linhasResumo[] = linhaExcel(21, ['F', $resumo['F']], [3, 4], [false, true]);
$linhasResumo[] = linhaExcel(22, ['NC', $resumo['NC']], [3, 4], [false, true]);
$linhasResumo[] = linhaExcel(23, ['']);
$linhasResumo[] = linhaExcel(
    24,
    [
        'Observação',
        'Os indicadores CAPES são gerenciais e utilizam o quadriênio ativo como referência. O sistema não produz nota oficial da CAPES.'
    ],
    [3, 6]
);

$xmlResumo = planilhaExcelXml(
    $linhasResumo,
    [24, 70, 15, 15],
    0,
    null,
    ['A1:D1', 'A9:D9', 'A17:D17', 'B24:D24']
);


$linhasProducoes = [];
$cabecalhosProducoes = [
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
];

$linhasProducoes[] = linhaExcel(
    1,
    $cabecalhosProducoes,
    array_fill(0, count($cabecalhosProducoes), 2),
    [],
    28
);

$numeroLinha = 2;

foreach ($producoes as $producao) {
    $linhasProducoes[] = linhaExcel(
        $numeroLinha,
        [
            $producao['titulo'],
            (int) $producao['ano'],
            $producao['tipo'],
            $producao['periodico'] ?? '',
            $producao['issn'] ?? '',
            $producao['doi'] ?? '',
            $producao['discentes'] ?? '',
            $producao['classificacao'] ?? '',
            (float) ($producao['pontos'] ?? 0),
            $producao['internacional'],
            $producao['coautoria_internacional']
        ],
        [6, 4, 6, 6, 0, 6, 6, 0, 5, 0, 0],
        [false, true, false, false, false, false, false, false, true, false, false]
    );

    $numeroLinha++;
}

$ultimaLinhaProducoes = max(1, $numeroLinha - 1);

$xmlProducoes = planilhaExcelXml(
    $linhasProducoes,
    [48, 10, 23, 32, 16, 30, 34, 20, 12, 16, 24],
    1,
    'A1:K' . $ultimaLinhaProducoes
);


$linhasAno = [];
$linhasAno[] = linhaExcel(1, ['Ano', 'Produções'], [2, 2], [], 24);
$numeroLinha = 2;

foreach ($producoesPorAno as $item) {
    $linhasAno[] = linhaExcel(
        $numeroLinha,
        [(int) $item['ano'], (int) $item['quantidade']],
        [4, 4],
        [true, true]
    );
    $numeroLinha++;
}

$ultimaLinhaAno = max(1, $numeroLinha - 1);

$xmlAno = planilhaExcelXml(
    $linhasAno,
    [14, 18],
    1,
    'A1:B' . $ultimaLinhaAno
);


$linhasTipo = [];
$linhasTipo[] = linhaExcel(1, ['Tipo de produção', 'Quantidade'], [2, 2], [], 24);
$numeroLinha = 2;

foreach ($producoesPorTipo as $item) {
    $linhasTipo[] = linhaExcel(
        $numeroLinha,
        [$item['nome'], (int) $item['quantidade']],
        [6, 4],
        [false, true]
    );
    $numeroLinha++;
}

$ultimaLinhaTipo = max(1, $numeroLinha - 1);

$xmlTipo = planilhaExcelXml(
    $linhasTipo,
    [38, 18],
    1,
    'A1:B' . $ultimaLinhaTipo
);


// ========================================================
// ESTRUTURA XLSX (OOXML)
// ========================================================

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet4.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '</Types>';

$relsRaiz = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" '
    . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" '
    . 'Target="xl/workbook.xml"/>'
    . '</Relationships>';

$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook '
    . 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
    . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<bookViews><workbookView/></bookViews>'
    . '<sheets>'
    . '<sheet name="Resumo" sheetId="1" r:id="rId1"/>'
    . '<sheet name="Produções" sheetId="2" r:id="rId2"/>'
    . '<sheet name="Produção por Ano" sheetId="3" r:id="rId3"/>'
    . '<sheet name="Produção por Tipo" sheetId="4" r:id="rId4"/>'
    . '</sheets>'
    . '</workbook>';

$relsWorkbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
    . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>'
    . '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet4.xml"/>'
    . '<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>';

$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="4">'
    . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
    . '<font><b/><color rgb="FFFFFFFF"/><sz val="16"/><name val="Calibri"/></font>'
    . '<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>'
    . '<font><b/><color rgb="FF111827"/><sz val="11"/><name val="Calibri"/></font>'
    . '</fonts>'
    . '<fills count="4">'
    . '<fill><patternFill patternType="none"/></fill>'
    . '<fill><patternFill patternType="gray125"/></fill>'
    . '<fill><patternFill patternType="solid"><fgColor rgb="FF111827"/><bgColor indexed="64"/></patternFill></fill>'
    . '<fill><patternFill patternType="solid"><fgColor rgb="FF166534"/><bgColor indexed="64"/></patternFill></fill>'
    . '</fills>'
    . '<borders count="2">'
    . '<border><left/><right/><top/><bottom/><diagonal/></border>'
    . '<border>'
    . '<left style="thin"><color rgb="FFD1D5DB"/></left>'
    . '<right style="thin"><color rgb="FFD1D5DB"/></right>'
    . '<top style="thin"><color rgb="FFD1D5DB"/></top>'
    . '<bottom style="thin"><color rgb="FFD1D5DB"/></bottom>'
    . '<diagonal/>'
    . '</border>'
    . '</borders>'
    . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    . '<cellXfs count="7">'
    // 0 - padrão
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
    // 1 - título
    . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyFont="1"><alignment vertical="center"/></xf>'
    // 2 - cabeçalho / seção
    . '<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFill="1" applyFont="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>'
    // 3 - rótulo
    . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
    // 4 - inteiro com borda leve
    . '<xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment vertical="top"/></xf>'
    // 5 - número decimal/pontuação com borda leve
    . '<xf numFmtId="2" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment vertical="top"/></xf>'
    // 6 - texto quebrável com borda leve
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="top" wrapText="1"/></xf>'
    . '</cellXfs>'
    . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
    . '</styleSheet>';


// ========================================================
// CRIA ARQUIVO XLSX
// ========================================================

$nomeSeguro = preg_replace(
    '/[^a-z0-9]+/i',
    '_',
    iconv(
        'UTF-8',
        'ASCII//TRANSLIT//IGNORE',
        $docente['nome']
    ) ?: 'docente'
);

$nomeSeguro = trim((string) $nomeSeguro, '_');
$nomeArquivo = 'relatorio_' . strtolower($nomeSeguro) . '.xlsx';

$temporarioBase = tempnam(sys_get_temp_dir(), 'ppga_excel_');

if ($temporarioBase === false) {
    http_response_code(500);
    exit('Não foi possível criar o arquivo temporário do Excel.');
}

@unlink($temporarioBase);
$arquivoZip = $temporarioBase . '.zip';

$usaZipArchive = class_exists('ZipArchive');

if ($usaZipArchive) {
    $zip = new ZipArchive();

    $abriu = $zip->open(
        $arquivoZip,
        ZipArchive::CREATE | ZipArchive::OVERWRITE
    );

    if ($abriu !== true) {
        http_response_code(500);
        exit('Não foi possível iniciar a exportação Excel.');
    }
}
elseif (class_exists('PharData')) {
    try {
        $zip = new PharData(
            $arquivoZip,
            0,
            null,
            Phar::ZIP
        );
    }
    catch (Throwable $erro) {
        http_response_code(500);
        exit('Não foi possível iniciar a exportação Excel.');
    }
}
else {
    http_response_code(500);
    exit(
        'A exportação Excel requer ZipArchive ou PharData habilitado no PHP.'
    );
}

adicionarArquivoAoZip($zip, $usaZipArchive, '[Content_Types].xml', $contentTypes);
adicionarArquivoAoZip($zip, $usaZipArchive, '_rels/.rels', $relsRaiz);
adicionarArquivoAoZip($zip, $usaZipArchive, 'xl/workbook.xml', $workbook);
adicionarArquivoAoZip($zip, $usaZipArchive, 'xl/_rels/workbook.xml.rels', $relsWorkbook);
adicionarArquivoAoZip($zip, $usaZipArchive, 'xl/styles.xml', $styles);
adicionarArquivoAoZip($zip, $usaZipArchive, 'xl/worksheets/sheet1.xml', $xmlResumo);
adicionarArquivoAoZip($zip, $usaZipArchive, 'xl/worksheets/sheet2.xml', $xmlProducoes);
adicionarArquivoAoZip($zip, $usaZipArchive, 'xl/worksheets/sheet3.xml', $xmlAno);
adicionarArquivoAoZip($zip, $usaZipArchive, 'xl/worksheets/sheet4.xml', $xmlTipo);

if ($usaZipArchive) {
    $zip->close();
}
else {
    unset($zip);
}

if (!is_file($arquivoZip) || filesize($arquivoZip) === 0) {
    http_response_code(500);
    exit('Falha ao finalizar o arquivo Excel.');
}


// ========================================================
// DOWNLOAD
// ========================================================

while (ob_get_level() > 0) {
    ob_end_clean();
}

header(
    'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
);
header(
    'Content-Disposition: attachment; filename="'
    . $nomeArquivo
    . '"'
);
header('Content-Length: ' . filesize($arquivoZip));
header('Cache-Control: max-age=0, no-cache, must-revalidate');
header('Pragma: public');

readfile($arquivoZip);
@unlink($arquivoZip);
exit;