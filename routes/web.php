<?php

use Illuminate\Support\Facades\Route;

// La SPA de React gestiona totes les rutes que no són de l'API.
Route::view('/{path?}', 'app')
    ->where('path', '^(?!api/|sanctum/|up$|build/|storage/).*$')
    ->name('spa');
