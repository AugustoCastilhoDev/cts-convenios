<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De qual prefeitura é cada registro de auditoria. Os Models passam a gravar isso sozinhos
     * (transformAudit); aqui a coluna nasce e o histórico já existente é preenchido a partir do
     * registro auditado (inclusive os excluídos logicamente, por isso a consulta é direta na tabela).
     */
    public function up(): void
    {
        $tabela = config('audit.drivers.database.table', 'audits');

        Schema::table($tabela, function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('user_agent')->index();
        });

        // tipo auditado => tabela onde está o tenant_id (Tenant é o próprio dono do registro).
        $origens = [
            'App\\Models\\Convenio' => 'convenios',
            'App\\Models\\ContratoVinculado' => 'contratos_vinculados',
            'App\\Models\\ArquivoConvenio' => 'arquivos_convenio',
            'App\\Models\\User' => 'users',
        ];

        foreach ($origens as $tipo => $origem) {
            DB::table($tabela)->where('auditable_type', $tipo)->orderBy('id')->chunkById(500, function ($auditorias) use ($tabela, $origem) {
                $tenants = DB::table($origem)->whereIn('id', $auditorias->pluck('auditable_id'))->pluck('tenant_id', 'id');

                foreach ($auditorias as $auditoria) {
                    if ($tenants->get($auditoria->auditable_id)) {
                        DB::table($tabela)->where('id', $auditoria->id)->update(['tenant_id' => $tenants->get($auditoria->auditable_id)]);
                    }
                }
            });
        }

        // A prefeitura é dona do próprio registro. Pelo PHP (e não "set tenant_id = auditable_id"): no Postgres
        // texto não vira uuid dentro do SQL sem cast, e o SQLite dos testes não acusa isso.
        DB::table($tabela)->where('auditable_type', 'App\\Models\\Tenant')->orderBy('id')->chunkById(500, function ($auditorias) use ($tabela) {
            foreach ($auditorias as $auditoria) {
                DB::table($tabela)->where('id', $auditoria->id)->update(['tenant_id' => $auditoria->auditable_id]);
            }
        });
    }

    public function down(): void
    {
        $tabela = config('audit.drivers.database.table', 'audits');

        Schema::table($tabela, function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
