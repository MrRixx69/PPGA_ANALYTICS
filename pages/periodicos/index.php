<?php

require_once '../../config/conexao.php';


function e(
    $valor
): string {

    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );

}


$busca =
    trim(
        (string) (
            $_GET['busca']
            ?? ''
        )
    );


$situacao =
    trim(
        (string) (
            $_GET['situacao']
            ?? 'todos'
        )
    );


if (
    !in_array(
        $situacao,
        [
            'todos',
            'indexados',
            'sem_indexacao'
        ],
        true
    )
) {

    $situacao = 'todos';

}


$totalPeriodicos =
    (int) $pdo->query(
        "
            SELECT COUNT(*)
            FROM periodicos
        "
    )->fetchColumn();


$totalIndexados =
    (int) $pdo->query(
        "
            SELECT COUNT(
                DISTINCT periodico_id
            )
            FROM periodicos_indexadores
        "
    )->fetchColumn();


$totalSemIndexacao =
    max(
        0,
        $totalPeriodicos
        -
        $totalIndexados
    );


$where = [];

$params = [];


if ($busca !== '') {

    $where[] = "
        (
            pe.nome LIKE :busca
            OR pe.issn LIKE :busca
            OR pe.eissn LIKE :busca
            OR pe.pais LIKE :busca
        )
    ";

    $params[':busca'] =
        '%' . $busca . '%';

}


if ($situacao === 'indexados') {

    $where[] = "
        EXISTS (
            SELECT 1
            FROM periodicos_indexadores pi_filtro
            WHERE pi_filtro.periodico_id = pe.id
        )
    ";

} elseif (
    $situacao
    ===
    'sem_indexacao'
) {

    $where[] = "
        NOT EXISTS (
            SELECT 1
            FROM periodicos_indexadores pi_filtro
            WHERE pi_filtro.periodico_id = pe.id
        )
    ";

}


$sql = "
    SELECT
        pe.id,
        pe.nome,
        pe.issn,
        pe.eissn,
        pe.pais,

        COUNT(
            DISTINCT p.id
        ) AS quantidade_producoes,

        GROUP_CONCAT(
            DISTINCT i.nome
            ORDER BY i.nome
            SEPARATOR ', '
        ) AS indexadores

    FROM periodicos pe

    LEFT JOIN producoes p
        ON p.periodico_id = pe.id

    LEFT JOIN periodicos_indexadores pi
        ON pi.periodico_id = pe.id

    LEFT JOIN indexadores i
        ON i.id = pi.indexador_id
";


if ($where) {

    $sql .=
        " WHERE "
        .
        implode(
            ' AND ',
            $where
        );

}


$sql .= "
    GROUP BY
        pe.id,
        pe.nome,
        pe.issn,
        pe.eissn,
        pe.pais

    ORDER BY pe.nome
";


$stmt =
    $pdo->prepare(
        $sql
    );


$stmt->execute(
    $params
);


$periodicos =
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
        Periódicos e Indexação - PPGA Analytics
    </title>

    <link
        rel="stylesheet"
        href="../../assets/css/periodicos.css"
    >

</head>

<body>

