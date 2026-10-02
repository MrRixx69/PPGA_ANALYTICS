<?php

// ========================================================
// CAMINHOS E CONFIGURAÇÕES
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
    . 'config'
    . DIRECTORY_SEPARATOR
    . 'python.php';


// ========================================================
// FUNÇÃO PARA ATUALIZAR STATUS
// ========================================================

function atualizarStatus(
    PDO $pdo,
    int $id,
    string $status,
    ?string $observacao = null
): void {

    if ($observacao === null) {

        $sql = "
            UPDATE curriculos_lattes

            SET status = :status

            WHERE id = :id
        ";


        $stmt =
            $pdo->prepare(
                $sql
            );


        $stmt->execute([

            ':status' =>
                $status,

            ':id' =>
                $id

        ]);


        return;
    }


    $sql = "
        UPDATE curriculos_lattes

        SET
            status = :status,
            observacoes = :observacao

        WHERE id = :id
    ";


    $stmt =
        $pdo->prepare(
            $sql
        );


    $stmt->execute([

        ':status' =>
            $status,

        ':observacao' =>
            $observacao,

        ':id' =>
            $id

    ]);

}


// ========================================================
// FUNÇÕES AUXILIARES DO PDF
// ========================================================

function textoMinusculo(
    string $texto
): string {

    if (
        function_exists(
            'mb_strtolower'
        )
    ) {

        return mb_strtolower(
            $texto,
            'UTF-8'
        );

    }


    return strtolower(
        $texto
    );

}


// ========================================================

function normalizarNome(
    string $nome
): string {

    $nome =
        trim(
            $nome
        );


    if ($nome === '') {

        return '';

    }


    $convertido =
        @iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $nome
        );


    if ($convertido !== false) {

        $nome =
            $convertido;

    }


    $nome =
        strtoupper(
            $nome
        );


    $nome =
        preg_replace(
            '/[^A-Z0-9 ]+/',
            ' ',
            $nome
        );


    $nome =
        preg_replace(
            '/\s+/',
            ' ',
            $nome
        );


    return trim(
        $nome
    );

}


// ========================================================

function descobrirOrdemAutoria(
    string $nomeDocente,
    array $autores
): ?int {

    $docenteNormalizado =
        normalizarNome(
            $nomeDocente
        );


    if ($docenteNormalizado === '') {

        return null;

    }


    $partesDocente =
        explode(
            ' ',
            $docenteNormalizado
        );


    $primeiroNome =
        $partesDocente[0]
        ?? '';


    $sobrenomes =
        array_slice(
            $partesDocente,
            1
        );


    foreach (
        $autores
        as $indice => $autor
    ) {

        $autorNormalizado =
            normalizarNome(
                (string) $autor
            );


        if (
            $autorNormalizado
            ===
            $docenteNormalizado
        ) {

            return $indice + 1;

        }


        if (
            $primeiroNome === ''
            ||
            strpos(
                $autorNormalizado,
                $primeiroNome
            ) === false
        ) {

            continue;

        }


        foreach (
            $sobrenomes
            as $sobrenome
        ) {

            if (
                strlen(
                    $sobrenome
                ) < 3
            ) {

                continue;

            }


            if (
                strpos(
                    $autorNormalizado,
                    $sobrenome
                ) !== false
            ) {

                return $indice + 1;

            }

        }

    }


    return null;

}

function descobrirFuncaoProjeto(
    string $docenteNome,
    array $integrantes
): string {

    $docenteNormalizado =
        normalizarNome(
            $docenteNome
        );


    foreach (
        $integrantes
        as $integrante
    ) {

        if (!is_array(
            $integrante
        )) {

            continue;
        }


        $nomeIntegrante =
            trim(
                $integrante['nome']
                ?? ''
            );


        if ($nomeIntegrante === '') {

            continue;
        }


        $nomeNormalizado =
            normalizarNome(
                $nomeIntegrante
            );


        if (
            $nomeNormalizado
            ===
            $docenteNormalizado
        ) {

            $papel =
                trim(
                    $integrante['papel']
                    ?? ''
                );


            if ($papel !== '') {

                return $papel;
            }
        }
    }


    return 'Integrante';
}

