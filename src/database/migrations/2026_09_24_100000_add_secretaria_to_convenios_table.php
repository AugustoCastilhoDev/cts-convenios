<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Secretaria responsável pelo convênio (enum App\Enums\Secretaria no Model).
     * Nullable de propósito: os convênios que já existem não têm classificação e
     * não podem ser inventados; a tela de criação passa a exigir a escolha.
     */
    public function up(): void
    {
        Schema::table('convenios', function (Blueprint $table) {
            $table->string('secretaria')->nullable()->after('objeto');
            $table->index(['tenant_id', 'secretaria']);
        });
    }

    public function down(): void
    {
        Schema::table('convenios', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'secretaria']);
            $table->dropColumn('secretaria');
        });
    }
};
