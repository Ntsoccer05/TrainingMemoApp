<?php

use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

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

// `/tr-sitemap` ルートを最初に定義
Route::get('/tr-sitemap', [SitemapController::class, 'getSitemap']);

Route::get('/{any}', function () {
    return view('layout');
})->where('any', '.*');