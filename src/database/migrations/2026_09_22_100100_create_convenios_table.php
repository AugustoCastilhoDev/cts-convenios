<?php

use App\Enums\StatusConvenio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convenios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('numero_convenio');
            $table->string('orgao_concedente');
            $table->text('objeto');
            $table->decimal('valor_repasse', 12, 2)->default(0);
            $table->decimal('valor_contrapartida', 12, 2)->default(0);

            // string + cast de Enum no Model (App\Enums\StatusConvenio): evita a
            // rigidez de um enum nativo do Postgres ao adicionar novos status no futuro.
            $table->string('status')->default(StatusConvenio::Proposta->value);

            $table->date('data_assinatura')->nullable();
            $table->date('data_vigencia_fim')->nullable();
            $table->date('prazo_prestacao_contas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Data crítica para a varredura diária do Motor de Alertas (Módulo 3).
            $table->index(['tenant_id', 'data_vigencia_fim']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'numero_convenio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenios');
    }
};
