<?php

namespace App\Services;

use App\Models\Car;

class PriceCalculationService
{
    /**
     * Calculate display prices for a car based on priority.
     * Priority: 1. Discount Price, 2. Regular Price
     *
     * @param Car $car
     * @return array
     */
    public function calculateDisplayPrices(Car $car): array
    {
        // Initialize all possible price types
        $displayPrices = [
            'day' => null,
            'week' => null,
            'km' => null,
            'trip' => null,
            'hour' => null,
            'month' => null,
        ];

        // Load price details if not already loaded
        if (!$car->relationLoaded('discountPriceDetails')) {
            $car->load('discountPriceDetails');
        }
        if (!$car->relationLoaded('priceDetails')) {
            $car->load('priceDetails');
        }

        
        // Get discount prices (Priority 1) - these take precedence
        $discountPrices = [];
        foreach ($car->discountPriceDetails as $discountPrice) {
            $priceType = strtolower($discountPrice->price_type);
            
            $discountPrices[$priceType] = [
                'price' => (float) $discountPrice->price,
                'type' => $discountPrice->price_type,
            ];
            
        }

        // Get all regular prices for regular_price_amount calculation
        $allRegularPrices = [];
        foreach ($car->priceDetails as $priceDetail) {
            $priceType = strtolower($priceDetail->price_type);
            $allRegularPrices[$priceType] = [
                'price' => (float) $priceDetail->price,
                'min_hours' => $priceDetail->min_hours ?? 0,
                'type' => $priceType,
            ];
        }

        // Get regular prices (Priority 2) - only if discount price doesn't exist (for display)
        $regularPrices = [];
        foreach ($car->priceDetails as $priceDetail) {
            $priceType = strtolower($priceDetail->price_type);
            // Only add if discount price doesn't exist for this type
            if (!isset($discountPrices[$priceType]) && isset($displayPrices[$priceType])) {
                $regularPrices[$priceType] = [
                    'price' => (float) $priceDetail->price,
                    'min_hours' => $priceDetail->min_hours ?? 0,
                    'type' => $priceType,
                ];
            }
        }

        // Merge prices with discount taking priority
        $allPrices = array_merge($regularPrices, $discountPrices);

        // Map to display prices (only set if price exists)
        foreach ($allPrices as $priceType => $priceData) {
            if (isset($displayPrices[$priceType])) {
                $displayPrices[$priceType] = $priceData;
            }
        }

        // Calculate discount_price_amount based on priority
        $discountPriceAmount = $this->getPriceAmountByPriority($discountPrices);

        // Calculate regular_price_amount based on priority (from all regular prices)
        $regularPriceAmount = $this->getPriceAmountByPriority($allRegularPrices);

        // Add discount_price_amount and regular_price_amount to display prices
        $displayPrices['discount_price_amount'] = $discountPriceAmount;
        $displayPrices['regular_price_amount'] = $regularPriceAmount;

        // Return all prices (including nulls for types that don't exist)
        // Frontend can filter out nulls if needed
        return $displayPrices;
    }

    /**
     * Get price amount based on priority.
     * Priority: month > week > trip > day > hour > km
     *
     * @param array $prices
     * @return array|null
     */
    private function getPriceAmountByPriority(array $prices): ?array
    {
        // Priority order
        $priorityOrder = ['month', 'week', 'trip', 'day', 'hour', 'km'];

        // Find first available price based on priority
        foreach ($priorityOrder as $priceType) {
            if (isset($prices[$priceType]) && $prices[$priceType]['price'] > 0) {
                return $prices[$priceType];
            }
        }

        // No price found
        return null;
    }

    /**
     * Calculate display prices for multiple cars.
     *
     * @param \Illuminate\Database\Eloquent\Collection $cars
     * @return array
     */
    public function calculateDisplayPricesForCars($cars): array
    {
        $result = [];
        foreach ($cars as $car) {
            $result[$car->id] = $this->calculateDisplayPrices($car);
        }
        return $result;
    }
}

