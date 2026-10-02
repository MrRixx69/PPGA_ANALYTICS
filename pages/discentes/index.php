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
            ?? ''
        )
    );


if (
    !in_array(
        $situacao,
        [
            '',
            'discente',
            'egresso',
            'inativo'
        ],
        true
    )
) {

    $situacao = '';

}


$where = [];

$params = [];


if ($busca !== '') {

    $where[] = "
        (
            d.nome LIKE :busca
            OR d.email LIKE :busca
            OR d.lattes_id LIKE :busca
        )
    ";

    $params[':busca'] =
        '%' . $busca . '%';

}


if ($situacao === 'discente') {

    $where[] =
        'd.ativo = 1 AND d.egresso = 0';

} elseif ($situacao === 'egresso') {

    $where[] =
        'd.ativo = 1 AND d.egresso = 1';

} elseif ($situacao === 'inativo') {

    $where[] =
        'd.ativo = 0';

}


$sqlWhere =
    $where
        ? 'WHERE ' . implode(
            ' AND ',
            $where
        )
        : '';


$stmt =
    $pdo->prepare(
        "
            SELECT
                d.id,
                d.nome,
                d.email,
                d.lattes_id,
                d.lattes_url,
                d.ativo,
                d.egresso,
                d.data_cadastro,
                COUNT(
                    DISTINCT pd.producao_id
                ) AS total_producoes,
                COUNT(
                    DISTINCT o.id
                ) AS total_orientacoes

            FROM discentes d

            LEFT JOIN producoes_discentes pd
                ON pd.discente_id = d.id

            LEFT JOIN orientacoes o
                ON o.discente_id = d.id

            {$sqlWhere}

            GROUP BY
                d.id,
                d.nome,
                d.email,
                d.lattes_id,
                d.lattes_url,
                d.ativo,
                d.egresso,
                d.data_cadastro

            ORDER BY d.nome
        "
    );


$stmt->execute(
    $params
);


$discentes =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


