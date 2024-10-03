<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\cart;
use App\Models\cart_product;
use App\Models\combo;
use App\Models\coupon;
use App\Models\order;
use App\Models\product;
use App\Models\product_size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    /**
     * Add Coupon.
     */
    public function addCoupon(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required',
            'coupon_type' => 'required|in:discount,net discount',
            'discount' => 'required|integer|max:100|min:1',
            'threshold_amount' => 'required_if:coupon_type,net discount',
            'title' => 'required',
            'valid_from' => 'required|date_format:Y-m-d|after_or_equal:today',
            'valid_until' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:valid_from',
            ],
            // 'valid_until' => [
            //     'nullable',
            //     'date_format:Y-m-d',
            //     function ($attribute, $value, $fail) {
            //         $validFrom = request()->input('valid_from');
            //         $today = date('Y-m-d');
            //         if ($value && $value < $today) {
            //             $fail('The valid until date must be today or later.');
            //         }
            //         if ($validFrom && $value < $validFrom) {
            //             $fail('The valid until date must be after the valid from date.');
            //         }
            //     },
            // ],
        ], [
            'valid_until.after_or_equal' => 'valid_until date must be after or same as valid_from date'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $code = trim(strtoupper($request->code));
            $existingCoupon = coupon::whereRaw('UPPER(TRIM(code)) = ?', [$code])->first();

            if ($existingCoupon) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Coupon with this code already exist'
                ], 200);
            }
            $message = '';
            if ($request->coupon_type === 'discount') {
                $existingDiscount = coupon::where('coupon_type', $request->coupon_type)->where('discount', $request->discount)->first();
                $message = 'Coupon with this coupon type and discount already exist';
            } else if ($request->coupon_type === 'net discount') {
                $existingDiscount = coupon::where('coupon_type', $request->coupon_type)->where('discount', $request->discount)->where('threshold_amount', $request->threshold_amount)->first();
                $message = 'Coupon with this coupon type, discount and threshold amount already exist';
            }
            if ($existingDiscount) {
                return response()->json([
                    'status_code' => 400,
                    'message' => $message
                ], 200);
            }
            $coupon = new coupon();
            $coupon->code = $code;
            $coupon->title = $request->title;
            $coupon->discount = $request->discount;
            $coupon->coupon_type = $request->coupon_type;
            $coupon->threshold_amount = $request->coupon_type !== 'net discount' ? null : $request->threshold_amount;
            $coupon->description = $request->description;
            $coupon->more_details = $request->more_details;
            $coupon->valid_from = $request->valid_from;
            $coupon->valid_until = $request->valid_until;
            $coupon->save();

            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Coupon added successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to add coupon.'
            ], 500);
        }
    }

    /**
     * Update Coupon.
     */
    public function updateCoupon(Request $request)
    {
        try {
            $coupon = coupon::find($request->coupon_id);
            if (!$coupon) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Coupon not found'
                ], 404);
            }
            $validator = Validator::make($request->all(), [
                'coupon_id' => 'required',
                'coupon_type' => 'sometimes|in:discount,net discount',
                'discount' => 'sometimes|integer|max:100|min:1',
                'threshold_amount' => 'sometimes',
                'title' => 'sometimes|min:1',
                'valid_from' => 'sometimes|date_format:Y-m-d|after_or_equal:today',
                'valid_until' => 'sometimes|date_format:Y-m-d|after_or_equal:today'
            ], []);
            if ($validator->fails()) {
                return response()->json([
                    'status_code' => 400,
                    'message' => $validator->messages()
                ], 400);
            }
            if ($request->has('valid_from')) {
                $validFrom = $request->valid_from;
                $validUntil = $request->has('valid_until') ? $request->valid_until : $coupon->valid_until;

                if ($validUntil && $validFrom > $validUntil) {
                    return response()->json([
                        'status_code' => 400,
                        'message' => 'The valid from date must be before the existing valid until date.'
                    ], 400);
                }
            }

            if ($request->has('valid_until')) {
                $validUntil = $request->valid_until;
                $validFrom = $request->has('valid_from') ? $request->valid_from : $coupon->valid_from;

                if ($validUntil < $validFrom) {
                    return response()->json([
                        'status_code' => 400,
                        'message' => 'The valid until date must be after the valid from date.'
                    ], 400);
                }
            }
            DB::beginTransaction();
            if ($request->filled('code')) {
                $code = trim(strtoupper($request->code));
                $existingCoupon = coupon::whereRaw('UPPER(TRIM(code)) = ?', [$code])->where('_id', '!=', $coupon->_id)->first();
                if ($existingCoupon) {
                    return response()->json([
                        'status_code' => 400,
                        'message' => 'Coupon with this code already exist'
                    ], 200);
                }
                $coupon->code = $code;
            }
            if ($request->has('title')) {
                $coupon->title = $request->title;
            }
            if ($request->filled('coupon_type')) {
                $coupon->coupon_type = $request->coupon_type;
            }
            if ($coupon->coupon_type === 'net discount') {
                if ($request->has('threshold_amount')) {
                    $coupon->threshold_amount = $request->threshold_amount;
                }
            }
            if ($request->has('discount')) {
                $coupon->discount = $request->discount;
            }
            if ($request->has('description')) {
                $coupon->description = $request->description;
            }
            if ($request->has('more_details')) {
                $coupon->more_details = $request->more_details;
            }
            if ($request->has('valid_from')) {
                $coupon->valid_from = $request->valid_from;
            }
            if ($request->has('valid_until')) {
                $coupon->valid_until = $request->valid_until;
            }

            $existingDiscount = coupon::where('discount', $coupon->discount)->where('_id', '!=', $coupon->_id)->where('coupon_type', $coupon->coupon_type)->where('threshold_amount', $coupon->threshold_amount)->exists();
            if ($existingDiscount) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Coupon with this discount,coupon type and threshold amount already exist'
                ], 200);
            }
            $coupon->save();
            if ($coupon->coupon_type === 'net discount') {
                $carts = cart::where('coupon_id', $coupon->_id)->get();
                foreach ($carts as $cart) {
                    $cartProducts = cart_product::where('cart_id', $cart->_id);
                    $grandTotal = 0;

                    foreach ($cartProducts as $cartProduct) {
                        $productPrice = 0;
                        if ($cartProduct->size) {
                            $size = product_size::find($cartProduct->size_id);
                            if ($size) {
                                $productPrice = $size->selling_price;
                            }
                        } else if ($cartProduct->product_id) {
                            $product = product::find($cartProduct->product_id);
                            if ($product) {
                                $productPrice = $product->selling_price;
                            }
                        } elseif ($cartProduct->combo_id) {
                            $combo = combo::find($cartProduct->combo_id);
                            if ($combo) {
                                $productPrice = $combo->selling_price;
                            }
                        }
                        $grandTotal += $productPrice * $cartProduct->quantity;
                    }
                    if ($grandTotal < $coupon->threshold_amount) {
                        $cart->coupon_id = null;
                        $cart->save();
                    }
                }
            }
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Coupon updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update coupon.'
            ], 500);
        }
    }

    /**
     * Delete Coupon.
     */
    public function deleteCoupon(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coupon_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $coupon = coupon::find($request->coupon_id);
            if (!$coupon) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Coupon not found'
                ], 404);
            }
            cart::where('coupon_id', $coupon->_id)->update(['coupon_id' => null]);
            $coupon->delete();

            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Coupon deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to delete coupon.'
            ], 500);
        }
    }

    /**
     * Get Coupons for admin.
     */
    public function getCouponsAdmin()
    {
        try {
            $coupons = coupon::all();
            return response()->json([
                'status_code' => 200,
                'data' => $coupons,
                'message' => 'Coupons retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve coupons.'
            ], 500);
        }
    }

    /**
     * Get Coupons for customer.
     */
    public function getCouponsCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        try {
            $today = date('Y-m-d');
            $usedCoupons = order::where('user_id', $request->user_id)
                ->whereNotNull('coupon_id')
                ->pluck('coupon_id')
                ->toArray();
                
            // Retrieve coupons that are valid and not in the used coupons list
            $coupons = Coupon::where(function ($query) use ($today) {
                $query->where('valid_until', '>=', $today)
                    ->orWhereNull('valid_until');
            })
                ->whereNotIn('_id', $usedCoupons)
                ->get();

            return response()->json([
                'status_code' => 200,
                'data' => $coupons,
                'message' => 'Coupons retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve coupons.'
            ], 500);
        }
    }

    /**
     * Apply Coupon.
     */
    public function applyCoupon(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required',
            'coupon_id' => 'required',
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
                    'message' => 'Cart not found'
                ], 404);
            }
            $coupon = coupon::find($request->coupon_id);
            if (!$coupon) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Coupon not found'
                ], 404);
            }
            $cart->coupon_id = $coupon->_id;
            $cart->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Coupon applied successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to apply coupon.'
            ], 500);
        }
    }

    /**
     * Remove applied Coupon.
     */
    public function removeAppliedCoupon(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required'
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
                    'message' => 'Cart not found'
                ], 404);
            }
            $cart->coupon_id = null;
            $cart->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Coupon removed successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to remove coupon.'
            ], 500);
        }
    }
}
