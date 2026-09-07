<?php

function render_job_form_fields(array $values): void
{
    $types = ['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'internship' => 'Internship'];
    ?>
    <div class="mb-3">
        <label class="form-label">Job title</label>
        <input type="text" name="title" class="form-control" required value="<?= h($values['title']) ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" rows="8" class="form-control" required><?= h($values['description']) ?></textarea>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control" required value="<?= h($values['location']) ?>">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Employment type</label>
            <select name="employment_type" class="form-select">
                <?php foreach ($types as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $values['employment_type'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Industry</label>
            <select name="industry_id" class="form-select">
                <option value="">Select an industry</option>
                <?php foreach (db()->query('SELECT id, name FROM industries ORDER BY name')->fetchAll() as $ind): ?>
                    <option value="<?= (int) $ind['id'] ?>" <?= (int) ($values['industry_id'] ?? 0) === (int) $ind['id'] ? 'selected' : '' ?>><?= h($ind['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Use RVZ Response Handling for this job? <span class="badge rvz-badge-soft">Optional service</span></label>
        <div class="form-check">
            <input type="checkbox" name="use_response_handling" id="use_response_handling" class="form-check-input" <?= !empty($values['use_response_handling']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="use_response_handling">Let RVZ's response handling team advertise, screen and shortlist candidates for this job</label>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Salary min (optional)</label>
            <input type="number" name="salary_min" class="form-control" value="<?= h((string) ($values['salary_min'] ?? '')) ?>">
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Salary max (optional)</label>
            <input type="number" name="salary_max" class="form-control" value="<?= h((string) ($values['salary_max'] ?? '')) ?>">
        </div>
    </div>
    <div class="form-check mb-3">
        <input type="checkbox" name="is_remote" id="is_remote" class="form-check-input" <?= $values['is_remote'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="is_remote">This role is remote</label>
    </div>
    <?php if (array_key_exists('is_open', $values)): ?>
    <div class="form-check mb-3">
        <input type="checkbox" name="is_open" id="is_open" class="form-check-input" <?= $values['is_open'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="is_open">Job is open for applications</label>
    </div>
    <?php endif; ?>
    <?php
}
