<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Filament::auth()->check()) {
        redirect()->intended(Filament::getUrl());
    }

    return redirect()->intended('/admin/login');
});
