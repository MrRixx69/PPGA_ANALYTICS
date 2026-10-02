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

require_once
    $raizProjeto
    . DIRECTORY_SEPARATOR
    . 'includes'
    . DIRECTORY_SEPARATOR
    . 'capes_area27.php';


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
            p.periodico_id,

            tp.nome
                AS tipo_producao,

            pe.nome
                AS periodico,

            pe.issn
                AS periodico_issn,

            pe.eissn
                AS periodico_eissn,

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


$classificacoesPorCodigo = [];

foreach ($classificacoes as $classificacao) {

    $classificacoesPorCodigo[
        strtoupper(
            (string) $classificacao['codigo']
        )
    ] = $classificacao;
}


$estruturaArea27Disponivel =
    tabelaExisteCapes(
        $pdo,
        'periodicos_indicadores'
    )
    &&
    colunaExisteCapes(
        $pdo,
        'avaliacoes_producao',
        'classificacao_calculada_id'
    );

$indicadoresPeriodico = [];
$calculoAutomatico =
    calcularClassificacaoPeriodicoArea27([]);

if (
    $estruturaArea27Disponivel
    &&
    !empty($producao['periodico_id'])
) {

    $indicadoresPeriodico =
        buscarIndicadoresAtuaisPeriodicoArea27(
            $pdo,
            (int) $producao['periodico_id']
        );

    $calculoAutomatico =
        calcularClassificacaoPeriodicoArea27(
            $indicadoresPeriodico
        );
}

$classificacaoCalculadaId = null;
$classeAntesAjusteId = null;
$pontosCalculadosQuadrienio = null;

if (!empty($calculoAutomatico['classe_final'])) {

    $codigoFinal =
        strtoupper(
            (string) $calculoAutomatico['classe_final']
        );

    if (isset($classificacoesPorCodigo[$codigoFinal])) {

        $classificacaoCalculadaId =
            (int) $classificacoesPorCodigo[$codigoFinal]['id'];

        $pontosCalculadosQuadrienio =
            (float) $classificacoesPorCodigo[$codigoFinal]['pontos'];
    }
}

if (!empty($calculoAutomatico['classe_antes_ajuste'])) {

    $codigoAntes =
        strtoupper(
            (string) $calculoAutomatico['classe_antes_ajuste']
        );

    if (isset($classificacoesPorCodigo[$codigoAntes])) {
        $classeAntesAjusteId =
            (int) $classificacoesPorCodigo[$codigoAntes]['id'];
    }
}


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

