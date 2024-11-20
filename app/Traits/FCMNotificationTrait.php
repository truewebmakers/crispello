<?php

namespace App\Traits;

use App\Models\admin_fcm_token;
use App\Models\delivery_fcm_token;
use App\Models\user_fcm_token;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;


trait FCMNotificationTrait
{
    protected $messaging;

    public function __construct()
    {
        // Construct the full path to the service account JSON file
        $serviceAccount = config_path('firebase_credentials.json');
        // Initialize the Firebase Admin SDK
        $factory = (new Factory)->withServiceAccount($serviceAccount);
        $this->messaging = $factory->createMessaging();
    }

    /**
     * Send a notification to the given FCM tokens.
     *
     * @param array $tokens
     * @param string $title
     * @param string $body
     * @param string $type
     * @param string|null $order_status
     * @param string|null $order_id
     * @param string|null $image
     * @param string|null $notification_id
     * @param boolean $only_data
     * @return void
     */
    public function sendNotification($tokens, $title, $body, $image = '', $type, $order_status = null, $order_id = null, $only_data = 0,$notification_id=null)
    {
        try {
            if ($only_data === 0) {
                foreach ($tokens as $token) {
                    $data = [
                        'type' => $type,
                        'only_data' => $only_data
                    ];
                    if ($order_status !== null) {
                        $data['order_status'] = $order_status;
                    }
                    if ($order_id !== null) {
                        $data['order_id'] = $order_id;
                    }
                    if ($notification_id !== null) {
                        $data['notification_id'] = $notification_id;
                    }
                    if ($image !== null) {
                        $notification = Notification::fromArray([
                            'title' => $title,
                            'body' => $body,
                            'image' => $image,
                        ]);
                    } else {
                        $notification = Notification::create($title, $body);
                    }
                    $message = CloudMessage::withTarget('token', $token)
                        ->withNotification($notification)
                        ->withAndroidConfig(['priority' => 'high'])
                        ->withData($data);

                    // Send the message
                    $this->messaging->send($message);
                }
            } else {
                foreach ($tokens as $token) {
                    $data = [
                        'type' => $type,
                        'title' => $title,
                        'body' => $body,
                        'only_data' => $only_data
                    ];
                    if ($order_status !== null) {
                        $data['order_status'] = $order_status;
                    }
                    if ($order_id !== null) {
                        $data['order_id'] = $order_id;
                    }
                    $message = CloudMessage::withTarget('token', $token)
                        ->withAndroidConfig(['priority' => 'high'])
                        ->withData($data);

                    // Send the message
                    $this->messaging->send($message);
                }
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    }

    /**
     * Validate the given FCM tokens and update the database for invalid tokens.
     *
     * @param array $tokens
     * @param bool $admin
     * @param bool $customer
     * @return array
     */
    public function validateTokens($tokens, $admin, $customer, $delivery)
    {
        try {
            $result = $this->messaging->validateRegistrationTokens($tokens);
            $validTokens = $result['valid'] ?? [];
            $invalidTokens = $result['invalid'] ?? [];
            $unknownTokens = $result['unknown'] ?? [];
            $tokensToDelete = array_merge($invalidTokens, $unknownTokens);
            if (!empty($tokensToDelete)) {
                DB::beginTransaction();
                // if ($admin) {
                //     user_fcm_token::whereIn('token', $tokensToDelete)->update(['token' => null]);
                // }
                // if ($customer) {
                //     user_fcm_token::whereIn('token', $tokensToDelete)->update(['token' => null]);
                // }
                // if ($delivery) {
                    user_fcm_token::whereIn('token', $tokensToDelete)->update(['token' => null]);
                // }
                DB::commit();
            }
            return $validTokens;
        } catch (\Exception $e) {
            DB::rollBack();
            return [];
        }
    }

    /**
     * Send a delivery regarding notification to the given FCM tokens.
     *
     * @param array $tokens
     * @param string $title
     * @param string $body
     * @param string $type
     * @param string|null $order_id
     * @param string|null $driver_id
     * @param boolean $only_data
     * @return void
     */
    public function sendDeliveryNotification($tokens, $title, $body, $type, $order_id = null, $driver_id = null, $only_data = 0)
    {
        try {
            foreach ($tokens as $token) {
                $data = [
                    'type' => $type,
                    'only_data' => $only_data
                ];
                if ($order_id !== null) {
                    $data['order_id'] = $order_id;
                }
                if ($driver_id !== null) {
                    $data['driver_id'] = $driver_id;
                }

                $notification = Notification::create($title, $body);
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification($notification)
                    ->withData($data);

                $this->messaging->send($message);
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    }
}
