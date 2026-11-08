<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Services\QrCodeGenerator;
use Illuminate\Http\Request;

/**
 * Admin page that turns the family's home page address into a QR code relatives can scan,
 * with PNG and SVG downloads for printing on invitations, reunion posters or framed photos.
 */
class QrCodeController extends Controller
{
    public function show(Family $family, QrCodeGenerator $qr)
    {
        $url = route('families.show', $family);

        return view('qr.show', [
            'url' => $url,
            'svg' => $qr->svg($url, 'currentColor'),
            'sizes' => QrCodeGenerator::PNG_SIZES,
        ]);
    }

    public function download(Request $request, Family $family, QrCodeGenerator $qr)
    {
        $data = $request->validate([
            'format' => 'required|in:png,svg',
            'size' => 'nullable|integer|in:'.implode(',', QrCodeGenerator::PNG_SIZES),
        ]);
        $url = route('families.show', $family);
        $name = $family->slug.'-qr-code';

        if ($data['format'] === 'svg') {
            return response($qr->svgFile($url), 200, [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => 'attachment; filename="'.$name.'.svg"',
            ]);
        }

        $size = (int) ($data['size'] ?? 1024);

        return response($qr->png($url, $size), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$name.'-'.$size.'.png"',
        ]);
    }
}
