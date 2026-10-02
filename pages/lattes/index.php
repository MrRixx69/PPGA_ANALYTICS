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


$sql = "
    SELECT

        cl.id,

        cl.versao,

        cl.nome_arquivo,

        cl.data_importacao,

        cl.data_atualizacao_lattes,

        cl.status,

        cl.quantidade_registros,

        d.id AS docente_id,

        d.nome AS docente_nome

    FROM curriculos_lattes cl

    INNER JOIN docentes d
        ON d.id = cl.docente_id

    ORDER BY
        cl.data_importacao DESC,
        cl.id DESC
";


$stmt =
    $pdo->query(
        $sql
    );


$curriculos =
    $stmt->fetchAll();


$totalImportacoes =
    count(
        $curriculos
    );


$docentesComHistorico = [];

$totalConcluidas = 0;


foreach (
    $curriculos
    as $curriculo
) {

    $docentesComHistorico[
        (int) $curriculo['docente_id']
    ] = true;


    if (
        strtoupper(
            trim(
                (string)
                $curriculo['status']
            )
        )
        ===
        'CONCLUIDO'
    ) {

        $totalConcluidas++;

    }

}


$totalDocentesComHistorico =
    count(
        $docentesComHistorico
    );


$ultimaImportacao =
    $curriculos[0]['data_importacao']
    ?? null;

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
        Currículos Lattes - PPGA Analytics
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
                Currículos Lattes
            </h1>

            <p>
                Importações, versões e histórico dos currículos dos docentes.
            </p>

        </div>


        <div class="header-actions">

            <a
                href="../../index.php"
                class="botao botao-secundario"
            >
                Página inicial
            </a>

            <a
                href="../dashboard/index.php"
                class="botao botao-secundario"
            >
                Voltar à Dashboard
            </a>

            <a
                href="../docentes/index.php"
                class="botao botao-secundario"
            >
                Docentes
            </a>

            <a
                href="importar.php"
                class="botao"
            >
                + Importar currículo
            </a>

        </div>


    </header>


    <section class="cards-resumo">


        <article class="card-resumo">

            <span class="card-resumo-label">
                Importações
            </span>

            <strong class="card-resumo-valor">
                <?= $totalImportacoes ?>
            </strong>

            <span class="card-resumo-detalhe">
                versões preservadas no histórico
            </span>

        </article>


        <article class="card-resumo">

            <span class="card-resumo-label">
                Docentes com histórico
            </span>

            <strong class="card-resumo-valor">
                <?= $totalDocentesComHistorico ?>
            </strong>

            <span class="card-resumo-detalhe">
                docentes com ao menos uma importação
            </span>

        </article>


        <article class="card-resumo">

            <span class="card-resumo-label">
                Concluídas
            </span>

            <strong class="card-resumo-valor">
                <?= $totalConcluidas ?>
            </strong>

            <span class="card-resumo-detalhe">
                importações processadas com sucesso
            </span>

        </article>


        <article class="card-resumo">

            <span class="card-resumo-label">
                Última importação
            </span>

            <strong class="card-resumo-valor detalhe-valor-menor">
                <?= htmlspecialchars(
                    formatarDataHoraLattes(
                        $ultimaImportacao
                    )
                ) ?>
            </strong>

            <span class="card-resumo-detalhe">
                registro mais recente do histórico
            </span>

        </article>


    </section>


    <section class="painel">


        <div class="painel-cabecalho">

            <div>

                <h2>
                    Histórico de importações
                </h2>

                <p>
                    Cada nova importação cria uma versão sem substituir as anteriores.
                </p>

            </div>

        </div>


        <div class="tabela-container">


            <?php if (empty($curriculos)): ?>


                <div class="sem-dados">

                    <strong>
                        Nenhum currículo importado.
                    </strong>

                    Importe o primeiro currículo para iniciar o histórico.

                </div>


            <?php else: ?>


                <table>


                    <thead>

                        <tr>

                            <th>
                                Docente
                            </th>

                            <th>
                                Versão
                            </th>

                            <th>
                                Arquivo
                            </th>

                            <th>
                                Importação
                            </th>

                            <th>
                                Atualização Lattes
                            </th>

                            <th>
                                Registros
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Ações
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach ($curriculos as $curriculo): ?>


                            <tr>


                                <td class="col-docente">

                                    <span class="nome-docente">

                                        <?= htmlspecialchars(
                                            $curriculo['docente_nome']
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <span class="versao">

                                        v<?= (int) $curriculo['versao'] ?>

                                    </span>

                                </td>


                                <td class="col-arquivo">

                                    <span
                                        class="nome-arquivo"
                                        title="<?= htmlspecialchars(
                                            $curriculo['nome_arquivo']
                                            ?? '—'
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $curriculo['nome_arquivo']
                                            ?? '—'
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        formatarDataHoraLattes(
                                            $curriculo['data_importacao']
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        formatarDataLattes(
                                            $curriculo[
                                                'data_atualizacao_lattes'
                                            ]
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= (int)
                                        $curriculo[
                                            'quantidade_registros'
                                        ]
                                    ?>

                                </td>


                                <td>

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

                                </td>


                                <td>

                                    <a
                                        href="visualizar.php?id=<?= (int) $curriculo['id'] ?>"
                                        class="botao botao-secundario"
                                    >
                                        Visualizar
                                    </a>

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