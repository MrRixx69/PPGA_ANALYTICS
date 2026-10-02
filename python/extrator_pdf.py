import sys
import json
import os
import re

import pymupdf


# =========================================
# CONFIGURA SAÍDA UTF-8
# =========================================

try:
    sys.stdout.reconfigure(
        encoding="utf-8"
    )

except Exception:
    pass


# =========================================
# RETORNA ERRO EM JSON
# =========================================

def retornar_erro(mensagem):

    resultado = {
        "sucesso": False,
        "erro": mensagem
    }

    print(
        json.dumps(
            resultado,
            ensure_ascii=False
        )
    )

    sys.exit(1)


# =========================================
# NORMALIZA O TEXTO EXTRAÍDO
# =========================================

def normalizar_texto(texto):

    # Padroniza quebras de linha
    texto = texto.replace(
        "\r",
        "\n"
    )

    # Remove excesso de espaços e tabs
    texto = re.sub(
        r"[ \t]+",
        " ",
        texto
    )

    # Evita muitas linhas vazias seguidas
    texto = re.sub(
        r"\n{3,}",
        "\n\n",
        texto
    )

    return texto.strip()


# =========================================
# NORMALIZA TÍTULOS DE SEÇÕES
# =========================================

def normalizar_titulo(texto):

    texto = texto.strip().lower()

    texto = re.sub(
        r"\s+",
        " ",
        texto
    )

    # Permite, por exemplo:
    # "Projetos de pesquisa:"
    # ser tratado como
    # "Projetos de pesquisa"

    texto = texto.rstrip(
        ":.-"
    )

    return texto


# =========================================
# SEÇÕES CONHECIDAS DO CURRÍCULO LATTES
# =========================================

SECOES_CONHECIDAS = [

    "Artigos completos publicados em periódicos",

    "Livros publicados/organizados ou edições",

    "Livros publicados ou organizados",

    "Capítulos de livros publicados",

    "Textos em jornais de notícias/revistas",

    "Trabalhos completos publicados em anais de congressos",

    "Resumos expandidos publicados em anais de congressos",

    "Resumos publicados em anais de congressos",

    "Apresentações de Trabalho",

    "Outras produções bibliográficas",

    "Produção técnica",

    "Software",

    "Produtos tecnológicos",

    "Patentes e registros",

    "Projetos de pesquisa",

    "Projetos de extensão",

    "Projetos de desenvolvimento",

    "Orientações e supervisões concluídas",

    "Orientações e supervisões em andamento",

    "Bancas",

    "Participação em bancas de trabalhos de conclusão",

    "Participação em bancas de comissões julgadoras",

    "Eventos",

    "Participação em eventos, congressos, exposições e feiras",

    "Organização de eventos, congressos, exposições e feiras",

    "Prêmios e títulos",

    "Formação acadêmica/titulação",

    "Atuação profissional",

    "Áreas de atuação",

    "Idiomas",

    "Linhas de pesquisa",

    "Revisor de periódico",

    "Membro de corpo editorial"

]


# =========================================
# DETECTA AS SEÇÕES DO LATTES
# =========================================

def detectar_secoes(paginas):

    secoes = []

    # Cria uma versão normalizada dos títulos
    # para facilitar a comparação

    secoes_normalizadas = {
        normalizar_titulo(secao): secao
        for secao in SECOES_CONHECIDAS
    }


    for pagina in paginas:

        numero_pagina = pagina["pagina"]

        texto = pagina["texto"]

        linhas = texto.splitlines()


        for numero_linha, linha in enumerate(
            linhas,
            start=1
        ):

            linha_limpa = linha.strip()


            if not linha_limpa:
                continue


            linha_normalizada = normalizar_titulo(
                linha_limpa
            )


            # Aqui usamos comparação EXATA.
            #
            # Isso evita que:
            #
            # "Seminários de Linhas de Pesquisa"
            #
            # seja confundido com:
            #
            # "Linhas de pesquisa"

            if linha_normalizada in secoes_normalizadas:

                secao_original = secoes_normalizadas[
                    linha_normalizada
                ]


                secoes.append({

                    "secao":
                        secao_original,

                    "texto_encontrado":
                        linha_limpa,

                    "pagina":
                        numero_pagina,

                    "linha":
                        numero_linha

                })


    return secoes


# =========================================
# SEPARA O CONTEÚDO DE CADA SEÇÃO
# =========================================

def extrair_blocos_secoes(paginas):

    blocos = []

    secao_atual = None

    linhas_secao = []


    secoes_normalizadas = {
        normalizar_titulo(secao): secao
        for secao in SECOES_CONHECIDAS
    }


    for pagina in paginas:

        numero_pagina = pagina["pagina"]

        linhas = pagina["texto"].splitlines()


        for linha in linhas:

            linha_limpa = linha.strip()


            if not linha_limpa:
                continue


            linha_normalizada = normalizar_titulo(
                linha_limpa
            )


            # =================================
            # ENCONTROU UMA NOVA SEÇÃO
            # =================================

            if linha_normalizada in secoes_normalizadas:


                # Se já estávamos dentro de uma seção,
                # salvamos ela antes de começar a nova.

                if secao_atual is not None:

                    blocos.append({

                        "secao":
                            secao_atual,

                        "conteudo":
                            linhas_secao

                    })


                # Define a nova seção

                secao_atual = secoes_normalizadas[
                    linha_normalizada
                ]


                # Zera o conteúdo

                linhas_secao = []


                continue


            # =================================
            # CONTEÚDO DA SEÇÃO ATUAL
            # =================================

            if secao_atual is not None:

                linhas_secao.append({

                    "pagina":
                        numero_pagina,

                    "texto":
                        linha_limpa

                })


    # =========================================
    # SALVA A ÚLTIMA SEÇÃO
    # =========================================

    if secao_atual is not None:

        blocos.append({

            "secao":
                secao_atual,

            "conteudo":
                linhas_secao

        })


    return blocos

# =========================================
# SEPARA OS ARTIGOS INDIVIDUALMENTE
# =========================================

def extrair_artigos(blocos_secoes):

    artigos = []

    numero_bloco = 0


    for bloco in blocos_secoes:

        if normalizar_titulo(
            bloco["secao"]
        ) != normalizar_titulo(
            "Artigos completos publicados em periódicos"
        ):

            continue


        numero_bloco += 1

        artigo_atual = None

        numero_esperado = 1

        prefixo_numero = None


        for linha in bloco["conteudo"]:

            texto = linha["texto"].strip()

            pagina = linha["pagina"]


            # =================================
            # IGNORA CABEÇALHOS AUXILIARES
            # =================================

            if texto in [
                "Ordenar por",
                "Ordem Cronológica"
            ]:

                continue


            numero_artigo = None


            # =================================
            # TRATA NÚMEROS QUEBRADOS
            #
            # Exemplo:
            #
            # 100.
            #
            # pode vir como:
            #
            # 10
            # 0.
            # =================================

            if prefixo_numero is not None:

                sufixo = re.fullmatch(
                    r"(\d)\.",
                    texto
                )

                if sufixo:

                    candidato = int(
                        prefixo_numero
                        + sufixo.group(1)
                    )

                    if candidato == numero_esperado:

                        numero_artigo = candidato


                if numero_artigo is None:

                    if artigo_atual is not None:

                        artigo_atual["linhas"].append(
                            prefixo_numero
                        )


                prefixo_numero = None


            # =================================
            # DETECTA NÚMERO NORMAL
            #
            # 1.
            # 2.
            # ...
            # 99.
            # 100.
            # =================================

            if numero_artigo is None:

                resultado_numero = re.fullmatch(
                    r"([1-9]\d{0,2})\.",
                    texto
                )

                if resultado_numero:

                    candidato = int(
                        resultado_numero.group(1)
                    )

                    if candidato == numero_esperado:

                        numero_artigo = candidato


            # =================================
            # DETECTA PRIMEIRA PARTE DE 100+
            #
            # esperado 100 → "10"
            # esperado 101 → "10"
            # esperado 104 → "10"
            # =================================

            if (
                numero_artigo is None
                and numero_esperado >= 100
            ):

                esperado_texto = str(
                    numero_esperado
                )

                prefixo_esperado = esperado_texto[:-1]

                if texto == prefixo_esperado:

                    prefixo_numero = texto

                    continue


            # =================================
            # COMEÇA NOVO ARTIGO
            # =================================

            if numero_artigo is not None:

                if (
                    artigo_atual is not None
                    and artigo_atual["linhas"]
                ):

                    artigo_atual["texto"] = " ".join(
                        artigo_atual["linhas"]
                    )

                    del artigo_atual["linhas"]

                    artigos.append(
                        artigo_atual
                    )


                artigo_atual = {

                    "bloco":
                        numero_bloco,

                    "numero":
                        numero_artigo,

                    "pagina_inicio":
                        pagina,

                    "linhas":
                        []

                }


                numero_esperado += 1

                continue


            # =================================
            # CONTEÚDO DO ARTIGO
            # =================================

            if artigo_atual is not None:

                artigo_atual["linhas"].append(
                    texto
                )


        # =====================================
        # CASO TENHA SOBRADO PREFIXO
        # =====================================

        if (
            prefixo_numero is not None
            and artigo_atual is not None
        ):

            artigo_atual["linhas"].append(
                prefixo_numero
            )


        # =====================================
        # SALVA O ÚLTIMO ARTIGO
        # =====================================

        if (
            artigo_atual is not None
            and artigo_atual["linhas"]
        ):

            artigo_atual["texto"] = " ".join(
                artigo_atual["linhas"]
            )

            del artigo_atual["linhas"]

            artigos.append(
                artigo_atual
            )


        # Só queremos a primeira seção real
        # de artigos do currículo.

        break


    return artigos

