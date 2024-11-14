<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\admin;
use App\Models\admin_fcm_token;
use App\Models\delivery_driver;
use App\Models\delivery_fcm_token;
use App\Models\delivery_request;
use App\Models\order;
use App\Models\order_product;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Traits\CalculateDistanceTrait;
use Illuminate\Support\Facades\DB;
use App\Traits\FCMNotificationTrait;

class DeliveryController extends Controller
{
    use CalculateDistanceTrait, FCMNotificationTrait;

    /**
     * Get all delivery requests(pending requests)
     */
    public function getAllDeliveryRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        try {
            $driver = delivery_driver::find($request->driver_id);
            if (!$driver) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Driver not found'
                ], 404);
            }
            $deliveryRequest = delivery_request::where('driver_id', $driver->_id)->where('status', 'pending')->get();
            $result = [];
            foreach ($deliveryRequest as $req) {
                $order = order::find($req->order_id);
                if (!$order) {
                    continue;
                }
                $order->makeHidden('delivery_charge', 'order_type', 'payment_id', 'table_no', 'location', 'longitude', 'latitude', 'house_no', 'area', 'options_to_reach', 'coupon_id', 'user_id');
                if ($order->delivery_charge) {
                    $order->total += $order->delivery_charge;
                }
                $order->products = order_product::select('_id', 'name','arabic_name', 'size','arabic_size', 'quantity')->where('order_id', $req->order_id)->get();
                $user = User::select('_id', 'name', 'phoneno')->where('_id', $order->user_id)->first();
                if (!$user) {
                    continue;
                }
                $user->location = $order->location;
                $user->latitude = $order->latitude;
                $user->longitude = $order->longitude;
                $user->house_no = $order->house_no;
                $user->area = $order->area;
                $user->options_to_reach = $order->options_to_reach;
                $restaurant = admin::select('_id', 'phoneno', 'email', 'location', 'latitude', 'longitude')->first();
                if (!$restaurant) {
                    continue;
                }
                $restaurant->name = "Crispello";
                if ($user->latitude && $user->longitude && $restaurant->latitude && $restaurant->longitude) {
                    $distance = $this->calculateDistanceWithDuration(['latitude' => $restaurant->latitude, 'longitude' => $restaurant->longitude], ['latitude' => $user->latitude, 'longitude' => $user->longitude]);
                    $order->distance = $distance['distance']['text'];
                    $order->duration = $distance['duration']['text'];
                }
                $result[] = [
                    'order' => $order,
                    'user' => $user,
                    'restaurant' => $restaurant
                ];
            }
            return response()->json([
                'status_code' => 200,
                'data' => $result,
                'message' => 'Delivery request retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve delivery requests'
            ], 500);
        }
    }

    /**
     * Get all delivery requests(pending requests)
     */
    public function getAllCompletedAndRejectedDeliveries(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        try {
            $driver = delivery_driver::find($request->driver_id);
            if (!$driver) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Driver not found'
                ], 404);
            }
            $deliveryRequest = delivery_request::where('driver_id', $driver->_id)->whereIn('status', ['accepted', 'rejected'])->get();
            $result = [];
            foreach ($deliveryRequest as $req) {
                $order = order::find($req->order_id);
                if (!$order) {
                    continue;
                }
                $order->makeHidden('delivery_charge', 'order_type', 'total', 'payment_id', 'table_no', 'location', 'longitude', 'latitude', 'house_no', 'area', 'options_to_reach', 'coupon_id', 'user_id', 'paid', 'payment_method');
                $order->delivery_request_status = $req->status;
                $user = User::select('_id', 'name')->where('_id', $order->user_id)->first();
                if (!$user) {
                    continue;
                }
                $user->location = $order->location;
                $user->latitude = $order->latitude;
                $user->longitude = $order->longitude;
                $user->house_no = $order->house_no;
                $user->area = $order->area;
                $user->options_to_reach = $order->options_to_reach;
                $restaurant = admin::select('_id', 'phoneno', 'location', 'latitude', 'longitude')->first();
                if (!$restaurant) {
                    continue;
                }
                $restaurant->name = "Crispello";
                if ($user->latitude && $user->longitude && $restaurant->latitude && $restaurant->longitude) {
                    $distance = $this->calculateDistanceWithDuration(['latitude' => $restaurant->latitude, 'longitude' => $restaurant->longitude], ['latitude' => $user->latitude, 'longitude' => $user->longitude]);
                    $order->distance = $distance['distance']['text'];
                    $order->duration = $distance['duration']['text'];
                }
                $result[] = [
                    'order' => $order,
                    'user' => $user,
                    'restaurant' => $restaurant
                ];
            }
            return response()->json([
                'status_code' => 200,
                'data' => $result,
                'message' => 'Deliveries retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve deliveries'
            ], 500);
        }
    }

    /**
     * Get single delivery request
     */
    public function getDeliveryRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required',
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        try {
            $driver = delivery_driver::find($request->driver_id);
            if (!$driver) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Driver not found'
                ], 404);
            }
            $order = order::find($request->order_id);
            if (!$order) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Order not found'
                ], 404);
            }
            $deliveryRequest = delivery_request::where('driver_id', $driver->_id)->where('order_id', $order->_id)->first();
            if (!$deliveryRequest) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'There is no request made to this driver for this order'
                ], 404);
            }
            $order->makeHidden('user_id', 'delivery_charge', 'order_type', 'payment_id', 'table_no', 'location', 'longitude', 'latitude', 'house_no', 'area', 'options_to_reach', 'coupon_id');
            if ($order->delivery_charge) {
                $order->total += $order->delivery_charge;
            }
            $order->delivery_status = $deliveryRequest->status;
            $order->products = order_product::select('_id', 'name','arabic_name', 'size','arabic_size', 'quantity')->where('order_id', $order->_id)->get();
            $user = User::select('_id', 'phoneno', 'name')->where('_id', $order->user_id)->first();
            if (!$user) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'User not found'
                ], 404);
            }
            $user->latitude = $order->latitude;
            $user->longitude = $order->longitude;
            $user->location = $order->location;
            $user->house_no = $order->house_no;
            $user->area = $order->area;
            $user->options_to_reach = $order->options_to_reach;
            $restaurant = admin::select('_id', 'phoneno', 'email', 'location', 'latitude', 'longitude')->first();
            if (!$restaurant) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Restaurant not found'
                ], 404);
            }
            $restaurant->name = "Crispello";
            $data = [
                'order' => $order,
                'user' => $user,
                'restaurant' => $restaurant
            ];
            return response()->json([
                'status_code' => 200,
                'data' => $data,
                'messsage' => 'Delivery request retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve delivery request'
            ], 500);
        }
    }

    /**
     * Accept delivery request
     */
    public function changeDeliveryRequestStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required',
            'order_id' => 'required',
            'status' => 'in:accepted,rejected'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $driver = delivery_driver::find($request->driver_id);
            if (!$driver) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Driver not found'
                ], 404);
            }
            $order = order::find($request->order_id);
            if (!$order) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Order not found'
                ], 404);
            }
            $deliveryRequest = delivery_request::where('order_id', $order->_id)->where('driver_id', $driver->_id)->first();
            if (!$deliveryRequest) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'There is no request made to this driver for this order'
                ], 404);
            }
            if ($request->status === 'accepted') {
                delivery_request::where('order_id', $order->_id)->where('driver_id', $driver->_id)->update(['status' => 'accepted']);

                $driver->available = 0;
                $driver->save();

                $order->driver_id = $driver->_id;
                $order->save();
                $other_drivers = delivery_request::where('order_id', $order->_id)->where('driver_id', '!=', $driver->_id)->get();
                foreach ($other_drivers as $other_driver) {
                    // $other_driver->status = 'cancelled';
                    delivery_request::where('order_id', $order->_id)->where('driver_id', $other_driver->driver_id)->update(['status' => 'cancelled']);
                    // $other_driver->save();
                    $fcm_tokens = delivery_fcm_token::whereNotNull('token')->where('driver_id', $other_driver->driver_id)->pluck('token')->all();
                    if (!empty($fcm_tokens)) {
                        $validTokens = $this->validateTokens($fcm_tokens, 0, 0, 1);
                        if (!empty($validTokens)) {
                            $title = 'Delivery Request Cancelled';
                            $message = 'The current delivery request is cancelled. No further action is needed.';
                            $this->sendDeliveryNotification(
                                $fcm_tokens,
                                $title,
                                $message,
                                'new_delivery',
                                $order->_id,
                                $other_driver->driver_id,
                                0
                            );
                        }
                    }
                }
                DB::commit();
                $admin_tokens = admin_fcm_token::whereNotNull('token')->pluck('token')->all();
                if (!empty($admin_tokens)) {
                    $validTokens = $this->validateTokens($admin_tokens, 1, 0, 0);
                    if (!empty($validTokens)) {
                        $message = $driver->name ? $driver->name . ' has accepted the delivery request for order #' . $order->_id . '.' : 'Delivery Boy has accepted the delivery request for order #' . $order->_id . '.';
                        $this->sendDeliveryNotification(
                            $validTokens,
                            'Delivery Request Accepted',
                            $message,
                            'order',
                            $order->_id,
                            null,
                            0
                        );
                    }
                }

                return response()->json([
                    'status_code' => 200,
                    'message' => 'Delivery request accepted successfully'
                ], 200);
            } else if ($request->status === 'rejected') {
                if ($deliveryRequest->status === 'accepted') {
                    return response()->json([
                        'status_code' => 400,
                        'message' => 'You have already accepted delivery request so you can not reject the request'
                    ], 200);
                }
                delivery_request::where('order_id', $order->_id)->where('driver_id', $driver->_id)->update(['status' => 'rejected']);
                DB::commit();
                $admin_tokens = admin_fcm_token::whereNotNull('token')->pluck('token')->all();
                if (!empty($admin_tokens)) {
                    $validTokens = $this->validateTokens($admin_tokens, 1, 0, 0);
                    if (!empty($validTokens)) {
                        $message = $driver->name ? $driver->name . ' has rejected the delivery request for order #' . $order->_id . '.' : 'Delivery Boy has rejected the delivery request for order #' . $order->_id . '.';
                        $this->sendDeliveryNotification(
                            $validTokens,
                            'Delivery Request Rejected',
                            $message,
                            'order',
                            $order->_id,
                            null,
                            0
                        );
                    }
                }
                return response()->json([
                    'status_code' => 200,
                    'message' => 'Delivery request rejected successfully'
                ], 200);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to change delivery request status'
            ], 500);
        }
    }
}
