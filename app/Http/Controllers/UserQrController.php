<?php

namespace App\Http\Controllers;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserQrController extends Controller
{
    public function show(Request $request): Response
    {
        $membershipNumber = $request->user()?->membership_number;

        if (blank($membershipNumber)) {
            abort(404, 'Nario numeris nerastas');
        }

        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd()
        );

        $svg = (new Writer($renderer))->writeString($membershipNumber);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }
}








