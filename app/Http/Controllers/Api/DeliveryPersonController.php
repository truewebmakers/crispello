<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\address;
use App\Models\delivery_driver;
use App\Models\delivery_fcm_token;
use App\Models\delivery_request;
use App\Models\order;
use App\Models\User;
use App\Models\user_fcm_token;
use App\Models\user_otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Traits\ImageHandleTrait;
use Illuminate\Support\Facades\Storage;
use App\Traits\CalculateDistanceTrait;
use Illuminate\Support\Facades\Auth;

class DeliveryPersonController extends Controller
{
    use ImageHandleTrait, CalculateDistanceTrait;

    /**
     * Delivery app login
     */
    public function deliveryPersonLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phoneno' => 'required',
            'otp' => 'required',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
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
            $otp = user_otp::where('phoneno', $request->phoneno)->latest()->first();
            if (!$otp) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'OTP not found'
                ], 404);
            }

            if (now()->greaterThan($otp->expire_time)) {
                return response()->json([
                    'status_code' => 401,
                    'message' => 'OTP has expired'
                ], 200);
            }

            if ($request->otp !== $otp->otp) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Invalid OTP'
                ], 200);
            }

            $existingUser = User::where('phoneno', $request->phoneno)->first();
            if (!$existingUser) {
                $existingUser = new User();
                $existingUser->phoneno = $request->phoneno;
                $existingUser->name = null;
                $existingUser->email = null;
                $existingUser->image = null;
                $existingUser->online = 1;
                $existingUser->available = 1;
                $existingUser->user_role = 'delivery_partner';
                $existingUser->save();
                address::create([
                    'user_id' => $existingUser->_id,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                ]);
            } else {
                $existingUser = User::where('phoneno', $request->phoneno)->where('user_role', 'delivery_partner')->first();
                if ($existingUser) {
                    $address = address::where('user_id', $existingUser->_id)->first();
                    if ($address) {
                        $address->latitude =  $request->latitude;
                        $address->longitude = $request->longitude;
                        $address->save();
                    }
                    $existingUser->online = 1;
                    $existingUser->available = 1;
                    $existingUser->save();
                } else {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'You can not register with this number'
                    ], 404);
                }
            }
            $existingDevice = user_fcm_token::where('device_id', $request->device_id)->where('user_id',$existingUser->_id)->first();
            if ($existingDevice) {
                $existingDevice->token = $request->token;
                $existingDevice->user_id = $existingUser->_id;
                $existingDevice->save();
            } else {
                $newDevice = new user_fcm_token();
                $newDevice->device_id = $request->device_id;
                $newDevice->token = $request->token;
                $newDevice->user_id = $existingUser->_id;
                $newDevice->save();
            }
            $token = $existingUser->createToken('DeliveryToken', ['delivery_driver'])->accessToken;
            DB::commit();
            $existingUser->device_id = $request->device_id;
            $address = address::where('user_id', $existingUser->_id)->first();
            $existingUser->latitude=$address?$address->latitude:null;
            $existingUser->longitude=$address?$address->longitude:null;
            $existingUser->makeHidden(['password']);
            return response()->json([
                'status_code' => 200,
                'data' => $existingUser,
                'token' => $token,
                'message' => 'Login successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to login'
            ], 500);
        }
    }

    /**
     * Update delivery person profile
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'available' => 'boolean',
            'online' => 'boolean',
            'email' => 'email|nullable',
            'profile_image' => 'nullable'
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
                    'message' => 'User Not Found'
                ], 404);
            }
            if ($request->filled('name')) {
                $user->name = $request->name;
            }
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
            if ($request->has('profile_image')) {
                $oldImage = parse_url($user->image, PHP_URL_PATH);
                if ($request->profile_image !== null) {
                    $image = $this->decodeBase64Image($request->profile_image);
                    $imageName = 'delivery_driver_' . $user->_id . '.' . $image['extension'];
                    $imagePath = 'public/delivery/' . $imageName;
                    Storage::put($imagePath, $image['data']);
                    $user->image = 'storage/app/public/delivery/' . $imageName . '?timestamp=' . time();
                    $path = str_replace('storage/app/', '', $oldImage);
                    if ($path !== $imagePath) {
                        if ($oldImage && Storage::exists($path)) {
                            Storage::delete($path);
                        }
                    }
                } else {
                    $path = str_replace('storage/app/', '', $oldImage);
                    if ($oldImage && Storage::exists($path)) {
                        Storage::delete($path);
                    }
                    $user->image = null;
                }
            }
            if ($request->filled('available')) {
                $user->available = $request->available;
            }
            if ($request->filled('online')) {
                $user->online = $request->online;
            }
            $user->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'data' => $user,
                'message' => 'User profile updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update profile'
            ], 500);
        }
    }

    /**
     * Get Delivery person profile
     */
    public function getProfile(Request $request)
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
                    'message' => 'User Not Found'
                ], 404);
            }
            $address = address::where('user_id', $user->_id)->first();
            $user->latitude=$address?$address->latitude:null;
            $user->longitude=$address?$address->longitude:null;
            return response()->json([
                'status_code' => 200,
                'data' => $user,
                'message' => 'User profile retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve user profile'
            ], 500);
        }
    }

    /**
     * update delivery person current location
     */
    public function updateLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            // $user = delivery_driver::find($request->user_id);
            $address = address::where('user_id', Auth::id())->first();
            if (!$address) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Not found'
                ], 404);
            }
            $address->latitude = $request->latitude;
            $address->longitude = $request->longitude;
            $address->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Location updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update current location'
            ], 500);
        }
    }

    /**
     * Get all driver list
     */
    public function getDriverList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'order_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        try {
            $order = order::find($request->order_id);
            if (!$order) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Order not found'
                ], 404);
            }
            if ($order->order_type !== 'Delivery') {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'You can only hire drivers when order type is Delivery'
                ], 200);
            }
            // Retrieve drivers with valid addresses
            $drivers = User::where('online', 1)->where('user_role', 'delivery_partner')
                ->whereHas('deliverAddress', function ($query) {
                    $query->whereNotNull('latitude')
                        ->whereNotNull('longitude');
                })
                ->with('deliverAddress')
                ->get()->map(function ($driver) {
                    // Extract latitude and longitude from deliverAddress
                    $driver->latitude = $driver->deliverAddress->latitude ?? null;
                    $driver->longitude = $driver->deliverAddress->longitude ?? null;

                    // Optionally, remove deliverAddress if you don't want to include it in the response
                    unset($driver->deliverAddress);

                    return $driver;
                });
            // $drivers = delivery_driver::where('online', 1)->whereNotNull('latitude')->whereNotNull('longitude')->get();
            // $drivers = delivery_driver::where('available', 1)->where('online', 1)->whereNotNull('latitude')->whereNotNull('longitude')->get();
            if ($drivers->isEmpty()) {
                return response()->json([
                    'status_code' => 200,
                    'data' => $drivers,
                    'message' => 'drivers retrieved successfully'
                ], 200);
            }

            // Get list of driver IDs from delivery_request where order_id matches and status is not cancelled
            $cancelledDriverIds = delivery_request::where('order_id', $request->order_id)->whereIn('status', ['cancelled', 'rejected'])
                ->pluck('driver_id')
                ->toArray();

            if (!empty($cancelledDriverIds)) {
                $drivers = $drivers->filter(function ($driver) use ($cancelledDriverIds) {
                    return !in_array($driver->_id, $cancelledDriverIds);
                });
                $drivers = $drivers->values();
            }
            if ($request->latitude && $request->longitude) {
                $destination = ['latitude' => $request->latitude, 'longitude' => $request->longitude];
                $origins = $drivers->map(function ($driver) {
                    return ['latitude' => $driver->latitude, 'longitude' => $driver->longitude];
                })->toArray();
                $distances = $this->calculateDistances($origins, $destination);
                if (empty($distances)) {
                    return response()->json([
                        'status_code' => 500,
                        'message' => 'Failed to retrieve distances from Google Maps API'
                    ], 500);
                }
                // Filter valid distances and map them to drivers
                $validDrivers = [];
                foreach ($distances as $index => $distance) {
                    if ($distance['status'] === 'OK') {
                        $driver = $drivers[$index] ?? null;
                        if ($driver) {
                            $driver->distance_value = $distance['distance']['value'];
                            $driver->distance = $distance['distance']['text'];
                            $validDrivers[] = $driver;
                        }
                    }
                }

                $sortedDrivers = collect($validDrivers)->sortBy('distance_value')->values();
            } else {
                $sortedDrivers = $drivers;
            }
            foreach ($sortedDrivers as $index => $driver) {
                $deliveryRequest = delivery_request::where('driver_id', $driver->_id)
                    ->where('order_id', $request->order_id)
                    ->first();
                $driver->status = $deliveryRequest ? $deliveryRequest->status : null;
            }


            return response()->json([
                'status_code' => 200,
                'data' => $sortedDrivers,
                'message' => 'Drivers retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve drivers'
            ], 500);
        }
    }
}
