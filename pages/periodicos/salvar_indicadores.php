<?php

require_once '../../config/conexao.php';
require_once '../../includes/capes_area27.php';


if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {
    header('Location: index.php');
    exit;
}


$periodicoId =
    (int) (
        $_POST['periodico_id']
        ?? 0
    );


if ($periodicoId <= 0) {
    die('Periódico inválido.');
}


if (!tabelaExisteCapes(
    $pdo,
    'periodicos_indicadores'
)) {

    die(
        'A estrutura de indicadores ainda não existe. '
        . 'Execute a migração 05_indicadores_periodicos_area27.sql.'
    );
}


$stmtPeriodico =
    $pdo->prepare("
        SELECT id
        FROM periodicos
        WHERE id = :id
        LIMIT 1
    ");

$stmtPeriodico->execute([
    ':id' => $periodicoId
]);

if (!$stmtPeriodico->fetchColumn()) {
    die('Periódico não encontrado.');
}


$entrada =
    $_POST['indicadores']
    ?? [];

$basesPermitidas = [
    'ABDC',
    'ABS',
    'JCR',
    'SJR',
    'SPELL',
    'SCIELO'
];

$statusPermitidos = [
    'PENDENTE',
    'CONFIRMADO',
    'DIVERGENTE'
];


function textoIndicador(
    array $dados,
    string $chave,
    int $limite = 500
): ?string {

    $valor = trim(
        (string) (
            $dados[$chave]
            ?? ''
        )
    );

    if ($valor === '') {
        return null;
    }

    if (function_exists('mb_substr')) {
        return mb_substr(
            $valor,
            0,
            $limite,
            'UTF-8'
        );
    }

    return substr(
        $valor,
        0,
        $limite
    );
}


function numeroAnoIndicador(
    array $dados
): ?int {

    $ano =
        (int) (
            $dados['ano']
            ?? 0
        );

    if (
        $ano < 1900
        ||
        $ano > 2100
    ) {
        return null;
    }

    return $ano;
}


function percentualIndicador(
    array $dados
): ?float {

    $valor =
        $dados['percentual']
        ?? null;

    if (
        $valor === null
        ||
        $valor === ''
    ) {
        return null;
    }

    $numero =
        (float) str_replace(
            ',',
            '.',
            (string) $valor
        );

    if (
        $numero < 0
        ||
        $numero > 100
    ) {
        return null;
    }

    return round(
        $numero,
        2
    );
}


function estadoComparavelIndicador(
    array $dados
): array {

    $campos = [
        'ano',
        'edicao',
        'categoria',
        'valor',
        'percentual',
        'classe_candidata',
        'url_evidencia',
        'comprovante',
        'status',
        'revisado_por',
        'observacao',
        'regra_versao'
    ];

    $resultado = [];

    foreach ($campos as $campo) {

        $valor =
            $dados[$campo]
            ?? null;

        if (
            $campo === 'percentual'
            &&
            $valor !== null
            &&
            $valor !== ''
        ) {
            $valor = number_format(
                (float) $valor,
                2,
                '.',
                ''
            );
        }

        $resultado[$campo] =
            $valor === ''
                ? null
                : $valor;
    }

    return $resultado;
}


// ========================================================
// NORMALIZA O ESTADO ENVIADO
// ========================================================

$normalizados = [];

foreach ($basesPermitidas as $base) {

    $dados =
        is_array(
            $entrada[$base]
            ?? null
        )
            ? $entrada[$base]
            : [];

    $status = strtoupper(
        trim(
            (string) (
                $dados['status']
                ?? 'PENDENTE'
            )
        )
    );

    if (!in_array(
        $status,
        $statusPermitidos,
        true
    )) {
        $status = 'PENDENTE';
    }

    $valor =
        textoIndicador(
            $dados,
            'valor',
            80
        );

    if ($base === 'SCIELO') {

        $valor =
            normalizarValorIndicadorArea27(
                $valor
            );

        if (!in_array(
            $valor,
            ['SIM', 'NAO'],
            true
        )) {
            $valor = null;
        }
    }

    $normalizados[$base] = [
        'periodico_id' => $periodicoId,
        'base' => $base,
        'ano' => numeroAnoIndicador($dados),
        'edicao' => textoIndicador($dados, 'edicao', 100),
        'categoria' => textoIndicador($dados, 'categoria', 180),
        'valor' => $valor,
        'percentual' => percentualIndicador($dados),
        'classe_candidata' => null,
        'url_evidencia' => textoIndicador($dados, 'url_evidencia', 500),
        'comprovante' => textoIndicador($dados, 'comprovante', 500),
        'status' => $status,
        'revisado_por' => textoIndicador($dados, 'revisado_por', 150),
        'observacao' => textoIndicador($dados, 'observacao', 2000),
        'regra_versao' => CAPES_AREA27_REGRA_VERSAO
    ];
}


$scieloConfirmado =
    $normalizados['SCIELO']['status']
        === 'CONFIRMADO'
    &&
    $normalizados['SCIELO']['valor']
        === 'SIM';


foreach (
    ['ABDC', 'ABS', 'JCR', 'SJR', 'SPELL']
    as $base
) {

    $dados =
        $normalizados[$base];

    $normalizados[$base]['classe_candidata'] =
        classeCandidataIndicadorArea27(
            $base,
            $dados['valor'],
            $dados['percentual'],
            $scieloConfirmado
        );
}


try {

    $pdo->beginTransaction();

    $stmtAtual =
        $pdo->prepare("
            SELECT *
            FROM periodicos_indicadores
            WHERE periodico_id = :periodico_id
              AND base = :base
              AND vigente = 1
            ORDER BY id DESC
            LIMIT 1
        ");

    $stmtArquivar =
        $pdo->prepare("
            UPDATE periodicos_indicadores
            SET vigente = 0
            WHERE periodico_id = :periodico_id
              AND base = :base
              AND vigente = 1
        ");

    $stmtInserir =
        $pdo->prepare("
            INSERT INTO periodicos_indicadores
            (
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
                regra_versao,
                vigente
            )
            VALUES
            (
                :periodico_id,
                :base,
                :ano,
                :edicao,
                :categoria,
                :valor,
                :percentual,
                :classe_candidata,
                :url_evidencia,
                :comprovante,
                :status,
                NOW(),
                :revisado_por,
                :observacao,
                :regra_versao,
                1
            )
        ");


    foreach (
        $basesPermitidas
        as $base
    ) {

        $novo =
            $normalizados[$base];

        $stmtAtual->execute([
            ':periodico_id' => $periodicoId,
            ':base' => $base
        ]);

        $atual =
            $stmtAtual->fetch(
                PDO::FETCH_ASSOC
            );


        $possuiConteudo =
            $novo['valor'] !== null
            ||
            $novo['percentual'] !== null
            ||
            $novo['ano'] !== null
            ||
            $novo['edicao'] !== null
            ||
            $novo['categoria'] !== null
            ||
            $novo['url_evidencia'] !== null
            ||
            $novo['comprovante'] !== null
            ||
            $novo['revisado_por'] !== null
            ||
            $novo['observacao'] !== null;


        if (
            $atual
            &&
            estadoComparavelIndicador($atual)
            === estadoComparavelIndicador($novo)
        ) {
            continue;
        }


        if ($atual) {

            $stmtArquivar->execute([
                ':periodico_id' => $periodicoId,
                ':base' => $base
            ]);
        }


        if (!$possuiConteudo) {
            continue;
        }


        $stmtInserir->execute([
            ':periodico_id' => $periodicoId,
            ':base' => $base,
            ':ano' => $novo['ano'],
            ':edicao' => $novo['edicao'],
            ':categoria' => $novo['categoria'],
            ':valor' => $novo['valor'],
            ':percentual' => $novo['percentual'],
            ':classe_candidata' => $novo['classe_candidata'],
            ':url_evidencia' => $novo['url_evidencia'],
            ':comprovante' => $novo['comprovante'],
            ':status' => $novo['status'],
            ':revisado_por' => $novo['revisado_por'],
            ':observacao' => $novo['observacao'],
            ':regra_versao' => $novo['regra_versao']
        ]);
    }


    $pdo->commit();

    header(
        'Location: editar.php?id='
        . $periodicoId
        . '&indicadores=salvos'
    );

    exit;

}
catch (Throwable $erro) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die(
        'Erro ao salvar os indicadores: '
        . $erro->getMessage()
    );
}
