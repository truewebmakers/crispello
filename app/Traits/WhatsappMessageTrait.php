<?php

namespace App\Traits;

use Twilio\Rest\Client;

trait WhatsappMessageTrait
{
    protected $twilio;

    public function initializeTwilio()
    {
        $this->twilio = new Client(config('services.twilio.sid'), config('services.twilio.token'));
    }

    /**
     * Send Message on Whatsapp.
     *
     * @param string $to
     * @param string $message
     */
    public function sendWhatsappMessage($to, $messageBody)
    {
        try {
            $this->initializeTwilio();
            $this->twilio->messages->create("whatsapp:$to", [
                'from' => config('services.twilio.whatsappfrom'),
                'body' => $messageBody
            ]);
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    }
}
