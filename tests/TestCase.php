<?php
namespace ProConsultancy\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use ProConsultancy\Core\Database;

abstract class TestCase extends BaseTestCase
{
    protected $db;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::getInstance();
        
        // Start transaction for each test
        $this->db->getConnection()->begin_transaction();
    }
    
    protected function tearDown(): void
    {
        // Rollback transaction after each test
        $this->db->getConnection()->rollback();
        parent::tearDown();
    }
}