<?php
/**
 * Minimal cURL helpers so the OAuth callbacks don't need any Composer
 * packages or SDKs — just PHP's built-in cURL extension, which is enabled
 * by default on most cPanel shared hosting PHP builds (including this
 * host-h.net account).
 */

function http_post_form(string $url, array $fields, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('HTTP request failed: ' . $err);
    }
    $decoded = json_decode($body, true);
    return ['status' => $status, 'body' => $decoded ?? $body];
}

function http_get_json(string $url, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('HTTP request failed: ' . $err);
    }
    return ['status' => $status, 'body' => json_decode($body, true) ?? []];
}