<main class="periodicos-page">

    <header class="periodicos-header">

        <div>

            <h1>
                Periódicos e indexação
            </h1>

            <p>
                Gerencie ISSN, país e bases de indexação
                dos periódicos utilizados nas produções.
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
                Dashboard
            </a>

        </div>

    </header>


    <section class="cards-resumo">

        <article class="card-resumo">

            <span class="card-resumo-label">
                Periódicos cadastrados
            </span>

            <strong class="card-resumo-valor">
                <?= $totalPeriodicos ?>
            </strong>

            <span class="card-resumo-detalhe">
                periódicos presentes na base
            </span>

        </article>

        <article class="card-resumo">

            <span class="card-resumo-label">
                Com indexação
            </span>

            <strong class="card-resumo-valor">
                <?= $totalIndexados ?>
            </strong>

            <span class="card-resumo-detalhe">
                com ao menos uma base cadastrada
            </span>

        </article>

        <article class="card-resumo">

            <span class="card-resumo-label">
                Sem indexação
            </span>

            <strong class="card-resumo-valor">
                <?= $totalSemIndexacao ?>
            </strong>

            <span class="card-resumo-detalhe">
                aguardando classificação institucional
            </span>

        </article>

    </section>


    <section class="painel filtros">

        <form
            method="GET"
            class="filtros-grid"
        >

            <div class="campo">

                <label for="busca">
                    Buscar periódico
                </label>

                <input
                    type="text"
                    id="busca"
                    name="busca"
                    value="<?= e($busca) ?>"
                    placeholder="Nome, ISSN, eISSN ou país"
                >

            </div>

            <div class="campo">

                <label for="situacao">
                    Indexação
                </label>

                <select
                    id="situacao"
                    name="situacao"
                >

                    <option
                        value="todos"
                        <?= $situacao === 'todos'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Todos
                    </option>

                    <option
                        value="indexados"
                        <?= $situacao === 'indexados'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Com indexação
                    </option>

                    <option
                        value="sem_indexacao"
                        <?= $situacao === 'sem_indexacao'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Sem indexação
                    </option>

                </select>

            </div>

            <div class="filtros-acoes">

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


    <section class="painel">

        <div class="painel-cabecalho">

            <div>

                <h2>
                    Catálogo de periódicos
                </h2>

                <p>
                    Consulte os metadados, indexadores
                    e a quantidade de produções vinculadas.
                </p>

            </div>

            <span class="painel-contador">
                <?= count($periodicos) ?> periódico(s)
            </span>

        </div>

        <div class="tabela-container">

            <table>

                <thead>

                    <tr>

                        <th>
                            Periódico
                        </th>

                        <th>
                            ISSN / eISSN
                        </th>

                        <th>
                            País
                        </th>

                        <th>
                            Indexação
                        </th>

                        <th>
                            Produções
                        </th>

                        <th>
                            Ação
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (!$periodicos): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="sem-dados"
                            >
                                Nenhum periódico encontrado
                                para os filtros selecionados.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach (
                            $periodicos
                            as $periodico
                        ): ?>

                            <tr>

                                <td>

                                    <span class="nome-periodico">

                                        <?= e(
                                            $periodico['nome']
                                        ) ?>

                                    </span>

                                </td>

                                <td>

                                    <?php if (
                                        !empty(
                                            $periodico['issn']
                                        )
                                        ||
                                        !empty(
                                            $periodico['eissn']
                                        )
                                    ): ?>

                                        <div class="issn-lista">

                                            <?php if (
                                                !empty(
                                                    $periodico['issn']
                                                )
                                            ): ?>

                                                <span>
                                                    <strong>ISSN</strong>
                                                    <?= e(
                                                        $periodico['issn']
                                                    ) ?>
                                                </span>

                                            <?php endif; ?>

                                            <?php if (
                                                !empty(
                                                    $periodico['eissn']
                                                )
                                            ): ?>

                                                <span>
                                                    <strong>eISSN</strong>
                                                    <?= e(
                                                        $periodico['eissn']
                                                    ) ?>
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    <?php else: ?>

                                        <span class="texto-secundario">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if (
                                        !empty(
                                            $periodico['pais']
                                        )
                                    ): ?>

                                        <?= e(
                                            $periodico['pais']
                                        ) ?>

                                    <?php else: ?>

                                        <span class="texto-secundario">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if (
                                        !empty(
                                            $periodico['indexadores']
                                        )
                                    ): ?>

                                        <div class="indexador-tags">

                                            <?php foreach (
                                                explode(
                                                    ', ',
                                                    $periodico['indexadores']
                                                )
                                                as $indexador
                                            ): ?>

                                                <span class="indexador-tag">

                                                    <?= e(
                                                        $indexador
                                                    ) ?>

                                                </span>

                                            <?php endforeach; ?>

                                        </div>

                                    <?php else: ?>

                                        <span class="sem-indexacao">
                                            Não informado
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <span class="quantidade-producoes">

                                        <?= (int)
                                            $periodico[
                                                'quantidade_producoes'
                                            ]
                                        ?>

                                    </span>

                                </td>

                                <td>

                                    <a
                                        href="editar.php?id=<?= (int) $periodico['id'] ?>"
                                        class="botao botao-pequeno botao-secundario"
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

</main>

</body>

</html>