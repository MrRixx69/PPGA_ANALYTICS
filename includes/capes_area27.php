<?php

// ========================================================
// REGRAS DE CLASSIFICAÇÃO - CAPES ÁREA 27 (2025-2028)
// ========================================================
//
// Este arquivo concentra a lógica preliminar de classificação
// dos artigos por periódico. A classificação oficial continua
// dependendo da confirmação humana no módulo de avaliações.
//
// Regra interna baseada no guia técnico do PPGA/USCS.
// ========================================================

const CAPES_AREA27_REGRA_VERSAO = 'AREA27_2025_2028_V1';


function tabelaExisteCapes(
    PDO $pdo,
    string $tabela
): bool {

    $stmt =
        $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = :tabela
        ");

    $stmt->execute([
        ':tabela' => $tabela
    ]);

    return (int) $stmt->fetchColumn() > 0;
}


function colunaExisteCapes(
    PDO $pdo,
    string $tabela,
    string $coluna
): bool {

    $stmt =
        $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = :tabela
              AND column_name = :coluna
        "
        );

    $stmt->execute([
        ':tabela' => $tabela,
        ':coluna' => $coluna
    ]);

    return (int) $stmt->fetchColumn() > 0;
}


function normalizarValorIndicadorArea27(
    ?string $valor
): string {

    $valor = strtoupper(
        trim(
            (string) $valor
        )
    );

    $valor = str_replace(
        [' ', 'º'],
        ['', ''],
        $valor
    );

    return $valor;
}


function classeCandidataIndicadorArea27(
    string $base,
    ?string $valor,
    ?float $percentual = null,
    bool $scieloBrasilConfirmado = false
): ?string {

    $base = strtoupper(trim($base));
    $valorNormalizado =
        normalizarValorIndicadorArea27(
            $valor
        );

    if ($base === 'ABDC') {

        if (in_array(
            $valorNormalizado,
            ['A*', 'A'],
            true
        )) {
            return 'MB';
        }

        if ($valorNormalizado === 'B') {
            return 'B';
        }

        if ($valorNormalizado === 'C') {
            return 'R';
        }

        return null;
    }


    if ($base === 'ABS') {

        if (in_array(
            $valorNormalizado,
            ['4*', '4', '3', '2'],
            true
        )) {
            return 'MB';
        }

        if ($valorNormalizado === '1') {
            return 'B';
        }

        return null;
    }


    if (
        $base === 'JCR'
        ||
        $base === 'SJR'
    ) {

        return match ($valorNormalizado) {
            'Q1' => 'MB',
            'Q2' => 'B',
            'Q3' => 'R',
            'Q4' => 'F',
            default => null
        };
    }


    if ($base === 'SPELL') {

        if ($percentual === null) {
            return null;
        }

        // Percentual representa a posição da revista a partir
        // do topo da coleção: 1 = top 1%, 10 = top 10% etc.
        if ($percentual <= 10) {

            return $scieloBrasilConfirmado
                ? 'B'
                : null;
        }

        if ($percentual <= 40) {
            return 'R';
        }

        if ($percentual <= 70) {
            return 'F';
        }

        return null;
    }


    return null;
}


function elevarClasseComScieloArea27(
    ?string $classe,
    bool $scieloBrasilConfirmado
): ?string {

    if (
        !$scieloBrasilConfirmado
        ||
        $classe === null
    ) {
        return $classe;
    }

    return match ($classe) {
        'F' => 'R',
        'R' => 'B',
        'B' => 'B',
        'MB' => 'MB',
        default => $classe
    };
}


function pontosPadraoClasseArea27(
    ?string $classe
): ?float {

    return match ($classe) {
        'MB' => 8.0,
        'B' => 4.0,
        'R' => 2.0,
        'F' => 1.0,
        default => null
    };
}


