<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarAdditionalDetail extends Model
{
    use HasFactory;

    protected $table = 'car_additional_details';

    protected $fillable = [
        'car_id',
        'no_of_seats',
        'amenity',
    ];

    protected $casts = [
        'amenity' => 'array',
    ];

    protected $appends = ['amenity_names'];

    /**
     * Get the car that owns the additional detail.
     */
    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    /**
     * Get amenity names based on amenity IDs.
     *
     * @return array
     */
    public function getAmenityNamesAttribute(): array
    {
        // Get raw attribute value (before any casting)
        $amenityValue = $this->attributes['amenity'] ?? null;
        
        // Log for debugging
        \Log::info('Amenity raw value:', ['value' => $amenityValue, 'type' => gettype($amenityValue)]);
        
        // If null or empty, return empty array
        if (empty($amenityValue) || $amenityValue === null) {
            \Log::info('Amenity value is empty');
            return [];
        }
        
        $amenityIds = null;

        \Log::info("Raw amenityValue: " . json_encode($amenityValue));
        
        // Decode JSON string to array
        if (is_string($amenityValue)) {
            // First decode: Handle double-encoded JSON like "\"[\\2\\,\\9\\]\""
            $firstDecode = json_decode($amenityValue, true);
            if (is_string($firstDecode)) {
                $amenityValue = $firstDecode;
            }
            
            // Now we should have something like "[\\2\\,\\9\\]" or "[\2\,\9\]"
            // Remove outer quotes if present
            $cleanedValue = trim($amenityValue, '"\'');
            
            // Extract numbers from pattern like [\2\,\9\] or [\\2\\,\\9\\]
            // Match: [ followed by \digit or ,\digit, ending with ]
            if (preg_match('/^\[(.*?)\]$/', $cleanedValue, $matches)) {
                $content = $matches[1];
                // Extract all numbers (handles both \2 and \\2 formats)
                preg_match_all('/(\d+)/', $content, $numberMatches);
                if (!empty($numberMatches[1])) {
                    // Build proper JSON array: ["2","9"]
                    $amenityIds = array_map('intval', $numberMatches[1]);
                    \Log::info("Extracted amenity IDs from pattern: " . json_encode($amenityIds));
                } else {
                    // Try standard JSON decode
                    $amenityIds = json_decode($cleanedValue, true);
                }
            } else {
                // Try standard JSON decode
                $amenityIds = json_decode($cleanedValue, true);
            }
           
            // Check for JSON decode errors
            if (!is_array($amenityIds) && json_last_error() !== JSON_ERROR_NONE) {
                \Log::error('JSON decode error:', ['error' => json_last_error_msg(), 'original' => $amenityValue, 'cleaned' => $cleanedValue]);
                return [];
            }
        } elseif (is_array($amenityValue)) {
            // Already an array (from cast)
            $amenityIds = $amenityValue;
        } else {
            \Log::warning('Amenity value is not string or array:', ['type' => gettype($amenityValue)]);
            return [];
        }

        \Log::info("Decoded amenity IDs type: " . gettype($amenityIds) . ", value: " . json_encode($amenityIds));
        
        // Ensure it's an array
        if (!is_array($amenityIds) || empty($amenityIds)) {
            \Log::info('Amenity IDs is not array or empty:', ['ids' => $amenityIds]);
            return [];
        }

        \Log::info('Decoded amenity IDs:', ['ids' => $amenityIds]);

        // Convert all values to integers (handles string IDs like "2", "9", "4")
        $amenityIds = array_map(function($id) {
            // Handle string IDs by converting to int
            if (is_string($id)) {
                $id = trim($id, '"\'');
            }
            return (int) $id;
        }, $amenityIds);

        // Filter out any invalid values (0 or negative) and remove duplicates
        $amenityIds = array_values(array_unique(array_filter($amenityIds, function($id) {
            return $id > 0;
        })));

        \Log::info('Processed amenity IDs:', ['ids' => $amenityIds]);

        if (empty($amenityIds)) {
            return [];
        }

        // Fetch amenities from master table
        try {
            $amenities = Amenity::whereIn('id', $amenityIds)->orderBy('name')->get();
            \Log::info('Fetched amenities:', ['count' => $amenities->count(), 'amenities' => $amenities->toArray()]);
            
            if ($amenities->isEmpty()) {
                \Log::warning('No amenities found for IDs:', ['ids' => $amenityIds]);
                return [];
            }
            return $amenities->pluck('name')->toArray();
        } catch (\Exception $e) {
            // Log error for debugging
            \Log::error('Error fetching amenity names: ' . $e->getMessage(), [
                'amenity_ids' => $amenityIds,
                'raw_value' => $amenityValue,
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
}

