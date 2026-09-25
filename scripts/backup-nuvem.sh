#!/usr/bin/env bash
# ==========================================================================
# CTS Convênios — cópia dos backups para a nuvem (Cloudflare R2 ou qualquer armazenamento S3).
#
# Os arquivos são CRIPTOGRAFADOS aqui, no servidor, antes de sair (AES-256): o bucket nunca vê
# dados pessoais em texto aberto. O backup.sh chama este script sozinho; use direto para:
#
#   ./scripts/backup-nuvem.sh listar                       # o que existe na nuvem
#   ./scripts/backup-nuvem.sh baixar banco-AAAA-MM-DD_HHMMSS.sql.gz.enc
#                                                          # baixa, descriptografa e deixa em ./backups/restaurar/
#   ./scripts/backup-nuvem.sh enviar arquivo...            # (usado pelo backup.sh)
#   ./scripts/backup-nuvem.sh limpar                       # apaga da nuvem o que passou da retenção
#
# Configuração no .env.production (veja .env.production.example). Sem ela, "enviar" só avisa
# e não faz nada: o backup continua só local.
#
# GUARDE A SENHA (BACKUP_SENHA) FORA DO SERVIDOR (gerenciador de senhas). Sem ela os backups
# da nuvem não podem ser lidos por ninguém, nem por nós.
# ==========================================================================
set -euo pipefail
export MSYS_NO_PATHCONV=1   # Git Bash (Windows): não converter caminhos dos containers

cd "$(dirname "$0")/.."

