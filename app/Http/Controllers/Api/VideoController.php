<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\product_category;
use App\Models\video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Traits\ImageHandleTrait;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    use ImageHandleTrait;
    /**
     * Add Video.
     */
    public function addVideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes',
            'video' => 'required|file',
            'category_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $category = product_category::find($request->category_id);
            if (!$category) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Product category not found'
                ], 404);
            }
            $newVideo = new video();
            $newVideo->title = $request->title;
            $newVideo->category_id = $category->_id;
            $newVideo->save();
            if ($request->hasFile('video')) {
                $video = $request->file('video');
                $videoName =  'video_' . $newVideo->_id . '.' . $video->getClientOriginalExtension();
                $videoPath = $video->storeAs('public/video', $videoName);
                $newVideo->video = 'storage/app/public/video/' . $videoName;
            } else {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Video not found'
                ], 404);
            }
            // $video = $this->decodeBase64Image($request->video);
            // // Handle file upload
            // $videoName = 'video_' . $video->_id . '.' . $video['extension'];
            // $videoPath = 'public/video/' . $videoName;
            // Storage::put($videoPath, $video['data']);
            // $video->video = 'storage/app/public/video/' . $videoName;
            $newVideo->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Video added successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($videoPath) && Storage::exists($videoPath)) {
                Storage::delete($videoPath);
            }
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to add video'
            ], 500);
        }
    }

    /**
     * Update Video.
     */
    public function updateVideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required',
            'title' => 'sometimes',
            'video' => 'sometimes|file',
            'category_id' => 'sometimes'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $video = video::find($request->video_id);
            if (!$video) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Video not found'
                ], 404);
            }
            if ($request->has('category_id')) {
                $category = product_category::find($request->category_id);
                if (!$category) {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'Product category not found'
                    ], 404);
                }
                $video->category_id = $category->_id;
            }
            if ($request->has('title')) {
                $video->title = $request->title;
            }
            $oldVideo = parse_url($video->video, PHP_URL_PATH);
            if ($request->has('video')) {
                if ($request->hasFile('video')) {
                    $file = $request->file('video');
                    $videoName =  'video_' . $video->_id . '.' . $file->getClientOriginalExtension();
                    $videoPath = 'public/video/' . $videoName;
                    $file->storeAs('public/video', $videoName);

                    $path = str_replace('storage/app/', '', $oldVideo);
                    if ($path !== $videoPath) {
                        if ($oldVideo && Storage::exists($path)) {
                            Storage::delete($path);
                        }
                    }
                    $video->video = 'storage/app/public/video/' . $videoName . '?timestamp=' . time();
                } else {
                    return response()->json([
                        'status_code' => 404,
                        'message' => 'Video not found'
                    ], 404);
                }
            }
            $video->save();
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Video updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to update video'
            ], 500);
        }
    }

    /**
     * Delete Video.
     */
    public function deleteVideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'message' => $validator->messages()
            ], 400);
        }
        DB::beginTransaction();
        try {
            $video = video::find($request->video_id);
            if (!$video) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Video not found'
                ], 404);
            }

            $oldVideo =  parse_url($video->video, PHP_URL_PATH);
            $video->delete();
            $path = str_replace('storage/app/', '', $oldVideo);
            if ($oldVideo && Storage::exists($path)) {
                Storage::delete($path);
            }
            DB::commit();
            return response()->json([
                'status_code' => 200,
                'message' => 'Video deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to delete video'
            ], 500);
        }
    }

    /**
     * Get All Videos.
     */
    public function getAllVideos()
    {
        try {
            $videos = video::all();

            return response()->json([
                'status_code' => 200,
                'data' => $videos,
                'message' => 'Videos retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to retrieve videos'
            ], 500);
        }
    }
}
