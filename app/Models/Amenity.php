<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Amenity extends Model
{
    use HasFactory;

    protected $table = 'amenities';

    protected $fillable = [
        'name',
    ];

    /**
     * Get the cars that have this amenity.
     */
    public function cars()
    {
        return $this->belongsToMany(Car::class, 'car_amenities', 'amenity_id', 'car_id');
    }
}

