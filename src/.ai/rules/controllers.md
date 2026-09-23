---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Autorização e middleware declarativos
Autorize com #[Authorize('habilidade', 'parametro')] e aplique middleware com #[Middleware] no Controller; não use $this->authorize() nem chame Policies manualmente. Em rotas aninhadas passe o Model pai: #[Authorize('create', [Filho::class, 'pai'])].
