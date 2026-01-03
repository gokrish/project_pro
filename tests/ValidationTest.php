<?php
namespace ProConsultancy\Tests\Core;

use ProConsultancy\Tests\TestCase;
use ProConsultancy\Core\Validator;

class ValidatorTest extends TestCase
{
    public function testEmailValidation()
    {
        $validator = new Validator([
            'email' => 'test@example.com'
        ]);
        
        $validator->email('email');
        
        $this->assertTrue($validator->validate());
        $this->assertEmpty($validator->errors());
    }
    
    public function testInvalidEmail()
    {
        $validator = new Validator([
            'email' => 'not-an-email'
        ]);
        
        $validator->email('email');
        
        $this->assertFalse($validator->validate());
        $this->assertNotEmpty($validator->errors());
    }
    
    public function testRequiredField()
    {
        $validator = new Validator([
            'name' => ''
        ]);
        
        $validator->required('name');
        
        $this->assertFalse($validator->validate());
    }
    
    public function testMinLength()
    {
        $validator = new Validator([
            'password' => '123'
        ]);
        
        $validator->minLength('password', 8);
        
        $this->assertFalse($validator->validate());
        $this->assertArrayHasKey('password', $validator->errors());
    }
}