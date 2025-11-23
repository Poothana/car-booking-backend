<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarCategory extends Model
{
    use HasFactory;

    protected $table = 'car_categories';

    protected $fillable = [
        'name',
    ];

    /**
     * Get the cars for the category.
     */
    public function cars()
    {
        return $this->hasMany(Car::class, 'car_category');
    }
}

