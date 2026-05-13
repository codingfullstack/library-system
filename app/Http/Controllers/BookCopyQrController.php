<?php

namespace App\Http\Controllers;

use App\Queries\BookCopies\GetVisibleBookCopyQuery;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Response;

class BookCopyQrController extends Controller
{
    public function show(string $id, GetVisibleBookCopyQuery $getVisibleBookCopyQuery): Response
    {
        $copy = $getVisibleBookCopyQuery->handle(auth()->user(), $id);

        if (empty($copy->qr_code)) {
            abort(404, 'QR kodo reikšmė nerasta');
        }

        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        $svg = $writer->writeString($copy->qr_code);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }
}








