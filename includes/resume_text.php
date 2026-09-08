<?php
/**
 * Best-effort full-text extraction from uploaded resumes, so Direct Search
 * can match a keyword that only appears inside the file itself (not just
 * the structured "Skills" field) — see recruiter_search.php. No Composer,
 * so this is a from-scratch, dependency-free extractor:
 *
 * - .docx is just a zip of XML — reliable, uses PHP's built-in ZipArchive.
 * - .pdf has no single standard way to hold text; this scans content
 *   streams for text-showing operators (Tj/TJ), decompressing FlateDecode
 *   streams with zlib. It's a heuristic, not a real PDF parser — it does
 *   well on typical single-column, non-scanned resumes, and can extract
 *   little or nothing from a scanned/image-only PDF or one with heavily
 *   subsetted/custom-encoded fonts. That's an acceptable trade-off for a
 *   search-relevance feature (best effort beats no full-text at all), but
 *   worth knowing if a specific resume doesn't turn up in a keyword search.
 * - .doc (legacy binary Word, pre-2007) is not supported — it's a complex
 *   OLE compound-file format, not worth the effort for a format almost no
 *   one still uploads. Falls back to empty string (structured profile
 *   fields still work as before).
 *
 * Never throws — a parsing failure must never block a resume upload.
 */

/** Reads a resume file on disk and returns its plain text, or '' if unsupported/unreadable. */
function extract_resume_text(string $absolutePath, string $extension): string
{
    if (!is_file($absolutePath)) {
        return '';
    }
    try {
        $ext = strtolower($extension);
        if ($ext === 'docx') {
            return extract_docx_text($absolutePath);
        }
        if ($ext === 'pdf') {
            return extract_pdf_text($absolutePath);
        }
    } catch (Throwable $e) {
        error_log('Resume text extraction failed for ' . basename($absolutePath) . ': ' . $e->getMessage());
    }
    return '';
}

function extract_docx_text(string $path): string
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return '';
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false) {
        return '';
    }

    // Preserve word/line boundaries before stripping tags, or "One Two"
    // in separate runs/paragraphs would otherwise collapse into "OneTwo".
    $xml = str_replace(['<w:tab/>', '<w:br/>', '<w:cr/>'], ' ', $xml);
    $xml = preg_replace('/<\/w:p>/', "</w:p>\n", $xml);
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

    return normalize_extracted_text($text);
}

function extract_pdf_text(string $path): string
{
    $data = file_get_contents($path);
    if ($data === false) {
        return '';
    }

    $streams = [];
    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $data, $matches)) {
        $streams = $matches[1];
    }

    $text = '';
    foreach ($streams as $raw) {
        $content = @gzuncompress($raw);
        if ($content === false) {
            $content = @gzinflate($raw);
        }
        if ($content === false) {
            // Not compressed (or a non-text stream, e.g. an image) — scan
            // it as-is; the Tj/TJ regexes below simply won't match binary noise.
            $content = $raw;
        }
        $text .= extract_text_operators($content) . "\n";
    }

    return normalize_extracted_text($text);
}

/** Pulls literal (Tj/TJ) and hex (<..>Tj) text-showing operators out of one decoded PDF content stream. */
function extract_text_operators(string $content): string
{
    $out = '';

    // (literal string) Tj  — single text-show
    if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)\s*Tj/s', $content, $m)) {
        foreach ($m[1] as $s) {
            $out .= decode_pdf_literal_string($s) . ' ';
        }
    }
    // <hex string> Tj
    if (preg_match_all('/<([0-9A-Fa-f\s]+)>\s*Tj/s', $content, $m)) {
        foreach ($m[1] as $s) {
            $out .= decode_pdf_hex_string($s) . ' ';
        }
    }
    // [ (a) -200 (b) <hex> ... ] TJ  — array show, mixing literal/hex/kerning numbers
    if (preg_match_all('/\[((?:[^\[\]])*)\]\s*TJ/s', $content, $m)) {
        foreach ($m[1] as $arrayBody) {
            if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/s', $arrayBody, $lits)) {
                foreach ($lits[1] as $s) {
                    $out .= decode_pdf_literal_string($s);
                }
            }
            if (preg_match_all('/<([0-9A-Fa-f\s]+)>/s', $arrayBody, $hexes)) {
                foreach ($hexes[1] as $s) {
                    $out .= decode_pdf_hex_string($s);
                }
            }
            $out .= ' ';
        }
    }
    // Position/line operators — insert a break so words from different
    // lines don't run together when there was no space between them.
    if (preg_match('/\b(Td|TD|T\*|ET)\b/', $content)) {
        $out .= "\n";
    }

    return $out;
}

function decode_pdf_literal_string(string $s): string
{
    $map = ['\\n' => "\n", '\\r' => "\r", '\\t' => "\t", '\\(' => '(', '\\)' => ')', '\\\\' => '\\'];
    $s = strtr($s, $map);
    // Octal escapes like \101
    $s = preg_replace_callback('/\\\\([0-7]{1,3})/', fn ($m) => chr((int) octdec($m[1]) & 0xFF), $s);
    return $s;
}

function decode_pdf_hex_string(string $hex): string
{
    $hex = preg_replace('/\s+/', '', $hex);
    if (strlen($hex) % 2 !== 0) {
        $hex .= '0';
    }
    $bytes = @hex2bin($hex);
    return $bytes === false ? '' : $bytes;
}

/** Shared cleanup: collapse whitespace, drop control characters, cap length so the DB column stays sane. */
function normalize_extracted_text(string $text): string
{
    $text = @mb_convert_encoding($text, 'UTF-8', 'UTF-8') ?: '';
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    $text = trim($text);
    return mb_substr($text, 0, 200000);
}
