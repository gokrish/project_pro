<?php
// Expected variables:
// $searchAction (url to submit to)
// $query (current search term)
// $filters (array of additional filter inputs/selects in HTML)
?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form action="<?= $searchAction ?>" method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-bold">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search">🔍</i></span>
                    <input type="text" name="q" class="form-control"
                        placeholder="Keywords (e.g. PHP, Sales, Manager)..."
                        value="<?= htmlspecialchars($query ?? '') ?>">
                </div>
            </div>

            <?php if (isset($filtersHtml)): ?>
                <?= $filtersHtml ?>
            <?php endif; ?>

            <div class="col-md-auto">
                <button type="submit" class="btn btn-primary px-4">Search</button>
                <?php if (!empty($query) || !empty($_GET['status']) || !empty($_GET['client_id'])): ?>
                    <a href="<?= $searchAction ?>" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>