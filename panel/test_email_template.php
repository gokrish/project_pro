<?php
require_once __DIR__ . '/modules/_common.php';

use ProConsultancy\Core\Mailer;

$testEmail = 'your-email@example.com';

$result = Mailer::send(
    $testEmail,
    'Test Daily Report - {{summary_date}}',
    'daily_summary_email',
    [
        'summary_date' => date('F j, Y'),
        'kpis' => [
            'New Candidates' => 5,
            'Submissions' => 3,
            'Placements' => 1,
            'Calls Logged' => 12
        ],
        'recruiter_breakdown' => [
            ['name' => 'John Doe', 'candidates_added' => 3, 'calls_logged' => 5, 'submissions_created' => 2]
        ],
        'followup_table_html' => '',
        'view_report_url' => 'https://yourdomain.com/panel/modules/reports/daily.php'
    ]
);

if ($result) {
    echo "✅ Template email sent!\n";
} else {
    echo "❌ Failed to send template email.\n";
}