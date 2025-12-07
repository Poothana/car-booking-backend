<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Add a new customer.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function add(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'address' => 'nullable|string',
            'adharno' => 'nullable|string|size:12|unique:customers,adharno',
            'pan_no' => 'nullable|string|size:10|unique:customers,pan_no',
            'phone_no' => 'required|string|max:15',
            'gender' => 'nullable|in:male,female,other',
            'is_prime_user' => 'nullable|boolean',
            'is_red_flag' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $customer = Customer::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'address' => $request->address,
                'adharno' => $request->adharno,
                'pan_no' => $request->pan_no,
                'phone_no' => $request->phone_no,
                'gender' => $request->gender,
                'is_prime_user' => $request->has('is_prime_user') ? $request->is_prime_user : false,
                'is_red_flag' => $request->has('is_red_flag') ? $request->is_red_flag : false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer added successfully',
                'data' => $customer,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