$resumo =
    $pdo->query(
        "
            SELECT
                COUNT(*) AS total,
                SUM(
                    CASE
                        WHEN ativo = 1
                        AND egresso = 0
                        THEN 1
                        ELSE 0
                    END
                ) AS discentes_ativos,
                SUM(
                    CASE
                        WHEN ativo = 1
                        AND egresso = 1
                        THEN 1
                        ELSE 0
                    END
                ) AS egressos,
                SUM(
                    CASE
                        WHEN ativo = 0
                        THEN 1
                        ELSE 0
                    END
                ) AS inativos

            FROM discentes
        "
    )->fetch(
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
        Discentes e Egressos - PPGA Analytics
    </title>

    <link
        rel="stylesheet"
        href="../../assets/css/discentes.css"
    >

</head>

<body>

<main class="discentes-page">

    <header class="discentes-header">

        <div>

            <h1>
                Discentes e Egressos
            </h1>

            <p>
                Gerencie a situação acadêmica dos participantes
                vinculados às produções e orientações.
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


    <?php if (
        isset($_GET['salvo'])
        &&
        $_GET['salvo'] === '1'
    ): ?>

        <div class="alerta-sucesso">
            Participante atualizado com sucesso.
        </div>

    <?php endif; ?>


    <section class="cards-resumo">

        <article class="card-resumo">

            <span class="card-resumo-label">
                Participantes
            </span>

            <strong class="card-resumo-valor">
                <?= (int) (
                    $resumo['total']
                    ?? 0
                ) ?>
            </strong>

            <span class="card-resumo-detalhe">
                registros cadastrados
            </span>

        </article>

        <article class="card-resumo">

            <span class="card-resumo-label">
                Discentes ativos
            </span>

            <strong class="card-resumo-valor">
                <?= (int) (
                    $resumo['discentes_ativos']
                    ?? 0
                ) ?>
            </strong>

            <span class="card-resumo-detalhe">
                com vínculo acadêmico atual
            </span>

        </article>

        <article class="card-resumo">

            <span class="card-resumo-label">
                Egressos
            </span>

            <strong class="card-resumo-valor">
                <?= (int) (
                    $resumo['egressos']
                    ?? 0
                ) ?>
            </strong>

            <span class="card-resumo-detalhe">
                vínculo acadêmico concluído
            </span>

        </article>

        <article class="card-resumo">

            <span class="card-resumo-label">
                Inativos
            </span>

            <strong class="card-resumo-valor">
                <?= (int) (
                    $resumo['inativos']
                    ?? 0
                ) ?>
            </strong>

            <span class="card-resumo-detalhe">
                fora da seleção de participantes
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
                    Buscar participante
                </label>

                <input
                    type="text"
                    id="busca"
                    name="busca"
                    value="<?= e($busca) ?>"
                    placeholder="Nome, e-mail ou ID Lattes"
                >

            </div>

            <div class="campo">

                <label for="situacao">
                    Situação
                </label>

                <select
                    id="situacao"
                    name="situacao"
                >

                    <option value="">
                        Todos
                    </option>

                    <option
                        value="discente"
                        <?= $situacao === 'discente'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Discentes ativos
                    </option>

                    <option
                        value="egresso"
                        <?= $situacao === 'egresso'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Egressos
                    </option>

                    <option
                        value="inativo"
                        <?= $situacao === 'inativo'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Inativos
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
                    Participantes
                </h2>

                <p>
                    Consulte a situação, vínculos e dados acadêmicos
                    de discentes e egressos.
                </p>

            </div>

            <span class="painel-contador">
                <?= count($discentes) ?> registro(s)
            </span>

        </div>

        <div class="tabela-container">

            <table>

                <thead>

                    <tr>

                        <th>
                            Nome
                        </th>

                        <th>
                            Situação
                        </th>

                        <th>
                            Produções
                        </th>

                        <th>
                            Orientações
                        </th>

                        <th>
                            Lattes
                        </th>

                        <th>
                            Ação
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (!$discentes): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="sem-dados"
                            >
                                Nenhum participante encontrado.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach (
                            $discentes
                            as $discente
                        ): ?>

                            <tr>

                                <td>

                                    <span class="nome-participante">

                                        <?= e(
                                            $discente['nome']
                                        ) ?>

                                    </span>

                                    <?php if (
                                        !empty(
                                            $discente['email']
                                        )
                                    ): ?>

                                        <span class="texto-secundario">

                                            <?= e(
                                                $discente['email']
                                            ) ?>

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if (
                                        (int) $discente['ativo']
                                        === 0
                                    ): ?>

                                        <span class="status status-inativo">
                                            Inativo
                                        </span>

                                    <?php elseif (
                                        (int) $discente['egresso']
                                        === 1
                                    ): ?>

                                        <span class="status status-egresso">
                                            Egresso
                                        </span>

                                    <?php else: ?>

                                        <span class="status status-discente">
                                            Discente
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= (int)
                                        $discente['total_producoes']
                                    ?>
                                </td>

                                <td>
                                    <?= (int)
                                        $discente['total_orientacoes']
                                    ?>
                                </td>

                                <td>

                                    <?php if (
                                        !empty(
                                            $discente['lattes_url']
                                        )
                                    ): ?>

                                        <a
                                            href="<?= e(
                                                $discente['lattes_url']
                                            ) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="link-lattes"
                                        >
                                            Abrir Lattes
                                        </a>

                                    <?php elseif (
                                        !empty(
                                            $discente['lattes_id']
                                        )
                                    ): ?>

                                        <?= e(
                                            $discente['lattes_id']
                                        ) ?>

                                    <?php else: ?>

                                        <span class="texto-secundario">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <a
                                        class="botao botao-pequeno botao-secundario"
                                        href="editar.php?id=<?= (int) $discente['id'] ?>"
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