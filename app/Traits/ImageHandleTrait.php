<?php

namespace App\Traits;

trait ImageHandleTrait
{
    public function decodeBase64Image($base64Image)
    {
        try {
                list($type, $data) = explode(';', $base64Image);
                list(, $data)      = explode(',', $data);
                $fileData = base64_decode($data);
                list(, $extension) = explode('/', $type);
                return [
                    'extension' => $extension,
                    'data' => $fileData,
                ];
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Image Upload Fail.'
            ], 500);
        }
    }
}
