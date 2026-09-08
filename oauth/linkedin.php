<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/oauth_helper.php';

if (!LINKEDIN_CLIENT_ID) {
    die('LinkedIn login is not configured yet. Add LINKEDIN_CLIENT_ID / LINKEDIN_CLIENT_SECRET to config/config.php.');
}

$state = oauth_state_generate('linkedin');

$params = http_build_query([
    'response_type' => 'code',
    'client_id' => LINKEDIN_CLIENT_ID,
    'redirect_uri' => base_url('oauth/linkedin_callback.php'),
    'state' => $state,
    'scope' => 'openid profile email',
]);

redirect('https://www.linkedin.com/oauth/v2/authorization?' . $params);
