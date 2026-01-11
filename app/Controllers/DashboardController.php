<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;

class DashboardController extends Controller
{
    public function index()
    {
        (new \App\Middleware\AuthMiddleware())->handle();

        // Simple stats query
        $db = Database::getPDO();

        $stats = [
            'jobs' => $db->query("SELECT COUNT(*) FROM jobs WHERE deleted_at IS NULL")->fetchColumn(),
            'candidates' => $db->query("SELECT COUNT(*) FROM candidates")->fetchColumn(),
            'clients' => $db->query("SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL")->fetchColumn(),
        ];

        $this->view('dashboard.index', [
            'user' => [
                'name' => Session::get('user_name'),
                'role' => Session::get('role_id') // 1=admin, 2=recruiter
            ],
            'stats' => $stats
        ]);
    }
}
