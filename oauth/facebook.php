<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/oauth_helper.php';

if (!FACEBOOK_CLIENT_ID) {
    die('Facebook login is not configured yet. Add FACEBOOK_CLIENT_ID / FACEBOOK_CLIENT_SECRET to config/config.php.');
}

$state = oauth_state_generate('facebook');

$params = http_build_query([
    'client_id' => FACEBOOK_CLIENT_ID,
    'redirect_uri' => base_url('oauth/facebook_callback.php'),
    'state' => $state,
    'scope' => 'email,public_profile',
]);

redirect('https://www.facebook.com/v19.0/dialog/oauth?' . $params);
