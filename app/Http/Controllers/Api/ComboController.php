<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\cart_product;
use App\Models\combo;
use App\Models\combo_details;
use App\Models\product;
use App\Models\product_size;
use App\Traits\ImageHandleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ComboController extends Controller
{
    use ImageHandleTrait;
    /**
     * add Combo.
     */
    public function addCombo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'image' => 'required',
            'delivery_selling_price' => 'required|numeric',
            'dinein_selling_price' => 'required|numeric',
            'pickup_selling_price' => 'required|numeric',
            'veg' => 'required|boolean',
            'best_seller' => 'boolean',
            'recommended' => 'boolean',
            'is_available' => 'boolean',
            'products' => 'required|array'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $name = trim($request->name);
            $existingCombo = combo::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->first();

            if ($existingCombo) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Combo name already exist'
                ], 200);
            }
            $productArray = $request->products;
            $validationResponse = $this->validateProducts($productArray);
            if ($validationResponse !== null) {
                return $validationResponse;
            }

            $newCombo = new combo();
            $newCombo->name = $name;
            $newCombo->delivery_selling_price = $request->delivery_selling_price;
            $newCombo->dinein_selling_price = $request->dinein_selling_price;
            $newCombo->pickup_selling_price = $request->pickup_selling_price;
            $newCombo->veg = $request->veg;
            $newCombo->best_seller = $request->has('best_seller') ? $request->best_seller : 0;
            $newCombo->recommended = $request->has('recommended') ? $request->recommended : 0;
            $newCombo->is_available = $request->has('is_available') ? $request->is_available : 1;
            $newCombo->disable = 0;
            $prices = $this->calculateActualPrice($request->products);
            $newCombo->delivery_actual_price = $prices['deliveryActualPrice'];
            $newCombo->dinein_actual_price = $prices['dineinActualPrice'];
            $newCombo->pickup_actual_price = $prices['pickupActualPrice'];

            if (($newCombo->delivery_selling_price > $newCombo->delivery_actual_price)||($newCombo->dinein_selling_price > $newCombo->dinein_actual_price)||($newCombo->pickup_selling_price > $newCombo->pickup_actual_price)) {
                return response()->json([
                    'status_code' => 100,
                    'message' => 'Selling price is more than actual price'
                ], 200);
            }

            $newCombo->save();
            $image = $this->decodeBase64Image($request->image);
            $imageName = 'combo_' . $newCombo->_id . '.' . $image['extension'];
            $imagePath = 'public/product/' . $imageName;
            Storage::put($imagePath, $image['data']);

            $newCombo->image = 'storage/app/public/product/' . $imageName;
            $newCombo->save();
            foreach ($productArray as $item) {
                if (isset($item['id']) && isset($item['qty'])) {
                    $combo_details = new combo_details();
                    $combo_details->product_id = $item['id'];
                    $combo_details->combo_id = $newCombo->_id;
                    $combo_details->quantity = $item['qty'];
                    $combo_details->size = (isset($item['size']) && $item['size'] !== '') ? $item['size'] : null;
                    $combo_details->save();
                }
            }
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Combo added successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($imagePath) && Storage::exists($imagePath)) {
                Storage::delete($imagePath);
            }
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to add combo.'
            ], 500);
        }
    }

    /**
     * update Combo.
     */
    public function updateCombo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'combo_id' => 'required',
            'products' => 'array',
            'veg' => 'boolean',
            'best_seller' => 'boolean',
            'recommended' => 'boolean',
            'is_available' => 'boolean',
            'delivery_selling_price' => 'sometimes|numeric',
            'dinein_selling_price' => 'sometimes|numeric',
            'pickup_selling_price' => 'sometimes|numeric',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $combo = combo::find($request->combo_id);
            if (!$combo) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Combo not found'
                ], 404);
            }

            if ($request->filled('name')) {
                $name = trim($request->name);
                $existingCombo = combo::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->where('_id', '!=', $combo->_id)->first();

                if ($existingCombo) {
                    return response()->json([
                        'status_code' => 400,
                        'message' => 'Combo name already exist'
                    ], 200);
                }
                $combo->name = $name;
            }

            $oldImage = parse_url($combo->image, PHP_URL_PATH);
            $productArray = [];
            if ($request->has('products')) {
                $productArray = $request->products;
                $validationResponse = $this->validateProducts($productArray);
                if ($validationResponse !== null) {
                    return $validationResponse;
                }

                combo_details::where('combo_id', $combo->_id)->delete();
                $newPrice = $this->calculateActualPrice($productArray);
                $combo->actual_price = $newPrice;
                foreach ($productArray as $item) {
                    if (isset($item['id']) && isset($item['qty'])) {
                        $combo_details = new combo_details();
                        $combo_details->product_id = $item['id'];
                        $combo_details->combo_id = $combo->_id;
                        $combo_details->quantity = $item['qty'];
                        $combo_details->size = (isset($item['size']) && $item['size'] !== '') ? $item['size'] : null;
                        $combo_details->save();
                    }
                }
                cart_product::where('combo_id', $combo->_id)->update(['is_update' => 1]);
            }

            if ($request->has('delivery_selling_price')) {
                $combo->delivery_selling_price = $request->delivery_selling_price;
            }
            if ($request->has('dinein_selling_price')) {
                $combo->dinein_selling_price = $request->dinein_selling_price;
            }
            if ($request->has('pickup_selling_price')) {
                $combo->pickup_selling_price = $request->pickup_selling_price;
            }
            if ($request->has('veg')) {
                $combo->veg = $request->veg;
            }
            if ($request->has('best_seller')) {
                $combo->best_seller = $request->best_seller;
            }
            if ($request->has('recommended')) {
                $combo->recommended = $request->recommended;
            }
            if ($request->has('is_available')) {
                if ($request->is_available == 1) {
                    $comboDetails = combo_details::where('combo_id', $combo->_id)->get();

                    foreach ($comboDetails as $detail) {
                        $product = product::find($detail->product_id);
                        if (!$product || $product->disable == 1 || $product->is_available == 0) {
                            return response()->json([
                                'status_code' => 104,
                                'message' => 'Product ' . $product->name . ' is either not available or disabled. Please update the product status or remove it from the combo.'
                            ], 200);
                        }

                        if ($detail->size) {
                            $productSize = product_size::where('product_id', $product->_id)
                                ->where('_id', $detail->size)
                                ->first();
                            if (!$productSize) {
                                return response()->json([
                                    'status_code' => 105,
                                    'message' => 'Product size ' . $productSize->size . ' for product ' . $product->name . ' is not available. Please update the product size in combo or remove product from combo.'
                                ], 200);
                            }
                        }
                    }
                }
                $combo->is_available = $request->is_available;
            }
            if ($request->filled('image')) {
                $image = $this->decodeBase64Image($request->image);
                $imageName = 'combo_' . $combo->_id . '.' . $image['extension'];
                $imagePath = 'public/product/' . $imageName;
                Storage::put($imagePath, $image['data']);
                $path = str_replace('storage/app/', '', $oldImage);
                if ($path !== $imagePath) {
                    if ($oldImage && Storage::exists($path)) {
                        Storage::delete($path);
                    }
                }
                $combo->image = 'storage/app/public/product/' . $imageName . '?timestamp=' . time();
            }
            if (($combo->delivery_selling_price > $combo->delivery_actual_price)||($combo->dinein_selling_price > $combo->dinein_actual_price)||($combo->pickup_selling_price > $combo->pickup_actual_price)) {
                return response()->json([
                    'status_code' => 100,
                    'message' => 'Selling price is more than actual price'
                ], 200);
            }
            $combo->save();
            DB::commit();

            return response()->json([
                'status_code' => 200,
                'message' => 'Combo updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update combo.'
            ], 500);
        }
    }

    /**
     * delete Combo.
     */
    public function deleteCombo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'combo_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $combo = combo::find($request->combo_id);
            if (!$combo) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Combo not found'
                ], 404);
            }
            $combo->disable = 1;
            $combo->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Combo disabled successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to disable combo.'
            ], 500);
        }
    }

    /**
     * get Combos for admin.
     */
    public function getAllCombosAdmin()
    {
        try {
            $combos = combo::all()->map(function ($combo) {
                $products = combo_details::where('combo_id', $combo->_id)
                    ->get()
                    ->map(function ($comboDetail) {
                        $product = product::where('_id', $comboDetail->product_id)
                            ->select('name', '_id')
                            ->first();
                        $productDetail = [
                            '_id' => $product->_id,
                            'name' => $product->name,
                            'quantity' => $comboDetail->quantity,
                            'size' => null,
                        ];
                        if ($comboDetail->size) {
                            $product_size = product_size::where('_id', $comboDetail->size)
                                ->select('_id', 'size')
                                ->first();

                            if ($product_size) {
                                $productDetail['size'] = [
                                    '_id' => $product_size->_id,
                                    'name' => $product_size->size,
                                ];
                            }
                        }

                        return $productDetail;
                    });
                $combo->products = $products;
                return $combo;
            });
            return response()->json([
                'status_code' => 200,
                'data' => $combos,
                'message' => 'Combos retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve combos.'
            ], 500);
        }
    }

    /**
     * Get Combos for customer.
     */
    public function getAllCombosCustomer()
    {
        try {
            $combos = combo::where('disable', 0)->get()->map(function ($combo) {
                $products = combo_details::where('combo_id', $combo->_id)
                    ->get()
                    ->map(function ($comboDetail) {

                        $product = product::where('_id', $comboDetail->product_id)
                            ->select('name', '_id')
                            ->first();
                        $productDetail = [
                            '_id' => $product->_id,
                            'name' => $product->name,
                            'quantity' => $comboDetail->quantity,
                            'size' => null,
                        ];

                        if ($comboDetail->size) {
                            $product_size = product_size::where('_id', $comboDetail->size)
                                ->select('_id', 'size')
                                ->first();

                            if ($product_size) {
                                $productDetail['size'] = $product_size->size;
                            }
                        }
                        return $productDetail;
                    });
                // Constructing the description
                $description = $products->map(function ($product) {
                    return $product['quantity'] . ' ' . $product['name'] . ' ' . ($product['size'] ? '(' . $product['size'] . ') ' : '');
                })->join('+ ');
                // $combo->products = $products;
                $combo->description = $description;
                $combo->combo = 1;
                return $combo;
            });
            return response()->json([
                'status_code' => 200,
                'data' => $combos,
                'message' => 'Combos retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve combos.'
            ], 500);
        }
    }

    /**
     * Calculate the actual price of the combo.
     */
    private function calculateActualPrice($products)
    {
        $deliveryActualPrice = 0;
        $dineinActualPrice = 0;
        $pickupActualPrice = 0;

        foreach ($products as $item) {
            $product = product::find($item['id']);
            if (!$product) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Product with id ' . $item['id'] . ' not found.'
                ], 404);
            }
            $quantity = $item['qty'];

            if (isset($item['size']) && $item['size'] !== '') {
                $product_size = product_size::where('product_id', $product->_id)
                    ->where('_id', $item['size'])
                    ->first();
                if ($product_size) {
                    $deliveryActualPrice += $product_size->delivery_selling_price * $quantity;
                    $dineinActualPrice += $product_size->dinein_selling_price * $quantity;
                    $pickupActualPrice += $product_size->pickup_selling_price * $quantity;
                    // $actualPrice += $product_size->selling_price * $quantity;
                } else {
                    return response()->json([
                        'status_code' => 102,
                        'message' => 'Invalid size for product ' . $product->name
                    ], 200);
                }
            } else {
                $deliveryActualPrice += $product->delivery_selling_price * $quantity;
                $dineinActualPrice += $product->dinein_selling_price * $quantity;
                $pickupActualPrice += $product->pickup_selling_price * $quantity;
                // $actualPrice += $product->selling_price * $quantity;
            }
        }
        return [
            'deliveryActualPrice' => $deliveryActualPrice,
            'dineinActualPrice' => $dineinActualPrice,
            'pickupActualPrice' => $pickupActualPrice
        ];
        // return $actualPrice;
    }

    /**
     * Validate products in combo.
     */
    private function validateProducts($productArray)
    {
        if (count($productArray) < 2) {
            return response()->json([
                'status_code' => 422,
                'message' => 'There should be atleast 2 products in combo'
            ], 200);
        }
        foreach ($productArray as $item) {
            if (!isset($item['id']) || !isset($item['qty'])) {
                return response()->json([
                    'status_code' => 100,
                    'message' => 'Invalid products array'
                ], 200);
            }
            $product = product::find($item['id']);
            if (!$product) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Product with id ' . $item['id'] . ' not found.'
                ], 404);
            }
            if ($item['qty'] <= 0 || preg_match('/[A-Za-z]/', $item['qty'])) {
                return response()->json([
                    'status_code' => 101,
                    'message' => 'Invalid quantity for product ' .  $product->name
                ], 200);
            }
            if (isset($item['size']) && $item['size'] !== '') {
                $product_size = product_size::where('product_id', $product->_id)->where('_id', $item['size'])->first();
                if (!$product_size) {
                    return response()->json([
                        'status_code' => 102,
                        'message' => 'Invalid size for product ' . $product->name
                    ], 200);
                }
            }
        }
        return null;
    }

    /**
     * Get product to add in combo.
     */
    public function getProductsForCombo()
    {
        try {
            $products = product::where('disable', 0)->where('is_available', 1)->get()
                ->each(function ($product) {
                    $product->sizes = product_size::where('product_id', $product->_id)->get()->makeHidden('product_id');
                });

            return response()->json([
                'status_code' => 200,
                'data' => $products,
                'message' => 'Products retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve products.'
            ], 500);
        }
    }
}
