<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Services\AuthService;

class AuthController extends Controller
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function loginForm()
    {
        // If already logged in, redirect to dashboard
        if (Session::has('user_id')) {
            $this->redirect('/');
        }

        $this->view('auth.login', [
            'csrf_token' => Session::generateCsrfToken()
        ]);
    }

    public function login()
    {
        $email = $this->input('email');
        $password = $this->input('password');
        $token = $this->input('csrf_token');

        if (!Session::verifyCsrfToken($token)) {
            die("Invalid CSRF Token");
        }

        if ($this->auth->login($email, $password)) {
            $this->redirect('/');
        } else {
            $this->view('auth.login', [
                'error' => 'Invalid credentials',
                'csrf_token' => Session::generateCsrfToken(),
                'old_email' => $email
            ]);
        }
    }

    public function logout()
    {
        $this->auth->logout();
        $this->redirect('/login');
    }
}
