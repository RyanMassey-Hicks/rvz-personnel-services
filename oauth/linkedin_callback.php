<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/http.php';
require __DIR__ . '/../includes/oauth_helper.php';

if (!oauth_state_verify('linkedin', $_GET['state'] ?? null)) {
    die('Login request could not be verified (invalid state). Please try signing in again.');
}

if (isset($_GET['error'])) {
    flash('danger', 'LinkedIn sign-in was cancelled.');
    redirect('/login.php');
}

$code = $_GET['code'] ?? '';
if (!$code) {
    die('Missing authorization code from LinkedIn.');
}

// Step 1: exchange the code for an access token
$tokenResponse = http_post_form('https://www.linkedin.com/oauth/v2/accessToken', [
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => base_url('oauth/linkedin_callback.php'),
    'client_id' => LINKEDIN_CLIENT_ID,
    'client_secret' => LINKEDIN_CLIENT_SECRET,
]);

$accessToken = $tokenResponse['body']['access_token'] ?? null;
if (!$accessToken) {
    die('Could not complete LinkedIn sign-in (token exchange failed).');
}

// Step 2: fetch the person's OpenID profile
// Note: LinkedIn's free "Sign In with LinkedIn using OpenID Connect" product
// only reliably returns name, email and photo — a full profile with
// headline/current position needs LinkedIn's separately-approved
// Marketing/Talent products, which are not self-serve.
$profileResponse = http_get_json('https://api.linkedin.com/v2/userinfo', [
    'Authorization: Bearer ' . $accessToken,
]);
$profile = $profileResponse['body'];

$userId = find_or_create_social_user(
    'linkedin',
    (string) ($profile['sub'] ?? ''),
    (string) ($profile['email'] ?? ''),
    (string) ($profile['given_name'] ?? ''),
    (string) ($profile['family_name'] ?? ''),
    (string) ($profile['picture'] ?? '')
);

log_in_user($userId);
flash('success', 'Signed in with LinkedIn.');
redirect(post_login_redirect_path(current_user()));
