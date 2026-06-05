<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SitemapController extends Controller
{
  public function getSitemap(Request $request)
  {
      $response = Http::get('https://training-memo.s3.ap-northeast-1.amazonaws.com/sitemap.xml');

      if ($response->successful()) {
          return response($response->body(), 200, [
              'Content-Type' => 'application/xml',
              'Cache-Control' => 'max-age=3600',
          ]);
      } else {
          return response("Error fetching sitemap", 500);
      }
  }
}