<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_prazo', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('convenio_id')->constrained('convenios')->cascadeOnDelete();

            // string + cast de Enum no Model (App\Enums\TipoPrazo).
            $table->string('tipo_prazo');

            // Marco da régua (90, 60, 30, 15) ou -1 para "prazo vencido".
            $table->smallInteger('marco_dias');

            // Data do prazo no momento do alerta: se o prazo for alterado, os
            // alertas do novo prazo começam do zero (nova linha).
            $table->date('data_prazo');

            $table->timestamp('enviado_em')->nullable();
            $table->unsignedSmallInteger('destinatarios')->nullable();

            // Alerta obsoleto (prazo alterado ou convênio finalizado antes do envio).
            $table->timestamp('cancelado_em')->nullable();

            $table->timestamps();

            // Garante um único alerta por marco, mesmo com várias execuções.
            $table->unique(['convenio_id', 'tipo_prazo', 'data_prazo', 'marco_dias']);
            $table->index(['tenant_id', 'convenio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_prazo');
    }
};
