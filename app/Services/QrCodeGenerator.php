<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Renders QR codes that point at a page of the archive, for printing or sharing.
 *
 * Both renderers use error-correction level M, so a code still scans when a corner is
 * covered by a sticker or a finger, and keep the standard four-module quiet zone.
 */
class QrCodeGenerator
{
    /** Allowed pixel widths for PNG downloads; large enough for an A4 poster at the top end. */
    public const PNG_SIZES = [256, 512, 1024, 2048];

    /** Inline SVG (no XML header) that scales to its container; dark modules follow the ink colour. */
    public function svg(string $url, string $colour = '#1c1712'): string
    {
        $qr = new QRCode(new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'eccLevel' => EccLevel::M,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
            'moduleValues' => $this->darkModules($colour),
        ]));

        return $qr->render($url);
    }

    /** Standalone SVG file with the XML header, dark modules on a white background. */
    public function svgFile(string $url): string
    {
        $qr = new QRCode(new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'eccLevel' => EccLevel::M,
            'outputBase64' => false,
            'svgAddXmlHeader' => true,
            'drawLightModules' => true,
            'moduleValues' => $this->darkModules('#000000') + $this->lightModules('#ffffff'),
        ]));

        return $qr->render($url);
    }

    /** PNG binary, black on white, scaled so the whole image (quiet zone included) is about $size pixels wide. */
    public function png(string $url, int $size = 1024): string
    {
        $size = in_array($size, self::PNG_SIZES, true) ? $size : 1024;
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel' => EccLevel::M,
            'outputBase64' => false,
            'bgColor' => [255, 255, 255],
            'drawLightModules' => true,
            'scale' => 10,
        ]);
        $qr = new QRCode($options);
        $matrix = $qr->addByteSegment($url)->getQRMatrix();
        // The matrix already includes the quiet zone; pick the scale that fills the requested width.
        $options->scale = max(1, intdiv($size, $matrix->getSize()));

        return (new QRCode($options))->render($url);
    }

    /** @return array<int, string> */
    private function darkModules(string $colour): array
    {
        $values = [];
        foreach (array_keys(QRMarkupSVG::DEFAULT_MODULE_VALUES) as $type) {
            if ($type & QRMatrix::IS_DARK) {
                $values[$type] = $colour;
            }
        }

        return $values;
    }

    /** @return array<int, string> */
    private function lightModules(string $colour): array
    {
        $values = [];
        foreach (array_keys(QRMarkupSVG::DEFAULT_MODULE_VALUES) as $type) {
            if (! ($type & QRMatrix::IS_DARK)) {
                $values[$type] = $colour;
            }
        }

        return $values;
    }
}
