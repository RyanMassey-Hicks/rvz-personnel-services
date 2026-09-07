<?php
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');

$staticPages = [
    'index.php', 'jobs.php', 'pricing.php', 'privacy-policy.php', 'disclaimer.php', 'contact-us.php',
    'terms-and-conditions.php', 'paia.php', 'cookie-policy.php', 'copyright-notice.php', 'acceptable-use.php',
    'login.php', 'become_recruiter.php',
];

$jobs = db()->query('SELECT id, created_at FROM jobs WHERE is_open = 1 ORDER BY created_at DESC')->fetchAll();
$companies = db()->query(
    'SELECT DISTINCT companies.id FROM companies JOIN jobs ON jobs.company_id = companies.id WHERE jobs.is_open = 1'
)->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($staticPages as $page): ?>
    <url><loc><?= h(base_url($page)) ?></loc><changefreq>weekly</changefreq></url>
<?php endforeach; ?>
<?php foreach ($jobs as $job): ?>
    <url>
        <loc><?= h(base_url('job.php?id=' . $job['id'])) ?></loc>
        <lastmod><?= h(date('Y-m-d', strtotime($job['created_at']))) ?></lastmod>
        <changefreq>daily</changefreq>
    </url>
<?php endforeach; ?>
<?php foreach ($companies as $company): ?>
    <url><loc><?= h(base_url('company_profile.php?id=' . $company['id'])) ?></loc><changefreq>weekly</changefreq></url>
<?php endforeach; ?>
</urlset>
