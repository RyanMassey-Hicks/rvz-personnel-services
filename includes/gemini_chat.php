<?php
/**
 * Gemini-powered support chat replies — the "head" AI for ajax/chatbot.php.
 * Plain cURL, no SDK/Composer. Gemini's text models have a genuine free
 * tier (unlike its image models), so this is safe to leave on by default;
 * the caller is still expected to fall back to the rule-based engine on
 * any GeminiChatException (network issue, quota hit, malformed response).
 */

class GeminiChatException extends RuntimeException {}

/**
 * Asks Gemini to reply to $userMessage as the RVZ support assistant,
 * grounded strictly in $facts (the visitor's own real account data — see
 * chatbot_account_facts() in ajax/chatbot.php). Returns the reply text.
 */
function gemini_chat_reply(string $userMessage, array $facts): string
{
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
        throw new GeminiChatException('Gemini chat is not configured (missing GEMINI_API_KEY).');
    }

    $model = defined('GEMINI_CHAT_MODEL') ? GEMINI_CHAT_MODEL : 'gemini-2.5-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';

    $systemInstruction = "You are the support assistant for RVZ Personnel Services & Labour Hiring Specialists, "
        . "a South African recruitment platform. Be warm, concise (2-4 sentences unless more detail is clearly "
        . "needed), and practical. Only state facts about the visitor's own account that appear in ACCOUNT_FACTS "
        . "below (as JSON) — never invent application counts, job counts, billing status, or any other data. If "
        . "ACCOUNT_FACTS shows logged_in=false, don't assume anything about their account. If you don't know the "
        . "answer to something platform-specific, say so plainly and suggest they click \"Escalate to Support\". "
        . "Don't discuss topics unrelated to job hunting, recruiting, or using this platform.\n\n"
        . "ACCOUNT_FACTS: " . json_encode($facts);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 28,
        CURLOPT_HTTPHEADER => [
            'x-goog-api-key: ' . GEMINI_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userMessage]]],
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                // This model spends part of its token budget on internal
                // "thinking" before the visible reply, so the budget is set
                // generously to avoid the reply itself getting truncated.
                'maxOutputTokens' => 2048,
            ],
        ]),
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new GeminiChatException('Could not reach Gemini: ' . $err);
    }
    $decoded = json_decode($raw, true);
    if ($status !== 200) {
        $msg = $decoded['error']['message'] ?? ('HTTP ' . $status);
        throw new GeminiChatException('Gemini chat request failed: ' . $msg);
    }

    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!$text || trim($text) === '') {
        throw new GeminiChatException('Gemini returned an empty reply.');
    }
    return trim($text);
}
