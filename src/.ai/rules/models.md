---
paths:
  - 'app/Models/**'
---

# Models

## Campos privilegiados fora do #[Fillable]
Mantenha tenant_id, role, active e chaves de relacionamento fora do #[Fillable]; o Service os atribui explicitamente (atribuição direta ou forceFill).

## Chave primária UUID nos Models de domínio
Models de domínio usam HasUuids; a tabela users permanece com id bigint por decisão registrada (audits.auditable_id é string para aceitar os dois).

## Enums PHP com cast, não enum do banco
Status e tipos são backed enums em app/Enums, aplicados por cast no Model.
