<?php
namespace ProConsultancy\Tests\Core;

use ProConsultancy\Tests\TestCase;
use ProConsultancy\Core\Auth;

class AuthTest extends TestCase
{
    public function testUserCanLogin()
    {
        // Create test user
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            INSERT INTO users (user_code, name, email, password, level, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        
        $userCode = 'TEST_' . uniqid();
        $name = 'Test User';
        $email = 'test@example.com';
        $password = password_hash('password123', PASSWORD_DEFAULT);
        $level = 'recruiter';
        
        $stmt->bind_param("sssss", $userCode, $name, $email, $password, $level);
        $stmt->execute();
        
        // Attempt login
        $result = Auth::attempt($email, 'password123');
        
        $this->assertTrue($result);
        $this->assertTrue(Auth::check());
        
        $user = Auth::user();
        $this->assertEquals($email, $user['email']);
        $this->assertEquals($name, $user['name']);
    }
    
    public function testLoginFailsWithWrongPassword()
    {
        $result = Auth::attempt('test@example.com', 'wrongpassword');
        $this->assertFalse($result);
        $this->assertFalse(Auth::check());
    }
    
    public function testUserCanLogout()
    {
        // Login first
        // ... (same as testUserCanLogin)
        
        Auth::logout();
        
        $this->assertFalse(Auth::check());
        $this->assertNull(Auth::user());
    }
}