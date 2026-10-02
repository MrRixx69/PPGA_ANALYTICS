<?php

require_once '../../config/conexao.php';

$tipos = $pdo->query("
    SELECT id, nome
    FROM tipos_producao
    ORDER BY nome
")->fetchAll();

$instituicoes = $pdo->query("
    SELECT id, nome, sigla
    FROM instituicoes
    ORDER BY nome
")->fetchAll();

$docentes = $pdo->query("
    SELECT id, nome
    FROM docentes
    WHERE ativo = 1
    ORDER BY nome
")->fetchAll();

$linhas = $pdo->query("
    SELECT id, nome, descricao
    FROM linhas_pesquisa
    ORDER BY nome
")->fetchAll();

$projetos = $pdo->query("
    SELECT
        p.id,
        p.nome,
        p.situacao,
        p.data_inicio,
        p.data_fim,
        GROUP_CONCAT(
            DISTINCT d.nome
            ORDER BY d.nome
            SEPARATOR ', '
        ) AS docentes
    FROM projetos p
    LEFT JOIN projetos_docentes pd
        ON pd.projeto_id = p.id
    LEFT JOIN docentes d
        ON d.id = pd.docente_id
    GROUP BY
        p.id,
        p.nome,
        p.situacao,
        p.data_inicio,
        p.data_fim
    ORDER BY
        CASE WHEN p.situacao = 'Em andamento' THEN 0 ELSE 1 END,
        p.data_inicio DESC,
        p.nome
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Produção - PPGA Analytics</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/metadados_producao.css">
</head>
<body>

<div class="container">

    <div class="page-header">
        <div>
            <h1>Nova produção</h1>
            <p>Cadastre uma produção científica</p>
        </div>

        <a href="index.php" class="btn">Voltar</a>
    </div>

    <form action="salvar.php" method="POST" class="form-card form-card-wide">

        <div class="metadata-section">
            <h2>Dados da produção</h2>
            <p class="helper-text">Informações básicas da produção científica.</p>

            <div class="form-group">
                <label for="titulo">Título *</label>
                <input type="text" id="titulo" name="titulo" required>
            </div>

            <div class="form-group">
                <label for="ano">Ano *</label>
                <input type="number" id="ano" name="ano" min="1900" max="2100" required>
            </div>

            <div class="form-group">
                <label for="tipo_producao_id">Tipo de produção *</label>
                <select id="tipo_producao_id" name="tipo_producao_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($tipos as $tipo): ?>
                        <option value="<?= (int) $tipo['id'] ?>">
                            <?= htmlspecialchars($tipo['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="instituicao_id">Instituição</label>
                <select id="instituicao_id" name="instituicao_id">
                    <option value="">Nenhuma</option>
                    <?php foreach ($instituicoes as $instituicao): ?>
                        <option value="<?= (int) $instituicao['id'] ?>">
                            <?= htmlspecialchars($instituicao['sigla'] ?: $instituicao['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Selecione</option>
                    <option value="Publicado">Publicado</option>
                    <option value="Aceito">Aceito</option>
                    <option value="Submetido">Submetido</option>
                    <option value="Em andamento">Em andamento</option>
                </select>
            </div>

            <div class="form-group metadata-flags">
                <label class="checkbox-item">
                    <input type="checkbox" name="internacional" value="1">
                    Produção internacional
                </label>

                <label class="checkbox-item">
                    <input type="checkbox" name="coautoria_internacional" value="1">
                    Coautoria internacional
                </label>
            </div>
        </div>

        <div class="metadata-section">
            <h2>Docentes / autores</h2>
            <p class="helper-text">Selecione os docentes do programa associados à produção.</p>

            <div class="checkbox-group">
                <?php foreach ($docentes as $docente): ?>
                    <label class="checkbox-item">
                        <input
                            type="checkbox"
                            name="docentes[]"
                            value="<?= (int) $docente['id'] ?>"
                        >
                        <?= htmlspecialchars($docente['nome']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="metadata-section">
            <h2>Linha(s) de pesquisa</h2>
            <p class="helper-text">
                Vincule a produção às linhas de pesquisa institucionais. É possível selecionar mais de uma.
            </p>

            <?php if ($linhas): ?>
                <div class="checkbox-group">
                    <?php foreach ($linhas as $linha): ?>
                        <label class="checkbox-item">
                            <input
                                type="checkbox"
                                name="linhas[]"
                                value="<?= (int) $linha['id'] ?>"
                            >
                            <span class="checkbox-item-content">
                                <span><?= htmlspecialchars($linha['nome']) ?></span>
                                <?php if (!empty($linha['descricao'])): ?>
                                    <small><?= htmlspecialchars($linha['descricao']) ?></small>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="helper-text">Nenhuma linha de pesquisa cadastrada.</p>
            <?php endif; ?>
        </div>

        <div class="metadata-section">
            <h2>Projeto(s) relacionado(s)</h2>
            <p class="helper-text">
                Selecione os projetos institucionais relacionados à produção. Os projetos em andamento aparecem primeiro.
            </p>

            <?php if ($projetos): ?>
                <div class="checkbox-group checkbox-group-scroll">
                    <?php foreach ($projetos as $projeto): ?>
                        <label class="checkbox-item">
                            <input
                                type="checkbox"
                                name="projetos[]"
                                value="<?= (int) $projeto['id'] ?>"
                            >
                            <span class="checkbox-item-content">
                                <span><?= htmlspecialchars($projeto['nome']) ?></span>
                                <small>
                                    <?= htmlspecialchars($projeto['situacao']) ?>
                                    <?php if (!empty($projeto['docentes'])): ?>
                                        · <?= htmlspecialchars($projeto['docentes']) ?>
                                    <?php endif; ?>
                                </small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="helper-text">Nenhum projeto cadastrado.</p>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <a href="index.php" class="btn">Cancelar</a>
            <button type="submit" class="btn btn-primary">Salvar produção</button>
        </div>

    </form>

</div>

</body>
</html>