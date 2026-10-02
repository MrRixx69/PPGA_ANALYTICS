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


$id =
    (int) (
        $_GET['id']
        ?? 0
    );


if ($id <= 0) {

    die(
        'Periódico inválido.'
    );

}


$stmt =
    $pdo->prepare(
        "
            SELECT
                id,
                nome,
                issn,
                eissn,
                pais

            FROM periodicos

            WHERE id = :id
        "
    );


$stmt->execute([

    ':id' =>
        $id

]);


$periodico =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$periodico) {

    die(
        'Periódico não encontrado.'
    );

}


$indexadores =
    $pdo->query(
        "
            SELECT
                id,
                nome

            FROM indexadores

            ORDER BY nome
        "
    )->fetchAll(
        PDO::FETCH_ASSOC
    );


$stmt =
    $pdo->prepare(
        "
            SELECT
                indexador_id

            FROM periodicos_indexadores

            WHERE periodico_id = :periodico_id
        "
    );


$stmt->execute([

    ':periodico_id' =>
        $id

]);


$indexadoresSelecionados =
    array_map(
        'intval',
        $stmt->fetchAll(
            PDO::FETCH_COLUMN
        )
    );


$stmt =
    $pdo->prepare(
        "
            SELECT COUNT(*)
            FROM producoes
            WHERE periodico_id = :periodico_id
        "
    );


$stmt->execute([

    ':periodico_id' =>
        $id

]);


$quantidadeProducoes =
    (int) $stmt->fetchColumn();

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
        Gerenciar Periódico - PPGA Analytics
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
                Gerenciar periódico
            </h1>

            <p>
                <?= e(
                    $periodico['nome']
                ) ?>
            </p>

        </div>

        <div class="header-actions">

            <a
                href="index.php"
                class="botao botao-secundario"
            >
                Voltar aos periódicos
            </a>

            <a
                href="../dashboard/index.php"
                class="botao"
            >
                Dashboard
            </a>

        </div>

    </header>


    <section class="edicao-layout">

        <form
            action="atualizar.php"
            method="POST"
            class="painel form-card"
        >

            <input
                type="hidden"
                name="id"
                value="<?= (int) $periodico['id'] ?>"
            >

            <div class="form-intro">

                <h2>
                    Dados do periódico
                </h2>

                <p>
                    Atualize os metadados institucionais
                    e as bases de indexação deste periódico.
                </p>

            </div>


            <div class="form-grid">

                <div class="form-group form-group-full">

                    <label
                        for="nome"
                        class="titulo-campo"
                    >
                        Nome do periódico
                        <span class="campo-obrigatorio">
                            *
                        </span>
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        value="<?= e(
                            $periodico['nome']
                        ) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label
                        for="issn"
                        class="titulo-campo"
                    >
                        ISSN
                    </label>

                    <input
                        type="text"
                        id="issn"
                        name="issn"
                        maxlength="20"
                        value="<?= e(
                            $periodico['issn']
                            ?? ''
                        ) ?>"
                        placeholder="0000-0000"
                    >

                </div>

                <div class="form-group">

                    <label
                        for="eissn"
                        class="titulo-campo"
                    >
                        eISSN
                    </label>

                    <input
                        type="text"
                        id="eissn"
                        name="eissn"
                        maxlength="20"
                        value="<?= e(
                            $periodico['eissn']
                            ?? ''
                        ) ?>"
                        placeholder="0000-0000"
                    >

                </div>

                <div class="form-group">

                    <label
                        for="pais"
                        class="titulo-campo"
                    >
                        País
                    </label>

                    <input
                        type="text"
                        id="pais"
                        name="pais"
                        maxlength="100"
                        value="<?= e(
                            $periodico['pais']
                            ?? ''
                        ) ?>"
                    >

                </div>

            </div>


            <div class="indexacao-bloco">

                <h2>
                    Indexação
                </h2>

                <p>
                    Marque as bases em que este periódico está indexado.
                    Essa informação será herdada pelas produções vinculadas.
                </p>

                <?php if ($indexadores): ?>

                    <div class="indexador-opcoes">

                        <?php foreach (
                            $indexadores
                            as $indexador
                        ): ?>

                            <label class="indexador-opcao">

                                <input
                                    type="checkbox"
                                    name="indexadores[]"
                                    value="<?= (int) $indexador['id'] ?>"
                                    <?= in_array(
                                        (int) $indexador['id'],
                                        $indexadoresSelecionados,
                                        true
                                    )
                                        ? 'checked'
                                        : ''
                                    ?>
                                >

                                <span>

                                    <?= e(
                                        $indexador['nome']
                                    ) ?>

                                </span>

                            </label>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <p>
                        Nenhum indexador cadastrado no banco.
                    </p>

                <?php endif; ?>

            </div>


            <div class="nota">

                <strong>
                    Importante:
                </strong>

                a indexação é um metadado institucional.
                Marcar uma base aqui não consulta Scopus,
                Web of Science ou SciELO automaticamente.

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
                    Salvar periódico
                </button>

            </div>

        </form>


        <aside class="painel lateral-card">

            <h2>
                Uso no sistema
            </h2>

            <p>
                As produções associadas a este periódico
                recebem automaticamente seus dados de indexação.
            </p>

            <strong class="lateral-destaque">
                <?= $quantidadeProducoes ?>
            </strong>

            <span class="lateral-legenda">
                produção(ões) vinculada(s)
            </span>


            <div class="lateral-lista">

                <div class="lateral-item">

                    <span class="lateral-item-numero">
                        1
                    </span>

                    <div>

                        <strong>
                            Metadados
                        </strong>

                        <span>
                            Nome, ISSN, eISSN e país
                            ficam centralizados no periódico.
                        </span>

                    </div>

                </div>

                <div class="lateral-item">

                    <span class="lateral-item-numero">
                        2
                    </span>

                    <div>

                        <strong>
                            Indexação
                        </strong>

                        <span>
                            Scopus, Web of Science e SciELO
                            podem ser marcados institucionalmente.
                        </span>

                    </div>

                </div>

                <div class="lateral-item">

                    <span class="lateral-item-numero">
                        3
                    </span>

                    <div>

                        <strong>
                            Filtros
                        </strong>

                        <span>
                            A dashboard pode filtrar produções
                            pelo periódico ou pela base de indexação.
                        </span>

                    </div>

                </div>

            </div>

        </aside>

    </section>

</main>

</body>

</html>