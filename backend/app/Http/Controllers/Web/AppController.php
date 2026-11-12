<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Müşteri web uygulaması (PWA): mobile/ klasöründeki Expo web derlemesi public/uygulama'ya kopyalanır.
 * Dosya olmayan her alt adres (ör. /uygulama/kesfet) app.html'e (Expo'nun index.html'i) düşer; yönlendirmeyi uygulama yapar.
 */
class AppController extends Controller
{
    public function pwa(): BinaryFileResponse
    {
        $index = public_path('uygulama/app.html');
        abort_unless(is_file($index), 404);

        return response()->file($index, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
