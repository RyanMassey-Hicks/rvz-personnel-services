<?php
/**
 * Composes the final social ad: the AI-generated image is used ONLY as a
 * background scene, and the job title, company, location, "APPLY NOW"
 * call-to-action and the RVZ logo are drawn over it here, server-side,
 * with GD + FreeType.
 *
 * Why: free image models cannot render text — asking them for a headline
 * produced gibberish ("bLoouNd", "ONELG") on live runs, and the free
 * provider's fallback model also stamps its own watermark bottom-right.
 * Rendering the words ourselves means every ad is spelled correctly, on
 * brand (navy / silver / purple), and the bottom band covers the watermark
 * corner — regardless of which model the free service happens to use.
 *
 * Fonts: Lato (SIL OFL) in assets/fonts/ — see the LICENSE.txt there.
 */

const AD_SIZE = 1080;

/**
 * @param string $imageBytes  raw bytes of the AI-generated background (any GD-readable format)
 * @param array  $job         needs title, company_name, location, is_remote
 * @return string JPEG bytes of the finished 1080x1080 ad
 */
function compose_ad_image(string $imageBytes, array $job): string
{
    $src = @imagecreatefromstring($imageBytes);
    if (!$src) {
        throw new AiImageException('The generated background image could not be read.');
    }

    $canvas = imagecreatetruecolor(AD_SIZE, AD_SIZE);
    // Scale the background to cover the square canvas (centre-cropped if not square).
    $sw = imagesx($src);
    $sh = imagesy($src);
    $scale = max(AD_SIZE / $sw, AD_SIZE / $sh);
    $dw = (int) ceil($sw * $scale);
    $dh = (int) ceil($sh * $scale);
    imagecopyresampled($canvas, $src, (int) ((AD_SIZE - $dw) / 2), (int) ((AD_SIZE - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);

    imagealphablending($canvas, true);

    $navy = [10, 31, 68];
    $silver = [199, 204, 214];
    $purple = [124, 58, 237];
    $white = [255, 255, 255];

    // Bottom band: solid navy, with a short fade above it so the scene
    // dissolves into the band instead of ending at a hard edge.
    $bandTop = 640;
    $fadeHeight = 90;
    for ($i = 0; $i < $fadeHeight; $i++) {
        $alpha = (int) round(127 - (127 * ($i / $fadeHeight))); // 127 = transparent, 0 = opaque
        $c = imagecolorallocatealpha($canvas, $navy[0], $navy[1], $navy[2], $alpha);
        imageline($canvas, 0, $bandTop - $fadeHeight + $i, AD_SIZE, $bandTop - $fadeHeight + $i, $c);
    }
    $navyC = imagecolorallocate($canvas, $navy[0], $navy[1], $navy[2]);
    imagefilledrectangle($canvas, 0, $bandTop, AD_SIZE, AD_SIZE, $navyC);
    // Thin purple accent rule along the top of the band.
    $purpleC = imagecolorallocate($canvas, $purple[0], $purple[1], $purple[2]);
    imagefilledrectangle($canvas, 0, $bandTop, AD_SIZE, $bandTop + 5, $purpleC);

    $fontBlack = __DIR__ . '/../assets/fonts/Lato-Black.ttf';
    $fontBold = __DIR__ . '/../assets/fonts/Lato-Bold.ttf';
    $fontRegular = __DIR__ . '/../assets/fonts/Lato-Regular.ttf';
    $whiteC = imagecolorallocate($canvas, $white[0], $white[1], $white[2]);
    $silverC = imagecolorallocate($canvas, $silver[0], $silver[1], $silver[2]);

    $margin = 60;
    $textLeft = $margin;
    $textWidth = AD_SIZE - 2 * $margin;

    // Bottom row is pinned first: "APPLY NOW" pill bottom-right, site URL
    // bottom-left. Everything above has to fit within the space that leaves.
    $cta = 'APPLY NOW';
    $ctaSize = 26;
    $box = imagettfbbox($ctaSize, 0, $fontBlack, $cta);
    $ctaTextW = abs($box[2] - $box[0]);
    $padX = 34;
    $pillH = 62;
    $pillW = $ctaTextW + 2 * $padX;
    $pillX = AD_SIZE - $margin - $pillW;
    $pillY = AD_SIZE - $margin - $pillH;
    $contentBottom = $pillY - 22; // nothing above may extend past this

    // Logo mark (white on transparent) top-left of the band, company name beside it.
    $company = trim((string) ($job['company_name'] ?? ''));
    $logoPath = __DIR__ . '/../assets/img/logo-mark-white.png';
    $y = $bandTop + 40;
    if (is_file($logoPath) && ($logo = @imagecreatefrompng($logoPath))) {
        $logoH = 48;
        $logoW = (int) round(imagesx($logo) * ($logoH / imagesy($logo)));
        imagecopyresampled($canvas, $logo, $textLeft, $y, 0, 0, $logoW, $logoH, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
        if ($company !== '') {
            ad_text($canvas, $fontBold, 22, $silverC, $textLeft + $logoW + 18, $y + 33, mb_strtoupper($company));
        }
        $y += $logoH + 26;
    }

    $title = ad_headline_title($job);

    $where = trim((string) ($job['location'] ?? ''));
    if (!empty($job['is_remote'])) {
        $where = $where !== '' ? $where . '  ·  Remote' : 'Remote';
    }
    $whereSize = 26;
    $whereBlock = $where !== '' ? 12 + (int) round($whereSize * 1.3) : 0;

    // Auto-fit the headline by real rendered height: shrink until the
    // wrapped title (max three lines) plus the location line sits above
    // the bottom row, instead of overflowing into it.
    $size = 58;
    $lines = [];
    for (; $size >= 30; $size -= 2) {
        $lines = ad_wrap($fontBlack, $size, $title, $textWidth);
        $blockH = count($lines) * (int) round($size * 1.2);
        if (count($lines) <= 3 && $y + $blockH + $whereBlock <= $contentBottom) {
            break;
        }
    }
    $lineHeight = (int) round($size * 1.2);
    foreach ($lines as $line) {
        ad_text($canvas, $fontBlack, $size, $whiteC, $textLeft, $y + $size, $line);
        $y += $lineHeight;
    }

    if ($where !== '') {
        $y += 12;
        ad_text($canvas, $fontRegular, $whereSize, $silverC, $textLeft, $y + $whereSize, $where);
    }

    ad_pill($canvas, $pillX, $pillY, $pillW, $pillH, $purpleC);
    ad_text($canvas, $fontBlack, $ctaSize, $whiteC, $pillX + $padX, $pillY + (int) round($pillH / 2 + $ctaSize / 2.6), $cta);

    $siteLine = preg_replace('#^https?://#', '', rtrim(SITE_URL, '/'));
    ad_text($canvas, $fontRegular, 20, $silverC, $textLeft, $pillY + (int) round($pillH / 2 + 20 / 2.6), $siteLine);

    ob_start();
    imagejpeg($canvas, null, 90);
    imagedestroy($canvas);
    return (string) ob_get_clean();
}

/**
 * The job title as it should read on the ad. Recruiters often append the
 * company to the title ("Graphic Designer – Acme (Pty) Ltd"); since the
 * company is shown separately on the ad and named in the caption, that
 * suffix is dropped so the headline stays short.
 */
function ad_headline_title(array $job): string
{
    $title = trim((string) ($job['title'] ?? 'We are hiring'));
    $company = trim((string) ($job['company_name'] ?? ''));
    if ($company !== '' && preg_match('/^(.+?)\s+[–\-|:]\s+(.+)$/u', $title, $m) && mb_stripos($m[2], $company) !== false) {
        return trim($m[1]);
    }
    return $title;
}

/** Draws one line of UTF-8 text with FreeType; $y is the text baseline. */
function ad_text($img, string $font, int $size, int $color, int $x, int $y, string $text): void
{
    imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
}

/** Greedy word-wrap against the real rendered width of the font. */
function ad_wrap(string $font, int $size, string $text, int $maxWidth): array
{
    $words = preg_split('/\s+/', trim($text)) ?: [];
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        $box = imagettfbbox($size, 0, $font, $candidate);
        if (abs($box[2] - $box[0]) > $maxWidth && $current !== '') {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $candidate;
        }
    }
    if ($current !== '') {
        $lines[] = $current;
    }
    return $lines;
}

/** A pill (fully-rounded rectangle) — GD has no rounded-rect primitive. */
function ad_pill($img, int $x, int $y, int $w, int $h, int $color): void
{
    $r = (int) ($h / 2);
    imagefilledrectangle($img, $x + $r, $y, $x + $w - $r, $y + $h, $color);
    imagefilledellipse($img, $x + $r, $y + $r, $h, $h, $color);
    imagefilledellipse($img, $x + $w - $r, $y + $r, $h, $h, $color);
}
