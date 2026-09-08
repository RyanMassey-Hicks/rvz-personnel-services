<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/http.php';
require __DIR__ . '/../includes/oauth_helper.php';

if (!oauth_state_verify('facebook', $_GET['state'] ?? null)) {
    die('Login request could not be verified (invalid state). Please try signing in again.');
}

if (isset($_GET['error'])) {
    flash('danger', 'Facebook sign-in was cancelled.');
    redirect('/login.php');
}

$code = $_GET['code'] ?? '';
if (!$code) {
    die('Missing authorization code from Facebook.');
}

// Step 1: exchange the code for an access token
$tokenResponse = http_post_form('https://graph.facebook.com/v19.0/oauth/access_token', [
    'client_id' => FACEBOOK_CLIENT_ID,
    'client_secret' => FACEBOOK_CLIENT_SECRET,
    'redirect_uri' => base_url('oauth/facebook_callback.php'),
    'code' => $code,
]);

$accessToken = $tokenResponse['body']['access_token'] ?? null;
if (!$accessToken) {
    die('Could not complete Facebook sign-in (token exchange failed).');
}

// Step 2: fetch the person's profile (including a decent-size photo)
$profileResponse = http_get_json(
    'https://graph.facebook.com/me?fields=id,first_name,last_name,email,picture.width(200).height(200)&access_token=' . urlencode($accessToken)
);
$profile = $profileResponse['body'];

$avatarUrl = $profile['picture']['data']['url'] ?? '';

$userId = find_or_create_social_user(
    'facebook',
    (string) ($profile['id'] ?? ''),
    (string) ($profile['email'] ?? ''),
    (string) ($profile['first_name'] ?? ''),
    (string) ($profile['last_name'] ?? ''),
    (string) $avatarUrl
);

log_in_user($userId);
flash('success', 'Signed in with Facebook.');
redirect(post_login_redirect_path(current_user()));
