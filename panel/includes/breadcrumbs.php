<?php
/**
 * Breadcrumbs Component
 * Dynamic breadcrumb navigation
 * 
 * @version 5.0
 */

if (empty($breadcrumbs)) {
    return;
}
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb breadcrumb-style1">
        <li class="breadcrumb-item">
            <a href="/panel/dashboard.php">
                <i class="bx bx-home-alt"></i>
            </a>
        </li>
        <?php foreach ($breadcrumbs as $index => $crumb): ?>
            <?php if ($index === count($breadcrumbs) - 1): ?>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= htmlspecialchars($crumb['title']) ?>
                </li>
            <?php else: ?>
                <li class="breadcrumb-item">
                    <a href="<?= htmlspecialchars($crumb['url']) ?>">
                        <?= htmlspecialchars($crumb['title']) ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>