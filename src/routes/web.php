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

// SPA本体はS3+CloudFrontから直接配信しており(deploy-frontend.yml)、
// このLaravel(Lambda)側はAPIのみを提供する。webルートはこのサイトマップのみ。
Route::get('/tr-sitemap', [SitemapController::class, 'getSitemap']);