function buscarIndicadoresAtuaisPeriodicoArea27(
    PDO $pdo,
    int $periodicoId
): array {

    if (
        $periodicoId <= 0
        ||
        !tabelaExisteCapes(
            $pdo,
            'periodicos_indicadores'
        )
    ) {
        return [];
    }

    $stmt =
        $pdo->prepare("
            SELECT
                id,
                periodico_id,
                base,
                ano,
                edicao,
                categoria,
                valor,
                percentual,
                classe_candidata,
                url_evidencia,
                comprovante,
                status,
                coletado_em,
                revisado_por,
                observacao,
                regra_versao
            FROM periodicos_indicadores
            WHERE periodico_id = :periodico_id
              AND vigente = 1
            ORDER BY FIELD(
                base,
                'ABDC',
                'ABS',
                'JCR',
                'SJR',
                'SPELL',
                'SCIELO'
            )
        ");

    $stmt->execute([
        ':periodico_id' => $periodicoId
    ]);

    $linhas =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    $resultado = [];

    foreach ($linhas as $linha) {

        $resultado[
            strtoupper(
                (string) $linha['base']
            )
        ] = $linha;
    }

    return $resultado;
}


function calcularClassificacaoPeriodicoArea27(
    array $indicadores
): array {

    $ordem = [
        'F' => 1,
        'R' => 2,
        'B' => 3,
        'MB' => 4
    ];

    $scieloBrasilConfirmado = false;

    if (isset($indicadores['SCIELO'])) {

        $scielo =
            $indicadores['SCIELO'];

        $scieloBrasilConfirmado =
            strtoupper(
                (string) (
                    $scielo['status']
                    ?? ''
                )
            ) === 'CONFIRMADO'
            &&
            normalizarValorIndicadorArea27(
                $scielo['valor']
                ?? ''
            ) === 'SIM';
    }


    $candidatas = [];
    $pendencias = [];

    foreach (
        ['ABDC', 'ABS', 'JCR', 'SJR', 'SPELL']
        as $base
    ) {

        if (!isset($indicadores[$base])) {
            continue;
        }

        $indicador =
            $indicadores[$base];

        $status = strtoupper(
            (string) (
                $indicador['status']
                ?? 'PENDENTE'
            )
        );

        if ($status !== 'CONFIRMADO') {

            $pendencias[] = [
                'base' => $base,
                'motivo' => 'Evidência ainda não confirmada.'
            ];

            continue;
        }

        $percentual = null;

        if (
            isset($indicador['percentual'])
            &&
            $indicador['percentual'] !== null
            &&
            $indicador['percentual'] !== ''
        ) {
            $percentual =
                (float) $indicador['percentual'];
        }

        $classe =
            classeCandidataIndicadorArea27(
                $base,
                $indicador['valor']
                    ?? null,
                $percentual,
                $scieloBrasilConfirmado
            );

        if (
            $base === 'SPELL'
            &&
            $percentual !== null
            &&
            $percentual <= 10
            &&
            !$scieloBrasilConfirmado
        ) {

            $pendencias[] = [
                'base' => 'SPELL',
                'motivo' => 'Top 10% depende de SciELO Brasil confirmado para resultar em B.'
            ];
        }

        if ($classe !== null) {

            $candidatas[] = [
                'base' => $base,
                'classe' => $classe,
                'valor' => $indicador['valor']
                    ?? null,
                'percentual' => $percentual,
                'ano' => $indicador['ano']
                    ?? null,
                'categoria' => $indicador['categoria']
                    ?? null,
                'url_evidencia' => $indicador['url_evidencia']
                    ?? null
            ];
        }
    }


    $melhorClasse = null;
    $melhorNivel = 0;
    $fontesDeterminantes = [];

    foreach ($candidatas as $candidata) {

        $classe =
            $candidata['classe'];

        $nivel =
            $ordem[$classe]
            ?? 0;

        if ($nivel > $melhorNivel) {

            $melhorNivel = $nivel;
            $melhorClasse = $classe;
            $fontesDeterminantes = [
                $candidata['base']
            ];
        }
        elseif (
            $nivel > 0
            &&
            $nivel === $melhorNivel
        ) {

            $fontesDeterminantes[] =
                $candidata['base'];
        }
    }


    $classeFinal =
        elevarClasseComScieloArea27(
            $melhorClasse,
            $scieloBrasilConfirmado
        );

    $ajusteScielo =
        $melhorClasse !== null
        &&
        $classeFinal !== $melhorClasse;

    $fonteDeterminante =
        $fontesDeterminantes
            ? implode(
                ' / ',
                array_unique(
                    $fontesDeterminantes
                )
            )
            : null;

    if (
        $ajusteScielo
        &&
        $fonteDeterminante !== null
    ) {
        $fonteDeterminante .=
            ' + SciELO Brasil';
    }


    return [
        'regra_versao' => CAPES_AREA27_REGRA_VERSAO,
        'classe_antes_ajuste' => $melhorClasse,
        'classe_final' => $classeFinal,
        'pontos_padrao' =>
            pontosPadraoClasseArea27(
                $classeFinal
            ),
        'fonte_determinante' => $fonteDeterminante,
        'ajuste_scielo' => $ajusteScielo,
        'scielo_brasil_confirmado' => $scieloBrasilConfirmado,
        'candidatas' => $candidatas,
        'pendencias' => $pendencias,
        'possui_evidencia_confirmada' =>
            count($candidatas) > 0
    ];
}
