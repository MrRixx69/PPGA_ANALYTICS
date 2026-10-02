<?php

require_once '../../config/conexao.php';


function formatarDataHoraLattes(
    ?string $data
): string {

    if (
        $data === null
        ||
        trim($data) === ''
    ) {

        return '—';

    }

    $timestamp =
        strtotime(
            $data
        );

    if ($timestamp === false) {

        return $data;

    }

    return date(
        'd/m/Y H:i',
        $timestamp
    );

}


function formatarDataLattes(
    ?string $data
): string {

    if (
        $data === null
        ||
        trim($data) === ''
    ) {

        return '—';

    }

    $timestamp =
        strtotime(
            $data
        );

    if ($timestamp === false) {

        return $data;

    }

    return date(
        'd/m/Y',
        $timestamp
    );

}


function classeStatusLattes(
    ?string $status
): string {

    $status =
        strtoupper(
            trim(
                (string) $status
            )
        );

    return match ($status) {

        'CONCLUIDO',
        'CONCLUÍDO' =>
            'status-concluido',

        'PENDENTE' =>
            'status-pendente',

        'PROCESSANDO' =>
            'status-processando',

        'ERRO' =>
            'status-erro',

        default =>
            'status-outro'

    };

}


// ==============================
// ID
// ==============================

$id =
    (int) (
        $_GET['id']
        ?? 0
    );


if ($id <= 0) {

    die(
        'Importação inválida.'
    );

}


// ==============================
// CURRÍCULO
// ==============================

$sql = "
    SELECT

        cl.*,

        d.nome AS docente_nome

    FROM curriculos_lattes cl

    INNER JOIN docentes d
        ON d.id = cl.docente_id

    WHERE cl.id = :id
";


$stmt =
    $pdo->prepare(
        $sql
    );


$stmt->execute([

    ':id' =>
        $id

]);


$curriculo =
    $stmt->fetch();


if (!$curriculo) {

    die(
        'Importação não encontrada.'
    );

}


// ==============================
// REGISTROS
// ==============================

$sqlRegistros = "
    SELECT

        id,

        tipo_registro,

        titulo,

        ano,

        dados_brutos

    FROM registros_lattes

    WHERE curriculo_lattes_id = :id

    ORDER BY
        ano DESC,
        tipo_registro,
        titulo
";


$stmtRegistros =
    $pdo->prepare(
        $sqlRegistros
    );


$stmtRegistros->execute([

    ':id' =>
        $id

]);


$registros =
    $stmtRegistros->fetchAll();


// ==============================
// RESUMO DOS REGISTROS
// ==============================

$resumoTipos = [];

$anosEncontrados = [];


foreach (
    $registros
    as $registro
) {

    $tipo =
        trim(
            (string) (
                $registro['tipo_registro']
                ?? ''
            )
        );


    if ($tipo === '') {

        $tipo = 'OUTRO';

    }


    if (!isset(
        $resumoTipos[$tipo]
    )) {

        $resumoTipos[$tipo] = 0;

    }


    $resumoTipos[$tipo]++;


    $ano =
        (int) (
            $registro['ano']
            ?? 0
        );


    if ($ano > 0) {

        $anosEncontrados[] =
            $ano;

    }

}


arsort(
    $resumoTipos
);


$anoMaisRecente =
    !empty($anosEncontrados)
        ? max($anosEncontrados)
        : null;


