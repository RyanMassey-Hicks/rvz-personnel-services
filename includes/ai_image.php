<?php
/**
 * AI image generation for the recruiter "Ads" feature — multi-provider,
 * plain cURL, no SDK/Composer needed.
 *
 * Every company defaults to Pollinations (ai_image_provider = 'free'): a
 * genuinely free, no-API-key image generator, so ad generation never has a
 * platform-wide credit balance that can run out. A company can instead
 * bring its own Gemini or OpenAI API key (set up per-company in
 * admin_integrations.php — "the control room") if it wants a specific
 * model, higher quality, or to fold its own brand guidelines into the
 * prompt more heavily — that usage and any cost is on the company's own
 * account, never RVZ's.
 */

class AiImageException extends RuntimeException {}

/**
 * Free, keyless image generation via Pollinations.ai. No account, no
 * billing, ever — this is the platform-wide default for exactly that reason.
 *
 * @return array{bytes: string, mime: string}
 */
function pollinations_generate_image(string $prompt): array
{
    // Pollinations is free and keyless, which also means it's flaky: observed
    // live returning HTTP 500s, and HTTP 200s with a completely empty body.
    // Those failures come back fast (~1-2s), so there's budget to retry —
    // first with a fresh seed on the higher-quality "flux" model, then on
    // "turbo", which runs on different infrastructure and is a genuinely
    // independent fallback rather than just the same request again.
    //
    // One overall time budget is shared across attempts, and each attempt's
    // cURL timeout is capped to whatever is left of it. The hard ceiling
    // here is NOT PHP's max_execution_time (set_time_limit() is honoured on
    // this host) but the web server in front of it, which was observed live
    // returning a 503 once a request passed ~60s — so this call runs in its
    // own request (ajax/ad_render.php) and must finish, including file write
    // and DB insert, inside that. A *successful* Pollinations render is
    // routinely 40-45s, so 50s fits one slow success or a fast-fail retry.
    // (It used to be a single 90s timeout bundled in the same request as
    // Gemini planning, which could get the whole thing killed with nothing
    // shown to the recruiter at all.)
    $attempts = [
        ['model' => 'flux'],
        ['model' => 'flux'],
        ['model' => 'turbo'],
    ];
    $totalBudget = 50;
    $started = microtime(true);
    $lastError = 'Unknown error';

    foreach ($attempts as $i => $attempt) {
        $remaining = $totalBudget - (microtime(true) - $started);
        if ($remaining < 8) {
            break; // not enough time left for a meaningful attempt
        }

        $url = 'https://image.pollinations.ai/prompt/' . rawurlencode($prompt)
            . '?width=1024&height=1024&nologo=true&model=' . $attempt['model'] . '&seed=' . random_int(1, 999999999);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) floor($remaining),
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            $lastError = 'Could not reach the free image generator: ' . $err;
        } elseif ($status !== 200 || strpos($contentType, 'image/') !== 0) {
            $lastError = 'The free image generator did not return an image (HTTP ' . $status . ').';
        } elseif ($raw === '') {
            // HTTP 200 with an empty body — without this check it silently
            // saved and displayed as a broken/blank image with no error.
            $lastError = 'The free image generator returned an empty image.';
        } else {
            $mime = strpos($contentType, 'image/png') !== false ? 'image/png' : 'image/jpeg';
            return ['bytes' => $raw, 'mime' => $mime];
        }
        error_log('[ai_image] Pollinations attempt ' . ($i + 1) . ' (' . $attempt['model'] . ') failed: ' . $lastError);
    }

    throw new AiImageException($lastError . ' The free image service is having trouble right now — please try again in a moment.');
}

/**
 * Google Gemini image generation using a company-supplied API key.
 *
 * @return array{bytes: string, mime: string}
 */
