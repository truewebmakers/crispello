<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\combo;
use App\Models\feedback;
use App\Models\order;
use App\Models\order_product;
use App\Models\product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    /**
     * Give Feedback.
     */
    public function addFeedback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'combo_id' => 'required_without:product_id',
            'product_id' => 'required_without:combo_id',
            'feedback' => 'required_without:rating',
            'rating' => 'required_without:feedback',

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
                    'message' => 'User not found'
                ], 404);
            }
            $combo = null;
            $product = null;
            $name = 'this product or combo';
            if ($request->has('combo_id')) {
                $combo = combo::find($request->combo_id);
                if (!$combo) {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'Combo not found'
                    ], 404);
                }
                $name = $combo->name;
            } else if ($request->has('product_id')) {
                $product = product::find($request->product_id);
                if (!$product) {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'Product not found'
                    ], 404);
                }
                $name = $product->name;
            }
            $orders = order::where('user_id', $user->_id)->pluck('_id');
            if ($orders->isEmpty()) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'User has not ordered any products or combos'
                ], 403);
            }
            $hasOrdered = order_product::whereIn('order_id', $orders)
                ->when($combo, function ($query) use ($combo) {
                    return $query->where('combo_id', $combo->_id);
                })
                ->when($product, function ($query) use ($product) {
                    return $query->where('product_id', $product->_id);
                })
                ->exists();

            if (!$hasOrdered) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'User has not ordered ' . $name
                ], 403);
            }

            $existingFeedback = $product ?
                feedback::where('user_id', $user->_id)->where('product_id', $product->_id)->first() :
                feedback::where('user_id', $user->_id)->where('combo_id', $combo->_id)->first();
            if ($existingFeedback) {
                return response()->json([
                    'status_code' => 400,
                    'message' => 'you have already given feedback on ' . $name
                ], 200);
            }
            $feedback = new feedback();
            $feedback->user_id = $user->_id;
            $feedback->product_id = $product ? $product->_id : null;
            $feedback->combo_id = $combo ? $combo->_id : null;
            $feedback->feedback = $request->feedback ? $request->feedback : null;
            $feedback->rating = $request->rating ? $request->rating : null;
            $feedback->reply_time = null;
            $feedback->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Feedback given successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to give feedback'
            ], 500);
        }
    }

    /**
     * Update Feedback.
     */
    public function updateFeedback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'feedback_id' => 'required',
            'feedback' => 'required_without:rating',
            'rating' => 'required_without:feedback',

        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $feedback = feedback::find($request->feedback_id);
            if (!$feedback) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Feedback not found'
                ], 404);
            }
            $feedback->feedback = $request->feedback ? $request->feedback : null;
            $feedback->rating = $request->rating ? $request->rating : null;
            $feedback->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Feedback updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update feedback'
            ], 500);
        }
    }

    /**
     * Give Reply.
     */
    public function giveReply(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'feedback_id' => 'required',
            'reply' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $feedback = feedback::find($request->feedback_id);
            if (!$feedback) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Feedback not found'
                ], 404);
            }
            $feedback->reply = $request->reply;
            $feedback->reply_time = now();
            $feedback->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Reply given successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to give reply'
            ], 500);
        }
    }

    /**
     * Remove Reply.
     */
    public function removeReply(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'feedback_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $feedback = feedback::find($request->feedback_id);
            if (!$feedback) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Feedback not found'
                ], 404);
            }
            $feedback->reply = null;
            $feedback->reply_time = null;
            $feedback->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Reply deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to delete reply'
            ], 500);
        }
    }

    /**
     * Delete Feedback.
     */
    public function deleteFeedback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'feedback_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $feedback = feedback::find($request->feedback_id);
            if (!$feedback) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Feedback not found'
                ], 404);
            }
            $feedback->delete();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Feedback deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to delete feedback'
            ], 500);
        }
    }

    /**
     * Get all Feedbacks.
     */
    public function getAllFeedbacks(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'is_combo' => 'required|boolean'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        try {
            $feedbacks = [];
            if ($request->is_combo) {
                $product = combo::find($request->id);
                if (!$product) {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'Combo not found'
                    ], 404);
                }
                $feedbacks = feedback::join('users', 'feedback.user_id', '=', 'users._id')
                    ->where('combo_id', $product->_id)
                    ->orderBy('created_at', 'desc')
                    ->select('feedback.*', 'users.name as user_name', 'users.image as user_image')
                    ->get();
            } else {
                $product = product::find($request->id);
                if (!$product) {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'Product not found'
                    ], 404);
                }
                $feedbacks = feedback::join('users', 'feedback.user_id', '=', 'users._id')
                    ->where('product_id', $product->_id)
                    ->orderBy('created_at', 'desc')
                    ->select('feedback.*', 'users.name as user_name', 'users.image as user_image')
                    ->get();
            }
            return response()->json([
                'status_code' => 200,
                'data' => $feedbacks,
                'message' => 'Feedbacks retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve feedbacks'
            ], 500);
        }
    }
}
