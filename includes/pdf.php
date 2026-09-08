<?php
/**
 * A minimal, dependency-free PDF writer — no FPDF/Composer needed. Supports
 * flowing wrapped text in Helvetica (a standard PDF core font, no embedding
 * needed), colored fill rectangles/text for simple branded layouts (header
 * bands, section rules), and a repeated footer across every page (used for
 * the RVZ watermark/copyright line on generated CVs and the SLA). Not a
 * general-purpose PDF library.
 */
class SimplePdf
{
    private array $pages = [];
    private float $y;
    private float $pageWidth;
    private float $pageHeight;
    private float $margin = 42;
    private float $lineHeight = 14;

    /** $size: 'a4' (default — South African standard) or 'letter'. */
    public function __construct(string $size = 'a4')
    {
        if ($size === 'letter') {
            $this->pageWidth = 612;
            $this->pageHeight = 792;
        } else {
            $this->pageWidth = 595.28;
            $this->pageHeight = 841.89;
        }
        $this->startPage();
    }

    private function startPage(): void
    {
        $this->pages[] = [];
        $this->y = $this->pageHeight - $this->margin;
    }

    private function sanitize(string $text): string
    {
        // Core Helvetica uses WinAnsi/Latin-1 encoding — transliterate anything else.
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $text);
        return $converted !== false ? $converted : preg_replace('/[^\x20-\x7E]/', '', $text);
    }

    private function esc(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    private function colorOp(array $rgb): string
    {
        return sprintf('%.3F %.3F %.3F', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
    }

    public function pageHeight(): float
    {
        return $this->pageHeight;
    }

    public function pageWidth(): float
    {
        return $this->pageWidth;
    }

    public function margin(): float
    {
        return $this->margin;
    }

    public function cursorY(): float
    {
        return $this->y;
    }

    public function setCursorY(float $y): void
    {
        $this->y = $y;
    }

    public function addHeading(string $text, float $size = 16): void
    {
        $this->addSpacer(4);
        $this->addText($text, $size, true);
        $this->addSpacer(6);
    }

    /** A bold, uppercase-ish section label with a thin rule underneath — the CV's section headers. */
    public function addSectionLabel(string $text, array $color = [10, 31, 68]): void
    {
        $this->addSpacer(10);
        $this->addText(strtoupper($text), 12, true, $color);
        $this->addRuleUnderCursor($color);
        $this->addSpacer(6);
    }

    public function addRuleUnderCursor(array $color = [200, 200, 200]): void
    {
        $idx = count($this->pages) - 1;
        $y = $this->y + 3;
        $this->pages[$idx][] = sprintf(
            '%s RG 1 w %.2F %.2F m %.2F %.2F l S',
            $this->colorOp($color), $this->margin, $y, $this->pageWidth - $this->margin, $y
        );
    }

    public function addText(string $text, float $size = 11, bool $bold = false, array $color = [0, 0, 0]): void
    {
        $text = $this->sanitize($text);
        $avgCharWidth = $size * 0.5;
        $maxChars = max(10, (int) floor(($this->pageWidth - 2 * $this->margin) / $avgCharWidth));
        foreach (explode("\n", $text) as $para) {
            if ($para === '') {
                $this->addSpacer($this->lineHeight * ($size / 11));
                continue;
            }
            foreach (explode("\n", wordwrap($para, $maxChars, "\n", true)) as $line) {
                $this->emitLine($line, $size, $bold, $color);
            }
        }
    }

    public function addSpacer(float $h = 10): void
    {
        $this->y -= $h;
    }

    /** Fills a rectangle. $yFromTop is measured from the top of the page down to the rect's TOP edge. */
    public function addRect(float $x, float $yFromTop, float $w, float $h, array $color): void
    {
        $idx = count($this->pages) - 1;
        $pdfY = $this->pageHeight - $yFromTop - $h;
        $this->pages[$idx][] = sprintf('%s rg %.2F %.2F %.2F %.2F re f', $this->colorOp($color), $x, $pdfY, $w, $h);
    }

    /** Places one line of text at an explicit position (yFromTop = distance from top of page to the text baseline). */
    public function addTextAt(float $x, float $yFromTop, string $text, float $size = 11, bool $bold = false, array $color = [0, 0, 0]): void
    {
        $idx = count($this->pages) - 1;
        $font = $bold ? 'F2' : 'F1';
        $pdfY = $this->pageHeight - $yFromTop;
        $this->pages[$idx][] = sprintf(
            '%s rg BT /%s %.1F Tf %.2F %.2F Td (%s) Tj ET',
            $this->colorOp($color), $font, $size, $x, $pdfY, $this->esc($this->sanitize($text))
        );
    }

    private function emitLine(string $line, float $size, bool $bold, array $color = [0, 0, 0]): void
    {
        if ($this->y < $this->margin + 30) {
            $this->startPage();
        }
        $font = $bold ? 'F2' : 'F1';
        $idx = count($this->pages) - 1;
        $this->pages[$idx][] = sprintf(
            '%s rg BT /%s %.1F Tf %.2F %.2F Td (%s) Tj ET',
            $this->colorOp($color), $font, $size, $this->margin, $this->y, $this->esc($line)
        );
        $this->y -= $this->lineHeight * ($size / 11);
    }

    /** Call once, after all content is added — a light, diagonal watermark behind the content on every page. */
    public function addWatermarkToAllPages(string $text, array $color = [235, 237, 241], float $size = 46): void
    {
        $text = $this->sanitize($text);
        $angle = deg2rad(40);
        $cos = cos($angle);
        $sin = sin($angle);
        $cx = $this->pageWidth / 2 - (strlen($text) * $size * 0.28);
        $cy = $this->pageHeight / 2;
        $cmd = sprintf(
            '%s rg BT /F2 %.1F Tf %.4F %.4F %.4F %.4F %.2F %.2F Tm (%s) Tj ET',
            $this->colorOp($color), $size, $cos, $sin, -$sin, $cos, $cx, $cy, $this->esc($text)
        );
        // Prepended so it paints first — real content, added later, paints over it instead of the reverse.
        foreach (array_keys($this->pages) as $idx) {
            array_unshift($this->pages[$idx], $cmd);
        }
    }

    /**
     * Call once, after all content is added — stamps a small centered footer
     * line (the RVZ watermark/copyright line) near the bottom of every page,
     * safely INSIDE the page margin band, never overlapping body content or
     * the header.
     */
    public function addFooterToAllPages(string $text, array $color = [140, 140, 140]): void
    {
        $text = $this->sanitize($text);
        $approxWidth = strlen($text) * 8 * 0.45;
        $x = max($this->margin, ($this->pageWidth - $approxWidth) / 2);
        // Absolute PDF coordinates (origin bottom-left) — both values are
        // deliberately smaller than $this->margin so the footer sits inside
        // the reserved bottom margin, below where body text is ever allowed
        // to flow (see emitLine's page-break check).
        $ruleY = $this->margin * 0.62;
        $textY = $this->margin * 0.4;
        foreach (array_keys($this->pages) as $idx) {
            $this->pages[$idx][] = sprintf('0.85 0.85 0.85 RG 0.75 w %.2F %.2F m %.2F %.2F l S', $this->margin, $ruleY, $this->pageWidth - $this->margin, $ruleY);
            $this->pages[$idx][] = sprintf(
                '%s rg BT /F1 8.0 Tf %.2F %.2F Td (%s) Tj ET',
                $this->colorOp($color), $x, $textY, $this->esc($text)
            );
        }
    }

    public function output(): string
    {
        $n = count($this->pages);
        $pageObjNums = range(5, 5 + $n - 1);
        $contentObjNums = range(5 + $n, 5 + 2 * $n - 1);

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [ ' . implode(' ', array_map(fn ($x) => "$x 0 R", $pageObjNums)) . " ] /Count $n >>";
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($this->pages as $i => $commands) {
            $pageNum = $pageObjNums[$i];
            $contentNum = $contentObjNums[$i];
            $objects[$pageNum] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] "
                . "/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $contentNum 0 R >>";
            $stream = implode("\n", $commands);
            $objects[$contentNum] = "<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream";
        }

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "$num 0 obj\n$body\nendobj\n";
        }
        $maxNum = max(array_keys($objects));
        $xrefStart = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxNum + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxNum; $i++) {
            $pdf .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 00000 f \n";
        }
        $pdf .= "trailer\n<< /Size " . ($maxNum + 1) . " /Root 1 0 R >>\nstartxref\n$xrefStart\n%%EOF";
        return $pdf;
    }
}
