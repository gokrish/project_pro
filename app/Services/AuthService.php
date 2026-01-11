<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Session;

class AuthService
{
    public function login(string $email, string $password): bool
    {
        $db = Database::getPDO();
        $stmt = $db->prepare("SELECT id, password, role_id, name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Prevent Session Fixation
            session_regenerate_id(true);

            Session::put('user_id', $user['id']);
            Session::put('user_name', $user['name']);
            Session::put('role_id', $user['role_id']);
            return true;
        }

        return false;
    }

    public function logout(): void
    {
        Session::destroy();
    }

    public function user()
    {
        if (!Session::has('user_id'))
            return null;

        $db = Database::getPDO();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([Session::get('user_id')]);
        return $stmt->fetch();
    }
}
