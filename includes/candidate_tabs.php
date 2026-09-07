<?php
/** Shared secondary nav for candidate-side pages, so moving between them doesn't mean hunting the dropdown each time. */
function render_candidate_tabs(string $active): void
{
    $tabs = [
        'profile' => ['label' => 'Profile', 'url' => 'profile.php'],
        'documents' => ['label' => 'Documents', 'url' => 'documents.php'],
        'cv' => ['label' => 'My CV', 'url' => 'my_cv.php'],
        'applications' => ['label' => 'My Applications', 'url' => 'my_applications.php'],
        'saved' => ['label' => 'Saved Jobs', 'url' => 'saved_jobs.php'],
    ];
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
