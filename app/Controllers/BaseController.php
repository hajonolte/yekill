<?php
/**
 * Base Controller
 * Common functionality for all controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use Core\Request;
use Core\Response;
use Core\Application;

abstract class BaseController
{
    protected Application $app;
    protected Request $request;
    protected Response $response;
    
    public function __construct()
    {
        // These will be injected by the router
    }
    
    /**
     * Set application dependencies
     */
    public function setDependencies(Application $app, Request $request, Response $response): void
    {
        $this->app = $app;
        $this->request = $request;
        $this->response = $response;
    }
    
    /**
     * Render a template
     */
    protected function render(string $template, array $data = []): void
    {
        $templatePath = APP_ROOT . "/templates/{$template}.php";
        
        if (!file_exists($templatePath)) {
            throw new \Exception("Template not found: {$template}");
        }
        
        // Extract data to variables
        extract($data);
        
        // Start output buffering
        ob_start();
        include $templatePath;
        $content = ob_get_clean();
        
        $this->response->html($content);
        $this->response->send();
    }
    
    /**
     * Return JSON response
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        $this->response->json($data, $statusCode);
        $this->response->send();
    }
    
    /**
     * Redirect to URL
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        $this->response->redirect($url, $statusCode);
        $this->response->send();
    }
    
    /**
     * Get current tenant
     */
    protected function getCurrentTenant(): ?array
    {
        return $this->app->getCurrentTenant();
    }
    
    /**
     * Get current user
     */
    protected function getCurrentUser(): ?array
    {
        return $this->app->getCurrentUser();
    }
    
    /**
     * Check if user has permission
     */
    protected function hasPermission(string $permission): bool
    {
        return $this->app->hasPermission($permission);
    }
    
    /**
     * Require authentication
     */
    protected function requireAuth(): void
    {
        if (!$this->app->isAuthenticated()) {
            $this->redirect('/login');
        }
    }
    
    /**
     * Require permission
     */
    protected function requirePermission(string $permission): void
    {
        $this->requireAuth();
        
        if (!$this->hasPermission($permission)) {
            $this->response->setStatusCode(403);
            $this->response->setContent('<h1>403 - Forbidden</h1><p>You do not have permission to access this resource.</p>');
            $this->response->send();
        }
    }
    
    /**
     * Validate CSRF token
     */
    protected function validateCsrf(): bool
    {
        $token = $this->request->input('_token');
        return $this->app->getSession()->verifyCsrfToken($token);
    }
    
    /**
     * Get database instance
     */
    protected function getDatabase()
    {
        return $this->app->getDatabase();
    }
}
