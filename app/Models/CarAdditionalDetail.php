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
    ];

    /**
     * Get the car that owns the additional detail.
     */
    public function car()
    {
        return $this->belongsTo(Car::class);
    }
}

