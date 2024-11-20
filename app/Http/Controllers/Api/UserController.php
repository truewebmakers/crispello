<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\address;
use App\Models\cart;
use App\Models\User;
use App\Models\user_fcm_token;
use App\Models\user_otp;
use App\Traits\ImageHandleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    use ImageHandleTrait;

    /**
     * Verify OTP.
     */
    public function verifyOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phoneno' => 'required',
            'otp' => 'required',
            'device_id' => 'required',
            'token' => 'required'
            // 'phoneno' => 'required|regex:/[0-9]{10}/',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $otp = user_otp::where('phoneno', $request->phoneno)->latest()->first();

            if (!$otp) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'OTP not found.'
                ], 400);
            }

            // Check if the OTP has expired
            if (now()->greaterThan($otp->expire_time)) {
                return response()->json([
                    'status_code' => 401,
                    'message' => 'OTP has expired.'
                ], 200);
            }

            if ($otp->otp !== $request->otp) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Invalid OTP.'
                ], 200);
            }
            $newUser = false;
            $existingUser = User::with('referralcode')->where('phoneno', $request->phoneno)->first();
            if (!$existingUser) {
                $newUser = true;
                $user = new User();
                $user->phoneno = $request->phoneno;
                $user->name = null;
                $user->email = null;
                $user->gender = null;
                $user->dob = null;
                $user->aniversary_date = null;
                $user->disable = 0;
                $user->image = null;
                $user->save();
                $cart = new cart();
                $cart->user_id = $user->_id;
                $cart->save();
            } else {
                $existingUser = User::with('referralcode')->where('phoneno', $request->phoneno)->where('user_role', 'user')->first();
                if (!$existingUser) {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'You can not register with this number'
                    ], 404);
                }
                $newUser = false;
                $user = $existingUser;
            }
            $existingDevice = user_fcm_token::where('device_id', $request->device_id)->where('user_id',$user->_id)->first();
            if ($existingDevice) {
                $existingDevice->token = $request->token;
                $existingDevice->save();
            } else {
                $newDevice = new user_fcm_token();
                $newDevice->device_id = $request->device_id;
                $newDevice->token = $request->token;
                $newDevice->user_id = $user->_id;
                $newDevice->save();
            }
            $token = $user->createToken('UserToken', ['user'])->accessToken; //specify scope name

            DB::commit();
            $user->device_id = $request->device_id;
            $user->makeHidden(['password']);
            return response()->json([
                'status_code' => 200,
                'data' => $user,
                'token' => $token,
                'is_new' => $newUser,
                'message' => 'User logged in successfully.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to login user.'
            ], 500);
        }
    }

    /**
     * Update Profile.
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'email' => 'nullable|email',
            'dob' => 'nullable|date_format:d-m-Y',
            'aniversary_date' => 'nullable|date_format:d-m-Y',
            // 'phoneno' => 'required|regex:/[0-9]{10}/',
        ], [
            'dob.date_format' => 'The dob must be in the format DD-MM-YYYY.',
            'aniversary_date.date_format' => 'The aniversary_date must be in the format DD-MM-YYYY.',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $user = User::where('_id', $request->user_id)->where('disable', 0)->first();

            if (!$user) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'User not found.'
                ], 404);
            }

            if ($request->filled('name')) {
                $user->name = $request->name;
            }
            // if ($request->has('phoneno')) {
            //     if ($request->phoneno) {
            //         $user->phoneno = $request->phoneno;
            //     }
            // }
            
            if ($request->has('email')) {
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
            if ($request->has('dob')) {
                $user->dob = $request->dob;
            }
            if ($request->has('aniversary_date')) {
                $user->aniversary_date = $request->aniversary_date;
            }
            if ($request->has('gender')) {
                $user->gender = trim($request->gender);
            }
            if ($request->has('image')) {
                $oldImage = parse_url($user->image, PHP_URL_PATH);
                if ($request->image !== null) {
                    $image = $this->decodeBase64Image($request->image);
                    $imageName = 'user_' . $user->_id . '.' . $image['extension'];
                    $imagePath = 'public/user/' . $imageName;
                    Storage::put($imagePath, $image['data']);

                    $user->image = 'storage/app/public/user/' . $imageName . '?timestamp=' . time();
                    $path = str_replace('storage/app/', '', $oldImage);
                    if ($path !== $imagePath) {
                        if ($oldImage && Storage::exists($path)) {
                            Storage::delete($path);
                        }
                    }
                } else {
                    $path = str_replace('storage/app/', '', $oldImage);
                    if ($path) {
                        if ($oldImage && Storage::exists($path)) {
                            Storage::delete($path);
                        }
                    }
                    $user->image = null;
                }
            }
            $user->save();

            DB::commit();
            return response()->json([
                'status_code' => 200,
                'data' => $user,
                'message' => 'User profile updated successfully.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update user profile.'
            ], 500);
        }
    }

    /**
     * Get Profile.
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
            $user = User::where('_id', $request->user_id)->where('disable', 0)->first();

            if (!$user) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'User not found.'
                ], 404);
            }

            return response()->json([
                'status_code' => 200,
                'data' => $user,
                'message' => 'User profile retrieved successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve user profile.'
            ], 500);
        }
    }

    /**
     * Get all users.
     */
    public function getAllUsers()
    {
        try {
            $users = User::where('disable', 0)->select('_id', 'name', 'phoneno')->get()->each(function ($user) {
                $addressCount = address::where('user_id', $user->_id)->count();
                if ($addressCount <= 0) {
                    $address = null;
                } else {
                    $address = address::where('user_id', $user->_id)->where('is_default', 1)->first();
                    if (!$address) {
                        $address = address::where('user_id', $user->_id)->orderBy('created_at', 'asc')->first();
                    }
                }
                $user->address = $address;
            });

            return response()->json([
                'status_code' => 200,
                'data' => $users,
                'message' => 'Users retrieved successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve users.'
            ], 500);
        }
    }

    /**
     * User Logout.
     */
    public function userLogout(Request $request)
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
            $device = user_fcm_token::where('device_id', $request->device_id)->first();
            if (!$device) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Device id not found'
                ], 404);
            }
            $device->token = null;
            $device->save();
            DB::commit();

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
