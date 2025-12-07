<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarBooking extends Model
{
    use HasFactory;

    protected $table = 'car_bookings';

    protected $fillable = [
        'car_id',
        'customer_id',
        'pickup_location',
        'drop_location',
        'journey_from_date',
        'journey_end_date',
        'status',
        'payment_amount',
        'paid_amount',
    ];

    protected $casts = [
        'journey_from_date' => 'datetime',
        'journey_end_date' => 'datetime',
        'payment_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    /**
     * Get the car for the booking.
     */
    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    /**
     * Get the customer for the booking.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the pickup location.
     */
    public function pickupLocation()
    {
        return $this->belongsTo(Location::class, 'pickup_location');
    }

    /**
     * Get the drop location.
     */
    public function dropLocation()
    {
        return $this->belongsTo(Location::class, 'drop_location');
    }
}

