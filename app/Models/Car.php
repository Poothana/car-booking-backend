<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_name',
        'car_model',
        'car_image_url',
        'car_category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the category that owns the car.
     */
    public function category()
    {
        return $this->belongsTo(CarCategory::class, 'car_category');
    }

    /**
     * Get the additional details for the car.
     */
    public function additionalDetails()
    {
        return $this->hasOne(CarAdditionalDetail::class);
    }

    /**
     * Get the price details for the car.
     */
    public function priceDetails()
    {
        return $this->hasMany(CarPriceDetail::class);
    }

    /**
     * Get the discount price details for the car.
     */
    public function discountPriceDetails()
    {
        return $this->hasMany(CarDiscountPriceDetail::class);
    }
}

