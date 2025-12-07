<?php

use App\Http\Controllers\CarController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

Route::get('/cars/list', [CarController::class, 'list']);

Route::post('/admin/car/add', [CarController::class, 'add']);
Route::get('/admin/car/category', [CarController::class, 'category']);
Route::get('/admin/car/{id}', [CarController::class, 'show']);
Route::match(['put', 'post'], '/admin/car/update/{id}', [CarController::class, 'edit']);

Route::get('/price-type', [CarController::class, 'priceType']);
Route::get('/amenities', [CarController::class, 'amenities']);

Route::post('/customer/add', [CustomerController::class, 'add']);

Route::post('/booking/add', [BookingController::class, 'add']);

