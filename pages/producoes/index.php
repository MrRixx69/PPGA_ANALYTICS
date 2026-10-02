<?php

require_once '../../config/conexao.php';

$sql = "
    SELECT
        p.id,
        p.titulo,
        p.ano,
        p.internacional,
        p.status,
    
        tp.nome AS tipo,
    
        i.sigla AS instituicao
    
    FROM producoes p
    
    INNER JOIN tipos_producao tp
        ON tp.id = p.tipo_producao_id

    LEFT JOIN instituicoes i
        ON i.id = p.instituicao_id
        
    ORDER BY
        p.ano DESC,
        p.titulo";

$stmt = $pdo->query($sql);

$producoes = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Producoes - PPGA Analytics</title>
    <link rel = "stylesheet" href="../../assets/css/style.css">
</head>
<body>
    
   <div class="container">

    <div class="page-header">

        <div>
            <h1>Produções científicas</h1>
            <p>Gerenciamento das produções do PPGA</p>
        </div>

        <a
            href="novo.php"
            class="btn btn-primary"
        >
            + Nova Produção
        </a>

    </div>
             <div class="table-container">

        <table>


            <thead>

                <tr>

                    <th>
                        Título
                    </th>

                    <th>
                        Ano
                    </th>

                    <th>
                        Tipo
                    </th>

                    <th>
                        Instituição
                    </th>

                    <th>
                        Internacional
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Ações
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php foreach ($producoes as $producao): ?>


                    <tr>


                        <td>

                            <?= htmlspecialchars(
                                $producao['titulo']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $producao['ano']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $producao['tipo']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $producao['instituicao'] ?? '-'
                            ) ?>

                        </td>


                        <td>

                            <?= $producao['internacional']
                                ? 'Sim'
                                : 'Não'
                            ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $producao['status'] ?? '-'
                            ) ?>

                        </td>


                        <td class="actions">

                            <a
                                href="visualizar.php?id=<?= $producao['id'] ?>"
                                class="btn btn-small"
                            >
                                Visualizar
                            </a>


                            <a
                                href="editar.php?id=<?= $producao['id'] ?>"
                                class="btn btn-small btn-primary"
                            >
                                Editar
                            </a>

                        </td>


                    </tr>


                <?php endforeach; ?>


            </tbody>


        </table>

    </div>


        </div>
    </div>

</body>
</html>