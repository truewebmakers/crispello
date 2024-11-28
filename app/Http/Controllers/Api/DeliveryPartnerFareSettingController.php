<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{DeliveryPartnerFareSetting, DeliveryPartnerFareLogs};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DeliveryPartnerFareSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = DeliveryPartnerFareSetting::get();
        return response()->json([
            'status_code' => 200,
            'data' => $data,
            'messsage' => 'Delivery setting retrieved successfully'
        ], 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $validator = Validator::make($request->all(), [
            'fare_per_km' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        try {
            DeliveryPartnerFareSetting::create([
                'fare_per_km' => $request->input('fare_per_km'),
                'added_by' => Auth::id()
            ]);
            return response()->json([
                'status_code' => 200,
                'messsage' => 'Fare setting added successfully'
            ], 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'status_code' => 500,
                'messsage' => 'There is some error'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        //
        $deliverySetting = DeliveryPartnerFareSetting::find($id);
        $validator = Validator::make($request->all(), [
            'fare_per_km' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        try {
            $deliverySetting->update([
                'fare_per_km' => $request->input('fare_per_km'),
                'added_by' => Auth::id()
            ]);
            return response()->json([
                'status_code' => 200,
                'messsage' => 'Fare setting update successfully'
            ], 200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'status_code' => 500,
                'messsage' => 'There is some error'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeliveryPartnerFareSetting $deliveryPartnerFareSetting)
    {
        //
    }

    public function DeliveryPartnerStorelogsget(Request $request, $partnerId)
    {
        $data =  DeliveryPartnerFareLogs::where(['delivery_partner_id' => $partnerId])
        ->where('status', '!=', 'pending')->get();
        $totalBalance = DeliveryPartnerFareLogs::where('delivery_partner_id', $partnerId)
        ->where('status', 'credit')
        ->sum('total_fare');
        return response()->json([
            'message' => 'Logs retrieved successfully.',
            'status_code' => 200,
            'data' => $data,
            'total_balance' => $totalBalance,
        ], 200);
    }




    public function DeliveryPartnerStorelogs(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'delivery_partner_id' => 'nullable|exists:users,_id',
            'order_id' => 'nullable|exists:orders,_id',
            'pickup_lat' => 'nullable|string|max:191',
            'pickup_long' => 'nullable|string|max:191',
            'destination_lat' => 'nullable|string|max:191',
            'destination_long' => 'nullable|string|max:191',
            'total_km' => 'nullable|numeric',
            'total_fare' => 'nullable|numeric',
            'status' => 'nullable|in:pending,credit,in-progress,withdraw',
        ]);

        // If validation fails, return with errors
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create a new delivery record
        $delivery = DeliveryPartnerFareLogs::create([
            'delivery_partner_id' => $request->delivery_partner_id,
            'order_id' => $request->order_id,
            'pickup_lat' => $request->pickup_lat,
            'pickup_long' => $request->pickup_long,
            'destination_lat' => $request->destination_lat,
            'destination_long' => $request->destination_long,
            'total_km' => $request->total_km,
            'total_fare' => $request->total_fare,
            'status' => $request->status,
        ]);

        // Return a success response
        return response()->json([
            'message' => 'Delivery created successfully.',
            'data' => $delivery,
            'status_code' => 200
        ], 200);
    }


    public function UpdateLogs(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:credit,in-progress,withdraw',
        ]);

        // If validation fails, return with errors
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create a new delivery record
        $delivery = DeliveryPartnerFareLogs::where('id', $request->id)->update([
            'status' => $request->status,
        ]);

        // Return a success response
        return response()->json([
            'message' => 'Delivery updated successfully.',
            'status_code' => 200,
            'data' => $delivery
        ], 200);
    }
}
