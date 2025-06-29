<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Base controller for the application.
 *
 * This file was missing from the codebase which caused
 * "Class App\\Http\\Controllers\\Controller not found" errors when
 * other controllers attempted to extend it. The implementation below
 * reproduces Laravel's default base controller skeleton.
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
} 