<?php

use App\Core\Router;

// Define Routes
// We will register controllers here as they are created

// Example: Router::get('/', [HomeController::class, 'index']);

// Auth Routes
Router::get('/login', [App\Controllers\AuthController::class, 'loginForm']);
Router::post('/login', [App\Controllers\AuthController::class, 'login']);
Router::get('/logout', [App\Controllers\AuthController::class, 'logout']);

// Dashboard
Router::get('/', [App\Controllers\DashboardController::class, 'index']);

// Jobs
Router::get('/jobs', [App\Controllers\JobController::class, 'index']);
Router::get('/jobs/create', [App\Controllers\JobController::class, 'create']);
Router::post('/jobs', [App\Controllers\JobController::class, 'store']);
Router::get('/jobs/{id}/edit', [App\Controllers\JobController::class, 'edit']);
Router::post('/jobs/{id}/update', [App\Controllers\JobController::class, 'update']);
Router::get('/jobs/{id}', [App\Controllers\JobController::class, 'show']);

// Clients
Router::get('/clients', [App\Controllers\ClientController::class, 'index']);
Router::get('/clients/create', [App\Controllers\ClientController::class, 'create']);
Router::post('/clients', [App\Controllers\ClientController::class, 'store']);

// Candidates
Router::get('/candidates', [App\Controllers\CandidateController::class, 'index']);
Router::get('/candidates/create', [App\Controllers\CandidateController::class, 'create']);
Router::post('/candidates', [App\Controllers\CandidateController::class, 'store']);
Router::get('/candidates/{id}', [App\Controllers\CandidateController::class, 'show']);
Router::get('/candidates/{id}/edit', [App\Controllers\CandidateController::class, 'edit']);
Router::post('/candidates/{id}/update', [App\Controllers\CandidateController::class, 'update']);

// Applications (Job Assignments)
Router::post('/applications', [App\Controllers\ApplicationController::class, 'store']);
Router::post('/applications/status', [App\Controllers\ApplicationController::class, 'updateStatus']);

// CV Inbox
Router::get('/inbox', [App\Controllers\InboxController::class, 'index']);
Router::post('/inbox/upload', [App\Controllers\InboxController::class, 'upload']);
Router::get('/inbox/convert/{id}', [App\Controllers\InboxController::class, 'convert']);

// User Profile
Router::get('/profile', [App\Controllers\ProfileController::class, 'edit']);
Router::post('/profile/update', [App\Controllers\ProfileController::class, 'update']);

// Submissions (Enterprise)
Router::get('/submissions/create', [App\Controllers\SubmissionController::class, 'create']);
Router::post('/submissions', [App\Controllers\SubmissionController::class, 'store']);
Router::get('/submissions/{id}', [App\Controllers\SubmissionController::class, 'show']);
// Reports
Router::get('/reports', [App\Controllers\ReportsController::class, 'index']);

// Recruiters
Router::get('/recruiters', [App\Controllers\RecruiterController::class, 'index']);
