<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kardex extends Model
{
    protected $table = 'kardex';

    protected $fillable = [
        'producto_id',
        'fecha',
        'tipo_movimiento',
        'concepto',
        'cantidad',
        'valor_unitario',
        'valor_total',
        'saldo_cantidad',
        'saldo_valor_unitario',
        'saldo_valor_total',
        'documento_tipo',
        'documento_numero'
    ];

    public function producto()
    {
        return $this->belongsTo(Productos::class, 'producto_id');
    }
}
