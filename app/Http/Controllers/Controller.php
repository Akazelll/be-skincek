<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use ApiResponse;

    protected const ALLOWED_PER_PAGE = [5, 10, 20, 50];

    protected const DEFAULT_PER_PAGE = 5;

    protected function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page');

        return in_array($perPage, self::ALLOWED_PER_PAGE, true)
            ? $perPage
            : self::DEFAULT_PER_PAGE;
    }
}
