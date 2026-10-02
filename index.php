<?php

$sistemaOnline = false;

try {

    require_once __DIR__ . '/config/conexao.php';

    $sistemaOnline =
        isset($pdo)
        && $pdo instanceof PDO;

} catch (Throwable $e) {

    $sistemaOnline = false;

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
        PPGA Analytics
    </title>

    <link
        rel="stylesheet"
        href="assets/css/home.css"
    >

</head>

<body>

    <main class="pagina-inicial">

        <section class="hero">

            <div class="hero-conteudo">

                <div class="marca">

                    <span class="marca-selo">
                        PPGA
                    </span>

                    <span
                        class="
                            status-sistema
                            <?= $sistemaOnline
                                ? 'status-online'
                                : 'status-offline'
                            ?>
                        "
                    >

                        <span class="status-ponto"></span>

                        <?= $sistemaOnline
                            ? 'Sistema disponível'
                            : 'Banco indisponível'
                        ?>

                    </span>

                </div>

                <h1>
                    PPGA Analytics
                </h1>

                <p class="subtitulo">
                    Plataforma de análise da produção científica,
                    indicadores institucionais e acompanhamento
                    da avaliação CAPES.
                </p>

                <div class="acoes-principais">

                    <a
                        class="botao botao-principal"
                        href="pages/dashboard/index.php"
                    >
                        Acessar Dashboard
                    </a>

                    <a
                        class="botao botao-secundario"
                        href="pages/lattes/index.php"
                    >
                        Currículos Lattes
                    </a>

                    <a
                        class="botao botao-secundario"
                        href="pages/docentes/index.php"
                    >
                        Docentes
                    </a>

                </div>

            </div>

            <aside class="hero-painel">

                <div class="painel-topo">

                    <span class="painel-etiqueta">
                        Visão institucional
                    </span>

                    <strong>
                        Produção científica em um só lugar
                    </strong>

                </div>

                <div class="atalhos">

                    <a
                        class="atalho"
                        href="pages/comparativos/index.php"
                    >

                        <span class="atalho-icone">
                            ↔
                        </span>

                        <span>

                            <strong>
                                Comparativos
                            </strong>

                            <small>
                                Docentes, anos, quadriênios e linhas
                            </small>

                        </span>

                    </a>

                    <a
                        class="atalho"
                        href="pages/relatorios/index.php"
                    >

                        <span class="atalho-icone">
                            ↓
                        </span>

                        <span>

                            <strong>
                                Relatórios
                            </strong>

                            <small>
                                PDF, Word, Excel e CSV
                            </small>

                        </span>

                    </a>

                    <a
                        class="atalho"
                        href="pages/periodicos/index.php"
                    >

                        <span class="atalho-icone">
                            ⌕
                        </span>

                        <span>

                            <strong>
                                Periódicos
                            </strong>

                            <small>
                                ISSN, indexação e produções
                            </small>

                        </span>

                    </a>

                    <a
                        class="atalho"
                        href="pages/discentes/index.php"
                    >

                        <span class="atalho-icone">
                            +
                        </span>

                        <span>

                            <strong>
                                Discentes e Egressos
                            </strong>

                            <small>
                                Participação nas produções
                            </small>

                        </span>

                    </a>

                </div>

            </aside>

        </section>

        <section class="recursos">

            <article class="recurso">

                <span class="recurso-numero">
                    01
                </span>

                <h2>
                    Indicadores CAPES
                </h2>

                <p>
                    Acompanhe classificações, pontuação,
                    participação discente e indicadores
                    do quadriênio ativo.
                </p>

            </article>

            <article class="recurso">

                <span class="recurso-numero">
                    02
                </span>

                <h2>
                    Análise comparativa
                </h2>

                <p>
                    Compare docentes, médias do programa,
                    anos, quadriênios e linhas de pesquisa.
                </p>

            </article>

            <article class="recurso">

                <span class="recurso-numero">
                    03
                </span>

                <h2>
                    Histórico preservado
                </h2>

                <p>
                    Cada importação do Currículo Lattes
                    mantém sua própria versão para
                    consulta e auditoria.
                </p>

            </article>

        </section>

        <footer class="rodape">

            <span>
                PPGA Analytics
            </span>

            <span>
                Dashboard de Produção Científica
            </span>

        </footer>

    </main>

</body>

</html>