function gemini_generate_image(string $prompt, string $apiKey): array
{
    if ($apiKey === '') {
        throw new AiImageException('No Google Gemini API key is set for this company.');
    }

    $model = defined('GEMINI_IMAGE_MODEL') ? GEMINI_IMAGE_MODEL : 'gemini-2.5-flash-image';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'x-goog-api-key: ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'responseModalities' => ['IMAGE'],
            ],
        ]),
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new AiImageException('Could not reach Gemini: ' . $err);
    }
    $decoded = json_decode($raw, true);
    if ($status !== 200) {
        $msg = $decoded['error']['message'] ?? ('HTTP ' . $status);
        throw new AiImageException('Gemini request failed: ' . $msg);
    }

    $parts = $decoded['candidates'][0]['content']['parts'] ?? [];
    foreach ($parts as $part) {
        $inline = $part['inlineData'] ?? null;
        if ($inline && !empty($inline['data'])) {
            $bytes = base64_decode($inline['data'], true);
            if ($bytes === false) {
                throw new AiImageException('Could not decode the image Gemini returned.');
            }
            return ['bytes' => $bytes, 'mime' => $inline['mimeType'] ?? 'image/png'];
        }
    }
    throw new AiImageException('Gemini returned no image data.');
}

/**
 * OpenAI image generation using a company-supplied API key.
 *
 * @return array{bytes: string, mime: string}
 */
function openai_generate_image(string $prompt, string $apiKey): array
{
    if ($apiKey === '') {
        throw new AiImageException('No OpenAI API key is set for this company.');
    }

    $ch = curl_init('https://api.openai.com/v1/images/generations');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-image-1',
            'prompt' => $prompt,
            'size' => '1024x1024',
            'quality' => 'low',
            'n' => 1,
        ]),
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new AiImageException('Could not reach OpenAI: ' . $err);
    }
    $decoded = json_decode($raw, true);
    if ($status !== 200) {
        $msg = $decoded['error']['message'] ?? ('HTTP ' . $status);
        throw new AiImageException('OpenAI request failed: ' . $msg);
    }
    $b64 = $decoded['data'][0]['b64_json'] ?? null;
    if (!$b64) {
        throw new AiImageException('OpenAI returned no image data.');
    }
    $bytes = base64_decode($b64, true);
    if ($bytes === false) {
        throw new AiImageException('Could not decode the image OpenAI returned.');
    }
    return ['bytes' => $bytes, 'mime' => 'image/png'];
}

/**
 * Uses Gemini's TEXT model (the same free-tier one that powers the support
 * chatbot — see includes/gemini_chat.php) to plan an ad before any image is
 * drawn: a short social-media caption, plus a vivid scene description for
 * the image generator to render. Pairing a real text model with Pollinations
 * (a keyless, free image renderer) gives noticeably better, more specific
 * ad images than handing Pollinations a templated prompt directly — two
 * independent free AI systems doing what each is actually good at.
 *
 * Requires the platform's GEMINI_API_KEY (config.php) to be set; throws
 * AiImageException if it's blank or the call fails for any reason, so the
 * caller can fall back to the plain templated prompt (see build_ad_prompt()).
 *
 * @return array{copy: string, scene: string}
 */
function gemini_generate_ad_content(string $jobTitle, string $companyName, string $location, string $style, string $brandGuidelines = ''): array
{
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
        throw new AiImageException('Gemini is not configured (missing GEMINI_API_KEY).');
    }

    $model = defined('GEMINI_CHAT_MODEL') ? GEMINI_CHAT_MODEL : 'gemini-3.6-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';

    $instruction = "You are a recruitment social-media copywriter and art director. Given a job posting, produce two things:\n"
        . "1. \"copy\": a short, punchy 2-3 sentence social media caption promoting this job, ending with a clear call to action to apply. No hashtags, no emojis.\n"
        . "2. \"scene\": a vivid, concrete description of a photorealistic background scene for a recruitment ad image — the workplace/industry setting, mood, lighting, and colour palette. Do NOT describe any text, words, headlines, or logos in the scene — those are added separately. Do NOT describe any specific person's face.\n"
        . "Match the requested style and any brand guidelines given. Respond with ONLY strict JSON, no markdown, no commentary: {\"copy\": \"...\", \"scene\": \"...\"}";

    $details = "Job title: {$jobTitle}\nCompany: {$companyName}\nLocation: {$location}\nStyle: {$style}"
        . ($brandGuidelines !== '' ? "\nBrand guidelines: {$brandGuidelines}" : '');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        // Short on purpose: this is a fallback-able planning step, not the
        // main event — failing fast here leaves the image-generation call
        // below enough of the 60s PHP execution budget to actually complete
        // (see pollinations_generate_image()'s own timeout comment).
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => [
            'x-goog-api-key: ' . GEMINI_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'systemInstruction' => ['parts' => [['text' => $instruction]]],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $details]]],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024,
                'responseMimeType' => 'application/json',
            ],
        ]),
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new AiImageException('Could not reach Gemini: ' . $err);
    }
    $decoded = json_decode($raw, true);
    if ($status !== 200) {
        $msg = $decoded['error']['message'] ?? ('HTTP ' . $status);
        throw new AiImageException('Gemini ad-planning request failed: ' . $msg);
    }

    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $content = json_decode($text, true);
    $copy = trim((string) ($content['copy'] ?? ''));
    $scene = trim((string) ($content['scene'] ?? ''));
    if ($copy === '' || $scene === '') {
        throw new AiImageException('Gemini did not return usable ad copy/scene.');
    }
    return ['copy' => $copy, 'scene' => $scene];
}

