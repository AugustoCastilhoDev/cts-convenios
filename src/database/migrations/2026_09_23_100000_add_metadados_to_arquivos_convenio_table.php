<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arquivos_convenio', function (Blueprint $table) {
            $table->string('nome_original')->after('tipo_documento');
            $table->string('mime_type')->after('nome_original');
            $table->unsignedBigInteger('tamanho_bytes')->after('mime_type');
            $table->foreignId('enviado_por')->nullable()->after('file_path')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('arquivos_convenio', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enviado_por');
            $table->dropColumn(['nome_original', 'mime_type', 'tamanho_bytes']);
        });
    }
};
