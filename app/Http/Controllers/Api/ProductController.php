<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\combo;
use App\Models\combo_details;
use App\Models\home_slider;
use App\Models\product;
use App\Models\product_category;
use App\Models\product_size;
use App\Models\{cart_product, customization, video, RelatedProducts};
use App\Traits\ImageHandleTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    use ImageHandleTrait;
    /**
     * Add Product.
     */
    public function addProduct(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'image' => 'required',
            'veg' => 'required|boolean',
            'best_seller' => 'boolean',
            'recommended' => 'boolean',
            'is_available' => 'boolean',
            'only_combo' => 'boolean',
            'delivery_actual_price' => 'required|numeric|min:0.01',
            'delivery_selling_price' => 'required|numeric|min:0.01|lte:delivery_actual_price',
            'pickup_actual_price' => 'required|numeric|min:0.01',
            'pickup_selling_price' => 'required|numeric|min:0.01|lte:pickup_actual_price',
            'dinein_actual_price' => 'required|numeric|min:0.01',
            'dinein_selling_price' => 'required|numeric|min:0.01|lte:dinein_actual_price',
            'category_id' => 'required',
            'customization' => 'array|nullable',
        ], [
            'delivery_selling_price.lte' => 'The selling price must be less than or equal to the actual price.',
            'pickup_selling_price.lte' => 'The selling price must be less than or equal to the actual price.',
            'dinein_selling_price.lte' => 'The selling price must be less than or equal to the actual price.',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();








        try {
            $category = product_category::find($request->category_id);
            if (!$category) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Product category not found'
                ], 404);
            }

            $name = trim($request->name);
            $existingProduct = product::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->where('product_category_id', $category->_id)->first();

            if ($existingProduct) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Product already exists in ' . $category->name
                ], 200);
            }

            $product = new product();
            $product->name = $name;
            $product->arabic_name = $request->arabic_name;
            $product->veg = $request->veg;
            $product->delivery_actual_price = $request->delivery_actual_price;
            $product->delivery_selling_price = $request->delivery_selling_price;
            $product->pickup_actual_price = $request->pickup_actual_price;
            $product->pickup_selling_price = $request->pickup_selling_price;
            $product->dinein_actual_price = $request->dinein_actual_price;
            $product->dinein_selling_price = $request->dinein_selling_price;
            $product->description =  $request->description;
            $product->arabic_description =  $request->arabic_description;
            $product->best_seller = $request->has('best_seller') ? $request->best_seller : 0;
            $product->recommended = $request->has('recommended') ? $request->recommended : 0;
            $product->is_available = $request->has('is_available') ? $request->is_available : 1;
            $product->only_combo = $request->has('only_combo') ? $request->only_combo : 0;
            $product->disable = 0;
            $product->image = '';
            $product->product_category_id = $request->category_id;

            if ($request->filled('customization')) {
                if (!empty($request->customization)) {
                    $filteredCustomization = [];
                    foreach ($request->customization as $customization_id) {
                        $customization = customization::find($customization_id);
                        if ($customization) {
                            $filteredCustomization[] = $customization->_id;
                        }
                    }
                    $product->customization = !empty($filteredCustomization) ? json_encode($filteredCustomization) : null;
                }
            }

            $product->save();
            $image = $this->decodeBase64Image($request->image);
            $imageName = 'product_' . $product->_id . '.' . $image['extension'];
            $imagePath = 'public/product/' . $imageName;
            Storage::put($imagePath, $image['data']);

            $product->image = 'storage/app/public/product/' . $imageName;
            $product->save();


            $relatedProductsIds = $request->input('related_product_ids');

            if (!empty($relatedProductsIds)) {
                foreach ($relatedProductsIds as $relatedProductId) {
                    $relatedProduct = product::find($relatedProductId);
                    if($relatedProduct)
                    {
                        RelatedProducts::create([
                            'product_id' => $product->_id,
                            'related_product_id' => $relatedProductId,
                            'category_id' => $relatedProduct->product_category_id,
                            'added_by' => Auth::id(),
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'status_code' => 200,
                'product_id' => $product->_id,
                'message' => 'Product added successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($imagePath) && Storage::exists($imagePath)) {
                Storage::delete($imagePath);
            }
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to add product.'
            ], 500);
        }
    }

    /**
     * Update Product.
     */
    public function updateProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            'veg' => 'boolean',
            'best_seller' => 'boolean',
            'recommended' => 'boolean',
            'is_available' => 'boolean',
            'only_combo' => 'boolean',
            'category_id' => 'sometimes|min:1',
            'delivery_actual_price' => 'sometimes|numeric|min:0.01',
            'delivery_selling_price' => 'sometimes|numeric|min:0.01',
            'pickup_actual_price' => 'sometimes|numeric|min:0.01',
            'pickup_selling_price' => 'sometimes|numeric|min:0.01',
            'dinein_actual_price' => 'sometimes|numeric|min:0.01',
            'dinein_selling_price' => 'sometimes|numeric|min:0.01',
            'customization' => 'array|nullable',
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
                    'message' => 'Product not found'
                ], 404);
            }

            if ($request->has('category_id')) {
                $category = product_category::find($request->category_id);
                if (!$category) {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'Product category not found'
                    ], 404);
                }
                $product->product_category_id = $request->category_id;
            }

            if ($request->filled('name')) {
                $name = trim($request->name);
                $existingProduct = product::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->where('_id', '!=', $request->product_id)->first();

                if ($existingProduct) {
                    return response()->json([
                        'status_code' => 400,
                        'message' => 'Product already exists in ' . $category->name
                    ], 200);
                }
                $product->name = $name;
            }
            if ($request->has('arabic_name')) {
                $product->arabic_name = $request->arabic_name;
            }
            $deliveryOldPrice = $product->delivery_selling_price;
            $dineinOldPrice = $product->dinein_selling_price;
            $pickupOldPrice = $product->pickup_selling_price;
            $oldImage = parse_url($product->image, PHP_URL_PATH);
            $product->description = $request->description;
            if($request->has('arabic_description'))
            {
                $product->arabic_description = $request->arabic_description;
            }
            if ($request->has('veg')) {
                $product->veg = $request->veg;
            }
            if ($request->has('best_seller')) {
                $product->best_seller = $request->best_seller;
            }
            if ($request->has('recommended')) {
                $product->recommended = $request->recommended;
            }
            if ($request->has('only_combo')) {
                $product->only_combo = $request->only_combo;
            }
            if ($request->has('delivery_actual_price')) {
                $product->delivery_actual_price = $request->delivery_actual_price;
            }
            if ($request->has('delivery_selling_price')) {
                $product->delivery_selling_price = $request->delivery_selling_price;
            }
            if ($product->delivery_selling_price > $product->delivery_actual_price) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Delivery selling price of product is more than actual price'
                ], 200);
            }
            if ($request->has('pickup_actual_price')) {
                $product->pickup_actual_price = $request->pickup_actual_price;
            }
            if ($request->has('pickup_selling_price')) {
                $product->pickup_selling_price = $request->pickup_selling_price;
            }
            if ($product->pickup_selling_price > $product->pickup_actual_price) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Pickup selling price of product is more than actual price'
                ], 200);
            }
            if ($request->has('dinein_actual_price')) {
                $product->dinein_actual_price = $request->dinein_actual_price;
            }
            if ($request->has('dinein_selling_price')) {
                $product->dinein_selling_price = $request->dinein_selling_price;
            }
            if ($product->dinein_selling_price > $product->dinein_actual_price) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Dine In selling price of product is more than actual price'
                ], 200);
            }
            if ($request->has('is_available')) {
                if ($request->is_available == 0) {
                    $existInCombo = combo_details::join('combos', 'combo_details.combo_id', '=', 'combos._id')
                        ->where('combo_details.product_id', $product->_id)
                        ->where('combos.disable', 0)
                        ->exists();
                    if ($existInCombo) {
                        return response()->json([
                            'status_code' => 100,
                            'message' => 'This product is part of an active combo. Please remove the product from the combo or disable the combo before marking the product as unavailable.'
                        ], 200);
                    }
                }
                $product->is_available = $request->is_available;
            }
            if ($request->filled('image')) {
                $image = $this->decodeBase64Image($request->image);
                $imageName = 'product_' . $product->_id . '.' . $image['extension'];
                $imagePath = 'public/product/' . $imageName;
                Storage::put($imagePath, $image['data']);

                $path = str_replace('storage/app/', '', $oldImage);
                if ($path !== $imagePath) {
                    if ($oldImage && Storage::exists($path)) {
                        Storage::delete($path);
                    }
                }
                $product->image = 'storage/app/public/product/' . $imageName . '?timestamp=' . time();
            }


            if ($request->has('delivery_selling_price')) {
                $sizeExist = product_size::where('product_id', $product->_id)->exists();
                if (!$sizeExist) {
                    $combos = combo::join('combo_details', 'combos._id', '=', 'combo_details.combo_id')
                        ->where('combo_details.product_id', $product->_id)
                        ->whereNull('combo_details.size')
                        ->select('combos._id', 'combos.delivery_actual_price', 'combo_details.quantity', 'combo_details.size')
                        ->get();

                    foreach ($combos as $combo) {
                        $comboModel = combo::find($combo->_id);
                        $newTotalPrice = $comboModel->delivery_actual_price - ($deliveryOldPrice * $combo->quantity) + ($product->delivery_selling_price * $combo->quantity);
                        $comboModel->delivery_actual_price = $newTotalPrice;
                        if ($comboModel->delivery_selling_price > $comboModel->delivery_actual_price) {
                            return response()->json([
                                'status_code' => 100,
                                'message' => 'Delivery selling price will be more than actual price for combo ' . $comboModel->name . ' if price of the ' . $product->name . ' will change.'
                            ], 200);
                        }
                        $comboModel->save();
                    }
                }
            }
            if ($request->has('dinein_selling_price')) {
                $sizeExist = product_size::where('product_id', $product->_id)->exists();
                if (!$sizeExist) {
                    $combos = combo::join('combo_details', 'combos._id', '=', 'combo_details.combo_id')
                        ->where('combo_details.product_id', $product->_id)
                        ->whereNull('combo_details.size')
                        ->select('combos._id', 'combos.dinein_actual_price', 'combo_details.quantity', 'combo_details.size')
                        ->get();

                    foreach ($combos as $combo) {
                        $comboModel = combo::find($combo->_id);
                        $newTotalPrice = $comboModel->dinein_actual_price - ($dineinOldPrice * $combo->quantity) + ($product->dinein_selling_price * $combo->quantity);
                        $comboModel->dinein_actual_price = $newTotalPrice;
                        if ($comboModel->dinein_selling_price > $comboModel->dinein_actual_price) {
                            return response()->json([
                                'status_code' => 100,
                                'message' => 'Dine In selling price will be more than actual price for combo ' . $comboModel->name . ' if price of the ' . $product->name . ' will change.'
                            ], 200);
                        }
                        $comboModel->save();
                    }
                }
            }
            if ($request->has('pickup_selling_price')) {
                $sizeExist = product_size::where('product_id', $product->_id)->exists();
                if (!$sizeExist) {
                    $combos = combo::join('combo_details', 'combos._id', '=', 'combo_details.combo_id')
                        ->where('combo_details.product_id', $product->_id)
                        ->whereNull('combo_details.size')
                        ->select('combos._id', 'combos.pickup_actual_price', 'combo_details.quantity', 'combo_details.size')
                        ->get();

                    foreach ($combos as $combo) {
                        $comboModel = combo::find($combo->_id);
                        $newTotalPrice = $comboModel->pickup_actual_price - ($pickupOldPrice * $combo->quantity) + ($product->pickup_selling_price * $combo->quantity);
                        $comboModel->pickup_actual_price = $newTotalPrice;
                        if ($comboModel->pickup_selling_price > $comboModel->pickup_actual_price) {
                            return response()->json([
                                'status_code' => 100,
                                'message' => 'Pickup selling price will be more than actual price for combo ' . $comboModel->name . ' if price of the ' . $product->name . ' will change.'
                            ], 200);
                        }
                        $comboModel->save();
                    }
                }
            }
            if ($request->has('customization')) {
                if (is_null($request->customization) || empty($request->customization)) {
                    $product->customization = null;
                } else {
                    $filteredCustomization = [];
                    foreach ($request->customization as $customization_id) {
                        $customization = customization::find($customization_id);
                        if ($customization) {
                            $filteredCustomization[] = $customization->_id;
                        }
                    }
                    $product->customization = !empty($filteredCustomization) ? json_encode($filteredCustomization) : null;
                }
            }
            $product->save();

            $productId = $request->input('product_id');

            $relatedProductsIds = $request->input('related_product_ids');

            if (!empty($relatedProductsIds)) {
                RelatedProducts::where('product_id', $productId)->delete();
                foreach ($relatedProductsIds as $relatedProductId) {
                    $relatedProduct = product::find($relatedProductId);
                    if($relatedProduct)
                    {
                        RelatedProducts::create([
                            'product_id' => $product->_id,
                            'related_product_id' => $relatedProductId,
                            'category_id' => $relatedProduct->product_category_id,
                            'added_by' => Auth::id(),
                        ]);
                    }
                }
            }
            else
            {
                RelatedProducts::where('product_id', $productId)->delete();
            }
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Product updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update product.'
            ], 500);
        }
    }

    /**
     * Delete Product.
     */
    public function deleteProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
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
                    'message' => 'Product not found'
                ], 404);
            }

            $product->disable = 1;
            $product->best_seller = 0;
            $product->recommended = 0;
            $product->is_available = 0;
            if ($product->customization) {
                $customizationIds = json_decode($product->customization, true);
                if (!empty($customizationIds)) {
                    foreach ($customizationIds as $customizationId) {
                        $cart_products = cart_product::where('product_id', $product->_id)->whereNotNull('customization')->whereRaw("JSON_CONTAINS(customization, $customizationId)")->get();
                        foreach ($cart_products as $cart_product) {
                            $customizationCartProductIds = json_decode($cart_product->customization, true);
                            if (($key = array_search($customizationId, $customizationCartProductIds)) !== false) {
                                unset($customizationCartProductIds[$key]);
                            }
                            if (empty($customizationCartProductIds)) {
                                $cart_product->customization = null;
                            } else {
                                $cart_product->customization = json_encode(array_values($customizationCartProductIds));
                            }
                            $cart_product->save();
                        }
                    }
                }
            }

            $existInCombo = combo_details::join('combos', 'combo_details.combo_id', '=', 'combos._id')
                ->where('combo_details.product_id', $product->_id)
                ->where('combos.disable', 0)
                ->exists();
            if ($existInCombo) {
                return response()->json([
                    'status_code' => 100,
                    'message' => 'This product is part of an active combo. Please remove the product from the combo or disable the combo before disabling the product.'
                ], 200);
            }

            $product->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Product disabled successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to disable product.'
            ], 500);
        }
    }

    /**
     * Get all product by category admin.
     */
    public function getAllProductByCategoryAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        try {
            $category = product_category::find($request->category_id);

            if (!$category) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Product category not found'
                ], 404);
            }

            $products = product::with('relatedProducts')->where('product_category_id', $category->_id)->get()
                ->each(function ($product) {
                    $product->sizes = product_size::where('product_id', $product->_id)->get()->makeHidden('product_id');
                    $customizationIds = json_decode($product->customization, true);
                    if (!is_null($customizationIds) && is_array($customizationIds)) {
                        $customizations = customization::whereIn('_id', $customizationIds)->get();
                    } else {
                        $customizations = null;
                    }
                    $product->customization = $customizations;
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

    /**
     * Get all product by category customer.
     */
    public function getAllProductByCategoryCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        try {
            if ($request->category_id === 'combo') {
                $products = combo::where('disable', 0)->get()->map(function ($combo) {
                    $products = combo_details::where('combo_id', $combo->_id)
                        ->get()
                        ->map(function ($comboDetail) {

                            $product = product::where('_id', $comboDetail->product_id)
                                ->select('name','arabic_name', '_id')
                                ->first();
                            $productDetail = [
                                '_id' => $product->_id,
                                'name' => $product->name,
                                'arabic_name' => $product->arabic_name,
                                'quantity' => $comboDetail->quantity,
                                'size' => null,
                            ];

                            if ($comboDetail->size) {
                                $product_size = product_size::where('_id', $comboDetail->size)
                                    ->select('_id', 'size','arabic_size')
                                    ->first();

                                if ($product_size) {
                                    $productDetail['size'] = $product_size->size;
                                    $productDetail['arabic_size'] = $product_size->arabic_size;
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
            } else {
                $category = product_category::find($request->category_id);
                if (!$category) {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'Product category not found'
                    ], 404);
                }

                $products = product::with('relatedProducts')
                    ->where('product_category_id', operator: $category->_id)
                    ->where('disable', 0)->where('only_combo', 0)->get()
                    ->each(function ($product) {
                        $product->combo = 0;
                        $sizes = product_size::where('product_id', $product->_id)->get()->makeHidden('product_id');
                        $product->sizes = $sizes;
                        if ($sizes->isNotEmpty()) {
                            $firstSize = $sizes->first();
                            $product->delivery_actual_price = $firstSize->delivery_actual_price;
                            $product->delivery_selling_price = $firstSize->delivery_selling_price;
                            $product->pickup_actual_price = $firstSize->pickup_actual_price;
                            $product->pickup_selling_price = $firstSize->pickup_selling_price;
                            $product->dinein_actual_price = $firstSize->dinein_actual_price;
                            $product->dinein_selling_price = $firstSize->dinein_selling_price;
                            // $product->actual_price = $firstSize->actual_price;
                            // $product->selling_price = $firstSize->selling_price;
                        }
                        $customizationIds = json_decode($product->customization, true);
                        if (!is_null($customizationIds) && is_array($customizationIds)) {
                            $customizations = customization::whereIn('_id', $customizationIds)->get();
                        } else {
                            $customizations = null;
                        }
                        $product->customization = $customizations;
                        $product->relatedProducts = $product->relatedProducts->map(function ($relatedProduct) {
                            $category = product_category::find($relatedProduct->product_category_id);
                            return array_merge(
                                $relatedProduct->toArray(),
                                [
                                    'category' => $category ? $category->toArray() : null,
                                ]
                            );
                        });
                       
                    })
                    ->makeHidden(['disable', 'only_combo']);
            }

            return response()->json([
                'status_code' => 200,
                'data' => $products,
                'message' => 'Products retrieved successfully1'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve products.'
            ], 500);
        }
    }

    /**
     * Get best seller products.
     */
    public function getBestSeller()
    {
        try {
            $products = product::where('best_seller', 1)->where('disable', 0)->where('only_combo', 0)->get()
                ->each(function ($product) {
                    $product->combo = 0;
                    $sizes = product_size::where('product_id', $product->_id)->get()->makeHidden('product_id');
                    $product->sizes = $sizes;
                    if ($sizes->isNotEmpty()) {
                        $firstSize = $sizes->first();
                        $product->delivery_actual_price = $firstSize->delivery_actual_price;
                        $product->delivery_selling_price = $firstSize->delivery_selling_price;
                        $product->pickup_actual_price = $firstSize->pickup_actual_price;
                        $product->pickup_selling_price = $firstSize->pickup_selling_price;
                        $product->dinein_actual_price = $firstSize->dinein_actual_price;
                        $product->dinein_selling_price = $firstSize->dinein_selling_price;
                        // $product->actual_price = $firstSize->actual_price;
                        // $product->selling_price = $firstSize->selling_price;
                    }
                })
                ->makeHidden(['disable', 'only_combo']);
            $combos = combo::where('disable', 0)->where('best_seller', 1)->get()->map(function ($combo) {
                $products = combo_details::where('combo_id', $combo->_id)
                    ->get()
                    ->map(function ($comboDetail) {

                        $product = product::where('_id', $comboDetail->product_id)
                            ->select('name','arabic_name', '_id')
                            ->first();
                        $productDetail = [
                            '_id' => $product->_id,
                            'name' => $product->name,
                            'arabic_name' => $product->arabic_name,
                            'quantity' => $comboDetail->quantity,
                            'size' => null,
                        ];

                        if ($comboDetail->size) {
                            $product_size = product_size::where('_id', $comboDetail->size)
                                ->select('_id', 'size','arabic_size')
                                ->first();

                            if ($product_size) {
                                $productDetail['size'] = $product_size->size;
                                $productDetail['arabic_size'] = $product_size->arabic_size;
                            }
                        }
                        return $productDetail;
                    });
                // Constructing the description
                $description = $products->map(function ($product) {
                    return $product['quantity'] . ' ' . $product['name'] . ' ' . ($product['size'] ? '(' . $product['size'] . ') ' : '');
                })->join('+ ');
                $combo->description = $description;
                $combo->combo = 1;
                return $combo;
            });
            $data = $products->concat($combos);
            return response()->json([
                'status_code' => 200,
                'data' => $data,
                'message' => 'Best seller products retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve best seller products.'
            ], 500);
        }
    }

    /**
     * Get recommended products.
     */
    public function getRecommendeds()
    {
        try {
            $products = product::where('recommended', 1)->where('disable', 0)->where('only_combo', 0)->get()
                ->each(function ($product) {
                    $product->combo = 0;
                    $sizes = product_size::where('product_id', $product->_id)->get()->makeHidden('product_id');
                    $product->sizes = $sizes;
                    if ($sizes->isNotEmpty()) {
                        $firstSize = $sizes->first();
                        // $product->actual_price = $firstSize->actual_price;
                        // $product->selling_price = $firstSize->selling_price;
                        $product->delivery_actual_price = $firstSize->delivery_actual_price;
                        $product->delivery_selling_price = $firstSize->delivery_selling_price;
                        $product->pickup_actual_price = $firstSize->pickup_actual_price;
                        $product->pickup_selling_price = $firstSize->pickup_selling_price;
                        $product->dinein_actual_price = $firstSize->dinein_actual_price;
                        $product->dinein_selling_price = $firstSize->dinein_selling_price;
                    }
                })
                ->makeHidden(['disable', 'only_combo']);

            $combos = combo::where('disable', 0)->where('recommended', 1)->get()->map(function ($combo) {
                $products = combo_details::where('combo_id', $combo->_id)
                    ->get()
                    ->map(function ($comboDetail) {

                        $product = product::where('_id', $comboDetail->product_id)
                            ->select('name','arabic_name', '_id')
                            ->first();
                        $productDetail = [
                            '_id' => $product->_id,
                            'name' => $product->name,
                            'arabic_name' => $product->arabic_name,
                            'quantity' => $comboDetail->quantity,
                            'size' => null,
                        ];

                        if ($comboDetail->size) {
                            $product_size = product_size::where('_id', $comboDetail->size)
                                ->select('_id', 'size','arabic_size')
                                ->first();

                            if ($product_size) {
                                $productDetail['size'] = $product_size->size;
                                $productDetail['arabic_size'] = $product_size->arabic_size;
                            }
                        }
                        return $productDetail;
                    });
                // Constructing the description
                $description = $products->map(function ($product) {
                    return $product['quantity'] . ' ' . $product['name'] . ' ' . ($product['size'] ? '(' . $product['size'] . ') ' : '');
                })->join('+ ');
                $combo->description = $description;
                $combo->combo = 1;
                return $combo;
            });
            $data = $products->concat($combos);
            return response()->json([
                'status_code' => 200,
                'data' => $data,
                'message' => 'Recommended products retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve recommended products.'
            ], 500);
        }
    }

    /**
     * Get serached products.
     */
    public function searchProduct(Request $request)
    {
        try {
            if ($request->has('value')) {
                $value = $request->value;
                $products = product::with('relatedProducts')->where('name', 'LIKE', "%$value%")->where('disable', 0)->where('only_combo', 0)->get()
                    ->each(function ($product) {
                        $product->combo = 0;
                        $sizes = product_size::where('product_id', $product->_id)->get()->makeHidden('product_id');
                        $product->sizes = $sizes;
                        if ($sizes->isNotEmpty()) {
                            $firstSize = $sizes->first();
                            // $product->actual_price = $firstSize->actual_price;
                            // $product->selling_price = $firstSize->selling_price;
                            $product->delivery_actual_price = $firstSize->delivery_actual_price;
                            $product->delivery_selling_price = $firstSize->delivery_selling_price;
                            $product->pickup_actual_price = $firstSize->pickup_actual_price;
                            $product->pickup_selling_price = $firstSize->pickup_selling_price;
                            $product->dinein_actual_price = $firstSize->dinein_actual_price;
                            $product->dinein_selling_price = $firstSize->dinein_selling_price;
                        }
                        $customizationIds = json_decode($product->customization, true);
                        if (!is_null($customizationIds) && is_array($customizationIds)) {
                            $customizations = customization::whereIn('_id', $customizationIds)->get();
                        } else {
                            $customizations = null;
                        }
                        $product->customization = $customizations;
                        $product->relatedProducts = $product->relatedProducts->map(function ($relatedProduct) {
                            $category = product_category::find($relatedProduct->product_category_id);
                            return array_merge(
                                $relatedProduct->toArray(),
                                [
                                    'category' => $category ? $category->toArray() : null,
                                ]
                            );
                        });
                    })
                    ->makeHidden(['disable', 'only_combo']);

                $combos = combo::where('disable', 0)->where('name', 'LIKE', "%$value%")->get()->map(function ($combo) {
                    $products = combo_details::where('combo_id', $combo->_id)
                        ->get()
                        ->map(function ($comboDetail) {

                            $product = product::where('_id', $comboDetail->product_id)
                                ->select('name','arabic_name', '_id')
                                ->first();
                            $productDetail = [
                                '_id' => $product->_id,
                                'name' => $product->name,
                                'arabic_name' => $product->arabic_name,
                                'quantity' => $comboDetail->quantity,
                                'size' => null,
                            ];

                            if ($comboDetail->size) {
                                $product_size = product_size::where('_id', $comboDetail->size)
                                    ->select('_id', 'size','arabic_size')
                                    ->first();

                                if ($product_size) {
                                    $productDetail['size'] = $product_size->size;
                                    $productDetail['arabic_size'] = $product_size->arabic_size;
                                }
                            }
                            return $productDetail;
                        });
                    // Constructing the description
                    $description = $products->map(function ($product) {
                        return $product['quantity'] . ' ' . $product['name'] . ' ' . ($product['size'] ? '(' . $product['size'] . ') ' : '');
                    })->join('+ ');
                    $combo->description = $description;
                    $combo->combo = 1;
                    return $combo;
                });
            } else {
                $products = product::with('relatedProducts')->where('disable', 0)->where('only_combo', 0)->get()
                    ->each(function ($product) {
                        $product->combo = 0;
                        $sizes = product_size::where('product_id', $product->_id)->get()->makeHidden('product_id');
                        $product->sizes = $sizes;
                        if ($sizes->isNotEmpty()) {
                            $firstSize = $sizes->first();
                            // $product->actual_price = $firstSize->actual_price;
                            // $product->selling_price = $firstSize->selling_price;
                            $product->delivery_actual_price = $firstSize->delivery_actual_price;
                            $product->delivery_selling_price = $firstSize->delivery_selling_price;
                            $product->pickup_actual_price = $firstSize->pickup_actual_price;
                            $product->pickup_selling_price = $firstSize->pickup_selling_price;
                            $product->dinein_actual_price = $firstSize->dinein_actual_price;
                            $product->dinein_selling_price = $firstSize->dinein_selling_price;
                        }
                        $customizationIds = json_decode($product->customization, true);
                        if (!is_null($customizationIds) && is_array($customizationIds)) {
                            $customizations = customization::whereIn('_id', $customizationIds)->get();
                        } else {
                            $customizations = null;
                        }
                        $product->customization = $customizations;
                        $product->relatedProducts = $product->relatedProducts->map(function ($relatedProduct) {
                            $category = product_category::find($relatedProduct->product_category_id);
                            return array_merge(
                                $relatedProduct->toArray(),
                                [
                                    'category' => $category ? $category->toArray() : null,
                                ]
                            );
                        });
                    })
                    ->makeHidden(['disable', 'only_combo']);

                $combos = combo::where('disable', 0)->get()->map(function ($combo) {
                    $products = combo_details::where('combo_id', $combo->_id)
                        ->get()
                        ->map(function ($comboDetail) {

                            $product = product::where('_id', $comboDetail->product_id)
                                ->select('name','arabic_name', '_id')
                                ->first();
                            $productDetail = [
                                '_id' => $product->_id,
                                'name' => $product->name,
                                'arabic_name' => $product->arabic_name,
                                'quantity' => $comboDetail->quantity,
                                'size' => null,
                            ];

                            if ($comboDetail->size) {
                                $product_size = product_size::where('_id', $comboDetail->size)
                                    ->select('_id', 'size','arabic_size')
                                    ->first();

                                if ($product_size) {
                                    $productDetail['size'] = $product_size->size;
                                    $productDetail['arabic_size'] = $product_size->arabic_size;
                                }
                            }
                            return $productDetail;
                        }); // Constructing the description
                    $description = $products->map(function ($product) {
                        return $product['quantity'] . ' ' . $product['name'] . ' ' . ($product['size'] ? '(' . $product['size'] . ') ' : '');
                    })->join('+ ');
                    $combo->description = $description;
                    $combo->combo = 1;
                    return $combo;
                });
            }
            $data = $products->concat($combos);
            return response()->json([
                'status_code' => 200,
                'data' => $data,
                'message' => 'Searched products retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve searched products.'
            ], 500);
        }
    }

    /**
     * Get all home screen details (product category , recommended , best seller , slider , videos , ).
     */
    public function getAllHomeDetails()
    {
        try {

            //sliders
            $sliders = home_slider::all();

            //videos
            $videos = video::all();

            //product categories
            $categories = product_category::whereHas('products', function (Builder $query) {
                $query->where('disable', 0)
                    ->where('only_combo', 0);
            })->get();

            //best sellers
            $best_seller_products = product::where('best_seller', 1)->where('disable', 0)->where('only_combo', 0)->get()
                ->each(function ($product) {
                    $product->combo = 0;
                    $sizes = product_size::where('product_id', $product->_id)->get()->makeHidden('product_id');
                    $product->sizes = $sizes;
                    if ($sizes->isNotEmpty()) {
                        $firstSize = $sizes->first();
                        // $product->actual_price = $firstSize->actual_price;
                        // $product->selling_price = $firstSize->selling_price;
                        $product->delivery_actual_price = $firstSize->delivery_actual_price;
                        $product->delivery_selling_price = $firstSize->delivery_selling_price;
                        $product->pickup_actual_price = $firstSize->pickup_actual_price;
                        $product->pickup_selling_price = $firstSize->pickup_selling_price;
                        $product->dinein_actual_price = $firstSize->dinein_actual_price;
                        $product->dinein_selling_price = $firstSize->dinein_selling_price;
                    }
                    $customizationIds = json_decode($product->customization, true);
                    if (!is_null($customizationIds) && is_array($customizationIds)) {
                        $customizations = customization::whereIn('_id', $customizationIds)->get();
                    } else {
                        $customizations = null;
                    }
                    $product->customization = $customizations;
                })
                ->makeHidden(['disable', 'only_combo']);
            $best_seller_combos = combo::where('disable', 0)->where('best_seller', 1)->get()->map(function ($combo) {
                $products = combo_details::where('combo_id', $combo->_id)
                    ->get()
                    ->map(function ($comboDetail) {

                        $product = product::where('_id', $comboDetail->product_id)
                            ->select('name','arabic_name', '_id')
                            ->first();
                        $productDetail = [
                            '_id' => $product->_id,
                            'name' => $product->name,
                            'arabic_name' => $product->arabic_name,
                            'quantity' => $comboDetail->quantity,
                            'size' => null,
                        ];

                        if ($comboDetail->size) {
                            $product_size = product_size::where('_id', $comboDetail->size)
                                ->select('_id', 'size','arabic_size')
                                ->first();

                            if ($product_size) {
                                $productDetail['size'] = $product_size->size;
                                $productDetail['arabic_size'] = $product_size->arabic_size;
                            }
                        }
                        return $productDetail;
                    });
                // Constructing the description
                $description = $products->map(function ($product) {
                    return $product['quantity'] . ' ' . $product['name'] . ' ' . ($product['size'] ? '(' . $product['size'] . ') ' : '');
                })->join('+ ');
                $combo->description = $description;
                $combo->combo = 1;
                return $combo;
            });
            $best_seller_data = $best_seller_products->concat($best_seller_combos);

            //recommended products
            $recommended_products = product::where('recommended', 1)->where('disable', 0)->where('only_combo', 0)->get()
                ->each(function ($product) {
                    $product->combo = 0;
                    $sizes = product_size::where('product_id', $product->_id)->get()->makeHidden('product_id');
                    $product->sizes = $sizes;
                    if ($sizes->isNotEmpty()) {
                        $firstSize = $sizes->first();
                        // $product->actual_price = $firstSize->actual_price;
                        // $product->selling_price = $firstSize->selling_price;
                        $product->delivery_actual_price = $firstSize->delivery_actual_price;
                        $product->delivery_selling_price = $firstSize->delivery_selling_price;
                        $product->pickup_actual_price = $firstSize->pickup_actual_price;
                        $product->pickup_selling_price = $firstSize->pickup_selling_price;
                        $product->dinein_actual_price = $firstSize->dinein_actual_price;
                        $product->dinein_selling_price = $firstSize->dinein_selling_price;
                    }
                    $customizationIds = json_decode($product->customization, true);
                    if (!is_null($customizationIds) && is_array($customizationIds)) {
                        $customizations = customization::whereIn('_id', $customizationIds)->get();
                    } else {
                        $customizations = null;
                    }
                    $product->customization = $customizations;
                })
                ->makeHidden(['disable', 'only_combo']);

            $recommended_combos = combo::where('disable', 0)->where('recommended', 1)->get()->map(function ($combo) {
                $products = combo_details::where('combo_id', $combo->_id)
                    ->get()
                    ->map(function ($comboDetail) {

                        $product = product::where('_id', $comboDetail->product_id)
                            ->select('name','arabic_name', '_id')
                            ->first();
                        $productDetail = [
                            '_id' => $product->_id,
                            'name' => $product->name,
                            'arabic_name' => $product->arabic_name,
                            'quantity' => $comboDetail->quantity,
                            'size' => null,
                        ];

                        if ($comboDetail->size) {
                            $product_size = product_size::where('_id', $comboDetail->size)
                                ->select('_id', 'size','arabic_size')
                                ->first();

                            if ($product_size) {
                                $productDetail['size'] = $product_size->size;
                                $productDetail['arabic_size'] = $product_size->arabic_size;
                            }
                        }
                        return $productDetail;
                    });
                // Constructing the description
                $description = $products->map(function ($product) {
                    return $product['quantity'] . ' ' . $product['name'] . ' ' . ($product['size'] ? '(' . $product['size'] . ') ' : '');
                })->join('+ ');
                $combo->description = $description;
                $combo->combo = 1;
                return $combo;
            });
            $recommended_data = $recommended_products->concat($recommended_combos);


            return response()->json([
                'status_code' => 200,
                'sliders' => $sliders,
                'videos' => $videos,
                'product_categories' => $categories,
                'best_sellers' => $best_seller_data,
                'recommended_products' => $recommended_data,
                'message' => 'Home screen details retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve home screen details.'
            ], 500);
        }
    }

    public function getallproductsrelated()
    {
        try {
            $categories = product_category::with(['products' => function ($query) {
                $query->doesntHave('productSizes');  // Filter products with no sizes
                $query->select('_id', 'name', 'arabic_name', 'image', 'product_category_id');
            }])->get();
    
            // Filter out categories that have no products after the filtering
            $products = $categories->filter(function ($category) {
                return $category->products->isNotEmpty();  // Only keep categories with products
            })->values();
            // $products = product_category::with('products:_id,name,arabic_name,_id,image,product_category_id')->get();
            // $products = product::get(['name','arabic_name', '_id', 'image', 'product_category_id']);

            return response()->json([
                'status_code' => 200,
                'data' => $products,
                'message' => 'Products retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve product.'
            ], 500);
        }
    }
}
