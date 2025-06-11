<?php
/**
 * Configuration Management Class
 * Handles loading and accessing configuration values
 */

declare(strict_types=1);

namespace Core;

class Config
{
    private array $config = [];
    
    public function __construct()
    {
        $this->loadConfig();
    }
    
    /**
     * Get configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Set configuration value
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
    
    /**
     * Load configuration from files
     */
    private function loadConfig(): void
    {
        $configDir = APP_ROOT . '/config';
        
        // Load main configuration
        $mainConfig = $configDir . '/app.php';
        if (file_exists($mainConfig)) {
            $this->config = array_merge($this->config, require $mainConfig);
        }
        
        // Load database configuration
        $dbConfig = $configDir . '/database.php';
        if (file_exists($dbConfig)) {
            $this->config['database'] = require $dbConfig;
        }
        
        // Load mail configuration
        $mailConfig = $configDir . '/mail.php';
        if (file_exists($mailConfig)) {
            $this->config['mail'] = require $mailConfig;
        }
        
        // Load environment-specific config
        $env = $_ENV['APP_ENV'] ?? 'production';
        $envConfig = $configDir . "/env/{$env}.php";
        if (file_exists($envConfig)) {
            $this->config = array_merge_recursive($this->config, require $envConfig);
        }
        
        // Override with environment variables
        $this->loadEnvironmentVariables();
    }
    
    /**
     * Load configuration from environment variables
     */
    private function loadEnvironmentVariables(): void
    {
        $envMappings = [
            'DB_HOST' => 'database.host',
            'DB_NAME' => 'database.name',
            'DB_USER' => 'database.username',
            'DB_PASS' => 'database.password',
            'DB_PORT' => 'database.port',
            'MAIL_HOST' => 'mail.default.host',
            'MAIL_PORT' => 'mail.default.port',
            'MAIL_USER' => 'mail.default.username',
            'MAIL_PASS' => 'mail.default.password',
            'MAIL_ENCRYPTION' => 'mail.default.encryption',
            'AWS_ACCESS_KEY' => 'mail.ses.access_key',
            'AWS_SECRET_KEY' => 'mail.ses.secret_key',
            'AWS_REGION' => 'mail.ses.region',
            'APP_DEBUG' => 'app.debug',
            'APP_URL' => 'app.url',
        ];
        
        foreach ($envMappings as $envKey => $configKey) {
            $value = $_ENV[$envKey] ?? null;
            if ($value !== null) {
                // Convert string booleans
                if (in_array(strtolower($value), ['true', 'false'])) {
                    $value = strtolower($value) === 'true';
                }
                // Convert numeric strings
                elseif (is_numeric($value)) {
                    $value = is_float($value) ? (float)$value : (int)$value;
                }
                
                $this->set($configKey, $value);
            }
        }
    }
    
    /**
     * Get all configuration
     */
    public function all(): array
    {
        return $this->config;
    }
    
    /**
     * Check if configuration key exists
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}
