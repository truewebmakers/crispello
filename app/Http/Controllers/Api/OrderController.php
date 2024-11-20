<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\address;
use App\Models\admin;
use App\Models\admin_fcm_token;
use App\Models\cart;
use App\Models\cart_product;
use App\Models\combo;
use App\Models\customization;
use App\Models\delivery_driver;
use App\Models\delivery_fcm_token;
use App\Models\delivery_request;
use App\Models\notification;
use App\Models\order;
use App\Models\order_customization;
use App\Models\order_product;
use App\Models\product;
use App\Models\product_size;
use App\Models\User;
use App\Models\user_fcm_token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Traits\FCMNotificationTrait;
use App\Traits\WhatsappMessageTrait;
use App\Traits\CalculateDistanceTrait;
use Carbon\Carbon;

class OrderController extends Controller
{
    use FCMNotificationTrait, WhatsappMessageTrait, CalculateDistanceTrait;
    /**
     * Place Order.
     */
    public function placeOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required',
            'order_type' => 'required|in:Delivery,Dine In,Pickup',
            'table_no' => 'required_if:order_type,Dine In',
            'address_id' => 'required_if:order_type,Delivery',
            'payment_method' => 'sometimes|boolean',
            'total' => 'required',
            'paid' => 'required|boolean'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $cart = cart::find($request->cart_id);
            if (!$cart) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Cart not found'
                ], 404);
            }
            if ($request->has('order_type')) {
                $cart->order_type = $request->order_type;
            }
            if ($cart->order_type === 'Dine In' && $request->has('table_no')) {
                $cart->table_no = $request->table_no;
                $cart->address_id = null;
            } else if ($cart->order_type === 'Delivery' && $request->has('address_id')) {
                $address = address::find($request->address_id);
                if (!$address) {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'Address not found.'
                    ], 404);
                }
                $cart->address_id = $request->address_id;
                $cart->table_no = null;
            } else if ($cart->order_type === 'Pickup') {
                $cart->address_id = null;
                $cart->table_no = null;
            }
            if ($request->has('payment_method')) {
                if ($cart->order_type === 'Dine In') {
                    if ($request->payment_method) {
                        return response()->json([
                            'status_code' => 400,
                            'message' => 'Invalid option selected for payment method'
                        ], 400);
                    }
                }
                $cart->payment_method = $request->payment_method;
            }
            if ($request->filled($request->coupon_id)) {
                $cart->coupon_id = $request->coupon_id;
            }
            $cart->save();
            $cart_products = cart_product::where('cart_id', $cart->_id)->get();
            if ($cart_products->isEmpty()) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'There is no products in the cart'
                ], 404);
            }
            if ($cart->payment_method === 1 && !$request->payment_id) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'Payment id is required'
                ], 400);
            }
            $order = new order();
            $order->user_id = $cart->user_id;
            $order->total = $request->total;
            $order->order_type = $cart->order_type;
            $order->payment_method = $cart->payment_method;
            $order->paid = $request->paid;
            $order->order_status = 'pending';
            $order->delivery_charge = $request->delivery_charge;
            $order->order_date = Carbon::now()->toIso8601String();
            if ($cart->payment_method === 1) {
                $order->payment_id = $request->payment_id;
            }
            if ($cart->order_type === 'Dine In') {
                if ($cart->table_no) {
                    $order->table_no = $cart->table_no;
                } else {
                    return response()->json([
                        'status_code' => 400,
                        'message' => 'Table no is required when order type is Dine In'
                    ], 400);
                }
            } else if ($cart->order_type === 'Delivery') {
                if ($cart->address_id) {
                    $address = address::find($cart->address_id);
                    $order->longitude = $address->longitude;
                    $order->latitude = $address->latitude;
                    $order->house_no = $address->house_no;
                    $order->area = $address->area;
                    $order->options_to_reach = $address->options_to_reach;
                } else {
                    return response()->json([
                        'status_code' => 400,
                        'message' => 'Address is required when order type is Delivery'
                    ], 400);
                }
            }
            if ($cart->coupon_id) {
                $order->coupon_id = $cart->coupon_id;
            }
            $order->save();
            foreach ($cart_products as $item) {
                if ($item->product_id) {
                    $product = product::where('_id', $item->product_id)->where('disable', 0)->first();
                    if (!$product) {
                        return response()->json([
                            'status_code' => 404,
                            'message' => 'Product with id ' . $item->product_id . ' not found'
                        ], 404);
                    }
                    $product_size = null;
                    if ($item->size) {
                        $product_size = product_size::where('_id', $item->size)->where('product_id', $item->product_id)->first();
                        if (!$product_size) {
                            return response()->json([
                                'status_code' => 404,
                                'message' => 'Product size with id ' . $item->size . ' not found'
                            ], 404);
                        }
                    }
                    $order_product = new order_product();
                    $order_product->order_id = $order->_id;
                    $order_product->name = $product->name;
                    $order_product->size = $product_size ? $product_size->size : null;
                    $order_product->size_id = $product_size ? $product_size->_id : null;
                    // $order_product->price =  $product_size ? $product_size->selling_price : $product->selling_price;
                    $order_product->quantity = $item->quantity;
                    $order_product->veg = $product->veg;
                    $order_product->product_id  = $product->_id;
                    if ($order->order_type === 'Delivery') {
                        $order_product->price = $product_size ? $product_size->delivery_selling_price : $product->delivery_selling_price;
                    } else if ($order->order_type === 'Dine In') {
                        $order_product->price = $product_size ? $product_size->dinein_selling_price : $product->dinein_selling_price;
                    } else if ($order->order_type === 'Pickup') {
                        $order_product->price = $product_size ? $product_size->pickup_selling_price : $product->pickup_selling_price;
                    }
                    $order_product->save();
                    if ($item->customization) {
                        $selectedCustomizationIds = json_decode($item->customization, true);
                        if (!is_null($selectedCustomizationIds) && is_array($selectedCustomizationIds)) {
                            foreach ($selectedCustomizationIds as $customizationId) {
                                $customization = customization::findOrFail($customizationId);
                                $order_customization = new order_customization();
                                $order_customization->name = $customization->name;
                                $order_customization->price = $customization->price;
                                $order_customization->veg = $customization->veg;
                                $order_customization->type = $customization->type;
                                $order_customization->order_product_id = $order_product->_id;
                                $order_customization->order_id = $order->_id;
                                $order_customization->save();
                            }
                        }
                    }
                } else if ($item->combo_id) {
                    $combo = combo::where('_id', $item->combo_id)->where('disable', 0)->first();
                    if (!$combo) {
                        return response()->json([
                            'status_code' => 404,
                            'message' => 'Combo with id ' . $item->combo_id . ' not found'
                        ], 404);
                    }
                    $order_product = new order_product();
                    $order_product->order_id = $order->_id;
                    $order_product->name = $combo->name;
                    // $order_product->price = $combo->selling_price;
                    $order_product->quantity = $item->quantity;
                    $order_product->veg = $combo->veg;
                    $order_product->combo_id  = $combo->_id;
                    if ($order->order_type === 'Delivery') {
                        $order_product->price = $combo->delivery_selling_price;
                    } else if ($order->order_type === 'Dine In') {
                        $order_product->price = $combo->dinein_selling_price;
                    } else if ($order->order_type === 'Pickup') {
                        $order_product->price = $combo->pickup_selling_price;
                    }
                    $order_product->save();
                }
            }
            cart_product::where('cart_id', $cart->_id)->delete();
            $cart->coupon_id = null;
            $cart->table_no = null;
            $cart->order_type = 'Delivery';
            $cart->payment_method = 0;
            $defaultAddress = address::where('user_id', $cart->user_id)->where('is_default', 1)->first();
            $cart->address_id = $defaultAddress ? $defaultAddress->_id : null;
            $cart->save();
            DB::commit();

            //send notification to admin and customer
            $admin = User::where('user_role', 'admin')->first();
            if ($admin) {
                $fcm_tokens_admin = user_fcm_token::where('user_id', $admin->_id)->whereNotNull('token')->pluck('token')->all();
                if (!empty($fcm_tokens_admin)) {
                    $validTokens = $this->validateTokens($fcm_tokens_admin, 1, 0, 0);
                    if (!empty($validTokens)) {
                        $this->sendNotification(
                            $validTokens,
                            'New Order Received!',
                            "Order ID: " . $order->_id,
                            null,
                            'new_order',
                            null,
                            null,
                            1,
                            null
                        );
                    }
                }
            }
            $fcm_tokens_customer = user_fcm_token::whereNotNull('token')->where('user_id', $order->user_id)->pluck('token')->all();
            if (!empty($fcm_tokens_customer)) {
                $validTokens = $this->validateTokens($fcm_tokens_customer, 0, 1, 0);
                if (!empty($validTokens)) {
                    $this->sendNotification(
                        $validTokens,
                        "Order ID: " . $order->_id,
                        'Your order is currently pending approval. We will notify you as soon as it has been accepted and is being processed. Thank you for your patience.',
                        null,
                        'order',
                        $order->order_status,
                        $order->_id,
                        0,
                        null
                    );
                }
            }
            if ($request->order_type === 'Delivery') {
                $admin = User::where('user_role', 'admin')->first();
                $restaurant_location = address::where('user_id', $admin->_id)->first();

                // $restaurant_location = admin::select('_id', 'latitude', 'longitude')->first();
                if ($restaurant_location) {
                    if ($restaurant_location->latitude && $restaurant_location->longitude) {
                        $drivers = $this->getDriversList($order->_id, $restaurant_location->latitude, $restaurant_location->longitude);
                        if (!empty($drivers)) {
                            $drivers = $drivers->toArray();
                            // Get the first 4 drivers
                            $firstFourDrivers = array_slice($drivers, 0, 4);
                            $existingRequest = delivery_request::where('order_id', $order->_id)->where('status', 'accepted')->first();
                            if (!$existingRequest) {
                                foreach ($firstFourDrivers as $driver) {
                                    if ($driver['online'] === 1) {
                                        // if ($driver->available === 1 && $driver->online === 1) {
                                        $delivery_request = new delivery_request();
                                        $delivery_request->order_id = $order->_id;
                                        $delivery_request->driver_id = $driver['_id'];
                                        $delivery_request->status = 'pending';
                                        $delivery_request->save();
                                        $fcm_tokens = user_fcm_token::whereNotNull('token')->where('driver_id', $driver['_id'])->pluck('token')->all();
                                        if (!empty($fcm_tokens)) {
                                            $validTokens = $this->validateTokens($fcm_tokens, 0, 0, 1);
                                            if (!empty($validTokens)) {
                                                $title = 'New Delivery Assigned!';
                                                $message = 'A new delivery order is ready. Please open the app to view the details and start delivering.';
                                                $this->sendDeliveryNotification(
                                                    $fcm_tokens,
                                                    $title,
                                                    $message,
                                                    'new_delivery',
                                                    $order->_id,
                                                    $driver['_id'],
                                                    0
                                                );
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // $user = User::find($order->user_id);
            // $message = '';
            // $message .= "*Thank you for your order!* \n\n";
            // $message .= "*Order Summary*\n\n";
            // $message .= "Order Type: " . $order->order_type . "\n";
            // $message .= "Ordered Products:\n";
            // $product_total = 0;
            // $discount = 0;
            // $order_products = order_product::where('order_id', $order->_id)->get();
            // foreach ($order_products as $index => $order_product) {
            //     $product_total += $order_product->price * $order_product->quantity;
            //     $message .= ($index + 1) . ". " . $order_product->name;
            //     if ($order_product->size) {
            //         $message .= "(" . $order_product->size . ")\n";
            //     } else {
            //         $message .= "\n";
            //     }
            //     $message .= "Price: ₹" . $order_product->price . "\n";
            //     $message .= "Quantity: " . $order_product->quantity . "\n";
            //     $message .= "----------------------------\n";
            // }
            // $discount = $product_total - $order->total;
            // if ($discount > 0 && $order->delivery_charge) {
            //     $message .= "Total Amount: ₹" . $product_total . "\n";
            //     $message .= "Discount: ₹" . $discount . "\n";
            //     $message .= "Delivery Charge: ₹" . $order->delivery_charge . "\n";
            //     $message .= "Grand Total: ₹" . $order->total + $order->delivery_charge . "\n";
            // } else if ($discount > 0) {
            //     $message .= "Total Amount: ₹" . $product_total . "\n";
            //     $message .= "Discount: ₹" . $discount . "\n";
            //     $message .= "Grand Total: ₹" . $order->total . "\n";
            // } else if ($order->delivery_charge) {
            //     $message .= "Total Amount: ₹" . $order->total . "\n";
            //     $message .= "Delivery Charge: ₹" . $order->delivery_charge . "\n";
            //     $message .= "Grand Total: ₹" . $order->total + $order->delivery_charge . "\n";
            // } else {
            //     $message .= "Total Amount: ₹" . $order->total . "\n";
            // }

            // if ($message != '') {
            //     $this->sendWhatsappMessage($user->phoneno, $message);
            // }
            return response()->json([
                'status_code' => 200,
                'order_id' => $order->_id,
                'message' => 'Order placed successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to place order.'
            ], 500);
        }
    }
    public function getDriversList($order_id, $latitude, $longitude)
    {
         // Retrieve drivers with valid addresses
         $drivers = User::where('online', 1)->where('user_role','delivery_partner')
         ->whereHas('deliverAddress', function ($query) {
             $query->whereNotNull('latitude')
                 ->whereNotNull('longitude');
         })
         ->with('deliverAddress')
         ->get() ->map(function ($driver) {
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
            return [];
        }
        $cancelledDriverIds = delivery_request::where('order_id', $order_id)->whereIn('status', ['cancelled', 'rejected'])
            ->pluck('driver_id')
            ->toArray();

        if (!empty($cancelledDriverIds)) {
            $drivers = $drivers->filter(function ($driver) use ($cancelledDriverIds) {
                return !in_array($driver->_id, $cancelledDriverIds);
            });
            $drivers = $drivers->values();
        }
        $sortedDrivers = [];
        if ($latitude && $longitude) {
            $destination = ['latitude' => $latitude, 'longitude' => $longitude];
            $origins = $drivers->map(function ($driver) {
                return ['latitude' => $driver->latitude, 'longitude' => $driver->longitude];
            })->toArray();
            $distances = $this->calculateDistances($origins, $destination);
            if (empty($distances)) {
                return [];
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
        return $sortedDrivers;
    }

    /**
     * Update Paid or Order Status From Admin.
     */
    public function updateStatusAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'paid' => 'sometimes|boolean',
            // 'order_status' => 'sometimes|in:accepted,preparing,dispatched,delivered,cancelled',
            'order_status' => ['sometimes', function ($attribute, $value, $fail) use ($request) {
                if ($request->has('admin') && $request->admin === 0) {
                    if ($value !== 'delivered') {
                        $fail('The order_status value must be delivered.');
                    }
                } else {
                    $validStatuses = ['accepted', 'preparing', 'dispatched', 'delivered', 'cancelled'];
                    if (!in_array($value, $validStatuses)) {
                        $fail('The order_status is invalid.');
                    }
                }
            }],
            'admin' => 'boolean'
            // 'payment_id' => 'sometimes'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $order = order::find($request->order_id);
            if (!$order) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Order not found'
                ], 404);
            }
            if ($request->has('paid')) {
                // if ($order->payment_method === 1 && $request->paid === 1 && !$request->payment_id) {
                //     return response()->json([
                //         'status_code' => 400,
                //         'message' => 'Payment ID is required for this payment method pay through app when marked as paid'
                //     ], 400);
                // }
                $order->paid = $request->paid;
                $order->payment_id = $request->payment_id;
            }
            if ($request->has('order_status')) {
                $order->order_status = $request->order_status;
            }
            $order->save();
            if ($request->has('admin') && $request->admin === 0 && $order->order_status === 'delivered') {
                if ($order->driver_id) {
                    User::where('_id', $order->driver_id)->update(['available' => 1]);
                }
            }
            DB::commit();
            //send notification to user
            $fcm_tokens = user_fcm_token::whereNotNull('token')->where('user_id', $order->user_id)->pluck('token')->all();
            if (!empty($fcm_tokens)) {
                $validTokens = $this->validateTokens($fcm_tokens, 0, 1, 0);
                if (!empty($validTokens)) {
                    $message = '';
                    if ($order->order_status === 'accepted') {
                        $message = 'Your order has been accepted and is now being processed.';
                    } else if ($order->order_status === 'preparing') {
                        $message = 'We are preparing your order with care. It will be on its way soon!';
                    } else if ($order->order_status === 'dispatched') {
                        $message = 'Good news! Your order has been dispatched and is en route to you.';
                    } else if ($order->order_status === 'delivered') {
                        $message = 'Congratulations! Your order has been successfully delivered.';
                    } else if ($order->order_status === 'cancelled') {
                        $message = 'We regret to inform you that your order has been cancelled. Please contact us for further assistance.';
                    }
                    $title = "Order ID: " . $order->_id;

                    $notification = new notification();
                    $notification->user_id = $order->user_id;
                    $notification->title = $title;
                    $notification->body = $message;
                    $notification->type = 'order';
                    $notification->save();
                    $this->sendNotification(
                        $fcm_tokens,
                        $title,
                        $message,
                        null,
                        'order',
                        $order->order_status,
                        $order->_id,
                        0,
                        $notification->_id
                    );
                }
            }
            if ($request->has('admin') && $request->admin === 0 && $order->order_status === 'delivered') {
                $admin=User::where('user_role','admin')->first();
                $fcm_tokens = user_fcm_token::where('user_id',$admin->_id)->whereNotNull('token')->pluck('token')->all();
                if (!empty($fcm_tokens)) {
                    $validTokens = $this->validateTokens($fcm_tokens, 1, 0, 0);
                    if (!empty($validTokens)) {
                        $message = "The order #" . $order->_id . " has been successfully delivered to the customer.";
                        $title = "Delivery Completed";
                        $this->sendNotification(
                            $fcm_tokens,
                            $title,
                            $message,
                            null,
                            'order',
                            $order->order_status,
                            $order->_id,
                            0,
                            null
                        );
                    }
                }
            }
            return response()->json([
                'status_code' => 200,
                'message' => 'Status changed successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to change status.'
            ], 500);
        }
    }

    /**
     * Update Order Status From Customer.
     */
    public function updateStatusCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            // 'order_status' => 'sometimes|in:accepted,preparing,dispatched,delivered,cancelled'
            'order_status' => 'required|in:cancelled'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $order = order::find($request->order_id);
            if (!$order) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Order not found'
                ], 404);
            }
            $order->order_status = $request->order_status;

            $order->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Status changed successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to change status.'
            ], 500);
        }
    }


    /**
     * Get all orders for admin.
     */
    public function getAllOrdersAdmin()
    {
        try {
            $orders = order::orderBy('created_at', 'desc')->get()
                ->each(function ($order) {
                    $user = User::find($order->user_id);
                    if (!$user) {
                        $order->user_name = null;
                        $order->user_phoneno = null;
                    } else {
                        $order->user_name = $user->name;
                        $order->user_phoneno = $user->phoneno;
                    }
                    if ($order->order_type === 'Dine In') {
                        $order->makeHidden(['longitude', 'latitude','house_no', 'area', 'options_to_reach', 'coupon_id']);
                    } else if ($order->order_type === 'Delivery') {
                        $address = (object)[
                            'longitude' => $order->longitude,
                            'latitude' => $order->latitude,
                            'house_no' => $order->house_no,
                            'area' => $order->area,
                            'options_to_reach' => $order->options_to_reach,
                        ];
                        $order->address = $address;
                        $order->makeHidden(['table_no', 'longitude', 'latitude', 'house_no', 'area', 'options_to_reach', 'coupon_id']);
                    } else if ($order->order_type === 'Pickup') {
                        $order->makeHidden(['longitude', 'latitude', 'house_no', 'area', 'options_to_reach', 'table_no', 'coupon_id']);
                    } else {
                        $order->makeHidden('coupon_id');
                    }
                    if ($order->driver_id) {
                        $driver = User::find($order->driver_id);
                        $order->delivery_person = $driver;
                    } else {
                        $order->delivery_person = null;
                    }
                    $order->makeHidden('driver_id');
                    $order->products = order_product::where('order_id', $order->_id)->get()->makeHidden('order_id');
                    $order->products = $order->products->map(function ($product) {
                        $customizations = order_customization::where('order_product_id', $product->_id)->get();
                        $product->customization = $customizations->isEmpty() ? null : $customizations;
                        return $product;
                    });
                });
            return response()->json([
                'status_code' => 200,
                'data' => $orders,
                'message' => 'Orders retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve orders.'
            ], 500);
        }
    }

    /**
     * Get Order History For Customer.
     */
    public function getOrderHistory(Request $request)
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
                    'message' => 'User not found'
                ], 404);
            }
            $orders = order::where('user_id', $user->_id)->orderBy('created_at', 'desc')->get()
                ->each(function ($order) {
                    // if ($order->order_type === 'Dine In') {
                    //     $order->makeHidden(['longitude', 'latitude','location', 'house_no', 'area', 'options_to_reach', 'coupon_id']);
                    // } else if ($order->order_type === 'Delivery') {
                    //     $order->makeHidden(['table_no', 'longitude', 'latitude','location', 'house_no', 'area', 'options_to_reach', 'coupon_id']);
                    // } else if ($order->order_type === 'Pickup') {
                    //     $order->makeHidsden(['longitude', 'latitude','location', 'house_no', 'area', 'options_to_reach', 'table_no', 'coupon_id']);
                    // } else {
                    //     $order->makeHidden('coupon_id');
                    // }
                    $order->makeHidden(['longitude', 'latitude','house_no', 'area', 'options_to_reach', 'coupon_id', 'table_no', 'user_id']);
                    if ($order->driver_id) {
                        $driver = User::find($order->driver_id);
                        $order->delivery_person = $driver;
                    } else {
                        $order->delivery_person = null;
                    }
                    $order->makeHidden('driver_id');
                    $order->products = order_product::where('order_id', $order->_id)->get()->makeHidden('order_id');
                    $order->products = $order->products->map(function ($product) {
                        $customizations = order_customization::where('order_product_id', $product->_id)->get();
                        $product->customization = $customizations->isEmpty() ? null : $customizations;
                        return $product;
                    });
                });
            return response()->json([
                'status_code' => 200,
                'data' => $orders,
                'message' => 'Orders retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve orders.'
            ], 500);
        }
    }

    /**
     * Reorder.
     */
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $order = order::find($request->order_id);
            if (!$order) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Order not found'
                ], 404);
            }
            $order_products = order_product::where('order_id', $order->_id)->get();
            $cart = cart::where('user_id', $order->user_id)->first();
            if (!$cart) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Cart not found'
                ], 404);
            }
            foreach ($order_products as $order_product) {
                if ($order_product->combo_id) {
                    $cart_product = cart_product::where('cart_id', $cart->_id)->where('combo_id', $order_product->combo_id)->first();
                    if ($cart_product) {
                        $cart_product->quantity = $cart_product->quantity + 1;
                    } else {
                        $cart_product = new cart_product();
                        $cart_product->cart_id = $cart->_id;
                        $cart_product->combo_id = $order_product->combo_id;
                    }
                    $cart_product->save();
                } else if ($order_product->product_id) {
                    if ($order_product->size_id) {
                        $cart_product = cart_product::where('cart_id', $cart->_id)->where('product_id', $order_product->product_id)->where('size', $order_product->size_id)->first();
                    } else {
                        $cart_product = cart_product::where('cart_id', $cart->_id)->where('product_id', $order_product->product_id)->first();
                    }
                    if ($cart_product) {
                        $cart_product->quantity = $cart_product->quantity + 1;
                    } else {
                        $cart_product = new cart_product();
                        $cart_product->cart_id = $cart->_id;
                        $cart_product->product_id = $order_product->product_id;
                        $cart_product->size = $order_product->size_id ? $order_product->size_id : null;
                    }
                    $cart_product->save();
                }
            }

            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Reorder Successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to reorder.'
            ], 500);
        }
    }

    /**
     * Send delivery request to delivery person for order.
     */
    public function sendDeliveryRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'driver_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
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
                    'message' => 'You can only hire driver when order type is Delivery'
                ], 200);
            }

            $driver = User::find($request->driver_id);
            if (!$driver) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Driver not found'
                ], 404);
            }

            $existingRequest = delivery_request::where('order_id', $request->order_id)->whereIn('status', ['accepted'])->first();
            if ($existingRequest) {
                return response([
                    'status_code' => 402,
                    'message' => 'You have already sent delivery request for this order'
                ], 200);
            }

            if ($driver->online === 0) {
                // if ($driver->available === 0 || $driver->online === 0) {
                return response()->json([
                    'status_code' => 401,
                    'message' => 'Driver is not available'
                ], 200);
            }

            $delivery_request = new delivery_request();
            $delivery_request->order_id = $request->order_id;
            $delivery_request->driver_id = $request->driver_id;
            $delivery_request->status = 'pending';
            $delivery_request->save();
            DB::commit();

            $fcm_tokens = user_fcm_token::whereNotNull('token')->where('driver_id', $request->driver_id)->pluck('token')->all();
            if (!empty($fcm_tokens)) {
                $validTokens = $this->validateTokens($fcm_tokens, 0, 0, 1);
                if (!empty($validTokens)) {
                    $title = 'New Delivery Assigned!';
                    $message = 'A new delivery order is ready. Please open the app to view the details and start delivering.';
                    $this->sendDeliveryNotification(
                        $fcm_tokens,
                        $title,
                        $message,
                        'new_delivery',
                        $order->_id,
                        $driver->_id,
                        0
                    );
                }
            }
            return response()->json([
                'status_code' => 200,
                'message' => 'Delivery request sent successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to send delivery request.'
            ], 500);
        }
    }

    /**
     * Cancel delivery request order.
     */
    public function cancelDeliveryRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'driver_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $order = order::find($request->order_id);
            if (!$order) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Order not found'
                ], 404);
            }

            $driver = User::find($request->driver_id);
            if (!$driver) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Driver not found'
                ], 404);
            }

            $accepted_request = delivery_request::where('order_id', $request->order_id)->where('driver_id', $driver->_id)->where('status', 'accepted')->first();
            if ($accepted_request) {
                return response([
                    'status_code' => 402,
                    'message' => 'You can not cancel accepted delivery request'
                ], 200);
            }

            $delivery_request = delivery_request::where('order_id', $order->_id)->where('driver_id', $driver->_id)->first();
            if (!$delivery_request) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'You have not sent request to this driver'
                ], 200);
            }
            delivery_request::where('order_id', $order->_id)->where('driver_id', $driver->_id)->update(['status' => 'cancelled']);
            DB::commit();

            $fcm_tokens = user_fcm_token::whereNotNull('token')->where('driver_id', $request->driver_id)->pluck('token')->all();
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
                        $driver->_id,
                        0
                    );
                }
            }
            return response()->json([
                'status_code' => 200,
                'message' => 'Delivery request cancelled successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to cancel delivery request.'
            ], 500);
        }
    }
}