function buscarOuCriarAgenciaFinanciadora(
    PDO $pdo,
    string $nome
): ?int {

    $nome =
        trim(
            $nome
        );


    if ($nome === '') {

        return null;
    }


    // =====================================
    // PROCURA AGÊNCIA
    // =====================================

    $stmtBusca =
        $pdo->prepare("
            SELECT id
            FROM agencias_financiadoras
            WHERE nome = :nome
            LIMIT 1
        ");


    $stmtBusca->execute([
        ':nome' => $nome
    ]);


    $agencia =
        $stmtBusca->fetch();


    if ($agencia) {

        return (int)
            $agencia['id'];
    }


    // =====================================
    // CRIA AGÊNCIA
    // =====================================

    $stmtInsert =
        $pdo->prepare("
            INSERT INTO agencias_financiadoras (
                nome,
                sigla
            )
            VALUES (
                :nome,
                NULL
            )
        ");


    $stmtInsert->execute([
        ':nome' => $nome
    ]);


    return (int)
        $pdo->lastInsertId();
}



// ========================================================
// FUNÇÕES AUXILIARES DE ORIENTAÇÕES
// ========================================================

function colunaExiste(
    PDO $pdo,
    string $tabela,
    string $coluna
): bool {

    $tabelaSegura =
        str_replace(
            '`',
            '``',
            $tabela
        );


    $stmt =
        $pdo->query(
            "SHOW COLUMNS FROM `$tabelaSegura`"
        );


    foreach (
        $stmt->fetchAll()
        as $dadosColuna
    ) {

        if (
            (
                $dadosColuna['Field']
                ?? null
            ) === $coluna
        ) {

            return true;
        }
    }


    return false;
}


// ========================================================

function buscarOuCriarDiscente(
    PDO $pdo,
    string $nome
): ?int {

    $nome = trim($nome);


    if ($nome === '') {

        return null;
    }


    // Primeiro tenta uma comparação direta.

    $stmt =
        $pdo->prepare("
            SELECT id
            FROM discentes
            WHERE TRIM(nome) = :nome
            LIMIT 1
        ");


    $stmt->execute([
        ':nome' => $nome
    ]);


    $discente =
        $stmt->fetch();


    if ($discente) {

        return (int)
            $discente['id'];
    }


    // Depois tenta uma comparação normalizada para evitar
    // duplicatas causadas apenas por acentos/maiúsculas.

    $nomeNormalizado =
        normalizarNome(
            $nome
        );


    $stmtTodos =
        $pdo->query("
            SELECT id, nome
            FROM discentes
        ");


    foreach (
        $stmtTodos->fetchAll()
        as $discenteExistente
    ) {

        if (
            normalizarNome(
                (string) (
                    $discenteExistente['nome']
                    ?? ''
                )
            ) === $nomeNormalizado
        ) {

            return (int)
                $discenteExistente['id'];
        }
    }


    // Não existe: cria somente com o nome. Os demais
    // campos de discentes são opcionais ou possuem default.

    $stmtInsert =
        $pdo->prepare("
            INSERT INTO discentes (
                nome
            )
            VALUES (
                :nome
            )
        ");


    $stmtInsert->execute([
        ':nome' => $nome
    ]);


    return (int)
        $pdo->lastInsertId();
}


// ========================================================

function buscarOuCriarInstituicao(
    PDO $pdo,
    string $nome
): ?int {

    $nome = trim($nome);


    if ($nome === '') {

        return null;
    }


    // A instituição é um dado complementar da orientação.
    // Se a estrutura da tabela não permitir cadastro automático,
    // a orientação continua sendo salva com instituicao_id = NULL.

    try {

        if (!colunaExiste(
            $pdo,
            'instituicoes',
            'nome'
        )) {

            return null;
        }


        $stmt =
            $pdo->prepare("
                SELECT id, nome
                FROM instituicoes
                WHERE TRIM(nome) = :nome
                LIMIT 1
            ");


        $stmt->execute([
            ':nome' => $nome
        ]);


        $instituicao =
            $stmt->fetch();


        if ($instituicao) {

            return (int)
                $instituicao['id'];
        }


        $nomeNormalizado =
            normalizarNome(
                $nome
            );


        $stmtTodos =
            $pdo->query("
                SELECT id, nome
                FROM instituicoes
            ");


        foreach (
            $stmtTodos->fetchAll()
            as $instituicaoExistente
        ) {

            if (
                normalizarNome(
                    (string) (
                        $instituicaoExistente['nome']
                        ?? ''
                    )
                ) === $nomeNormalizado
            ) {

                return (int)
                    $instituicaoExistente['id'];
            }
        }


        $stmtInsert =
            $pdo->prepare("
                INSERT INTO instituicoes (
                    nome
                )
                VALUES (
                    :nome
                )
            ");


        $stmtInsert->execute([
            ':nome' => $nome
        ]);


        return (int)
            $pdo->lastInsertId();

    } catch (Throwable $erro) {

        return null;
    }
}


// ========================================================

function descobrirNivelOrientacao(
    array $orientacao
): string {

    $categoria =
        trim(
            (string) (
                $orientacao['categoria']
                ?? ''
            )
        );


    $mapa = [
        'Dissertação de mestrado' =>
            'Mestrado',

        'Tese de doutorado' =>
            'Doutorado',

        'Trabalho de conclusão de curso de graduação' =>
            'Graduação',

        'Iniciação científica' =>
            'Iniciação científica',

        'Supervisão de pós-doutorado' =>
            'Pós-doutorado'
    ];


    if (isset(
        $mapa[$categoria]
    )) {

        return $mapa[$categoria];
    }


    if ($categoria === '') {

        return 'Não informado';
    }


    if (function_exists(
        'mb_substr'
    )) {

        return mb_substr(
            $categoria,
            0,
            50,
            'UTF-8'
        );
    }


    return substr(
        $categoria,
        0,
        50
    );
}


// ========================================================

function descobrirFuncaoOrientacao(
    array $orientacao
): string {

    $funcao =
        trim(
            (string) (
                $orientacao['funcao']
                ?? ''
            )
        );


    if ($funcao === '') {

        if (
            (
                $orientacao['categoria']
                ?? ''
            ) === 'Supervisão de pós-doutorado'
        ) {

            $funcao = 'Supervisor';

        } else {

            $funcao = 'Orientador';
        }
    }


    if (function_exists(
        'mb_substr'
    )) {

        return mb_substr(
            $funcao,
            0,
            30,
            'UTF-8'
        );
    }


    return substr(
        $funcao,
        0,
        30
    );
}


// ========================================================

function encontrarTipoArtigoId(
    PDO $pdo
): int {

    $stmt =
        $pdo->query(
            'SHOW COLUMNS FROM tipos_producao'
        );


    $colunas =
        $stmt->fetchAll();


    $nomesColunas = [];

    $colunasTexto = [];


    foreach (
        $colunas
        as $coluna
    ) {

        $nomeColuna =
            $coluna['Field']
            ?? null;


        $tipoColuna =
            strtolower(
                $coluna['Type']
                ?? ''
            );


        if ($nomeColuna === null) {

            continue;

        }


        $nomesColunas[] =
            $nomeColuna;


        if (
            str_contains(
                $tipoColuna,
                'char'
            )
            ||
            str_contains(
                $tipoColuna,
                'text'
            )
            ||
            str_contains(
                $tipoColuna,
                'enum'
            )
        ) {

            $colunasTexto[] =
                $nomeColuna;

        }

    }


    $candidatas = [

        'nome',
        'descricao',
        'tipo',
        'titulo',
        'nome_tipo',
        'descricao_tipo'

    ];


    $colunaTexto = null;


    foreach (
        $candidatas
        as $candidata
    ) {

        if (
            in_array(
                $candidata,
                $nomesColunas,
                true
            )
        ) {

            $colunaTexto =
                $candidata;

            break;

        }

    }


    if (
        $colunaTexto === null
        &&
        count(
            $colunasTexto
        ) > 0
    ) {

        $colunaTexto =
            $colunasTexto[0];

    }


    if ($colunaTexto === null) {

        throw new Exception(
            'Não foi possível identificar a coluna de nome '
            . 'da tabela tipos_producao.'
        );

    }


    $colunaSegura =
        str_replace(
            '`',
            '``',
            $colunaTexto
        );


    $sql = "
        SELECT id

        FROM tipos_producao

        WHERE
            UPPER(
                TRIM(`$colunaSegura`)
            ) IN (
                'ARTIGO',
                'ARTIGO CIENTÍFICO',
                'ARTIGO CIENTIFICO',
                'ARTIGO EM PERIÓDICO',
                'ARTIGO EM PERIODICO'
            )

            OR

            UPPER(
                TRIM(`$colunaSegura`)
            ) LIKE '%ARTIGO%'

        LIMIT 1
    ";


    $stmt =
        $pdo->query(
            $sql
        );


    $tipo =
        $stmt->fetch();


    if (!$tipo) {

        throw new Exception(
            'Não existe um tipo de produção referente a ARTIGO '
            . 'na tabela tipos_producao.'
        );

    }


    return (int) $tipo['id'];

}

// ========================================================

function encontrarTipoLivroId(
    PDO $pdo
): int {

    $stmt =
        $pdo->prepare("
            SELECT id
            FROM tipos_producao
            WHERE UPPER(TRIM(nome)) = 'LIVRO'
            LIMIT 1
        ");


    $stmt->execute();


    $tipo =
        $stmt->fetch();


    if (!$tipo) {

        throw new Exception(
            'Não existe um tipo de produção referente a LIVRO '
            . 'na tabela tipos_producao.'
        );
    }


    return (int) $tipo['id'];
}

// ========================================================


            function encontrarTipoCapituloId(
                PDO $pdo
            ): int {

                $stmt =
                    $pdo->prepare("
                        SELECT id
                        FROM tipos_producao
                        WHERE UPPER(TRIM(nome)) = 'CAPÍTULO DE LIVRO'
                        LIMIT 1
                    ");


                $stmt->execute();


                $tipo =
                    $stmt->fetch();


                if (!$tipo) {

                    throw new Exception(
                        'Não existe um tipo de produção referente a CAPÍTULO DE LIVRO '
                        . 'na tabela tipos_producao.'
                    );
                }


                return (int) $tipo['id'];
            }


// ========================================================

function encontrarTipoTrabalhoAnaisId(
    PDO $pdo
): int {

    $stmt =
        $pdo->prepare("
            SELECT id
            FROM tipos_producao
            WHERE UPPER(TRIM(nome)) = 'TRABALHO EM ANAIS'
            LIMIT 1
        ");


    $stmt->execute();


    $tipo =
        $stmt->fetch();


    if (!$tipo) {

        throw new Exception(
            'Não existe um tipo de produção referente a TRABALHO EM ANAIS '
            . 'na tabela tipos_producao.'
        );
    }


    return (int) $tipo['id'];
}

// ========================================================

function encontrarTipoResumoExpandidoId(
    PDO $pdo
): int {

    $stmt =
        $pdo->prepare("
            SELECT id
            FROM tipos_producao
            WHERE UPPER(TRIM(nome)) =
                  'RESUMO EXPANDIDO EM CONGRESSO'
            LIMIT 1
        ");


    $stmt->execute();


    $tipo =
        $stmt->fetch();


    if (!$tipo) {

        throw new Exception(
            'Não existe um tipo de produção referente a '
            . 'RESUMO EXPANDIDO EM CONGRESSO '
            . 'na tabela tipos_producao.'
        );
    }


    return (int) $tipo['id'];
}

function encontrarTipoResumoCongressoId(
    PDO $pdo
): int {

    $stmt =
        $pdo->prepare("
            SELECT id
            FROM tipos_producao
            WHERE UPPER(TRIM(nome)) =
                  'RESUMO EM CONGRESSO'
            LIMIT 1
        ");


    $stmt->execute();


    $tipo =
        $stmt->fetch();


    if (!$tipo) {

        throw new Exception(
            'Não existe um tipo de produção referente a '
            . 'RESUMO EM CONGRESSO '
            . 'na tabela tipos_producao.'
        );
    }


    return (int) $tipo['id'];
}

function encontrarTipoAssessoriaConsultoriaId(
    PDO $pdo
): int {

    $stmt =
        $pdo->prepare("
            SELECT id
            FROM tipos_producao
            WHERE UPPER(TRIM(nome)) =
                  'ASSESSORIA E CONSULTORIA'
            LIMIT 1
        ");


    $stmt->execute();


    $tipo =
        $stmt->fetch();


    if (!$tipo) {

        throw new Exception(
            'Não existe o tipo de produção '
            . 'ASSESSORIA E CONSULTORIA '
            . 'na tabela tipos_producao.'
        );
    }


    return (int) $tipo['id'];
}


// ========================================================

// ========================================================

function buscarOuCriarPeriodico(
    PDO $pdo,
    string $nome,
    ?string $issn
): ?int {

    $nome =
        trim(
            $nome
        );


    if ($nome === '') {

        return null;

    }


    if ($issn !== null) {

        $issn =
            trim(
                $issn
            );


        if ($issn === '') {

            $issn =
                null;

        }

    }


    // Primeiro tenta localizar pelo ISSN.

    if ($issn !== null) {

        $stmt =
            $pdo->prepare("

                SELECT id

                FROM periodicos

                WHERE issn = :issn

                LIMIT 1

            ");


        $stmt->execute([

            ':issn' =>
                $issn

        ]);


        $periodico =
            $stmt->fetch();


        if ($periodico) {

            return (int)
                $periodico['id'];

        }

    }


    // Depois tenta localizar pelo nome.

    $stmt =
        $pdo->prepare("

            SELECT
                id,
                issn

            FROM periodicos

            WHERE TRIM(nome) = :nome

            LIMIT 1

        ");


    $stmt->execute([

        ':nome' =>
            $nome

    ]);


    $periodico =
        $stmt->fetch();


    if ($periodico) {

        if (
            (
                $periodico['issn'] === null
                ||
                trim(
                    (string)
                    $periodico['issn']
                ) === ''
            )
            &&
            $issn !== null
        ) {

            $stmtAtualiza =
                $pdo->prepare("

                    UPDATE periodicos

                    SET issn = :issn

                    WHERE id = :id

                ");


            $stmtAtualiza->execute([

                ':issn' =>
                    $issn,

                ':id' =>
                    $periodico['id']

            ]);

        }


        return (int)
            $periodico['id'];

    }


    // Se não existe, cria.

    $stmt =
        $pdo->prepare("

            INSERT INTO periodicos
            (
                nome,
                issn
            )

            VALUES
            (
                :nome,
                :issn
            )

        ");


    $stmt->execute([

        ':nome' =>
            $nome,

        ':issn' =>
            $issn

    ]);


    return (int)
        $pdo->lastInsertId();

}


// ========================================================

function limparReprocessamentoPdf(
    PDO $pdo,
    int $curriculoId
): void {

    // =====================================
    // REMOVE ORIENTAÇÕES DESTA IMPORTAÇÃO
    // =====================================

    if (colunaExiste(
        $pdo,
        'orientacoes',
        'curriculo_lattes_id'
    )) {

        $stmtOrientacoesDocentes =
            $pdo->prepare("
                DELETE FROM orientacoes_docentes
                WHERE orientacao_id IN (
                    SELECT id
                    FROM orientacoes
                    WHERE curriculo_lattes_id = :id
                )
            ");


        $stmtOrientacoesDocentes->execute([
            ':id' => $curriculoId
        ]);


        $stmtOrientacoes =
            $pdo->prepare("
                DELETE FROM orientacoes
                WHERE curriculo_lattes_id = :id
            ");


        $stmtOrientacoes->execute([
            ':id' => $curriculoId
        ]);
    }

    $tabelasFilhas = [

        'avaliacoes_producao',
        'producoes_autores_externos',
        'producoes_discentes',
        'producoes_linhas',
        'producoes_projetos',
        'producoes_docentes'

    ];


    foreach (
        $tabelasFilhas
        as $tabela
    ) {

        $sql = "
            DELETE FROM `$tabela`

            WHERE producao_id IN (
                SELECT id

                FROM producoes

                WHERE curriculo_lattes_id = :id
            )
        ";


        $stmt =
            $pdo->prepare(
                $sql
            );


        $stmt->execute([

            ':id' =>
                $curriculoId

        ]);

    }


    $stmt =
        $pdo->prepare("

            DELETE FROM producoes

            WHERE curriculo_lattes_id = :id

        ");


    $stmt->execute([

        ':id' =>
            $curriculoId

    ]);


    $stmt =
        $pdo->prepare("

            DELETE FROM registros_lattes

            WHERE curriculo_lattes_id = :id

        ");


    $stmt->execute([

        ':id' =>
            $curriculoId

    ]);

    // =====================================
        // REMOVE PROJETOS DESTA IMPORTAÇÃO
        // =====================================

        $stmtProjetos =
            $pdo->prepare("
                DELETE FROM projetos
                WHERE curriculo_lattes_id = :curriculo_lattes_id
            ");


        $stmtProjetos->execute([
            ':curriculo_lattes_id' => $curriculoId
        ]);
}



// ========================================================
// VERIFICA SE UMA TABELA EXISTE
// ========================================================

function tabelaExiste(
    PDO $pdo,
    string $tabela
): bool {

    $stmt =
        $pdo->prepare("
            SELECT COUNT(*)

            FROM information_schema.tables

            WHERE table_schema = DATABASE()
              AND table_name = :tabela
        ");


    $stmt->execute([

        ':tabela' =>
            $tabela

    ]);


    return
        (int) $stmt->fetchColumn()
        > 0;

}


// ========================================================
// ========================================================
// SNAPSHOT OPERACIONAL ENTRE VERSÕES
// ========================================================
//
// curriculos_lattes + registros_lattes preservam TODAS as
// versões para histórico/auditoria.
//
// producoes, projetos e orientacoes representam o snapshot
// operacional vigente. Quando entra um PDF realmente novo,
// os metadados institucionais da versão anterior são
// reaproveitados quando conseguimos identificar o mesmo item.
// Depois disso, os snapshots normalizados antigos são removidos.
//
// Assim:
// - histórico bruto continua intacto;
// - Dashboard não soma versões repetidas;
// - vínculos institucionais não são perdidos em itens iguais.
// ========================================================

function encontrarCurriculoOperacionalAnterior(
    PDO $pdo,
    int $docenteId,
    int $curriculoAtualId
): ?int {

    $stmt =
        $pdo->prepare("
            SELECT
                cl.id

            FROM curriculos_lattes cl

            WHERE cl.docente_id = :docente_id
              AND cl.id <> :curriculo_atual_id
              AND cl.status = 'CONCLUIDO'
              AND
              (
                    EXISTS (
                        SELECT 1
                        FROM producoes p
                        WHERE p.curriculo_lattes_id = cl.id
                    )

                    OR EXISTS (
                        SELECT 1
                        FROM projetos pr
                        WHERE pr.curriculo_lattes_id = cl.id
                    )

                    OR EXISTS (
                        SELECT 1
                        FROM orientacoes o
                        WHERE o.curriculo_lattes_id = cl.id
                    )
              )

            ORDER BY cl.versao DESC

            LIMIT 1
        ");


    $stmt->execute([

        ':docente_id' =>
            $docenteId,

        ':curriculo_atual_id' =>
            $curriculoAtualId

    ]);


    $id =
        $stmt->fetchColumn();


    return $id !== false
        ? (int) $id
        : null;

}


// ========================================================

function transferirMetadadosInstitucionaisProducoes(
    PDO $pdo,
    int $curriculoAnteriorId,
    int $curriculoAtualId
): void {

    // ---------------------------------------------
    // MAPA: PRODUÇÃO ANTIGA -> PRODUÇÃO NOVA
    // ---------------------------------------------

    $pdo->exec(
        "
            DROP TEMPORARY TABLE IF EXISTS
            tmp_mapa_producoes_ppga
        "
    );


    $pdo->exec(
        "
            CREATE TEMPORARY TABLE
            tmp_mapa_producoes_ppga
            (
                antiga_id INT NOT NULL PRIMARY KEY,
                nova_id INT NOT NULL,
                KEY idx_tmp_mapa_producao_nova (nova_id)
            )
        "
    );


    $stmtMapaProducoes =
        $pdo->prepare("
            INSERT INTO tmp_mapa_producoes_ppga
            (
                antiga_id,
                nova_id
            )

            SELECT
                antiga.id,
                MIN(nova.id)

            FROM producoes antiga

            INNER JOIN producoes nova
                ON nova.curriculo_lattes_id = :curriculo_atual_id
                AND
                (
                    (
                        antiga.doi IS NOT NULL
                        AND TRIM(antiga.doi) <> ''
                        AND nova.doi IS NOT NULL
                        AND TRIM(nova.doi) <> ''
                        AND LOWER(TRIM(antiga.doi))
                            =
                            LOWER(TRIM(nova.doi))
                    )

                    OR

                    (
                        LOWER(TRIM(antiga.titulo))
                            =
                            LOWER(TRIM(nova.titulo))

                        AND antiga.ano = nova.ano

                        AND antiga.tipo_producao_id
                            =
                            nova.tipo_producao_id
                    )
                )

            WHERE antiga.curriculo_lattes_id
                =
                :curriculo_anterior_id

            GROUP BY antiga.id
        ");


    $stmtMapaProducoes->execute([

        ':curriculo_atual_id' =>
            $curriculoAtualId,

        ':curriculo_anterior_id' =>
            $curriculoAnteriorId

    ]);


    // ---------------------------------------------
    // CAMPOS INSTITUCIONAIS DA PRODUÇÃO
    // ---------------------------------------------

    $pdo->exec("
        UPDATE producoes nova

        INNER JOIN tmp_mapa_producoes_ppga mapa
            ON mapa.nova_id = nova.id

        INNER JOIN producoes antiga
            ON antiga.id = mapa.antiga_id

        SET
            nova.doi =
                COALESCE(
                    NULLIF(nova.doi, ''),
                    antiga.doi
                ),

            nova.issn =
                COALESCE(
                    NULLIF(nova.issn, ''),
                    antiga.issn
                ),

            nova.periodico_id =
                COALESCE(
                    nova.periodico_id,
                    antiga.periodico_id
                ),

            nova.instituicao_id =
                COALESCE(
                    antiga.instituicao_id,
                    nova.instituicao_id
                ),

            nova.internacional =
                GREATEST(
                    nova.internacional,
                    antiga.internacional
                ),

            nova.coautoria_internacional =
                GREATEST(
                    nova.coautoria_internacional,
                    antiga.coautoria_internacional
                )
    ");


    // ---------------------------------------------
    // DOCENTES / DISCENTES / LINHAS / AUTORES
    // ---------------------------------------------

    $pdo->exec("
        INSERT IGNORE INTO producoes_docentes
        (
            producao_id,
            docente_id,
            ordem_autoria
        )

        SELECT
            mapa.nova_id,
            pd.docente_id,
            pd.ordem_autoria

        FROM tmp_mapa_producoes_ppga mapa

        INNER JOIN producoes_docentes pd
            ON pd.producao_id = mapa.antiga_id
    ");


    $pdo->exec("
        INSERT IGNORE INTO producoes_discentes
        (
            producao_id,
            discente_id,
            ordem_autoria
        )

        SELECT
            mapa.nova_id,
            pd.discente_id,
            pd.ordem_autoria

        FROM tmp_mapa_producoes_ppga mapa

        INNER JOIN producoes_discentes pd
            ON pd.producao_id = mapa.antiga_id
    ");


    $pdo->exec("
        INSERT IGNORE INTO producoes_linhas
        (
            producao_id,
            linha_id
        )

        SELECT
            mapa.nova_id,
            pl.linha_id

        FROM tmp_mapa_producoes_ppga mapa

        INNER JOIN producoes_linhas pl
            ON pl.producao_id = mapa.antiga_id
    ");


    $pdo->exec("
        INSERT IGNORE INTO producoes_autores_externos
        (
            producao_id,
            autor_externo_id,
            ordem_autoria
        )

        SELECT
            mapa.nova_id,
            pae.autor_externo_id,
            pae.ordem_autoria

        FROM tmp_mapa_producoes_ppga mapa

        INNER JOIN producoes_autores_externos pae
            ON pae.producao_id = mapa.antiga_id
    ");


    // ---------------------------------------------
    // AVALIAÇÕES CAPES
    // ---------------------------------------------

    $pdo->exec("
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

        SELECT
            mapa.nova_id,
            ap.quadrienio_id,
            ap.classificacao_id,
            ap.pontuacao_interna,
            ap.status,
            ap.observacao,
            ap.data_avaliacao

        FROM tmp_mapa_producoes_ppga mapa

        INNER JOIN avaliacoes_producao ap
            ON ap.producao_id = mapa.antiga_id

        ON DUPLICATE KEY UPDATE
            classificacao_id =
                VALUES(classificacao_id),

            pontuacao_interna =
                VALUES(pontuacao_interna),

            status =
                VALUES(status),

            observacao =
                VALUES(observacao),

            data_avaliacao =
                VALUES(data_avaliacao)
    ");


    // ---------------------------------------------
    // MAPA: PROJETO ANTIGO -> PROJETO NOVO
    // ---------------------------------------------

    $pdo->exec(
        "
            DROP TEMPORARY TABLE IF EXISTS
            tmp_mapa_projetos_ppga
        "
    );


    $pdo->exec(
        "
            CREATE TEMPORARY TABLE
            tmp_mapa_projetos_ppga
            (
                antigo_id INT NOT NULL PRIMARY KEY,
                novo_id INT NOT NULL,
                KEY idx_tmp_mapa_projeto_novo (novo_id)
            )
        "
    );


    $stmtMapaProjetos =
        $pdo->prepare("
            INSERT INTO tmp_mapa_projetos_ppga
            (
                antigo_id,
                novo_id
            )

            SELECT
                antigo.id,
                MIN(novo.id)

            FROM projetos antigo

            INNER JOIN projetos novo
                ON novo.curriculo_lattes_id
                    =
                    :curriculo_atual_id

                AND LOWER(TRIM(antigo.nome))
                    =
                    LOWER(TRIM(novo.nome))

            WHERE antigo.curriculo_lattes_id
                =
                :curriculo_anterior_id

            GROUP BY antigo.id
        ");


    $stmtMapaProjetos->execute([

        ':curriculo_atual_id' =>
            $curriculoAtualId,

        ':curriculo_anterior_id' =>
            $curriculoAnteriorId

    ]);


    // Preserva metadados complementares do projeto.

    $pdo->exec("
        UPDATE projetos novo

        INNER JOIN tmp_mapa_projetos_ppga mapa
            ON mapa.novo_id = novo.id

        INNER JOIN projetos antigo
            ON antigo.id = mapa.antigo_id

        SET
            novo.financiado =
                GREATEST(
                    novo.financiado,
                    antigo.financiado
                ),

            novo.internacional =
                GREATEST(
                    novo.internacional,
                    antigo.internacional
                ),

            novo.observacoes =
                CASE
                    WHEN
                        novo.observacoes IS NULL
                        OR TRIM(novo.observacoes) = ''
                    THEN antigo.observacoes
                    ELSE novo.observacoes
                END
    ");


    $pdo->exec("
        INSERT IGNORE INTO projetos_docentes
        (
            projeto_id,
            docente_id,
            funcao
        )

        SELECT
            mapa.novo_id,
            pd.docente_id,
            pd.funcao

        FROM tmp_mapa_projetos_ppga mapa

        INNER JOIN projetos_docentes pd
            ON pd.projeto_id = mapa.antigo_id
    ");


    $pdo->exec("
        INSERT IGNORE INTO projetos_agencias
        (
            projeto_id,
            agencia_id
        )

        SELECT
            mapa.novo_id,
            pa.agencia_id

        FROM tmp_mapa_projetos_ppga mapa

        INNER JOIN projetos_agencias pa
            ON pa.projeto_id = mapa.antigo_id
    ");


    // Se existir a tabela projetos_linhas, preserva os vínculos.
    if (tabelaExiste(
        $pdo,
        'projetos_linhas'
    )) {

        $pdo->exec("
            INSERT IGNORE INTO projetos_linhas
            (
                projeto_id,
                linha_id
            )

            SELECT
                mapa.novo_id,
                pl.linha_id

            FROM tmp_mapa_projetos_ppga mapa

            INNER JOIN projetos_linhas pl
                ON pl.projeto_id = mapa.antigo_id
        ");

    }


    // ---------------------------------------------
    // PRODUÇÃO X PROJETO
    // ---------------------------------------------
    //
    // Primeiro preserva projetos permanentes/manuais,
    // isto é, aqueles que não pertencem ao snapshot antigo.

    $stmtProjetosPermanentes =
        $pdo->prepare("
            INSERT IGNORE INTO producoes_projetos
            (
                producao_id,
                projeto_id
            )

            SELECT
                mapa.nova_id,
                pp.projeto_id

            FROM tmp_mapa_producoes_ppga mapa

            INNER JOIN producoes_projetos pp
                ON pp.producao_id = mapa.antiga_id

            INNER JOIN projetos pr
                ON pr.id = pp.projeto_id

            WHERE
                pr.curriculo_lattes_id IS NULL
                OR pr.curriculo_lattes_id
                    <>
                    :curriculo_anterior_id
        ");


    $stmtProjetosPermanentes->execute([

        ':curriculo_anterior_id' =>
            $curriculoAnteriorId

    ]);


    // Depois remapeia projetos importados da versão anterior
    // para o projeto equivalente da nova versão.

    $pdo->exec("
        INSERT IGNORE INTO producoes_projetos
        (
            producao_id,
            projeto_id
        )

        SELECT
            mapaProducao.nova_id,
            mapaProjeto.novo_id

        FROM producoes_projetos pp

        INNER JOIN tmp_mapa_producoes_ppga mapaProducao
            ON mapaProducao.antiga_id = pp.producao_id

        INNER JOIN tmp_mapa_projetos_ppga mapaProjeto
            ON mapaProjeto.antigo_id = pp.projeto_id
    ");


    // ---------------------------------------------
    // ORIENTAÇÕES
    // ---------------------------------------------

    $pdo->exec(
        "
            DROP TEMPORARY TABLE IF EXISTS
            tmp_mapa_orientacoes_ppga
        "
    );


    $pdo->exec(
        "
            CREATE TEMPORARY TABLE
            tmp_mapa_orientacoes_ppga
            (
                antiga_id INT NOT NULL PRIMARY KEY,
                nova_id INT NOT NULL,
                KEY idx_tmp_mapa_orientacao_nova (nova_id)
            )
        "
    );


    $stmtMapaOrientacoes =
        $pdo->prepare("
            INSERT INTO tmp_mapa_orientacoes_ppga
            (
                antiga_id,
                nova_id
            )

            SELECT
                antiga.id,
                MIN(nova.id)

            FROM orientacoes antiga

            INNER JOIN orientacoes nova
                ON nova.curriculo_lattes_id
                    =
                    :curriculo_atual_id

                AND nova.discente_id
                    =
                    antiga.discente_id

                AND nova.nivel
                    =
                    antiga.nivel

                AND nova.ano
                    =
                    antiga.ano

                AND COALESCE(
                        TRIM(nova.tema),
                        ''
                    )
                    =
                    COALESCE(
                        TRIM(antiga.tema),
                        ''
                    )

            WHERE antiga.curriculo_lattes_id
                =
                :curriculo_anterior_id

            GROUP BY antiga.id
        ");


    $stmtMapaOrientacoes->execute([

        ':curriculo_atual_id' =>
            $curriculoAtualId,

        ':curriculo_anterior_id' =>
            $curriculoAnteriorId

    ]);


    $pdo->exec("
        UPDATE orientacoes nova

        INNER JOIN tmp_mapa_orientacoes_ppga mapa
            ON mapa.nova_id = nova.id

        INNER JOIN orientacoes antiga
            ON antiga.id = mapa.antiga_id

        SET
            nova.instituicao_id =
                COALESCE(
                    antiga.instituicao_id,
                    nova.instituicao_id
                )
    ");


    $pdo->exec("
        INSERT IGNORE INTO orientacoes_docentes
        (
            orientacao_id,
            docente_id,
            funcao
        )

        SELECT
            mapa.nova_id,
            od.docente_id,
            od.funcao

        FROM tmp_mapa_orientacoes_ppga mapa

        INNER JOIN orientacoes_docentes od
            ON od.orientacao_id = mapa.antiga_id
    ");

}


// ========================================================

function limparSnapshotsNormalizadosAnteriores(
    PDO $pdo,
    int $docenteId,
    int $curriculoAtualId
): void {

    // Apenas dados normalizados são removidos.
    // curriculos_lattes e registros_lattes NÃO são apagados.

    $stmt =
        $pdo->prepare("
            DELETE p

            FROM producoes p

            INNER JOIN curriculos_lattes cl
                ON cl.id = p.curriculo_lattes_id

            WHERE cl.docente_id = :docente_id
              AND cl.id <> :curriculo_atual_id
        ");


    $stmt->execute([

        ':docente_id' =>
            $docenteId,

        ':curriculo_atual_id' =>
            $curriculoAtualId

    ]);


    $stmt =
        $pdo->prepare("
            DELETE o

            FROM orientacoes o

            INNER JOIN curriculos_lattes cl
                ON cl.id = o.curriculo_lattes_id

            WHERE cl.docente_id = :docente_id
              AND cl.id <> :curriculo_atual_id
        ");


    $stmt->execute([

        ':docente_id' =>
            $docenteId,

        ':curriculo_atual_id' =>
            $curriculoAtualId

    ]);


    $stmt =
        $pdo->prepare("
            DELETE pr

            FROM projetos pr

            INNER JOIN curriculos_lattes cl
                ON cl.id = pr.curriculo_lattes_id

            WHERE cl.docente_id = :docente_id
              AND cl.id <> :curriculo_atual_id
        ");


    $stmt->execute([

        ':docente_id' =>
            $docenteId,

        ':curriculo_atual_id' =>
            $curriculoAtualId

    ]);


    $pdo->exec(
        "
            DROP TEMPORARY TABLE IF EXISTS
            tmp_mapa_producoes_ppga
        "
    );


    $pdo->exec(
        "
            DROP TEMPORARY TABLE IF EXISTS
            tmp_mapa_projetos_ppga
        "
    );


    $pdo->exec(
        "
            DROP TEMPORARY TABLE IF EXISTS
            tmp_mapa_orientacoes_ppga
        "
    );

}



// ========================================================
// FUNÇÕES DO XML
// ========================================================

function pegarAtributo(
    $elemento,
    $nome
) {


    if (!$elemento) {

        return null;

    }


    $atributos =
        $elemento->attributes();


    if (isset(
        $atributos[$nome]
    )) {


        $valor =
            trim(
                (string)
                $atributos[$nome]
            );


        return $valor !== ''

            ? $valor

            : null;

    }


    return null;

}


// ========================================================

function pegarPrimeiroFilho(
    $elemento,
    $nomeTag
) {


    $resultado =
        $elemento->xpath(

            './*[local-name()="'
            . $nomeTag
            . '"]'

        );


    return $resultado[0]
        ?? null;

}


// ========================================================

function pegarAutores(
    $elemento
) {


    $autores = [];


    $resultado =
        $elemento->xpath(

            './/*[local-name()="AUTORES"]'

        );


    foreach (
        $resultado
        as $autor
    ) {


        $nome =
            pegarAtributo(

                $autor,

                'NOME-COMPLETO-DO-AUTOR'

            );


        if ($nome !== null) {

            $autores[] =
                $nome;

        }

    }


    return $autores;

}


// ========================================================
// ID DA IMPORTAÇÃO
// ========================================================

$id =
    (int) (
        $_GET['id']
        ?? 0
    );


if ($id <= 0) {

    die(
        'Importação inválida.'
    );

}


// ========================================================
// BUSCA IMPORTAÇÃO
// ========================================================

$sql = "
    SELECT

        cl.*,

        d.nome AS docente_nome

    FROM curriculos_lattes cl

    INNER JOIN docentes d

        ON d.id = cl.docente_id

    WHERE cl.id = :id
";


$stmt =
    $pdo->prepare(
        $sql
    );


$stmt->execute([

    ':id' =>
        $id

]);


$curriculo =
    $stmt->fetch();


if (!$curriculo) {

    die(
        'Importação não encontrada.'
    );

}


// ========================================================
// CAMINHO DO ARQUIVO
// ========================================================

$caminhoArquivo =
    $curriculo[
        'caminho_arquivo'
    ];


$ehCaminhoWindows =
    preg_match(
        '/^[A-Za-z]:[\\\\\/]/',
        $caminhoArquivo
    ) === 1;


$ehCaminhoUnix =
    str_starts_with(
        $caminhoArquivo,
        '/'
    );


if (
    !$ehCaminhoWindows
    &&
    !$ehCaminhoUnix
) {

    $caminhoArquivo =

        $raizProjeto

        . DIRECTORY_SEPARATOR

        . str_replace(

            ['/', '\\'],

            DIRECTORY_SEPARATOR,

            ltrim(
                $caminhoArquivo,
                '/\\'
            )

        );

}


if (!file_exists(
    $caminhoArquivo
)) {


    atualizarStatus(

        $pdo,

        $id,

        'ERRO',

        'Arquivo do currículo não encontrado.'

    );


    die(
        'Arquivo do currículo não encontrado.'
    );

}


// ========================================================
// EXTENSÃO
// ========================================================

$extensao =
    strtolower(
        pathinfo(
            $caminhoArquivo,
            PATHINFO_EXTENSION
        )
    );


// ########################################################
// PDF
// ########################################################

if ($extensao === 'pdf') {

    try {

        // =====================================
        // STATUS: PROCESSANDO
        // =====================================

        atualizarStatus(
            $pdo,
            $id,
            'PROCESSANDO'
        );


        // =====================================
        // CAMINHO DO EXTRATOR PYTHON
        // =====================================

        $caminhoExtrator =
            $raizProjeto
            . DIRECTORY_SEPARATOR
            . 'python'
            . DIRECTORY_SEPARATOR
            . 'extrator_pdf.py';


        // =====================================
        // CONFERE PYTHON
        // =====================================

        if (!file_exists(
            $pythonExe
        )) {

            throw new Exception(
                'Executável do Python não encontrado.'
            );

        }


        // =====================================
        // CONFERE EXTRATOR
        // =====================================

        if (!file_exists(
            $caminhoExtrator
        )) {

            throw new Exception(
                'Arquivo extrator_pdf.py não encontrado.'
            );

        }


        // =====================================
        // MONTA O COMANDO
        // =====================================

        $comando =
            escapeshellarg(
                $pythonExe
            )
            . ' '
            . escapeshellarg(
                $caminhoExtrator
            )
            . ' '
            . escapeshellarg(
                $caminhoArquivo
            )
            . ' 2>&1';


        // =====================================
        // EXECUTA PYTHON
        // =====================================

        $saida =
            shell_exec(
                $comando
            );


        if (
            $saida === null
            ||
            trim(
                $saida
            ) === ''
        ) {

            throw new Exception(
                'O Python não retornou nenhuma saída.'
            );

        }


        // =====================================
        // CONVERTE JSON
        // =====================================

        $resultado =
            json_decode(
                trim(
                    $saida
                ),
                true
            );


        if (!is_array(
            $resultado
        )) {

            throw new Exception(
                'O Python retornou um JSON inválido. '
                . json_last_error_msg()
            );

        }


        // =====================================
        // CONFERE SE PYTHON TEVE SUCESSO
        // =====================================

        if (
            !isset(
                $resultado['sucesso']
            )
            ||
            $resultado['sucesso'] !== true
        ) {

            $erroPython =
                $resultado['erro']
                ?? 'Erro desconhecido no Python.';


            throw new Exception(
                $erroPython
            );

        }


        // =====================================
        // ARTIGOS ESTRUTURADOS
        // =====================================

        $artigosEstruturados =
            $resultado[
                'artigos_estruturados'
            ]
            ?? [];


        if (!is_array(
            $artigosEstruturados
        )) {

            throw new Exception(
                'O campo artigos_estruturados retornado pelo Python é inválido.'
            );

        }

        // =====================================
        // LIVROS ESTRUTURADOS
        // =====================================

        $livrosEstruturados =
            $resultado[
                'livros_estruturados'
            ]
            ?? [];


        if (!is_array(
            $livrosEstruturados
        )) {

            throw new Exception(
                'O campo livros_estruturados retornado pelo Python é inválido.'
            );
        }

        // =====================================
        // CAPÍTULOS ESTRUTURADOS
        // =====================================

        $capitulosEstruturados =
            $resultado[
                'capitulos_estruturados'
            ]
            ?? [];


        if (!is_array(
            $capitulosEstruturados
        )) {

            throw new Exception(
                'O campo capitulos_estruturados retornado pelo Python é inválido.'
            );
        }

        // =====================================
        // TRABALHOS EM ANAIS ESTRUTURADOS
        // =====================================

        $trabalhosAnaisEstruturados =
            $resultado[
                'trabalhos_anais_estruturados'
            ]
            ?? [];


        if (!is_array(
            $trabalhosAnaisEstruturados
        )) {

            throw new Exception(
                'O campo trabalhos_anais_estruturados retornado pelo Python é inválido.'
            );
        }

        // =====================================
        // RESUMOS EXPANDIDOS ESTRUTURADOS
        // =====================================

        $resumosExpandidosEstruturados =
            $resultado[
                'resumos_expandidos_estruturados'
            ]
            ?? [];


        if (!is_array(
            $resumosExpandidosEstruturados
        )) {

            throw new Exception(
                'O campo resumos_expandidos_estruturados retornado pelo Python é inválido.'
            );
        }

        // =====================================
        // RESUMOS EM ANAIS ESTRUTURADOS
        // =====================================

        $resumosAnaisEstruturados =
            $resultado[
                'resumos_anais_estruturados'
            ]
            ?? [];


        if (!is_array(
            $resumosAnaisEstruturados
        )) {

            throw new Exception(
                'O campo resumos_anais_estruturados retornado pelo Python é inválido.'
            );
        }

        // =====================================
        // RESUMOS EM ANAIS ESTRUTURADOS
        // =====================================

        $resumosAnaisEstruturados =
            $resultado[
                'resumos_anais_estruturados'
            ]
            ?? [];


        if (!is_array(
            $resumosAnaisEstruturados
        )) {

            throw new Exception(
                'O campo resumos_anais_estruturados retornado pelo Python é inválido.'
            );
        }

        // =====================================
        // PRODUÇÃO TÉCNICA ESTRUTURADA
        // =====================================

        $producaoTecnicaEstruturada =
            $resultado[
                'producao_tecnica_estruturada'
            ]
            ?? [];


        if (!is_array(
            $producaoTecnicaEstruturada
        )) {

            throw new Exception(
                'O campo producao_tecnica_estruturada retornado pelo Python é inválido.'
            );
        }

        // =====================================
        // PROJETOS DE PESQUISA ESTRUTURADOS
        // =====================================

        $projetosPesquisaEstruturados =
            $resultado[
                'projetos_pesquisa_estruturados'
            ]
            ?? [];


        if (!is_array(
            $projetosPesquisaEstruturados
        )) {

            throw new Exception(
                'O campo projetos_pesquisa_estruturados retornado pelo Python é inválido.'
            );
        }


        // =====================================
        // ORIENTAÇÕES ESTRUTURADAS
        // =====================================

        $chavesOrientacoes = [

            'orientacoes_andamento_dissertacao_estruturadas',
            'orientacoes_andamento_tese_estruturadas',
            'orientacoes_andamento_pos_doutorado_estruturadas',
            'orientacoes_andamento_iniciacao_estruturadas',

            'orientacoes_concluidas_dissertacao_estruturadas',
            'orientacoes_concluidas_tese_estruturadas',
            'orientacoes_concluidas_tcc_estruturadas',
            'orientacoes_concluidas_iniciacao_estruturadas'

        ];


        $orientacoesEstruturadas = [];


        foreach (
            $chavesOrientacoes
            as $chaveOrientacao
        ) {

            $listaOrientacoes =
                $resultado[
                    $chaveOrientacao
                ]
                ?? [];


            if (!is_array(
                $listaOrientacoes
            )) {

                throw new Exception(
                    'O campo '
                    . $chaveOrientacao
                    . ' retornado pelo Python é inválido.'
                );
            }


            foreach (
                $listaOrientacoes
                as $orientacao
            ) {

                if (is_array(
                    $orientacao
                )) {

                    $orientacoesEstruturadas[] =
                        $orientacao;
                }
            }
        }


        // Produto tecnológico NÃO entra em orientacoes.
        // O currículo o apresenta após o bloco de orientações,
        // mas ele pertence a outra parte da produção do Lattes.

        if (!colunaExiste(
            $pdo,
            'orientacoes',
            'curriculo_lattes_id'
        )) {

            throw new Exception(
                'A tabela orientacoes ainda não possui a coluna curriculo_lattes_id. '
                . 'Execute a migration_orientacoes_curriculo.sql antes de processar o PDF.'
            );
        }


        // =====================================
        // DESCOBRE O TIPO ARTIGO
        // =====================================

        $tipoArtigoId =
            encontrarTipoArtigoId(
                $pdo
            );

        // =====================================
        // DESCOBRE O TIPO LIVRO
        // =====================================

        $tipoLivroId =
            encontrarTipoLivroId(
                $pdo
            );   

        // =====================================
        // DESCOBRE O TIPO CAPÍTULO
        // =====================================

        $tipoCapituloId =
            encontrarTipoCapituloId(
                $pdo
            );

            // =====================================
            // DESCOBRE O TIPO TRABALHO EM ANAIS
            // =====================================

            $tipoTrabalhoAnaisId =
                encontrarTipoTrabalhoAnaisId(
                    $pdo
                );

            // =====================================
            // DESCOBRE O TIPO RESUMO EXPANDIDO
            // =====================================

            $tipoResumoExpandidoId =
                encontrarTipoResumoExpandidoId(
                    $pdo
                );

            // =====================================
            // DESCOBRE O TIPO RESUMO EM CONGRESSO
            // =====================================

            $tipoResumoCongressoId =
                encontrarTipoResumoCongressoId(
                    $pdo
                );

            // =====================================
            // DESCOBRE O TIPO ASSESSORIA E CONSULTORIA
            // =====================================

            $tipoAssessoriaConsultoriaId =
                encontrarTipoAssessoriaConsultoriaId(
                    $pdo
            );
            
       

        // =====================================
        // DADOS DO DOCENTE
        // =====================================

        $docenteId =
            (int)
            $curriculo['docente_id'];


        $docenteNome =
            (string)
            $curriculo['docente_nome'];


        // =====================================
        // INICIA TRANSAÇÃO
        // =====================================

        $pdo->beginTransaction();


        // =====================================
        // LIMPA SOMENTE ESTA IMPORTAÇÃO
        // =====================================

        limparReprocessamentoPdf(
            $pdo,
            $id
        );


        // =====================================
        // PREPARA registros_lattes
        // =====================================

        $stmtRegistro =
            $pdo->prepare("

                INSERT INTO registros_lattes
                (
                    curriculo_lattes_id,
                    tipo_registro,
                    chave_registro,
                    titulo,
                    ano,
                    dados_brutos
                )

                VALUES
                (
                    :curriculo_lattes_id,
                    :tipo_registro,
                    :chave_registro,
                    :titulo,
                    :ano,
                    :dados_brutos
                )

            ");


        // =====================================
        // PREPARA producoes
        // =====================================

        $stmtProducao =
            $pdo->prepare("

                INSERT INTO producoes
                (
                    titulo,
                    doi,
                    issn,
                    periodico_id,
                    ano,
                    tipo_producao_id,
                    instituicao_id,
                    internacional,
                    coautoria_internacional,
                    status,
                    origem,
                    curriculo_lattes_id
                )

                VALUES
                (
                    :titulo,
                    :doi,
                    :issn,
                    :periodico_id,
                    :ano,
                    :tipo_producao_id,
                    NULL,
                    0,
                    0,
                    :status,
                    :origem,
                    :curriculo_lattes_id
                )

            ");


        // =====================================
        // PREPARA producoes_docentes
        // =====================================

        $stmtProducaoDocente =
            $pdo->prepare("

                INSERT INTO producoes_docentes
                (
                    producao_id,
                    docente_id,
                    ordem_autoria
                )

                VALUES
                (
                    :producao_id,
                    :docente_id,
                    :ordem_autoria
                )

            ");

            // =====================================
// PREPARE - PROJETOS
// =====================================

$stmtProjeto =
    $pdo->prepare("
        INSERT INTO projetos (
            nome,
            data_inicio,
            data_fim,
            situacao,
            financiado,
            internacional,
            observacoes,
            curriculo_lattes_id
        )
        VALUES (
            :nome,
            :data_inicio,
            :data_fim,
            :situacao,
            :financiado,
            :internacional,
            :observacoes,
            :curriculo_lattes_id
        )
    ");


// =====================================
// PREPARE - PROJETO X DOCENTE
// =====================================

$stmtProjetoDocente =
    $pdo->prepare("
        INSERT INTO projetos_docentes (
            projeto_id,
            docente_id,
            funcao
        )
        VALUES (
            :projeto_id,
            :docente_id,
            :funcao
        )
    ");


// =====================================
// PREPARE - PROJETO X AGÊNCIA
// =====================================

$stmtProjetoAgencia =
    $pdo->prepare("
        INSERT INTO projetos_agencias (
            projeto_id,
            agencia_id
        )
        VALUES (
            :projeto_id,
            :agencia_id
        )
    ");


// =====================================
// PREPARE - ORIENTAÇÕES
// =====================================

$stmtOrientacao =
    $pdo->prepare("
        INSERT INTO orientacoes (
            discente_id,
            instituicao_id,
            nivel,
            situacao,
            ano,
            tema,
            curriculo_lattes_id
        )
        VALUES (
            :discente_id,
            :instituicao_id,
            :nivel,
            :situacao,
            :ano,
            :tema,
            :curriculo_lattes_id
        )
    ");


// =====================================
// PREPARE - ORIENTAÇÃO X DOCENTE
// =====================================

$stmtOrientacaoDocente =
    $pdo->prepare("
        INSERT INTO orientacoes_docentes (
            orientacao_id,
            docente_id,
            funcao
        )
        VALUES (
            :orientacao_id,
            :docente_id,
            :funcao
        )
    ");


        // =====================================
        // CONTADORES
        // =====================================

        $quantidadeDetectada =
            count(
                $artigosEstruturados
            );


        $quantidadeRegistros =
            0;


        $quantidadeProducoes =
            0;


        $quantidadeIgnoradaSemTitulo =
            0;


        $quantidadeIgnoradaSemAno =
            0;


        $quantidadeDuplicada =
            0;


        $quantidadeOrientacoes =
            0;


        $quantidadeOrientacoesDetectadas =
            count(
                $orientacoesEstruturadas
            );


        $chavesVistas =
            [];


        // =====================================
        // PERCORRE TODOS OS ARTIGOS
        // =====================================

        foreach (
            $artigosEstruturados
            as $artigo
        ) {

            // =================================
            // TÍTULO
            // =================================

            $titulo =
                trim(
                    $artigo['titulo']
                    ?? ''
                );


            if ($titulo === '') {

                $quantidadeIgnoradaSemTitulo++;

                continue;

            }


            // =================================
            // TEXTO ORIGINAL
            // =================================

            $textoOriginal =
                trim(
                    $artigo[
                        'texto_original'
                    ]
                    ?? ''
                );


            // =================================
            // ANO
            // =================================

            $ano =
                $artigo['ano']
                ?? null;


            if (
                $ano !== null
                &&
                $ano !== ''
            ) {

                $ano =
                    (int)
                    $ano;

            } else {

                $ano =
                    null;

            }


            // Fallback caso algum artigo tenha
            // escapado do parser principal.

            if (
                $ano === null
                &&
                $textoOriginal !== ''
            ) {

                preg_match_all(

                    '/\b(?:19|20)\d{2}\b/',

                    $textoOriginal,

                    $anosEncontrados

                );


                if (
                    !empty(
                        $anosEncontrados[0]
                    )
                ) {

                    $ultimoAno =
                        end(
                            $anosEncontrados[0]
                        );


                    $ano =
                        (int)
                        $ultimoAno;

                }

            }


            // =================================
            // DOI
            // =================================

            $doi =
                trim(
                    $artigo['doi']
                    ?? ''
                );


            if ($doi === '') {

                $doi =
                    null;

            }


            // =================================
            // ISSN
            // =================================

            $issn =
                trim(
                    $artigo['issn']
                    ?? ''
                );


            if ($issn === '') {

                $issn =
                    null;

            }


            // =================================
            // PERIÓDICO
            // =================================

            $periodicoNome =
                trim(
                    $artigo['periodico']
                    ?? ''
                );


            // =================================
            // AUTORES
            // =================================

            $autores =
                $artigo['autores']
                ?? [];


            if (!is_array(
                $autores
            )) {

                $autores =
                    [];

            }


            // =================================
            // CHAVE DO REGISTRO
            // =================================

            if ($doi !== null) {

                $baseChave =
                    'DOI|'
                    . textoMinusculo(
                        $doi
                    );

            } else {

                $baseChave =

                    textoMinusculo(
                        $titulo
                    )

                    . '|'

                    . (
                        $ano
                        ?? ''
                    )

                    . '|'

                    . textoMinusculo(
                        $periodicoNome
                    );

            }


            $chaveRegistro =
                hash(
                    'sha256',
                    $baseChave
                );


            // Evita duplicata dentro da mesma
            // versão do currículo.

            if (
                isset(
                    $chavesVistas[
                        $chaveRegistro
                    ]
                )
            ) {

                $quantidadeDuplicada++;

                continue;

            }


            $chavesVistas[
                $chaveRegistro
            ] = true;


            // =================================
            // JSON COMPLETO
            // =================================

            $dadosBrutos =
                json_encode(

                    $artigo,

                    JSON_UNESCAPED_UNICODE
                    |
                    JSON_UNESCAPED_SLASHES

                );


            if ($dadosBrutos === false) {

                throw new Exception(
                    'Erro ao gerar o JSON bruto do artigo.'
                );

            }


            // =================================
            // REGISTRO LATTES
            // =================================

            $stmtRegistro->execute([

                ':curriculo_lattes_id' =>
                    $id,

                ':tipo_registro' =>
                    'ARTIGO',

                ':chave_registro' =>
                    $chaveRegistro,

                ':titulo' =>
                    $titulo,

                ':ano' =>
                    $ano,

                ':dados_brutos' =>
                    $dadosBrutos

            ]);


            $quantidadeRegistros++;


            // producoes.ano é NOT NULL.
            // Mantemos o registro bruto para auditoria,
            // mas não criamos uma produção sem ano.

            if ($ano === null) {

                $quantidadeIgnoradaSemAno++;

                continue;

            }


            // =================================
            // PERIÓDICO
            // =================================

            $periodicoId =
                buscarOuCriarPeriodico(

                    $pdo,

                    $periodicoNome,

                    $issn

                );


            // =================================
            // PRODUÇÃO
            // =================================

            $stmtProducao->execute([

                ':titulo' =>
                    $titulo,

                ':doi' =>
                    $doi,

                ':issn' =>
                    $issn,

                ':periodico_id' =>
                    $periodicoId,

                ':ano' =>
                    $ano,

                ':tipo_producao_id' =>
                    $tipoArtigoId,

                ':status' =>
                    'IMPORTADO',

                ':origem' =>
                    'LATTES_PDF',

                ':curriculo_lattes_id' =>
                    $id

            ]);


            $producaoId =
                (int)
                $pdo->lastInsertId();


            // =================================
            // ORDEM DE AUTORIA
            // =================================

            $ordemAutoria =
                descobrirOrdemAutoria(

                    $docenteNome,

                    $autores

                );


            // =================================
            // PRODUÇÃO X DOCENTE
            // =================================

            $stmtProducaoDocente->execute([

                ':producao_id' =>
                    $producaoId,

                ':docente_id' =>
                    $docenteId,

                ':ordem_autoria' =>
                    $ordemAutoria

            ]);


            $quantidadeProducoes++;

        }

                // =====================================
        // PERCORRE TODOS OS LIVROS
        // =====================================

        foreach (
            $livrosEstruturados
            as $livro
        ) {

            // =================================
            // TÍTULO
            // =================================

            $titulo =
                trim(
                    $livro['titulo']
                    ?? ''
                );


            if ($titulo === '') {

                $quantidadeIgnoradaSemTitulo++;

                continue;
            }


            // =================================
            // ANO
            // =================================

            $ano =
                $livro['ano']
                ?? null;


            if (
                $ano !== null
                &&
                $ano !== ''
            ) {

                $ano =
                    (int) $ano;

            } else {

                $ano =
                    null;
            }


            // =================================
            // AUTORES
            // =================================

            $autores =
                $livro['autores']
                ?? [];


            if (!is_array(
                $autores
            )) {

                $autores =
                    [];
            }


            // =================================
            // CHAVE ÚNICA
            // =================================

            $baseChave =
                'LIVRO|'
                . textoMinusculo(
                    $titulo
                )
                . '|'
                . (
                    $ano
                    ?? ''
                );


            $chaveRegistro =
                hash(
                    'sha256',
                    $baseChave
                );


            // Evita duplicata dentro
            // da mesma importação

            if (
                isset(
                    $chavesVistas[
                        $chaveRegistro
                    ]
                )
            ) {

                $quantidadeDuplicada++;

                continue;
            }


            $chavesVistas[
                $chaveRegistro
            ] = true;


            // =================================
            // JSON COMPLETO
            // =================================

            $dadosBrutos =
                json_encode(

                    $livro,

                    JSON_UNESCAPED_UNICODE
                    |
                    JSON_UNESCAPED_SLASHES

                );


            if ($dadosBrutos === false) {

                throw new Exception(
                    'Erro ao gerar o JSON bruto do livro.'
                );
            }


            // =================================
            // REGISTRO LATTES
            // =================================

            $stmtRegistro->execute([

                ':curriculo_lattes_id' =>
                    $id,

                ':tipo_registro' =>
                    'LIVRO',

                ':chave_registro' =>
                    $chaveRegistro,

                ':titulo' =>
                    $titulo,

                ':ano' =>
                    $ano,

                ':dados_brutos' =>
                    $dadosBrutos

            ]);


            $quantidadeRegistros++;


            // producoes.ano é NOT NULL

            if ($ano === null) {

                $quantidadeIgnoradaSemAno++;

                continue;
            }


            // =================================
            // PRODUÇÃO
            // =================================

            $stmtProducao->execute([

                ':titulo' =>
                    $titulo,

                ':doi' =>
                    null,

                ':issn' =>
                    null,

                ':periodico_id' =>
                    null,

                ':ano' =>
                    $ano,

                ':tipo_producao_id' =>
                    $tipoLivroId,

                ':status' =>
                    'IMPORTADO',

                ':origem' =>
                    'LATTES_PDF',

                ':curriculo_lattes_id' =>
                    $id

            ]);


            $producaoId =
                (int)
                $pdo->lastInsertId();


            // =================================
            // ORDEM DE AUTORIA
            // =================================

            $ordemAutoria =
                descobrirOrdemAutoria(

                    $docenteNome,

                    $autores

                );


            // =================================
            // PRODUÇÃO X DOCENTE
            // =================================

            $stmtProducaoDocente->execute([

                ':producao_id' =>
                    $producaoId,

                ':docente_id' =>
                    $docenteId,

                ':ordem_autoria' =>
                    $ordemAutoria

            ]);


            $quantidadeProducoes++;
        }

                // =====================================
        // PERCORRE TODOS OS CAPÍTULOS
        // =====================================

        foreach (
            $capitulosEstruturados
            as $capitulo
        ) {

            // =================================
            // TÍTULO
            // =================================

            $titulo =
                trim(
                    $capitulo['titulo']
                    ?? ''
                );


            if ($titulo === '') {

                $quantidadeIgnoradaSemTitulo++;

                continue;
            }


            // =================================
            // ANO
            // =================================

            $ano =
                $capitulo['ano']
                ?? null;


            if (
                $ano !== null
                &&
                $ano !== ''
            ) {

                $ano =
                    (int) $ano;

            } else {

                $ano =
                    null;
            }


            // =================================
            // AUTORES
            // =================================

            $autores =
                $capitulo['autores']
                ?? [];


            if (!is_array(
                $autores
            )) {

                $autores =
                    [];
            }


            // =================================
            // CHAVE ÚNICA
            // =================================

            $baseChave =
                'CAPITULO|'
                . textoMinusculo(
                    $titulo
                )
                . '|'
                . (
                    $ano
                    ?? ''
                );


            $chaveRegistro =
                hash(
                    'sha256',
                    $baseChave
                );


            if (
                isset(
                    $chavesVistas[
                        $chaveRegistro
                    ]
                )
            ) {

                $quantidadeDuplicada++;

                continue;
            }


            $chavesVistas[
                $chaveRegistro
            ] = true;


            // =================================
            // JSON COMPLETO
            // =================================

            $dadosBrutos =
                json_encode(

                    $capitulo,

                    JSON_UNESCAPED_UNICODE
                    |
                    JSON_UNESCAPED_SLASHES

                );


            if ($dadosBrutos === false) {

                throw new Exception(
                    'Erro ao gerar o JSON bruto do capítulo.'
                );
            }


            // =================================
            // REGISTRO LATTES
            // =================================

            $stmtRegistro->execute([

                ':curriculo_lattes_id' =>
                    $id,

                ':tipo_registro' =>
                    'CAPITULO',

                ':chave_registro' =>
                    $chaveRegistro,

                ':titulo' =>
                    $titulo,

                ':ano' =>
                    $ano,

                ':dados_brutos' =>
                    $dadosBrutos

            ]);


            $quantidadeRegistros++;


            // producoes.ano é NOT NULL

            if ($ano === null) {

                $quantidadeIgnoradaSemAno++;

                continue;
            }


            // =================================
            // PRODUÇÃO
            // =================================

            $stmtProducao->execute([

                ':titulo' =>
                    $titulo,

                ':doi' =>
                    null,

                ':issn' =>
                    null,

                ':periodico_id' =>
                    null,

                ':ano' =>
                    $ano,

                ':tipo_producao_id' =>
                    $tipoCapituloId,

                ':status' =>
                    'IMPORTADO',

                ':origem' =>
                    'LATTES_PDF',

                ':curriculo_lattes_id' =>
                    $id

            ]);


            $producaoId =
                (int)
                $pdo->lastInsertId();


            // =================================
            // ORDEM DE AUTORIA
            // =================================

            $ordemAutoria =
                descobrirOrdemAutoria(

                    $docenteNome,

                    $autores

                );


            // =================================
            // PRODUÇÃO X DOCENTE
            // =================================

            $stmtProducaoDocente->execute([

                ':producao_id' =>
                    $producaoId,

                ':docente_id' =>
                    $docenteId,

                ':ordem_autoria' =>
                    $ordemAutoria

            ]);


            $quantidadeProducoes++;
        }

                // =====================================
        // PERCORRE TRABALHOS EM ANAIS
        // =====================================

        foreach (
            $trabalhosAnaisEstruturados
            as $trabalho
        ) {

            // =================================
            // TÍTULO
            // =================================

            $titulo =
                trim(
                    $trabalho['titulo']
                    ?? ''
                );


            if ($titulo === '') {

                $quantidadeIgnoradaSemTitulo++;

                continue;
            }


            // =================================
            // ANO
            // =================================

            $ano =
                $trabalho['ano']
                ?? null;


            if (
                $ano !== null
                &&
                $ano !== ''
            ) {

                $ano =
                    (int) $ano;

            } else {

                $ano =
                    null;
            }


            // =================================
            // AUTORES
            // =================================

            $autores =
                $trabalho['autores']
                ?? [];


            if (!is_array(
                $autores
            )) {

                $autores =
                    [];
            }


            // =================================
            // CHAVE ÚNICA
            // =================================

            $baseChave =
                'ANAIS|'
                . textoMinusculo(
                    $titulo
                )
                . '|'
                . (
                    $ano
                    ?? ''
                );


            $chaveRegistro =
                hash(
                    'sha256',
                    $baseChave
                );


            if (
                isset(
                    $chavesVistas[
                        $chaveRegistro
                    ]
                )
            ) {

                $quantidadeDuplicada++;

                continue;
            }


            $chavesVistas[
                $chaveRegistro
            ] = true;


            // =================================
            // JSON COMPLETO
            // =================================

            $dadosBrutos =
                json_encode(

                    $trabalho,

                    JSON_UNESCAPED_UNICODE
                    |
                    JSON_UNESCAPED_SLASHES

                );


            if ($dadosBrutos === false) {

                throw new Exception(
                    'Erro ao gerar o JSON bruto do trabalho em anais.'
                );
            }


            // =================================
            // REGISTRO LATTES
            // =================================

            $stmtRegistro->execute([

                ':curriculo_lattes_id' =>
                    $id,

                ':tipo_registro' =>
                    'TRABALHO_ANAIS',

                ':chave_registro' =>
                    $chaveRegistro,

                ':titulo' =>
                    $titulo,

                ':ano' =>
                    $ano,

                ':dados_brutos' =>
                    $dadosBrutos

            ]);


            $quantidadeRegistros++;


            // producoes.ano é NOT NULL

            if ($ano === null) {

                $quantidadeIgnoradaSemAno++;

                continue;
            }


            // =================================
            // PRODUÇÃO
            // =================================

            $stmtProducao->execute([

                ':titulo' =>
                    $titulo,

                ':doi' =>
                    null,

                ':issn' =>
                    null,

                ':periodico_id' =>
                    null,

                ':ano' =>
                    $ano,

                ':tipo_producao_id' =>
                    $tipoTrabalhoAnaisId,

                ':status' =>
                    'IMPORTADO',

                ':origem' =>
                    'LATTES_PDF',

                ':curriculo_lattes_id' =>
                    $id

            ]);


            $producaoId =
                (int)
                $pdo->lastInsertId();


            // =================================
            // ORDEM DE AUTORIA
            // =================================

            $ordemAutoria =
                descobrirOrdemAutoria(

                    $docenteNome,

                    $autores

                );


            // =================================
            // PRODUÇÃO X DOCENTE
            // =================================

            $stmtProducaoDocente->execute([

                ':producao_id' =>
                    $producaoId,

                ':docente_id' =>
                    $docenteId,

                ':ordem_autoria' =>
                    $ordemAutoria

            ]);


            $quantidadeProducoes++;
        }

                // =====================================
        // PERCORRE RESUMOS EXPANDIDOS
        // =====================================

        foreach (
            $resumosExpandidosEstruturados
            as $resumoExpandido
        ) {

            // =================================
            // TÍTULO
            // =================================

            $titulo =
                trim(
                    $resumoExpandido['titulo']
                    ?? ''
                );


            if ($titulo === '') {

                $quantidadeIgnoradaSemTitulo++;

                continue;
            }


            // =================================
            // ANO
            // =================================

            $ano =
                $resumoExpandido['ano']
                ?? null;


            if (
                $ano !== null
                &&
                $ano !== ''
            ) {

                $ano =
                    (int) $ano;

            } else {

                $ano =
                    null;
            }


            // =================================
            // AUTORES
            // =================================

            $autores =
                $resumoExpandido['autores']
                ?? [];


            if (!is_array(
                $autores
            )) {

                $autores =
                    [];
            }


            // =================================
            // CHAVE ÚNICA
            // =================================

            $baseChave =
                'RESUMO_EXPANDIDO|'
                . textoMinusculo(
                    $titulo
                )
                . '|'
                . (
                    $ano
                    ?? ''
                );


            $chaveRegistro =
                hash(
                    'sha256',
                    $baseChave
                );


            if (
                isset(
                    $chavesVistas[
                        $chaveRegistro
                    ]
                )
            ) {

                $quantidadeDuplicada++;

                continue;
            }


            $chavesVistas[
                $chaveRegistro
            ] = true;


            // =================================
            // JSON COMPLETO
            // =================================

            $dadosBrutos =
                json_encode(

                    $resumoExpandido,

                    JSON_UNESCAPED_UNICODE
                    |
                    JSON_UNESCAPED_SLASHES

                );


            if ($dadosBrutos === false) {

                throw new Exception(
                    'Erro ao gerar o JSON bruto do resumo expandido.'
                );
            }


            // =================================
            // REGISTRO LATTES
            // =================================

            $stmtRegistro->execute([

                ':curriculo_lattes_id' =>
                    $id,

                ':tipo_registro' =>
                    'RESUMO_EXPANDIDO',

                ':chave_registro' =>
                    $chaveRegistro,

                ':titulo' =>
                    $titulo,

                ':ano' =>
                    $ano,

                ':dados_brutos' =>
                    $dadosBrutos

            ]);


            $quantidadeRegistros++;


            // producoes.ano é NOT NULL

            if ($ano === null) {

                $quantidadeIgnoradaSemAno++;

                continue;
            }


            // =================================
            // PRODUÇÃO
            // =================================

            $stmtProducao->execute([

                ':titulo' =>
                    $titulo,

                ':doi' =>
                    null,

                ':issn' =>
                    null,

                ':periodico_id' =>
                    null,

                ':ano' =>
                    $ano,

                ':tipo_producao_id' =>
                    $tipoResumoExpandidoId,

                ':status' =>
                    'IMPORTADO',

                ':origem' =>
                    'LATTES_PDF',

                ':curriculo_lattes_id' =>
                    $id

            ]);


            $producaoId =
                (int)
                $pdo->lastInsertId();


            // =================================
            // ORDEM DE AUTORIA
            // =================================

            $ordemAutoria =
                descobrirOrdemAutoria(

                    $docenteNome,

                    $autores

                );


            // =================================
            // PRODUÇÃO X DOCENTE
            // =================================

            $stmtProducaoDocente->execute([

                ':producao_id' =>
                    $producaoId,

                ':docente_id' =>
                    $docenteId,

                ':ordem_autoria' =>
                    $ordemAutoria

            ]);


            $quantidadeProducoes++;
        }

        // =====================================
        // PERCORRE RESUMOS EM CONGRESSO
        // =====================================

        foreach (
            $resumosAnaisEstruturados
            as $resumo
        ) {


    // =================================
    // TÍTULO
    // =================================

    $titulo =
        trim(
            $resumo['titulo']
            ?? ''
        );


    if ($titulo === '') {

        $quantidadeIgnoradaSemTitulo++;

        continue;
    }


    // =================================
    // ANO
    // =================================

    $ano =
        $resumo['ano']
        ?? null;


    if (
        $ano !== null
        &&
        $ano !== ''
    ) {

        $ano =
            (int) $ano;

    } else {

        $ano =
            null;
    }


    // =================================
    // AUTORES
    // =================================

    $autores =
        $resumo['autores']
        ?? [];


    if (!is_array(
        $autores
    )) {

        $autores =
            [];
    }


    // =================================
    // CHAVE ÚNICA
    // =================================

    $baseChave =
        'RESUMO_CONGRESSO|'
        . textoMinusculo(
            $titulo
        )
        . '|'
        . (
            $ano
            ?? ''
        );


    $chaveRegistro =
        hash(
            'sha256',
            $baseChave
        );


    if (
        isset(
            $chavesVistas[
                $chaveRegistro
            ]
        )
    ) {

        $quantidadeDuplicada++;

        continue;
    }


    $chavesVistas[
        $chaveRegistro
    ] = true;


    // =================================
    // JSON COMPLETO
    // =================================

    $dadosBrutos =
        json_encode(

            $resumo,

            JSON_UNESCAPED_UNICODE
            |
            JSON_UNESCAPED_SLASHES

        );


    if ($dadosBrutos === false) {

        throw new Exception(
            'Erro ao gerar o JSON bruto do resumo em congresso.'
        );
    }


    // =================================
    // REGISTRO LATTES
    // =================================

    $stmtRegistro->execute([

        ':curriculo_lattes_id' =>
            $id,

        ':tipo_registro' =>
            'RESUMO_CONGRESSO',

        ':chave_registro' =>
            $chaveRegistro,

        ':titulo' =>
            $titulo,

        ':ano' =>
            $ano,

        ':dados_brutos' =>
            $dadosBrutos

    ]);


    $quantidadeRegistros++;


    // producoes.ano é NOT NULL

    if ($ano === null) {

        $quantidadeIgnoradaSemAno++;

        continue;
    }


    // =================================
    // PRODUÇÃO
    // =================================

    $stmtProducao->execute([

        ':titulo' =>
            $titulo,

        ':doi' =>
            null,

        ':issn' =>
            null,

        ':periodico_id' =>
            null,

        ':ano' =>
            $ano,

        ':tipo_producao_id' =>
            $tipoResumoCongressoId,

        ':status' =>
            'IMPORTADO',

        ':origem' =>
            'LATTES_PDF',

        ':curriculo_lattes_id' =>
            $id

    ]);


    $producaoId =
        (int)
        $pdo->lastInsertId();


    // =================================
    // ORDEM DE AUTORIA
    // =================================

    $ordemAutoria =
        descobrirOrdemAutoria(

            $docenteNome,

            $autores

        );


    // =================================
    // PRODUÇÃO X DOCENTE
    // =================================

    $stmtProducaoDocente->execute([

        ':producao_id' =>
            $producaoId,

        ':docente_id' =>
            $docenteId,

        ':ordem_autoria' =>
            $ordemAutoria

    ]);


    $quantidadeProducoes++;
}

// =====================================
// PERCORRE PRODUÇÃO TÉCNICA
// ASSESSORIA E CONSULTORIA
// =====================================

foreach (
    $producaoTecnicaEstruturada
    as $producaoTecnica
) {

    // =================================
    // TÍTULO
    // =================================

    $titulo =
        trim(
            $producaoTecnica['titulo']
            ?? ''
        );


    if ($titulo === '') {

        $quantidadeIgnoradaSemTitulo++;

        continue;
    }


    // =================================
    // ANO
    // =================================

    $ano =
        $producaoTecnica['ano']
        ?? null;


    if (
        $ano !== null
        &&
        $ano !== ''
    ) {

        $ano =
            (int) $ano;

    } else {

        $ano =
            null;
    }


    // =================================
    // AUTORES
    // =================================

    $autores =
        $producaoTecnica['autores']
        ?? [];


    if (!is_array(
        $autores
    )) {

        $autores = [];
    }


    // =================================
    // CHAVE ÚNICA
    // =================================

    $baseChave =
        'ASSESSORIA_CONSULTORIA|'
        . textoMinusculo(
            $titulo
        )
        . '|'
        . (
            $ano
            ?? ''
        );


    $chaveRegistro =
        hash(
            'sha256',
            $baseChave
        );


    if (
        isset(
            $chavesVistas[
                $chaveRegistro
            ]
        )
    ) {

        $quantidadeDuplicada++;

        continue;
    }


    $chavesVistas[
        $chaveRegistro
    ] = true;


    // =================================
    // JSON COMPLETO
    // =================================

    $dadosBrutos =
        json_encode(
            $producaoTecnica,
            JSON_UNESCAPED_UNICODE
            |
            JSON_UNESCAPED_SLASHES
        );


    if ($dadosBrutos === false) {

        throw new Exception(
            'Erro ao gerar JSON bruto da produção técnica.'
        );
    }


    // =================================
    // REGISTRO LATTES
    // =================================

    $stmtRegistro->execute([

        ':curriculo_lattes_id' =>
            $id,

        ':tipo_registro' =>
            'ASSESSORIA_CONSULTORIA',

        ':chave_registro' =>
            $chaveRegistro,

        ':titulo' =>
            $titulo,

        ':ano' =>
            $ano,

        ':dados_brutos' =>
            $dadosBrutos

    ]);


    $quantidadeRegistros++;


    // producoes.ano é NOT NULL

    if ($ano === null) {

        $quantidadeIgnoradaSemAno++;

        continue;
    }


    // =================================
    // PRODUÇÃO
    // =================================

    $stmtProducao->execute([

        ':titulo' =>
            $titulo,

        ':doi' =>
            null,

        ':issn' =>
            null,

        ':periodico_id' =>
            null,

        ':ano' =>
            $ano,

        ':tipo_producao_id' =>
            $tipoAssessoriaConsultoriaId,

        ':status' =>
            'IMPORTADO',

        ':origem' =>
            'LATTES_PDF',

        ':curriculo_lattes_id' =>
            $id

    ]);


    $producaoId =
        (int)
        $pdo->lastInsertId();


    // =================================
    // ORDEM DE AUTORIA
    // =================================

    $ordemAutoria =
        descobrirOrdemAutoria(
            $docenteNome,
            $autores
        );


    // =================================
    // PRODUÇÃO X DOCENTE
    // =================================

    $stmtProducaoDocente->execute([

        ':producao_id' =>
            $producaoId,

        ':docente_id' =>
            $docenteId,

        ':ordem_autoria' =>
            $ordemAutoria

    ]);


    $quantidadeProducoes++;
}

// =====================================
// PERCORRE PROJETOS DE PESQUISA
// =====================================

foreach (
    $projetosPesquisaEstruturados
    as $projeto
) {

    // =================================
    // TÍTULO
    // =================================

    $titulo =
        trim(
            $projeto['titulo']
            ?? ''
        );


    if ($titulo === '') {

        $quantidadeIgnoradaSemTitulo++;

        continue;
    }


    // =================================
    // ANOS
    // =================================

    $anoInicio =
        $projeto['ano_inicio']
        ?? null;

    $anoFim =
        $projeto['ano_fim']
        ?? null;


    if (
        $anoInicio !== null
        &&
        $anoInicio !== ''
    ) {

        $anoInicio =
            (int) $anoInicio;

    } else {

        $anoInicio = null;
    }


    if (
        $anoFim !== null
        &&
        $anoFim !== ''
    ) {

        $anoFim =
            (int) $anoFim;

    } else {

        $anoFim = null;
    }


    // =================================
    // DATAS
    // =================================

    $dataInicio =
        $anoInicio !== null
        ? $anoInicio . '-01-01'
        : null;


    $dataFim =
        $anoFim !== null
        ? $anoFim . '-12-31'
        : null;


    // =================================
    // SITUAÇÃO
    // =================================

    $situacao =
        trim(
            $projeto['situacao']
            ?? ''
        );


    if ($situacao === '') {

        $situacao =
            'Não informado';
    }


    // =================================
    // FINANCIADOR
    // =================================

    $financiador =
        trim(
            $projeto['financiador']
            ?? ''
        );


    $financiado =
        $financiador !== ''
        ? 1
        : 0;


    // =================================
    // OBSERVAÇÕES
    // =================================

    $observacoesPartes = [];


    $descricao =
        trim(
            $projeto['descricao']
            ?? ''
        );


    if ($descricao !== '') {

        $observacoesPartes[] =
            'Descrição: '
            . $descricao;
    }


    $natureza =
        trim(
            $projeto['natureza']
            ?? ''
        );


    if ($natureza !== '') {

        $observacoesPartes[] =
            'Natureza: '
            . $natureza;
    }


    $certificacao =
        trim(
            $projeto['certificacao']
            ?? ''
        );


    if ($certificacao !== '') {

        $observacoesPartes[] =
            'Certificação: '
            . $certificacao;
    }


    $alunosTexto =
        trim(
            $projeto['alunos_texto']
            ?? ''
        );


    if ($alunosTexto !== '') {

        $observacoesPartes[] =
            'Alunos envolvidos: '
            . $alunosTexto;
    }


    $tipoFinanciamento =
        trim(
            $projeto['tipo_financiamento']
            ?? ''
        );


    if ($tipoFinanciamento !== '') {

        $observacoesPartes[] =
            'Tipo de financiamento: '
            . $tipoFinanciamento;
    }


    $numeroProducoes =
        $projeto['numero_producoes']
        ?? null;


    if ($numeroProducoes !== null) {

        $observacoesPartes[] =
            'Número de produções C, T & A: '
            . (int) $numeroProducoes;
    }


    $observacoes =
        !empty($observacoesPartes)
        ? implode(
            PHP_EOL,
            $observacoesPartes
        )
        : null;


    // =================================
    // CHAVE DO REGISTRO
    // =================================

    $baseChave =
        'PROJETO_PESQUISA|'
        . textoMinusculo(
            $titulo
        )
        . '|'
        . (
            $anoInicio
            ?? ''
        )
        . '|'
        . (
            $anoFim
            ?? 'ATUAL'
        );


    $chaveRegistro =
        hash(
            'sha256',
            $baseChave
        );


    if (
        isset(
            $chavesVistas[
                $chaveRegistro
            ]
        )
    ) {

        $quantidadeDuplicada++;

        continue;
    }


    $chavesVistas[
        $chaveRegistro
    ] = true;


    // =================================
    // JSON BRUTO
    // =================================

    $dadosBrutos =
        json_encode(
            $projeto,
            JSON_UNESCAPED_UNICODE
            |
            JSON_UNESCAPED_SLASHES
        );


    if ($dadosBrutos === false) {

        throw new Exception(
            'Erro ao gerar JSON bruto do projeto de pesquisa.'
        );
    }


    // =================================
    // REGISTRO LATTES
    // =================================

    $stmtRegistro->execute([

        ':curriculo_lattes_id' =>
            $id,

        ':tipo_registro' =>
            'PROJETO_PESQUISA',

        ':chave_registro' =>
            $chaveRegistro,

        ':titulo' =>
            $titulo,

        ':ano' =>
            $anoInicio,

        ':dados_brutos' =>
            $dadosBrutos

    ]);


    $quantidadeRegistros++;


    // =================================
    // PROJETO
    // =================================

    $stmtProjeto->execute([

        ':nome' =>
            $titulo,

        ':data_inicio' =>
            $dataInicio,

        ':data_fim' =>
            $dataFim,

        ':situacao' =>
            $situacao,

        ':financiado' =>
            $financiado,

        ':internacional' =>
            0,

        ':observacoes' =>
            $observacoes,

        ':curriculo_lattes_id' =>
            $id

    ]);


    $projetoId =
        (int)
        $pdo->lastInsertId();


    // =================================
    // FUNÇÃO DO DOCENTE NO PROJETO
    // =================================

    $integrantes =
        $projeto['integrantes']
        ?? [];


    if (!is_array(
        $integrantes
    )) {

        $integrantes = [];
    }


    $funcao =
        descobrirFuncaoProjeto(
            $docenteNome,
            $integrantes
        );


    // =================================
    // PROJETO X DOCENTE
    // =================================

    $stmtProjetoDocente->execute([

        ':projeto_id' =>
            $projetoId,

        ':docente_id' =>
            $docenteId,

        ':funcao' =>
            $funcao

    ]);


    // =================================
    // PROJETO X AGÊNCIA
    // =================================

    if ($financiador !== '') {

        $agenciaId =
            buscarOuCriarAgenciaFinanciadora(
                $pdo,
                $financiador
            );


        if ($agenciaId !== null) {

            $stmtProjetoAgencia->execute([

                ':projeto_id' =>
                    $projetoId,

                ':agencia_id' =>
                    $agenciaId

            ]);
        }
    }
}


// =====================================
// PERCORRE ORIENTAÇÕES
// =====================================

foreach (
    $orientacoesEstruturadas
    as $orientacao
) {

    // =================================
    // ORIENTANDO
    // =================================

    $orientando =
        trim(
            (string) (
                $orientacao['orientando']
                ?? ''
            )
        );


    if ($orientando === '') {

        continue;
    }


    // =================================
    // ANO
    // =================================

    $ano =
        $orientacao['ano_conclusao']
        ?? $orientacao['ano_inicio']
        ?? null;


    if (
        $ano === null
        ||
        $ano === ''
    ) {

        continue;
    }


    $ano = (int) $ano;


    if (
        $ano < 1900
        ||
        $ano > 2200
    ) {

        continue;
    }


    // =================================
    // DADOS PRINCIPAIS
    // =================================

    $categoria =
        trim(
            (string) (
                $orientacao['categoria']
                ?? ''
            )
        );


    $nivel =
        descobrirNivelOrientacao(
            $orientacao
        );


    $situacao =
        trim(
            (string) (
                $orientacao['status']
                ?? ''
            )
        );


    if ($situacao === '') {

        $situacao =
            array_key_exists(
                'ano_conclusao',
                $orientacao
            )
            ? 'Concluída'
            : 'Em andamento';
    }


    $tema =
        trim(
            (string) (
                $orientacao['titulo']
                ?? ''
            )
        );


    if ($tema === '') {

        $tema = null;

    } else {

        if (function_exists(
            'mb_substr'
        )) {

            $tema = mb_substr(
                $tema,
                0,
                500,
                'UTF-8'
            );

        } else {

            $tema = substr(
                $tema,
                0,
                500
            );
        }
    }


    $instituicaoNome =
        trim(
            (string) (
                $orientacao['instituicao']
                ?? ''
            )
        );


    $funcao =
        descobrirFuncaoOrientacao(
            $orientacao
        );


    // =================================
    // CHAVE ÚNICA DESTA IMPORTAÇÃO
    // =================================

    $baseChave =
        'ORIENTACAO|'
        . normalizarNome(
            $orientando
        )
        . '|'
        . textoMinusculo(
            $categoria
        )
        . '|'
        . textoMinusculo(
            $situacao
        )
        . '|'
        . $ano
        . '|'
        . textoMinusculo(
            (string) (
                $tema
                ?? ''
            )
        );


    $chaveRegistro =
        hash(
            'sha256',
            $baseChave
        );


    if (isset(
        $chavesVistas[
            $chaveRegistro
        ]
    )) {

        $quantidadeDuplicada++;

        continue;
    }


    $chavesVistas[
        $chaveRegistro
    ] = true;


    // =================================
    // DISCENTE E INSTITUIÇÃO
    // =================================

    $discenteId =
        buscarOuCriarDiscente(
            $pdo,
            $orientando
        );


    if ($discenteId === null) {

        continue;
    }


    $instituicaoId =
        buscarOuCriarInstituicao(
            $pdo,
            $instituicaoNome
        );


    // =================================
    // JSON BRUTO / AUDITORIA
    // =================================

    $dadosBrutos =
        json_encode(
            $orientacao,
            JSON_UNESCAPED_UNICODE
            |
            JSON_UNESCAPED_SLASHES
        );


    if ($dadosBrutos === false) {

        throw new Exception(
            'Erro ao gerar JSON bruto de uma orientação.'
        );
    }


    $stmtRegistro->execute([

        ':curriculo_lattes_id' =>
            $id,

        ':tipo_registro' =>
            'ORIENTACAO',

        ':chave_registro' =>
            $chaveRegistro,

        ':titulo' =>
            $tema
            ?? $orientando,

        ':ano' =>
            $ano,

        ':dados_brutos' =>
            $dadosBrutos

    ]);


    $quantidadeRegistros++;


    // =================================
    // ORIENTAÇÃO
    // =================================

    $stmtOrientacao->execute([

        ':discente_id' =>
            $discenteId,

        ':instituicao_id' =>
            $instituicaoId,

        ':nivel' =>
            $nivel,

        ':situacao' =>
            $situacao,

        ':ano' =>
            $ano,

        ':tema' =>
            $tema,

        ':curriculo_lattes_id' =>
            $id

    ]);


    $orientacaoId =
        (int)
        $pdo->lastInsertId();


    // =================================
    // ORIENTAÇÃO X DOCENTE
    // =================================

    $stmtOrientacaoDocente->execute([

        ':orientacao_id' =>
            $orientacaoId,

        ':docente_id' =>
            $docenteId,

        ':funcao' =>
            $funcao

    ]);


    $quantidadeOrientacoes++;
}


        // =====================================
        // RESUMO DA IMPORTAÇÃO
        // =====================================

        $resumo = [

            'formato' =>
                'PDF',

            'paginas' =>
                $resultado['paginas']
                ?? 0,

            'caracteres' =>
                $resultado['caracteres']
                ?? 0,

            'quantidade_secoes' =>
                $resultado[
                    'quantidade_secoes'
                ]
                ?? 0,

            'quantidade_artigos_detectados' =>
                $quantidadeDetectada,

            'quantidade_registros_lattes' =>
                $quantidadeRegistros,

            'quantidade_producoes_criadas' =>
                $quantidadeProducoes,

            'quantidade_orientacoes_detectadas' =>
                $quantidadeOrientacoesDetectadas,

            'quantidade_orientacoes_criadas' =>
                $quantidadeOrientacoes,

            'quantidade_ignorados_sem_titulo' =>
                $quantidadeIgnoradaSemTitulo,

            'quantidade_ignorados_sem_ano' =>
                $quantidadeIgnoradaSemAno,

            'quantidade_duplicados_ignorados' =>
                $quantidadeDuplicada

        ];


        $resumoJson =
            json_encode(

                $resumo,

                JSON_UNESCAPED_UNICODE
                |
                JSON_UNESCAPED_SLASHES

            );


        if ($resumoJson === false) {

            throw new Exception(
                'Erro ao gerar o resumo da importação.'
            );

        }


        // =====================================
        // ATUALIZA curriculos_lattes
        // =====================================

        $stmtCurriculo =
            $pdo->prepare("

                UPDATE curriculos_lattes

                SET
                    quantidade_registros = :quantidade,
                    dados_extraidos = :dados_extraidos,
                    status = 'CONCLUIDO',
                    observacoes = NULL

                WHERE id = :id

            ");


        $stmtCurriculo->execute([

            ':quantidade' =>
                $quantidadeRegistros,

            ':dados_extraidos' =>
                $resumoJson,

            ':id' =>
                $id

        ]);


        // =====================================
        // CONSOLIDA SNAPSHOT OPERACIONAL
        // =====================================
        //
        // Primeiro reaproveitamos metadados institucionais
        // do último snapshot normalizado deste docente.
        // Depois removemos somente os snapshots normalizados
        // antigos. Histórico bruto permanece preservado.
        //

        $curriculoOperacionalAnterior =
            encontrarCurriculoOperacionalAnterior(
                $pdo,
                $docenteId,
                $id
            );


        if ($curriculoOperacionalAnterior !== null) {

            transferirMetadadosInstitucionaisProducoes(
                $pdo,
                $curriculoOperacionalAnterior,
                $id
            );

        }


        limparSnapshotsNormalizadosAnteriores(
            $pdo,
            $docenteId,
            $id
        );


        // =====================================
        // FINALIZA TRANSAÇÃO
        // =====================================

        $pdo->commit();


        // =====================================
        // REDIRECIONA
        // =====================================

        header(
            'Location: visualizar.php?id='
            . $id
        );


        exit;


    } catch (Throwable $erro) {


        if (
            $pdo->inTransaction()
        ) {

            $pdo->rollBack();

        }


        try {

            atualizarStatus(

                $pdo,

                $id,

                'ERRO',

                $erro->getMessage()

            );

        } catch (Throwable $erroStatus) {

            // Mantemos o erro original.

        }


        die(

            'Erro ao processar PDF: '
            . htmlspecialchars(
                $erro->getMessage()
            )

        );

    }

}


// O bloco XML existente continua abaixo.
// Ele ainda funciona como antes.

// ########################################################
// XML
// ########################################################

if ($extensao !== 'xml') {


    atualizarStatus(
        $pdo,
        $id,
        'ERRO'
    );


    die(
        'Formato de arquivo não suportado.'
    );

}


// ========================================
// STATUS XML
// ========================================

atualizarStatus(
    $pdo,
    $id,
    'PROCESSANDO'
);


// ========================================
// CARREGA XML
// ========================================

libxml_use_internal_errors(
    true
);


$xml =
    simplexml_load_file(

        $caminhoArquivo,

        'SimpleXMLElement',

        LIBXML_NONET
        |
        LIBXML_NOCDATA

    );


if ($xml === false) {


    atualizarStatus(
        $pdo,
        $id,
        'ERRO'
    );


    die(
        'Não foi possível interpretar o XML.'
    );

}


// ========================================
// TIPOS QUE EXTRAÍMOS POR ENQUANTO
// ========================================

$tiposExtracao = [


    [

        'tipo' =>
            'ARTIGO',

        'tag_item' =>
            'ARTIGO-PUBLICADO',

        'tag_dados' =>
            'DADOS-BASICOS-DO-ARTIGO',

        'titulo' =>
            'TITULO-DO-ARTIGO',

        'ano' =>
            'ANO-DO-ARTIGO'

    ],


    [

        'tipo' =>
            'LIVRO',

        'tag_item' =>
            'LIVRO-PUBLICADO-OU-ORGANIZADO',

        'tag_dados' =>
            'DADOS-BASICOS-DO-LIVRO',

        'titulo' =>
            'TITULO-DO-LIVRO',

        'ano' =>
            'ANO'

    ],


    [

        'tipo' =>
            'CAPITULO',

        'tag_item' =>
            'CAPITULO-DE-LIVRO-PUBLICADO',

        'tag_dados' =>
            'DADOS-BASICOS-DO-CAPITULO',

        'titulo' =>
            'TITULO-DO-CAPITULO-DO-LIVRO',

        'ano' =>
            'ANO'

    ],


    [

        'tipo' =>
            'EVENTO',

        'tag_item' =>
            'TRABALHO-EM-EVENTOS',

        'tag_dados' =>
            'DADOS-BASICOS-DO-TRABALHO',

        'titulo' =>
            'TITULO-DO-TRABALHO',

        'ano' =>
            'ANO-DO-TRABALHO'

    ]

];


// ========================================
// PROCESSA XML
// ========================================

try {


    $pdo->beginTransaction();


    // ====================================
    // LIMPA REPROCESSAMENTO
    // ====================================

    $sqlDelete = "
        DELETE FROM registros_lattes

        WHERE curriculo_lattes_id = :id
    ";


    $stmtDelete =
        $pdo->prepare(
            $sqlDelete
        );


    $stmtDelete->execute([

        ':id' =>
            $id

    ]);


    // ====================================
    // PREPARA INSERT
    // ====================================

    $sqlRegistro = "
        INSERT INTO registros_lattes (

            curriculo_lattes_id,

            tipo_registro,

            chave_registro,

            titulo,

            ano,

            dados_brutos

        )

        VALUES (

            :curriculo_lattes_id,

            :tipo_registro,

            :chave_registro,

            :titulo,

            :ano,

            :dados_brutos

        )
    ";


    $stmtRegistro =
        $pdo->prepare(
            $sqlRegistro
        );


    $quantidade =
        0;


    $resumo =
        [];


    // ====================================
    // PERCORRE TIPOS
    // ====================================

    foreach (
        $tiposExtracao
        as $config
    ) {


        $itens =
            $xml->xpath(

                '//*[local-name()="'

                . $config['tag_item']

                . '"]'

            );


        $resumo[
            $config['tipo']
        ] = 0;


        foreach (
            $itens
            as $item
        ) {


            $dados =
                pegarPrimeiroFilho(

                    $item,

                    $config['tag_dados']

                );


            if (!$dados) {

                continue;

            }


            // ====================================
            // TÍTULO
            // ====================================

            $titulo =
                pegarAtributo(

                    $dados,

                    $config['titulo']

                );


            // ====================================
            // ANO
            // ====================================

            $ano =
                pegarAtributo(

                    $dados,

                    $config['ano']

                );


            // ====================================
            // AUTORES
            // ====================================

            $autores =
                pegarAutores(
                    $item
                );


            // ====================================
            // JSON BRUTO
            // ====================================

            $dadosBrutos = [


                'tipo' =>
                    $config['tipo'],


                'titulo' =>
                    $titulo,


                'ano' =>
                    $ano,


                'autores' =>
                    $autores

            ];


            // ====================================
            // ARTIGO
            // ====================================

            if (
                $config['tipo']
                === 'ARTIGO'
            ) {


                $detalhamento =
                    pegarPrimeiroFilho(

                        $item,

                        'DETALHAMENTO-DO-ARTIGO'

                    );


                $dadosBrutos['doi'] =
                    pegarAtributo(

                        $dados,

                        'DOI'

                    );


                if ($detalhamento) {


                    $dadosBrutos['issn'] =
                        pegarAtributo(

                            $detalhamento,

                            'ISSN'

                        );


                    $dadosBrutos['periodico'] =
                        pegarAtributo(

                            $detalhamento,

                            'TITULO-DO-PERIODICO-OU-REVISTA'

                        );

                }

            }


            // ====================================
            // CHAVE
            // ====================================

            $baseChave =

                $config['tipo']

                . '|'

                . (
                    $dadosBrutos['doi']
                    ?? ''
                )

                . '|'

                . (
                    $titulo
                    ?? ''
                )

                . '|'

                . (
                    $ano
                    ?? ''
                );


            $chaveRegistro =
                hash(

                    'sha256',

                    $baseChave

                );


            // ====================================
            // INSERT
            // ====================================

            $stmtRegistro->execute([


                ':curriculo_lattes_id' =>
                    $id,


                ':tipo_registro' =>
                    $config['tipo'],


                ':chave_registro' =>
                    $chaveRegistro,


                ':titulo' =>
                    $titulo,


                ':ano' =>

                    $ano !== null

                        ? (int) $ano

                        : null,


                ':dados_brutos' =>

                    json_encode(

                        $dadosBrutos,

                        JSON_UNESCAPED_UNICODE

                        |

                        JSON_UNESCAPED_SLASHES

                    )

            ]);


            $quantidade++;


            $resumo[
                $config['tipo']
            ]++;

        }

    }


    // ====================================
    // ATUALIZA CURRÍCULO
    // ====================================

    $sqlUpdate = "
        UPDATE curriculos_lattes

        SET

            quantidade_registros =
                :quantidade,

            dados_extraidos =
                :dados_extraidos,

            status =
                'CONCLUIDO'

        WHERE id = :id
    ";


    $stmtUpdate =
        $pdo->prepare(
            $sqlUpdate
        );


    $stmtUpdate->execute([


        ':quantidade' =>
            $quantidade,


        ':dados_extraidos' =>

            json_encode(

                $resumo,

                JSON_UNESCAPED_UNICODE

            ),


        ':id' =>
            $id

    ]);


    $pdo->commit();


    header(

        'Location: visualizar.php?id='
        . $id

    );


    exit;


} catch (Throwable $e) {


    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    atualizarStatus(
        $pdo,
        $id,
        'ERRO'
    );


    die(

        'Erro ao processar currículo XML: '

        . $e->getMessage()

    );

}

?>