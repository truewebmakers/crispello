<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\address;
use App\Models\cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AddressController extends Controller
{
    /**
     * Add address.
     */
    public function addAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'save_as' => 'required',
            'house_no' => 'required',
            'area' => 'required',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'user_id' => 'required',
            'default' => 'required'
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
                    'message' => 'User not Found'
                ], 404);
            }

            $address = new address();
            $address->save_as = $request->save_as;
            $address->house_no = $request->house_no;
            $address->area = $request->area;
            $address->latitude = $request->latitude;
            $address->longitude = $request->longitude;
            $address->user_id = $user->_id;
            if ($request->has('options_to_reach')) {
                $address->options_to_reach = $request->options_to_reach;
            }
            $isFirstAddress = address::where('user_id', $user->_id)->exists();
            if (!$isFirstAddress) {
                $address->is_default = 1;
            } else {
                if ($request->default === 1) {
                    address::where('user_id', $user->_id)->where('is_default', 1)->update(['is_default' => 0]);
                    $address->is_default = 1;
                } else {
                    $address->is_default = 0;
                }
            }
            $address->save();

            if ($address->is_default === 1) {
                $cart = cart::where('user_id', $user->_id)->where('order_type', 'Delivery')->first();
                if ($cart) {
                    $cart->address_id = $address->_id;
                    $cart->save();
                }
            }

            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Address added successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to add address.'
            ], 500);
        }
    }

    /**
     * Update address.
     */
    public function updateAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'numeric',
            'longitude' => 'numeric',
            'address_id' => 'required',
            'save_as' => 'sometimes|min:1',
            'house_no' => 'sometimes|min:1',
            'area' => 'sometimes|min:1',
            'default' => 'boolean'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $address = address::find($request->address_id);
            if (!$address) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Address not Found'
                ], 404);
            }
            if ($request->filled('latitude')) {
                $address->latitude = $request->latitude;
            }
            if ($request->filled('longitude')) {
                $address->longitude = $request->longitude;
            }
            if ($request->has('save_as')) {
                $address->save_as = $request->save_as;
            }
            if ($request->has('house_no')) {
                $address->house_no = $request->house_no;
            }
            if ($request->has('area')) {
                $address->area = $request->area;
            }
            if ($request->has('options_to_reach')) {
                $address->options_to_reach = $request->options_to_reach;
            }
            $cart = cart::where('user_id', $address->user_id)->where('order_type', 'Delivery')->first();
            if ($request->has('default')) {
                $addressCount = address::where('user_id', $address->user_id)->count();
                if ($addressCount > 1) {
                    if ($request->default !== $address->is_default) {
                        if ($request->default === 1) {
                            address::where('user_id', $address->user_id)->where('is_default', 1)->where('_id', '!=', $address->_id)->update(['is_default' => 0]);
                            $address->is_default = 1;
                        } else {
                            $address->is_default = 0;
                            $address->save();
                            $fistAddress = address::where('user_id', $address->user_id)->orderBy('created_at', 'asc')->where('_id', '!=', $address->_id)->first();
                            if ($fistAddress) {
                                $fistAddress->is_default = 1;
                                $fistAddress->save();
                                if ($cart) {
                                    $cart->address_id = $fistAddress->_id;
                                    $cart->save();
                                }
                            }
                        }
                    }
                }
            }
            $address->save();
            if ($address->is_default === 1) {
                $cart->address_id = $address->_id;
                $cart->save();
            }

            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Address updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update address.'
            ], 500);
        }
    }

    /**
     * Delete address.
     */
    public function deleteAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'address_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $address = address::find($request->address_id);
            if (!$address) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Address not Found'
                ], 404);
            }
            $cart = cart::where('user_id', $address->user_id)->where('order_type', 'Delivery')->first();
            if ($address->is_default === 1) {
                $newDefaultAddress = address::where('user_id', $address->user_id)->orderBy('created_at', 'asc')->where('_id', '!=', $address->_id)->first();
                if ($newDefaultAddress) {
                    $newDefaultAddress->is_default = 1;
                    $newDefaultAddress->save();
                    if ($cart) {
                        $cart->address_id = $newDefaultAddress->_id;
                        $cart->save();
                    }
                }
            }
            $cart = cart::where('address_id', $address->_id)->first();
            if ($cart) {
                $cart->address_id = null;
                $cart->save();
            }
            $address->delete();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Address deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to delete address.'
            ], 500);
        }
    }

    /**
     * Get all addresses.
     */
    public function getAllAddresses(Request $request)
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
            $user = User::find($request->user_id);
            if (!$user) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'User not Found'
                ], 404);
            }

            $addresses = address::where('user_id', $user->_id)->get();
            return response()->json([
                'status_code' => 200,
                'data' => $addresses,
                'message' => 'Addresses retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve addresses.'
            ], 500);
        }
    }
}
