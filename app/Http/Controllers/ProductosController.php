<?php

namespace App\Http\Controllers;

use App\Models\Productos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ProductosController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('America/Guayaquil');
    }

    public function getAll(Request $request)
    {
        try {
            $products = Productos::all();
            return response()->json($products, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function search(Request $request)
    {
        try {
            $query = Productos::query();
            $searchTerm = $request['search'];

            if (!is_null($request->search)) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('codigo', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('nombre', 'LIKE', "%{$searchTerm}%");
                });
            }
            $products = $query->where('estado', 1)->get();
            return response()->json($products, 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'error' => $e->getMessage()], 422);
        }
    }

    public function create(Request $request)
    {
        try {
            if (!$request->codigo || !$request->nombre || !$request->descripcion || !$request->precio || !$request->stock) {
                return response()->json(['status' => "error", 'message' => "Todos los campos son requeridos"], 422);
            }

            $producto = Productos::where('codigo', $request->codigo)->first();

            if (!is_null($producto)) {
                return response()->json(['status' => 'error', 'message' => "El codigo ya existe"], 422);
            }

            if ($request->precio < 0) {
                return response()->json(['status' => 'error', 'message' => "El precio no puede ser negativo"], 422);
            }
            if ($request->stock <= 0) {
                return response()->json(['status' => 'error', 'message' => "El stock no puede ser menor o igual a 0"], 422);
            }

            $producto = Productos::create([
                'codigo' => $request->codigo,
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'precio' => str_replace(',', '.', $request->precio),
                'stock' => $request->stock,
                'iva'   => $request->iva,
            ]);

            KardexController::registrarProductoNuevo(
                $producto->id,
                $request->stock,
                $request->precio
            );

            return response()->json(['status' => 'success', 'message' => 'Guardado con exito'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Ocurrio un error', 'error' => $e->getMessage()], 422);
        }
    }
    public function getById($id)
    {
        try {
            $product = Productos::find($id);
            if (!$product) {
                return response()->json(['status' => 'error', 'message' => 'No se encontro el producto'], 422);
            }
            return response()->json($product, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
    public function edit(Request $request)
    {
        DB::beginTransaction();
        try {
            
            $product = Productos::find($request->id);
            if (!$product) {
                return response()->json(['status' => 'error', 'message' => 'No se encontro el producto'], 422);
            }

            if (!$request->codigo || !$request->nombre || !$request->descripcion || !$request->precio || !$request->stock) {
                return response()->json(['status' => "error", 'message' => "Todos los campos son requeridos"], 422);
            }

            $producto = Productos::where('codigo', $request->codigo)->where('id', '<>', $request->id)->first();

            if (!is_null($producto)) {
                return response()->json(['status' => 'error', 'message' => "El codigo ya existe"], 422);
            }

            if ($request->precio <= 0) {
                return response()->json(['status' => 'error', 'message' => "El precio no puede ser menor o igual a 0"], 422);
            }
            if ($request->stock <= 0) {
                return response()->json(['status' => 'error', 'message' => "El stock no puede ser menor o igual a 0"], 422);
            }

            $stockDifference = $request->stock - $product->stock;
            $priceChanged = floatval($request->precio) != floatval($product->precio);
            
            if ($stockDifference != 0 || $priceChanged) {
                $kardexController = new KardexController();
                
                if ($priceChanged) {
                    $kardexController->registrarMovimiento(new Request([
                        'producto_id' => $product->id,
                        'tipo_movimiento' => 'entrada',
                        'concepto' => 'Ajuste de precio',
                        'cantidad' => $product->stock,
                        'valor_unitario' => $request->precio,
                        'documento_tipo' => 'ajuste_precio',
                        'documento_numero' => 'AP-' . $product->id . '-' . time()
                    ]));
                }

                if ($stockDifference != 0) {
                    $kardexController->registrarMovimiento(new Request([
                        'producto_id' => $product->id,
                        'tipo_movimiento' => $stockDifference > 0 ? 'entrada' : 'salida',
                        'concepto' => 'Ajuste de inventario',
                        'cantidad' => abs($stockDifference),
                        'valor_unitario' => $request->precio,
                        'documento_tipo' => 'ajuste_stock',
                        'documento_numero' => 'AS-' . $product->id . '-' . time()
                    ]));
                }
            }

            $product->update([
                'codigo' => $request->codigo,
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'iva'   => $request->iva,
                'precio' => floatval($request->precio),
                'stock' => $request->stock,
            ]);

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Actualizado con exito'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
    public function delete(Request $request)
    {
        try {
            $product = Productos::find($request->id);
            if (!$product) {
                return response()->json(['status' => 'error', 'message' => 'No se encontro el producto'], 422);
            }
            $product->estado = 0;
            $product->save();
            return response()->json(['status' => 'success', 'message' => 'Eliminado con exito'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
    public function getTotalProducts()
        {
            try {
                $totals = Productos::selectRaw('
                    COUNT(*) as total_productos,
                    SUM(stock) as total_stock,
                    SUM(stock * precio) as valor_inventario,
                    COUNT(CASE WHEN iva = 1 THEN 1 END) as productos_con_iva,
                    COUNT(CASE WHEN iva = 0 THEN 1 END) as productos_sin_iva
                ')
                ->where('estado', 1)
                ->first();
    
                return response()->json([
                    'total_productos' => $totals->total_productos,
                    'total_stock' => $totals->total_stock,
                    'valor_inventario' => round($totals->valor_inventario, 2),
                    'productos_con_iva' => $totals->productos_con_iva,
                    'productos_sin_iva' => $totals->productos_sin_iva
                ], 200);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }
}
