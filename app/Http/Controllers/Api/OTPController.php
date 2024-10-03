<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\user_otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Twilio\Rest\Client;

class OTPController extends Controller
{

    protected $twilio;

    public function __construct()
    {
        $this->twilio = new Client(config('services.twilio.sid'), config('services.twilio.token'));
        // $this->twilio = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
    }
    /**
     * send OTP.
     */
    public function sendOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phoneno' => 'required',
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
            user_otp::where('phoneno', $request->phoneno)->delete();
            $otp = rand(1000, 9999);
            $message = "Hi Your login OTP for Restaurant App is $otp";
            // $this->twilio->messages->create($request->phoneno, [
            //     // 'from' => env('TWILIO_PHONE_NUMBER'),
            //     'from' => config('services.twilio.from'),
            //     'body' => $message
            // ]);
            $length = 4;
            do {
                $code = substr(str_shuffle('123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, $length);
            } while (user_otp::where('_id', $code)->exists());
            $user_otp = new user_otp();
            $user_otp->_id = $code;
            $user_otp->otp = $otp;
            $user_otp->phoneno = $request->phoneno;
            $user_otp->expire_time = now()->addMinutes(5);
            $user_otp->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'otp' => $otp,
                'message' => 'OTP sent successfully.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to send OTP.'
            ], 500);
        }
    }
}
