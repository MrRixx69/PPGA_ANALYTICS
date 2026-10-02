<?php

// ========================================================
// CONFIGURAÇÃO
// ========================================================

$raizProjeto = dirname(
    __DIR__,
    2
);

require_once
    $raizProjeto
    . DIRECTORY_SEPARATOR
    . 'config'
    . DIRECTORY_SEPARATOR
    . 'conexao.php';


// ========================================================
// FUNÇÃO HTML
// ========================================================

function e($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}


// ========================================================
// PRODUÇÃO
// ========================================================

$producaoId =
    isset($_GET['producao_id'])
        ? (int) $_GET['producao_id']
        : (
            isset($_POST['producao_id'])
                ? (int) $_POST['producao_id']
                : 0
        );


if ($producaoId <= 0) {

    die(
        'Produção inválida.'
    );
}


// ========================================================
// BUSCA PRODUÇÃO
// ========================================================

$stmtProducao =
    $pdo->prepare("
        SELECT
            p.id,
            p.titulo,
            p.ano,

            tp.nome AS tipo

        FROM producoes p

        LEFT JOIN tipos_producao tp
            ON tp.id =
                p.tipo_producao_id

        WHERE
            p.id = :id

        LIMIT 1
    ");

$stmtProducao->execute([
    ':id' =>
        $producaoId
]);

$producao =
    $stmtProducao->fetch(
        PDO::FETCH_ASSOC
    );


if (!$producao) {

    die(
        'Produção não encontrada.'
    );
}


// ========================================================
// SALVAR VÍNCULOS
// ========================================================

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {

    $discentesSelecionados =
        $_POST['discentes']
        ?? [];

    try {

        $pdo->beginTransaction();


        // =================================================
        // REMOVE RELAÇÕES ANTIGAS
        // =================================================

        $stmtExcluir =
            $pdo->prepare("
                DELETE FROM producoes_discentes
                WHERE producao_id = :producao_id
            ");

        $stmtExcluir->execute([
            ':producao_id' =>
                $producaoId
        ]);


        // =================================================
        // INSERE NOVAS RELAÇÕES
        // =================================================

        if (
            count(
                $discentesSelecionados
            ) > 0
        ) {

            $stmtInserir =
                $pdo->prepare("
                    INSERT INTO producoes_discentes
                    (
                        producao_id,
                        discente_id,
                        ordem_autoria
                    )

                    VALUES
                    (
                        :producao_id,
                        :discente_id,
                        NULL
                    )
                ");


            foreach (
                $discentesSelecionados
                as $discenteId
            ) {

                $discenteId =
                    (int)
                    $discenteId;


                if ($discenteId <= 0) {

                    continue;
                }


                $stmtInserir->execute([

                    ':producao_id' =>
                        $producaoId,

                    ':discente_id' =>
                        $discenteId
                ]);
            }
        }


        $pdo->commit();


        header(
            'Location: discentes.php'
            . '?producao_id='
            . $producaoId
            . '&salvo=1'
        );

        exit;

    }

    catch (Throwable $erro) {

        if (
            $pdo->inTransaction()
        ) {

            $pdo->rollBack();
        }


        die(
            'Erro ao salvar discentes: '
            . e(
                $erro->getMessage()
            )
        );
    }
}


// ========================================================
// BUSCA DISCENTES
// ========================================================

$stmtDiscentes =
    $pdo->query("
        SELECT
            id,
            nome,
            egresso,
            ativo

        FROM discentes

        WHERE ativo = 1

        ORDER BY nome
    ");

$discentes =
    $stmtDiscentes->fetchAll(
        PDO::FETCH_ASSOC
    );


// ========================================================
// DISCENTES JÁ VINCULADOS
// ========================================================

$stmtVinculados =
    $pdo->prepare("
        SELECT
            discente_id

        FROM producoes_discentes

        WHERE
            producao_id =
                :producao_id
    ");

$stmtVinculados->execute([
    ':producao_id' =>
        $producaoId
]);

$discentesVinculados =
    $stmtVinculados->fetchAll(
        PDO::FETCH_COLUMN
    );


$discentesVinculados =
    array_map(
        'intval',
        $discentesVinculados
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
        Discentes da Produção - PPGA Analytics
    </title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f6f8;

            color: #1f2937;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        .cabecalho {
            margin-bottom: 20px;
        }

        .cabecalho h1 {
            margin: 0 0 6px;
        }

        .cabecalho p {
            margin: 0;
            color: #6b7280;
        }

        .painel {
            background: white;

            border:
                1px solid
                #e5e7eb;

            border-radius: 12px;

            padding: 24px;

            margin-bottom: 20px;

            box-shadow:
                0 2px 8px
                rgba(
                    0,
                    0,
                    0,
                    0.04
                );
        }

        .producao-titulo {
            font-size: 19px;
            margin-bottom: 10px;
        }

        .producao-info {
            color: #6b7280;
        }

        .mensagem {
            padding: 14px;

            margin-bottom: 20px;

            background: #dcfce7;

            color: #166534;

            border:
                1px solid
                #bbf7d0;

            border-radius: 8px;
        }

        .busca {
            width: 100%;

            padding: 12px;

            margin-bottom: 18px;

            border:
                1px solid
                #d1d5db;

            border-radius: 7px;

            font-size: 15px;
        }

        .lista {
            max-height: 500px;

            overflow-y: auto;

            border:
                1px solid
                #e5e7eb;

            border-radius: 8px;
        }

        .discente {
            display: flex;

            align-items: center;

            gap: 10px;

            padding: 13px 15px;

            border-bottom:
                1px solid
                #eeeeee;
        }

        .discente:last-child {
            border-bottom: none;
        }

        .discente:hover {
            background: #f9fafb;
        }

        .discente label {
            flex: 1;

            cursor: pointer;
        }

        .nome {
            font-weight: bold;
        }

        .egresso {
            display: inline-block;

            margin-left: 8px;

            padding:
                3px
                7px;

            border-radius: 10px;

            background: #eef2ff;

            color: #4338ca;

            font-size: 11px;
        }

        .acoes {
            display: flex;

            gap: 10px;

            margin-top: 20px;
        }

        button,
        .botao {
            display: inline-block;

            padding:
                11px
                18px;

            border: none;

            border-radius: 7px;

            background: #111827;

            color: white;

            text-decoration: none;

            cursor: pointer;

            font-size: 14px;
        }

        .botao-secundario {
            background: #6b7280;
        }

        .contador {
            margin:
                0
                0
                15px;

            color: #6b7280;
        }

    </style>

</head>


<body>

<div class="container">

    <div class="cabecalho">

        <h1>
            Discentes da Produção
        </h1>

        <p>
            Vincule alunos e egressos
            participantes desta produção.
        </p>

    </div>


    <?php if (
        isset($_GET['salvo'])
        &&
        $_GET['salvo'] === '1'
    ): ?>

        <div class="mensagem">
            Discentes vinculados com sucesso.
        </div>

    <?php endif; ?>


    <!-- ======================================== -->
    <!-- PRODUÇÃO -->
    <!-- ======================================== -->

    <div class="painel">

        <div class="producao-titulo">

            <strong>
                <?= e(
                    $producao['titulo']
                ) ?>
            </strong>

        </div>

        <div class="producao-info">

            <?= e(
                $producao['tipo']
                ?? 'Não informado'
            ) ?>

            •

            <?= (int)
                $producao['ano']
            ?>

        </div>

    </div>


    <!-- ======================================== -->
    <!-- DISCENTES -->
    <!-- ======================================== -->

    <form method="POST">

        <input
            type="hidden"
            name="producao_id"
            value="<?= $producaoId ?>"
        >


        <div class="painel">

            <input
                type="text"
                id="buscaDiscente"
                class="busca"
                placeholder="Pesquisar discente..."
            >


            <p class="contador">

                Selecionados:

                <strong id="quantidadeSelecionados">
                    0
                </strong>

            </p>


            <div
                class="lista"
                id="listaDiscentes"
            >

                <?php foreach (
                    $discentes
                    as $discente
                ): ?>

                    <?php

                    $selecionado =
                        in_array(
                            (int)
                            $discente['id'],
                            $discentesVinculados,
                            true
                        );

                    ?>

                    <div
                        class="discente"
                        data-nome="<?=
                            e(
                                strtolower(
                                    $discente['nome']
                                )
                            )
                        ?>"
                    >

                        <input
                            type="checkbox"

                            class="checkbox-discente"

                            name="discentes[]"

                            id="discente_<?=
                                (int)
                                $discente['id']
                            ?>"

                            value="<?=
                                (int)
                                $discente['id']
                            ?>"

                            <?= $selecionado
                                ? 'checked'
                                : ''
                            ?>
                        >


                        <label
                            for="discente_<?=
                                (int)
                                $discente['id']
                            ?>"
                        >

                            <span class="nome">

                                <?= e(
                                    $discente['nome']
                                ) ?>

                            </span>


                            <?php if (
                                (int)
                                $discente[
                                    'egresso'
                                ] === 1
                            ): ?>

                                <span class="egresso">
                                    Egresso
                                </span>

                            <?php endif; ?>

                        </label>

                    </div>

                <?php endforeach; ?>

            </div>


            <div class="acoes">

                <button
                    type="submit"
                >
                    Salvar vínculos
                </button>


                <a
                    href="../dashboard/index.php"
                    class="
                        botao
                        botao-secundario
                    "
                >
                    Voltar
                </a>

            </div>

        </div>

    </form>

</div>


<script>

// ========================================================
// BUSCA DE DISCENTES
// ========================================================

const busca =
    document.getElementById(
        'buscaDiscente'
    );

const itens =
    document.querySelectorAll(
        '.discente'
    );


busca.addEventListener(
    'input',
    function ()
    {

        const termo =
            this.value
                .toLowerCase()
                .trim();


        itens.forEach(
            function (item)
            {

                const nome =
                    item.dataset.nome;

                item.style.display =
                    nome.includes(
                        termo
                    )
                        ? 'flex'
                        : 'none';
            }
        );
    }
);


// ========================================================
// CONTADOR
// ========================================================

const checkboxes =
    document.querySelectorAll(
        '.checkbox-discente'
    );

const contador =
    document.getElementById(
        'quantidadeSelecionados'
    );


function atualizarContador()
{
    let quantidade = 0;

    checkboxes.forEach(
        function (checkbox)
        {

            if (
                checkbox.checked
            ) {

                quantidade++;
            }
        }
    );

    contador.textContent =
        quantidade;
}


checkboxes.forEach(
    function (checkbox)
    {

        checkbox.addEventListener(
            'change',
            atualizarContador
        );
    }
);


atualizarContador();

</script>

</body>

</html>