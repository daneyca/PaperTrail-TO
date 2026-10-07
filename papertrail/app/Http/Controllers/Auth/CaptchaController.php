<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class CaptchaController extends Controller
{
    private const SESSION_KEY = 'login_captcha_hash';
    private const CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function image(Request $request): Response
    {
        $code = $this->generateCode();
        $request->session()->put(self::SESSION_KEY, Hash::make($code));

        if (! function_exists('imagecreatetruecolor')) {
            return response($this->svgCaptcha($code), 200, [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        }

        return response($this->pngCaptcha($code), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function refresh(): JsonResponse
    {
        return response()->json([
            'url' => route('captcha.image', ['t' => now()->timestamp]),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public static function matches(Request $request, ?string $value): bool
    {
        $hash = $request->session()->pull(self::SESSION_KEY);
        $value = strtoupper(trim((string) $value));

        if (! $hash || $value === '') {
            return false;
        }

        return Hash::check($value, $hash);
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    private function generateCode(): string
    {
        $length = random_int(5, 6);
        $code = '';
        $maxIndex = strlen(self::CHARSET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $code .= self::CHARSET[random_int(0, $maxIndex)];
        }

        return $code;
    }

    private function pngCaptcha(string $code): string
    {
        $width = 132;
        $height = 44;
        $image = imagecreatetruecolor($width, $height);

        $paper = imagecolorallocate($image, 250, 252, 254);
        $navy = imagecolorallocate($image, 11, 35, 65);
        $blue = imagecolorallocate($image, 31, 73, 125);
        $gold = imagecolorallocate($image, 240, 180, 41);
        $gray = imagecolorallocate($image, 209, 216, 226);

        imagefilledrectangle($image, 0, 0, $width, $height, $paper);

        for ($i = 0; $i < 7; $i++) {
            $color = $i % 2 === 0 ? $gray : $gold;
            imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $color);
        }

        for ($i = 0; $i < 90; $i++) {
            imagesetpixel($image, random_int(1, $width - 2), random_int(1, $height - 2), $i % 4 === 0 ? $gold : $gray);
        }

        $font = 5;
        $charWidth = imagefontwidth($font);
        $x = (int) (($width - (strlen($code) * ($charWidth + 7))) / 2);

        foreach (str_split($code) as $index => $char) {
            $y = random_int(12, 18);
            imagestring($image, $font, $x + ($index * ($charWidth + 8)), $y, $char, $index % 2 === 0 ? $navy : $blue);
        }

        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    private function svgCaptcha(string $code): string
    {
        $escapedCode = e($code);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="132" height="44" viewBox="0 0 132 44" role="img" aria-label="Security code image">
    <rect width="132" height="44" rx="6" fill="#fafcfe"/>
    <path d="M4 34 C28 8, 42 56, 70 18 S102 5, 128 28" stroke="#f0b429" stroke-width="1.4" opacity=".55" fill="none"/>
    <path d="M0 14 L132 32" stroke="#d1d8e2" stroke-width="1" opacity=".8"/>
    <path d="M8 24 L126 10" stroke="#d1d8e2" stroke-width="1" opacity=".55"/>
    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#0b2341" font-family="Arial, sans-serif" font-size="23" font-weight="700" letter-spacing="3">{$escapedCode}</text>
</svg>
SVG;
    }
}
