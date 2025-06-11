<?php
/**
 * YeKill Newsletter System - Installation Script
 * Automated installation and setup for multi-tenant newsletter system
 * 
 * @author HJN
 * @version 1.0.0
 * @php 8.4+
 */

declare(strict_types=1);

// Prevent direct access in production
if (file_exists(__DIR__ . '/.installed')) {
    die('Installation already completed. Delete .installed file to reinstall.');
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define constants
define('INSTALL_ROOT', __DIR__);
define('MIN_PHP_VERSION', '8.4.0');

class YeKillInstaller
{
    private array $config = [];
    private array $errors = [];
    private array $warnings = [];
    private ?PDO $db = null;
    
    public function __construct()
    {
        $this->checkRequirements();
    }
    
    /**
     * Run the installation process
     */
    public function install(): void
    {
        $this->displayHeader();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processInstallation();
        } else {
            $this->displayInstallationForm();
        }
    }
    
    /**
     * Check system requirements
     */
    private function checkRequirements(): void
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
            $this->errors[] = "PHP " . MIN_PHP_VERSION . " or higher is required. Current version: " . PHP_VERSION;
        }
        
        // Check required extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'curl'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $this->errors[] = "Required PHP extension missing: {$ext}";
            }
        }
        
        // Check directory permissions
        $directories = [
            'storage/logs',
            'storage/cache',
            'storage/uploads',
            'config'
        ];
        
        foreach ($directories as $dir) {
            $fullPath = INSTALL_ROOT . '/' . $dir;
            if (!is_dir($fullPath)) {
                if (!mkdir($fullPath, 0755, true)) {
                    $this->errors[] = "Cannot create directory: {$dir}";
                }
            } elseif (!is_writable($fullPath)) {
                $this->errors[] = "Directory not writable: {$dir}";
            }
        }
        
        // Check Apache mod_rewrite
        if (function_exists('apache_get_modules') && !in_array('mod_rewrite', apache_get_modules())) {
            $this->warnings[] = "Apache mod_rewrite is not enabled. URL rewriting may not work.";
        }
    }
    
    /**
     * Process installation form submission
     */
    private function processInstallation(): void
    {
        $this->config = [
            'db_host' => $_POST['db_host'] ?? 'localhost',
            'db_port' => (int)($_POST['db_port'] ?? 3306),
            'db_name' => $_POST['db_name'] ?? '',
            'db_user' => $_POST['db_user'] ?? '',
            'db_pass' => $_POST['db_pass'] ?? '',
            'admin_email' => $_POST['admin_email'] ?? '',
            'admin_password' => $_POST['admin_password'] ?? '',
            'admin_first_name' => $_POST['admin_first_name'] ?? '',
            'admin_last_name' => $_POST['admin_last_name'] ?? '',
            'site_name' => $_POST['site_name'] ?? 'YeKill Newsletter',
            'site_url' => $_POST['site_url'] ?? '',
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
            'smtp_user' => $_POST['smtp_user'] ?? '',
            'smtp_pass' => $_POST['smtp_pass'] ?? '',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
        ];
        
        // Validate input
        if (empty($this->config['db_name'])) {
            $this->errors[] = "Database name is required";
        }
        if (empty($this->config['admin_email']) || !filter_var($this->config['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "Valid admin email is required";
        }
        if (empty($this->config['admin_password']) || strlen($this->config['admin_password']) < 8) {
            $this->errors[] = "Admin password must be at least 8 characters";
        }
        
        if (empty($this->errors)) {
            try {
                $this->createDatabase();
                $this->createTables();
                $this->createDefaultTenant();
                $this->createAdminUser();
                $this->createConfigFiles();
                $this->createHtaccess();
                $this->finalizeInstallation();
                
                $this->displaySuccess();
            } catch (Exception $e) {
                $this->errors[] = "Installation failed: " . $e->getMessage();
                $this->displayInstallationForm();
            }
        } else {
            $this->displayInstallationForm();
        }
    }
    
    /**
     * Create database connection and database if needed
     */
    private function createDatabase(): void
    {
        try {
            // Connect without database first
            $dsn = "mysql:host={$this->config['db_host']};port={$this->config['db_port']};charset=utf8mb4";
            $this->db = new PDO($dsn, $this->config['db_user'], $this->config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Create database if it doesn't exist
            $this->db->exec("CREATE DATABASE IF NOT EXISTS `{$this->config['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Connect to the specific database
            $dsn = "mysql:host={$this->config['db_host']};port={$this->config['db_port']};dbname={$this->config['db_name']};charset=utf8mb4";
            $this->db = new PDO($dsn, $this->config['db_user'], $this->config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Create database tables
     */
    private function createTables(): void
    {
        $schemaFile = INSTALL_ROOT . '/database/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found: {$schemaFile}");
        }
        
        $sql = file_get_contents($schemaFile);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !str_starts_with($statement, '--')) {
                $this->db->exec($statement);
            }
        }
    }
    
    /**
     * Create default tenant
     */
    private function createDefaultTenant(): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO tenants (name, subdomain, status, settings, created_at) 
            VALUES (?, 'app', 'active', '{}', NOW())
        ");
        $stmt->execute([$this->config['site_name']]);
        
        $this->config['tenant_id'] = $this->db->lastInsertId();
    }
    
    /**
     * Create admin user and role
     */
    private function createAdminUser(): void
    {
        // Create admin role
        $stmt = $this->db->prepare("
            INSERT INTO roles (tenant_id, name, permissions, created_at) 
            VALUES (?, 'Administrator', ?, NOW())
        ");
        $permissions = json_encode(['*']); // All permissions
        $stmt->execute([$this->config['tenant_id'], $permissions]);
        $roleId = $this->db->lastInsertId();
        
        // Create admin user
        $stmt = $this->db->prepare("
            INSERT INTO users (tenant_id, role_id, email, password, first_name, last_name, status, email_verified_at, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        $hashedPassword = password_hash($this->config['admin_password'], PASSWORD_DEFAULT);
        $stmt->execute([
            $this->config['tenant_id'],
            $roleId,
            $this->config['admin_email'],
            $hashedPassword,
            $this->config['admin_first_name'],
            $this->config['admin_last_name']
        ]);
    }
    
    /**
     * Create configuration files
     */
    private function createConfigFiles(): void
    {
        // Database config
        $dbConfig = "<?php\nreturn [\n";
        $dbConfig .= "    'host' => '{$this->config['db_host']}',\n";
        $dbConfig .= "    'port' => {$this->config['db_port']},\n";
        $dbConfig .= "    'name' => '{$this->config['db_name']}',\n";
        $dbConfig .= "    'username' => '{$this->config['db_user']}',\n";
        $dbConfig .= "    'password' => '{$this->config['db_pass']}',\n";
        $dbConfig .= "    'charset' => 'utf8mb4',\n";
        $dbConfig .= "    'collation' => 'utf8mb4_unicode_ci',\n";
        $dbConfig .= "];\n";
        
        file_put_contents(INSTALL_ROOT . '/config/database.php', $dbConfig);
        
        // Mail config
        $mailConfig = "<?php\nreturn [\n";
        $mailConfig .= "    'default' => 'smtp',\n";
        $mailConfig .= "    'smtp' => [\n";
        $mailConfig .= "        'host' => '{$this->config['smtp_host']}',\n";
        $mailConfig .= "        'port' => {$this->config['smtp_port']},\n";
        $mailConfig .= "        'username' => '{$this->config['smtp_user']}',\n";
        $mailConfig .= "        'password' => '{$this->config['smtp_pass']}',\n";
        $mailConfig .= "        'encryption' => '{$this->config['smtp_encryption']}',\n";
        $mailConfig .= "    ],\n";
        $mailConfig .= "];\n";
        
        file_put_contents(INSTALL_ROOT . '/config/mail.php', $mailConfig);
    }
    
    /**
     * Create .htaccess file for URL rewriting
     */
    private function createHtaccess(): void
    {
        $htaccess = "RewriteEngine On\n";
        $htaccess .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
        $htaccess .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
        $htaccess .= "RewriteRule ^(.*)$ index.php [QSA,L]\n";
        
        file_put_contents(INSTALL_ROOT . '/.htaccess', $htaccess);
    }
    
    /**
     * Finalize installation
     */
    private function finalizeInstallation(): void
    {
        // Create installation marker
        file_put_contents(INSTALL_ROOT . '/.installed', date('Y-m-d H:i:s'));
        
        // Set secure permissions
        chmod(INSTALL_ROOT . '/config', 0750);
        chmod(INSTALL_ROOT . '/storage', 0750);
    }
    
    /**
     * Display installation header
     */
    private function displayHeader(): void
    {
        echo "<!DOCTYPE html>\n<html>\n<head>\n";
        echo "<title>YeKill Newsletter System - Installation</title>\n";
        echo "<meta charset='utf-8'>\n";
        echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px}";
        echo ".error{color:red;background:#ffe6e6;padding:10px;margin:10px 0;border:1px solid red}";
        echo ".warning{color:orange;background:#fff3cd;padding:10px;margin:10px 0;border:1px solid orange}";
        echo ".success{color:green;background:#d4edda;padding:10px;margin:10px 0;border:1px solid green}";
        echo "input,select{width:100%;padding:8px;margin:5px 0}";
        echo "button{background:#007cba;color:white;padding:10px 20px;border:none;cursor:pointer}</style>\n";
        echo "</head>\n<body>\n";
        echo "<h1>🚀 YeKill Newsletter System Installation</h1>\n";
        
        // Display errors and warnings
        foreach ($this->errors as $error) {
            echo "<div class='error'>❌ {$error}</div>\n";
        }
        foreach ($this->warnings as $warning) {
            echo "<div class='warning'>⚠️ {$warning}</div>\n";
        }
    }
    
    /**
     * Display installation form
     */
    private function displayInstallationForm(): void
    {
        if (!empty($this->errors)) {
            echo "<p><strong>Please fix the errors above before continuing.</strong></p>\n";
            if (count($this->errors) > 0) {
                echo "</body></html>";
                return;
            }
        }
        
        echo "<form method='post'>\n";
        echo "<h2>📊 Database Configuration</h2>\n";
        echo "<label>Database Host:</label><input type='text' name='db_host' value='localhost' required>\n";
        echo "<label>Database Port:</label><input type='number' name='db_port' value='3306' required>\n";
        echo "<label>Database Name:</label><input type='text' name='db_name' required>\n";
        echo "<label>Database Username:</label><input type='text' name='db_user' required>\n";
        echo "<label>Database Password:</label><input type='password' name='db_pass'>\n";
        
        echo "<h2>👤 Administrator Account</h2>\n";
        echo "<label>Admin Email:</label><input type='email' name='admin_email' required>\n";
        echo "<label>Admin Password:</label><input type='password' name='admin_password' required>\n";
        echo "<label>First Name:</label><input type='text' name='admin_first_name' required>\n";
        echo "<label>Last Name:</label><input type='text' name='admin_last_name' required>\n";
        
        echo "<h2>🌐 Site Configuration</h2>\n";
        echo "<label>Site Name:</label><input type='text' name='site_name' value='YeKill Newsletter' required>\n";
        echo "<label>Site URL:</label><input type='url' name='site_url' value='http://{$_SERVER['HTTP_HOST']}'>\n";
        
        echo "<h2>📧 SMTP Configuration (Optional)</h2>\n";
        echo "<label>SMTP Host:</label><input type='text' name='smtp_host'>\n";
        echo "<label>SMTP Port:</label><input type='number' name='smtp_port' value='587'>\n";
        echo "<label>SMTP Username:</label><input type='text' name='smtp_user'>\n";
        echo "<label>SMTP Password:</label><input type='password' name='smtp_pass'>\n";
        echo "<label>Encryption:</label><select name='smtp_encryption'><option value='tls'>TLS</option><option value='ssl'>SSL</option><option value=''>None</option></select>\n";
        
        echo "<br><button type='submit'>🚀 Install YeKill Newsletter System</button>\n";
        echo "</form>\n";
        echo "</body></html>";
    }
    
    /**
     * Display success message
     */
    private function displaySuccess(): void
    {
        echo "<div class='success'>\n";
        echo "<h2>✅ Installation Completed Successfully!</h2>\n";
        echo "<p>YeKill Newsletter System has been installed and configured.</p>\n";
        echo "<p><strong>Admin Login:</strong> {$this->config['admin_email']}</p>\n";
        echo "<p><strong>Next Steps:</strong></p>\n";
        echo "<ul>\n";
        echo "<li>Delete this install.php file for security</li>\n";
        echo "<li><a href='/login'>Login to your admin panel</a></li>\n";
        echo "<li>Configure your SMTP settings in the admin panel</li>\n";
        echo "<li>Create your first contact list</li>\n";
        echo "<li>Design your first newsletter template</li>\n";
        echo "</ul>\n";
        echo "</div>\n";
        echo "</body></html>";
    }
}

// Run installer
try {
    $installer = new YeKillInstaller();
    $installer->install();
} catch (Exception $e) {
    echo "<div style='color:red;background:#ffe6e6;padding:20px;margin:20px;border:1px solid red'>";
    echo "<h2>Installation Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
