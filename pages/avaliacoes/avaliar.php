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
// FUNÇÃO PARA ESCAPAR HTML
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
// RECEBE IDs
// ========================================================

$producaoId =
    isset($_GET['producao_id'])
        ? (int) $_GET['producao_id']
        : (
            isset($_POST['producao_id'])
                ? (int) $_POST['producao_id']
                : 0
        );

$quadrienioId =
    isset($_GET['quadrienio_id'])
        ? (int) $_GET['quadrienio_id']
        : (
            isset($_POST['quadrienio_id'])
                ? (int) $_POST['quadrienio_id']
                : 0
        );


if (
    $producaoId <= 0
    ||
    $quadrienioId <= 0
) {

    die(
        'Produção ou quadriênio inválido.'
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
            p.doi,
            p.issn,

            tp.nome
                AS tipo_producao,

            pe.nome
                AS periodico,

            (
                SELECT
                    GROUP_CONCAT(
                        DISTINCT d.nome
                        ORDER BY d.nome
                        SEPARATOR ', '
                    )

                FROM producoes_docentes pd

                INNER JOIN docentes d
                    ON d.id = pd.docente_id

                WHERE
                    pd.producao_id = p.id
            )
                AS docentes

        FROM producoes p

        LEFT JOIN tipos_producao tp
            ON tp.id =
                p.tipo_producao_id

        LEFT JOIN periodicos pe
            ON pe.id =
                p.periodico_id

        WHERE
            p.id = :producao_id

        LIMIT 1
    ");

$stmtProducao->execute([
    ':producao_id' =>
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
// BUSCA QUADRIÊNIO
// ========================================================

$stmtQuadrienio =
    $pdo->prepare("
        SELECT
            id,
            ano_inicio,
            ano_fim,
            descricao,
            ativo
        FROM quadrienios
        WHERE id = :id
        LIMIT 1
    ");

$stmtQuadrienio->execute([
    ':id' =>
        $quadrienioId
]);

$quadrienio =
    $stmtQuadrienio->fetch(
        PDO::FETCH_ASSOC
    );


if (!$quadrienio) {

    die(
        'Quadriênio não encontrado.'
    );
}


// ========================================================
// CLASSIFICAÇÕES E PONTUAÇÕES
// ========================================================

$stmtClassificacoes =
    $pdo->prepare("
        SELECT
            c.id,
            c.codigo,
            c.descricao,
            qc.pontos

        FROM quadrienios_classificacoes qc

        INNER JOIN classificacoes_capes c
            ON c.id =
                qc.classificacao_id

        WHERE
            qc.quadrienio_id =
                :quadrienio_id

        ORDER BY
            qc.pontos DESC,
            c.codigo ASC
    ");

$stmtClassificacoes->execute([
    ':quadrienio_id' =>
        $quadrienioId
]);

$classificacoes =
    $stmtClassificacoes->fetchAll(
        PDO::FETCH_ASSOC
    );


if (
    count(
        $classificacoes
    ) === 0
) {

    die(
        'Nenhuma classificação configurada para este quadriênio.'
    );
}


// ========================================================
// BUSCA AVALIAÇÃO EXISTENTE
// ========================================================

$stmtAvaliacao =
    $pdo->prepare("
        SELECT
            id,
            classificacao_id,
            pontuacao_interna,
            status,
            observacao,
            data_avaliacao

        FROM avaliacoes_producao

        WHERE
            producao_id =
                :producao_id

            AND quadrienio_id =
                :quadrienio_id

        LIMIT 1
    ");

$stmtAvaliacao->execute([

    ':producao_id' =>
        $producaoId,

    ':quadrienio_id' =>
        $quadrienioId
]);

$avaliacao =
    $stmtAvaliacao->fetch(
        PDO::FETCH_ASSOC
    );


// ========================================================
// VALORES INICIAIS
// ========================================================

$classificacaoSelecionada =
    $avaliacao
        ? (int)
            $avaliacao[
                'classificacao_id'
            ]
        : 0;

$observacao =
    $avaliacao[
        'observacao'
    ]
    ?? '';

$mensagemErro = '';


// ========================================================
// SALVAR AVALIAÇÃO
// ========================================================

if (
    $_SERVER['REQUEST_METHOD']
    ===
    'POST'
) {

    $classificacaoSelecionada =
        isset(
            $_POST[
                'classificacao_id'
            ]
        )
            ? (int)
                $_POST[
                    'classificacao_id'
                ]
            : 0;

    $observacao =
        isset(
            $_POST[
                'observacao'
            ]
        )
            ? trim(
                $_POST[
                    'observacao'
                ]
            )
            : '';


    // ====================================================
    // VALIDA CLASSIFICAÇÃO E BUSCA PONTOS
    // ====================================================

    $stmtPontuacao =
        $pdo->prepare("
            SELECT
                qc.pontos,
                c.codigo

            FROM quadrienios_classificacoes qc

            INNER JOIN classificacoes_capes c
                ON c.id =
                    qc.classificacao_id

            WHERE
                qc.quadrienio_id =
                    :quadrienio_id

                AND qc.classificacao_id =
                    :classificacao_id

            LIMIT 1
        ");

    $stmtPontuacao->execute([

        ':quadrienio_id' =>
            $quadrienioId,

        ':classificacao_id' =>
            $classificacaoSelecionada
    ]);

    $dadosPontuacao =
        $stmtPontuacao->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$dadosPontuacao) {

        $mensagemErro =
            'Selecione uma classificação válida.';
    }

    else {

        $pontuacao =
            (float)
            $dadosPontuacao[
                'pontos'
            ];


        try {

            $pdo->beginTransaction();


            // ============================================
            // INSERT / UPDATE
            //
            // A tabela possui UNIQUE:
            //
            // producao_id + quadrienio_id
            //
            // Então, caso a avaliação já exista,
            // apenas atualizamos.
            // ============================================

            $stmtSalvar =
                $pdo->prepare("
                    INSERT INTO avaliacoes_producao
                    (
                        producao_id,
                        quadrienio_id,
                        classificacao_id,
                        pontuacao_interna,
                        status,
                        observacao,
                        data_avaliacao
                    )

                    VALUES
                    (
                        :producao_id,
                        :quadrienio_id,
                        :classificacao_id,
                        :pontuacao,
                        'AVALIADO',
                        :observacao,
                        NOW()
                    )

                    ON DUPLICATE KEY UPDATE

                        classificacao_id =
                            :classificacao_id_update,

                        pontuacao_interna =
                            :pontuacao_update,

                        status =
                            'AVALIADO',

                        observacao =
                            :observacao_update,

                        data_avaliacao =
                            NOW()
                ");

            $stmtSalvar->execute([

                ':producao_id' =>
                    $producaoId,

                ':quadrienio_id' =>
                    $quadrienioId,

                ':classificacao_id' =>
                    $classificacaoSelecionada,

                ':pontuacao' =>
                    $pontuacao,

                ':observacao' =>
                    $observacao !== ''
                        ? $observacao
                        : null,

                ':classificacao_id_update' =>
                    $classificacaoSelecionada,

                ':pontuacao_update' =>
                    $pontuacao,

                ':observacao_update' =>
                    $observacao !== ''
                        ? $observacao
                        : null
            ]);


            $pdo->commit();


            // ============================================
            // REDIRECIONA PARA LISTAGEM
            // ============================================

            header(
                'Location: index.php'
            );

            exit;

        }

        catch (Throwable $erro) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            $mensagemErro =
                'Erro ao salvar avaliação: '
                . $erro->getMessage();
        }
    }
}


// ========================================================
// MONTA MAPA DE PONTUAÇÃO PARA JAVASCRIPT
// ========================================================

$mapaPontuacoes = [];

foreach (
    $classificacoes
    as $classificacao
) {

    $mapaPontuacoes[
        (int)
        $classificacao['id']
    ] = [

        'codigo' =>
            $classificacao[
                'codigo'
            ],

        'pontos' =>
            (float)
            $classificacao[
                'pontos'
            ]
    ];
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
        Avaliar Produção - PPGA Analytics
    </title>

    <link rel="stylesheet" href="../../assets/css/avaliacoes.css">

</head>

<body>

<div class="container container-formulario">

    <!-- ======================================== -->
    <!-- CABEÇALHO -->
    <!-- ======================================== -->

    <div class="cabecalho">

        <h1>
            Avaliar Produção
        </h1>

        <p>
            Classificação e pontuação
            da produção científica
        </p>

    </div>


    <?php if (
        $mensagemErro !== ''
    ): ?>

        <div class="erro">

            <?= e(
                $mensagemErro
            ) ?>

        </div>

    <?php endif; ?>


    <?php if (
        $avaliacao
    ): ?>

        <div class="avaliacao-existente">

            Esta produção já possui
            uma avaliação neste quadriênio.

            Você está editando
            a avaliação existente.

        </div>

    <?php endif; ?>


    <!-- ======================================== -->
    <!-- DADOS DA PRODUÇÃO -->
    <!-- ======================================== -->

    <div class="painel">

        <div class="informacoes">

            <div
                class="
                    informacao
                    informacao-grande
                "
            >

                <span>
                    Produção
                </span>

                <strong>

                    <?= e(
                        $producao[
                            'titulo'
                        ]
                    ) ?>

                </strong>

            </div>


            <div class="informacao">

                <span>
                    Docente(s)
                </span>

                <strong>

                    <?= e(
                        $producao[
                            'docentes'
                        ]
                        ??
                        'Não informado'
                    ) ?>

                </strong>

            </div>


            <div class="informacao">

                <span>
                    Tipo
                </span>

                <strong>

                    <?= e(
                        $producao[
                            'tipo_producao'
                        ]
                        ??
                        'Não informado'
                    ) ?>

                </strong>

            </div>


            <div class="informacao">

                <span>
                    Ano
                </span>

                <strong>

                    <?= e(
                        $producao[
                            'ano'
                        ]
                    ) ?>

                </strong>

            </div>


            <div class="informacao">

                <span>
                    Quadriênio
                </span>

                <strong>

                    <?= e(
                        $quadrienio[
                            'ano_inicio'
                        ]
                    ) ?>

                    -

                    <?= e(
                        $quadrienio[
                            'ano_fim'
                        ]
                    ) ?>

                </strong>

            </div>


            <?php if (
                !empty(
                    $producao[
                        'periodico'
                    ]
                )
            ): ?>

                <div class="informacao">

                    <span>
                        Periódico
                    </span>

                    <strong>

                        <?= e(
                            $producao[
                                'periodico'
                            ]
                        ) ?>

                    </strong>

                </div>

            <?php endif; ?>


            <?php if (
                !empty(
                    $producao[
                        'doi'
                    ]
                )
            ): ?>

                <div class="informacao">

                    <span>
                        DOI
                    </span>

                    <strong>

                        <?= e(
                            $producao[
                                'doi'
                            ]
                        ) ?>

                    </strong>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- ======================================== -->
    <!-- FORMULÁRIO DE AVALIAÇÃO -->
    <!-- ======================================== -->

    <div class="painel">

        <form
            method="POST"
            class="formulario"
        >

            <input
                type="hidden"
                name="producao_id"
                value="<?=
                    $producaoId
                ?>"
            >

            <input
                type="hidden"
                name="quadrienio_id"
                value="<?=
                    $quadrienioId
                ?>"
            >


            <!-- CLASSIFICAÇÃO -->

            <div class="campo">

                <label
                    for="classificacao_id"
                >
                    Classificação
                </label>

                <select
                    name="classificacao_id"
                    id="classificacao_id"
                    required
                >

                    <option value="">
                        Selecione
                    </option>

                    <?php foreach (
                        $classificacoes
                        as $classificacao
                    ): ?>

                        <option

                            value="<?=
                                (int)
                                $classificacao[
                                    'id'
                                ]
                            ?>"

                            <?=
                                $classificacaoSelecionada
                                ===
                                (int)
                                $classificacao[
                                    'id'
                                ]
                                    ? 'selected'
                                    : ''
                            ?>

                        >

                            <?= e(
                                $classificacao[
                                    'codigo'
                                ]
                            ) ?>

                            -

                            <?= number_format(
                                (float)
                                $classificacao[
                                    'pontos'
                                ],
                                0,
                                ',',
                                '.'
                            ) ?>

                            pontos

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- PONTUAÇÃO AUTOMÁTICA -->

            <div class="pontuacao-box">

                <span>
                    Pontuação calculada
                    automaticamente
                </span>

                <strong id="pontuacao">

                    -

                </strong>

            </div>


            <!-- OBSERVAÇÃO -->

            <div class="campo">

                <label
                    for="observacao"
                >
                    Observação
                </label>

                <textarea
                    name="observacao"
                    id="observacao"
                    placeholder="Observações sobre a avaliação..."
                ><?= e(
                    $observacao
                ) ?></textarea>

            </div>


            <!-- AÇÕES -->

            <div class="acoes">

                <button
                    type="submit"
                >

                    <?=
                        $avaliacao
                            ? 'Salvar alterações'
                            : 'Salvar avaliação'
                    ?>

                </button>

                <a
                    href="index.php"
                    class="
                        botao
                        botao-secundario
                    "
                >
                    Cancelar
                </a>

            </div>

        </form>

    </div>

</div>


<!-- ===================================================== -->
<!-- PONTUAÇÃO AUTOMÁTICA -->
<!-- ===================================================== -->

<script>

const pontuacoes =
    <?= json_encode(
        $mapaPontuacoes,
        JSON_UNESCAPED_UNICODE
        |
        JSON_UNESCAPED_SLASHES
    ) ?>;


const selectClassificacao =
    document.getElementById(
        'classificacao_id'
    );

const campoPontuacao =
    document.getElementById(
        'pontuacao'
    );


function atualizarPontuacao()
{
    const id =
        selectClassificacao.value;

    if (
        !id
        ||
        !pontuacoes[id]
    ) {

        campoPontuacao.textContent =
            '-';

        return;
    }


    const pontos =
        Number(
            pontuacoes[id].pontos
        );


    campoPontuacao.textContent =
        pontos.toLocaleString(
            'pt-BR',
            {
                maximumFractionDigits:
                    2
            }
        )
        +
        ' pts';
}


selectClassificacao.addEventListener(
    'change',
    atualizarPontuacao
);


atualizarPontuacao();

</script>

</body>

</html>