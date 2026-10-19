<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['app' => 'Londrian API', 'status' => 'ok']));
