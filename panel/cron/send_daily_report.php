<?php
/**
 * Daily Report Email Cron Job
 * Send daily summary to managers
 * 
 * Run daily at 8 AM:
 * 0 8 * * * php /panel/cron/send_daily_report.php
 */

require_once __DIR__ . '/../modules/_common.php';

use ProConsultancy\Core\{Database, Mailer, Logger};

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get yesterday's date
    $date = date('Y-m-d', strtotime('-1 day'));
    $displayDate = date('F j, Y', strtotime($date));
    
    // ============================================================================
    // GET DAILY METRICS
    // ============================================================================
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN DATE(c.created_at) = ? THEN c.candidate_code END) as new_candidates,
            COUNT(DISTINCT CASE WHEN DATE(c.last_contacted_date) = ? THEN c.candidate_code END) as contacted,
            COUNT(DISTINCT CASE 
                WHEN DATE(sl.changed_at) = ? AND sl.new_status = 'placed' 
                THEN sl.candidate_code 
            END) as placements,
            COUNT(DISTINCT CASE WHEN DATE(s.submitted_at) = ? THEN s.submission_code END) as submissions,
            COUNT(DISTINCT CASE 
                WHEN DATE(comm.contacted_at) = ? AND comm.communication_type = 'Call' 
                THEN comm.id 
            END) as calls_logged
        FROM candidates c
        LEFT JOIN candidate_status_log sl ON c.candidate_code = sl.candidate_code
        LEFT JOIN submissions s ON c.candidate_code = s.candidate_code AND s.deleted_at IS NULL
        LEFT JOIN candidate_communications comm ON c.candidate_code = comm.candidate_code
        WHERE c.deleted_at IS NULL
    ");
    
    $stmt->bind_param("sssss", $date, $date, $date, $date, $date);
    $stmt->execute();
    $metrics = $stmt->get_result()->fetch_assoc();
    
    // ============================================================================
    // GET RECRUITER BREAKDOWN
    // ============================================================================
    
    $stmt = $conn->prepare("
        SELECT 
            u.name as recruiter_name,
            COUNT(DISTINCT CASE WHEN DATE(c.created_at) = ? THEN c.candidate_code END) as candidates_added,
            COUNT(DISTINCT CASE 
                WHEN DATE(comm.contacted_at) = ? AND comm.communication_type = 'Call' 
                THEN comm.id 
            END) as calls_logged,
            COUNT(DISTINCT CASE WHEN DATE(s.submitted_at) = ? THEN s.submission_code END) as submissions_created
        FROM users u
        LEFT JOIN candidates c ON u.user_code = c.created_by AND c.deleted_at IS NULL
        LEFT JOIN candidate_communications comm ON u.user_code = comm.contacted_by
        LEFT JOIN submissions s ON u.user_code = s.submitted_by AND s.deleted_at IS NULL
        WHERE u.level IN ('recruiter', 'senior_recruiter', 'manager')
        AND u.is_active = 1
        GROUP BY u.user_code, u.name
        HAVING candidates_added > 0 OR calls_logged > 0 OR submissions_created > 0
        ORDER BY submissions_created DESC, candidates_added DESC
    ");
    
    $stmt->bind_param("sss", $date, $date, $date);
    $stmt->execute();
    $recruiterBreakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // ============================================================================
    // GET MANAGER EMAILS
    // ============================================================================
    
    $stmt = $conn->prepare("
        SELECT email, name 
        FROM users 
        WHERE level IN ('manager', 'admin', 'super_admin')
        AND is_active = 1
        AND email IS NOT NULL
        AND email != ''
    ");
    
    $stmt->execute();
    $managers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($managers)) {
        Logger::getInstance()->warning('No managers found for daily report');
        exit;
    }
    
    // ============================================================================
    // PREPARE EMAIL DATA
    // ============================================================================
    
    $emailData = [
        'summary_date' => $displayDate,
        'kpis' => [
            'New Candidates' => $metrics['new_candidates'],
            'Contacted' => $metrics['contacted'],
            'Submissions' => $metrics['submissions'],
            'Placements' => $metrics['placements'],
            'Calls Logged' => $metrics['calls_logged']
        ],
        'recruiter_breakdown' => $recruiterBreakdown,
        'followup_table_html' => '', // Optional: add follow-up data
        'view_report_url' => 'APP_URL/panel/modules/reports/daily.php?date=' . $date
    ];
    
    // ============================================================================
    // SEND EMAILS
    // ============================================================================
    
    $sent = 0;
    $failed = 0;
    
    foreach ($managers as $manager) {
        $success = Mailer::send(
            $manager['email'],
            "Daily Recruitment Summary - " . $displayDate,
            'daily_summary_email', // Template name
            $emailData
        );
        
        if ($success) {
            $sent++;
            Logger::getInstance()->info('Daily report sent', [
                'to' => $manager['email'],
                'date' => $date
            ]);
        } else {
            $failed++;
            Logger::getInstance()->error('Daily report failed', [
                'to' => $manager['email'],
                'date' => $date
            ]);
        }
    }
    
    // Log summary
    Logger::getInstance()->info('Daily report cron completed', [
        'date' => $date,
        'sent' => $sent,
        'failed' => $failed,
        'total_managers' => count($managers)
    ]);
    
    echo "Daily report sent: {$sent} succeeded, {$failed} failed\n";
    
} catch (Exception $e) {
    Logger::getInstance()->error('Daily report cron failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}