# =========================================
# SEPARA OS LIVROS INDIVIDUALMENTE
# =========================================

def extrair_livros(blocos_secoes):

    livros = []

    numero_bloco = 0


    for bloco in blocos_secoes:

        secao = normalizar_titulo(
            bloco["secao"]
        )


        if secao not in [
            normalizar_titulo(
                "Livros publicados/organizados ou edições"
            ),
            normalizar_titulo(
                "Livros publicados ou organizados"
            )
        ]:

            continue


        numero_bloco += 1

        livro_atual = None

        numero_esperado = 1


        for linha in bloco["conteudo"]:

            texto = linha["texto"].strip()

            pagina = linha["pagina"]


            # =================================
            # DETECTA NÚMERO DO LIVRO
            # =================================

            resultado_numero = re.fullmatch(
                r"([1-9]\d{0,2})\.",
                texto
            )


            if resultado_numero:

                numero_livro = int(
                    resultado_numero.group(1)
                )


                if numero_livro != numero_esperado:

                    if livro_atual is not None:

                        livro_atual["linhas"].append(
                            texto
                        )

                    continue


                # Salva livro anterior

                if (
                    livro_atual is not None
                    and livro_atual["linhas"]
                ):

                    livro_atual["texto"] = " ".join(
                        livro_atual["linhas"]
                    )

                    del livro_atual["linhas"]

                    livros.append(
                        livro_atual
                    )


                # Começa novo livro

                livro_atual = {

                    "bloco":
                        numero_bloco,

                    "numero":
                        numero_livro,

                    "pagina_inicio":
                        pagina,

                    "linhas":
                        []

                }


                numero_esperado += 1

                continue


            # =================================
            # CONTEÚDO DO LIVRO
            # =================================

            if livro_atual is not None:

                livro_atual["linhas"].append(
                    texto
                )


        # =====================================
        # SALVA O ÚLTIMO LIVRO
        # =====================================

        if (
            livro_atual is not None
            and livro_atual["linhas"]
        ):

            livro_atual["texto"] = " ".join(
                livro_atual["linhas"]
            )

            del livro_atual["linhas"]

            livros.append(
                livro_atual
            )


        # Só queremos a primeira seção principal
        # de livros.

        break


    return livros

# =====================================
  # EXTRAI OS CAPÍTULOS DE LIVROS
 # =====================================

def extrair_capitulos(blocos_secoes):

    capitulos = []

    nome_secao = normalizar_titulo(
        "Capítulos de livros publicados"
    )

    for bloco in blocos_secoes:

        secao_atual = normalizar_titulo(
            bloco["secao"]
        )

        if secao_atual != nome_secao:
            continue

        numero_esperado = 1

        capitulo_atual = None

        for linha in bloco["conteudo"]:

            texto = linha["texto"].strip()

            numero = re.fullmatch(
                r"(\d+)\.",
                texto
            )

            if numero:

                numero_encontrado = int(
                    numero.group(1)
                )

                if numero_encontrado == numero_esperado:

                    if capitulo_atual is not None:

                        capitulo_atual["texto"] = " ".join(
                            capitulo_atual["linhas"]
                        )

                        del capitulo_atual["linhas"]

                        capitulos.append(
                            capitulo_atual
                        )

                    capitulo_atual = {
                        "bloco": 1,
                        "numero": numero_encontrado,
                        "pagina_inicio": linha["pagina"],
                        "linhas": []
                    }

                    numero_esperado += 1

                    continue

            if capitulo_atual is not None:

                capitulo_atual["linhas"].append(
                    texto
                )

        if capitulo_atual is not None:

            capitulo_atual["texto"] = " ".join(
                capitulo_atual["linhas"]
            )

            del capitulo_atual["linhas"]

            capitulos.append(
                capitulo_atual
            )

        break

    return capitulos

def extrair_trabalhos_anais(blocos_secoes):

    trabalhos = []

    nome_secao = normalizar_titulo(
        "Trabalhos completos publicados em anais de congressos"
    )

    for bloco in blocos_secoes:

        secao_atual = normalizar_titulo(
            bloco["secao"]
        )

        if secao_atual != nome_secao:
            continue

        numero_esperado = 1

        trabalho_atual = None

        for linha in bloco["conteudo"]:

            texto = linha["texto"].strip()

            numero = re.fullmatch(
                r"(\d+)\.",
                texto
            )

            if numero:

                numero_encontrado = int(
                    numero.group(1)
                )

                if numero_encontrado == numero_esperado:

                    if trabalho_atual is not None:

                        trabalho_atual["texto"] = " ".join(
                            trabalho_atual["linhas"]
                        )

                        del trabalho_atual["linhas"]

                        trabalhos.append(
                            trabalho_atual
                        )

                    trabalho_atual = {
                        "bloco": 1,
                        "numero": numero_encontrado,
                        "pagina_inicio": linha["pagina"],
                        "linhas": []
                    }

                    numero_esperado += 1

                    continue

            if trabalho_atual is not None:

                trabalho_atual["linhas"].append(
                    texto
                )

        if trabalho_atual is not None:

            trabalho_atual["texto"] = " ".join(
                trabalho_atual["linhas"]
            )

            del trabalho_atual["linhas"]

            trabalhos.append(
                trabalho_atual
            )

        break

    return trabalhos

def extrair_resumos_expandidos(blocos_secoes):

    resumos = []

    nome_secao = normalizar_titulo(
        "Resumos expandidos publicados em anais de congressos"
    )

    for bloco in blocos_secoes:

        secao_atual = normalizar_titulo(
            bloco["secao"]
        )

        if secao_atual != nome_secao:
            continue

        numero_esperado = 1

        resumo_atual = None

        for linha in bloco["conteudo"]:

            texto = linha["texto"].strip()

            numero = re.fullmatch(
                r"(\d+)\.",
                texto
            )

            if numero:

                numero_encontrado = int(
                    numero.group(1)
                )

                if numero_encontrado == numero_esperado:

                    if resumo_atual is not None:

                        resumo_atual["texto"] = " ".join(
                            resumo_atual["linhas"]
                        )

                        del resumo_atual["linhas"]

                        resumos.append(
                            resumo_atual
                        )

                    resumo_atual = {
                        "bloco": 1,
                        "numero": numero_encontrado,
                        "pagina_inicio": linha["pagina"],
                        "linhas": []
                    }

                    numero_esperado += 1

                    continue

            if resumo_atual is not None:

                resumo_atual["linhas"].append(
                    texto
                )

        if resumo_atual is not None:

            resumo_atual["texto"] = " ".join(
                resumo_atual["linhas"]
            )

            del resumo_atual["linhas"]

            resumos.append(
                resumo_atual
            )

        break

    return resumos

def extrair_resumos_anais(blocos_secoes):

    resumos = []

    nome_secao = normalizar_titulo(
        "Resumos publicados em anais de congressos"
    )

    for bloco in blocos_secoes:

        secao_atual = normalizar_titulo(
            bloco["secao"]
        )

        if secao_atual != nome_secao:
            continue

        numero_esperado = 1
        resumo_atual = None

        for linha in bloco["conteudo"]:

            texto = linha["texto"].strip()

            numero = re.fullmatch(
                r"(\d+)\.",
                texto
            )

            if numero:

                numero_encontrado = int(
                    numero.group(1)
                )

                if numero_encontrado == numero_esperado:

                    if resumo_atual is not None:

                        resumo_atual["texto"] = " ".join(
                            resumo_atual["linhas"]
                        )

                        del resumo_atual["linhas"]

                        resumos.append(
                            resumo_atual
                        )

                    resumo_atual = {
                        "bloco": 1,
                        "numero": numero_encontrado,
                        "pagina_inicio": linha["pagina"],
                        "linhas": []
                    }

                    numero_esperado += 1
                    continue

            if resumo_atual is not None:

                resumo_atual["linhas"].append(
                    texto
                )

        if resumo_atual is not None:

            resumo_atual["texto"] = " ".join(
                resumo_atual["linhas"]
            )

            del resumo_atual["linhas"]

            resumos.append(
                resumo_atual
            )

        break

    return resumos

def extrair_producao_tecnica(blocos_secoes):

    producoes = []

    nome_secao = normalizar_titulo(
        "Produção técnica"
    )

    for bloco in blocos_secoes:

        secao_atual = normalizar_titulo(
            bloco["secao"]
        )

        if secao_atual != nome_secao:
            continue

        numero_esperado = 1
        producao_atual = None

        subcategoria = None

        for linha in bloco["conteudo"]:

            texto = linha["texto"].strip()

            # =====================================
            # SUBCATEGORIA
            # =====================================

            if (
                producao_atual is None
                and numero_esperado == 1
                and not re.fullmatch(r"\d+\.", texto)
            ):

                subcategoria = texto
                continue


            # =====================================
            # NOVO ITEM
            # =====================================

            numero = re.fullmatch(
                r"(\d+)\.",
                texto
            )

            if numero:

                numero_encontrado = int(
                    numero.group(1)
                )

                if numero_encontrado == numero_esperado:

                    if producao_atual is not None:

                        producao_atual["texto"] = " ".join(
                            producao_atual["linhas"]
                        )

                        del producao_atual["linhas"]

                        producoes.append(
                            producao_atual
                        )

                    producao_atual = {
                        "numero": numero_encontrado,
                        "pagina_inicio": linha["pagina"],
                        "subcategoria": subcategoria,
                        "linhas": []
                    }

                    numero_esperado += 1

                    continue


            # =====================================
            # CONTEÚDO DO ITEM
            # =====================================

            if producao_atual is not None:

                producao_atual["linhas"].append(
                    texto
                )


        # último registro

        if producao_atual is not None:

            producao_atual["texto"] = " ".join(
                producao_atual["linhas"]
            )

            del producao_atual["linhas"]

            producoes.append(
                producao_atual
            )

        break


    return producoes

