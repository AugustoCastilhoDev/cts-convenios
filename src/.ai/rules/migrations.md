---
paths:
  - 'database/migrations/**'
---

# Migrations

## Chave primária UUID nas tabelas de domínio
Tabelas de domínio usam uuid('id')->primary() e foreignUuid(); a tabela users permanece com id bigint por decisão registrada.

## Enums guardados como string
Guarde status e tipos como string() (cast para Enum PHP no Model); nunca use enum() nativo do banco.
