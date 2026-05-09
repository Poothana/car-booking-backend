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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
     * Normalize multipart / JSON values so Laravel integer rules accept numeric strings like "5.0".
     */
    protected function normalizeCarFormInput(Request $request): void
    {
        if ($request->has('additional_details') && is_array($request->input('additional_details'))) {
            $details = $request->input('additional_details');
            if (array_key_exists('no_of_seats', $details) && is_numeric($details['no_of_seats'])) {
                $details['no_of_seats'] = (int) round((float) $details['no_of_seats']);
            }
            if (isset($details['amenities']) && is_array($details['amenities'])) {
                $details['amenities'] = array_values(array_filter(
                    array_map(static function ($id) {
                        if ($id === null || $id === '') {
                            return null;
                        }

                        return (int) $id;
                    }, $details['amenities']),
                    static fn ($id) => $id !== null && $id > 0
                ));
            }
            $request->merge(['additional_details' => $details]);
        }

        $priceDetails = $request->input('price_details');
        if (is_array($priceDetails)) {
            foreach ($priceDetails as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (isset($row['min_hours']) && is_numeric($row['min_hours'])) {
                    $priceDetails[$i]['min_hours'] = (int) round((float) $row['min_hours']);
                }
            }
            $request->merge(['price_details' => $priceDetails]);
        }
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
            'include_inactive' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Car::with(['category', 'priceDetails', 'discountPriceDetails', 'additionalDetails']);

        // By default show only active cars (public list). Admin can request all.
        $includeInactive = $request->boolean('include_inactive', false);
        if (! $includeInactive) {
            $query->where('is_active', true);
        }

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

            // Format price_details array with fuel_charge_per_liter
            $formattedPriceDetails = [];
            $fuelChargePerLiter = null;
            
            if ($car->priceDetails && $car->priceDetails->count() > 0) {
                foreach ($car->priceDetails as $index => $priceDetail) {
                    // Get fuel_charge value - access the raw attribute to ensure we get the actual value
                    $fuelCharge = $priceDetail->getAttribute('fuel_charge');
                    
                    // Handle null or empty values - default to 0
                    if ($fuelCharge === null || $fuelCharge === '') {
                        $fuelCharge = 0;
                    }
                    
                    // Convert to float/number format
                    $fuelCharge = is_numeric($fuelCharge) ? (float)$fuelCharge : 0;
                    
                    $formattedPriceDetails[] = [
                        'range_type' => $priceDetail->range_type,
                        'price_type' => $priceDetail->price_type,
                        'price' => $priceDetail->price,
                        'min_hours' => $priceDetail->min_hours ?? 0,
                        'fuel_charge' => $fuelCharge,
                        'fuel_charge_per_liter' => $fuelCharge,
                        'driver_betta' => (float) ($priceDetail->driver_betta ?? 0),
                    ];
                    
                    // Use the first price detail's fuel_charge as the root level value
                    // (assuming all price details have the same fuel_charge)
                    if ($fuelChargePerLiter === null) {
                        $fuelChargePerLiter = $fuelCharge;
                    }
                }
            }

            // Convert car to array and modify price_details
            $carData = $car->toArray();
            $carData['price_details'] = $formattedPriceDetails;
            
            // Add fuel_charge_per_liter at root level
            // Use the first price detail's value, or 0 if no price details exist
            if ($fuelChargePerLiter !== null) {
                $carData['fuel_charge_per_liter'] = $fuelChargePerLiter;
            } elseif (isset($formattedPriceDetails[0]['fuel_charge_per_liter'])) {
                $carData['fuel_charge_per_liter'] = $formattedPriceDetails[0]['fuel_charge_per_liter'];
            } else {
                $carData['fuel_charge_per_liter'] = 0;
            }

            return response()->json([
                'success' => true,
                'data' => $carData,
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
        $this->normalizeCarFormInput($request);
        Log::info('request data', $request->all());
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
            'price_details.*.range_type' => ['nullable', Rule::in(['below 250km', 'above 250km', 'below_250km', 'above_250km'])],
            'price_details.*.price_type' => 'required_with:price_details|in:day,week,trip,km',
            'price_details.*.min_hours' => 'nullable|integer|min:0',
            'price_details.*.price' => 'required_with:price_details|numeric|min:0',
            'price_details.*.fuel_charge' => 'nullable|numeric|min:0',
            'price_details.*.fuel_charge_per_liter' => 'nullable|numeric|min:0',
            'price_details.*.driver_betta' => 'nullable|numeric|min:0',
            'fuel_charge_per_liter' => 'nullable|numeric|min:0',
            'discount_price_details' => 'nullable|array',
            'discount_price_details.*.price_type' => 'required_with:discount_price_details|in:day,week,trip',
            'discount_price_details.*.price' => 'required_with:discount_price_details|numeric|min:0',
        ]);

        if ($validator->fails()) {
            Log::warning('Car add validation failed', ['errors' => $validator->errors()->toArray()]);

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
                'fuel_charge_per_liter',
            ]);

            // Handle price_details if it's a JSON string
            if (isset($data['price_details']) && is_string($data['price_details'])) {
                $data['price_details'] = json_decode($data['price_details'], true);
            }

            // Handle discount_price_details if it's a JSON string
            if (isset($data['discount_price_details']) && is_string($data['discount_price_details'])) {
                $data['discount_price_details'] = json_decode($data['discount_price_details'], true);
            }

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
        $this->normalizeCarFormInput($request);
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
            'price_details.*.range_type' => ['nullable', Rule::in(['below 250km', 'above 250km', 'below_250km', 'above_250km'])],
            'price_details.*.price_type' => 'required_with:price_details|in:day,week,trip,km',
            'price_details.*.min_hours' => 'nullable|integer|min:0',
            'price_details.*.price' => 'required_with:price_details|numeric|min:0',
            'price_details.*.fuel_charge' => 'nullable|numeric|min:0',
            'price_details.*.fuel_charge_per_liter' => 'nullable|numeric|min:0',
            'price_details.*.driver_betta' => 'nullable|numeric|min:0',
            'fuel_charge_per_liter' => 'nullable|numeric|min:0',
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
                Log::warning('Car edit image validation failed', ['errors' => $fileValidator->errors()->toArray()]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $fileValidator->errors(),
                ], 422);
            }
        }

        if ($validator->fails()) {
            Log::warning('Car edit validation failed', ['errors' => $validator->errors()->toArray()]);

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
                $priceDetails = $request->input('price_details');
                // Handle if it's a JSON string
                if (is_string($priceDetails)) {
                    $data['price_details'] = json_decode($priceDetails, true);
                } else {
                    $data['price_details'] = $priceDetails;
                }
            }
            
            if ($request->has('discount_price_details')) {
                $discountPriceDetails = $request->input('discount_price_details');
                // Handle if it's a JSON string
                if (is_string($discountPriceDetails)) {
                    $data['discount_price_details'] = json_decode($discountPriceDetails, true);
                } else {
                    $data['discount_price_details'] = $discountPriceDetails;
                }
            }
            
            if ($request->has('fuel_charge_per_liter')) {
                $data['fuel_charge_per_liter'] = $request->input('fuel_charge_per_liter');
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

    /**
     * Delete a car and its related records.
     */
    public function delete(int $id): JsonResponse
    {
        try {
            $car = Car::with(['additionalDetails', 'priceDetails', 'discountPriceDetails'])
                ->findOrFail($id);

            // Remove image file if stored in /storage/cars/{filename}
            $image = $car->car_image_url;
            if (is_string($image) && $image !== '') {
                $filename = basename($image);
                $path = "cars/{$filename}";
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            // Remove pivot (if it exists in this DB) + children
            if (Schema::hasTable('car_amenities')) {
                $car->amenities()->detach();
            }
            $car->priceDetails()->delete();
            $car->discountPriceDetails()->delete();
            $car->additionalDetails()?->delete();
            $car->delete();

            return response()->json([
                'success' => true,
                'message' => 'Car deleted successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Car not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Car delete failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete car',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