if (
    !$avaliacao
    &&
    $classificacaoCalculadaId !== null
) {
    $classificacaoSelecionada =
        $classificacaoCalculadaId;
}

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

        if (
            $classificacaoCalculadaId !== null
            &&
            $classificacaoSelecionada !== $classificacaoCalculadaId
            &&
            $observacao === ''
        ) {

            $mensagemErro =
                'A classificação confirmada difere do cálculo preliminar. '
                . 'Informe uma justificativa na observação.';
        }

        elseif (
            $classificacaoCalculadaId === null
            &&
            strtoupper((string) $dadosPontuacao['codigo']) !== 'NC'
            &&
            $observacao === ''
        ) {

            $mensagemErro =
                'Não há classe preliminar comprovada para este periódico. '
                . 'Informe uma justificativa para confirmar manualmente.';
        }

        else {

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
                        regra_versao,
                        classe_antes_ajuste_id,
                        classificacao_calculada_id,
                        ajuste_scielo,
                        fonte_determinante,
                        calculado_em,
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
                        :regra_versao,
                        :classe_antes_ajuste_id,
                        :classificacao_calculada_id,
                        :ajuste_scielo,
                        :fonte_determinante,
                        :calculado_em,
                        :classificacao_id,
                        :pontuacao,
                        'CONFIRMADO',
                        :observacao,
                        NOW()
                    )

                    ON DUPLICATE KEY UPDATE

                        regra_versao =
                            :regra_versao_update,

                        classe_antes_ajuste_id =
                            :classe_antes_ajuste_id_update,

                        classificacao_calculada_id =
                            :classificacao_calculada_id_update,

                        ajuste_scielo =
                            :ajuste_scielo_update,

                        fonte_determinante =
                            :fonte_determinante_update,

                        calculado_em =
                            :calculado_em_update,

                        classificacao_id =
                            :classificacao_id_update,

                        pontuacao_interna =
                            :pontuacao_update,

                        status =
                            'CONFIRMADO',

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

                ':regra_versao' =>
                    CAPES_AREA27_REGRA_VERSAO,

                ':classe_antes_ajuste_id' =>
                    $classeAntesAjusteId,

                ':classificacao_calculada_id' =>
                    $classificacaoCalculadaId,

                ':ajuste_scielo' =>
                    !empty($calculoAutomatico['ajuste_scielo'])
                        ? 1
                        : 0,

                ':fonte_determinante' =>
                    $calculoAutomatico['fonte_determinante']
                    ?? null,

                ':calculado_em' =>
                    $classificacaoCalculadaId !== null
                        ? date('Y-m-d H:i:s')
                        : null,

                ':classificacao_id' =>
                    $classificacaoSelecionada,

                ':pontuacao' =>
                    $pontuacao,

                ':observacao' =>
                    $observacao !== ''
                        ? $observacao
                        : null,

                ':regra_versao_update' =>
                    CAPES_AREA27_REGRA_VERSAO,

                ':classe_antes_ajuste_id_update' =>
                    $classeAntesAjusteId,

                ':classificacao_calculada_id_update' =>
                    $classificacaoCalculadaId,

                ':ajuste_scielo_update' =>
                    !empty($calculoAutomatico['ajuste_scielo'])
                        ? 1
                        : 0,

                ':fonte_determinante_update' =>
                    $calculoAutomatico['fonte_determinante']
                    ?? null,

                ':calculado_em_update' =>
                    $classificacaoCalculadaId !== null
                        ? date('Y-m-d H:i:s')
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


    <?php if (!$estruturaArea27Disponivel): ?>

        <div class="erro">
            A estrutura nova da Área 27 ainda não foi instalada.
            Execute a migração
            <strong>database/migrations/05_indicadores_periodicos_area27.sql</strong>
            antes de confirmar avaliações pelo novo fluxo.
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
    <!-- CÁLCULO PRELIMINAR ÁREA 27 -->
    <!-- ======================================== -->

    <div class="painel calculo-area27-painel">

        <div class="calculo-area27-cabecalho">
            <div>
                <span class="rotulo-secao">Cálculo preliminar · Área 27</span>
                <h2>
                    <?= e(
                        $calculoAutomatico['classe_final']
                        ?? 'Pendente'
                    ) ?>
                </h2>
                <p>
                    <?php if (!empty($calculoAutomatico['fonte_determinante'])): ?>
                        Fonte determinante:
                        <strong><?= e($calculoAutomatico['fonte_determinante']) ?></strong>
                    <?php else: ?>
                        Nenhuma posição confirmada foi suficiente para classificar o artigo.
                    <?php endif; ?>
                </p>
            </div>

            <div class="calculo-area27-pontos">
                <span>Pontuação projetada</span>
                <strong>
                    <?php if ($pontosCalculadosQuadrienio !== null): ?>
                        <?= number_format(
                            $pontosCalculadosQuadrienio,
                            0,
                            ',',
                            '.'
                        ) ?> pts
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </strong>
            </div>
        </div>


        <?php if (empty($producao['periodico_id'])): ?>

            <div class="calculo-aviso">
                Esta produção não possui periódico vinculado. O cálculo automático
                por ABDC/ABS/JCR/SJR/SPELL/SciELO não pode ser executado.
            </div>

        <?php elseif (!$estruturaArea27Disponivel): ?>

            <div class="calculo-aviso">
                Instale a migração da Área 27 para habilitar as evidências do periódico.
            </div>

        <?php else: ?>

            <div class="evidencias-area27">

                <?php foreach (
                    ['ABDC', 'ABS', 'JCR', 'SJR', 'SPELL', 'SCIELO']
                    as $base
                ): ?>

                    <?php
                    $evidencia =
                        $indicadoresPeriodico[$base]
                        ?? null;
                    ?>

                    <div class="evidencia-area27">
                        <span class="evidencia-base">
                            <?= $base === 'SCIELO' ? 'SciELO Brasil' : e($base) ?>
                        </span>

                        <?php if ($evidencia): ?>

                            <strong>
                                <?php if (
                                    $base === 'SPELL'
                                    &&
                                    $evidencia['percentual'] !== null
                                ): ?>
                                    Top <?= e($evidencia['percentual']) ?>%
                                <?php else: ?>
                                    <?= e($evidencia['valor'] ?? '—') ?>
                                <?php endif; ?>
                            </strong>

                            <small>
                                <?= e($evidencia['status'] ?? 'PENDENTE') ?>
                                <?php if (!empty($evidencia['classe_candidata'])): ?>
                                    · candidata <?= e($evidencia['classe_candidata']) ?>
                                <?php endif; ?>
                            </small>

                        <?php else: ?>

                            <strong>—</strong>
                            <small>Sem evidência</small>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

            <?php if (!empty($calculoAutomatico['ajuste_scielo'])): ?>
                <div class="calculo-ajuste">
                    Ajuste SciELO Brasil aplicado:
                    <strong>
                        <?= e($calculoAutomatico['classe_antes_ajuste']) ?>
                        →
                        <?= e($calculoAutomatico['classe_final']) ?>
                    </strong>
                </div>
            <?php endif; ?>

            <?php if (!empty($calculoAutomatico['pendencias'])): ?>
                <div class="calculo-pendencias">
                    <strong>Pendências encontradas:</strong>
                    <ul>
                        <?php foreach ($calculoAutomatico['pendencias'] as $pendencia): ?>
                            <li>
                                <?= e($pendencia['base']) ?>:
                                <?= e($pendencia['motivo']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="calculo-acoes">
                <a
                    href="../periodicos/editar.php?id=<?= (int) $producao['periodico_id'] ?>"
                    class="botao botao-secundario"
                >
                    Gerenciar indicadores do periódico
                </a>
            </div>

        <?php endif; ?>

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
                    Classificação confirmada
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

                <small class="ajuda-campo">
                    O sistema sugere a classe calculada pelas evidências da revista.
                    A coordenação pode confirmar outra classe, mas deve justificar a divergência.
                </small>

            </div>


            <!-- PONTUAÇÃO AUTOMÁTICA -->

            <div class="pontuacao-box">

                <span>
                    Pontuação oficial após confirmação
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
                    <?= !$estruturaArea27Disponivel ? 'disabled' : '' ?>
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