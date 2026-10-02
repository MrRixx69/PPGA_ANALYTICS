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

function textoSeguroPdf(?string $texto): string
{
    $texto = trim((string) $texto);

    if ($texto === '') {
        return '—';
    }

    return $texto;
}

function montarFiltroPeriodoPdf(
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
// GERADOR PDF SIMPLES - SEM DEPENDÊNCIAS EXTERNAS
// ========================================================

class PdfRelatorio
{
    private const MM_PARA_PT = 72 / 25.4;

    private float $larguraMm = 210.0;
    private float $alturaMm = 297.0;
    private float $margemEsquerda = 14.0;
    private float $margemDireita = 14.0;
    private float $margemInferior = 16.0;

    private array $paginas = [];
    private int $paginaAtual = -1;
    private float $y = 18.0;

    private string $tituloCabecalho = 'PPGA Analytics';
    private string $subtituloCabecalho = 'Relatório Individual do Docente';

    public function adicionarPagina(): void
    {
        $this->paginas[] = '';
        $this->paginaAtual = count($this->paginas) - 1;
        $this->y = 18.0;

        $this->desenharCabecalho();
    }

    public function y(): float
    {
        return $this->y;
    }

    public function definirY(float $y): void
    {
        $this->y = $y;
    }

    public function avancar(float $mm): void
    {
        $this->y += $mm;
    }

    public function precisaQuebra(float $alturaNecessaria): bool
    {
        return ($this->y + $alturaNecessaria) > ($this->alturaMm - $this->margemInferior);
    }

    public function garantirEspaco(float $alturaNecessaria): void
    {
        if ($this->precisaQuebra($alturaNecessaria)) {
            $this->adicionarPagina();
        }
    }

    public function texto(
        float $x,
        float $y,
        string $texto,
        float $tamanho = 10,
        bool $negrito = false
    ): void {
        $fonte = $negrito ? 'F2' : 'F1';
        $texto = $this->escaparTexto($texto);
        $xPt = $this->mmParaPt($x);
        $yPt = $this->mmParaPt($this->alturaMm - $y);

        $this->escrever(
            "BT /{$fonte} {$tamanho} Tf 0 g "
            . $this->numero($xPt) . ' '
            . $this->numero($yPt) . " Td ({$texto}) Tj ET\n"
        );
    }

    public function linha(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $espessura = 0.25
    ): void {
        $this->escrever(
            '0 G '
            . $this->numero($espessura) . ' w '
            . $this->numero($this->mmParaPt($x1)) . ' '
            . $this->numero($this->mmParaPt($this->alturaMm - $y1)) . ' m '
            . $this->numero($this->mmParaPt($x2)) . ' '
            . $this->numero($this->mmParaPt($this->alturaMm - $y2)) . " l S\n"
        );
    }

    public function retangulo(
        float $x,
        float $y,
        float $largura,
        float $altura,
        bool $preenchido = false,
        float $cinza = 0.95
    ): void {
        $xPt = $this->mmParaPt($x);
        $yPt = $this->mmParaPt($this->alturaMm - $y - $altura);
        $wPt = $this->mmParaPt($largura);
        $hPt = $this->mmParaPt($altura);

        if ($preenchido) {
            $cinza = max(0, min(1, $cinza));
            $this->escrever(
                $this->numero($cinza) . ' g 0 G '
                . $this->numero($xPt) . ' '
                . $this->numero($yPt) . ' '
                . $this->numero($wPt) . ' '
                . $this->numero($hPt) . " re B\n"
            );
        }
        else {
            $this->escrever(
                '0 G '
                . $this->numero($xPt) . ' '
                . $this->numero($yPt) . ' '
                . $this->numero($wPt) . ' '
                . $this->numero($hPt) . " re S\n"
            );
        }
    }

    public function tituloSecao(string $titulo): void
    {
        $this->garantirEspaco(10);

        $this->texto(
            $this->margemEsquerda,
            $this->y,
            $titulo,
            12,
            true
        );

        $this->y += 3.0;
        $this->linha(
            $this->margemEsquerda,
            $this->y,
            $this->larguraMm - $this->margemDireita,
            $this->y,
            0.35
        );
        $this->y += 5.0;
    }

    public function paragrafo(
        string $texto,
        float $largura = 182.0,
        float $tamanho = 9,
        float $alturaLinha = 4.2,
        bool $negrito = false
    ): void {
        $linhas = $this->quebrarTexto($texto, $largura, $tamanho);
        $altura = max(1, count($linhas)) * $alturaLinha;
        $this->garantirEspaco($altura + 2);

        foreach ($linhas as $linha) {
            $this->texto(
                $this->margemEsquerda,
                $this->y,
                $linha,
                $tamanho,
                $negrito
            );
            $this->y += $alturaLinha;
        }
    }

    public function linhasTexto(
        string $texto,
        float $largura,
        float $tamanho
    ): array {
        return $this->quebrarTexto($texto, $largura, $tamanho);
    }

    public function caixaIndicador(
        float $x,
        float $y,
        float $largura,
        string $titulo,
        string $valor
    ): void {
        $this->retangulo($x, $y, $largura, 18, true, 0.965);
        $this->texto($x + 3, $y + 6, $titulo, 8, false);
        $this->texto($x + 3, $y + 13, $valor, 15, true);
    }

    public function barra(
        string $rotulo,
        int $valor,
        int $maximo,
        float $larguraMaxima = 118.0
    ): void {
        $this->garantirEspaco(7);

        $this->texto(
            $this->margemEsquerda,
            $this->y,
            $rotulo,
            8,
            false
        );

        $xBarra = $this->margemEsquerda + 50;
        $this->retangulo($xBarra, $this->y - 3, $larguraMaxima, 4, true, 0.96);

        if ($maximo > 0 && $valor > 0) {
            $largura = ($valor / $maximo) * $larguraMaxima;
            $largura = max(1.0, min($larguraMaxima, $largura));
            $this->retangulo($xBarra, $this->y - 3, $largura, 4, true, 0.66);
        }

        $this->texto($xBarra + $larguraMaxima + 3, $this->y, (string) $valor, 8, true);
        $this->y += 7;
    }

    public function cabecalhoTabela(): void
    {
        $this->garantirEspaco(12);

        $x = $this->margemEsquerda;
        $y = $this->y;
        $alt = 8.0;

        $larguras = [12, 28, 72, 16, 12, 32, 10];
        $titulos = ['Ano', 'Tipo', 'Produção / periódico', 'CAPES', 'Pts', 'Discente(s)', 'Int.'];

        foreach ($larguras as $i => $largura) {
            $this->retangulo($x, $y, $largura, $alt, true, 0.90);
            $this->texto($x + 1.5, $y + 5.2, $titulos[$i], 7.5, true);
            $x += $largura;
        }

        $this->y += $alt;
    }

    public function linhaTabela(array $producao): void
    {
        $larguras = [12, 28, 72, 16, 12, 32, 10];
        $tamanho = 7.2;
        $alturaLinha = 3.8;

        $tituloCompleto = textoSeguroPdf($producao['titulo'] ?? '');

        if (!empty($producao['periodico'])) {
            $tituloCompleto .= ' | ' . $producao['periodico'];
        }

        $linhasTitulo = $this->quebrarTexto($tituloCompleto, 68, $tamanho);
        $linhasDiscente = $this->quebrarTexto(
            textoSeguroPdf($producao['discentes'] ?? ''),
            28,
            $tamanho
        );
        $linhasTipo = $this->quebrarTexto(
            textoSeguroPdf($producao['tipo'] ?? ''),
            24,
            $tamanho
        );

        $qtdLinhas = max(
            1,
            count($linhasTitulo),
            count($linhasDiscente),
            count($linhasTipo)
        );

        $altura = max(8.0, 3.0 + ($qtdLinhas * $alturaLinha));

        if ($this->precisaQuebra($altura + 3)) {
            $this->adicionarPagina();
            $this->tituloSecao('Produções detalhadas - continuação');
            $this->cabecalhoTabela();
        }

        $x = $this->margemEsquerda;
        $y = $this->y;

        foreach ($larguras as $largura) {
            $this->retangulo($x, $y, $largura, $altura, false);
            $x += $largura;
        }

        $x = $this->margemEsquerda;

        $this->texto(
            $x + 1.5,
            $y + 5,
            (string) ((int) ($producao['ano'] ?? 0)),
            $tamanho
        );
        $x += $larguras[0];

        $this->escreverLinhasCelula($x + 1.5, $y + 4.5, $linhasTipo, $tamanho, $alturaLinha);
        $x += $larguras[1];

        $this->escreverLinhasCelula($x + 1.5, $y + 4.5, $linhasTitulo, $tamanho, $alturaLinha);
        $x += $larguras[2];

        $classificacao = !empty($producao['classificacao'])
            ? (string) $producao['classificacao']
            : '—';

        $this->texto($x + 1.5, $y + 5, $classificacao, $tamanho, true);
        $x += $larguras[3];

        $pontos = !empty($producao['classificacao'])
            ? number_format((float) ($producao['pontos'] ?? 0), 0, ',', '.')
            : '—';

        $this->texto($x + 1.5, $y + 5, $pontos, $tamanho);
        $x += $larguras[4];

        $this->escreverLinhasCelula($x + 1.5, $y + 4.5, $linhasDiscente, $tamanho, $alturaLinha);
        $x += $larguras[5];

        $internacional = (
            (int) ($producao['internacional'] ?? 0) === 1
            || (int) ($producao['coautoria_internacional'] ?? 0) === 1
        ) ? 'Sim' : 'Não';

        $this->texto($x + 1.0, $y + 5, $internacional, $tamanho);

        $this->y += $altura;
    }

    public function gerar(string $nomeArquivo): void
    {
        if (count($this->paginas) === 0) {
            $this->adicionarPagina();
        }

        foreach ($this->paginas as $indice => $conteudo) {
            $numeroPagina = $indice + 1;
            $totalPaginas = count($this->paginas);

            $rodape = 'PPGA Analytics | Página '
                . $numeroPagina
                . ' de '
                . $totalPaginas;

            $this->paginaAtual = $indice;
            $this->texto(14, 289, $rodape, 7, false);
        }

        $pdf = $this->montarDocumento();

        header('Content-Type: application/pdf');
        header(
            'Content-Disposition: attachment; filename="'
            . $nomeArquivo
            . '"'
        );
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdf;
        exit;
    }

    private function desenharCabecalho(): void
    {
        $this->texto(14, 12, $this->tituloCabecalho, 16, true);
        $this->texto(14, 17, $this->subtituloCabecalho, 8.5, false);
        $this->linha(14, 21, 196, 21, 0.5);
        $this->y = 28;
    }

    private function escreverLinhasCelula(
        float $x,
        float $y,
        array $linhas,
        float $tamanho,
        float $alturaLinha
    ): void {
        foreach ($linhas as $linha) {
            $this->texto($x, $y, $linha, $tamanho);
            $y += $alturaLinha;
        }
    }

    private function quebrarTexto(
        string $texto,
        float $larguraMm,
        float $tamanho
    ): array {
        $texto = preg_replace('/\s+/u', ' ', trim($texto)) ?? '';

        if ($texto === '') {
            return ['—'];
        }

        $palavras = preg_split('/\s+/u', $texto) ?: [$texto];
        $linhas = [];
        $linha = '';

        foreach ($palavras as $palavra) {
            $teste = $linha === '' ? $palavra : $linha . ' ' . $palavra;

            if ($this->larguraTextoAproximada($teste, $tamanho) <= $larguraMm) {
                $linha = $teste;
                continue;
            }

            if ($linha !== '') {
                $linhas[] = $linha;
                $linha = '';
            }

            if ($this->larguraTextoAproximada($palavra, $tamanho) <= $larguraMm) {
                $linha = $palavra;
                continue;
            }

            $pedaco = '';
            $caracteres = preg_split('//u', $palavra, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($caracteres as $caractere) {
                $testePedaco = $pedaco . $caractere;

                if (
                    $pedaco !== ''
                    && $this->larguraTextoAproximada($testePedaco, $tamanho) > $larguraMm
                ) {
                    $linhas[] = $pedaco;
                    $pedaco = $caractere;
                }
                else {
                    $pedaco = $testePedaco;
                }
            }

            $linha = $pedaco;
        }

        if ($linha !== '') {
            $linhas[] = $linha;
        }

        return $linhas ?: ['—'];
    }

    private function larguraTextoAproximada(string $texto, float $tamanho): float
    {
        $quantidade = function_exists('mb_strlen')
            ? mb_strlen($texto, 'UTF-8')
            : strlen($texto);

        return $quantidade * $tamanho * 0.18;
    }

    private function escaparTexto(string $texto): string
    {
        $convertido = iconv(
            'UTF-8',
            'Windows-1252//TRANSLIT//IGNORE',
            $texto
        );

        if ($convertido === false) {
            $convertido = $texto;
        }

        $convertido = str_replace('\\', '\\\\', $convertido);
        $convertido = str_replace('(', '\\(', $convertido);
        $convertido = str_replace(')', '\\)', $convertido);
        $convertido = str_replace(["\r", "\n"], ' ', $convertido);

        return $convertido;
    }

    private function mmParaPt(float $mm): float
    {
        return $mm * self::MM_PARA_PT;
    }

    private function numero(float $numero): string
    {
        return rtrim(rtrim(number_format($numero, 3, '.', ''), '0'), '.');
    }

    private function escrever(string $comando): void
    {
        if ($this->paginaAtual < 0) {
            $this->adicionarPagina();
        }

        $this->paginas[$this->paginaAtual] .= $comando;
    }

    private function montarDocumento(): string
    {
        $objetos = [];

        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $idsPaginas = [];
        $proximoId = 5;

        foreach ($this->paginas as $pagina) {
            $idPagina = $proximoId++;
            $idConteudo = $proximoId++;
            $idsPaginas[] = $idPagina . ' 0 R';

            $objetos[$idPagina] =
                '<< /Type /Page /Parent 2 0 R '
                . '/MediaBox [0 0 '
                . $this->numero($this->mmParaPt($this->larguraMm)) . ' '
                . $this->numero($this->mmParaPt($this->alturaMm)) . '] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> '
                . '/Contents ' . $idConteudo . ' 0 R >>';

            $objetos[$idConteudo] =
                '<< /Length ' . strlen($pagina) . " >>\nstream\n"
                . $pagina
                . "endstream";
        }

        $objetos[2] =
            '<< /Type /Pages /Count '
            . count($idsPaginas)
            . ' /Kids ['
            . implode(' ', $idsPaginas)
            . '] >>';

        $objetos[3] =
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica '
            . '/Encoding /WinAnsiEncoding >>';

        $objetos[4] =
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold '
            . '/Encoding /WinAnsiEncoding >>';

        ksort($objetos);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        $maxId = max(array_keys($objetos));

        for ($id = 1; $id <= $maxId; $id++) {
            if (!isset($objetos[$id])) {
                continue;
            }

            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n";
            $pdf .= $objetos[$id] . "\n";
            $pdf .= "endobj\n";
        }

        $inicioXref = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id <= $maxId; $id++) {
            $offset = $offsets[$id] ?? 0;
            $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
        }

        $pdf .= "trailer\n";
        $pdf .= '<< /Size ' . ($maxId + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= "startxref\n";
        $pdf .= $inicioXref . "\n";
        $pdf .= "%%EOF";

        return $pdf;
    }
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
        lattes_id
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
    $textoPeriodo = $anoInicio . '-' . $anoFim;
}
elseif ($anoInicio > 0) {
    $textoPeriodo = 'A partir de ' . $anoInicio;
}
elseif ($anoFim > 0) {
    $textoPeriodo = 'Até ' . $anoFim;
}


// ========================================================
// RESUMO GERAL
// ========================================================

$filtroResumo = montarFiltroPeriodoPdf(
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

$resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [];


// ========================================================
// PRODUÇÕES POR ANO
// ========================================================

$filtroAno = montarFiltroPeriodoPdf(
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

$filtroTipo = montarFiltroPeriodoPdf(
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

$filtroLista = montarFiltroPeriodoPdf(
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


// ========================================================
// NOME DO ARQUIVO
// ========================================================

$nomeAscii = iconv(
    'UTF-8',
    'ASCII//TRANSLIT//IGNORE',
    (string) $docente['nome']
);

$nomeSeguro = preg_replace(
    '/[^a-z0-9]+/i',
    '_',
    $nomeAscii ?: 'docente'
);

$nomeSeguro = trim((string) $nomeSeguro, '_');

$arquivo = 'relatorio_'
    . strtolower($nomeSeguro)
    . '_'
    . date('Ymd_His')
    . '.pdf';


// ========================================================
// MONTAGEM DO PDF
// ========================================================

$pdf = new PdfRelatorio();
$pdf->adicionarPagina();

$pdf->texto(14, 31, 'Docente', 8, false);
$pdf->texto(14, 38, (string) $docente['nome'], 15, true);
$pdf->texto(14, 45, 'Período analisado: ' . $textoPeriodo, 9, false);

if (!empty($docente['email'])) {
    $pdf->texto(14, 51, 'E-mail: ' . $docente['email'], 8, false);
}

if (!empty($docente['lattes_id'])) {
    $pdf->texto(110, 51, 'Lattes: ' . $docente['lattes_id'], 8, false);
}

$pdf->definirY(61);
$pdf->tituloSecao('Resumo dos indicadores');

$larguraCaixa = 56.5;
$gap = 6.2;
$x1 = 14;
$x2 = $x1 + $larguraCaixa + $gap;
$x3 = $x2 + $larguraCaixa + $gap;
$yCaixas = $pdf->y();

$pdf->caixaIndicador(
    $x1,
    $yCaixas,
    $larguraCaixa,
    'Produções',
    (string) ((int) ($resumo['total_producoes'] ?? 0))
);

$pdf->caixaIndicador(
    $x2,
    $yCaixas,
    $larguraCaixa,
    'Produções avaliadas',
    (string) ((int) ($resumo['producoes_avaliadas'] ?? 0))
);

$pdf->caixaIndicador(
    $x3,
    $yCaixas,
    $larguraCaixa,
    'Pontuação CAPES',
    number_format((float) ($resumo['pontuacao'] ?? 0), 0, ',', '.')
);

$yCaixas2 = $yCaixas + 23;

$pdf->caixaIndicador(
    $x1,
    $yCaixas2,
    $larguraCaixa,
    'Com discentes',
    (string) ((int) ($resumo['com_discentes'] ?? 0))
);

$pdf->caixaIndicador(
    $x2,
    $yCaixas2,
    $larguraCaixa,
    'Internacionais',
    (string) ((int) ($resumo['internacionais'] ?? 0))
);

$pdf->caixaIndicador(
    $x3,
    $yCaixas2,
    $larguraCaixa,
    'Coautoria internacional',
    (string) ((int) ($resumo['coautoria_internacional'] ?? 0))
);

$pdf->definirY($yCaixas2 + 27);
$pdf->tituloSecao('Classificações CAPES');

$codigos = ['MB', 'B', 'R', 'F', 'NC'];
$larguraCapes = 32.4;
$gapCapes = 5.0;
$xCapes = 14;
$yCapes = $pdf->y();

foreach ($codigos as $codigo) {
    $pdf->caixaIndicador(
        $xCapes,
        $yCapes,
        $larguraCapes,
        $codigo,
        (string) ((int) ($resumo[$codigo] ?? 0))
    );

    $xCapes += $larguraCapes + $gapCapes;
}

$pdf->definirY($yCapes + 24);
$pdf->tituloSecao('Produções por ano');

$maxAno = 0;
foreach ($producoesPorAno as $item) {
    $maxAno = max($maxAno, (int) $item['quantidade']);
}

if (count($producoesPorAno) === 0) {
    $pdf->paragrafo('Nenhuma produção encontrada no período selecionado.');
}
else {
    foreach ($producoesPorAno as $item) {
        $pdf->barra(
            (string) $item['ano'],
            (int) $item['quantidade'],
            $maxAno
        );
    }
}

$pdf->avancar(2);
$pdf->tituloSecao('Produções por tipo');

$maxTipo = 0;
foreach ($producoesPorTipo as $item) {
    $maxTipo = max($maxTipo, (int) $item['quantidade']);
}

if (count($producoesPorTipo) === 0) {
    $pdf->paragrafo('Nenhuma produção encontrada no período selecionado.');
}
else {
    $limiteTipos = 10;
    $contadorTipos = 0;

    foreach ($producoesPorTipo as $item) {
        if ($contadorTipos >= $limiteTipos) {
            break;
        }

        $rotulo = (string) $item['nome'];

        if (function_exists('mb_strlen') && mb_strlen($rotulo, 'UTF-8') > 28) {
            $rotulo = mb_substr($rotulo, 0, 27, 'UTF-8') . '…';
        }
        elseif (strlen($rotulo) > 28) {
            $rotulo = substr($rotulo, 0, 27) . '...';
        }

        $pdf->barra(
            $rotulo,
            (int) $item['quantidade'],
            $maxTipo
        );

        $contadorTipos++;
    }
}

$pdf->adicionarPagina();
$pdf->tituloSecao('Produções detalhadas');
$pdf->paragrafo(
    count($producoes)
    . ' registro(s) no período analisado.',
    182,
    8.5,
    4.0
);
$pdf->avancar(1);
$pdf->cabecalhoTabela();

if (count($producoes) === 0) {
    $pdf->paragrafo('Nenhuma produção encontrada para o período selecionado.');
}
else {
    foreach ($producoes as $producao) {
        $pdf->linhaTabela($producao);
    }
}

$pdf->avancar(6);
$pdf->tituloSecao('Nota metodológica');
$pdf->paragrafo(
    'Os indicadores CAPES apresentados neste relatório são gerenciais e utilizam '
    . 'as avaliações cadastradas no quadriênio ativo. O PPGA Analytics não gera '
    . 'nota oficial da CAPES.',
    182,
    8.5,
    4.2
);

$pdf->paragrafo(
    'Relatório gerado em ' . date('d/m/Y H:i') . '.',
    182,
    8,
    4.0
);

$pdf->gerar($arquivo);