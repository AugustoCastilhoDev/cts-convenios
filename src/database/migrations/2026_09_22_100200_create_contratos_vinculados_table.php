<?php

use App\Enums\StatusExecucaoContrato;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos_vinculados', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // tenant_id denormalizado do convênio-pai: defesa em profundidade contra
            // vazamento entre prefeituras e evita join extra para o escopo de tenant.
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('convenio_id')->constrained('convenios')->cascadeOnDelete();

            $table->string('numero_contrato');
            $table->string('empresa_contratada');
            $table->decimal('valor_contratado', 12, 2)->default(0);
            $table->string('status_execucao')->default(StatusExecucaoContrato::NaoIniciado->value);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'convenio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos_vinculados');
    }
};
