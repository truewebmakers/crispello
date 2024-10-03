<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\home_slider;
use App\Traits\ImageHandleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class HomeSliderController extends Controller
{
    use ImageHandleTrait;
    /**
     * Get all sliders for customer.
     */
    public function getSliders()
    {
        try {
            $sliders = home_slider::all();
            return response()->json([
                'status_code' => 200,
                'data' => $sliders,
                'message' => 'Sliders retrieved successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve sliders.'
            ], 500);
        }
    }

    /**
     * add Slider.
     */
    public function addSlider(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $slider = new home_slider();
            $slider->image = '';
            $slider->save();

            $image = $this->decodeBase64Image($request->image);
            // Handle file upload
            $imageName = 'home_slider_' . $slider->_id . '.' . $image['extension'];
            $imagePath = 'public/slider/' . $imageName;
            Storage::put($imagePath, $image['data']);
            $slider->image = 'storage/app/public/slider/' . $imageName;
            $slider->save();

            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Slider added successfully.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($imagePath) && Storage::exists($imagePath)) {
                Storage::delete($imagePath);
            }
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to add slider.'
            ], 500);
        }
    }

    /**
     * update Slider.
     */
    public function updateSlider(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required',
            'slider_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $slider = home_slider::find($request->slider_id);
            if (!$slider) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Slider not found.'
                ], 404);
            }
            $oldImage = parse_url($slider->image, PHP_URL_PATH);
            $image = $this->decodeBase64Image($request->image);
            $imageName = 'home_slider_' . $slider->_id . '.' . $image['extension'];
            $imagePath = 'public/slider/' . $imageName;
            Storage::put($imagePath, $image['data']);

            $path = str_replace('storage/app/', '', $oldImage);
            if ($path !== $imagePath) {
                if ($oldImage && Storage::exists($path)) {
                    Storage::delete($path);
                }
            }
            $slider->image = 'storage/app/public/slider/' . $imageName . '?timestamp=' . time();
            $slider->save();

            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Slider updated successfully.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update slider.'
            ], 500);
        }
    }

    /**
     * delete Slider.
     */
    public function deleteSlider(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'slider_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $slider = home_slider::find($request->slider_id);
            if (!$slider) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Slider not found',
                ], 404);
            }
            $image = parse_url($slider->image, PHP_URL_PATH);
            $slider->delete();
            $path = str_replace('storage/app/', '', $image);
            if ($image && Storage::exists($path)) {
                Storage::delete($path);
            }
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Slider deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to delete slider.'
            ], 500);
        }
    }
}
