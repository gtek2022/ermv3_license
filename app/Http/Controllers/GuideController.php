<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the static licensing guide HTML behind the auth wall.
 *
 * The file lives at public/panduan-lisensi.html so it can also be served
 * directly by nginx if needed (e.g. for client docs site), but going
 * through this controller adds the auth middleware so anonymous traffic
 * cannot scrape the internal documentation.
 */
class GuideController extends Controller
{
    public function show(): BinaryFileResponse|Response
    {
        $path = public_path('panduan-lisensi.html');

        if (! is_file($path)) {
            abort(404, 'Panduan tidak ditemukan. Hubungi developer untuk publish guide.');
        }

        return response()->file($path, [
            'Content-Type'  => 'text/html; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
