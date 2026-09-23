---
paths:
  - 'tests/**'
---

# Tests

## Estilo dos testes
Use PHPUnit com RefreshDatabase e crie registros manualmente (trait CriaUsuariosDeTeste, forceCreate), sem factories. Use Storage::fake('local') em testes de arquivo.

## Trocar de usuário no mesmo teste
Ao trocar de usuário dentro do mesmo teste, chame $this->app['auth']->forgetGuards() antes de Sanctum::actingAs().
