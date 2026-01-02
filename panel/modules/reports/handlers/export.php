<?php
/**
 * Report Export Handler
 * Export reports to CSV format
 * 
 * @version 5.0 - Production Ready
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\{Permission, Database, Auth};

// Check permission
Permission::require('reports', 'view_dashboard');

$user = Auth::user();
$userLevel = $user['level'] ?? 'user';
$isAdmin = in_array($userLevel, ['admin', 'super_admin', 'manager']);

$db = Database::getInstance();
$conn = $db->getConnection();

// Get report type
$report = filter_input(INPUT_GET, 'report', FILTER_SANITIZE_STRING);

// Route to appropriate export function
switch ($report) {
    case 'daily':
        exportDailyReport();
        break;
        
    case 'pipeline':
        exportPipelineReport();
        break;
        
    case 'performance':
        exportPerformanceReport();
        break;
        
    case 'followup':
        exportFollowupReport();
        break;
        
    default:
        header('Location: /panel/modules/reports/');
        exit;
}

/**
 * Export Daily Report
 */
function exportDailyReport() {
    global $conn, $isAdmin;
    
    $date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING) ?: date('Y-m-d');
    
    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="daily-report-' . $date . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, ['Daily Activity Report - ' . date('F j, Y', strtotime($date))]);
    fputcsv($output, ['']);
    fputcsv($output, ['Metric', 'Count']);
    
    // Get metrics (same query as daily.php)
    try {
        $userCode = Auth::userCode();
        $accessFilter = $isAdmin ? '' : " AND c.created_by = '$userCode'";
        
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT CASE WHEN DATE(c.created_at) = ? THEN c.candidate_code END) as new_candidates,
                COUNT(DISTINCT CASE WHEN DATE(c.last_contacted_date) = ? THEN c.candidate_code END) as contacted,
                COUNT(DISTINCT CASE 
                    WHEN DATE(sl.changed_at) = ? 
                    AND sl.new_status = 'qualified' 
                    THEN sl.candidate_code 
                END) as qualified,
                COUNT(DISTINCT CASE 
                    WHEN DATE(sl.changed_at) = ? 
                    AND sl.new_status = 'placed' 
                    THEN sl.candidate_code 
                END) as placements,
                COUNT(DISTINCT CASE 
                    WHEN DATE(s.submitted_at) = ? 
                    THEN s.submission_code 
                END) as submissions,
                COUNT(DISTINCT CASE 
                    WHEN DATE(comm.contacted_at) = ? 
                    AND comm.communication_type = 'Call' 
                    THEN comm.id 
                END) as calls_logged
            FROM candidates c
            LEFT JOIN candidate_status_log sl ON c.candidate_code = sl.candidate_code
            LEFT JOIN submissions s ON c.candidate_code = s.candidate_code AND s.deleted_at IS NULL
            LEFT JOIN candidate_communications comm ON c.candidate_code = comm.candidate_code
            WHERE c.deleted_at IS NULL $accessFilter
        ");
        
        $stmt->bind_param("ssssss", $date, $date, $date, $date, $date, $date);
        $stmt->execute();
        $metrics = $stmt->get_result()->fetch_assoc();
        
        // Write data
        fputcsv($output, ['New Candidates', $metrics['new_candidates']]);
        fputcsv($output, ['Contacted', $metrics['contacted']]);
        fputcsv($output, ['Qualified', $metrics['qualified']]);
        fputcsv($output, ['Submissions', $metrics['submissions']]);
        fputcsv($output, ['Placements', $metrics['placements']]);
        fputcsv($output, ['Calls Logged', $metrics['calls_logged']]);
        
    } catch (Exception $e) {
        fputcsv($output, ['Error', 'Unable to fetch data']);
    }
    
    fclose($output);
    exit;
}

/**
 * Export Pipeline Report
 */