def extrair_projetos_pesquisa(blocos_secoes):

    projetos = []

    nome_secao = normalizar_titulo(
        "Projetos de pesquisa"
    )

    for bloco in blocos_secoes:

        secao_atual = normalizar_titulo(
            bloco["secao"]
        )

        if secao_atual != nome_secao:
            continue


        projeto_atual = None


        for linha in bloco["conteudo"]:

            texto = linha["texto"].strip()


            # =====================================
            # INÍCIO DE UM NOVO PROJETO
            # Ex:
            # 2025 - Atual
            # 2023 - 2024
            # =====================================

            periodo = re.fullmatch(
                r"(\d{4})\s*-\s*(\d{4}|Atual)",
                texto,
                re.IGNORECASE
            )


            if periodo:

                # salva o projeto anterior

                if projeto_atual is not None:

                    projeto_atual["texto"] = " ".join(
                        projeto_atual["linhas"]
                    )

                    del projeto_atual["linhas"]

                    projetos.append(
                        projeto_atual
                    )


                ano_inicio = int(
                    periodo.group(1)
                )


                ano_fim_texto = periodo.group(2)


                if ano_fim_texto.lower() == "atual":

                    ano_fim = None
                    em_andamento = True

                else:

                    ano_fim = int(
                        ano_fim_texto
                    )

                    em_andamento = False


                projeto_atual = {

                    "numero":
                        len(projetos) + 1,

                    "pagina_inicio":
                        linha["pagina"],

                    "periodo":
                        texto,

                    "ano_inicio":
                        ano_inicio,

                    "ano_fim":
                        ano_fim,

                    "em_andamento":
                        em_andamento,

                    "linhas":
                        []
                }


                continue


            # =====================================
            # CONTEÚDO DO PROJETO
            # =====================================

            if projeto_atual is not None:

                projeto_atual["linhas"].append(
                    texto
                )


        # =========================================
        # ÚLTIMO PROJETO
        # =========================================

        if projeto_atual is not None:

            projeto_atual["texto"] = " ".join(
                projeto_atual["linhas"]
            )

            del projeto_atual["linhas"]

            projetos.append(
                projeto_atual
            )


        # IMPORTANTE:
        # usa somente o primeiro bloco principal.
        # O segundo bloco é repetido.

        break


    return projetos

# =========================================
# EXTRAI ORIENTAÇÕES EM ANDAMENTO
# =========================================

def extrair_orientacoes_andamento_categoria(
    blocos_secoes,
    categoria
):

    orientacoes = []

    nome_secao = normalizar_titulo(
        "Orientações e supervisões em andamento"
    )

    categorias = [
        "Dissertação de mestrado",
        "Tese de doutorado",
        "Supervisão de pós-doutorado",
        "Iniciação científica"
    ]

    for bloco in blocos_secoes:

        if normalizar_titulo(
            bloco["secao"]
        ) != nome_secao:

            continue

        conteudo = bloco["conteudo"]

        dentro_categoria = False
        orientacao_atual = None
        numero_esperado = 1

        for linha in conteudo:

            texto = linha["texto"].strip()

            # =====================================
            # ENTRA NA CATEGORIA
            # =====================================

            if normalizar_titulo(
                texto
            ) == normalizar_titulo(
                categoria
            ):

                dentro_categoria = True
                continue

            # =====================================
            # SAI AO ENCONTRAR OUTRA CATEGORIA
            # =====================================

            if (
                dentro_categoria
                and texto in categorias
                and normalizar_titulo(texto)
                    != normalizar_titulo(categoria)
            ):

                break

            if not dentro_categoria:
                continue

            # =====================================
            # NOVO ITEM
            # =====================================

            numero = re.fullmatch(
                r"(\d+)\.",
                texto
            )

            if numero:

                numero_encontrado = int(
                    numero.group(1)
                )

                if numero_encontrado == numero_esperado:

                    if orientacao_atual is not None:

                        orientacao_atual["texto"] = " ".join(
                            orientacao_atual["linhas"]
                        )

                        del orientacao_atual["linhas"]

                        orientacoes.append(
                            orientacao_atual
                        )

                    orientacao_atual = {
                        "numero":
                            numero_encontrado,

                        "pagina_inicio":
                            linha["pagina"],

                        "categoria":
                            categoria,

                        "linhas":
                            []
                    }

                    numero_esperado += 1
                    continue

            if orientacao_atual is not None:

                orientacao_atual["linhas"].append(
                    texto
                )

        if orientacao_atual is not None:

            orientacao_atual["texto"] = " ".join(
                orientacao_atual["linhas"]
            )

            del orientacao_atual["linhas"]

            orientacoes.append(
                orientacao_atual
            )

        break

    return orientacoes

# =========================================
# EXTRAI ORIENTAÇÕES CONCLUÍDAS
# =========================================

def extrair_orientacoes_concluidas_categoria(
    blocos_secoes,
    categoria
):

    orientacoes = []

    nome_secao = normalizar_titulo(
        "Orientações e supervisões concluídas"
    )

    categorias = [
        "Dissertação de mestrado",
        "Tese de doutorado",
        "Trabalho de conclusão de curso de graduação",
        "Iniciação científica",
        "Produto tecnológico"
    ]


    for bloco in blocos_secoes:

        if normalizar_titulo(
            bloco["secao"]
        ) != nome_secao:

            continue


        conteudo = bloco["conteudo"]

        dentro_categoria = False
        orientacao_atual = None
        numero_esperado = 1


        for linha in conteudo:

            texto = linha["texto"].strip()


            # =====================================
            # ENTRA NA CATEGORIA
            # =====================================

            if normalizar_titulo(
                texto
            ) == normalizar_titulo(
                categoria
            ):

                dentro_categoria = True
                continue


            # =====================================
            # SAI AO ENCONTRAR OUTRA CATEGORIA
            # =====================================

            if dentro_categoria:

                outra_categoria = any(
                    normalizar_titulo(texto)
                    == normalizar_titulo(item)
                    for item in categorias
                    if normalizar_titulo(item)
                    != normalizar_titulo(categoria)
                )

                if outra_categoria:
                    break


            if not dentro_categoria:
                continue


            # =====================================
            # NOVO ITEM
            # =====================================

            numero = re.fullmatch(
                r"(\d+)\.",
                texto
            )


            if numero:

                numero_encontrado = int(
                    numero.group(1)
                )


                if numero_encontrado == numero_esperado:

                    if orientacao_atual is not None:

                        orientacao_atual["texto"] = " ".join(
                            orientacao_atual["linhas"]
                        )

                        del orientacao_atual["linhas"]

                        orientacoes.append(
                            orientacao_atual
                        )


                    orientacao_atual = {

                        "numero":
                            numero_encontrado,

                        "pagina_inicio":
                            linha["pagina"],

                        "categoria":
                            categoria,

                        "linhas":
                            []
                    }


                    numero_esperado += 1
                    continue


            if orientacao_atual is not None:

                orientacao_atual["linhas"].append(
                    texto
                )


        if orientacao_atual is not None:

            orientacao_atual["texto"] = " ".join(
                orientacao_atual["linhas"]
            )

            del orientacao_atual["linhas"]

            orientacoes.append(
                orientacao_atual
            )


        break


    return orientacoes

def estruturar_produto_tecnologico_concluido(
    orientacao
):

    texto = limpar_texto_artigo(
        orientacao["texto"]
    )

    autores_texto = None
    autores = []
    titulo = None
    ano_conclusao = None
    restante = texto

    # =====================================
    # AUTORES
    # =====================================

    if " . " in texto:

        autores_texto, restante = texto.split(
            " . ",
            1
        )

        autores_texto = autores_texto.strip()

        autores = [
            autor.strip()
            for autor in autores_texto.split(";")
            if autor.strip()
        ]

    # =====================================
    # ANO E TÍTULO
    # =====================================

    resultado_ano = re.search(
        r"\.?\s*((?:19|20)\d{2})\.\s*$",
        restante
    )

    if resultado_ano:

        ano_conclusao = int(
            resultado_ano.group(1)
        )

        titulo = restante[
            :resultado_ano.start()
        ].strip(" .")

    else:

        titulo = restante.strip(
            " ."
        )

    # =====================================
    # RESULTADO
    # =====================================

    return {
        "numero":
            orientacao["numero"],

        "pagina_inicio":
            orientacao["pagina_inicio"],

        "categoria":
            orientacao["categoria"],

        "orientando":
            None,

        "titulo":
            titulo,

        "ano_conclusao":
            ano_conclusao,

        "curso":
            None,

        "instituicao":
            None,

        "financiador":
            None,

        "funcao":
            None,

        "orientador_nome":
            None,

        "autores_texto":
            autores_texto,

        "autores":
            autores,

        "status":
            "Concluída",

        "texto_original":
            texto
    }


