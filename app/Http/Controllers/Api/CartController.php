<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\address;
use App\Models\admin;
use App\Models\cart;
use App\Models\cart_product;
use App\Models\combo;
use App\Models\coupon;
use App\Models\product;
use App\Models\product_size;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Traits\CalculateDistanceTrait;

class CartController extends Controller
{
    use CalculateDistanceTrait;
    /**
     * Add Product or Combo in Cart.
     */
    public function addToCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'combo_id' => 'required_without:product_id|integer',
            'product_id' => 'required_without:combo_id|integer',
            'product_size' => 'sometimes|integer',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $user = User::find($request->user_id);
            if (!$user) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'User not found.'
                ], 404);
            }
            //validation for combo_id or product_id or product_size exist in DB 
            if ($request->has('combo_id')) {
                $combo = combo::where('_id', $request->combo_id)->where('disable', 0)->where('is_available', 1)->first();
                if (!$combo) {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'Combo is not available.'
                    ], 404);
                }
            } else if ($request->has('product_id')) {
                $product = product::where('_id', $request->product_id)->where('disable', 0)->where('is_available', 1)->first();
                if (!$product) {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'Product is not available.'
                    ], 404);
                }
                if ($request->has('product_size')) {
                    $prosuct_size = product_size::where('_id', $request->product_size)->where('product_id', $product->_id)->first();
                    if (!$prosuct_size) {
                        return response()->json([
                            'status_code' => 404,
                            'message' => 'Product size not found for product.'
                        ], 404);
                    }
                }
            } else {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'product_id or combo_id not found.'
                ], 400);
            }

            $cart = cart::where('user_id', $user->_id)->first();
            if (!$cart) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Cart not found.'
                ], 400);
            }
            if (!$cart->address_id) {
                $address = address::where('user_id', $user->_id)->where('is_default', 1)->first();
                if ($address) {
                    $cart->address_id = $address->_id;
                    $cart->save();
                }
            }

            //add product or combo or change quantity of existing product or combo
            if ($request->has('combo_id')) {
                $cart_product = cart_product::where('cart_id', $cart->_id)->where('combo_id', $combo->_id)->first();
                if ($cart_product) {
                    $cart_product->quantity = $cart_product->quantity + 1;
                } else {
                    $cart_product = new cart_product();
                    $cart_product->cart_id = $cart->_id;
                    $cart_product->combo_id = $combo->_id;
                }
            } else if ($request->has('product_id')) {
                if ($request->has('product_size')) {
                    $cart_product = cart_product::where('cart_id', $cart->_id)->where('product_id', $product->_id)->where('size', $request->product_size)->first();
                } else {
                    $cart_product = cart_product::where('cart_id', $cart->_id)->where('product_id', $product->_id)->first();
                }
                if ($cart_product) {
                    $cart_product->quantity = $cart_product->quantity + 1;
                } else {
                    $cart_product = new cart_product();
                    $cart_product->cart_id = $cart->_id;
                    $cart_product->product_id = $product->_id;
                    $cart_product->size = $request->has('product_size') ? $request->product_size : null;
                }
            }
            $cart_product->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Product or Combo added successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to add product or combo in cart.'
            ], 500);
        }
    }

    /**
     * Remove Product or Combo from Cart.
     */
    public function removeFromCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required|integer',
            'combo_id' => 'required_without:product_id|integer',
            'product_id' => 'required_without:combo_id|integer',
            'product_size' => 'sometimes|integer',
            'delete' => 'required|boolean'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $cart = cart::find($request->cart_id);
            if (!$cart) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Cart not found.'
                ], 404);
            }
            //validation for combo_id or product_id or product_size exist in DB 
            if ($request->delete === 0) {
                if ($request->has('combo_id')) {
                    $combo = combo::where('_id', $request->combo_id)->where('disable', 0)->where('is_available', 1)->first();
                    if (!$combo) {
                        return response()->json([
                            'status_code' => 404,
                            'message' => 'Combo is not available.'
                        ], 404);
                    }
                } else if ($request->has('product_id')) {
                    $product = product::where('_id', $request->product_id)->where('disable', 0)->where('is_available', 1)->first();
                    if (!$product) {
                        return response()->json([
                            'status_code' => 404,
                            'message' => 'Product is not available.'
                        ], 404);
                    }
                    if ($request->has('product_size')) {
                        $prosuct_size = product_size::where('_id', $request->product_size)->where('product_id', $product->_id)->first();
                        if (!$prosuct_size) {
                            return response()->json([
                                'status_code' => 404,
                                'message' => 'Product size not found for product.'
                            ], 404);
                        }
                    }
                } else {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'product_id or combo_id not found.'
                    ], 400);
                }
            }

            if ($request->delete === 1) {
                //delete combo or product from cart
                if ($request->has('combo_id')) {
                    cart_product::where('cart_id', $cart->_id)->where('combo_id', $request->combo_id)->delete();
                } else if ($request->has('product_id')) {
                    if ($request->has('product_size')) {
                        $cart_product = cart_product::where('cart_id', $cart->_id)->where('product_id', $request->product_id)->where('size', $request->product_size)->delete();
                    } else {
                        $cart_product = cart_product::where('cart_id', $cart->_id)->where('product_id', $request->product_id)->delete();
                    }
                }
            } else {
                //remove product or combo or change quantity of existing product or combo
                if ($request->has('combo_id')) {
                    $cart_product = cart_product::where('cart_id', $cart->_id)->where('combo_id', $combo->_id)->first();
                    if ($cart_product) {
                        if ($cart_product->quantity > 1) {
                            $cart_product->quantity = $cart_product->quantity - 1;
                            $cart_product->save();
                        } else {
                            $cart_product->delete();
                        }
                    } else {
                        return response()->json([
                            'status_code' => 404,
                            'message' => 'There is no combo with this combo_id in cart'
                        ], 400);
                    }
                } else if ($request->has('product_id')) {
                    if ($request->has('product_size')) {
                        $cart_product = cart_product::where('cart_id', $cart->_id)->where('product_id', $product->_id)->where('size', $request->product_size)->first();
                    } else {
                        $cart_product = cart_product::where('cart_id', $cart->_id)->where('product_id', $product->_id)->first();
                    }
                    if ($cart_product) {
                        if ($cart_product->quantity > 1) {
                            $cart_product->quantity = $cart_product->quantity - 1;
                            $cart_product->save();
                        } else {
                            $cart_product->delete();
                        }
                    } else {
                        return response()->json([
                            'status_code' => 404,
                            'message' => 'There is no product with this product_id and product_size in cart'
                        ], 400);
                    }
                }
            }
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Product or Combo removed successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to remove product or combo in cart.'
            ], 500);
        }
    }

    /**
     * Get Cart Details.
     */
    public function getCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        try {
            $cart = cart::where('user_id', $request->user_id)->first();
            if (!$cart) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Cart not found.'
                ], 404);
            }
            DB::beginTransaction();
            if ($cart->coupon_id) {
                $coupon = coupon::find($cart->coupon_id);
                if ($coupon && now()->greaterThan($coupon->valid_until)) {
                    $cart->coupon_id = null;
                    $cart->save();
                    DB::commit();
                }
            }
            $cart->makeHidden('address_id');
            if ($cart->address_id) {
                $address = address::find($cart->address_id);
                if (!$address) {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'Address not found.'
                    ], 404);
                }
                $admin_address = admin::select('latitude', 'longitude', 'delivery_charge', 'free_upto_km', 'delivery_coverage_km')->first();
                if ($admin_address && $admin_address->latitude && $admin_address->longitude && $address->latitude && $address->longitude) {
                    $distance = $this->calculateDistance(
                        ['latitude' => $admin_address->latitude, 'longitude' => $admin_address->longitude],
                        ['latitude' => $address->latitude, 'longitude' => $address->longitude]
                    );
                    if ($admin_address->delivery_coverage_km) {
                        if ($distance > $admin_address->delivery_coverage_km) {
                            $cart->in_coverage = 0;
                        } else {
                            $cart->in_coverage = 1;
                        }
                    } else {
                        $cart->in_coverage = 1;
                    }
                    if ($admin_address->delivery_charge && $admin_address->free_upto_km && $admin_address->delivery_charge !== 0) {
                        $free_upto_km = floatval($admin_address->free_upto_km);
                        if ($distance > $free_upto_km) {
                            $charging_kms = $distance - $free_upto_km;
                            $delivery_charge = round(floatval($admin_address->delivery_charge) * $charging_kms);
                            $cart->delivery_charge = $delivery_charge === 0 ? null : $delivery_charge;
                        } else {
                            $cart->delivery_charge = null;
                        }
                    } else {
                        $cart->delivery_charge = null;
                    }

                    $cart->delivery_coverage_km = $admin_address->delivery_coverage_km;
                } else {
                    $cart->delivery_charge = null;
                    $cart->delivery_coverage_km = null;
                    $cart->in_coverage = 1;
                }
                $cart->address = $address;
            } else {
                $cart->addrress = null;
                $cart->delivery_charge = null;
                $cart->delivery_coverage_km = null;
                $cart->in_coverage = 1;
            }
            $products = cart_product::where('cart_id', $cart->_id)
                ->get()
                ->map(function ($cartDetail) {
                    $is_combo = 0;
                    if ($cartDetail->combo_id) {
                        $product = combo::where('_id', $cartDetail->combo_id)
                            ->select('_id', 'name', 'veg', 'selling_price as price', 'is_available', 'disable')
                            ->first();
                        $is_combo = 1;
                    } else {
                        $product = product::where('_id', $cartDetail->product_id)
                            ->select('_id', 'name', 'veg', 'selling_price as price', 'is_available', 'disable')
                            ->first();
                    }
                    $product->quantity = $cartDetail->quantity;
                    $product->is_update = $cartDetail->is_update;
                    $product->is_combo = $is_combo;
                    $product->size = null;
                    if ($cartDetail->size) {
                        $product_size = product_size::where('_id', $cartDetail->size)
                            ->first();

                        if ($product_size) {
                            $product_size->makeHidden(['product_id', 'actual_price', 'selling_price']);
                            $product_size->price = $product_size->selling_price;
                            $product->size = $product_size;
                            $product->price = $product_size->selling_price;
                        } else {
                            $product->size = (object) [
                                "_id" => $cartDetail->size,
                                "size" => "0",
                                "price" => "0"
                            ];
                            $product->price = 0;
                        }
                    }
                    return $product;
                });
            $cart->products = $products;
            return response()->json([
                'status_code' => 200,
                'data' => $cart,
                'message' => 'Cart retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve cart.'
            ], 500);
        }
    }

    /**
     * Update Cart.
     */
    public function updateCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required',
            // 'order_type' => 'in:Delivery,Dine In,Pickup',
            // 'table_no' => 'required_if:order_type,Dine In',
            // 'address_id' => 'required_if:order_type,Delivery|integer',
            // 'payment_method' => 'sometimes|boolean'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $cart = cart::find($request->cart_id);
            if (!$cart) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Cart not found.'
                ], 404);
            }
            if ($request->has('address_id')) {
                $address = address::find($request->address_id);
                if (!$address) {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'Address not found.'
                    ], 404);
                }
                $cart->order_type = 'Delivery';
                $cart->address_id = $request->address_id;
                $cart->table_no = null;
            }
            // if ($request->has('order_type')) {
            //     $cart->order_type = $request->order_type;
            // }
            // if ($cart->order_type === 'Dine In' && $request->has('table_no')) {
            //     $cart->table_no = $request->table_no;
            //     $cart->address_id = null;
            // } else if ($cart->order_type === 'Delivery' && $request->has('address_id')) {
            //     $address = address::find($request->address_id);
            //     if (!$address) {
            //         return response()->json([
            //             'status_code' => 404,
            //             'message' => 'Address not found.'
            //         ], 404);
            //     }
            //     $cart->address_id = $request->address_id;
            //     $cart->table_no = null;
            // } else if ($cart->order_type === 'Pickup') {
            //     $cart->address_id = null;
            //     $cart->table_no = null;
            // }
            // if ($request->has('payment_method')) {
            //     if ($cart->order_type === 'Dine In') {
            //         if ($request->payment_method) {
            //             return response()->json([
            //                 'status_code' => 400,
            //                 'message' => 'Invalid option selected for payment method'
            //             ], 400);
            //         }
            //     }
            //     $cart->payment_method = $request->payment_method;
            // }
            $cart->save();
            DB::commit();

            return response()->json([
                'status_code' => 200,
                'message' => 'Cart updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update cart.'
            ], 500);
        }
    }
}