$totalTipos =
    count(
        $resumoTipos
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
        Importação Lattes - PPGA Analytics
    </title>

    <link
        rel="stylesheet"
        href="../../assets/css/lattes.css"
    >

</head>


<body>


<main class="lattes-page">


    <header class="lattes-header">


        <div>

            <h1>

                <?= htmlspecialchars(
                    $curriculo['docente_nome']
                ) ?>

            </h1>

            <p>

                Currículo Lattes · versão
                v<?= (int) $curriculo['versao'] ?>

            </p>

        </div>


        <div class="header-actions">

            <a
                href="index.php"
                class="botao botao-secundario"
            >
                Voltar ao histórico
            </a>

            <a
                href="../dashboard/index.php"
                class="botao"
            >
                Dashboard
            </a>

        </div>


    </header>


    <section class="detalhes-importacao">


        <article class="detalhe-card">

            <span class="detalhe-label">
                Status
            </span>

            <span
                class="
                    status
                    <?= classeStatusLattes(
                        $curriculo['status']
                    ) ?>
                "
            >
                <?= htmlspecialchars(
                    $curriculo['status']
                ) ?>
            </span>

        </article>


        <article class="detalhe-card">

            <span class="detalhe-label">
                Versão
            </span>

            <strong class="detalhe-valor">
                v<?= (int) $curriculo['versao'] ?>
            </strong>

        </article>


        <article class="detalhe-card">

            <span class="detalhe-label">
                Registros encontrados
            </span>

            <strong class="detalhe-valor">
                <?= (int)
                    $curriculo['quantidade_registros']
                ?>
            </strong>

        </article>


        <article class="detalhe-card">

            <span class="detalhe-label">
                Atualização do Lattes
            </span>

            <strong class="detalhe-valor detalhe-valor-menor">
                <?= htmlspecialchars(
                    formatarDataLattes(
                        $curriculo[
                            'data_atualizacao_lattes'
                        ]
                    )
                ) ?>
            </strong>

        </article>


    </section>


    <section class="painel arquivo-card">


        <div class="arquivo-info">

            <span class="arquivo-info-label">
                Arquivo importado
            </span>

            <span class="arquivo-info-valor">

                <?= htmlspecialchars(
                    $curriculo['nome_arquivo']
                    ?? '—'
                ) ?>

            </span>

        </div>


        <div class="arquivo-info">

            <span class="arquivo-info-label">
                Data da importação
            </span>

            <span class="arquivo-info-valor">

                <?= htmlspecialchars(
                    formatarDataHoraLattes(
                        $curriculo['data_importacao']
                    )
                ) ?>

            </span>

        </div>


        <?php if (!empty($resumoTipos)): ?>


            <div class="arquivo-info" style="grid-column: 1 / -1;">

                <span class="arquivo-info-label">
                    Resumo dos registros
                </span>

                <div class="resumo-tipos">

                    <?php foreach ($resumoTipos as $tipo => $quantidade): ?>

                        <span class="tipo-chip">

                            <?= htmlspecialchars(
                                $tipo
                            ) ?>

                            &nbsp;·&nbsp;

                            <?= (int) $quantidade ?>

                        </span>

                    <?php endforeach; ?>

                </div>

            </div>


        <?php endif; ?>


    </section>


    <section class="cards-resumo">


        <article class="card-resumo">

            <span class="card-resumo-label">
                Registros nesta versão
            </span>

            <strong class="card-resumo-valor">
                <?= count($registros) ?>
            </strong>

            <span class="card-resumo-detalhe">
                registros extraídos e armazenados
            </span>

        </article>


        <article class="card-resumo">

            <span class="card-resumo-label">
                Tipos de registro
            </span>

            <strong class="card-resumo-valor">
                <?= $totalTipos ?>
            </strong>

            <span class="card-resumo-detalhe">
                categorias identificadas nesta versão
            </span>

        </article>


        <article class="card-resumo">

            <span class="card-resumo-label">
                Ano mais recente
            </span>

            <strong class="card-resumo-valor">
                <?= $anoMaisRecente
                    !== null
                        ? (int) $anoMaisRecente
                        : '—'
                ?>
            </strong>

            <span class="card-resumo-detalhe">
                maior ano encontrado nos registros
            </span>

        </article>


        <article class="card-resumo">

            <span class="card-resumo-label">
                Histórico
            </span>

            <strong class="card-resumo-valor">
                v<?= (int) $curriculo['versao'] ?>
            </strong>

            <span class="card-resumo-detalhe">
                versão preservada para auditoria
            </span>

        </article>


    </section>


    <section class="painel">


        <div class="painel-cabecalho">

            <div>

                <h2>
                    Registros extraídos
                </h2>

                <p>
                    Conteúdo identificado no arquivo desta versão do currículo.
                </p>

            </div>

        </div>


        <div class="tabela-container">


            <?php if (empty($registros)): ?>


                <div class="sem-dados">

                    <strong>
                        Nenhum registro extraído.
                    </strong>

                    Esta versão não possui registros disponíveis para exibição.

                </div>


            <?php else: ?>


                <table>


                    <thead>

                        <tr>

                            <th>
                                Tipo
                            </th>

                            <th>
                                Título
                            </th>

                            <th>
                                Ano
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach ($registros as $registro): ?>


                            <tr>


                                <td>

                                    <span class="tipo-registro">

                                        <?= htmlspecialchars(
                                            $registro['tipo_registro']
                                            ?? 'OUTRO'
                                        ) ?>

                                    </span>

                                </td>


                                <td class="titulo-registro">

                                    <?= htmlspecialchars(
                                        $registro['titulo']
                                        ?? '—'
                                    ) ?>

                                </td>


                                <td class="ano-registro">

                                    <?= htmlspecialchars(
                                        $registro['ano']
                                        ?? '—'
                                    ) ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>


                </table>


            <?php endif; ?>


        </div>


    </section>


</main>


</body>

</html>