# =========================================
# ESTRUTURA ORIENTAÇÃO CONCLUÍDA
# =========================================

def estruturar_orientacao_concluida(
    orientacao
):

    # Produto tecnológico tem um formato próprio no Lattes.
    if orientacao["categoria"] == "Produto tecnológico":

        return estruturar_produto_tecnologico_concluido(
            orientacao
        )

    texto = limpar_texto_artigo(
        orientacao["texto"]
    )

    orientando = None
    titulo = None
    ano_conclusao = None
    curso = None
    instituicao = None
    financiador = None
    funcao = None
    orientador_nome = None

    # =====================================
    # ANO DE CONCLUSÃO
    # =====================================

    resultado_ano = re.search(
        r"\.\s*((?:19|20)\d{2})\.\s*"
        r"(?:Dissertação|Tese|Trabalho de Conclusão de Curso|Iniciação Científica)",
        texto,
        re.IGNORECASE
    )

    if resultado_ano:

        ano_conclusao = int(
            resultado_ano.group(1)
        )

    # =====================================
    # ORIENTANDO E TÍTULO
    # =====================================

    if resultado_ano:

        trecho_inicial = texto[
            :resultado_ano.start()
        ].strip(" .")

        if ". " in trecho_inicial:

            orientando, titulo = trecho_inicial.split(
                ". ",
                1
            )

            orientando = orientando.strip(
                " ."
            )

            titulo = titulo.strip(
                " ."
            )

        else:

            orientando = trecho_inicial

    # =====================================
    # ORIENTADOR / COORIENTADOR
    # =====================================

    resultado_orientador = re.search(
        r"(Coorientador|Orientador):\s*([^.]+)\.",
        texto,
        re.IGNORECASE
    )

    if resultado_orientador:

        funcao = resultado_orientador.group(
            1
        ).strip()

        orientador_nome = resultado_orientador.group(
            2
        ).strip()

    # =====================================
    # DISSERTAÇÃO / TESE
    # =====================================

    if orientacao["categoria"] in [
        "Dissertação de mestrado",
        "Tese de doutorado"
    ]:

        resultado_detalhes = re.search(
            r"(?:Dissertação|Tese)\s*\((.*?)\)\s*-\s*"
            r"(.*?)\.\s*(?:Coorientador|Orientador):",
            texto,
            re.IGNORECASE
        )

        if resultado_detalhes:

            curso = resultado_detalhes.group(
                1
            ).strip()

            detalhes = resultado_detalhes.group(
                2
            ).strip(" .")

            partes = detalhes.rsplit(
                ",",
                1
            )

            instituicao = partes[0].strip(
                " ."
            )

            if len(partes) == 2:

                financiador_temp = partes[1].strip(
                    " ."
                )

                if financiador_temp:

                    financiador = financiador_temp

    # =====================================
    # TCC DE GRADUAÇÃO
    # =====================================

    elif orientacao["categoria"] == (
        "Trabalho de conclusão de curso de graduação"
    ):

        resultado_detalhes = re.search(
            r"Trabalho de Conclusão de Curso\.\s*"
            r"\((.*?)\)\s*-\s*"
            r"(.*?)\.\s*(?:Coorientador|Orientador):",
            texto,
            re.IGNORECASE
        )

        if resultado_detalhes:

            curso = resultado_detalhes.group(
                1
            ).strip()

            detalhes = resultado_detalhes.group(
                2
            ).strip(" .")

            partes = detalhes.rsplit(
                ",",
                1
            )

            instituicao = partes[0].strip(
                " ."
            )

            if len(partes) == 2:

                financiador_temp = partes[1].strip(
                    " ."
                )

                if financiador_temp:

                    financiador = financiador_temp

    # =====================================
    # INICIAÇÃO CIENTÍFICA
    # =====================================

    elif orientacao["categoria"] == "Iniciação científica":

        resultado_iniciacao = re.search(
            r"Iniciação Científica"
            r"(?:\.\s*\((.*?)\))?"
            r"\s*-\s*(.*?)\.\s*"
            r"(?:Coorientador|Orientador):",
            texto,
            re.IGNORECASE
        )

        if resultado_iniciacao:

            curso_encontrado = resultado_iniciacao.group(
                1
            )

            if curso_encontrado:

                curso = curso_encontrado.strip()

            detalhes = resultado_iniciacao.group(
                2
            ).strip(" .")

            partes = detalhes.rsplit(
                ",",
                1
            )

            instituicao = partes[0].strip(
                " ."
            )

            if len(partes) == 2:

                financiador_temp = partes[1].strip(
                    " ."
                )

                if financiador_temp:

                    financiador = financiador_temp

    # =====================================
    # RESULTADO
    # =====================================

    return {
        "numero":
            orientacao["numero"],

        "pagina_inicio":
            orientacao["pagina_inicio"],

        "categoria":
            orientacao["categoria"],

        "orientando":
            orientando,

        "titulo":
            titulo,

        "ano_conclusao":
            ano_conclusao,

        "curso":
            curso,

        "instituicao":
            instituicao,

        "financiador":
            financiador,

        "funcao":
            funcao,

        "orientador_nome":
            orientador_nome,

        "autores_texto":
            None,

        "autores":
            [],

        "status":
            "Concluída",

        "texto_original":
            texto
    }


def estruturar_orientacoes_concluidas(
    orientacoes
):

    resultado = []

    for orientacao in orientacoes:

        resultado.append(
            estruturar_orientacao_concluida(
                orientacao
            )
        )

    return resultado

# =========================================
# ESTRUTURA ORIENTAÇÃO EM ANDAMENTO
# =========================================

def estruturar_orientacao_andamento(
    orientacao
):

    texto = limpar_texto_artigo(
        orientacao["texto"]
    )

    orientando = None
    titulo = None
    ano_inicio = None
    curso = None
    instituicao = None
    funcao = None
    financiador = None


    # =====================================
    # ANO DE INÍCIO
    # =====================================

    resultado_inicio = re.search(
        r"Início:\s*((?:19|20)\d{2})",
        texto,
        re.IGNORECASE
    )

    if resultado_inicio:

        ano_inicio = int(
            resultado_inicio.group(1)
        )


    # =====================================
    # ORIENTANDO E TÍTULO
    # =====================================

    if resultado_inicio:

        trecho_inicial = texto[
            :resultado_inicio.start()
        ].strip(" .")


        if ". " in trecho_inicial:

            orientando, titulo = trecho_inicial.split(
                ". ",
                1
            )

            orientando = orientando.strip(
                " ."
            )

            titulo = titulo.strip(
                " ."
            )

        else:

            orientando = trecho_inicial


    # =====================================
    # CURSO
    # =====================================

    resultado_curso = re.search(
        r"(?:Dissertação|Tese|Iniciação científica)\s*\((.*?)\)\s*-",
        texto,
        re.IGNORECASE
)

    if resultado_curso:

        curso = resultado_curso.group(
            1
        ).strip()


    # =====================================
    # INSTITUIÇÃO
    # =====================================

    resultado_instituicao = re.search(
        r"(?:Dissertação|Tese)\s*\(.*?\)\s*-\s*(.*?)\.\s*\(",
        texto,
        re.IGNORECASE
    )

    if resultado_instituicao:

        instituicao = resultado_instituicao.group(
            1
        ).strip()


    # =====================================
    # FUNÇÃO
    # =====================================

    resultado_funcao = re.search(
        r"\(([^()]+)\)\.?\s*$",
        texto
    )

    if resultado_funcao:

        funcao = resultado_funcao.group(
            1
        ).strip()


    # =====================================
    # SUPERVISÃO DE PÓS-DOUTORADO
    # =====================================

    if orientacao["categoria"] == "Supervisão de pós-doutorado":

        funcao = "Supervisor"

        resultado_supervisao = re.search(
            r"Início:\s*(?:19|20)\d{2}\.\s*(.*)",
            texto,
            re.IGNORECASE
        )

        if resultado_supervisao:

            detalhes = resultado_supervisao.group(
                1
            ).strip(" .")

            partes = detalhes.split(
                ",",
                1
            )

            if len(partes) >= 1:

                instituicao = partes[0].strip(
                    " ."
                )

            if len(partes) == 2:

                financiador = partes[1].strip(
                    " ."
                )

    # =====================================
    # INICIAÇÃO CIENTÍFICA
    # =====================================

    if orientacao["categoria"] == "Iniciação científica":

        resultado_iniciacao = re.search(
            r"Iniciação científica\s*\((.*?)\)\s*-\s*(.*?)\.\s*\(([^()]+)\)\.?\s*$",
            texto,
            re.IGNORECASE
        )

        if resultado_iniciacao:

            curso = resultado_iniciacao.group(
                1
            ).strip()

            detalhes = resultado_iniciacao.group(
                2
            ).strip()

            funcao = resultado_iniciacao.group(
                3
            ).strip()

            partes = detalhes.split(
                ",",
                1
            )

            if len(partes) >= 1:

                instituicao = partes[0].strip(
                    " ."
                )

            if len(partes) == 2:

                financiador = partes[1].strip(
                    " ."
                )


    # =====================================
    # RESULTADO
    # =====================================

    return {

        "numero":
            orientacao["numero"],

        "pagina_inicio":
            orientacao["pagina_inicio"],

        "categoria":
            orientacao["categoria"],

        "orientando":
            orientando,

        "titulo":
            titulo,

        "ano_inicio":
            ano_inicio,

        "curso":
            curso,

        "instituicao":
            instituicao,

        "funcao":
            funcao,

        "financiador":
            financiador,

        "status":
            "Em andamento",

        "texto_original":
            texto
    }


