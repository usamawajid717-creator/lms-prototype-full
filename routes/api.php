<?php

use App\Http\Controllers\JobApplicationController;
use Illuminate\Support\Facades\Route;

Route::post('/applications', [JobApplicationController::class, 'storeApi']);