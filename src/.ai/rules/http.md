---
paths:
  - 'app/Http/**'
---

# Http

## Validação em Form Requests
Valide toda entrada em Form Requests com #[StopOnFirstFailure]; nunca use $request->validate() inline no Controller.

## Respostas via API Resource
Responda sempre com API Resources; inclua relacionamentos apenas com whenLoaded().
