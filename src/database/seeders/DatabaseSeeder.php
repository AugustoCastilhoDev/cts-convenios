<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * tenant_id e role ficam fora do #[Fillable] do User por segurança
     * (nunca vêm de payload de cliente). Aqui usamos forceCreate() de
     * propósito — é exatamente o tipo de escrita privilegiada que só um
     * seeder ou um Service de administração faria.
     */
    public function run(): void
    {
        $tenant = Tenant::create([
            'razao_social' => 'Prefeitura Municipal de Exemplópolis (dados fictícios)',
            'cnpj' => '00.000.000/0001-00',
        ]);

        User::forceCreate([
            'tenant_id' => null,
            'role' => UserRole::AdministradorInterno,
            'name' => 'Administrador CTS',
            'email' => 'admin@ctsconvenios.com.br',
            'password' => Hash::make('password'),
        ]);

        User::forceCreate([
            'tenant_id' => $tenant->id,
            'role' => UserRole::GestorConvenios,
            'name' => 'Gestor de Convênios',
            'email' => 'gestor@municipio-exemplo.gov.br',
            'password' => Hash::make('password'),
        ]);

        User::forceCreate([
            'tenant_id' => $tenant->id,
            'role' => UserRole::FiscalControleInterno,
            'name' => 'Fiscal de Controle Interno',
            'email' => 'fiscal@municipio-exemplo.gov.br',
            'password' => Hash::make('password'),
        ]);
    }
}
