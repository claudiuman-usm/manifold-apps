<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Generates the home-screen / touch icons and the web-app manifest on the fly
 * so a plain `git pull` keeps them in sync — no binary assets to commit or copy
 * into the shim's served folder (mirrors AssetController). Icons are a full-bleed
 * amber diagonal gradient; iOS applies its own rounded-corner mask.
 */
class IconController extends Controller
{
    /** Amber gradient stops (top-left → bottom-right): #fcd34d → #f59e0b → #ea7317. */
    private const FROM = [252, 211, 77];
    private const MID = [245, 158, 11];
    private const TO = [234, 115, 23];

    /** Sizes Safari/Chrome request for touch icons and the manifest. */
    private const ALLOWED = [120, 152, 167, 180, 192, 512];

    public function icon(int $size): Response
    {
        $size = in_array($size, self::ALLOWED, true) ? $size : 180;

        $img = imagecreatetruecolor($size, $size);
        $span = 2 * ($size - 1);

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $t = ($x + $y) / $span; // 0..1 along the diagonal
                [$r, $g, $b] = $t < 0.5
                    ? $this->lerp(self::FROM, self::MID, $t / 0.5)
                    : $this->lerp(self::MID, self::TO, ($t - 0.5) / 0.5);
                imagesetpixel($img, $x, $y, ($r << 16) | ($g << 8) | $b);
            }
        }

        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    public function manifest(): Response
    {
        $manifest = [
            'name' => 'Manifold Apps',
            'short_name' => 'Manifold',
            'display' => 'standalone',
            'background_color' => '#f8f7fb',
            'theme_color' => '#f59e0b',
            'icons' => [
                ['src' => route('assets.icon', 192), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => route('assets.icon', 512), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ];

        return response(json_encode($manifest, JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /**
     * @param  array{int,int,int}  $a
     * @param  array{int,int,int}  $b
     * @return array{int,int,int}
     */
    private function lerp(array $a, array $b, float $k): array
    {
        return [
            (int) round($a[0] + ($b[0] - $a[0]) * $k),
            (int) round($a[1] + ($b[1] - $a[1]) * $k),
            (int) round($a[2] + ($b[2] - $a[2]) * $k),
        ];
    }
}