def estruturar_orientacoes_andamento(
    orientacoes
):

    resultado = []

    for orientacao in orientacoes:

        resultado.append(
            estruturar_orientacao_andamento(
                orientacao
            )
        )

    return resultado

def estruturar_projeto_pesquisa(projeto):

    texto = limpar_texto_artigo(
        projeto["texto"]
    )


    # =====================================
    # TÍTULO
    # =====================================

    posicoes = []

    for marcador in [
        "Projeto certificado",
        "Descrição:",
        "Situação:"
    ]:

        posicao = texto.find(
            marcador
        )

        if posicao != -1:

            posicoes.append(
                posicao
            )


    if posicoes:

        fim_titulo = min(
            posicoes
        )

        titulo = texto[
            :fim_titulo
        ].strip(" .")

    else:

        titulo = texto.strip(" .")


    # =====================================
    # CERTIFICAÇÃO
    # =====================================

    certificacao = None

    resultado_certificacao = re.search(
        r"(Projeto certificado pelo\(a\) coordenador\(a\).*?\d{2}/\d{2}/\d{4}\.)",
        texto,
        re.IGNORECASE
    )

    if resultado_certificacao:

        certificacao = (
            resultado_certificacao
            .group(1)
            .strip()
        )


    # =====================================
    # DESCRIÇÃO
    # =====================================

    descricao = None

    resultado_descricao = re.search(
        r"Descrição:\s*(.*?)(?=\s+Situação:)",
        texto,
        re.IGNORECASE
    )

    if resultado_descricao:

        descricao = (
            resultado_descricao
            .group(1)
            .strip()
        )

        # Trata:
        # Descrição: Descrição: ...

        descricao = re.sub(
            r"^(?:Descrição:\s*)+",
            "",
            descricao,
            flags=re.IGNORECASE
        )

        descricao = descricao.strip(
            " ."
        )


    # =====================================
    # SITUAÇÃO
    # =====================================

    situacao = None

    resultados_situacao = re.findall(
        r"Situação:\s*([^;.]+)",
        texto,
        re.IGNORECASE
    )

    if resultados_situacao:

        situacao = (
            resultados_situacao[-1]
            .strip()
        )


    # =====================================
    # NATUREZA
    # =====================================

    natureza = None

    resultados_natureza = re.findall(
        r"Natureza:\s*([^;.]+)",
        texto,
        re.IGNORECASE
    )

    if resultados_natureza:

        natureza = (
            resultados_natureza[-1]
            .strip()
        )


    # =====================================
    # ALUNOS ENVOLVIDOS
    # =====================================

    alunos_texto = None
    alunos = {}

    resultado_alunos = re.search(
        r"Alunos envolvidos:\s*(.*?)(?=\s+Integrantes:)",
        texto,
        re.IGNORECASE
    )

    if resultado_alunos:

        alunos_texto = (
            resultado_alunos
            .group(1)
            .strip(" .")
        )


        niveis = re.findall(
            r"(Graduação|Especialização|Mestrado acadêmico|Mestrado profissional|Doutorado):\s*\((\d+)\)",
            alunos_texto,
            re.IGNORECASE
        )


        for nivel, quantidade in niveis:

            alunos[
                nivel
            ] = int(
                quantidade
            )


    # =====================================
    # INTEGRANTES
    # =====================================

    integrantes_texto = None
    integrantes = []

    resultado_integrantes = re.search(
        r"Integrantes:\s*(.*?)(?=\s+(?:Financiador\(es\):|Número de produções C,\s*T\s*&\s*A:)|$)",
        texto,
        re.IGNORECASE
    )

    if resultado_integrantes:

        integrantes_texto = (
            resultado_integrantes
            .group(1)
            .strip(" .")
        )


        partes_integrantes = re.split(
            r"\s*/\s*",
            integrantes_texto
        )


        for parte in partes_integrantes:

            parte = parte.strip(
                " ."
            )


            resultado_integrante = re.match(
                r"(.+?)\s*-\s*(Coordenador|Integrante)$",
                parte,
                re.IGNORECASE
            )


            if resultado_integrante:

                integrantes.append({

                    "nome":
                        resultado_integrante
                        .group(1)
                        .strip(),

                    "papel":
                        resultado_integrante
                        .group(2)
                        .strip()
                })


    # =====================================
    # FINANCIADOR
    # =====================================

    financiador_texto = None
    financiador = None
    tipo_financiamento = None

    resultado_financiador = re.search(
        r"Financiador\(es\):\s*(.*?)(?=\s+Número de produções C,\s*T\s*&\s*A:|$)",
        texto,
        re.IGNORECASE
    )

    if resultado_financiador:

        financiador_texto = (
            resultado_financiador
            .group(1)
            .strip(" .")
        )


        if " - " in financiador_texto:

            financiador, tipo_financiamento = (
                financiador_texto.rsplit(
                    " - ",
                    1
                )
            )

            financiador = financiador.strip()
            tipo_financiamento = (
                tipo_financiamento
                .strip(" .")
            )

        else:

            financiador = (
                financiador_texto
            )


    # =====================================
    # NÚMERO DE PRODUÇÕES
    # =====================================

    numero_producoes = None

    resultado_numero_producoes = re.search(
        r"Número de produções C,\s*T\s*&\s*A:\s*(\d+)",
        texto,
        re.IGNORECASE
    )

    if resultado_numero_producoes:

        numero_producoes = int(
            resultado_numero_producoes
            .group(1)
        )


    # =====================================
    # RESULTADO
    # =====================================

    return {

        "numero":
            projeto["numero"],

        "pagina_inicio":
            projeto["pagina_inicio"],

        "periodo":
            projeto["periodo"],

        "ano_inicio":
            projeto["ano_inicio"],

        "ano_fim":
            projeto["ano_fim"],

        "em_andamento":
            projeto["em_andamento"],

        "titulo":
            titulo,

        "descricao":
            descricao,

        "situacao":
            situacao,

        "natureza":
            natureza,

        "certificacao":
            certificacao,

        "alunos_texto":
            alunos_texto,

        "alunos":
            alunos,

        "integrantes_texto":
            integrantes_texto,

        "integrantes":
            integrantes,

        "financiador_texto":
            financiador_texto,

        "financiador":
            financiador,

        "tipo_financiamento":
            tipo_financiamento,

        "numero_producoes":
            numero_producoes,

        "texto_original":
            texto
    }


def estruturar_projetos_pesquisa(
    projetos
):

    resultado = []

    for projeto in projetos:

        resultado.append(
            estruturar_projeto_pesquisa(
                projeto
            )
        )

    return resultado

def estruturar_producao_tecnica(producao):

    texto = limpar_texto_artigo(
        producao["texto"]
    )

    autores_texto = None
    autores = []
    restante = texto

    ano = extrair_ano(
        texto
    )


    # =====================================
    # AUTORES
    # =====================================

    if " . " in texto:

        autores_texto, restante = texto.split(
            " . ",
            1
        )

    else:

        # Exemplo:
        # MACHADO JUNIOR, C.. Avaliação...

        formato_duplo_ponto = re.match(
            r"^(.+?\.)\.\s+(.+)$",
            texto
        )

        if formato_duplo_ponto:

            autores_texto = formato_duplo_ponto.group(
                1
            )

            restante = formato_duplo_ponto.group(
                2
            )

        else:

            # Exemplo:
            # MACHADO, CELSO. PIPE Empreendedor...

            formato_autor_simples = re.match(
                r"^([^.;]+,\s*[^.]+)\.\s+(.+)$",
                texto
            )

            if formato_autor_simples:

                autores_texto = formato_autor_simples.group(
                    1
                )

                restante = formato_autor_simples.group(
                    2
                )


    if autores_texto is not None:

        autores_texto = autores_texto.strip()

        autores = [
            autor.strip()
            for autor in autores_texto.split(";")
            if autor.strip()
        ]


    # =====================================
    # REMOVE O ANO FINAL DO TÍTULO
    # =====================================

    titulo = restante.strip()


    if ano is not None:

        titulo = re.sub(
            rf"\.?\s*{ano}\.\s*$",
            "",
            titulo
        )


    titulo = titulo.strip(" .")


    return {
        "numero":
            producao["numero"],

        "pagina_inicio":
            producao["pagina_inicio"],

        "subcategoria":
            producao["subcategoria"],

        "autores_texto":
            autores_texto,

        "autores":
            autores,

        "titulo":
            titulo,

        "ano":
            ano,

        "texto_original":
            texto
    }


def estruturar_producoes_tecnicas(
    producoes
):

    resultado = []

    for producao in producoes:

        resultado.append(
            estruturar_producao_tecnica(
                producao
            )
        )

    return resultado

