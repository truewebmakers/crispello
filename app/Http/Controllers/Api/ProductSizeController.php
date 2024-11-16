<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\cart_product;
use App\Models\combo;
use App\Models\combo_details;
use App\Models\product;
use App\Models\product_size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductSizeController extends Controller
{
    /**
     * Add Product Size.
     */
    public function addProductSize(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            'sizes' => 'required|array'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $product = product::find($request->product_id);
            if (!$product) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'product not found.'
                ], 404);
            }
            foreach ($request->sizes as $item) {
                // if (!isset($item['size']) || !isset($item['actual_price']) || !isset($item['selling_price']) || $item['actual_price'] <= 0 || $item['selling_price'] <= 0) {
                if (
                    !isset($item['size']) || !isset($item['delivery_actual_price']) || !isset($item['delivery_selling_price']) || $item['delivery_actual_price'] <= 0 || $item['delivery_selling_price'] <= 0
                    || !isset($item['pickup_actual_price']) || !isset($item['pickup_selling_price']) || $item['pickup_actual_price'] <= 0 || $item['pickup_selling_price'] <= 0
                    || !isset($item['dinein_actual_price']) || !isset($item['dinein_selling_price']) || $item['dinein_actual_price'] <= 0 || $item['dinein_selling_price'] <= 0
                ) {
                    return response()->json([
                        'status_code' => 100,
                        'message' => 'Invalid sizes array'
                    ], 200);
                }
                $name = trim($item['size']);
                $existingSize = product_size::whereRaw('LOWER(TRIM(size)) = ?', [strtolower($name)])->where('product_id', $product->_id)->first();
                if ($existingSize) {
                    return response()->json([
                        'status_code' => 400,
                        'message' => 'Product size ' . $item['size'] . ' already exist in ' . $product->name
                    ], 200);
                }
                if (($item['delivery_selling_price'] > $item['delivery_actual_price']) ||
                    ($item['pickup_selling_price'] > $item['pickup_actual_price']) ||
                    ($item['dinein_selling_price'] > $item['dinein_actual_price'])
                ) {
                    return response()->json([
                        'status_code' => 400,
                        'message' => 'Selling price of product is more than actual price for size ' . $name
                    ], 200);
                }
                $product_size = new product_size();
                $product_size->size = $name;
                $product_size->arabic_size = $item['arabic_size'] ?? null;
                $product_size->delivery_actual_price = trim($item['delivery_actual_price']);
                $product_size->delivery_selling_price = trim($item['delivery_selling_price']);
                $product_size->pickup_actual_price = trim($item['pickup_actual_price']);
                $product_size->pickup_selling_price = trim($item['pickup_selling_price']);
                $product_size->dinein_actual_price = trim($item['dinein_actual_price']);
                $product_size->dinein_selling_price = trim($item['dinein_selling_price']);
                $product_size->product_id = $product->_id;
                $product_size->save();
            }

            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Product sizes added successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to add product sizes.'
            ], 500);
        }
    }

    /**
     * Update Product Size.
     */
    public function updateProductSize(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sizes' => 'required|array',
            'product_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $product = product::find($request->product_id);
            if (!$product) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'product not found.'
                ], 404);
            }
            foreach ($request->sizes as $item) {
                // if (!isset($item['size']) || !isset($item['actual_price']) || !isset($item['selling_price']) || $item['actual_price'] <= 0 || $item['selling_price'] <= 0) {
                if (
                    !isset($item['size']) || !isset($item['delivery_actual_price']) || !isset($item['delivery_selling_price']) || $item['delivery_actual_price'] <= 0 || $item['delivery_selling_price'] <= 0
                    || !isset($item['pickup_actual_price']) || !isset($item['pickup_selling_price']) || $item['pickup_actual_price'] <= 0 || $item['pickup_selling_price'] <= 0
                    || !isset($item['dinein_actual_price']) || !isset($item['dinein_selling_price']) || $item['dinein_actual_price'] <= 0 || $item['dinein_selling_price'] <= 0
                ) {
                    return response()->json([
                        'status_code' => 100,
                        'message' => 'Invalid sizes array'
                    ], 200);
                }
                $name = trim($item['size']);
                if (isset($item['_id'])) {
                    $product_size = product_size::find($item['_id']);
                    if (!$product_size) {
                        return response()->json([
                            'status_code' => 404,
                            'message' => 'product size with id ' . $item['_id'] . ' not found.'
                        ], 404);
                    }
                    $existingSize = product_size::whereRaw('LOWER(TRIM(size)) = ?', [strtolower($name)])->where('_id', '!=', $product_size->_id)->where('product_id', $product_size->product_id)->first();
                    if ($existingSize) {
                        return response()->json([
                            'status_code' => 400,
                            'message' => 'Product size ' . $item['size'] . ' already exist.'
                        ], 200);
                    }
                    $deliveryOldPrice = $product_size->delivery_selling_price;
                    $dineinOldPrice = $product_size->dinein_selling_price;
                    $pickupOldPrice = $product_size->pickup_selling_price;
                    $product_size->size = $name;
                    $product_size->arabic_size = $item['arabic_size'] ?? null;
                    $product_size->delivery_actual_price = trim($item['delivery_actual_price']);
                    $product_size->delivery_selling_price = trim($item['delivery_selling_price']);
                    $product_size->pickup_actual_price = trim($item['pickup_actual_price']);
                    $product_size->pickup_selling_price = trim($item['pickup_selling_price']);
                    $product_size->dinein_actual_price = trim($item['dinein_actual_price']);
                    $product_size->dinein_selling_price = trim($item['dinein_selling_price']);
                    // $product_size->actual_price = trim($item['actual_price']);
                    // $product_size->selling_price = trim($item['selling_price']);

                    // if ($product_size->selling_price > $product_size->actual_price) {
                    if (($item['delivery_selling_price'] > $item['delivery_actual_price']) ||
                        ($item['pickup_selling_price'] > $item['pickup_actual_price']) ||
                        ($item['dinein_selling_price'] > $item['dinein_actual_price'])
                    ) {
                        return response()->json([
                            'status_code' => 400,
                            'message' => 'Selling price of product is more than actual price for size ' . $product_size->size
                        ], 200);
                    }

                    $product_size->save();
                    $combos = combo::join('combo_details', 'combos._id', '=', 'combo_details.combo_id')
                        ->where('combo_details.product_id', $product_size->product_id)
                        ->where('combo_details.size', $product_size->_id)
                        ->select('combos._id', 'combos.delivery_actual_price','combos.dinein_actual_price','combos.pickup_actual_price', 'combo_details.quantity', 'combo_details.size')
                        ->get();
                    foreach ($combos as $combo) {
                        $comboModel = combo::find($combo->_id);
                        $newDeliveryTotalPrice = $comboModel->delivery_actual_price - ($deliveryOldPrice * $combo->quantity) + ($product_size->delivery_selling_price * $combo->quantity);
                        $comboModel->delivery_actual_price = $newDeliveryTotalPrice;
                        if ($comboModel->delivery_selling_price > $comboModel->delivery_actual_price) {
                            return response()->json([
                                'status_code' => 100,
                                'message' => 'Delivery selling price will be more than actual price for combo ' . $comboModel->name . ' if price of the size ' . $product_size->size . ' will change.'
                            ], 200);
                        }

                        $newDineinTotalPrice = $comboModel->dinein_actual_price - ($dineinOldPrice* $combo->quantity) + ($product_size->dinein_selling_price * $combo->quantity);
                        $comboModel->dinein_actual_price = $newDineinTotalPrice;
                        if ($comboModel->dinein_selling_price > $comboModel->dinein_actual_price) {
                            return response()->json([
                                'status_code' => 100,
                                'message' => 'Dine In selling price will be more than actual price for combo ' . $comboModel->name . ' if price of the size ' . $product_size->size . ' will change.'
                            ], 200);
                        }

                        $newDineinTotalPrice = $comboModel->pickup_actual_price - ($pickupOldPrice * $combo->quantity) + ($product_size->pickup_selling_price * $combo->quantity);
                        $comboModel->pickup_actual_price = $newDineinTotalPrice;
                        if ($comboModel->pickup_selling_price > $comboModel->pickup_actual_price) {
                            return response()->json([
                                'status_code' => 100,
                                'message' => 'Pickup selling price will be more than actual price for combo ' . $comboModel->name . ' if price of the size ' . $product_size->size . ' will change.'
                            ], 200);
                        }
                        $comboModel->save();
                    }
                } else {
                    $product_size = new product_size();
                    $product_size->size = $name;
                    $product_size->delivery_actual_price = trim($item['delivery_actual_price']);
                    $product_size->delivery_selling_price = trim($item['delivery_selling_price']);
                    $product_size->pickup_actual_price = trim($item['pickup_actual_price']);
                    $product_size->pickup_selling_price = trim($item['pickup_selling_price']);
                    $product_size->dinein_actual_price = trim($item['dinein_actual_price']);
                    $product_size->dinein_selling_price = trim($item['dinein_selling_price']);
                    // $product_size->actual_price = trim($item['actual_price']);
                    // $product_size->selling_price = trim($item['selling_price']);
                    $product_size->product_id = $product->_id;
                    $product_size->save();
                }
            }
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Product sizes updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update product sizes.'
            ], 500);
        }
    }

    /**
     * Delete Product Size.
     */
    public function deleteProductSize(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'size_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $product_size = product_size::find($request->size_id);
            if (!$product_size) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'product size not found.'
                ], 404);
            }
            $existInCombo = combo_details::join('combos', 'combo_details.combo_id', '=', 'combos._id')
                ->where('combo_details.product_id', $product_size->product_id)
                ->where('combo_details.size', $product_size->_id)
                ->exists();
            if ($existInCombo) {
                return response()->json([
                    'status_code' => 100,
                    'message' => 'This product size is part of an active combo. Please change or remove the product size from the combo.'
                ], 200);
            }
            cart_product::where('product_id', $product_size->product_id)->where('size', $product_size->_id)->update(['is_update' => 1]);
            $product_size->delete();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Product size deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to delete product size.'
            ], 500);
        }
    }
}
