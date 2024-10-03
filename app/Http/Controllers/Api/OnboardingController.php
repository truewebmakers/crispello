<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OnboardingController extends Controller
{
    /**
     * Get Onboarding Sliders.
     */
    public function getOnboardings()
    {
        try {
            $onboardings = onboarding::all();
            return response()->json([
                'status_code' => 200,
                'data' => $onboardings,
                'message' => 'Onboardings retrieved successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve onboarding sliders.'
            ], 500);
        }
    }

    /**
     * Add Onboarding Slider.
     */
    public function addOnboarding(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'description' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $title = trim($request->title);
            $existingOnboarding = onboarding::whereRaw('LOWER(TRIM(title)) = ?', [strtolower($title)])->first();
            if ($existingOnboarding) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Onboarding with this title already exist.'
                ], 200);
            }
            $onboarding = new onboarding();
            $onboarding->title = $title;
            $onboarding->description = trim($request->description);
            $onboarding->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Onboarding slider added successfully.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to add onboarding slider.'
            ], 500);
        }
    }

    /**
     * Update Onboarding Slider.
     */
    public function updateOnboarding(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'description' => 'required',
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
            $slider = onboarding::find($request->slider_id);
            if (!$slider) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Onboarding slider not found.'
                ], 404);
            }
            $title = trim($request->title);
            $existingOnboarding = onboarding::whereRaw('LOWER(TRIM(title)) = ?', [strtolower($title)])->where('_id', '!=', $request->slider_id)->first();

            if ($existingOnboarding) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Onboarding slider already exists'
                ], 200);
            }

            $slider->title = $title;
            $slider->description = trim($request->description);
            $slider->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Onboarding slider updated successfully.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update onboarding slider.'
            ], 500);
        }
    }

    /**
     * Delete Onboarding Slider.
     */
    public function deleteOnboarding(Request $request)
    {
        $validator = Validator::make($request->all(), [
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
            $slider = onboarding::find($request->slider_id);
            if (!$slider) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Onboarding slider not found.'
                ], 404);
            }

            $slider->delete();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Onboarding slider deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to delete onboarding slider.'
            ], 500);
        }
    }
}
