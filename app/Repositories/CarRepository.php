<?php

namespace App\Repositories;

use App\Models\Car;
use App\Models\CarAdditionalDetail;
use App\Models\CarPriceDetail;
use App\Models\CarDiscountPriceDetail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CarRepository implements CarRepositoryInterface
{
    /**
     * Create a new car with additional details.
     *
     * @param array $data
     * @param UploadedFile|null $image
     * @return Car
     */
    public function create(array $data, ?UploadedFile $image = null): Car
    {
        DB::beginTransaction();

        try {
            // Handle car image upload
            $carImageUrl = null;
            $uploadedImagePath = null;

            if ($image) {
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $uploadedImagePath = $image->storeAs('cars', $imageName, 'public');
                $carImageUrl = $imageName; // Store only the image name
            }

            // Create the car
            $car = Car::create([
                'car_name' => $data['car_name'],
                'car_model' => $data['car_model'] ?? null,
                'car_image_url' => $carImageUrl,
                'car_category' => $data['car_category'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Create additional details if provided
            if (isset($data['additional_details']['no_of_seats'])) {
                CarAdditionalDetail::create([
                    'car_id' => $car->id,
                    'no_of_seats' => $data['additional_details']['no_of_seats'],
                ]);
            }

            // Create price details if provided
            if (isset($data['price_details']) && is_array($data['price_details'])) {
                foreach ($data['price_details'] as $priceDetail) {
                    CarPriceDetail::create([
                        'car_id' => $car->id,
                        'price_type' => $priceDetail['price_type'],
                        'min_hours' => $priceDetail['min_hours'] ?? 0,
                    ]);
                }
            }

            // Create discount price details if provided
            if (isset($data['discount_price_details']) && is_array($data['discount_price_details'])) {
                foreach ($data['discount_price_details'] as $discountPriceDetail) {
                    CarDiscountPriceDetail::create([
                        'car_id' => $car->id,
                        'price_type' => $discountPriceDetail['price_type'],
                        'price' => $discountPriceDetail['price'],
                    ]);
                }
            }

            DB::commit();

            // Load relationships for response
            $car->load(['category', 'additionalDetails', 'priceDetails', 'discountPriceDetails']);

            return $car;

        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded file if transaction failed
            if (isset($uploadedImagePath) && Storage::disk('public')->exists($uploadedImagePath)) {
                Storage::disk('public')->delete($uploadedImagePath);
            }

            throw $e;
        }
    }

    /**
     * Update an existing car with additional details.
     *
     * @param int $id
     * @param array $data
     * @param UploadedFile|null $image
     * @param string|null $imageName
     * @return Car
     */
    public function update(int $id, array $data, ?UploadedFile $image = null, ?string $imageName = null): Car
    {
        $car = Car::findOrFail($id);

        DB::beginTransaction();

        try {
            // Handle car image - can be file upload or string (image name)
            $carImageUrl = $car->car_image_url;
            $uploadedImagePath = null;
            $oldImagePath = null;

            if ($image) {
                // It's a file upload - delete old image and upload new one
                if ($car->car_image_url) {
                    $oldImagePath = 'cars/' . $car->car_image_url;
                    if (Storage::disk('public')->exists($oldImagePath)) {
                        Storage::disk('public')->delete($oldImagePath);
                    }
                }

                // Upload new image
                $newImageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $uploadedImagePath = $image->storeAs('cars', $newImageName, 'public');
                $carImageUrl = $newImageName; // Store only the image name
            } elseif ($imageName !== null) {
                // It's a string (image name) - just update the name
                $carImageUrl = $imageName;
            }

            // Build update data - only include fields that are provided
            $updateData = [];

            // Update car_name if provided
            if (array_key_exists('car_name', $data)) {
                $updateData['car_name'] = $data['car_name'];
            }

            // Update car_model if provided
            if (array_key_exists('car_model', $data)) {
                $updateData['car_model'] = $data['car_model'];
            }

            // Update car_category if provided
            if (array_key_exists('car_category', $data)) {
                $updateData['car_category'] = $data['car_category'];
            }

            // Update is_active if provided
            if (array_key_exists('is_active', $data)) {
                $updateData['is_active'] = $data['is_active'];
            }

            // Update image URL if it was changed (either file upload or string name)
            if ($image || $imageName !== null) {
                $updateData['car_image_url'] = $carImageUrl;
            } elseif (array_key_exists('car_image_url', $data)) {
                // If car_image_url is in data array (from controller), use it
                $updateData['car_image_url'] = $data['car_image_url'];
                unset($data['car_image_url']); // Remove from data to avoid duplicate
            }

            // Update the car
            if (!empty($updateData)) {
                $car->update($updateData);
                $car->refresh();
            }

            // Update or create additional details if provided
            if (isset($data['additional_details']['no_of_seats'])) {
                $additionalDetail = CarAdditionalDetail::where('car_id', $car->id)->first();
                if ($additionalDetail) {
                    $additionalDetail->update([
                        'no_of_seats' => $data['additional_details']['no_of_seats'],
                    ]);
                } else {
                    CarAdditionalDetail::create([
                        'car_id' => $car->id,
                        'no_of_seats' => $data['additional_details']['no_of_seats'],
                    ]);
                }
            }

            // Update price details if provided
            if (isset($data['price_details']) && is_array($data['price_details'])) {
                // Delete existing price details
                CarPriceDetail::where('car_id', $car->id)->delete();

                // Create new price details
                foreach ($data['price_details'] as $priceDetail) {
                    CarPriceDetail::create([
                        'car_id' => $car->id,
                        'price_type' => $priceDetail['price_type'],
                        'min_hours' => $priceDetail['min_hours'] ?? 0,
                    ]);
                }
            }

            // Update discount price details if provided
            if (isset($data['discount_price_details']) && is_array($data['discount_price_details'])) {
                // Delete existing discount price details
                CarDiscountPriceDetail::where('car_id', $car->id)->delete();

                // Create new discount price details
                foreach ($data['discount_price_details'] as $discountPriceDetail) {
                    CarDiscountPriceDetail::create([
                        'car_id' => $car->id,
                        'price_type' => $discountPriceDetail['price_type'],
                        'price' => $discountPriceDetail['price'],
                    ]);
                }
            }

            DB::commit();

            // Load relationships for response
            $car->load(['category', 'additionalDetails', 'priceDetails', 'discountPriceDetails']);

            return $car;

        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded file if transaction failed
            if (isset($uploadedImagePath) && Storage::disk('public')->exists($uploadedImagePath)) {
                Storage::disk('public')->delete($uploadedImagePath);
            }

            throw $e;
        }
    }
}

