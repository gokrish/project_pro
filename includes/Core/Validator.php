<?php
namespace ProConsultancy\Core;

/**
 * Input Validator Class
 * Validates and sanitizes user input
 * 
 * @version 5.0
 */
class Validator {
    private array $data = [];
    private array $errors = [];
    private array $rules = [];
    
    public function __construct(array $data) {
        $this->data = $data;
    }
    
    /**
     * Validate data against rules
     */
    public function validate(array $rules): bool {
        $this->rules = $rules;
        $this->errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            
            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Apply validation rule
     */
    private function applyRule(string $field, string $rule): void {
        // Parse rule and parameters
        $params = [];
        if (strpos($rule, ':') !== false) {
            [$rule, $paramString] = explode(':', $rule, 2);
            $params = explode(',', $paramString);
        }
        
        $value = $this->data[$field] ?? null;
        $label = ucfirst(str_replace('_', ' ', $field));
        
        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, "{$label} is required");
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "{$label} must be a valid email address");
                }
                break;
                
            case 'min':
                $min = $params[0] ?? 0;
                if (!empty($value) && strlen($value) < $min) {
                    $this->addError($field, "{$label} must be at least {$min} characters");
                }
                break;
                
            case 'max':
                $max = $params[0] ?? 0;
                if (!empty($value) && strlen($value) > $max) {
                    $this->addError($field, "{$label} must not exceed {$max} characters");
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, "{$label} must be a number");
                }
                break;
                
            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, "{$label} must be an integer");
                }
                break;
                
            case 'alpha':
                if (!empty($value) && !ctype_alpha(str_replace(' ', '', $value))) {
                    $this->addError($field, "{$label} must contain only letters");
                }
                break;
                
            case 'alphanumeric':
                if (!empty($value) && !ctype_alnum(str_replace(' ', '', $value))) {
                    $this->addError($field, "{$label} must contain only letters and numbers");
                }
                break;
                
            case 'in':
                if (!empty($value) && !in_array($value, $params)) {
                    $this->addError($field, "{$label} must be one of: " . implode(', ', $params));
                }
                break;
                
            case 'unique':
                if (!empty($value)) {
                    [$table, $column] = explode(',', $params[0]);
                    $excludeId = $params[1] ?? null;
                    
                    if (!$this->isUnique($table, $column, $value, $excludeId)) {
                        $this->addError($field, "{$label} already exists");
                    }
                }
                break;
                
            case 'exists':
                if (!empty($value)) {
                    [$table, $column] = explode(',', $params[0]);
                    
                    if (!$this->exists($table, $column, $value)) {
                        $this->addError($field, "{$label} does not exist");
                    }
                }
                break;
                
            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    $this->addError($field, "{$label} must be a valid date");
                }
                break;
                
            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "{$label} must be a valid URL");
                }
                break;
                
            case 'phone':
                if (!empty($value) && !preg_match('/^[\+]?[(]?[0-9]{1,4}[)]?[-\s\.]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{1,9}$/', $value)) {
                    $this->addError($field, "{$label} must be a valid phone number");
                }
                break;
                
            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if ($value !== ($this->data[$confirmField] ?? null)) {
                    $this->addError($field, "{$label} confirmation does not match");
                }
                break;
                
            case 'same':
                $otherField = $params[0];
                if ($value !== ($this->data[$otherField] ?? null)) {
                    $otherLabel = ucfirst(str_replace('_', ' ', $otherField));
                    $this->addError($field, "{$label} must match {$otherLabel}");
                }
                break;
                
            case 'different':
                $otherField = $params[0];
                if ($value === ($this->data[$otherField] ?? null)) {
                    $otherLabel = ucfirst(str_replace('_', ' ', $otherField));
                    $this->addError($field, "{$label} must be different from {$otherLabel}");
                }
                break;
        }
    }
    
    /**
     * Check if value is unique in database
     */
    private function isUnique(string $table, string $column, $value, $excludeId = null): bool {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?";
            $params = [$value];
            $types = 's';
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
                $types .= 'i';
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            return $row['count'] == 0;
            
        } catch (\Exception $e) {
            Logger::getInstance()->error('Unique validation failed', [
                'table' => $table,
                'column' => $column,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Check if value exists in database
     */
    private function exists(string $table, string $column, $value): bool {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?");
            $stmt->bind_param("s", $value);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            return $row['count'] > 0;
            
        } catch (\Exception $e) {
            Logger::getInstance()->error('Exists validation failed', [
                'table' => $table,
                'column' => $column,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Add validation error
     */
    private function addError(string $field, string $message): void {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Check if validation passed
     */
    public function passes(): bool {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails(): bool {
        return !empty($this->errors);
    }
    
    /**
     * Get all errors
     */
    public function errors(): array {
        return $this->errors;
    }
    
    /**
     * Get first error for field
     */
    public function first(string $field): ?string {
        return $this->errors[$field][0] ?? null;
    }
    
    /**
     * Get validated data
     */
    public function validated(): array {
        $validated = [];
        
        foreach (array_keys($this->rules) as $field) {
            if (isset($this->data[$field])) {
                $validated[$field] = $this->data[$field];
            }
        }
        
        return $validated;
    }
    
    /**
     * Sanitize input
     */
    public static function sanitize($value, string $type = 'string') {
        if (is_array($value)) {
            return array_map(fn($v) => self::sanitize($v, $type), $value);
        }
        
        return match($type) {
            'string' => htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8'),
            'email' => filter_var(trim($value), FILTER_SANITIZE_EMAIL),
            'url' => filter_var(trim($value), FILTER_SANITIZE_URL),
            'int' => filter_var($value, FILTER_SANITIZE_NUMBER_INT),
            'float' => filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            default => trim($value)
        };
    }
}