function exportPipelineReport() {
    global $conn, $isAdmin;
    
    $assignedTo = filter_input(INPUT_GET, 'assigned_to', FILTER_SANITIZE_STRING);
    $leadType = filter_input(INPUT_GET, 'lead_type', FILTER_SANITIZE_STRING);
    
    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pipeline-report-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, ['Candidate Pipeline Report - ' . date('F j, Y')]);
    fputcsv($output, ['']);
    fputcsv($output, ['Status', 'Count', 'Hot Leads', 'Warm Leads', 'Cold Leads', 'Avg Days in Status', 'Unassigned']);
    
    // Get pipeline data
    try {
        $sql = "
            SELECT 
                c.status,
                COUNT(*) as count,
                COUNT(CASE WHEN c.lead_type = 'Hot' THEN 1 END) as hot_leads,
                COUNT(CASE WHEN c.lead_type = 'Warm' THEN 1 END) as warm_leads,
                COUNT(CASE WHEN c.lead_type = 'Cold' THEN 1 END) as cold_leads,
                AVG(DATEDIFF(CURDATE(), c.created_at)) as avg_days_in_status,
                COUNT(CASE WHEN c.assigned_to IS NULL THEN 1 END) as unassigned
            FROM candidates c
            WHERE c.deleted_at IS NULL
        ";
        
        $params = [];
        $types = '';
        
        if (!$isAdmin) {
            $sql .= " AND c.created_by = ?";
            $params[] = Auth::userCode();
            $types .= 's';
        }
        
        if ($assignedTo) {
            $sql .= " AND c.assigned_to = ?";
            $params[] = $assignedTo;
            $types .= 's';
        }
        
        if ($leadType) {
            $sql .= " AND c.lead_type = ?";
            $params[] = $leadType;
            $types .= 's';
        }
        
        $sql .= " GROUP BY c.status";
        $sql .= " ORDER BY FIELD(c.status, 'new', 'screening', 'qualified', 'active', 'placed', 'rejected', 'archived')";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $pipelineData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Write data
        foreach ($pipelineData as $stage) {
            fputcsv($output, [
                ucfirst($stage['status']),
                $stage['count'],
                $stage['hot_leads'],
                $stage['warm_leads'],
                $stage['cold_leads'],
                round($stage['avg_days_in_status']),
                $stage['unassigned']
            ]);
        }
        
    } catch (Exception $e) {
        fputcsv($output, ['Error', 'Unable to fetch data']);
    }
    
    fclose($output);
    exit;
}

/**
 * Export Performance Report
 */
function exportPerformanceReport() {
    global $conn, $isAdmin;
    
    $period = filter_input(INPUT_GET, 'period', FILTER_SANITIZE_STRING) ?: 'month';
    $specificUser = filter_input(INPUT_GET, 'user_code', FILTER_SANITIZE_STRING);
    
    // Calculate date range
    switch ($period) {
        case 'today':
            $dateFrom = date('Y-m-d');
            $dateTo = date('Y-m-d');
            break;
        case 'week':
            $dateFrom = date('Y-m-d', strtotime('monday this week'));
            $dateTo = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'month':
        default:
            $dateFrom = date('Y-m-01');
            $dateTo = date('Y-m-t');
            break;
    }
    
    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="performance-report-' . $period . '-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, ['Recruiter Performance Report - ' . ucfirst($period)]);
    fputcsv($output, ['Period: ' . date('F j', strtotime($dateFrom)) . ' - ' . date('F j, Y', strtotime($dateTo))]);
    fputcsv($output, ['']);
    fputcsv($output, ['Recruiter', 'Candidates Added', 'Calls Logged', 'Submissions', 'Placements', 'Contacts Converted', 'CVs Converted']);
    
    // Get performance data
    try {
        $sql = "
            SELECT 
                u.name as recruiter_name,
                COUNT(DISTINCT CASE 
                    WHEN DATE(c.created_at) BETWEEN ? AND ? 
                    THEN c.candidate_code 
                END) as candidates_added_period,
                COUNT(DISTINCT CASE 
                    WHEN comm.communication_type = 'Call' 
                    THEN comm.id 
                END) as calls_logged,
                COUNT(DISTINCT CASE 
                    WHEN DATE(s.submitted_at) BETWEEN ? AND ? 
                    THEN s.submission_code 
                END) as submissions_period,
                COUNT(DISTINCT CASE 
                    WHEN DATE(sl.changed_at) BETWEEN ? AND ? 
                    AND sl.new_status = 'placed' 
                    THEN sl.candidate_code 
                END) as placements_period,
                COUNT(DISTINCT CASE 
                    WHEN cont.converted_to_candidate IS NOT NULL 
                    THEN cont.contact_code 
                END) as contacts_converted,
                COUNT(DISTINCT CASE 
                    WHEN cv.converted_at IS NOT NULL 
                    THEN cv.id 
                END) as cv_converted
            FROM users u
            LEFT JOIN candidates c ON u.user_code = c.assigned_to AND c.deleted_at IS NULL
            LEFT JOIN submissions s ON u.user_code = s.submitted_by AND s.deleted_at IS NULL
            LEFT JOIN candidate_status_log sl ON u.user_code = sl.changed_by
            LEFT JOIN candidate_communications comm ON u.user_code = comm.contacted_by
            LEFT JOIN contacts cont ON u.user_code = cont.assigned_to AND cont.deleted_at IS NULL
            LEFT JOIN cv_inbox cv ON u.user_code = cv.assigned_to AND cv.deleted_at IS NULL
            WHERE u.is_active = 1 
            AND u.level IN ('recruiter', 'senior_recruiter', 'manager')
        ";
        
        $params = [$dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo];
        $types = 'ssssss';
        
        if ($specificUser) {
            $sql .= " AND u.user_code = ?";
            $params[] = $specificUser;
            $types .= 's';
        }
        
        if (!$isAdmin) {
            $sql .= " AND u.user_code = ?";
            $params[] = Auth::userCode();
            $types .= 's';
        }
        
        $sql .= " GROUP BY u.user_code, u.name";
        $sql .= " ORDER BY placements_period DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $performanceData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Write data
        foreach ($performanceData as $rec) {
            fputcsv($output, [
                $rec['recruiter_name'],
                $rec['candidates_added_period'],
                $rec['calls_logged'],
                $rec['submissions_period'],
                $rec['placements_period'],
                $rec['contacts_converted'],
                $rec['cv_converted']
            ]);
        }
        
    } catch (Exception $e) {
        fputcsv($output, ['Error', 'Unable to fetch data']);
    }
    
    fclose($output);
    exit;
}

