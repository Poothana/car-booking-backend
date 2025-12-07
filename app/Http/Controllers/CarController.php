<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\Car;
use App\Models\CarCategory;
use App\Models\PriceType;
use App\Repositories\CarRepositoryInterface;
use App\Services\PriceCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CarController extends Controller
{
    protected CarRepositoryInterface $carRepository;
    protected PriceCalculationService $priceCalculationService;

    public function __construct(CarRepositoryInterface $carRepository, PriceCalculationService $priceCalculationService)
    {
        $this->carRepository = $carRepository;
        $this->priceCalculationService = $priceCalculationService;
    }
    /**
     * Fetch all cars with their categories.
     * Optionally filter by availability based on journey dates.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'car_id' => 'nullable|exists:cars,id',
            'journey_start_date' => 'nullable|date',
            'journey_end_date' => 'nullable|date|after:journey_start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Car::with(['category', 'priceDetails', 'discountPriceDetails', 'additionalDetails'])
            ->where('is_active', true);

        // Filter by car_id if provided
        if ($request->has('car_id')) {
            $query->where('id', $request->car_id);
        }

        // Filter by availability if journey dates are provided
        if ($request->has('journey_start_date') && $request->has('journey_end_date')) {
            $journeyStartDate = $request->journey_start_date;
            $journeyEndDate = $request->journey_end_date;

            // Exclude cars that have overlapping bookings (excluding cancelled bookings)
            $query->whereDoesntHave('bookings', function ($q) use ($journeyStartDate, $journeyEndDate) {
                $q->where('status', '!=', 'cancelled')
                  ->where(function ($subQuery) use ($journeyStartDate, $journeyEndDate) {
                      // Check for overlap: booking starts before or on requested end date
                      // AND booking ends after or on requested start date
                      $subQuery->where('journey_from_date', '<=', $journeyEndDate)
                               ->where('journey_end_date', '>=', $journeyStartDate);
                  });
            });
        }

        $cars = $query->get();

        // Ensure amenity_names is calculated for each car's additional details
        // and add display prices
        $cars->each(function ($car) {
            if ($car->additionalDetails) {
                // Force accessor to be called by accessing the attribute
                $car->additionalDetails->amenity_names;
            }
            
            // Calculate and add display prices
            $car->display_price = $this->priceCalculationService->calculateDisplayPrices($car);
        });

        return response()->json([
            'success' => true,
            'data' => $cars,
        ]);
    }

    /**
     * Fetch car details by ID.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $car = Car::with(['category', 'additionalDetails', 'priceDetails', 'discountPriceDetails'])
                ->findOrFail($id);

            // Force accessor to be called by accessing the attribute
            if ($car->additionalDetails) {
                $car->additionalDetails->amenity_names;
            }

            return response()->json([
                'success' => true,
                'data' => $car,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Car not found',
            ], 404);
        }
    }

    /**
     * Fetch all car categories.
     *
     * @return JsonResponse
     */
    public function category(): JsonResponse
    {
        $categories = CarCategory::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Fetch all price types.
     *
     * @return JsonResponse
     */
    public function priceType(): JsonResponse
    {
        $priceTypes = PriceType::orderBy('type_name')->get();

        return response()->json([
            'success' => true,
            'data' => $priceTypes,
        ]);
    }

    /**
     * Fetch all amenities.
     *
     * @return JsonResponse
     */
    public function amenities(): JsonResponse
    {
        $amenities = Amenity::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $amenities,
        ]);
    }

    /**
     * Add a new car with additional details.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function add(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'car_name' => 'required|string|max:100',
            'car_model' => 'nullable|string|max:100',
            'car_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            'car_category' => 'nullable|exists:car_categories,id',
            'is_active' => 'nullable|boolean',
            'additional_details' => 'nullable|array',
            'additional_details.no_of_seats' => 'required_with:additional_details|integer|min:1',
            'additional_details.amenities' => 'nullable|array',
            'additional_details.amenities.*' => 'nullable|integer|exists:amenities,id',
            'price_details' => 'nullable|array',
            'price_details.*.price_type' => 'required_with:price_details|in:day,week,trip',
            'price_details.*.min_hours' => 'nullable|integer|min:0',
            'price_details.*.price' => 'required_with:price_details|numeric|min:0',
            'discount_price_details' => 'nullable|array',
            'discount_price_details.*.price_type' => 'required_with:discount_price_details|in:day,week,trip',
            'discount_price_details.*.price' => 'required_with:discount_price_details|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $request->only([
                'car_name',
                'car_model',
                'car_category',
                'is_active',
                'additional_details',
                'price_details',
                'discount_price_details',
            ]);

            $image = $request->hasFile('car_image') ? $request->file('car_image') : null;

            $car = $this->carRepository->create($data, $image);

            return response()->json([
                'success' => true,
                'message' => 'Car added successfully',
                'data' => $car,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add car',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Edit an existing car with additional details.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function edit(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'car_name' => 'nullable|string|max:100',
            'car_model' => 'nullable|string|max:100',
            'car_image' => 'nullable', // Can be either file upload or string (image name)
            'car_category' => 'nullable|exists:car_categories,id',
            'is_active' => 'nullable|boolean',
            'additional_details' => 'nullable|array',
            'additional_details.no_of_seats' => 'required_with:additional_details|integer|min:1',
            'additional_details.amenities' => 'nullable|array',
            'additional_details.amenities.*' => 'nullable|integer|exists:amenities,id',
            'price_details' => 'nullable|array',
            'price_details.*.price_type' => 'required_with:price_details|in:day,week,trip',
            'price_details.*.min_hours' => 'nullable|integer|min:0',
            'price_details.*.price' => 'required_with:price_details|numeric|min:0',
            'discount_price_details' => 'nullable|array',
            'discount_price_details.*.price_type' => 'required_with:discount_price_details|in:day,week,trip',
            'discount_price_details.*.price' => 'required_with:discount_price_details|numeric|min:0',
        ]);
        
        // Additional validation for car_image if it's a file
        if ($request->hasFile('car_image')) {
            $fileValidator = Validator::make($request->all(), [
                'car_image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            ]);
            
            if ($fileValidator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $fileValidator->errors(),
                ], 422);
            }
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            
            // For PUT requests with form-data, Laravel may not parse the body correctly
            // We need to manually parse or use input() method
            $data = [];
            
            // Try to get data using input() which works with form-data
            if ($request->input('car_name') !== null) {
                $data['car_name'] = $request->input('car_name');
            }
            
            if ($request->input('car_model') !== null) {
                $data['car_model'] = $request->input('car_model');
            }
            
            if ($request->input('car_category') !== null) {
                $data['car_category'] = $request->input('car_category');
            }
            
            if ($request->input('is_active') !== null) {
                $data['is_active'] = $request->input('is_active');
            }
            
            if ($request->has('additional_details')) {
                $data['additional_details'] = $request->input('additional_details');
            }
            
            if ($request->has('price_details')) {
                $data['price_details'] = $request->input('price_details');
            }
            
            if ($request->has('discount_price_details')) {
                $data['discount_price_details'] = $request->input('discount_price_details');
            }
            
            // Handle car_image - can be either file upload or string (image name)
            $image = null;
            $imageName = null;
            
            if ($request->hasFile('car_image')) {
                // It's a file upload
                $image = $request->file('car_image');
            } elseif ($request->has('car_image') && $request->input('car_image') !== null) {
                // It's a string (image name)
                $imageName = $request->input('car_image');
                $data['car_image_url'] = $imageName;
            }

            $car = $this->carRepository->update($id, $data, $image, $imageName);

            return response()->json([
                'success' => true,
                'message' => 'Car updated successfully',
                'data' => $car,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Car not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update car',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

