<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\AuthMiddleware;

class RecruiterController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
    }

    public function index()
    {
        $db = Database::getPDO();

        // Fetch all Recruiters with Stats
        $sql = "
            SELECT u.id, u.name, u.email, u.avatar_path,
                   (SELECT COUNT(*) FROM jobs j WHERE j.recruiter_id = u.id AND j.status='open') as active_jobs,
                   (SELECT COUNT(*) FROM submissions s WHERE s.recruiter_id = u.id) as total_submissions,
                   (SELECT MAX(created_at) FROM submissions s WHERE s.recruiter_id = u.id) as last_activity
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE r.slug = 'recruiter' AND u.is_active = 1
            ORDER BY total_submissions DESC
        ";

        $recruiters = $db->query($sql)->fetchAll();

        $this->view('recruiters.index', [
            'recruiters' => $recruiters
        ]);
    }
}
