<?php

require_once '../../config/conexao.php';


$sqlAreas = "
    SELECT
        id,
        nome
    FROM areas_atuacao
    ORDER BY nome
";


$stmtAreas =
    $pdo->query(
        $sqlAreas
    );


$areas =
    $stmtAreas->fetchAll();


$sqlLinhas = "
    SELECT
        id,
        nome
    FROM linhas_pesquisa
    ORDER BY nome
";


$stmtLinhas =
    $pdo->query(
        $sqlLinhas
    );


$linhas =
    $stmtLinhas->fetchAll();

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
        Novo docente - PPGA Analytics
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
                Novo docente
            </h1>

            <p>
                Cadastre um novo docente no PPGA.
            </p>

        </div>

        <div class="header-actions">

            <a
                href="index.php"
                class="botao botao-secundario"
            >
                Voltar aos docentes
            </a>

        </div>

    </header>


    <form
        action="salvar.php"
        method="POST"
        class="painel form-card"
    >

        <div class="form-intro">

            <h2>
                Dados do docente
            </h2>

            <p>
                Informe os dados institucionais e os vínculos acadêmicos do docente.
            </p>

        </div>


        <div class="form-grid">

            <div class="form-group">

                <label
                    for="nome"
                    class="titulo-campo"
                >
                    Nome
                    <span class="campo-obrigatorio">
                        *
                    </span>
                </label>

                <input
                    type="text"
                    id="nome"
                    name="nome"
                    required
                >

            </div>

            <div class="form-group">

                <label
                    for="email"
                    class="titulo-campo"
                >
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                >

            </div>

            <div class="form-group">

                <label
                    for="telefone"
                    class="titulo-campo"
                >
                    Telefone
                </label>

                <input
                    type="text"
                    id="telefone"
                    name="telefone"
                >

            </div>

            <div class="form-group">

                <label
                    for="lattes_id"
                    class="titulo-campo"
                >
                    ID Lattes
                </label>

                <input
                    type="text"
                    id="lattes_id"
                    name="lattes_id"
                >

            </div>

            <div class="form-group form-group-full">

                <label
                    for="lattes_url"
                    class="titulo-campo"
                >
                    Link do currículo Lattes
                </label>

                <input
                    type="url"
                    id="lattes_url"
                    name="lattes_url"
                    placeholder="https://lattes.cnpq.br/..."
                >

            </div>

            <div class="form-group form-group-full">

                <label class="titulo-campo">
                    Áreas de atuação
                </label>

                <div class="grupo-selecao">

                    <?php foreach ($areas as $area): ?>

                        <label class="checkbox-item">

                            <input
                                type="checkbox"
                                name="areas[]"
                                value="<?= (int) $area['id'] ?>"
                            >

                            <span>
                                <?= htmlspecialchars(
                                    $area['nome']
                                ) ?>
                            </span>

                        </label>

                    <?php endforeach; ?>

                </div>

            </div>

            <div class="form-group form-group-full">

                <label class="titulo-campo">
                    Linhas de pesquisa
                </label>

                <div class="grupo-selecao">

                    <?php foreach ($linhas as $linha): ?>

                        <label class="checkbox-item">

                            <input
                                type="checkbox"
                                name="linhas[]"
                                value="<?= (int) $linha['id'] ?>"
                            >

                            <span>
                                <?= htmlspecialchars(
                                    $linha['nome']
                                ) ?>
                            </span>

                        </label>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>


        <div class="form-actions">

            <a
                href="index.php"
                class="botao botao-secundario"
            >
                Cancelar
            </a>

            <button
                type="submit"
                class="botao"
            >
                Salvar docente
            </button>

        </div>

    </form>

</main>

</body>

</html>