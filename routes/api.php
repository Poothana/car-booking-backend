<?php

use App\Http\Controllers\CarController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\SiteBasicSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/cars/list', [CarController::class, 'list']);

Route::post('/admin/login', [AdminAuthController::class, 'login']);

Route::post('/admin/car/add', [CarController::class, 'add']);
Route::get('/admin/car/category', [CarController::class, 'category']);
Route::get('/admin/car/{id}', [CarController::class, 'show']);
Route::match(['put', 'post'], '/admin/car/update/{id}', [CarController::class, 'edit']);
Route::delete('/admin/car/delete/{id}', [CarController::class, 'delete']);

Route::get('/price-type', [CarController::class, 'priceType']);
Route::get('/amenities', [CarController::class, 'amenities']);

Route::post('/customer/add', [CustomerController::class, 'add']);

Route::post('/booking/add', [BookingController::class, 'add']);

Route::get('/admin/enquiry/list', [EnquiryController::class, 'list']);
Route::get('/admin/enquiry/{id}', [EnquiryController::class, 'show']);
Route::post('/admin/enquiry/update/{id}', [EnquiryController::class, 'update']);
Route::post('/enquiry/add', [EnquiryController::class, 'add']);

// Admin settings
Route::get('/admin/settings', [SiteBasicSettingController::class, 'index']);
Route::post('/admin/settings/update', [SiteBasicSettingController::class, 'update']);

// Public settings
Route::get('/settings/basic', [SiteBasicSettingController::class, 'basic']);

