<?php

namespace App\Http\Controllers;

use App\Models\EnquiryDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EnquiryController extends Controller
{
    protected array $allowedStatuses = [
        'Pending',
        'Processed',
        'Invalid',
        'Paid',
        'Payment Pending',
        'Completed',
    ];

    /**
     * List enquiries (admin).
     * Query params:
     * - order: asc|desc (default desc)
     */
    public function list(Request $request): JsonResponse
    {
        $order = strtolower((string) $request->query('order', 'desc'));
        $order = in_array($order, ['asc', 'desc'], true) ? $order : 'desc';

        $rows = EnquiryDetail::orderBy('id', $order)->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    /**
     * Show one enquiry (admin).
     */
    public function show(int $id): JsonResponse
    {
        try {
            $row = EnquiryDetail::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $row,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Enquiry not found',
            ], 404);
        }
    }

    /**
     * Update enquiry status (admin).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:' . implode(',', $this->allowedStatuses),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $row = EnquiryDetail::findOrFail($id);
            $row->status = $request->input('status');
            $row->save();

            return response()->json([
                'success' => true,
                'message' => 'Enquiry updated successfully',
                'data' => $row,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Enquiry not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update enquiry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a new enquiry and store it in enquiry_details.
     */
    public function add(Request $request): JsonResponse
    {
        // Normalize legacy frontend keys (keeps existing UI working)
        $input = $request->all();
        if (!isset($input['email_address']) && isset($input['email'])) $input['email_address'] = $input['email'];
        if (!isset($input['phone_number']) && isset($input['phone'])) $input['phone_number'] = $input['phone'];
        if (!isset($input['pick_location']) && isset($input['pickup_location'])) $input['pick_location'] = $input['pickup_location'];
        if (!isset($input['drop_location']) && isset($input['drop_location'])) $input['drop_location'] = $input['drop_location'];

        $validator = Validator::make($input, [
            'name' => 'required|string|max:255',
            'email_address' => 'nullable|email|max:255',
            'phone_number' => 'nullable|string|max:20',
            'alt_phone_number' => 'nullable|string|max:20',
            'message' => 'nullable|string',
            'pick_location' => 'nullable|string',
            'drop_location' => 'nullable|string',
            'address' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', $this->allowedStatuses),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $payload = $validator->validated();
            $payload['status'] = $payload['status'] ?? 'Pending';

            $enquiry = EnquiryDetail::create([
                'name' => $payload['name'],
                'email_address' => $payload['email_address'] ?? null,
                'phone_number' => $payload['phone_number'] ?? null,
                'alt_phone_number' => $payload['alt_phone_number'] ?? null,
                'message' => $payload['message'] ?? null,
                'pick_location' => $payload['pick_location'] ?? null,
                'drop_location' => $payload['drop_location'] ?? null,
                'address' => $payload['address'] ?? null,
                'status' => $payload['status'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Enquiry received successfully',
                'data' => $enquiry,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit enquiry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

