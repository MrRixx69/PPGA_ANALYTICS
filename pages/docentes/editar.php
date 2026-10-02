<?php

require_once '../../config/conexao.php';


$id =
    (int) (
        $_GET['id']
        ?? 0
    );


if ($id <= 0) {

    die(
        'Docente inválido.'
    );

}


$sql = "
    SELECT *
    FROM docentes
    WHERE id = :id
";


$stmt =
    $pdo->prepare(
        $sql
    );


$stmt->execute([

    ':id' =>
        $id

]);


$docente =
    $stmt->fetch();


if (!$docente) {

    die(
        'Docente não encontrado.'
    );

}


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


$sqlAreasSelecionadas = "
    SELECT
        area_id
    FROM docentes_areas
    WHERE docente_id = :docente_id
";


$stmtAreasSelecionadas =
    $pdo->prepare(
        $sqlAreasSelecionadas
    );


$stmtAreasSelecionadas->execute([

    ':docente_id' =>
        $id

]);


$areasSelecionadas =
    $stmtAreasSelecionadas->fetchAll(
        PDO::FETCH_COLUMN
    );


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


$sqlLinhasSelecionadas = "
    SELECT
        linha_id
    FROM docentes_linhas
    WHERE docente_id = :docente_id
";


$stmtLinhasSelecionadas =
    $pdo->prepare(
        $sqlLinhasSelecionadas
    );


$stmtLinhasSelecionadas->execute([

    ':docente_id' =>
        $id

]);


$linhasSelecionadas =
    $stmtLinhasSelecionadas->fetchAll(
        PDO::FETCH_COLUMN
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
        Editar docente - PPGA Analytics
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
                Editar docente
            </h1>

            <p>
                Altere os dados de <?= htmlspecialchars(
                    $docente['nome']
                ) ?>.
            </p>

        </div>

        <div class="header-actions">

            <a
                href="visualizar.php?id=<?= (int) $docente['id'] ?>"
                class="botao botao-secundario"
            >
                Voltar ao perfil
            </a>

        </div>

    </header>


    <form
        action="atualizar.php"
        method="POST"
        class="painel form-card"
    >

        <input
            type="hidden"
            name="id"
            value="<?= (int) $docente['id'] ?>"
        >


        <div class="form-intro">

            <h2>
                Dados do docente
            </h2>

            <p>
                Atualize os dados institucionais e vínculos acadêmicos.
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
                    value="<?= htmlspecialchars(
                        $docente['nome']
                    ) ?>"
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
                    value="<?= htmlspecialchars(
                        $docente['email']
                        ?? ''
                    ) ?>"
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
                    value="<?= htmlspecialchars(
                        $docente['telefone']
                        ?? ''
                    ) ?>"
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
                    value="<?= htmlspecialchars(
                        $docente['lattes_id']
                        ?? ''
                    ) ?>"
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
                    value="<?= htmlspecialchars(
                        $docente['lattes_url']
                        ?? ''
                    ) ?>"
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

                                <?= in_array(
                                    $area['id'],
                                    $areasSelecionadas
                                )
                                    ? 'checked'
                                    : ''
                                ?>
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

                                <?= in_array(
                                    $linha['id'],
                                    $linhasSelecionadas
                                )
                                    ? 'checked'
                                    : ''
                                ?>
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
                href="visualizar.php?id=<?= (int) $docente['id'] ?>"
                class="botao botao-secundario"
            >
                Cancelar
            </a>

            <button
                type="submit"
                class="botao"
            >
                Salvar alterações
            </button>

        </div>

    </form>

</main>

</body>

</html>