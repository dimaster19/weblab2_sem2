<?php

use Illuminate\Support\Facades\Route;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Http\Controllers\TelegramController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/', function() {
   Telegram::setWebhook(['url' => 'https://fd01-109-254-41-76.ngrok-free.app/webhook']);

return view('welcome');
});
Route::post('/webhook', [TelegramController::class, 'load']);
Route::get('/webhook', function() {
    return view('welcome');
    });
