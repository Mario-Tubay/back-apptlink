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
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_cliente');  // Changed to match users table id type
            $table->foreign('id_cliente')->references('id')->on('users');
            $table->date('fecha');
            $table->decimal('iva', 5, 2);
            $table->decimal('subtotal_0', 10, 2);
            $table->decimal('subtotal_impuesto', 10, 2);
            $table->decimal('descuento', 10, 2);
            $table->decimal('total', 10, 2);
            $table->integer('estado')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
