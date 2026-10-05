<?php

namespace App\Support;

use App\Models\Person;

/**
 * Renders a left-to-right ancestor chart as SVG. Used on screen and as a downloadable,
 * shareable "N generation family tree" (with subtle product branding).
 */
class PedigreeChart
{
    /** @param array<int, array<int, ?Person>> $rows */
    public static function svg(array $rows, string $familyName, bool $standalone = false): string
    {
        $gens = count($rows);
        $boxW = 220;
        $boxH = 46;
        $gap = 36;
        $slot = $gens >= 6 ? 50 : 58;
        $top = 70;
        $height = (2 ** ($gens - 1)) * $slot;
        $width = $gens * ($boxW + $gap) + 40;
        $total = $height + $top + 50;
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $out = [];
        if ($standalone) {
            $out[] = '<?xml version="1.0" encoding="UTF-8"?>';
        }
        $out[] = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 {$width} {$total}\" width=\"{$width}\" height=\"{$total}\" font-family=\"Georgia, 'Times New Roman', serif\" role=\"img\" aria-label=\"Family tree of {$e($rows[0][0]?->full_name)}\">";
        $out[] = "<rect width=\"{$width}\" height=\"{$total}\" fill=\"#fbf8f3\"/>";
        $out[] = "<text x=\"20\" y=\"38\" font-size=\"22\" fill=\"#3b2f24\">{$e($gens)} generations of the {$e($familyName)}</text>";

        $center = fn (int $g, int $i) => $top + ($i + 0.5) * $height / (2 ** $g);
        foreach ($rows as $g => $row) {
            $x = 20 + $g * ($boxW + $gap);
            foreach ($row as $i => $p) {
                $cy = $center($g, $i);
                // connectors to parents
                if ($g + 1 < $gens) {
                    foreach ([0, 1] as $k) {
                        $parent = $rows[$g + 1][$i * 2 + $k] ?? null;
                        if ($parent) {
                            $py = $center($g + 1, $i * 2 + $k);
                            $x1 = $x + $boxW;
                            $mx = $x1 + $gap / 2;
                            $x2 = $x1 + $gap;
                            $out[] = "<path d=\"M{$x1} {$cy} H{$mx} V{$py} H{$x2}\" fill=\"none\" stroke=\"#b9a88f\" stroke-width=\"1.5\"/>";
                        }
                    }
                }
                $y = $cy - $boxH / 2;
                if (! $p) {
                    if ($g > 0 && ($rows[$g - 1][intdiv($i, 2)] ?? null)) {
                        $out[] = "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$boxW}\" height=\"{$boxH}\" rx=\"8\" fill=\"none\" stroke=\"#d8ccb8\" stroke-dasharray=\"4 4\"/>";
                        $out[] = '<text x="'.($x + 12).'" y="'.($cy + 5).'" font-size="13" fill="#a8998a" font-style="italic">Unknown</text>';
                    }

                    continue;
                }
                $accent = match ($p->gender) {
                    'male' => '#5b7b9a', 'female' => '#a0606e', default => '#8a7a66'
                };
                $name = mb_strimwidth($p->full_name, 0, 28, '…');
                $sub = trim($p->lifespan.($p->birthPlace ? ' · '.$p->birthPlace->name : ''));
                $sub = mb_strimwidth($sub, 0, 36, '…');
                $out[] = "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$boxW}\" height=\"{$boxH}\" rx=\"8\" fill=\"#ffffff\" stroke=\"#d8ccb8\"/>";
                $out[] = "<rect x=\"{$x}\" y=\"{$y}\" width=\"5\" height=\"{$boxH}\" rx=\"2\" fill=\"{$accent}\"/>";
                $out[] = '<text x="'.($x + 14).'" y="'.($y + 20).'" font-size="14" fill="#2b2118">'.$e($name).'</text>';
                $out[] = '<text x="'.($x + 14).'" y="'.($y + 37).'" font-size="11.5" fill="#7a6a58">'.$e($sub).'</text>';
            }
        }
        $out[] = '<text x="'.($width - 20).'" y="'.($total - 18).'" font-size="11" fill="#a8998a" text-anchor="end">Created with Heirloom · Preserve your family’s story for generations</text>';
        $out[] = '</svg>';

        return implode("\n", $out);
    }
}
