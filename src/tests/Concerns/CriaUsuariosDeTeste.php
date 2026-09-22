<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Espelha o padrão do DatabaseSeeder: tenant_id/role ficam fora do
 * #[Fillable] do User por segurança, então usamos forceCreate() aqui
 * também — é a mesma escrita privilegiada que só testes/seeders fazem.
 */
trait CriaUsuariosDeTeste
{
    protected function criarTenant(array $atributos = []): Tenant
    {
        return Tenant::create(array_merge([
            'razao_social' => 'Prefeitura de Teste '.fake()->unique()->numberBetween(1, 999999),
            'cnpj' => fake()->unique()->numerify('##.###.###/####-##'),
        ], $atributos));
    }

    protected function criarAdmin(): User
    {
        return User::forceCreate([
            'tenant_id' => null,
            'role' => UserRole::AdministradorInterno,
            'name' => 'Admin Teste',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ]);
    }

    protected function criarGestor(?Tenant $tenant = null): User
    {
        $tenant ??= $this->criarTenant();

        return User::forceCreate([
            'tenant_id' => $tenant->id,
            'role' => UserRole::GestorConvenios,
            'name' => 'Gestor Teste',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ]);
    }

    protected function criarFiscal(?Tenant $tenant = null): User
    {
        $tenant ??= $this->criarTenant();

        return User::forceCreate([
            'tenant_id' => $tenant->id,
            'role' => UserRole::FiscalControleInterno,
            'name' => 'Fiscal Teste',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ]);
    }
}
