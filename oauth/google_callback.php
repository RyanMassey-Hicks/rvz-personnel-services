<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/http.php';
require __DIR__ . '/../includes/oauth_helper.php';

if (!oauth_state_verify('google', $_GET['state'] ?? null)) {
    die('Login request could not be verified (invalid state). Please try signing in again.');
}

if (isset($_GET['error'])) {
    flash('danger', 'Google sign-in was cancelled.');
    redirect('/login.php');
}

$code = $_GET['code'] ?? '';
if (!$code) {
    die('Missing authorization code from Google.');
}

// Step 1: exchange the code for an access token
$tokenResponse = http_post_form('https://oauth2.googleapis.com/token', [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => base_url('oauth/google_callback.php'),
    'grant_type' => 'authorization_code',
]);

$accessToken = $tokenResponse['body']['access_token'] ?? null;
if (!$accessToken) {
    die('Could not complete Google sign-in (token exchange failed).');
}

// Step 2: fetch the person's profile
$profileResponse = http_get_json('https://www.googleapis.com/oauth2/v3/userinfo', [
    'Authorization: Bearer ' . $accessToken,
]);
$profile = $profileResponse['body'];

$userId = find_or_create_social_user(
    'google',
    (string) ($profile['sub'] ?? ''),
    (string) ($profile['email'] ?? ''),
    (string) ($profile['given_name'] ?? ''),
    (string) ($profile['family_name'] ?? ''),
    (string) ($profile['picture'] ?? '')
);

log_in_user($userId);
flash('success', 'Signed in with Google.');
redirect(post_login_redirect_path(current_user()));
