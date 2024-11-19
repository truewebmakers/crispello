<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{CustomerWallet, ReferralCampaign,ReferralCode, ReferralLog};
use Illuminate\Support\Facades\Auth;

class ReferralCampaignController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'loyalty_points' => 'required|integer',
            'currency' => 'required',
            'points_equal_to' => 'required|numeric',
            'condition_install_app' => 'required|in:yes,no',
            'condition_make_purchase' => 'required|in:yes,no',
            'minimum_purchase' => 'nullable|integer',
            'status' => 'required|in:active,inactive',

        ]);

        $referalCode = strtoupper(substr(uniqid(), -6));
        $campaign = ReferralCampaign::create([
            'title' => $request->input('title'),
            'loyalty_points' => $request->input('loyalty_points'),
            'currency' => $request->input('currency'),
            'points_equal_to' => $request->input('points_equal_to'),
            'condition_install_app' => $request->input('condition_install_app'),
            'condition_make_purchase' => $request->input('condition_make_purchase'),
            'minimum_purchase' => $request->input('minimum_purchase'),
            'status' => $request->input('status'),
            'added_by' => Auth::id(),  // Assign the logged-in user's ID
            'code' =>  $referalCode

        ]);

        return response()->json([
            'status_code' => 200,
            'message' => 'Referral Code added successfully'
        ], 200);
    }


    public function craeteUserCode(Request $request){
        $request->validate([
            'campaign_id' => 'required'
        ]);
        $referalCode = strtoupper(substr(uniqid(), -6));
        $isExist = ReferralCode::where('user_id',Auth::id())->get()->first();
        if(empty( $isExist)){
            ReferralCode::create([
                'referral_campaign_id' => $request->campaign_id,
                'user_id' => Auth::id(),
                'code' =>  $referalCode
            ]);
        }


        return response()->json([
            'status_code' => 200,
             'code' =>  $referalCode,
            'message' => 'Referral Code added successfully'
        ], 200);
    }

    public function update(Request $request,$id)
    {
        $request->validate([
            'title' => 'required|string',
            'loyalty_points' => 'required|integer',
            'currency' => 'required',
            'points_equal_to' => 'required|numeric',
            'condition_install_app' => 'required|in:yes,no',
            'condition_make_purchase' => 'required|in:yes,no',
            'minimum_purchase' => 'nullable|integer',
            'status' => 'required|in:active,inactive',

        ]);


         ReferralCampaign::where(['id' => $id])->update([
            'title' => $request->input('title'),
            'loyalty_points' => $request->input('loyalty_points'),
            'currency' => $request->input('currency'),
            'points_equal_to' => $request->input('points_equal_to'),
            'condition_install_app' => $request->input('condition_install_app'),
            'condition_make_purchase' => $request->input('condition_make_purchase'),
            'minimum_purchase' => $request->input('minimum_purchase'),
            'status' => $request->input('status'),
            'added_by' => Auth::id(),
        ]);

        return response()->json([
            'status_code' => 200,
            'message' => 'Referral Code update successfully'
        ], 200);
    }

    public function index()
    {
        $campaigns = ReferralCampaign::first();

        if(empty($campaigns)){
            return response()->json([
                'status_code' => 404,
                'message' => 'Not Found',
            ], 404);
        }
        return response()->json([
            'status_code' => 200,
            'message' => 'Referral Code fetched successfully',
            'data' => $campaigns
        ], 200);
    }

    public function delete($id)
    {
        ReferralCampaign::find($id)->delete();
        ReferralCode::where(['referral_campaign_id' => $id])->delete();
        return response()->json([
            'status_code' => 200,
            'message' => 'Referral Code deleted successfully',
        ], 200);
    }


    // Customer Apis

    public function fetchDataWithCode(Request $request)
    {
        $code = $request->input('code');
        $campaigns =  ReferralCode::with('ReferralCampaign')->where(['code' =>$code])->first();
        if(empty($campaigns)){
            return response()->json([
                'status_code' => 404,
                'message' => 'Not Found',
            ], 404);
        }

        return response()->json([
            'status_code' => 200,
            'message' => 'Referral Code fetched successfully',
            'data' =>  $campaigns
        ], 200);
    }


    public function fetchDataWithOutCode(Request $request)
    {

        $campaigns = ReferralCampaign::first();
        if(empty($campaigns)){
            return response()->json([
                'status_code' => 404,
                'message' => 'Not Found',
            ], 404);
        }

        return response()->json([
            'status_code' => 200,
            'message' => 'Referral Code fetched successfully',
            'data' =>  $campaigns
        ], 200);
    }

    public function referralLog(Request $request)
    {
        $request->validate([
            'referral_code_id ' => 'required|string',
            'referrer_user_id' => 'required|integer',
            'referred_user_id' => 'required',
            'points' => 'required|numeric',
            'status' => 'required|in:credit,inactive',

        ]);


        $campaign = ReferralLog::create([
            'referral_code_id' => $request->input('referral_code_id'),
            'referrer_user_id' => $request->input('referrer_user_id'),
            'referred_user_id' => $request->input('referred_user_id'),
            'points' => $request->input('points'),
            'status' => $request->input('status'),
        ]);

        return response()->json([
            'status_code' => 200,
            'message' => 'Referral Log added successfully'
        ], 200);
    }

    public function CustomerWallet(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'loyalty_points' => 'required|numeric',

        ]);

        $campaign = CustomerWallet::create([
            'user_id' => $request->input('user_id'),
            'loyalty_points' => $request->input('loyalty_points'),
        ]);

        return response()->json([
            'status_code' => 200,
            'message' => 'Referral Log added successfully'
        ], 200);
    }

    public function CustomerWalletFetch($id)
    {
        $walletData = CustomerWallet::where([
            'user_id' => $id,
        ])->get();

        if($walletData->isEmpty()){
            return response()->json([
                'status_code' => 404,
                'message' => 'Not Found',
            ], 404);
        }

        return response()->json([
            'status_code' => 200,
            'message' => 'Customer wallet fetch successfully',
            'data' => $walletData
        ], 200);
    }

}
