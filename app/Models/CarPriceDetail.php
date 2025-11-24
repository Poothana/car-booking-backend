<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarPriceDetail extends Model
{
    use HasFactory;

    protected $table = 'car_price_details';

    protected $fillable = [
        'car_id',
        'price_type',
        'min_hours',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    /**
     * Get the car that owns the price detail.
     */
    public function car()
    {
        return $this->belongsTo(Car::class);
    }
}

