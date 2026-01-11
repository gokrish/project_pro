<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\AuthMiddleware;

class ApplicationController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
    }

    public function store()
    {
        $token = $this->input('csrf_token');
        if (!Session::verifyCsrfToken($token))
            die("Invalid CSRF");

        $candidateId = $this->input('candidate_id');
        $jobId = $this->input('job_id');
        $status = 'applied';

        $db = Database::getPDO();

        // Check if already applied
        $check = $db->prepare("SELECT id FROM candidate_jobs WHERE candidate_id = ? AND job_id = ?");
        $check->execute([$candidateId, $jobId]);

        if ($check->fetch()) {
            // Already exists, maybe redirect with message
            $this->redirect("/candidates/$candidateId");
            return;
        }

        $stmt = $db->prepare("INSERT INTO candidate_jobs (candidate_id, job_id, status) VALUES (?, ?, ?)");
        $stmt->execute([$candidateId, $jobId, $status]);

        $this->redirect("/candidates/$candidateId");
    }

    public function updateStatus()
    {
        $token = $this->input('csrf_token');
        if (!Session::verifyCsrfToken($token))
            die("Invalid CSRF");

        $appId = $this->input('application_id');
        $status = $this->input('status');
        $jobId = $this->input('job_id'); // For rectirect

        $validStatuses = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'];
        if (!in_array($status, $validStatuses))
            die("Invalid Status");

        $db = Database::getPDO();
        $stmt = $db->prepare("UPDATE candidate_jobs SET status = ? WHERE id = ?");
        $stmt->execute([$status, $appId]);

        // Redirect back to job or candidate. Assuming from Job View for now.
        if ($jobId) {
            $this->redirect("/jobs/$jobId");
        } else {
            $this->redirect("/jobs");
        }
    }

    // Helper to delete application
    public function delete($id)
    {
        // TODO: Implement safe delete if needed
    }
}
