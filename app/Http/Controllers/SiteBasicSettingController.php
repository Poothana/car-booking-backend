<?php

namespace App\Http\Controllers;

use App\Models\SiteBasicSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SiteBasicSettingController extends Controller
{
    /**
     * Public endpoint: return settings as a key/value map.
     */
    public function basic(): JsonResponse
    {
        $rows = SiteBasicSetting::orderBy('id')->get(['key', 'value', 'type']);
        $map = [];

        foreach ($rows as $row) {
            $map[$row->key] = $row->value;
        }

        return response()->json([
            'success' => true,
            'data' => $map,
        ]);
    }

    public function index(): JsonResponse
    {
        $rows = SiteBasicSetting::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array|min:1',
            'settings.*.id' => 'required|integer|exists:site_basic_settings,id',
            'settings.*.value' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            foreach ($request->input('settings', []) as $item) {
                $row = SiteBasicSetting::find($item['id']);
                if (! $row) {
                    continue;
                }

                // Keep existing type/key; only update value.
                $row->value = is_array($item['value']) ? json_encode($item['value']) : $item['value'];
                $row->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => SiteBasicSetting::orderBy('id')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Settings update failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

