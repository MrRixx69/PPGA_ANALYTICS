<?php

require_once '../../config/conexao.php';


$sql = "
    SELECT
        id,
        nome,
        email,
        telefone,
        lattes_url,
        ativo
    FROM docentes
    ORDER BY nome
";


$stmt =
    $pdo->query(
        $sql
    );


$docentes =
    $stmt->fetchAll();


$totalDocentes =
    count(
        $docentes
    );


$totalAtivos = 0;

$totalInativos = 0;

$totalComLattes = 0;


foreach (
    $docentes
    as $docente
) {

    if ((int) $docente['ativo'] === 1) {

        $totalAtivos++;

    } else {

        $totalInativos++;

    }


    if (!empty($docente['lattes_url'])) {

        $totalComLattes++;

    }

}

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
        Docentes - PPGA Analytics
    </title>

    <link
        rel="stylesheet"
        href="../../assets/css/docentes.css"
    >

</head>

<body>

<main class="docentes-page">

    <header class="docentes-header">

        <div>

            <h1>
                Docentes
            </h1>

            <p>
                Gerenciamento do corpo docente do PPGA.
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

            <a
                href="../lattes/index.php"
                class="botao botao-secundario"
            >
                Currículos Lattes
            </a>

            <a
                href="novo.php"
                class="botao"
            >
                + Novo docente
            </a>

        </div>

    </header>


    <section class="cards-resumo">

        <article class="card-resumo">

            <span class="card-resumo-label">
                Docentes cadastrados
            </span>

            <strong class="card-resumo-valor">
                <?= $totalDocentes ?>
            </strong>

            <span class="card-resumo-detalhe">
                registros no cadastro institucional
            </span>

        </article>

        <article class="card-resumo">

            <span class="card-resumo-label">
                Ativos
            </span>

            <strong class="card-resumo-valor">
                <?= $totalAtivos ?>
            </strong>

            <span class="card-resumo-detalhe">
                docentes considerados ativos
            </span>

        </article>

        <article class="card-resumo">

            <span class="card-resumo-label">
                Inativos
            </span>

            <strong class="card-resumo-valor">
                <?= $totalInativos ?>
            </strong>

            <span class="card-resumo-detalhe">
                docentes preservados no histórico
            </span>

        </article>

        <article class="card-resumo">

            <span class="card-resumo-label">
                Com link Lattes
            </span>

            <strong class="card-resumo-valor">
                <?= $totalComLattes ?>
            </strong>

            <span class="card-resumo-detalhe">
                cadastros com currículo informado
            </span>

        </article>

    </section>


    <section class="painel">

        <div class="painel-cabecalho">

            <div>

                <h2>
                    Corpo docente
                </h2>

                <p>
                    Consulte, edite ou altere a situação dos docentes cadastrados.
                </p>

            </div>

        </div>

        <div class="tabela-container">

            <?php if (empty($docentes)): ?>

                <div class="sem-dados">
                    Nenhum docente cadastrado.
                </div>

            <?php else: ?>

                <table>

                    <thead>

                        <tr>

                            <th>
                                Nome
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Telefone
                            </th>

                            <th>
                                Lattes
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

                        <?php foreach ($docentes as $docente): ?>

                            <tr>

                                <td>

                                    <span class="nome-docente">

                                        <?= htmlspecialchars(
                                            $docente['nome']
                                        ) ?>

                                    </span>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $docente['email']
                                        ?? '—'
                                    ) ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $docente['telefone']
                                        ?? '—'
                                    ) ?>

                                </td>

                                <td>

                                    <?php if (!empty($docente['lattes_url'])): ?>

                                        <a
                                            href="<?= htmlspecialchars(
                                                $docente['lattes_url']
                                            ) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="link-lattes"
                                        >
                                            Abrir Lattes
                                        </a>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if ((int) $docente['ativo'] === 1): ?>

                                        <span class="status status-ativo">
                                            Ativo
                                        </span>

                                    <?php else: ?>

                                        <span class="status status-inativo">
                                            Inativo
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <div class="acoes">

                                        <a
                                            href="visualizar.php?id=<?= (int) $docente['id'] ?>"
                                            class="botao botao-pequeno botao-secundario"
                                        >
                                            Visualizar
                                        </a>

                                        <a
                                            href="editar.php?id=<?= (int) $docente['id'] ?>"
                                            class="botao botao-pequeno"
                                        >
                                            Editar
                                        </a>

                                        <form
                                            action="alterar_status.php"
                                            method="POST"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $docente['id'] ?>"
                                            >

                                            <?php if ((int) $docente['ativo'] === 1): ?>

                                                <button
                                                    type="submit"
                                                    class="botao botao-pequeno botao-perigo"
                                                >
                                                    Desativar
                                                </button>

                                            <?php else: ?>

                                                <button
                                                    type="submit"
                                                    class="botao botao-pequeno botao-sucesso"
                                                >
                                                    Ativar
                                                </button>

                                            <?php endif; ?>

                                        </form>

                                    </div>

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