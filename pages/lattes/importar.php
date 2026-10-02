<?php

require_once '../../config/conexao.php';


$sql = "
    SELECT

        d.id,

        d.nome,

        COALESCE(
            (
                SELECT
                    MAX(cl.versao)

                FROM curriculos_lattes cl

                WHERE cl.docente_id = d.id
            ),
            0
        ) AS ultima_versao

    FROM docentes d

    WHERE d.ativo = 1

    ORDER BY d.nome
";


$stmt =
    $pdo->query(
        $sql
    );


$docentes =
    $stmt->fetchAll();

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
        Importar Lattes - PPGA Analytics
    </title>

    <link
        rel="stylesheet"
        href="../../assets/css/lattes.css"
    >

</head>


<body>


<main class="lattes-page">


    <header class="lattes-header">


        <div>

            <h1>
                Importar currículo Lattes
            </h1>

            <p>
                Envie uma nova versão do currículo de um docente.
            </p>

        </div>


        <div class="header-actions">

            <a
                href="index.php"
                class="botao botao-secundario"
            >
                Voltar ao histórico
            </a>

        </div>


    </header>


    <section class="importacao-layout">


        <form
            action="upload.php"
            method="POST"
            enctype="multipart/form-data"
            class="painel form-card"
        >


            <div class="form-intro">

                <h2>
                    Nova importação
                </h2>

                <p>
                    Selecione o docente, escolha o arquivo do Currículo Lattes
                    e confirme a importação.
                </p>

            </div>


            <div class="form-grid">


                <div class="form-group form-group-full">


                    <label for="docente_id">

                        Docente
                        <span class="campo-obrigatorio">
                            *
                        </span>

                    </label>


                    <select
                        id="docente_id"
                        name="docente_id"
                        required
                    >


                        <option value="">
                            Selecione o docente
                        </option>


                        <?php foreach ($docentes as $docente): ?>


                            <option
                                value="<?= (int) $docente['id'] ?>"
                            >

                                <?= htmlspecialchars(
                                    $docente['nome']
                                ) ?>

                                <?php if (
                                    (int) $docente['ultima_versao']
                                    > 0
                                ): ?>

                                    — versão atual
                                    v<?= (int) $docente['ultima_versao'] ?>

                                <?php else: ?>

                                    — sem histórico

                                <?php endif; ?>

                            </option>


                        <?php endforeach; ?>


                    </select>


                    <small>
                        Uma nova importação sempre cria a próxima versão
                        e preserva o histórico existente.
                    </small>


                </div>


                <div class="form-group form-group-full">


                    <label for="arquivo">

                        Currículo Lattes
                        <span class="campo-obrigatorio">
                            *
                        </span>

                    </label>


                    <input
                        type="file"
                        id="arquivo"
                        name="arquivo"
                        accept=".pdf,.xml"
                        required
                    >


                    <small>
                        Formatos aceitos: PDF e XML. Tamanho máximo: 20 MB.
                    </small>


                </div>


                <div class="form-group">


                    <label for="data_atualizacao_lattes">
                        Data de atualização do Lattes
                    </label>


                    <input
                        type="date"
                        id="data_atualizacao_lattes"
                        name="data_atualizacao_lattes"
                    >


                    <small>
                        Campo opcional. Informe a data exibida no currículo,
                        quando disponível.
                    </small>


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
                    Importar currículo
                </button>


            </div>


        </form>


        <aside class="painel ajuda-card">


            <h2>
                Como funciona
            </h2>

            <p>
                O sistema processa o arquivo e mantém cada importação
                separada para permitir histórico e auditoria.
            </p>


            <div class="etapas">


                <div class="etapa">

                    <span class="etapa-numero">
                        1
                    </span>

                    <div>

                        <strong>
                            Selecione o docente
                        </strong>

                        <span>
                            O currículo será associado ao cadastro
                            institucional do professor.
                        </span>

                    </div>

                </div>


                <div class="etapa">

                    <span class="etapa-numero">
                        2
                    </span>

                    <div>

                        <strong>
                            Envie PDF ou XML
                        </strong>

                        <span>
                            O arquivo é armazenado e processado
                            para extrair os registros acadêmicos.
                        </span>

                    </div>

                </div>


                <div class="etapa">

                    <span class="etapa-numero">
                        3
                    </span>

                    <div>

                        <strong>
                            Nova versão automática
                        </strong>

                        <span>
                            A importação anterior permanece preservada
                            e a nova recebe o próximo número de versão.
                        </span>

                    </div>

                </div>


                <div class="etapa">

                    <span class="etapa-numero">
                        4
                    </span>

                    <div>

                        <strong>
                            Dados disponíveis
                        </strong>

                        <span>
                            Após o processamento, os registros extraídos
                            podem ser consultados no histórico.
                        </span>

                    </div>

                </div>


            </div>


            <div class="aviso-versao">
                O PPGA Analytics preserva as versões anteriores do currículo.
                Uma nova importação não apaga o histórico já registrado.
            </div>


        </aside>


    </section>


</main>


</body>

</html>