/** Builds the image-generation prompt from a Gemini-written scene description, still enforcing our own safety/layout rules rather than trusting the model for those. */
function build_ad_prompt_from_scene(string $scene, string $jobTitle, string $companyName, string $style = 'professional'): string
{
    $bits = [
        "A polished, professional square (1:1) recruitment social media advertisement graphic for the job posting \"{$jobTitle}\" at \"{$companyName}\".",
        'Scene: ' . $scene,
        'Style: ' . $style . ', modern, minimalist corporate design using a navy blue, silver, black and white color palette.',
        'Include bold, legible headline text with the job title and "APPLY NOW" as a call to action. Leave clean negative space suitable for a company logo overlay.',
        'No photorealistic faces of real people. No spelling errors in any rendered text.',
    ];
    return implode(' ', $bits);
}

/** Human-readable label for a provider code, used in the Ads UI. */
function ai_image_provider_label(string $provider): string
{
    return match ($provider) {
        'gemini' => 'Google Gemini (this company\'s own key)',
        'openai' => 'OpenAI (this company\'s own key)',
        default => 'Free AI (no key needed)',
    };
}

/**
 * Routes to the right provider for a company: its own Gemini/OpenAI key if
 * configured, otherwise the platform's free provider. $company only needs
 * to carry ai_image_provider and ai_image_api_key_encrypted.
 *
 * @return array{bytes: string, mime: string, provider: string}
 */
function ai_generate_image_for_company(string $prompt, array $company): array
{
    $provider = $company['ai_image_provider'] ?? 'free';

    if ($provider === 'gemini' || $provider === 'openai') {
        $key = decrypt_secret($company['ai_image_api_key_encrypted'] ?? '');
        if ($key === '') {
            throw new AiImageException(
                'This company is set up to use its own ' . ($provider === 'gemini' ? 'Google Gemini' : 'OpenAI') .
                ' key for ads, but none has been added yet. Ask RVZ support to add it in Admin Integrations.'
            );
        }
        $result = $provider === 'gemini' ? gemini_generate_image($prompt, $key) : openai_generate_image($prompt, $key);
        $result['provider'] = $provider;
        return $result;
    }

    $result = pollinations_generate_image($prompt);
    $result['provider'] = 'free';
    return $result;
}

/** Builds a consistent, on-brand prompt from a job + company so recruiters don't have to write one. */
function build_ad_prompt(array $job, string $companyName, string $style = 'professional', string $brandGuidelines = ''): string
{
    $bits = [
        "A polished, professional square (1:1) recruitment social media advertisement graphic for the job posting \"{$job['title']}\" at \"{$companyName}\".",
        "Location: {$job['location']}.",
        'Style: ' . $style . ', modern, minimalist corporate design using a navy blue, silver, black and white color palette.',
        'Include bold, legible headline text with the job title and "APPLY NOW" as a call to action. Leave clean negative space suitable for a company logo overlay.',
        'No photorealistic faces of real people. No spelling errors in any rendered text.',
    ];
    if ($brandGuidelines !== '') {
        $bits[] = 'Follow this company\'s brand guidelines as closely as possible: ' . $brandGuidelines;
    }
    return implode(' ', $bits);
}
