<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/oauth_helper.php';

if (!GOOGLE_CLIENT_ID) {
    die('Google login is not configured yet. Add GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET to config/config.php.');
}

$state = oauth_state_generate('google');

$params = http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => base_url('oauth/google_callback.php'),
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'access_type' => 'online',
    'prompt' => 'select_account',
]);

redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
