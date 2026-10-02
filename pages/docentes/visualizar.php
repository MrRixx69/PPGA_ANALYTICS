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
        a.nome
    FROM areas_atuacao a

    INNER JOIN docentes_areas da
        ON da.area_id = a.id

    WHERE da.docente_id = :docente_id

    ORDER BY a.nome
";


$stmtAreas =
    $pdo->prepare(
        $sqlAreas
    );


$stmtAreas->execute([

    ':docente_id' =>
        $id

]);


$areas =
    $stmtAreas->fetchAll();


$sqlLinhas = "
    SELECT
        l.nome
    FROM linhas_pesquisa l

    INNER JOIN docentes_linhas dl
        ON dl.linha_id = l.id

    WHERE dl.docente_id = :docente_id

    ORDER BY l.nome
";


$stmtLinhas =
    $pdo->prepare(
        $sqlLinhas
    );


$stmtLinhas->execute([

    ':docente_id' =>
        $id

]);


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
        <?= htmlspecialchars(
            $docente['nome']
        ) ?> - PPGA Analytics
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
                <?= htmlspecialchars(
                    $docente['nome']
                ) ?>
            </h1>

            <p>
                Informações cadastrais e vínculos acadêmicos do docente.
            </p>

        </div>

        <div class="header-actions">

            <a
                href="index.php"
                class="botao botao-secundario"
            >
                Voltar aos docentes
            </a>

            <a
                href="editar.php?id=<?= (int) $docente['id'] ?>"
                class="botao"
            >
                Editar docente
            </a>

        </div>

    </header>


    <section class="perfil-grid">

        <article class="perfil-card">

            <span class="perfil-label">
                Status
            </span>

            <?php if ((int) $docente['ativo'] === 1): ?>

                <span class="status status-ativo">
                    Ativo
                </span>

            <?php else: ?>

                <span class="status status-inativo">
                    Inativo
                </span>

            <?php endif; ?>

        </article>

        <article class="perfil-card">

            <span class="perfil-label">
                Email
            </span>

            <strong class="perfil-valor">

                <?= htmlspecialchars(
                    $docente['email']
                    ?? '—'
                ) ?>

            </strong>

        </article>

        <article class="perfil-card">

            <span class="perfil-label">
                Telefone
            </span>

            <strong class="perfil-valor">

                <?= htmlspecialchars(
                    $docente['telefone']
                    ?? '—'
                ) ?>

            </strong>

        </article>

        <article class="perfil-card">

            <span class="perfil-label">
                ID Lattes
            </span>

            <strong class="perfil-valor">

                <?= htmlspecialchars(
                    $docente['lattes_id']
                    ?? '—'
                ) ?>

            </strong>

        </article>

    </section>


    <section class="painel">

        <div class="painel-cabecalho">

            <div>

                <h2>
                    Perfil acadêmico
                </h2>

                <p>
                    Currículo, áreas de atuação e linhas de pesquisa vinculadas.
                </p>

            </div>

        </div>

        <div class="detalhes-grid">

            <div class="detalhe-bloco">

                <h2>
                    Currículo Lattes
                </h2>

                <?php if (!empty($docente['lattes_url'])): ?>

                    <a
                        href="<?= htmlspecialchars(
                            $docente['lattes_url']
                        ) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="link-lattes"
                    >
                        Abrir currículo Lattes
                    </a>

                <?php else: ?>

                    <span class="vazio">
                        Link do Currículo Lattes não informado.
                    </span>

                <?php endif; ?>

            </div>

            <div class="detalhe-bloco">

                <h2>
                    Áreas de atuação
                </h2>

                <?php if (!empty($areas)): ?>

                    <div class="chips">

                        <?php foreach ($areas as $area): ?>

                            <span class="chip">

                                <?= htmlspecialchars(
                                    $area['nome']
                                ) ?>

                            </span>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <span class="vazio">
                        Nenhuma área de atuação vinculada.
                    </span>

                <?php endif; ?>

            </div>

            <div class="detalhe-bloco">

                <h2>
                    Linhas de pesquisa
                </h2>

                <?php if (!empty($linhas)): ?>

                    <div class="chips">

                        <?php foreach ($linhas as $linha): ?>

                            <span class="chip">

                                <?= htmlspecialchars(
                                    $linha['nome']
                                ) ?>

                            </span>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <span class="vazio">
                        Nenhuma linha de pesquisa vinculada.
                    </span>

                <?php endif; ?>

            </div>

            <div class="detalhe-bloco">

                <h2>
                    Cadastro
                </h2>

                <span class="vazio">
                    Use “Editar docente” para atualizar os dados
                    e os vínculos acadêmicos deste cadastro.
                </span>

            </div>

        </div>

    </section>

</main>

</body>

</html>