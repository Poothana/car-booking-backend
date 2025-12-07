<?php

namespace App\Http\Controllers;

use App\Models\CarBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    /**
     * Create a new car booking.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function add(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'car_id' => 'required|exists:cars,id',
            'customer_id' => 'required|exists:customers,id',
            'pickup_location' => 'nullable',
            'drop_location' => 'nullable',
            'journey_from_date' => 'required|date',
            'journey_end_date' => 'required|date|after:journey_from_date',
            'status' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $booking = CarBooking::create([
                'car_id' => $request->car_id,
                'customer_id' => $request->customer_id,
                'pickup_location' => $request->pickup_location,
                'drop_location' => $request->drop_location,
                'journey_from_date' => $request->journey_from_date,
                'journey_end_date' => $request->journey_end_date,
                'status' => $request->status ?? 'pending',
                'payment_amount' => $request->payment_amount ?? 0,
                'paid_amount' => $request->paid_amount ?? 0,
            ]);

            // Load relationships
            $booking->load(['car', 'customer', 'pickupLocation', 'dropLocation']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Car booking created successfully',
                'data' => $booking,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

