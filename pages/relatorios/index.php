<?php

$raizProjeto = dirname(__DIR__, 2);

require_once
    $raizProjeto
    . DIRECTORY_SEPARATOR
    . 'config'
    . DIRECTORY_SEPARATOR
    . 'conexao.php';


// ========================================================
// DOCENTES ATIVOS
// ========================================================

$stmtDocentes = $pdo->query("
    SELECT
        id,
        nome
    FROM docentes
    WHERE ativo = 1
    ORDER BY nome
");

$docentes = $stmtDocentes->fetchAll(PDO::FETCH_ASSOC);


// ========================================================
// QUADRIÊNIO ATIVO
// ========================================================

$stmtQuadrienio = $pdo->query("
    SELECT
        id,
        ano_inicio,
        ano_fim,
        descricao
    FROM quadrienios
    WHERE ativo = 1
    ORDER BY ano_inicio DESC
    LIMIT 1
");

$quadrienioAtivo = $stmtQuadrienio->fetch(PDO::FETCH_ASSOC);


// ========================================================
// ANOS DISPONÍVEIS
// ========================================================

$stmtAnos = $pdo->query("
    SELECT DISTINCT ano
    FROM producoes
    WHERE ano IS NOT NULL
    ORDER BY ano DESC
");

$anos = $stmtAnos->fetchAll(PDO::FETCH_COLUMN);

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
        Relatórios - PPGA Analytics
    </title>

    <link
        rel="stylesheet"
        href="../../assets/css/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/relatorios.css"
    >
</head>

<body>

<div class="dashboard">

    <header class="dashboard-header">
        <div>
            <h1>
                Relatórios
            </h1>

            <p>
                Relatórios gerenciais do PPGA Analytics
            </p>
        </div>

        <div class="relatorios-acoes-topo">
            <a
                href="../dashboard/index.php"
                class="botao botao-secundario"
            >
                Voltar à Dashboard
            </a>
        </div>
    </header>


    <section class="painel painel-relatorio-destaque">
        <div class="painel-cabecalho">
            <h2>
                Relatório individual do docente
            </h2>

            <p>
                Gere um resumo consolidado da produção científica, indicadores CAPES,
                participação discente e internacionalização do docente selecionado.
            </p>
        </div>

        <?php if (count($docentes) === 0): ?>
            <div class="sem-dados">
                Nenhum docente ativo cadastrado.
            </div>
        <?php else: ?>

            <form
                method="GET"
                action="docente.php"
                class="form-relatorio-inicial"
            >
                <div class="campo-filtro">
                    <label for="docente">
                        Docente
                    </label>

                    <select
                        name="docente"
                        id="docente"
                        required
                    >
                        <?php foreach ($docentes as $docente): ?>
                            <option
                                value="<?= (int) $docente['id'] ?>"
                            >
                                <?= htmlspecialchars($docente['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>


                <div class="campo-filtro">
                    <label for="periodo">
                        Período
                    </label>

                    <select
                        name="periodo"
                        id="periodo"
                    >
                        <option value="">
                            Todo o histórico
                        </option>

                        <option value="ultimos_2">
                            Últimos 2 anos
                        </option>

                        <option value="ultimos_3">
                            Últimos 3 anos
                        </option>

                        <option value="ultimos_4">
                            Últimos 4 anos
                        </option>

                        <?php if ($quadrienioAtivo): ?>
                            <option
                                value="quadrienio"
                                selected
                            >
                                Quadriênio CAPES
                                (<?= (int) $quadrienioAtivo['ano_inicio'] ?>-<?= (int) $quadrienioAtivo['ano_fim'] ?>)
                            </option>
                        <?php endif; ?>

                        <option value="personalizado">
                            Intervalo personalizado
                        </option>

                        <optgroup label="Ano específico">
                            <?php foreach ($anos as $ano): ?>
                                <option value="<?= (int) $ano ?>">
                                    <?= (int) $ano ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>


                <div
                    class="campo-filtro campo-periodo-personalizado"
                    id="campoAnoInicio"
                >
                    <label for="ano_inicio">
                        Ano inicial
                    </label>

                    <input
                        type="number"
                        name="ano_inicio"
                        id="ano_inicio"
                        min="1900"
                        max="2100"
                    >
                </div>


                <div
                    class="campo-filtro campo-periodo-personalizado"
                    id="campoAnoFim"
                >
                    <label for="ano_fim">
                        Ano final
                    </label>

                    <input
                        type="number"
                        name="ano_fim"
                        id="ano_fim"
                        min="1900"
                        max="2100"
                    >
                </div>


                <div class="acoes-filtro">
                    <button
                        type="submit"
                        class="botao"
                    >
                        Gerar relatório
                    </button>
                </div>
            </form>

        <?php endif; ?>
    </section>


    <section class="cards cards-relatorios-tipos">
        <article class="card">
            <span class="card-titulo">
                Individual por docente
            </span>

            <strong class="card-numero card-status-texto">
                Disponível
            </strong>

            <span class="card-descricao">
                Indicadores, gráficos e lista detalhada de produções.
            </span>
        </article>

        <article class="card card-em-breve">
            <span class="card-titulo">
                Institucional
            </span>

            <strong class="card-numero card-status-texto">
                Próxima etapa
            </strong>

            <span class="card-descricao">
                Visão consolidada do programa e seus indicadores.
            </span>
        </article>

        <article class="card card-em-breve">
            <span class="card-titulo">
                Rankings
            </span>

            <strong class="card-numero card-status-texto">
                Próxima etapa
            </strong>

            <span class="card-descricao">
                Exportação dos rankings e recortes gerenciais.
            </span>
        </article>

        <article class="card card-em-breve">
            <span class="card-titulo">
                Comparativos
            </span>

            <strong class="card-numero card-status-texto">
                Próxima etapa
            </strong>

            <span class="card-descricao">
                Relatórios dos comparativos já disponíveis no sistema.
            </span>
        </article>
    </section>

</div>

<script>
const periodo = document.getElementById('periodo');
const campoAnoInicio = document.getElementById('campoAnoInicio');
const campoAnoFim = document.getElementById('campoAnoFim');

function atualizarCamposPersonalizados() {
    const exibir = periodo && periodo.value === 'personalizado';

    if (campoAnoInicio) {
        campoAnoInicio.style.display = exibir ? 'flex' : 'none';
    }

    if (campoAnoFim) {
        campoAnoFim.style.display = exibir ? 'flex' : 'none';
    }
}

if (periodo) {
    periodo.addEventListener('change', atualizarCamposPersonalizados);
    atualizarCamposPersonalizados();
}
</script>

</body>
</html>