/**
 * Export Follow-up Report
 */
function exportFollowupReport() {
    global $conn, $isAdmin;
    
    $urgency = filter_input(INPUT_GET, 'urgency', FILTER_SANITIZE_STRING);
    $assignedTo = filter_input(INPUT_GET, 'assigned_to', FILTER_SANITIZE_STRING);
    
    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="followup-report-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, ['Follow-up Dashboard - ' . date('F j, Y')]);
    fputcsv($output, ['']);
    fputcsv($output, ['Candidate Name', 'Phone', 'Email', 'Status', 'Lead Type', 'Follow-up Date', 'Days Until/Overdue', 'Assigned To']);
    
    // Get follow-up data
    try {
        $sql = "
            SELECT 
                c.candidate_name,
                c.phone,
                c.email,
                c.status,
                c.lead_type,
                c.follow_up_date,
                DATEDIFF(c.follow_up_date, CURDATE()) as days_until_followup,
                u.name as assigned_to_name,
                CASE 
                    WHEN c.follow_up_date < CURDATE() THEN 'overdue'
                    WHEN c.follow_up_date = CURDATE() THEN 'today'
                    WHEN c.follow_up_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'this_week'
                    ELSE 'later'
                END as urgency_level
            FROM candidates c
            LEFT JOIN users u ON c.assigned_to = u.user_code
            WHERE c.follow_up_date IS NOT NULL
            AND c.status NOT IN ('rejected', 'placed', 'archived')
            AND c.deleted_at IS NULL
        ";
        
        $params = [];
        $types = '';
        
        if (!$isAdmin) {
            $sql .= " AND c.assigned_to = ?";
            $params[] = Auth::userCode();
            $types .= 's';
        }
        
        if ($assignedTo) {
            $sql .= " AND c.assigned_to = ?";
            $params[] = $assignedTo;
            $types .= 's';
        }
        
        if ($urgency) {
            switch ($urgency) {
                case 'overdue':
                    $sql .= " AND c.follow_up_date < CURDATE()";
                    break;
                case 'today':
                    $sql .= " AND c.follow_up_date = CURDATE()";
                    break;
                case 'this_week':
                    $sql .= " AND c.follow_up_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                    break;
            }
        }
        
        $sql .= " ORDER BY c.follow_up_date ASC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $followups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Write data
        foreach ($followups as $followup) {
            $daysText = $followup['days_until_followup'] < 0 
                ? abs($followup['days_until_followup']) . ' days overdue'
                : $followup['days_until_followup'] . ' days';
                
            fputcsv($output, [
                $followup['candidate_name'],
                $followup['phone'],
                $followup['email'],
                ucfirst($followup['status']),
                $followup['lead_type'],
                date('Y-m-d', strtotime($followup['follow_up_date'])),
                $daysText,
                $followup['assigned_to_name'] ?? 'Unassigned'
            ]);
        }
        
    } catch (Exception $e) {
        fputcsv($output, ['Error', 'Unable to fetch data']);
    }
    
    fclose($output);
    exit;
}
