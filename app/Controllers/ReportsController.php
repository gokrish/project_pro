<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\AuthMiddleware;

class ReportsController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
        // TODO: Ensure only Admins can access reports? Or Managers.
    }

    public function index()
    {
        $db = Database::getPDO();

        // 1. Funnel Report (Overall)
        // Count submissions by status
        $funnelData = $db->query("
            SELECT status, COUNT(*) as count 
            FROM submissions 
            GROUP BY status
            ORDER BY FIELD(status, 'draft', 'submitted', 'client_review', 'interview', 'offer', 'placed', 'rejected')
        ")->fetchAll(\PDO::FETCH_KEY_PAIR); // returns ['submitted' => 5, 'interview' => 2]

        // 2. Recruiter Performance
        // Submissions, Interviews, Placements per Recruiter
        $recruiterStats = $db->query("
            SELECT u.name, 
                   COUNT(s.id) as total_submissions,
                   SUM(CASE WHEN s.status = 'interview' THEN 1 ELSE 0 END) as interviews,
                   SUM(CASE WHEN s.status = 'placed' THEN 1 ELSE 0 END) as placements
            FROM users u
            LEFT JOIN submissions s ON u.id = s.recruiter_id
            WHERE u.is_active = 1
            GROUP BY u.id
            ORDER BY total_submissions DESC
        ")->fetchAll();

        // 3. Client Activity
        // Active Jobs, Total Submissions
        $clientStats = $db->query("
            SELECT c.company_name,
                   (SELECT COUNT(*) FROM jobs j WHERE j.client_id = c.id AND j.status = 'open') as open_jobs,
                   COUNT(s.id) as total_submissions,
                   MAX(s.created_at) as last_activity
            FROM clients c
            LEFT JOIN submissions s ON c.id = s.client_id
            GROUP BY c.id
            ORDER BY total_submissions DESC
            LIMIT 10
        ")->fetchAll();

        // 4. Job Performance (Time to Fill) - difficult without 'filled_at' date tracking in jobs explicitly or history.
        // For now, let's just show top jobs by submission count
        $jobStats = $db->query("
            SELECT j.title, c.company_name, COUNT(s.id) as submission_count
            FROM jobs j
            JOIN clients c ON j.client_id = c.id
            LEFT JOIN submissions s ON j.id = s.job_id
            WHERE j.status = 'open'
            GROUP BY j.id
            ORDER BY submission_count DESC
            LIMIT 10
        ")->fetchAll();

        $this->view('reports.index', [
            'funnelData' => $funnelData,
            'recruiterStats' => $recruiterStats,
            'clientStats' => $clientStats,
            'jobStats' => $jobStats
        ]);
    }
}