# Valor do .env.production (sem exportar segredos para o ambiente); variável de ambiente tem prioridade.
ler() { [ -f .env.production ] && { grep -E "^$1=" .env.production | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' || true; } || true; }

ENDPOINT="${BACKUP_S3_ENDPOINT:-$(ler BACKUP_S3_ENDPOINT)}"
BUCKET="${BACKUP_S3_BUCKET:-$(ler BACKUP_S3_BUCKET)}"
CHAVE_ID="${BACKUP_S3_ACCESS_KEY_ID:-$(ler BACKUP_S3_ACCESS_KEY_ID)}"
CHAVE_SEGREDO="${BACKUP_S3_SECRET_ACCESS_KEY:-$(ler BACKUP_S3_SECRET_ACCESS_KEY)}"
SENHA="${BACKUP_SENHA:-$(ler BACKUP_SENHA)}"
PROVEDOR="${BACKUP_S3_PROVIDER:-$(ler BACKUP_S3_PROVIDER)}"; PROVEDOR="${PROVEDOR:-Cloudflare}"
REGIAO="${BACKUP_S3_REGION:-$(ler BACKUP_S3_REGION)}"; REGIAO="${REGIAO:-auto}"
PREFIXO="${BACKUP_S3_PREFIXO:-$(ler BACKUP_S3_PREFIXO)}"; PREFIXO="${PREFIXO:-cts-convenios}"
RETENCAO_DIAS="${BACKUP_NUVEM_RETENCAO_DIAS:-$(ler BACKUP_NUVEM_RETENCAO_DIAS)}"; RETENCAO_DIAS="${RETENCAO_DIAS:-30}"
BACKUP_DIR="${BACKUP_DIR:-./backups}"
IMAGEM_RCLONE="rclone/rclone:1"

COMANDO="${1:-}"
shift || true

# Nada configurado = backup só local (não é erro). Configuração pela metade É erro.
if [ -z "$ENDPOINT$BUCKET$CHAVE_ID$CHAVE_SEGREDO" ]; then
    if [ "$COMANDO" = "enviar" ] || [ "$COMANDO" = "limpar" ]; then
        echo "AVISO: backup na nuvem não configurado (BACKUP_S3_* no .env.production). Os backups ficam só neste servidor."
        exit 0
    fi
    echo "ERRO: backup na nuvem não configurado (BACKUP_S3_* no .env.production)." >&2
    exit 1
fi

for variavel in ENDPOINT BUCKET CHAVE_ID CHAVE_SEGREDO; do
    [ -n "${!variavel}" ] || { echo "ERRO: configuração da nuvem incompleta: falta ${variavel} (BACKUP_S3_*)." >&2; exit 1; }
done
[ -n "$SENHA" ] || { echo "ERRO: falta BACKUP_SENHA. Não enviamos backups sem criptografia." >&2; exit 1; }
[ "${#SENHA}" -ge 16 ] || { echo "ERRO: BACKUP_SENHA precisa ter ao menos 16 caracteres (gere com: openssl rand -base64 32)." >&2; exit 1; }
command -v openssl > /dev/null || { echo "ERRO: openssl não encontrado." >&2; exit 1; }

DESTINO="nuvem:${BUCKET}/${PREFIXO}"
TEMP="$BACKUP_DIR/.nuvem-tmp"
trap 'rm -rf "$TEMP"' EXIT

# rclone dentro de um container, configurado só por variáveis de ambiente (nenhum arquivo com segredo).
# Os segredos vão pelo NOME da variável, não pelo valor na linha de comando.
rclone() {
    RCLONE_CONFIG_NUVEM_ACCESS_KEY_ID="$CHAVE_ID" RCLONE_CONFIG_NUVEM_SECRET_ACCESS_KEY="$CHAVE_SEGREDO" \
    docker run --rm \
        -e RCLONE_LOG_LEVEL=ERROR \
        -e RCLONE_RETRIES=2 \
        -e RCLONE_LOW_LEVEL_RETRIES=3 \
        -e RCLONE_CONTIMEOUT=20s \
        -e RCLONE_TIMEOUT=120s \
        -e RCLONE_CONFIG_NUVEM_TYPE=s3 \
        -e RCLONE_CONFIG_NUVEM_PROVIDER="$PROVEDOR" \
        -e RCLONE_CONFIG_NUVEM_ENDPOINT="$ENDPOINT" \
        -e RCLONE_CONFIG_NUVEM_REGION="$REGIAO" \
        -e RCLONE_CONFIG_NUVEM_NO_CHECK_BUCKET=true \
        -e RCLONE_CONFIG_NUVEM_ACCESS_KEY_ID \
        -e RCLONE_CONFIG_NUVEM_SECRET_ACCESS_KEY \
        -v "$(cd "$TEMP" && pwd):/tmp-nuvem" \
        "$IMAGEM_RCLONE" "$@"
}

# AES-256 com chave derivada da senha (PBKDF2). A senha vai por variável de ambiente, não pela linha de comando.
criptografar() { BACKUP_SENHA="$SENHA" openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt -in "$1" -out "$2" -pass env:BACKUP_SENHA; }
descriptografar() { BACKUP_SENHA="$SENHA" openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -in "$1" -pass env:BACKUP_SENHA; }

mkdir -p "$TEMP"

case "$COMANDO" in
    enviar)
        [ "$#" -gt 0 ] || { echo "Uso: $0 enviar arquivo..." >&2; exit 1; }
        rm -rf "${TEMP:?}/envio" && mkdir -p "$TEMP/envio"
        for arquivo in "$@"; do
            [ -s "$arquivo" ] || { echo "ERRO: $arquivo não existe ou está vazio." >&2; exit 1; }
            echo "Criptografando $(basename "$arquivo")..."
            criptografar "$arquivo" "$TEMP/envio/$(basename "$arquivo").enc"
        done
        echo "Enviando para ${PROVEDOR} (${BUCKET}/${PREFIXO})..."
        rclone copy /tmp-nuvem/envio "$DESTINO/" --checksum --stats-one-line
        # Confere que cada arquivo chegou com o mesmo tamanho e conteúdo.
        rclone check /tmp-nuvem/envio "$DESTINO/" --one-way
        echo "Enviado e conferido: $(for a in "$@"; do printf '%s.enc ' "$(basename "$a")"; done)"
        ;;

    listar)
        rclone lsl "$DESTINO/"
        ;;

    baixar)
        NOME="${1:-}"
        [ -n "$NOME" ] || { echo "Uso: $0 baixar nome-do-arquivo.enc (veja: $0 listar)" >&2; exit 1; }
        case "$NOME" in *.enc) ;; *) echo "ERRO: informe o nome com .enc, como aparece em '$0 listar'." >&2; exit 1 ;; esac
        rm -rf "${TEMP:?}/baixa" && mkdir -p "$TEMP/baixa"
        rclone copyto "$DESTINO/$NOME" "/tmp-nuvem/baixa/$NOME" || true
        [ -s "$TEMP/baixa/$NOME" ] || { echo "ERRO: '$NOME' não foi encontrado na nuvem. Veja os nomes com: $0 listar" >&2; exit 1; }
        mkdir -p "$BACKUP_DIR/restaurar"
        SAIDA="$BACKUP_DIR/restaurar/${NOME%.enc}"
        # Descriptografa para um arquivo temporário: com a senha errada nada é deixado no destino.
        if ! descriptografar "$TEMP/baixa/$NOME" > "$TEMP/baixa/saida" 2> /dev/null || [ ! -s "$TEMP/baixa/saida" ]; then
            echo "ERRO: não foi possível descriptografar. A BACKUP_SENHA é a mesma usada no envio?" >&2
            exit 1
        fi
        mv "$TEMP/baixa/saida" "$SAIDA"
        echo "Pronto: $SAIDA"
        case "$NOME" in
            banco-*) echo "Para restaurar o banco: ./scripts/restaurar.sh $SAIDA" ;;
            *) echo "Documentos: veja 'Restaurar os documentos' em docs/PRODUCAO.md (use este arquivo no lugar do backups/documentos-*.tar.gz)." ;;
        esac
        ;;

    limpar)
        echo "Apagando da nuvem backups com mais de ${RETENCAO_DIAS} dias..."
        rclone delete "$DESTINO/" --min-age "${RETENCAO_DIAS}d" --include "banco-*.enc" --include "documentos-*.enc" -v 2>&1 | grep -E "Deleted|ERROR" || echo "Nada a apagar."
        ;;

    *)
        echo "Uso: $0 {enviar arquivo...|listar|baixar nome.enc|limpar}" >&2
        exit 1
        ;;
esac
