<?php

namespace App\Http\Controllers;

use App\Models\Kardex;
use App\Models\Productos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class KardexController extends Controller
{
    public function getMovimientosPorProducto(Request $request)
        {
            try {
            
                $search = $request->search;
                
                $producto = Productos::where('id', $search)
                    ->orWhere('codigo', 'LIKE', "%{$search}%")
                    ->orWhere('nombre', 'LIKE', "%{$search}%")
                    ->first();
                if (!$producto) {
                    return response()->json(['status'=> 'error', 'message' => 'Producto no encontrado'], 404);
                }
    
                $ultimoMovimiento = Kardex::where('producto_id', $producto->id)
                    ->orderBy('id', 'desc')
                    ->first();
    
                $movimientos = Kardex::where('producto_id', $producto->id)
                    ->orderBy('fecha', 'desc')
                    ->orderBy('id', 'desc')
                    ->get()
                    ->map(function ($movimiento) {
                        return [
                            'fecha' => date('Y-m-d', strtotime($movimiento->fecha)),
                            'tipo_movimiento' => $movimiento->tipo_movimiento,
                            'concepto' => $movimiento->concepto,
                            'cantidad' => $movimiento->cantidad,
                            'valor_unitario' => number_format($movimiento->valor_unitario, 2),
                            'valor_total' => number_format($movimiento->valor_total, 2),
                            'saldo_cantidad' => $movimiento->saldo_cantidad,
                            'saldo_valor_unitario' => number_format($movimiento->saldo_valor_unitario, 2),
                            'saldo_valor_total' => number_format($movimiento->saldo_valor_total, 2),
                            'documento_tipo' => $movimiento->documento_tipo,
                            'documento_numero' => $movimiento->documento_numero
                        ];
                    });
    
                $resumen = [
                    'total_entradas' => $movimientos->where('tipo_movimiento', 'entrada')->sum('cantidad'),
                    'total_salidas' => $movimientos->where('tipo_movimiento', 'salida')->sum('cantidad'),
                    'saldo_actual' => $producto->stock,
                    'ultimo_costo' => $movimientos->first() ? $movimientos->first()['saldo_valor_unitario'] : 0,
                    'costo_total_inventario' => $ultimoMovimiento ? number_format($ultimoMovimiento->saldo_valor_total, 2) : '0.00'
                ];
    
                return response()->json([
                    'producto' => [
                        'id' => $producto->id,
                        'codigo' => $producto->codigo,
                        'nombre' => $producto->nombre,
                        'descripcion' => $producto->descripcion,
                        'stock_actual' => $producto->stock
                    ],
                    'resumen' => $resumen,
                    'movimientos' => $movimientos
                ], 200);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

    public function registrarMovimiento(Request $request)
    {
        try {
            DB::beginTransaction();

            $producto = Productos::findOrFail($request->producto_id);
            $ultimoMovimiento = Kardex::where('producto_id', $request->producto_id)
                ->orderBy('id', 'desc')
                ->first();

            $saldoCantidad = $ultimoMovimiento ? $ultimoMovimiento->saldo_cantidad : 0;
            $saldoValorTotal = $ultimoMovimiento ? $ultimoMovimiento->saldo_valor_total : 0;

            if ($request->tipo_movimiento === 'entrada') {
                $nuevoSaldoCantidad = $saldoCantidad + $request->cantidad;
                $nuevoValorTotal = $saldoValorTotal + ($request->cantidad * $request->valor_unitario);
                $nuevoValorUnitario = $nuevoSaldoCantidad > 0 ? $nuevoValorTotal / $nuevoSaldoCantidad : 0;
            } else {
                $nuevoSaldoCantidad = $saldoCantidad - $request->cantidad;
                $valorUnitarioPromedio = $saldoCantidad > 0 ? $saldoValorTotal / $saldoCantidad : 0;
                $nuevoValorTotal = $nuevoSaldoCantidad * $valorUnitarioPromedio;
                $nuevoValorUnitario = $valorUnitarioPromedio;
            }

            Kardex::create([
                'producto_id' => $request->producto_id,
                'fecha' => $request->fecha ?? date('Y-m-d'),
                'tipo_movimiento' => $request->tipo_movimiento,
                'concepto' => $request->concepto,
                'cantidad' => $request->cantidad,
                'valor_unitario' => $request->valor_unitario,
                'valor_total' => $request->cantidad * $request->valor_unitario,
                'saldo_cantidad' => $nuevoSaldoCantidad,
                'saldo_valor_unitario' => $nuevoValorUnitario,
                'saldo_valor_total' => $nuevoValorTotal,
                'documento_tipo' => $request->documento_tipo,
                'documento_numero' => $request->documento_numero
            ]);

            // Actualizar stock del producto
            $producto->stock = $nuevoSaldoCantidad;
            $producto->save();

            DB::commit();
            return response()->json(['message' => 'Movimiento registrado exitosamente'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function generarReporteKardex($producto_id)
    {
        try {
            $producto = Productos::findOrFail($producto_id);
            $movimientos = Kardex::where('producto_id', $producto_id)
                ->orderBy('fecha', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $pdf = PDF::loadView('pdfs.kardex', [
                'producto' => $producto,
                'movimientos' => $movimientos
            ]);
            

            return $pdf->download('kardex-' . $producto->codigo . '.pdf');
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function updateKardex($id_producto, $cantidad, $costo_unitario, $tipo)
        {
            $kardex = $this->validateKardex($id_producto);
            
            if ($kardex) {
                $stock_anterior = $kardex->stock;
                $costo_total_anterior = $kardex->stock * $kardex->precio_promedio;
                
                if ($tipo === 'entrada') {
                    $nuevo_stock = $stock_anterior + $cantidad;
                    $nuevo_costo_total = $costo_total_anterior + ($cantidad * $costo_unitario);
                    $nuevo_costo_promedio = $nuevo_costo_total / $nuevo_stock;
                } else {
                    $nuevo_stock = $stock_anterior - $cantidad;
                    $nuevo_costo_total = $nuevo_stock * $kardex->precio_promedio;
                    $nuevo_costo_promedio = $kardex->precio_promedio;
                }
    
                $kardex->stock = $nuevo_stock;
                $kardex->precio_promedio = $nuevo_costo_promedio;
                $kardex->costo_total = $nuevo_costo_total;
                $kardex->save();
            } else {
                $kardex = new Kardex();
                $kardex->id_producto = $id_producto;
                $kardex->stock = $cantidad;
                $kardex->precio_promedio = $costo_unitario;
                $kardex->costo_total = $cantidad * $costo_unitario;
                $kardex->save();
            }
            
            return $kardex;
        }

    private function validateKardex($id_producto)
        {
            $kardex = Kardex::where('producto_id', $id_producto)
                ->orderBy('id', 'desc')
                ->first();
    
            if (!$kardex) {
                $kardex = new Kardex([
                    'producto_id' => $id_producto,
                    'stock' => 0,
                    'precio_promedio' => 0,
                    'costo_total' => 0
                ]);
            }
    
            return $kardex;
        }
        public static function registrarProductoNuevo($producto_id, $stock_inicial, $costo_unitario)
            {
                try {
                    DB::beginTransaction();
                    
                    Kardex::create([
                        'producto_id' => $producto_id,
                        'fecha' => date('Y-m-d'),
                        'tipo_movimiento' => 'entrada',
                        'concepto' => 'Registro inicial de producto',
                        'cantidad' => $stock_inicial,
                        'valor_unitario' => $costo_unitario,
                        'valor_total' => $stock_inicial * $costo_unitario,
                        'saldo_cantidad' => $stock_inicial,
                        'saldo_valor_unitario' => $costo_unitario,
                        'saldo_valor_total' => $stock_inicial * $costo_unitario,
                        'documento_tipo' => 'inicial',
                        'documento_numero' => 'REG-' . $producto_id
                    ]);
    
                    DB::commit();
                    return true;
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }
}
