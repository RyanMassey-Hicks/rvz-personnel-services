<?php
/**
 * Shared logic for all three OAuth providers. Each provider's callback file
 * fetches its own profile data, then hands it to find_or_create_social_user()
 * here — this is the PHP equivalent of the Django project's
 * accounts/adapters.py.
 */

function oauth_state_generate(string $provider): string
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state_' . $provider] = $state;
    return $state;
}

function oauth_state_verify(string $provider, ?string $stateFromProvider): bool
{
    $expected = $_SESSION['oauth_state_' . $provider] ?? null;
    unset($_SESSION['oauth_state_' . $provider]);
    return $expected && $stateFromProvider && hash_equals($expected, $stateFromProvider);
}

/**
 * @param string $provider 'google' | 'linkedin' | 'facebook'
 * @param string $providerUserId The provider's own unique id for this person
 * @param string $email
 * @param string $firstName
 * @param string $lastName
 * @param string $avatarUrl
 * @return int the local users.id to log in
 */
function find_or_create_social_user(
    string $provider,
    string $providerUserId,
    string $email,
    string $firstName,
    string $lastName,
    string $avatarUrl
): int {
    $pdo = db();

    // 1. Already linked this exact provider account before? Log them straight in.
    $stmt = $pdo->prepare('SELECT user_id FROM oauth_accounts WHERE provider = ? AND provider_user_id = ?');
    $stmt->execute([$provider, $providerUserId]);
    if ($row = $stmt->fetch()) {
        return (int) $row['user_id'];
    }

    // 2. An account with this email already exists (e.g. they signed up with
    //    email/password before) — link this provider to it instead of duplicating.
    $userId = null;
    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($row = $stmt->fetch()) {
            $userId = (int) $row['id'];
        }
    }

    // 3. Brand new person — create their user + candidate profile.
    if ($userId === null) {
        $baseUsername = $provider . '_' . substr(preg_replace('/[^a-zA-Z0-9]/', '', $providerUserId), 0, 20);
        $username = $baseUsername;
        $suffix = 1;
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        do {
            $check->execute([$username]);
            if (!$check->fetch()) break;
            $username = $baseUsername . $suffix++;
        } while (true);

        // A social-only account may not have a real email from the provider;
        // fall back to a unique placeholder so the UNIQUE constraint holds.
        $emailToStore = $email !== '' ? $email : ($username . '@no-email.dittohire.local');

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, first_name, last_name, role, avatar_url, signed_up_via)
             VALUES (?, ?, ?, ?, "candidate", ?, ?)'
        );
        $stmt->execute([$username, $emailToStore, $firstName, $lastName, $avatarUrl, $provider]);
        $userId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO candidate_profiles (user_id) VALUES (?)');
        $stmt->execute([$userId]);
    } else {
        // Existing account: fill in an avatar if they didn't have one yet.
        $stmt = $pdo->prepare('UPDATE users SET avatar_url = IF(avatar_url = "", ?, avatar_url) WHERE id = ?');
        $stmt->execute([$avatarUrl, $userId]);
    }

    // 4. Link this provider account to the user for next time.
    $stmt = $pdo->prepare('INSERT INTO oauth_accounts (user_id, provider, provider_user_id) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $provider, $providerUserId]);

    return $userId;
}
