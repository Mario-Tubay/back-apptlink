<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedidos extends Model
{
    protected $fillable = [
        'id_cliente',
        'fecha',
        'iva',
        'estado',
        'subtotal_0',
        'subtotal_impuesto',
        'descuento',
        'total'
    ];

    public function cliente()
    {
        return $this->belongsTo(User::class, 'id_cliente');
    }

    public function detallePedidos()
    {
        return $this->hasMany(DetallePedidos::class, 'id_pedido');
    }
}
