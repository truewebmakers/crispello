<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\notification;
use App\Models\User;
use App\Models\user_fcm_token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Traits\FCMNotificationTrait;
use App\Traits\ImageHandleTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class NotificationController extends Controller
{
    use FCMNotificationTrait, ImageHandleTrait;

    /**
     * Get all notifications of a specific user
     */
    public function getNotifications(Request $request)
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
            $notifications = notification::where('user_id', $user->_id)->get();
            return response()->json([
                'status_code' => 200,
                'data' => $notifications,
                'message' => 'Notifications retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve notifications'
            ], 500);
        }
    }

    /**
     *  Mark a notification as read
     */
    public function readNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'notification_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $notification = notification::find($request->notification_id);
            if (!$notification) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Notification not found'
                ], 404);
            }
            $notification->read = 1;
            $notification->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Notification read successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to read notification'
            ], 500);
        }
    }

    /**
     *  Delete a notification
     */
    public function deleteNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'notification_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $notification = notification::find($request->notification_id);
            if (!$notification) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Notification not found'
                ], 404);
            }
            $notification->delete();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Notification deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to delete notification'
            ], 500);
        }
    }

    /**
     * Broadcast Notification
     */
    public function broadcastNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'users' => 'array',
            'title' => 'required',
            'body' => 'required_without:image',
            'image' => 'required_without:body',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $title = $request->title;
            $body = $request->filled('body') ? $request->body : null;
            $image = null;
            if ($request->filled('image')) {
                $convertedImage = $this->decodeBase64Image($request->image);
                $now = Carbon::now();
                $imageName = 'notification_' . $now->timestamp . '.' . $convertedImage['extension'];
                $imagePath = 'public/notification/' . $imageName;
                Storage::put($imagePath, $convertedImage['data']);
                $image = 'storage/app/public/notification/' . $imageName;
            }

            if (!empty($request->users)) {
                $fcm_tokens = user_fcm_token::whereIn('user_id', $request->users)->pluck('token')->toArray();
            } else {
                $fcm_tokens = user_fcm_token::all()->pluck('token')->toArray();
            }
            $validTokens = $this->validateTokens($fcm_tokens, 0, 1,0);
            $distinctUserIds = user_fcm_token::whereIn('token', $validTokens)->distinct()->pluck('user_id');
            foreach ($distinctUserIds as $user) {
                $newNotification = new notification();
                $newNotification->user_id = $user;
                $newNotification->title = $title;
                $newNotification->body = $body;
                $newNotification->image = $image;
                $newNotification->save();
            }
            DB::commit();
            $this->sendNotification(
                $validTokens,
                $title,
                $body,
                $image ? 'https://www.certifit.in/crispello/' . $image : null,
                null,
                null,
                null,
                0,
                null
            );
            return response()->json([
                'status_code' => 200,
                'message' => 'Notification broadcast successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to broadcast notification'
            ], 500);
        }
    }
}
