<?php

require_once '../../config/conexao.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: index.php'
    );

    exit;

}


$docente_id =
    (int) (
        $_POST['docente_id']
        ?? 0
    );


$data_atualizacao_lattes =
    trim(
        $_POST['data_atualizacao_lattes']
        ?? ''
    );


if ($docente_id <= 0) {

    die(
        'Docente inválido.'
    );

}


$sqlDocente = "
    SELECT id
    FROM docentes
    WHERE id = :id
";


$stmtDocente =
    $pdo->prepare(
        $sqlDocente
    );


$stmtDocente->execute([

    ':id' =>
        $docente_id

]);


if (!$stmtDocente->fetch()) {

    die(
        'Docente não encontrado.'
    );

}


if (!isset($_FILES['arquivo'])) {

    die(
        'Nenhum arquivo foi enviado.'
    );

}


$arquivo =
    $_FILES['arquivo'];



if ($arquivo['error'] !== UPLOAD_ERR_OK) {

    die(
        'Erro durante o envio do arquivo. Código: '
        . $arquivo['error']
    );

}


$tamanhoMaximo =
    20 * 1024 * 1024;


if ($arquivo['size'] <= 0) {

    die(
        'O arquivo enviado está vazio.'
    );

}


if ($arquivo['size'] > $tamanhoMaximo) {

    die(
        'O arquivo ultrapassa o limite de 20 MB.'
    );

}


$nomeOriginal =
    basename(
        $arquivo['name']
    );


$extensao =
    strtolower(
        pathinfo(
            $nomeOriginal,
            PATHINFO_EXTENSION
        )
    );


$extensoesPermitidas = [

    'pdf',

    'xml'

];


if (!in_array(
    $extensao,
    $extensoesPermitidas,
    true
)) {

    die(
        'Formato inválido. Utilize PDF ou XML.'
    );

}


$hashArquivo =
    hash_file(
        'sha256',
        $arquivo['tmp_name']
    );


if ($hashArquivo === false) {

    die(
        'Não foi possível calcular o hash do arquivo.'
    );

}


// ========================================================
// VERIFICA SE O MESMO ARQUIVO JÁ FOI PROCESSADO
// ========================================================
//
// A versão continua sendo criada normalmente para preservar
// o histórico, mas um arquivo idêntico não precisa recriar
// novamente produções, projetos e orientações normalizadas.
//

$sqlCurriculoIgual = "
    SELECT
        id,
        versao,
        quantidade_registros,
        dados_extraidos

    FROM curriculos_lattes

    WHERE docente_id = :docente_id
      AND hash_arquivo = :hash_arquivo
      AND status = 'CONCLUIDO'

    ORDER BY versao DESC

    LIMIT 1
";


$stmtCurriculoIgual =
    $pdo->prepare(
        $sqlCurriculoIgual
    );


$stmtCurriculoIgual->execute([

    ':docente_id' =>
        $docente_id,

    ':hash_arquivo' =>
        $hashArquivo

]);


$curriculoIgualAnterior =
    $stmtCurriculoIgual->fetch(
        PDO::FETCH_ASSOC
    );


$sqlVersao = "
    SELECT

        COALESCE(
            MAX(versao),
            0
        ) + 1

    FROM curriculos_lattes

    WHERE docente_id = :docente_id
";


$stmtVersao =
    $pdo->prepare(
        $sqlVersao
    );


$stmtVersao->execute([

    ':docente_id' =>
        $docente_id

]);


$versao =
    (int)
    $stmtVersao->fetchColumn();


$raizProjeto =
    dirname(
        __DIR__,
        2
    );


$diretorioUpload =
    $raizProjeto
    . DIRECTORY_SEPARATOR
    . 'uploads'
    . DIRECTORY_SEPARATOR
    . 'lattes';


if (!is_dir($diretorioUpload)) {


    if (!mkdir(
        $diretorioUpload,
        0775,
        true
    )) {

        die(
            'Não foi possível criar a pasta de upload.'
        );

    }

}


