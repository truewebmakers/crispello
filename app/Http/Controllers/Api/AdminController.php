<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\admin;
use App\Models\admin_fcm_token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Traits\FCMNotificationTrait;

class AdminController extends Controller
{
    use FCMNotificationTrait;
    /**
     * Admin Registration.
     */
    public function adminRegistration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $name = trim($request->username);
            $existingUser = admin::where('username', $name)->first();
            if ($existingUser) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'User already exist'
                ], 200);
            }
            $admin = new admin();
            $admin->username = $name;
            $admin->password = trim($request->password);
            $admin->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'user' => $admin,
                'message' => 'User registered successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to Register.'
            ], 500);
        }
    }

    /**
     * Admin Login.
     */
    public function adminLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
            'device_id' => 'required',
            'token' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $user = admin::where('username', $request->username)->first();
            if (!$user) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'User not found'
                ], 200);
            }
            if ($request->password === $user->password) {
                $existingDevice = admin_fcm_token::where('device_id', $request->device_id)->first();
                if ($existingDevice) {
                    $existingDevice->token = $request->token;
                    $existingDevice->save();
                } else {
                    $newDevice = new admin_fcm_token();
                    $newDevice->device_id = $request->device_id;
                    $newDevice->token = $request->token;
                    $newDevice->save();
                }
                DB::commit();

                $token = $user->createToken('AdminToken', ['admin'])->accessToken; //specify scope name
                $user->device_id = $request->device_id;
                return response()->json([
                    'status_code' => 200,
                    'data' => $user,
                    'token' => $token,
                    'message' => 'Login successfully'
                ], 200);
            } else {
                return response()->json([
                    'status_code' => 401,
                    'message' => 'Invalid password',
                ], 200);
            }
            return response()->json([
                'status_code' => 200,
                'message' => 'Logged in successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to Login.'
            ], 500);
        }
    }

    /**
     * Update profile of admin.
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'latitude' => 'numeric',
            'longitude' => 'numeric',
            'email' => 'email',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        try {
            $user = admin::where('_id', $request->user_id)->first();
            if (!$user) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'User not found'
                ], 200);
            }
            if ($request->filled('name')) {
                $user->name = $request->name;
            }
            if ($request->filled('phoneno')) {
                $user->phoneno = $request->phoneno;
            }
            if ($request->filled('email')) {
                $user->email = $request->email;
            }
            if ($request->filled('location')) {
                $user->location = $request->location;
            }
            if ($request->filled('latitude')) {
                $user->latitude = $request->latitude;
            }
            if ($request->filled('longitude')) {
                $user->longitude = $request->longitude;
            }
            if ($request->has('delivery_charge')) {
                $user->delivery_charge = $request->delivery_charge;
            }
            if ($request->has('free_upto_km')) {
                $user->free_upto_km = $request->free_upto_km;
            }
            if ($request->has('delivery_coverage_km')) {
                $user->delivery_coverage_km = $request->delivery_coverage_km;
            }
            $user->save();

            return response()->json([
                'status_code' => 200,
                 'data' => $user,
                'message' => 'Admin profile updated successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update admin profile.'
            ], 500);
        }
    }

    /**
     * Get Profile of admin.
     */
    public function getProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }

        try {
            $user = admin::where('_id', $request->user_id)->first();
            $user->makeHidden('password');
            if (!$user) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'User not found.'
                ], 404);
            }

            return response()->json([
                'status_code' => 200,
                'data' => $user,
                'message' => 'Admin profile retrieved successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieved admin profile.'
            ], 500);
        }
    }

    /**
     * Admin Logout.
     */
    public function adminLogout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $device = admin_fcm_token::where('device_id', $request->device_id)->first();
            if ($device) {
                $device->delete();
                DB::commit();
            }

            return response()->json([
                'status_code' => 200,
                'message' => 'Logout successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to Logout.'
            ], 500);
        }
    }
}
