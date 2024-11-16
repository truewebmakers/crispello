<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\admin;
use App\Models\cart_product;
use App\Models\customization;
use App\Models\product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomizationController extends Controller
{
    /**
     * Add Customization.
     */
    public function addCustomization(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'price' => 'required',
            'veg' => 'required|boolean',
            'type' => 'required|in:Toppings',
            'admin_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $admin = admin::findOrFail($request->admin_id);
            $name = trim($request->name);
            $existingData = customization::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->where('admin_id', $admin->_id)->first();

            if ($existingData) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'This Customization option already exists'
                ], 200);
            }
            $customization = new customization();
            $customization->name = $name;
            $customization->price = $request->price;
            $customization->veg = $request->veg;
            $customization->type = $request->type;
            $customization->admin_id = $admin->_id;
            $customization->save();

            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Customization added successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to add customization.'
            ], 500);
        }
    }

    /**
     * Update Customization.
     */
    public function updateCustomization(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customization_id' => 'required',
            'veg' => 'boolean',
            'is_available' => 'boolean',
            'type' => 'in:Toppings',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $customization = customization::findOrFail($request->customization_id);
            if ($request->filled('name')) {
                $name = trim($request->name);
                $existingData = customization::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->where('admin_id', $customization->admin_id)->where('_id', '!=', $customization->_id)->first();

                if ($existingData) {
                    return response()->json([
                        'status_code' => 400,
                        'message' => 'This Customization option already exists'
                    ], 200);
                }
                $customization->name = $name;
            }
            if ($request->filled('price')) {
                $customization->price = $request->price;
            }
            if ($request->has('veg')) {
                $customization->veg = $request->veg;
            }
            if ($request->has('is_available')) {
                $customization->is_available = $request->is_available;
            }
            if ($request->file('type')) {
                $customization->type = $request->type;
            }
            $customization->save();

            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Customization updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update customization.'
            ], 500);
        }
    }

    /**
     * Delete Customization.
     */
    public function deleteCustomization(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customization_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $customization = customization::findOrFail($request->customization_id);
            $products = product::whereNotNull('customization')->whereRaw("JSON_CONTAINS(customization, $customization->_id)")->get();
            $cart_products=cart_product::whereNotNull('customization')->whereRaw("JSON_CONTAINS(customization, $customization->_id)")->get();
            foreach ($products as $product) {
                $customizationIds = json_decode($product->customization, true);
                // Remove the customization id from the array
                if (($key = array_search($customization->_id, $customizationIds)) !== false) {
                    unset($customizationIds[$key]);
                }
                if (empty($customizationIds)) {
                    $product->customization = null;
                } else {
                    $product->customization = json_encode(array_values($customizationIds));
                }
                $product->save();
            }
            foreach ($cart_products as $cart_product) {
                $customizationIds = json_decode($cart_product->customization, true);
                // Remove the customization id from the array
                if (($key = array_search($customization->_id, $customizationIds)) !== false) {
                    unset($customizationIds[$key]);
                }
                if (empty($customizationIds)) {
                    $cart_product->customization = null;
                } else {
                    $cart_product->customization = json_encode(array_values($customizationIds));
                }
                $cart_product->save();
            }

            $customization->delete();

            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Customization deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to delete customization.'
            ], 500);
        }
    }

    /**
     * Get All Customization.
     */
    public function getAllCustomizationsAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'admin_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        try {
            $admin = admin::findOrFail($request->admin_id);
            $customization = customization::where('admin_id', $admin->_id)->get();

            return response()->json([
                'status_code' => 200,
                'data' => $customization,
                'message' => 'Customization retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve customization.'
            ], 500);
        }
    }
}
