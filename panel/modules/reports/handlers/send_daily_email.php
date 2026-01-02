<?php
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\{Database, Mailer};

// Get yesterday's metrics
$date = date('Y-m-d', strtotime('-1 day'));

// Run daily report SQL queries
// ... (same as daily.php)

// Prepare email data
$emailData = [
    'summary_date' => date('F j, Y', strtotime($date)),
    'kpis' => [
        'New Candidates' => $metrics['new_candidates'],
        'Submissions' => $metrics['submissions'],
        'Placements' => $metrics['placements'],
        'Calls Logged' => $metrics['calls_logged']
    ],
    'recruiter_breakdown' => $recruiterBreakdown,
    'view_report_url' => 'https://yourdomain.com/panel/modules/reports/daily.php?date=' . $date
];

// Send email to admin/managers
$managers = getAdminOrManagerEmails(); // Your function

foreach ($managers as $managerEmail) {
    Mailer::send(
        $managerEmail,
        "Daily Recruitment Summary - " . $date,
        'daily_summary_email',
        $emailData
    );
}