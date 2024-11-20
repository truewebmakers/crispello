<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\address;
use App\Models\admin;
use App\Models\admin_fcm_token;
use App\Models\extra_setting;
use App\Models\User;
use App\Models\user_fcm_token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Traits\FCMNotificationTrait;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    use FCMNotificationTrait;
    /**
     * Admin Registration.
     */
    // public function adminRegistration(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'username' => 'required',
    //         'password' => 'required'
    //     ]);
    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status_code' => 400,
    //             'message' => $validator->messages()
    //         ], 400);
    //     }
    //     DB::beginTransaction();
    //     try {
    //         $name = trim($request->username);
    //         $existingUser = admin::where('username', $name)->first();
    //         if ($existingUser) {
    //             return response()->json([
    //                 'status_code' => 400,
    //                 'message' => 'User already exist'
    //             ], 200);
    //         }
    //         $admin = new admin();
    //         $admin->username = $name;
    //         $admin->password = trim($request->password);
    //         $admin->save();
    //         DB::commit();
    //         return response()->json([
    //             'status_code' => 200,
    //             'user' => $admin,
    //             'message' => 'User registered successfully'
    //         ], 200);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'status_code' => 500,
    //             'message' => 'Failed to Register.'
    //         ], 500);
    //     }
    // }

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
            $user = User::where('username', $request->username)->where('user_role', 'admin')->first();
            if (!$user) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'User not found'
                ], 200);
            }
            if (Hash::check($request->password, $user->password)) {
                $existingDevice = user_fcm_token::where('device_id', $request->device_id)->where('user_id',$user->_id)->first();
                if ($existingDevice) {
                    $existingDevice->token = $request->token;
                    $existingDevice->save();
                } else {
                    $newDevice = new user_fcm_token();
                    $newDevice->device_id = $request->device_id;
                    $newDevice->user_id = $user->_id;
                    $newDevice->token = $request->token;
                    $newDevice->save();
                }
                DB::commit();

                $token = $user->createToken('AdminToken', ['admin'])->accessToken; //specify scope name
                $user->device_id = $request->device_id;
                $address = address::where('user_id', $user->_id)->first();
                $user->latitude=$address?$address->latitude:null;
                $user->longitude=$address?$address->longitude:null;
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
            $user = User::where('_id', $request->user_id)->where('user_role', 'admin')->first();
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
                $existingUser=User::where('phoneno',$request->phoneno)->where('_id','!=',$user->_id)->first();
                if($existingUser)
                {
                    return response()->json([
                        'status_code' => 400,
                        'message' => 'You can not use this phoneno'
                    ], 400);
                }
                $user->phoneno = $request->phoneno;
            }
            if ($request->filled('email')) {
                $existingUser=User::where('email',$request->email)->where('_id','!=',$user->_id)->first();
                if($existingUser)
                {
                    return response()->json([
                        'status_code' => 400,
                        'message' => 'You can not use this email address'
                    ], 400);
                }
                $user->email = $request->email;
            }
            $user->save();
            if ($request->filled(['location', 'latitude', 'longitude'])) {
                $address = address::where('user_id', $user->_id)->first();

                if ($address) {
                    // Update existing address
                    $address->area = $request->filled('location') ? $request->location : $address->area;
                    $address->latitude = $request->filled('latitude') ? $request->latitude : $address->latitude;
                    $address->longitude = $request->filled('longitude') ? $request->longitude : $address->longitude;
                    $address->save();
                } else {
                    // Create new address entry
                    address::create([
                        'user_id' => $user->_id,
                        'area' => $request->location,
                        'latitude' => $request->latitude,
                        'longitude' => $request->longitude,
                    ]);
                }
            }
            // Handle Extra Settings
            if ($request->filled(['delivery_charge', 'free_upto_km', 'delivery_coverage_km'])) {
                $extraSetting = extra_setting::where('added_by', $user->_id)->first();

                if ($extraSetting) {
                    // Update existing extra settings
                    $extraSetting->delivery_charge = $request->filled('delivery_charge') ? $request->delivery_charge : $extraSetting->delivery_charge;
                    $extraSetting->free_upto_km = $request->filled('free_upto_km') ? $request->free_upto_km : $extraSetting->free_upto_km;
                    $extraSetting->delivery_coverage_km = $request->filled('delivery_coverage_km') ? $request->delivery_coverage_km : $extraSetting->delivery_coverage_km;
                    $extraSetting->save();
                } else {
                    // Create new extra settings entry
                    extra_setting::create([
                        'added_by' => $user->_id,
                        'delivery_charge' => $request->delivery_charge,
                        'free_upto_km' => $request->free_upto_km,
                        'delivery_coverage_km' => $request->delivery_coverage_km,
                    ]);
                }
            }

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
            $user = User::where('_id', $request->user_id)->where('user_role', 'admin')->first();
            if (!$user) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'User not found.'
                ], 404);
            }
            $user->address=address::where('user_id',$user->_id)->first();
            $user->extra_setting=extra_setting::where('added_by',$user->_id)->first();
            $user->makeHidden('password');
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
            'device_id' => 'required',
            'user_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $device = user_fcm_token::where('device_id', $request->device_id)->where('user_id',$request->user_id)->first();
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

    /**
     * Get Restaurant Details User.
     */
    public function getRestaurantDetailsUser(Request $request)
    {
        try {
            $admin = User::where('user_role', 'admin')->first();
            $admin->address = address::where('user_id', $admin->_id)->first();
            $admin->makeHidden(['username', 'password']);
            return response()->json([
                'status_code' => 200,
                'data' => $admin,
                'message' => 'Restaurant details retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve restaurant details.'
            ], 500);
        }
    }

    /**
     * Change Password.
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|min:6'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $user = User::where('user_role', 'admin')->first();
            if (!$user) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Not Found'
                ], 404);
            }
            $user->password = Hash::make($request->password);
            $user->save();
            DB::commit();

            return response()->json([
                'status_code' => 200,
                'message' => 'Password changed successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to change password.'
            ], 500);
        }
    }
}
