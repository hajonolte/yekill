<?php
/**
 * YeKill Newsletter System - Main Entry Point
 * Multi-Tenant Newsletter Management System
 * 
 * @author HJN
 * @version 1.0.0
 * @php 8.4+
 */

declare(strict_types=1);

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define application constants
define('APP_ROOT', __DIR__);
define('APP_VERSION', '1.0.0');
define('APP_NAME', 'YeKill Newsletter System');

// Autoloader for our custom framework
require_once APP_ROOT . '/core/Autoloader.php';

use Core\Application;
use Core\Config;
use Core\Database;
use Core\Router;
use Core\Request;
use Core\Response;
use Core\Session;

try {
    // Initialize autoloader
    $autoloader = new Core\Autoloader();
    $autoloader->register();
    
    // Load configuration
    $config = new Config();
    
    // Initialize database connection
    $database = new Database($config->get('database'));
    
    // Start session management
    $session = new Session();
    $session->start();
    
    // Create application instance
    $app = new Application($config, $database, $session);

    // Make app globally available for controllers
    $GLOBALS['app'] = $app;

    // Initialize router
    $router = new Router();
    
    // Load routes
    require_once APP_ROOT . '/config/routes.php';
    
    // Create request and response objects
    $request = new Request();
    $response = new Response();
    
    // Handle the request
    $router->dispatch($request, $response);
    
} catch (Exception $e) {
    // Error handling
    http_response_code(500);
    
    if (defined('DEBUG') && DEBUG) {
        echo '<h1>Application Error</h1>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
        echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>System Error</h1>';
        echo '<p>An error occurred. Please try again later.</p>';
    }
    
    // Log error
    error_log(sprintf(
        "[%s] %s in %s:%d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));
}
