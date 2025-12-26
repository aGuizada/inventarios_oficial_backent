<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Ruta temporal 'login' para evitar errores en el middleware de autenticación
// Esta ruta no se usa realmente ya que la autenticación se maneja en la API
Route::get('/login', function () {
    return redirect('/');
})->name('login');
