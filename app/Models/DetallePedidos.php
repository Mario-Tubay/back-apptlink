<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetallePedidos extends Model
{
    protected $fillable = [
        'id_pedido',
        'id_producto',
        'cantidad',
        'precio_unitario',
        'subtotal_0',
        'subtotal_impuesto',
        'descuento',
        'impuesto',
        'total'
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedidos::class, 'id_pedido');
    }

    public function producto()
    {
        return $this->belongsTo(Productos::class, 'id_producto');
    }
}