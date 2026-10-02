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

function montarFiltroPeriodoWord(
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

function textoXmlWord(mixed $valor): string
{
    $texto = (string) ($valor ?? '');

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

function textoWord(
    mixed $texto,
    bool $negrito = false,
    int $tamanho = 20,
    ?string $cor = null,
    bool $italico = false
): string {
    $propriedades = '<w:rPr>';

    if ($negrito) {
        $propriedades .= '<w:b/><w:bCs/>';
    }

    if ($italico) {
        $propriedades .= '<w:i/><w:iCs/>';
    }

    $propriedades .= '<w:sz w:val="' . $tamanho . '"/>';
    $propriedades .= '<w:szCs w:val="' . $tamanho . '"/>';

    if ($cor !== null && $cor !== '') {
        $propriedades .= '<w:color w:val="' . textoXmlWord($cor) . '"/>';
    }

    $propriedades .= '</w:rPr>';

    return '<w:r>'
        . $propriedades
        . '<w:t xml:space="preserve">'
        . textoXmlWord($texto)
        . '</w:t>'
        . '</w:r>';
}

function paragrafoWord(
    mixed $texto = '',
    bool $negrito = false,
    int $tamanho = 20,
    string $alinhamento = 'left',
    int $espacoDepois = 80,
    ?string $cor = null,
    bool $italico = false
): string {
    $mapaAlinhamento = [
        'left' => 'left',
        'center' => 'center',
        'right' => 'right',
        'justify' => 'both'
    ];

    $alinhamentoWord = $mapaAlinhamento[$alinhamento] ?? 'left';

    return '<w:p>'
        . '<w:pPr>'
        . '<w:jc w:val="' . $alinhamentoWord . '"/>'
        . '<w:spacing w:after="' . max(0, $espacoDepois) . '"/>'
        . '</w:pPr>'
        . textoWord($texto, $negrito, $tamanho, $cor, $italico)
        . '</w:p>';
}

function paragrafoComRotuloWord(
    string $rotulo,
    mixed $valor,
    int $tamanho = 20
): string {
    return '<w:p>'
        . '<w:pPr><w:spacing w:after="70"/></w:pPr>'
        . textoWord($rotulo . ': ', true, $tamanho)
        . textoWord($valor, false, $tamanho)
        . '</w:p>';
}

function celulaWord(
    mixed $conteudo,
    int $largura,
    bool $negrito = false,
    string $fundo = '',
    int $tamanho = 18,
    string $alinhamento = 'left'
): string {
    $sombreamento = $fundo !== ''
        ? '<w:shd w:val="clear" w:color="auto" w:fill="' . textoXmlWord($fundo) . '"/>'
        : '';

    $corTexto = $fundo !== '' ? 'FFFFFF' : null;

    return '<w:tc>'
        . '<w:tcPr>'
        . '<w:tcW w:w="' . max(200, $largura) . '" w:type="dxa"/>'
        . '<w:vAlign w:val="center"/>'
        . $sombreamento
        . '</w:tcPr>'
        . paragrafoWord(
            $conteudo,
            $negrito,
            $tamanho,
            $alinhamento,
            0,
            $corTexto
        )
        . '</w:tc>';
}

function linhaTabelaWord(
    array $valores,
    array $larguras,
    bool $cabecalho = false,
    int $tamanho = 18,
    array $alinhamentos = []
): string {
    $xml = '<w:tr>';

    if ($cabecalho) {
        $xml .= '<w:trPr><w:tblHeader/></w:trPr>';
    }

    foreach ($valores as $indice => $valor) {
        $largura = (int) ($larguras[$indice] ?? 1000);
        $alinhamento = (string) ($alinhamentos[$indice] ?? 'left');

        $xml .= celulaWord(
            $valor,
            $largura,
            $cabecalho,
            $cabecalho ? '1F2937' : '',
            $tamanho,
            $alinhamento
        );
    }

    return $xml . '</w:tr>';
}

function tabelaWord(
    array $cabecalhos,
    array $linhas,
    array $larguras,
    int $tamanho = 18,
    array $alinhamentos = []
): string {
    $xml = '<w:tbl>'
        . '<w:tblPr>'
        . '<w:tblW w:w="0" w:type="auto"/>'
        . '<w:tblLayout w:type="fixed"/>'
        . '<w:tblBorders>'
        . '<w:top w:val="single" w:sz="4" w:color="D1D5DB"/>'
        . '<w:left w:val="single" w:sz="4" w:color="D1D5DB"/>'
        . '<w:bottom w:val="single" w:sz="4" w:color="D1D5DB"/>'
        . '<w:right w:val="single" w:sz="4" w:color="D1D5DB"/>'
        . '<w:insideH w:val="single" w:sz="4" w:color="D1D5DB"/>'
        . '<w:insideV w:val="single" w:sz="4" w:color="D1D5DB"/>'
        . '</w:tblBorders>'
        . '<w:tblCellMar>'
        . '<w:top w:w="70" w:type="dxa"/>'
        . '<w:left w:w="90" w:type="dxa"/>'
        . '<w:bottom w:w="70" w:type="dxa"/>'
        . '<w:right w:w="90" w:type="dxa"/>'
        . '</w:tblCellMar>'
        . '</w:tblPr>';

    if ($cabecalhos !== []) {
        $xml .= linhaTabelaWord(
            $cabecalhos,
            $larguras,
            true,
            $tamanho,
            $alinhamentos
        );
    }

    foreach ($linhas as $linha) {
        $xml .= linhaTabelaWord(
            $linha,
            $larguras,
            false,
            $tamanho,
            $alinhamentos
        );
    }

    return $xml . '</w:tbl>';
}

function tituloSecaoWord(string $titulo): string
{
    return '<w:p>'
        . '<w:pPr>'
        . '<w:spacing w:before="220" w:after="100"/>'
        . '<w:keepNext/>'
        . '</w:pPr>'
        . textoWord($titulo, true, 24, '111827')
        . '</w:p>';
}

function quebraPaginaWord(): string
{
    return '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
}

function adicionarArquivoAoZipWord(
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

$filtroResumo = montarFiltroPeriodoWord(
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

$filtroAno = montarFiltroPeriodoWord(
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

$filtroTipo = montarFiltroPeriodoWord(
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

$filtroLista = montarFiltroPeriodoWord(
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
// DOCUMENTO WORD
// ========================================================

$corPrincipal = '166534';
$corEscura = '111827';

$corpo = '';

$corpo .= '<w:p>'
    . '<w:pPr><w:jc w:val="center"/><w:spacing w:after="80"/></w:pPr>'
    . textoWord('PPGA Analytics', true, 34, $corPrincipal)
    . '</w:p>';

$corpo .= '<w:p>'
    . '<w:pPr><w:jc w:val="center"/><w:spacing w:after="220"/></w:pPr>'
    . textoWord('Relatório Individual do Docente', true, 26, $corEscura)
    . '</w:p>';

$corpo .= paragrafoComRotuloWord('Docente', $docente['nome'], 21);
$corpo .= paragrafoComRotuloWord('Período analisado', $textoPeriodo, 21);

if (!empty($docente['email'])) {
    $corpo .= paragrafoComRotuloWord('E-mail', $docente['email'], 20);
}

if (!empty($docente['lattes_id'])) {
    $corpo .= paragrafoComRotuloWord('Lattes', $docente['lattes_id'], 20);
}

$corpo .= paragrafoComRotuloWord('Gerado em', date('d/m/Y H:i'), 20);

$corpo .= tituloSecaoWord('Resumo de indicadores');

$linhasResumoWord = [
    ['Produções', (string) $resumo['total_producoes']],
    ['Produções avaliadas', (string) $resumo['producoes_avaliadas']],
    ['Pontuação CAPES', number_format((float) $resumo['pontuacao'], 0, ',', '.')],
    ['Com discentes', (string) $resumo['com_discentes']],
    ['Internacionais', (string) $resumo['internacionais']],
    ['Coautoria internacional', (string) $resumo['coautoria_internacional']]
];

$corpo .= tabelaWord(
    ['Indicador', 'Valor'],
    $linhasResumoWord,
    [4300, 1800],
    19,
    ['left', 'center']
);

$corpo .= tituloSecaoWord('Classificações CAPES');

$linhasCapesWord = [];

foreach (['MB', 'B', 'R', 'F', 'NC'] as $codigo) {
    $linhasCapesWord[] = [
        $codigo,
        (string) $resumo[$codigo]
    ];
}

$corpo .= tabelaWord(
    ['Classificação', 'Quantidade'],
    $linhasCapesWord,
    [3200, 1800],
    19,
    ['left', 'center']
);

$corpo .= tituloSecaoWord('Produções por ano');

$linhasAnoWord = [];

foreach ($producoesPorAno as $item) {
    $linhasAnoWord[] = [
        (string) $item['ano'],
        (string) ((int) $item['quantidade'])
    ];
}

if ($linhasAnoWord === []) {
    $linhasAnoWord[] = ['—', '0'];
}

$corpo .= tabelaWord(
    ['Ano', 'Quantidade'],
    $linhasAnoWord,
    [2600, 1800],
    19,
    ['center', 'center']
);

$corpo .= tituloSecaoWord('Produções por tipo');

$linhasTipoWord = [];

foreach ($producoesPorTipo as $item) {
    $linhasTipoWord[] = [
        (string) $item['nome'],
        (string) ((int) $item['quantidade'])
    ];
}

if ($linhasTipoWord === []) {
    $linhasTipoWord[] = ['—', '0'];
}

$corpo .= tabelaWord(
    ['Tipo de produção', 'Quantidade'],
    $linhasTipoWord,
    [4300, 1800],
    19,
    ['left', 'center']
);

$corpo .= quebraPaginaWord();
$corpo .= tituloSecaoWord('Produções detalhadas');

$corpo .= paragrafoWord(
    count($producoes) . ' registro(s) no período analisado.',
    false,
    18,
    'left',
    120,
    '4B5563'
);

$linhasProducoesWord = [];

foreach ($producoes as $producao) {
    $linhasProducoesWord[] = [
        (string) ($producao['ano'] ?? '—'),
        (string) ($producao['tipo'] ?? '—'),
        (string) ($producao['titulo'] ?? '—'),
        (string) ($producao['periodico'] ?? '—'),
        !empty($producao['discentes'])
            ? (string) $producao['discentes']
            : '—',
        !empty($producao['classificacao'])
            ? (string) $producao['classificacao']
            : 'NC',
        number_format((float) ($producao['pontos'] ?? 0), 0, ',', '.'),
        (string) ($producao['internacional'] ?? 'Não')
    ];
}

if ($linhasProducoesWord === []) {
    $linhasProducoesWord[] = [
        '—',
        '—',
        'Nenhuma produção encontrada.',
        '—',
        '—',
        '—',
        '0',
        'Não'
    ];
}

$corpo .= tabelaWord(
    [
        'Ano',
        'Tipo',
        'Produção',
        'Periódico',
        'Discente(s)',
        'CAPES',
        'Pontos',
        'Internacional'
    ],
    $linhasProducoesWord,
    [
        760,
        1500,
        3600,
        2200,
        2100,
        850,
        850,
        1100
    ],
    16,
    [
        'center',
        'left',
        'left',
        'left',
        'left',
        'center',
        'center',
        'center'
    ]
);

$corpo .= tituloSecaoWord('Nota metodológica');
$corpo .= paragrafoWord(
    'Os indicadores CAPES apresentados neste relatório são gerenciais e utilizam o quadriênio ativo como referência. O sistema não gera nota oficial da CAPES.',
    false,
    18,
    'justify',
    120,
    '4B5563',
    true
);

$documentoXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:document '
    . 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
    . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<w:body>'
    . $corpo
    . '<w:sectPr>'
    // A4 paisagem: 297 x 210 mm em twips.
    . '<w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
    . '<w:pgMar '
    . 'w:top="720" w:right="720" w:bottom="720" w:left="720" '
    . 'w:header="360" w:footer="360" w:gutter="0"/>'
    . '</w:sectPr>'
    . '</w:body>'
    . '</w:document>';

$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    . '<w:docDefaults>'
    . '<w:rPrDefault><w:rPr>'
    . '<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:eastAsia="Calibri" w:cs="Calibri"/>'
    . '<w:sz w:val="20"/><w:szCs w:val="20"/>'
    . '</w:rPr></w:rPrDefault>'
    . '<w:pPrDefault><w:pPr><w:spacing w:after="80"/></w:pPr></w:pPrDefault>'
    . '</w:docDefaults>'
    . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal">'
    . '<w:name w:val="Normal"/>'
    . '<w:qFormat/>'
    . '</w:style>'
    . '</w:styles>';

$settingsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    . '<w:zoom w:percent="90"/>'
    . '<w:compat/>'
    . '</w:settings>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
    . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
    . '<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>'
    . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
    . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
    . '</Types>';

$relsRaiz = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" '
    . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" '
    . 'Target="word/document.xml"/>'
    . '<Relationship Id="rId2" '
    . 'Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" '
    . 'Target="docProps/core.xml"/>'
    . '<Relationship Id="rId3" '
    . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" '
    . 'Target="docProps/app.xml"/>'
    . '</Relationships>';

$relsDocumento = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" '
    . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" '
    . 'Target="styles.xml"/>'
    . '<Relationship Id="rId2" '
    . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" '
    . 'Target="settings.xml"/>'
    . '</Relationships>';

$dataIso = gmdate('Y-m-d\TH:i:s\Z');

$coreXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<cp:coreProperties '
    . 'xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
    . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
    . 'xmlns:dcterms="http://purl.org/dc/terms/" '
    . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
    . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
    . '<dc:title>Relatório Individual do Docente</dc:title>'
    . '<dc:subject>PPGA Analytics</dc:subject>'
    . '<dc:creator>PPGA Analytics</dc:creator>'
    . '<cp:lastModifiedBy>PPGA Analytics</cp:lastModifiedBy>'
    . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $dataIso . '</dcterms:created>'
    . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $dataIso . '</dcterms:modified>'
    . '</cp:coreProperties>';

$appXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Properties '
    . 'xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
    . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
    . '<Application>PPGA Analytics</Application>'
    . '<Company>PPGA</Company>'
    . '</Properties>';


// ========================================================
// CRIA ARQUIVO DOCX
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
$nomeArquivo = 'relatorio_' . strtolower($nomeSeguro) . '.docx';

$temporarioBase = tempnam(sys_get_temp_dir(), 'ppga_word_');

if ($temporarioBase === false) {
    http_response_code(500);
    exit('Não foi possível criar o arquivo temporário do Word.');
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
        exit('Não foi possível iniciar a exportação Word.');
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
        exit('Não foi possível iniciar a exportação Word.');
    }
}
else {
    http_response_code(500);
    exit(
        'A exportação Word requer ZipArchive ou PharData habilitado no PHP.'
    );
}

adicionarArquivoAoZipWord($zip, $usaZipArchive, '[Content_Types].xml', $contentTypes);
adicionarArquivoAoZipWord($zip, $usaZipArchive, '_rels/.rels', $relsRaiz);
adicionarArquivoAoZipWord($zip, $usaZipArchive, 'word/document.xml', $documentoXml);
adicionarArquivoAoZipWord($zip, $usaZipArchive, 'word/styles.xml', $stylesXml);
adicionarArquivoAoZipWord($zip, $usaZipArchive, 'word/settings.xml', $settingsXml);
adicionarArquivoAoZipWord($zip, $usaZipArchive, 'word/_rels/document.xml.rels', $relsDocumento);
adicionarArquivoAoZipWord($zip, $usaZipArchive, 'docProps/core.xml', $coreXml);
adicionarArquivoAoZipWord($zip, $usaZipArchive, 'docProps/app.xml', $appXml);

if ($usaZipArchive) {
    $zip->close();
}
else {
    unset($zip);
}

if (!is_file($arquivoZip) || filesize($arquivoZip) === 0) {
    http_response_code(500);
    exit('Falha ao finalizar o arquivo Word.');
}


// ========================================================
// DOWNLOAD
// ========================================================

while (ob_get_level() > 0) {
    ob_end_clean();
}

header(
    'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document'
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