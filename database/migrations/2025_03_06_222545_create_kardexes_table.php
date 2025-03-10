<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kardex', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->foreign('producto_id')->references('id')->on('productos');
            $table->date('fecha');
            $table->enum('tipo_movimiento', ['entrada', 'salida']);
            $table->string('concepto');
            $table->integer('cantidad');
            $table->decimal('valor_unitario', 10, 2);
            $table->decimal('valor_total', 10, 2);
            $table->integer('saldo_cantidad');
            $table->decimal('saldo_valor_unitario', 10, 2);
            $table->decimal('saldo_valor_total', 10, 2);
            $table->string('documento_tipo')->nullable();
            $table->string('documento_numero')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kardex');
    }
};
