<?php
/**
 * Status Badge Component
 * Usage: Include this file where $status or $app['status'] is available
 */

$statusColors = [
    'applied' => 'secondary',
    'screening' => 'info',
    'screening_passed' => 'primary',
    'pending_approval' => 'warning',
    'approved' => 'success',
    'submitted' => 'primary',
    'shortlisted' => 'info',
    'interviewing' => 'warning',
    'interview_passed' => 'success',
    'offered' => 'success',
    'offer_accepted' => 'success',
    'placed' => 'success',
    'rejected' => 'danger',
    'withdrawn' => 'secondary',
    'on_hold' => 'warning'
];

$statusLabels = [
    'applied' => 'Applied',
    'screening' => 'Screening',
    'screening_passed' => 'Screening Passed',
    'pending_approval' => 'Pending Approval',
    'approved' => 'Approved',
    'submitted' => 'Submitted to Client',
    'shortlisted' => 'Shortlisted',
    'interviewing' => 'Interviewing',
    'interview_passed' => 'Interview Passed',
    'offered' => 'Offer Extended',
    'offer_accepted' => 'Offer Accepted',
    'placed' => '✓ Placed',
    'rejected' => 'Rejected',
    'withdrawn' => 'Withdrawn',
    'on_hold' => 'On Hold'
];

$currentStatus = $status ?? $app['status'] ?? 'applied';
$badgeColor = $statusColors[$currentStatus] ?? 'secondary';
$statusLabel = $statusLabels[$currentStatus] ?? ucfirst($currentStatus);
?>

<span class="badge bg-<?php echo $badgeColor; ?>">
    <?php echo $statusLabel; ?>
</span>