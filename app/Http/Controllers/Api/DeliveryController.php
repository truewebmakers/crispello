<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\address;
use Illuminate\Http\Request;
use App\Models\admin;
use App\Models\admin_fcm_token;
use App\Models\delivery_driver;
use App\Models\delivery_fcm_token;
use App\Models\delivery_request;
use App\Models\DeliveryPartnerFareLogs;
use App\Models\DeliveryPartnerFareSetting;
use App\Models\order;
use App\Models\order_customization;
use App\Models\order_product;
use App\Models\User;
use App\Models\user_fcm_token;
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
            $driver = User::find($request->driver_id);
            if (!$driver) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Driver not found'
                ], 404);
            }
            $deliveryRequest = delivery_request::where('driver_id', $driver->_id)->whereIn('status', ['pending', 'accepted'])->get();
            $result = [];
            foreach ($deliveryRequest as $req) {
                $order = order::find($req->order_id);
                if (!$order) {
                    continue;
                }
                $order->makeHidden('delivery_charge', 'order_type', 'payment_id', 'table_no', 'longitude', 'latitude', 'house_no', 'area', 'options_to_reach', 'coupon_id', 'user_id');
                $order->products = order_product::select('_id', 'name', 'arabic_name', 'size', 'arabic_size', 'quantity')->where('order_id', $req->order_id)->get();
                $order->products = $order->products->map(function ($product) {
                    $customizations = order_customization::where('order_product_id', $product->_id)->get();
                    $product->customization = $customizations->isEmpty() ? null : $customizations;
                    return $product;
                });
                $user = User::select('_id', 'name', 'phoneno')->where('_id', $order->user_id)->first();
                if (!$user) {
                    continue;
                }
                $user->latitude = $order->latitude;
                $user->longitude = $order->longitude;
                $user->house_no = $order->house_no;
                $user->area = $order->area;
                $user->options_to_reach = $order->options_to_reach;
                // $restaurant = admin::select('_id', 'phoneno', 'email', 'latitude', 'longitude')->first();
                // if (!$restaurant) {
                //     continue;
                // }
                $adminUser = User::where('user_role', 'admin')->first();
                if (!$adminUser) {
                    continue;
                }

                $adminAddress = address::where('user_id', $adminUser->_id)->first(['latitude', 'longitude', 'area']);
                if (!$adminAddress) {
                    continue;
                }

                $restaurant = (object) [
                    '_id' => $adminUser->_id,
                    'phoneno' => $adminUser->phoneno ?? null,
                    'email' => $adminUser->email ?? null,
                    'latitude' => $adminAddress->latitude ?? null,
                    'longitude' => $adminAddress->longitude ?? null,
                    'area' => $adminAddress->area ?? null,
                    'name' => $adminUser->name
                ];
                if ($user->latitude && $user->longitude && $restaurant->latitude && $restaurant->longitude) {
                    $distance = $this->calculateDistanceWithDuration(['latitude' => $restaurant->latitude, 'longitude' => $restaurant->longitude], ['latitude' => $user->latitude, 'longitude' => $user->longitude]);
                    $order->distance = $distance['distance']['text'];
                    $order->duration = $distance['duration']['text'];
                    $order->distance_value = round($distance['distance']['value'] / 1000);
                    $deliveryFareSetting = DeliveryPartnerFareSetting::where('added_by', $restaurant->_id)->first();
                    if ($deliveryFareSetting && isset($distance['distance']['value']) && $distance['distance']['value'] !== null) {
                        $distanceInKm = round($distance['distance']['value'] / 1000);
                        $order->delivery_partner_earning = $deliveryFareSetting->fare_per_km * $distanceInKm;
                        $order->delivery_partner_earning_currency = $deliveryFareSetting->currency;
                    } else {
                        $order->delivery_partner_earning = 0;
                        $order->delivery_partner_earning_currency = 'INR';
                    }
                }
                $result[] = [
                    'order' => $order,
                    'user' => $user,
                    'restaurant' => $restaurant,
                    'delivery_request' => $req
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
            $driver = User::find($request->driver_id);
            if (!$driver) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Driver not found'
                ], 404);
            }
            $deliveryRequest = delivery_request::where('driver_id', $driver->_id)->whereIn('status', ['completed', 'rejected'])->get();
            $result = [];
            foreach ($deliveryRequest as $req) {
                $order = order::find($req->order_id);
                if (!$order) {
                    continue;
                }
                $order->makeHidden('delivery_charge', 'order_type', 'total', 'payment_id', 'table_no', 'longitude', 'latitude', 'house_no', 'area', 'options_to_reach', 'coupon_id', 'user_id', 'paid', 'payment_method');
                $order->delivery_request_status = $req->status;
                $user = User::select('_id', 'name')->where('_id', $order->user_id)->first();
                if (!$user) {
                    continue;
                }
                $user->latitude = $order->latitude;
                $user->longitude = $order->longitude;
                $user->house_no = $order->house_no;
                $user->area = $order->area;
                $user->options_to_reach = $order->options_to_reach;
                $adminUser = User::where('user_role', 'admin')->first();
                if (!$adminUser) {
                    continue;
                }

                $adminAddress = address::where('user_id', $adminUser->_id)->first(['latitude', 'longitude', 'area']);
                if (!$adminAddress) {
                    continue;
                }

                $restaurant = (object) [
                    '_id' => $adminUser->_id,
                    'phoneno' => $adminUser->phoneno ?? null,
                    'email' => $adminUser->email ?? null,
                    'latitude' => $adminAddress->latitude ?? null,
                    'longitude' => $adminAddress->longitude ?? null,
                    'area' => $adminAddress->area ?? null,
                    'name' => $adminUser->name
                ];
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
            $driver = User::find($request->driver_id);
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
            $order->makeHidden('user_id', 'delivery_charge', 'order_type', 'payment_id', 'table_no', 'longitude', 'latitude', 'house_no', 'area', 'options_to_reach', 'coupon_id');
            $order->delivery_status = $deliveryRequest->status;
            $order->products = order_product::select('_id', 'name', 'arabic_name', 'size', 'arabic_size', 'quantity')->where('order_id', $order->_id)->get();
            $order->products = $order->products->map(function ($product) {
                $customizations = order_customization::where('order_product_id', $product->_id)->get();
                $product->customization = $customizations->isEmpty() ? null : $customizations;
                return $product;
            });
            $user = User::select('_id', 'phoneno', 'name')->where('_id', $order->user_id)->first();
            if (!$user) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'User not found'
                ], 404);
            }
            $user->latitude = $order->latitude;
            $user->longitude = $order->longitude;
            $user->house_no = $order->house_no;
            $user->area = $order->area;
            $user->options_to_reach = $order->options_to_reach;
            // $restaurant = admin::select('_id', 'phoneno', 'email', 'latitude', 'longitude')->first();
            // if (!$restaurant) {
            //     return response()->json([
            //         'status_code' => 404,
            //         'message' => 'Restaurant not found'
            //     ], 404);
            // }
            // $restaurant->name = "Crispello";
            $adminUser = User::where('user_role', 'admin')->first();
            if (!$adminUser) {
                return response()->json([
                    'status_code' => 404,
                    'messsage' => 'Restaurant not found'
                ], 404);
            }

            $adminAddress = address::where('user_id', $adminUser->_id)->first(['latitude', 'longitude', 'area']);
            if (!$adminAddress) {
                return response()->json([
                    'status_code' => 404,
                    'messsage' => 'Restaurant not found'
                ], 404);
            }

            $restaurant = (object) [
                '_id' => $adminUser->_id,
                'phoneno' => $adminUser->phoneno ?? null,
                'email' => $adminUser->email ?? null,
                'latitude' => $adminAddress->latitude ?? null,
                'longitude' => $adminAddress->longitude ?? null,
                'area' => $adminAddress->area ?? null,
                'name' => $adminUser->name
            ];
            $deliveryFare=DeliveryPartnerFareLogs::where('order_id',$order->_id)->where('delivery_partner_id',$driver->_id)->where('status','pending')->first();
            $data = [
                'order' => $order,
                'user' => $user,
                'restaurant' => $restaurant,
                'delivery_fare' => $deliveryFare,
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
            $driver = User::find($request->driver_id);
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
                    $fcm_tokens = user_fcm_token::whereNotNull('token')->where('user_id', $other_driver->driver_id)->pluck('token')->all();
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
                $admin = User::where('user_role', 'admin')->first();
                if ($admin) {
                    $admin_tokens = user_fcm_token::where('user_id', $admin->_id)->whereNotNull('token')->pluck('token')->all();
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
                $admin = User::where('user_role', 'admin')->first();
                if ($admin) {
                    $admin_tokens = user_fcm_token::where('user_id', $admin->_id)->whereNotNull('token')->pluck('token')->all();
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

    /**
     * Get all orders of driver
     */
    public function getOrderHistoryOfDeliveryPartner(Request $request)
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
            $fareLogs = DeliveryPartnerFareLogs::select(
                'delivery_partner_fare_logs.*',
                'orders.order_status',
                'orders.order_date'
            )
            ->where('delivery_partner_fare_logs.delivery_partner_id', $request->driver_id)
            ->whereIn('delivery_partner_fare_logs.status', ['pending', 'credit'])
            ->leftJoin('orders', 'delivery_partner_fare_logs.order_id', '=', 'orders._id')
            ->get();
            
            return response()->json([
                'status_code' => 200,
                'data' => $fareLogs,
                'message' => 'Orders retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve orders'
            ], 500);
        }
    }
}