def estruturar_resumo_anais(resumo):

    texto = limpar_texto_artigo(
        resumo["texto"]
    )

    autores_texto = None
    autores = []
    titulo = None

    ano = extrair_ano(
        texto
    )


    # =====================================
    # AUTORES
    # =====================================

    if " . " in texto:

        autores_texto, restante = texto.split(
            " . ",
            1
        )

    else:

        # Trata casos como:
        # MACHADO JUNIOR, C.. A aprendizagem...

        formato_duplo_ponto = re.match(
            r"^(.+?\.)\.\s+(.+)$",
            texto
        )

        if formato_duplo_ponto:

            autores_texto = formato_duplo_ponto.group(
                1
            )

            restante = formato_duplo_ponto.group(
                2
            )

        else:

            restante = texto


    if autores_texto is not None:

        autores_texto = autores_texto.strip()

        autores = [
            autor.strip()
            for autor in autores_texto.split(";")
            if autor.strip()
        ]


    # =====================================
    # TÍTULO
    # =====================================

    partes = re.split(
        r"\s+In:\s*",
        restante,
        maxsplit=1,
        flags=re.IGNORECASE
    )


    if len(partes) > 1:

        titulo = partes[0].strip(" .")

    else:

        titulo = restante.strip(" .")


    return {
        "bloco": resumo["bloco"],
        "numero": resumo["numero"],
        "pagina_inicio": resumo["pagina_inicio"],
        "autores_texto": autores_texto,
        "autores": autores,
        "titulo": titulo,
        "ano": ano,
        "texto_original": texto
    }


def estruturar_resumos_anais(
    resumos
):

    resumos_estruturados = []

    for resumo in resumos:

        resultado = estruturar_resumo_anais(
            resumo
        )

        resumos_estruturados.append(
            resultado
        )

    return resumos_estruturados

def estruturar_resumo_expandido(resumo):

    texto = limpar_texto_artigo(
        resumo["texto"]
    )

    autores_texto = None
    autores = []
    titulo = None

    ano = extrair_ano(
        texto
    )


    # =====================================
    # AUTORES
    # =====================================

    if " . " in texto:

        autores_texto, restante = texto.split(
            " . ",
            1
        )

        autores_texto = autores_texto.strip()

        autores = [
            autor.strip()
            for autor in autores_texto.split(";")
            if autor.strip()
        ]

    else:

        restante = texto


    # =====================================
    # TÍTULO
    # =====================================

    partes = re.split(
        r"\s+In:\s*",
        restante,
        maxsplit=1,
        flags=re.IGNORECASE
    )


    if len(partes) > 1:

        titulo = partes[0].strip(" .")

    else:

        titulo = restante.strip(" .")


    return {
        "bloco": resumo["bloco"],
        "numero": resumo["numero"],
        "pagina_inicio": resumo["pagina_inicio"],
        "autores_texto": autores_texto,
        "autores": autores,
        "titulo": titulo,
        "ano": ano,
        "texto_original": texto
    }


def estruturar_resumos_expandidos(
    resumos
):

    resumos_estruturados = []

    for resumo in resumos:

        resultado = estruturar_resumo_expandido(
            resumo
        )

        resumos_estruturados.append(
            resultado
        )

    return resumos_estruturados

def estruturar_trabalho_anais(trabalho):

    texto = limpar_texto_artigo(
        trabalho["texto"]
    )

    autores_texto = None
    autores = []
    titulo = None

    ano = extrair_ano(
        texto
    )


    # =====================================
    # AUTORES
    # =====================================

    if " . " in texto:

        autores_texto, restante = texto.split(
            " . ",
            1
        )

        autores_texto = autores_texto.strip()

        autores = [
            autor.strip()
            for autor in autores_texto.split(";")
            if autor.strip()
        ]

    else:

        restante = texto


    # =====================================
    # TÍTULO
    # =====================================

    partes = re.split(
        r"\s+In:\s*",
        restante,
        maxsplit=1,
        flags=re.IGNORECASE
    )


    if len(partes) > 1:

        titulo = partes[0].strip(" .")

    else:

        titulo = restante.strip(" .")


    return {
        "bloco": trabalho["bloco"],
        "numero": trabalho["numero"],
        "pagina_inicio": trabalho["pagina_inicio"],
        "autores_texto": autores_texto,
        "autores": autores,
        "titulo": titulo,
        "ano": ano,
        "texto_original": texto
    }


def estruturar_trabalhos_anais(
    trabalhos
):

    trabalhos_estruturados = []

    for trabalho in trabalhos:

        resultado = estruturar_trabalho_anais(
            trabalho
        )

        trabalhos_estruturados.append(
            resultado
        )

    return trabalhos_estruturados

def estruturar_capitulo(capitulo):

    texto = limpar_texto_artigo(
        capitulo["texto"]
    )

    autores_texto = None
    autores = []
    titulo = None

    ano = extrair_ano(
        texto
    )


    # =====================================
    # AUTORES
    # =====================================

    if " . " in texto:

        autores_texto, restante = texto.split(
            " . ",
            1
        )

        autores_texto = autores_texto.strip()

        autores = [
            autor.strip()
            for autor in autores_texto.split(";")
            if autor.strip()
        ]

    else:

        restante = texto


    # =====================================
    # TÍTULO
    # Tudo antes de "In:"
    # =====================================

    partes = re.split(
        r"\s+In:\s*",
        restante,
        maxsplit=1,
        flags=re.IGNORECASE
    )


    if len(partes) > 1:

        titulo = partes[0].strip(" .")

    else:

        titulo = restante.strip(" .")


    return {
        "bloco": capitulo["bloco"],
        "numero": capitulo["numero"],
        "pagina_inicio": capitulo["pagina_inicio"],
        "autores_texto": autores_texto,
        "autores": autores,
        "titulo": titulo,
        "ano": ano,
        "texto_original": texto
    }


def estruturar_capitulos(capitulos):

    capitulos_estruturados = []

    for capitulo in capitulos:

        resultado = estruturar_capitulo(
            capitulo
        )

        capitulos_estruturados.append(
            resultado
        )

    return capitulos_estruturados

# =========================================
# ESTRUTURA UM LIVRO
# =========================================

def estruturar_livro(livro):

    texto = limpar_texto_artigo(
        livro["texto"]
    )

    autores_texto = None

    autores = []

    titulo = None

    ano = extrair_ano(
        texto
    )


    # =====================================
    # SEPARA AUTORES
    # =====================================

    if " . " in texto:

        autores_texto, restante = texto.split(
            " . ",
            1
        )

        autores_texto = autores_texto.strip()

        autores = [

            autor.strip()

            for autor in autores_texto.split(";")

            if autor.strip()

        ]

    else:

        restante = texto


    # =====================================
    # SEPARA TÍTULO
    #
    # Exemplo:
    # Título do Livro. 1. ed. ...
    # =====================================

    resultado_titulo = re.split(
        r"\.\s+\d+\.\s*ed\.",
        restante,
        maxsplit=1,
        flags=re.IGNORECASE
    )


    if len(resultado_titulo) > 1:

        titulo = resultado_titulo[0].strip(
            " ."
        )

    else:

        titulo = restante.strip(
            " ."
        )


    return {

        "bloco":
            livro["bloco"],

        "numero":
            livro["numero"],

        "pagina_inicio":
            livro["pagina_inicio"],

        "autores_texto":
            autores_texto,

        "autores":
            autores,

        "titulo":
            titulo,

        "ano":
            ano,

        "texto_original":
            texto

    }


# =========================================
# ESTRUTURA TODOS OS LIVROS
# =========================================

def estruturar_livros(livros):

    livros_estruturados = []


    for livro in livros:

        resultado = estruturar_livro(
            livro
        )

        livros_estruturados.append(
            resultado
        )


    return livros_estruturados

# =========================================
# LIMPA TEXTO DO ARTIGO
# =========================================

def limpar_texto_artigo(texto):

    texto = texto.strip()

    # Corrige palavras quebradas no fim
    # de uma linha do PDF:
    #
    # econômico- financeiro
    #
    # vira:
    #
    # econômico-financeiro

    texto = re.sub(
        r"(?<=\w)-\s+(?=\w)",
        "-",
        texto
    )

    # Remove espaços duplicados

    texto = re.sub(
        r"\s+",
        " ",
        texto
    )

    return texto.strip()


# =========================================
# EXTRAI DOI
# =========================================

def extrair_doi(texto):

    padrao = re.search(
        r"10\.\d{4,9}/[-._;()/:A-Z0-9]+",
        texto,
        re.IGNORECASE
    )

    if not padrao:
        return None


    doi = padrao.group(0)

    doi = doi.rstrip(
        ".,;)"
    )


    return doi


# =========================================
# EXTRAI ISSN
# =========================================

def extrair_issn(texto):

    # =====================================
    # FORMATO EXPLÍCITO:
    # ISSN: 2177-8396
    # =====================================

    resultado = re.search(
        r"ISSN:\s*(\d{4}-\d{3}[\dXx])",
        texto,
        re.IGNORECASE
    )

    if resultado:

        return resultado.group(1)


    # =====================================
    # FORMATO NORMAL
    # =====================================

    resultado = re.search(
        r"\b\d{4}-\d{3}[\dXx]\b",
        texto
    )

    if resultado:

        return resultado.group(0)


    return None


# =========================================
# EXTRAI ANO
# =========================================

def extrair_ano(texto):

    anos = re.findall(
        r"(?:19|20)\d{2}",
        texto
    )

    if len(anos) == 0:
        return None

    return int(anos[-1])


