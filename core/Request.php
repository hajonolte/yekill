<?php
/**
 * HTTP Request Handler
 * Manages incoming HTTP requests and data
 */

declare(strict_types=1);

namespace Core;

class Request
{
    private array $routeParams = [];
    
    /**
     * Get HTTP method
     */
    public function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
    
    /**
     * Get request path
     */
    public function getPath(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }
        
        return $path;
    }
    
    /**
     * Get query parameters
     */
    public function getQuery(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }
        
        return $_GET[$key] ?? $default;
    }
    
    /**
     * Get POST data
     */
    public function getPost(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_POST;
        }
        
        return $_POST[$key] ?? $default;
    }
    
    /**
     * Get request body (for JSON requests)
     */
    public function getBody(): string
    {
        return file_get_contents('php://input');
    }
    
    /**
     * Get JSON data from request body
     */
    public function getJson(): ?array
    {
        $body = $this->getBody();
        if (empty($body)) {
            return null;
        }
        
        $data = json_decode($body, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }
    
    /**
     * Get all input data (GET, POST, JSON)
     */
    public function all(): array
    {
        $data = array_merge($_GET, $_POST);
        
        $json = $this->getJson();
        if ($json) {
            $data = array_merge($data, $json);
        }
        
        return $data;
    }
    
    /**
     * Get specific input value
     */
    public function input(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        return $all[$key] ?? $default;
    }
    
    /**
     * Check if request has input
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }
    
    /**
     * Get uploaded files
     */
    public function getFiles(): array
    {
        return $_FILES;
    }
    
    /**
     * Get specific uploaded file
     */
    public function getFile(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }
    
    /**
     * Get request headers
     */
    public function getHeaders(): array
    {
        return getallheaders() ?: [];
    }
    
    /**
     * Get specific header
     */
    public function getHeader(string $name, string $default = ''): string
    {
        $headers = $this->getHeaders();
        return $headers[$name] ?? $headers[strtolower($name)] ?? $default;
    }
    
    /**
     * Check if request is AJAX
     */
    public function isAjax(): bool
    {
        return strtolower($this->getHeader('X-Requested-With')) === 'xmlhttprequest';
    }
    
    /**
     * Check if request is JSON
     */
    public function isJson(): bool
    {
        return str_contains($this->getHeader('Content-Type'), 'application/json');
    }
    
    /**
     * Get client IP address
     */
    public function getIp(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get user agent
     */
    public function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * Set route parameters
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }
    
    /**
     * Get route parameters
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }
    
    /**
     * Get specific route parameter
     */
    public function getRouteParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }
    
    /**
     * Validate input data
     */
    public function validate(array $rules): array
    {
        $data = $this->all();
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $ruleList = is_string($rule) ? explode('|', $rule) : $rule;
            
            foreach ($ruleList as $r) {
                if ($r === 'required' && empty($value)) {
                    $errors[$field][] = "The {$field} field is required.";
                } elseif ($r === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "The {$field} must be a valid email address.";
                } elseif (str_starts_with($r, 'min:') && !empty($value)) {
                    $min = (int)substr($r, 4);
                    if (strlen($value) < $min) {
                        $errors[$field][] = "The {$field} must be at least {$min} characters.";
                    }
                } elseif (str_starts_with($r, 'max:') && !empty($value)) {
                    $max = (int)substr($r, 4);
                    if (strlen($value) > $max) {
                        $errors[$field][] = "The {$field} may not be greater than {$max} characters.";
                    }
                }
            }
        }
        
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
        
        return $data;
    }
}

/**
 * Validation Exception
 */
class ValidationException extends \Exception
{
    private array $errors;
    
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct('Validation failed');
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
}
