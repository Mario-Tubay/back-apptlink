<?php

namespace App\Http\Controllers;

use App\Models\Pedidos;
use App\Models\DetallePedidos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\KardexController;
use App\Models\Productos;

class PedidosController extends Controller
{
    protected $kardexController;

    public function __construct(KardexController $kardexController)
    {
        $this->kardexController = $kardexController;
        date_default_timezone_set('America/Guayaquil');
    }

    public function getAll()
    {
        try {
            $pedidos = Pedidos::with(['cliente', 'detallePedidos'])
                ->select('pedidos.*')
                ->selectRaw('(SELECT COUNT(*) FROM detalle_pedidos WHERE detalle_pedidos.id_pedido = pedidos.id) as total_items')
                ->get()
                ->map(function ($pedido) {
                    return [
                        'id' => $pedido->id,
                        'fecha' => $pedido->fecha,
                        'cliente_nombre' => $pedido->cliente->nombres . ' ' . $pedido->cliente->apellidos,
                        'cliente_ci' => $pedido->cliente->ci,
                        'total_items' => $pedido->total_items,
                        'subtotal_0' => $pedido->subtotal_0,
                        'subtotal_impuesto' => $pedido->subtotal_impuesto,
                        'iva' => $pedido->iva,
                        'descuento' => $pedido->descuento,
                        'total' => $pedido->total
                    ];
                });

            return response()->json($pedidos, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function create(Request $request)
    {
        try {
            DB::beginTransaction();
            
            foreach ($request->detalle as $item) {
                $producto = Productos::find($item['id_producto']);
                if (!$producto || $producto->stock < $item['cantidad']) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Stock insuficiente para el producto: {$producto->nombre}",
                        'stock_disponible' => $producto->stock
                    ], 422);
                }
            }

            $pedido = Pedidos::create([
                'id_cliente' => $request->id_cliente,
                'fecha' => date('Y-m-d'),
                'iva' => $request->iva,
                'subtotal_0' => $request->subtotal_0,
                'subtotal_impuesto' => $request->subtotal_impuesto,
                'descuento' => $request->descuento,
                'total' => $request->total
            ]);

            foreach ($request->detalle as $item) {
                DetallePedidos::create([
                    'id_pedido' => $pedido->id,
                    'id_producto' => $item['id_producto'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal_0' => $item['subtotal_0'],
                    'subtotal_impuesto' => $item['subtotal_impuesto'],
                    'descuento' => $item['descuento'],
                    'impuesto' => $item['impuesto'],
                    'total' => $item['total']
                ]);

                $this->kardexController->registrarMovimiento(new Request([
                    'producto_id' => $item['id_producto'],
                    'tipo_movimiento' => 'salida',
                    'concepto' => 'Venta - Pedido #' . $pedido->id,
                    'cantidad' => $item['cantidad'],
                    'valor_unitario' => $item['precio_unitario'],
                    'documento_tipo' => 'pedido',
                    'documento_numero' => $pedido->id
                ]));
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Pedido creado con éxito'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function getById($id)
    {
        try {
            $pedido = Pedidos::with(['cliente', 'detallePedidos.producto'])->find($id);
            if (!$pedido) {
                return response()->json(['status' => 'error', 'message' => 'Pedido no encontrado'], 404);
            }

            $response = [
                'id' => $pedido->id,
                'id_cliente' => $pedido->id_cliente,
                'fecha' => $pedido->fecha,
                'iva' => $pedido->iva,
                'subtotal_0' => $pedido->subtotal_0,
                'subtotal_impuesto' => $pedido->subtotal_impuesto,
                'descuento' => $pedido->descuento,
                'impuesto' => $pedido->impuesto ?? 0,
                'total' => $pedido->total,
                'cliente' => $pedido->cliente,
                'detalle' => $pedido->detallePedidos->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'id_producto' => $item->id_producto,
                        'cantidad' => $item->cantidad,
                        'precio_unitario' => $item->precio_unitario,
                        'nombre' => $item->producto->nombre,
                        'subtotal_0' => $item->subtotal_0,
                        'subtotal_impuesto' => $item->subtotal_impuesto,
                        'impuesto' => $item->impuesto,
                        'descuento' => $item->descuento,
                        'total' => $item->total
                    ];
                })
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function delete($id)
    {
        try {
            DB::beginTransaction();
            
            $pedido = Pedidos::find($id);
            if (!$pedido) {
                return response()->json(['status' => 'error', 'message' => 'Pedido no encontrado'], 404);
            }
            $detalles = DetallePedidos::where('id_pedido', $id)->get();
            foreach ($detalles as $detalle) {
                $this->kardexController->registrarMovimiento(new Request([
                    'producto_id' => $detalle->id_producto,
                    'tipo_movimiento' => 'entrada',
                    'concepto' => 'Reversión por Anulación - Pedido #' . $pedido->id,
                    'cantidad' => $detalle->cantidad,
                    'valor_unitario' => $detalle->precio_unitario,
                    'documento_tipo' => 'pedido_anulado',
                    'documento_numero' => $pedido->id
                ]));
            }

            $pedido->estado = 0;
            $pedido->save();

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Pedido anulado con éxito'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function edit(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            
            $pedido = Pedidos::find($id);
            if (!$pedido) {
                return response()->json(['status' => 'error', 'message' => 'Pedido no encontrado'], 404);
            }

            $stockAjustado = [];
            $oldDetails = DetallePedidos::where('id_pedido', $id)->get();
            foreach ($oldDetails as $oldItem) {
                $stockAjustado[$oldItem->id_producto] = $oldItem->cantidad;
            }

            foreach ($request->detalle as $item) {
                $producto = Productos::find($item['id_producto']);
                $stockDisponible = $producto->stock + ($stockAjustado[$item['id_producto']] ?? 0);
                
                if (!$producto || $stockDisponible < $item['cantidad']) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Stock insuficiente para el producto: {$producto->nombre}",
                        'stock_disponible' => $stockDisponible
                    ], 422);
                }
            }

            $oldDetails = DetallePedidos::where('id_pedido', $id)->get();
            foreach ($oldDetails as $oldItem) {
                $this->kardexController->registrarMovimiento(new Request([
                    'producto_id' => $oldItem->id_producto,
                    'tipo_movimiento' => 'entrada', 
                    'concepto' => 'Reversión - Edición Pedido #' . $pedido->id,
                    'cantidad' => $oldItem->cantidad,
                    'valor_unitario' => $oldItem->precio_unitario,
                    'documento_tipo' => 'pedido_edit',
                    'documento_numero' => $pedido->id
                ]));
            }

            $pedido->update([
                'id_cliente' => $request->id_cliente,
                'iva' => $request->iva,
                'subtotal_0' => $request->subtotal_0,
                'subtotal_impuesto' => $request->subtotal_impuesto,
                'descuento' => $request->descuento,
                'total' => $request->total
            ]);

            DetallePedidos::where('id_pedido', $id)->delete();

            foreach ($request->detalle as $item) {
                DetallePedidos::create([
                    'id_pedido' => $pedido->id,
                    'id_producto' => $item['id_producto'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal_0' => $item['subtotal_0'],
                    'subtotal_impuesto' => $item['subtotal_impuesto'],
                    'descuento' => $item['descuento'],
                    'impuesto' => $item['impuesto'],
                    'total' => $item['total']
                ]);

                $this->kardexController->registrarMovimiento(new Request([
                    'producto_id' => $item['id_producto'],
                    'tipo_movimiento' => 'salida',
                    'concepto' => 'Venta Actualizada - Pedido #' . $pedido->id,
                    'cantidad' => $item['cantidad'],
                    'valor_unitario' => $item['precio_unitario'],
                    'documento_tipo' => 'pedido',
                    'documento_numero' => $pedido->id
                ]));
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Pedido actualizado con éxito'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function generatePDF($id)
    {
        try {
            $pedido = Pedidos::with(['cliente', 'detallePedidos.producto'])->find($id);
            if (!$pedido) {
                return response()->json(['status' => 'error', 'message' => 'Pedido no encontrado'], 404);
            }

            $pdf = PDF::loadView('pdfs.pedido', [
                'pedido' => $pedido,
                'cliente' => $pedido->cliente,
                'detalles' => $pedido->detallePedidos,
                'fecha' => date('d/m/Y', strtotime($pedido->fecha))
            ]);

            return $pdf->download('pedido-' . $pedido->id . '.pdf');
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function getTotalCost()
    {
        try {
            $totals = Pedidos::selectRaw('
                COUNT(*) as total_pedidos,
                SUM(total) as total_ventas,
                SUM(subtotal_0) as total_subtotal_0,
                SUM(subtotal_impuesto) as total_subtotal_impuesto,
                SUM(iva) as total_iva,
                SUM(descuento) as total_descuento
            ')->first();

            return response()->json([
                'total_pedidos' => $totals->total_pedidos,
                'total_ventas' => round($totals->total_ventas, 2),
                'total_subtotal_0' => round($totals->total_subtotal_0, 2),
                'total_subtotal_impuesto' => round($totals->total_subtotal_impuesto, 2),
                'total_iva' => round($totals->total_iva, 2),
                'total_descuento' => round($totals->total_descuento, 2)
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
