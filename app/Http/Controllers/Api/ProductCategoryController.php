<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\product;
use App\Models\product_category;
use App\Models\video;
use App\Traits\ImageHandleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;

class ProductCategoryController extends Controller
{
    use ImageHandleTrait;
    /**
     * Add Product Category.
     */
    public function addProductCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'image' => 'required'
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
            $existingCategory = product_category::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->first();

            if ($existingCategory) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Product category already exists'
                ], 200);
            }

            $product_category = new product_category();
            $product_category->name = $name;
            $product_category->image = '';
            $product_category->save();

            $image = $this->decodeBase64Image($request->image);
            $imageName = 'product_category_' . $product_category->_id . '.' . $image['extension'];
            $imagePath = 'public/product/' . $imageName;
            Storage::put($imagePath, $image['data']);

            $product_category->image = 'storage/app/public/product/' . $imageName;
            $product_category->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Product category added successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($imagePath) && Storage::exists($imagePath)) {
                Storage::delete($imagePath);
            }
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to add product category.'
            ], 500);
        }
    }

    /**
     * Update Product Category.
     */
    public function updateProductCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required',
            'name' => 'required',
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
            $existingCategory = product_category::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->where('_id', '!=', $request->category_id)->first();

            if ($existingCategory) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Product category already exists'
                ], 200);
            }

            $oldImage = parse_url($category->image, PHP_URL_PATH);
            $category->name = $name;
            if ($request->has('image')) {
                $image = $this->decodeBase64Image($request->image);
                $imageName = 'product_category_' . $category->_id . '.' . $image['extension'];
                $imagePath = 'public/product/' . $imageName;
                Storage::put($imagePath, $image['data']);

                $path = str_replace('storage/app/', '', $oldImage);
                if ($path !== $imagePath) {
                    if ($oldImage && Storage::exists($path)) {
                        Storage::delete($path);
                    }
                }
                // Update product category image
                $category->image = 'storage/app/public/product/' . $imageName . '?timestamp=' . time();
            }
            $category->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Product category updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update product category.'
            ], 500);
        }
    }

    /**
     * Delete Product Category.
     */
    public function deleteProductCategory(Request $request)
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
        DB::beginTransaction();
        try {
            $category = product_category::find($request->category_id);
            if (!$category) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Product category not found'
                ], 404);
            }

            $productCount = product::where('product_category_id', $request->category_id)->count();
            if ($productCount > 0) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Product category cannot be delete because it has associated products. Please move the products to another category first.'
                ], 404);
            }
            $image = parse_url($category->image, PHP_URL_PATH);
            video::where('category_id', $category->_id)->delete();
            $category->delete();
            $path = str_replace('storage/app/', '', $image);
            if ($image && Storage::exists($path)) {
                Storage::delete($path);
            }
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Product category deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to delete product category.'
            ], 500);
        }
    }

    /**
     * Get all product categories admin.
     */
    public function getAllProductCategoriesAdmin()
    {
        try {

            $categories = product_category::all();

            return response()->json([
                'status_code' => 200,
                'data' => $categories,
                'message' => 'Product categories retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve product categories.'
            ], 500);
        }
    }

    /**
     * Get all product categories customer.
     */
    public function getAllProductCategoriesCustomer()
    {
        try {
            $categories = product_category::whereHas('products', function (Builder $query) {
                $query->where('disable', 0)
                    ->where('only_combo', 0);
            })->get();

            return response()->json([
                'status_code' => 200,
                'data' => $categories,
                'message' => 'Product categories retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve product categories.'
            ], 500);
        }
    }



}