# =========================================
# EXTRAI VOLUME
# =========================================

def extrair_volume(texto):

    resultado = re.search(
        r"\bv\.\s*([^,]+)",
        texto,
        re.IGNORECASE
    )


    if not resultado:
        return None


    return resultado.group(1).strip()


# =========================================
# EXTRAI NÚMERO
# =========================================

def extrair_numero_periodico(texto):

    resultado = re.search(
        r"\bn\.\s*([^,]+)",
        texto,
        re.IGNORECASE
    )


    if not resultado:
        return None


    return resultado.group(1).strip()


# =========================================
# EXTRAI PÁGINAS
# =========================================

def extrair_paginas(texto):

    resultado = re.search(
        r"\bp\.\s*([^,]+)",
        texto,
        re.IGNORECASE
    )


    if not resultado:
        return None


    return resultado.group(1).strip()


# =========================================
# ESTRUTURA UM ARTIGO
# =========================================

def estruturar_artigo(artigo):

    texto = limpar_texto_artigo(
        artigo["texto"]
    )


    autores_texto = None

    autores = []

    titulo = None

    periodico = None


    # =====================================
    # SEPARA AUTORES
    # =====================================
    #
    # Exemplo:
    #
    # MACHADO, C. ; SILVA, J. .
    # Título do artigo...
    #

    if " . " in texto:

        autores_texto, restante = texto.split(
            " . ",
            1
        )


        autores_texto = autores_texto.strip()


        # Separa os autores pelo ";"

        autores = [

            autor.strip()

            for autor in autores_texto.split(";")

            if autor.strip()

        ]


    else:

        restante = texto


    # =====================================
    # SEPARA TÍTULO
    # =====================================
    #
    # Depois dos autores normalmente temos:
    #
    # Título do artigo. Nome do Periódico...

    if ". " in restante:

        titulo, detalhes = restante.split(
            ". ",
            1
        )


        titulo = titulo.strip(
            " ."
        )


    else:

        detalhes = restante


    # =====================================
    # TENTA IDENTIFICAR O PERIÓDICO
    # =====================================
    #
    # Exemplo:
    #
    # Metodologias e Aprendizado,
    # v. 9,
    # p. 172-179,
    # 2026.
    #

    periodico = detalhes


    marcadores = [

        r",\s*v\.",

        r",\s*n\.",

        r",\s*p\."

    ]


    for marcador in marcadores:

        partes = re.split(
            marcador,
            periodico,
            maxsplit=1,
            flags=re.IGNORECASE
        )


        if len(partes) > 1:

            periodico = partes[0]

            #hdwjggdhwhzognrusnf

            break


    # Caso não tenha volume/número/página,
    # tenta retirar o ano final.

    periodico = re.sub(
        r",?\s*(?:19|20)\d{2}\.?\s*$",
        "",
        periodico
    )


    periodico = periodico.strip(
        " ,."
    )

        # =====================================
    # CORRIGE FORMATO ESPECIAL COM "ISSN:"
    # =====================================

    if "ISSN:" in texto.upper():

        partes_issn = re.split(
            r"ISSN:\s*\d{4}-\d{3}[\dXx]",
            texto,
            maxsplit=1,
            flags=re.IGNORECASE
        )

        if len(partes_issn) > 1:

            trecho = partes_issn[1].strip()

            # Remove número solto grudado após o ISSN
            # Exemplo:
            # 2177-83964DESENVOLVIMENTO...
            #          ^
            trecho = re.sub(
                r"^\d+(?=[A-ZÁÉÍÓÚÂÊÔÃÕÇ])",
                "",
                trecho
            )

            formato_especial = re.match(
                r"(.+?)\.\s+(.+?),\s*v\.",
                trecho,
                re.IGNORECASE
            )

            if formato_especial:

                titulo = formato_especial.group(1).strip()

                periodico = formato_especial.group(2).strip()


    # =====================================
    # RESULTADO
    # =====================================

    resultado = {

        "bloco":
            artigo["bloco"],

        "numero":
            artigo["numero"],

        "pagina_inicio":
            artigo["pagina_inicio"],

        "autores_texto":
            autores_texto,

        "autores":
            autores,

        "titulo":
            titulo,

        "periodico":
            periodico,

        "ano":
            extrair_ano(texto),

        "volume":
            extrair_volume(texto),

        "numero_periodico":
            extrair_numero_periodico(texto),

        "paginas":
            extrair_paginas(texto),

        "doi":
            extrair_doi(texto),

        "issn":
            extrair_issn(texto),

        "texto_original":
            texto

    }


    return resultado


# =========================================
# ESTRUTURA TODOS OS ARTIGOS
# =========================================

def estruturar_artigos(artigos):

    artigos_estruturados = []


    for artigo in artigos:

        resultado = estruturar_artigo(
            artigo
        )


        artigos_estruturados.append(
            resultado
        )


    return artigos_estruturados


# =========================================
# EXTRAI O PDF
# =========================================