$nomeArquivo =

    'docente_'

    . $docente_id

    . '_v'

    . $versao

    . '_'

    . date(
        'Ymd_His'
    )

    . '.'

    . $extensao;


$caminhoCompleto =
    $diretorioUpload
    . DIRECTORY_SEPARATOR
    . $nomeArquivo;


$caminhoBanco =
    'uploads/lattes/'
    . $nomeArquivo;



if (!move_uploaded_file(
    $arquivo['tmp_name'],
    $caminhoCompleto
)) {

    die(
        'Não foi possível salvar o arquivo.'
    );

}

try {


    $sql = "
        INSERT INTO curriculos_lattes (

            docente_id,

            versao,

            nome_arquivo,

            caminho_arquivo,

            hash_arquivo,

            quantidade_registros,

            data_atualizacao_lattes,

            status

        )

        VALUES (

            :docente_id,

            :versao,

            :nome_arquivo,

            :caminho_arquivo,

            :hash_arquivo,

            0,

            :data_atualizacao_lattes,

            'PENDENTE'

        )
    ";


    $stmt =
        $pdo->prepare(
            $sql
        );


    $stmt->execute([


        ':docente_id' =>
            $docente_id,


        ':versao' =>
            $versao,


        ':nome_arquivo' =>
            $nomeOriginal,


        ':caminho_arquivo' =>
            $caminhoBanco,


        ':hash_arquivo' =>
            $hashArquivo,


        ':data_atualizacao_lattes' =>

            $data_atualizacao_lattes !== ''

                ? $data_atualizacao_lattes

                : null

    ]);


    $curriculo_id =
        (int)
        $pdo->lastInsertId();


    // ====================================================
    // ARQUIVO IDÊNTICO: REAPROVEITA A EXTRAÇÃO BRUTA
    // ====================================================
    //
    // A nova versão do currículo continua existindo.
    // Copiamos apenas registros_lattes/dados_extraidos.
    // O snapshot normalizado atual permanece único,
    // evitando inflar Dashboard, Rankings e Relatórios.
    //

    if ($curriculoIgualAnterior) {

        try {

            $pdo->beginTransaction();


            $stmtCopiarRegistros =
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

                    SELECT
                        :novo_curriculo_id,
                        tipo_registro,
                        chave_registro,
                        titulo,
                        ano,
                        dados_brutos

                    FROM registros_lattes

                    WHERE curriculo_lattes_id = :curriculo_anterior_id
                ");


            $stmtCopiarRegistros->execute([

                ':novo_curriculo_id' =>
                    $curriculo_id,

                ':curriculo_anterior_id' =>
                    (int) $curriculoIgualAnterior['id']

            ]);


            $stmtConcluirCopia =
                $pdo->prepare("
                    UPDATE curriculos_lattes

                    SET
                        quantidade_registros = :quantidade,
                        dados_extraidos = :dados_extraidos,
                        status = 'CONCLUIDO',
                        observacoes = :observacoes

                    WHERE id = :id
                ");


            $stmtConcluirCopia->execute([

                ':quantidade' =>
                    (int)
                    $curriculoIgualAnterior[
                        'quantidade_registros'
                    ],

                ':dados_extraidos' =>
                    $curriculoIgualAnterior[
                        'dados_extraidos'
                    ],

                ':observacoes' =>
                    'Arquivo idêntico à versão v'
                    . (int)
                    $curriculoIgualAnterior['versao']
                    . '. Extração histórica reaproveitada sem duplicar o snapshot operacional.',

                ':id' =>
                    $curriculo_id

            ]);


            $pdo->commit();


            header(
                'Location: visualizar.php?id='
                . $curriculo_id
            );


            exit;


        } catch (Throwable $erroCopia) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }


            // Se o reaproveitamento falhar, seguimos para
            // o processamento normal do arquivo.
        }

    }


    header(

        'Location: processar.php?id='
        . $curriculo_id

    );


    exit;


} catch (PDOException $e) {

    if (file_exists(
        $caminhoCompleto
    )) {

        unlink(
            $caminhoCompleto
        );

    }


    die(
        'Erro ao registrar currículo: '
        . $e->getMessage()
    );

}

?>