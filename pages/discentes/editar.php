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
        'Participante inválido.'
    );

}


$stmt =
    $pdo->prepare(
        "
            SELECT
                d.*,
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

            WHERE d.id = :id

            GROUP BY d.id

            LIMIT 1
        "
    );


$stmt->execute([

    ':id' =>
        $id

]);


$discente =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$discente) {

    die(
        'Participante não encontrado.'
    );

}


$stmt =
    $pdo->prepare(
        "
            SELECT
                p.id,
                p.titulo,
                p.ano,
                tp.nome AS tipo

            FROM producoes_discentes pd

            INNER JOIN producoes p
                ON p.id = pd.producao_id

            LEFT JOIN tipos_producao tp
                ON tp.id = p.tipo_producao_id

            WHERE pd.discente_id = :discente_id

            ORDER BY
                p.ano DESC,
                p.titulo

            LIMIT 20
        "
    );


$stmt->execute([

    ':discente_id' =>
        $id

]);


$producoes =
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
        Gerenciar participante - PPGA Analytics
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
                <?= e(
                    $discente['nome']
                ) ?>
            </h1>

            <p>
                Atualize os dados cadastrais e defina
                a situação acadêmica do participante.
            </p>

        </div>

        <div class="header-actions">

            <a
                href="index.php"
                class="botao botao-secundario"
            >
                Voltar aos participantes
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
                value="<?= (int) $discente['id'] ?>"
            >

            <div class="form-intro">

                <h2>
                    Dados do participante
                </h2>

                <p>
                    Mantenha os dados acadêmicos atualizados
                    e informe se o participante é discente ou egresso.
                </p>

            </div>

            <div class="form-grid">

                <div class="campo">

                    <label for="nome">
                        Nome
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        value="<?= e(
                            $discente['nome']
                        ) ?>"
                        required
                    >

                </div>

                <div class="campo">

                    <label for="email">
                        E-mail
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= e(
                            $discente['email']
                            ?? ''
                        ) ?>"
                    >

                </div>

                <div class="campo">

                    <label for="lattes_id">
                        ID Lattes
                    </label>

                    <input
                        type="text"
                        id="lattes_id"
                        name="lattes_id"
                        value="<?= e(
                            $discente['lattes_id']
                            ?? ''
                        ) ?>"
                    >

                </div>

                <div class="campo">

                    <label for="lattes_url">
                        URL do Lattes
                    </label>

                    <input
                        type="url"
                        id="lattes_url"
                        name="lattes_url"
                        value="<?= e(
                            $discente['lattes_url']
                            ?? ''
                        ) ?>"
                        placeholder="https://lattes.cnpq.br/..."
                    >

                </div>


                <div class="checks">

                    <label class="check-item">

                        <input
                            type="checkbox"
                            name="ativo"
                            value="1"
                            <?= (int) $discente['ativo']
                                === 1
                                    ? 'checked'
                                    : ''
                            ?>
                        >

                        <span>

                            <strong>
                                Participante ativo
                            </strong>

                            <small>
                                Permite que apareça na seleção
                                de participantes das produções.
                            </small>

                        </span>

                    </label>

                    <label class="check-item">

                        <input
                            type="checkbox"
                            name="egresso"
                            value="1"
                            <?= (int) $discente['egresso']
                                === 1
                                    ? 'checked'
                                    : ''
                            ?>
                        >

                        <span>

                            <strong>
                                Egresso
                            </strong>

                            <small>
                                Marque quando o participante
                                já concluiu seu vínculo acadêmico
                                com o programa.
                            </small>

                        </span>

                    </label>

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
                    Salvar participante
                </button>

            </div>

        </form>


        <div>

            <section class="resumo-lateral">

                <article class="card-resumo">

                    <span class="card-resumo-label">
                        Produções vinculadas
                    </span>

                    <strong class="card-resumo-valor">
                        <?= (int)
                            $discente['total_producoes']
                        ?>
                    </strong>

                    <span class="card-resumo-detalhe">
                        produções com participação registrada
                    </span>

                </article>

                <article class="card-resumo">

                    <span class="card-resumo-label">
                        Orientações
                    </span>

                    <strong class="card-resumo-valor">
                        <?= (int)
                            $discente['total_orientacoes']
                        ?>
                    </strong>

                    <span class="card-resumo-detalhe">
                        orientações vinculadas ao participante
                    </span>

                </article>

            </section>


            <section class="painel">

                <div class="painel-cabecalho">

                    <div>

                        <h2>
                            Produções vinculadas
                        </h2>

                        <p>
                            Até 20 produções mais recentes
                            associadas ao participante.
                        </p>

                    </div>

                </div>

                <?php if (!$producoes): ?>

                    <div class="producoes-vazio">
                        Nenhuma produção vinculada.
                    </div>

                <?php else: ?>

                    <div class="producoes-lista">

                        <?php foreach (
                            $producoes
                            as $producao
                        ): ?>

                            <a
                                href="../producoes/visualizar.php?id=<?= (int) $producao['id'] ?>"
                                class="producao-item"
                            >

                                <strong>

                                    <?= e(
                                        $producao['titulo']
                                    ) ?>

                                </strong>

                                <span>

                                    <?= e(
                                        $producao['tipo']
                                        ?? 'Não informado'
                                    ) ?>

                                    ·

                                    <?= (int)
                                        $producao['ano']
                                    ?>

                                </span>

                            </a>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </section>

</main>

</body>

</html>