def extrair_pdf(caminho_pdf):


    # =====================================
    # CONFERE SE O ARQUIVO EXISTE
    # =====================================

    if not os.path.exists(
        caminho_pdf
    ):

        retornar_erro(
            "Arquivo PDF não encontrado."
        )


    # =====================================
    # ABRE O PDF
    # =====================================

    try:

        documento = pymupdf.open(
            caminho_pdf
        )


    except Exception as erro:

        retornar_erro(

            f"Não foi possível abrir o PDF: {erro}"

        )


    try:

        paginas = []

        textos = []


        # =================================
        # PERCORRE TODAS AS PÁGINAS
        # =================================

        for numero, pagina in enumerate(
            documento,
            start=1
        ):

            texto = pagina.get_text(
                "text",
                sort=True
            )


            texto = normalizar_texto(
                texto
            )


            paginas.append({

                "pagina":
                    numero,

                "texto":
                    texto

            })


            textos.append(
                texto
            )


        # =================================
        # JUNTA TODO O TEXTO
        # =================================

        texto_completo = "\n\n".join(
            textos
        )


        # =================================
        # DETECTA AS SEÇÕES
        # =================================

        secoes_detectadas = detectar_secoes(
            paginas
        )


        # =================================
        # SEPARA OS BLOCOS DAS SEÇÕES
        # =================================

        blocos_secoes = extrair_blocos_secoes(
            paginas
        )

        artigos = extrair_artigos(
            blocos_secoes
        )

        artigos_estruturados = estruturar_artigos(
            artigos
        )

        livros = extrair_livros(
            blocos_secoes
        )

        capitulos = extrair_capitulos(
            blocos_secoes
        )

        capitulos_estruturados = estruturar_capitulos(
            capitulos
        )

        livros_estruturados = estruturar_livros(
            livros
            )

        trabalhos_anais = extrair_trabalhos_anais(
            blocos_secoes
        )

        trabalhos_anais_estruturados = estruturar_trabalhos_anais(
            trabalhos_anais
        )

        resumos_expandidos = extrair_resumos_expandidos(
            blocos_secoes
        )

        resumos_expandidos_estruturados = estruturar_resumos_expandidos(
            resumos_expandidos
        )

        resumos_anais = extrair_resumos_anais(
            blocos_secoes
        )

        resumos_anais_estruturados = estruturar_resumos_anais(
            resumos_anais
        )

        producao_tecnica = extrair_producao_tecnica(
            blocos_secoes
        )

        producao_tecnica_estruturada = estruturar_producoes_tecnicas(
            producao_tecnica
        )

        projetos_pesquisa = extrair_projetos_pesquisa(
            blocos_secoes
        )

        projetos_pesquisa_estruturados = estruturar_projetos_pesquisa(
            projetos_pesquisa
        )

        orientacoes_andamento_dissertacao = (
            extrair_orientacoes_andamento_categoria(
                blocos_secoes,
                "Dissertação de mestrado"
            )
        )

        orientacoes_andamento_dissertacao_estruturadas = (
            estruturar_orientacoes_andamento(
                orientacoes_andamento_dissertacao
            )
        )

        orientacoes_andamento_tese = (
            extrair_orientacoes_andamento_categoria(
                blocos_secoes,
                "Tese de doutorado"
            )
        )

        orientacoes_andamento_tese_estruturadas = (
            estruturar_orientacoes_andamento(
                orientacoes_andamento_tese
            )
        )


        orientacoes_andamento_pos_doutorado = (
            extrair_orientacoes_andamento_categoria(
                blocos_secoes,
                "Supervisão de pós-doutorado"
            )
        )

        orientacoes_andamento_pos_doutorado_estruturadas = (
            estruturar_orientacoes_andamento(
                orientacoes_andamento_pos_doutorado
            )
        )


        orientacoes_andamento_iniciacao = (
            extrair_orientacoes_andamento_categoria(
                blocos_secoes,
                "Iniciação científica"
            )
        )

        orientacoes_andamento_iniciacao_estruturadas = (
            estruturar_orientacoes_andamento(
                orientacoes_andamento_iniciacao
            )
        )

        # =================================
        # ORIENTAÇÕES CONCLUÍDAS
        # =================================

        orientacoes_concluidas_dissertacao = (
            extrair_orientacoes_concluidas_categoria(
                blocos_secoes,
                "Dissertação de mestrado"
            )
        )

        orientacoes_concluidas_dissertacao_estruturadas = (
            estruturar_orientacoes_concluidas(
                orientacoes_concluidas_dissertacao
            )
        )

        orientacoes_concluidas_tese = (
            extrair_orientacoes_concluidas_categoria(
                blocos_secoes,
                "Tese de doutorado"
            )
        )

        orientacoes_concluidas_tese_estruturadas = (
            estruturar_orientacoes_concluidas(
                orientacoes_concluidas_tese
            )
        )

        orientacoes_concluidas_tcc = (
            extrair_orientacoes_concluidas_categoria(
                blocos_secoes,
                "Trabalho de conclusão de curso de graduação"
            )
        )

        orientacoes_concluidas_tcc_estruturadas = (
            estruturar_orientacoes_concluidas(
                orientacoes_concluidas_tcc
            )
        )

        orientacoes_concluidas_iniciacao = (
            extrair_orientacoes_concluidas_categoria(
                blocos_secoes,
                "Iniciação científica"
            )
        )

        orientacoes_concluidas_iniciacao_estruturadas = (
            estruturar_orientacoes_concluidas(
                orientacoes_concluidas_iniciacao
            )
        )

        orientacoes_concluidas_produto_tecnologico = (
            extrair_orientacoes_concluidas_categoria(
                blocos_secoes,
                "Produto tecnológico"
            )
        )

        orientacoes_concluidas_produto_tecnologico_estruturadas = (
            estruturar_orientacoes_concluidas(
                orientacoes_concluidas_produto_tecnologico
            )
        )

        orientacoes_concluidas_estruturadas = (
            orientacoes_concluidas_dissertacao_estruturadas
            + orientacoes_concluidas_tese_estruturadas
            + orientacoes_concluidas_tcc_estruturadas
            + orientacoes_concluidas_iniciacao_estruturadas
            + orientacoes_concluidas_produto_tecnologico_estruturadas
        )


        # =================================
        # RESULTADO
        # =================================

        resultado = {

            "sucesso":
                True,

            "paginas":
                len(documento),

            "caracteres":
                len(texto_completo),

            "quantidade_secoes":
                len(secoes_detectadas),

            "secoes_detectadas":
                secoes_detectadas,

            "blocos_secoes":
                blocos_secoes,

            "quantidade_artigos":
                len(artigos),

            "artigos":
                artigos,

            "quantidade_livros":
                len(livros),

            "livros":
                livros,

            "quantidade_livros_estruturados":
                len(livros_estruturados),

            "livros_estruturados":
                livros_estruturados,

            "quantidade_capitulos":
                len(capitulos),

            "capitulos":
                capitulos,
    
            "quantidade_capitulos_estruturados":
                len(capitulos_estruturados),

            "quantidade_artigos_estruturados":
                len(artigos_estruturados),

            "quantidade_trabalhos_anais":
                len(trabalhos_anais),
            
            "quantidade_trabalhos_anais_estruturados":
                len(trabalhos_anais_estruturados),

            "quantidade_resumos_expandidos":
                len(resumos_expandidos),
            
            "quantidade_resumos_expandidos_estruturados":
                len(resumos_expandidos_estruturados),
            
            "capitulos_estruturados":
                capitulos_estruturados,

            "artigos_estruturados":
                artigos_estruturados,

            "trabalhos_anais":
                trabalhos_anais,

            "trabalhos_anais_estruturados":
                trabalhos_anais_estruturados,

            "resumos_expandidos":
                resumos_expandidos,

            "resumos_expandidos_estruturados":
                resumos_expandidos_estruturados,

            "quantidade_resumos_anais":
                len(resumos_anais),

            "resumos_anais":
                resumos_anais,

            "quantidade_resumos_anais_estruturados":
                len(resumos_anais_estruturados),

            "resumos_anais_estruturados":
                resumos_anais_estruturados,

            "quantidade_producao_tecnica":
                len(producao_tecnica),

            "producao_tecnica":
                producao_tecnica,

            "quantidade_producao_tecnica_estruturada":
                len(producao_tecnica_estruturada),

            "producao_tecnica_estruturada":
                producao_tecnica_estruturada,

            "quantidade_projetos_pesquisa":
                len(projetos_pesquisa),

            "projetos_pesquisa":
                projetos_pesquisa,

            "quantidade_projetos_pesquisa_estruturados":
                len(projetos_pesquisa_estruturados),

            "projetos_pesquisa_estruturados":
                projetos_pesquisa_estruturados,

            "quantidade_orientacoes_andamento_dissertacao":
                len(orientacoes_andamento_dissertacao),

            "orientacoes_andamento_dissertacao":
                orientacoes_andamento_dissertacao,

            "quantidade_orientacoes_andamento_dissertacao_estruturadas":
                len(orientacoes_andamento_dissertacao_estruturadas),

            "orientacoes_andamento_dissertacao_estruturadas":
                orientacoes_andamento_dissertacao_estruturadas,

            "quantidade_orientacoes_andamento_tese":
                len(orientacoes_andamento_tese),

            "orientacoes_andamento_tese":
                orientacoes_andamento_tese,

            "quantidade_orientacoes_andamento_tese_estruturadas":
                len(orientacoes_andamento_tese_estruturadas),

            "orientacoes_andamento_tese_estruturadas":
                orientacoes_andamento_tese_estruturadas,


            "quantidade_orientacoes_andamento_pos_doutorado":
                len(orientacoes_andamento_pos_doutorado),

            "orientacoes_andamento_pos_doutorado":
                orientacoes_andamento_pos_doutorado,

            "quantidade_orientacoes_andamento_pos_doutorado_estruturadas":
                len(orientacoes_andamento_pos_doutorado_estruturadas),

            "orientacoes_andamento_pos_doutorado_estruturadas":
                orientacoes_andamento_pos_doutorado_estruturadas,


            "quantidade_orientacoes_andamento_iniciacao":
                len(orientacoes_andamento_iniciacao),

            "orientacoes_andamento_iniciacao":
                orientacoes_andamento_iniciacao,

            "quantidade_orientacoes_andamento_iniciacao_estruturadas":
                len(orientacoes_andamento_iniciacao_estruturadas),

            "orientacoes_andamento_iniciacao_estruturadas":
                orientacoes_andamento_iniciacao_estruturadas,

            "quantidade_orientacoes_concluidas_dissertacao":
                len(orientacoes_concluidas_dissertacao),

            "orientacoes_concluidas_dissertacao":
                orientacoes_concluidas_dissertacao,

            "quantidade_orientacoes_concluidas_dissertacao_estruturadas":
                len(orientacoes_concluidas_dissertacao_estruturadas),

            "orientacoes_concluidas_dissertacao_estruturadas":
                orientacoes_concluidas_dissertacao_estruturadas,

            "quantidade_orientacoes_concluidas_tese":
                len(orientacoes_concluidas_tese),

            "orientacoes_concluidas_tese":
                orientacoes_concluidas_tese,

            "quantidade_orientacoes_concluidas_tese_estruturadas":
                len(orientacoes_concluidas_tese_estruturadas),

            "orientacoes_concluidas_tese_estruturadas":
                orientacoes_concluidas_tese_estruturadas,

            "quantidade_orientacoes_concluidas_tcc":
                len(orientacoes_concluidas_tcc),

            "orientacoes_concluidas_tcc":
                orientacoes_concluidas_tcc,

            "quantidade_orientacoes_concluidas_tcc_estruturadas":
                len(orientacoes_concluidas_tcc_estruturadas),

            "orientacoes_concluidas_tcc_estruturadas":
                orientacoes_concluidas_tcc_estruturadas,

            "quantidade_orientacoes_concluidas_iniciacao":
                len(orientacoes_concluidas_iniciacao),

            "orientacoes_concluidas_iniciacao":
                orientacoes_concluidas_iniciacao,

            "quantidade_orientacoes_concluidas_iniciacao_estruturadas":
                len(orientacoes_concluidas_iniciacao_estruturadas),

            "orientacoes_concluidas_iniciacao_estruturadas":
                orientacoes_concluidas_iniciacao_estruturadas,

            "quantidade_orientacoes_concluidas_produto_tecnologico":
                len(orientacoes_concluidas_produto_tecnologico),

            "orientacoes_concluidas_produto_tecnologico":
                orientacoes_concluidas_produto_tecnologico,

            "quantidade_orientacoes_concluidas_produto_tecnologico_estruturadas":
                len(orientacoes_concluidas_produto_tecnologico_estruturadas),

            "orientacoes_concluidas_produto_tecnologico_estruturadas":
                orientacoes_concluidas_produto_tecnologico_estruturadas,

            "quantidade_orientacoes_concluidas_total":
                len(orientacoes_concluidas_estruturadas),

            "orientacoes_concluidas_estruturadas":
                orientacoes_concluidas_estruturadas,

            "texto":
                texto_completo

        }


        # =================================
        # RETORNA JSON
        # =================================

        print(

            json.dumps(

                resultado,

                ensure_ascii=False

            )

        )


    except Exception as erro:

        retornar_erro(

            f"Erro durante a extração: {erro}"

        )


    finally:

        documento.close()


# =========================================
# EXECUÇÃO DO PROGRAMA
# =========================================

if __name__ == "__main__":


    # Precisa receber:
    #
    # python extrator_pdf.py arquivo.pdf

    if len(sys.argv) < 2:

        retornar_erro(
            "Informe o caminho do PDF."
        )


    caminho_pdf = sys.argv[1]


    extrair_pdf(
        caminho_pdf
    )