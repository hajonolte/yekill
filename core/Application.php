<?php
/**
 * Main Application Class
 * Manages the application lifecycle and dependencies
 */

declare(strict_types=1);

namespace Core;

class Application
{
    private Config $config;
    private Database $database;
    private Session $session;
    private ?array $currentTenant = null;
    
    public function __construct(Config $config, Database $database, Session $session)
    {
        $this->config = $config;
        $this->database = $database;
        $this->session = $session;
        
        $this->initializeTenant();
    }
    
    /**
     * Get configuration instance
     */
    public function getConfig(): Config
    {
        return $this->config;
    }
    
    /**
     * Get database instance
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }
    
    /**
     * Get session instance
     */
    public function getSession(): Session
    {
        return $this->session;
    }
    
    /**
     * Get current tenant information
     */
    public function getCurrentTenant(): ?array
    {
        return $this->currentTenant;
    }
    
    /**
     * Set current tenant
     */
    public function setCurrentTenant(array $tenant): void
    {
        $this->currentTenant = $tenant;
        $this->session->set('tenant_id', $tenant['id']);
    }
    
    /**
     * Initialize tenant context
     */
    private function initializeTenant(): void
    {
        // Check for tenant in session
        $tenantId = $this->session->get('tenant_id');
        
        if ($tenantId) {
            $tenant = $this->database->query(
                'SELECT * FROM tenants WHERE id = ? AND status = "active"',
                [$tenantId]
            )->fetch();
            
            if ($tenant) {
                $this->currentTenant = $tenant;
            }
        }
        
        // If no tenant in session, try to determine from subdomain or domain
        if (!$this->currentTenant) {
            $this->determineTenantFromRequest();
        }
    }
    
    /**
     * Determine tenant from HTTP request
     */
    private function determineTenantFromRequest(): void
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        // Try subdomain matching (e.g., customer1.newsletter.com)
        if (preg_match('/^([^.]+)\./', $host, $matches)) {
            $subdomain = $matches[1];
            
            $tenant = $this->database->query(
                'SELECT * FROM tenants WHERE subdomain = ? AND status = "active"',
                [$subdomain]
            )->fetch();
            
            if ($tenant) {
                $this->currentTenant = $tenant;
                return;
            }
        }
        
        // Try custom domain matching
        $tenant = $this->database->query(
            'SELECT * FROM tenants WHERE custom_domain = ? AND status = "active"',
            [$host]
        )->fetch();
        
        if ($tenant) {
            $this->currentTenant = $tenant;
        }
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->session->has('user_id');
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $userId = $this->session->get('user_id');
        $tenantId = $this->currentTenant['id'] ?? null;
        
        if (!$tenantId) {
            return null;
        }
        
        return $this->database->query(
            'SELECT u.*, r.name as role_name, r.permissions 
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             WHERE u.id = ? AND u.tenant_id = ? AND u.status = "active"',
            [$userId, $tenantId]
        )->fetch();
    }
    
    /**
     * Check if current user has permission
     */
    public function hasPermission(string $permission): bool
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return false;
        }
        
        $permissions = json_decode($user['permissions'] ?? '[]', true);
        
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }
}
