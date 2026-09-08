<?php
/** Shared tab bar for the recruiter settings hub (Branding / Teams / Embed / Site Settings). */
function render_settings_tabs(string $active): void
{
    $user = current_user();
    $tabs = [
        'branding' => ['label' => 'Branding', 'url' => 'company_branding.php'],
        'teams' => ['label' => 'Teams', 'url' => 'teams.php'],
        'embed' => ['label' => 'Embed Your Jobs', 'url' => 'embed_jobs.php'],
    ];
    if (is_privileged_recruiter($user)) {
        $tabs['site'] = ['label' => 'Site Settings', 'url' => 'site_settings.php'];
    }
    ?>
    <ul class="nav nav-tabs mb-4">
        <?php foreach ($tabs as $key => $tab): ?>
            <li class="nav-item">
                <a class="nav-link <?= $key === $active ? 'active' : '' ?>" href="<?= h(base_url($tab['url'])) ?>"><?= h($tab['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
}
