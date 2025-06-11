<?php
/**
 * Session Management
 * Handles PHP sessions with security features
 */

declare(strict_types=1);

namespace Core;

class Session
{
    private bool $started = false;
    
    /**
     * Start the session
     */
    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Configure session settings
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $this->isHttps() ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', '1');
        
        session_start();
        $this->started = true;
        
        // Regenerate session ID periodically for security
        if (!$this->has('_session_started')) {
            session_regenerate_id(true);
            $this->set('_session_started', time());
        } elseif (time() - $this->get('_session_started') > 1800) { // 30 minutes
            session_regenerate_id(true);
            $this->set('_session_started', time());
        }
    }
    
    /**
     * Set session value
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get session value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Check if session has key
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session value
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }
    
    /**
     * Get all session data
     */
    public function all(): array
    {
        return $_SESSION ?? [];
    }
    
    /**
     * Clear all session data
     */
    public function clear(): void
    {
        $_SESSION = [];
    }
    
    /**
     * Destroy the session
     */
    public function destroy(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
            $this->started = false;
        }
    }
    
    /**
     * Regenerate session ID
     */
    public function regenerate(bool $deleteOld = true): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOld);
            $this->set('_session_started', time());
        }
    }
    
    /**
     * Flash message - store for next request only
     */
    public function flash(string $key, mixed $value): void
    {
        $this->set("_flash_{$key}", $value);
    }
    
    /**
     * Get flash message
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $this->get("_flash_{$key}", $default);
        $this->remove("_flash_{$key}");
        return $value;
    }
    
    /**
     * Check if flash message exists
     */
    public function hasFlash(string $key): bool
    {
        return $this->has("_flash_{$key}");
    }
    
    /**
     * Get session ID
     */
    public function getId(): string
    {
        return session_id();
    }
    
    /**
     * Set session name
     */
    public function setName(string $name): void
    {
        session_name($name);
    }
    
    /**
     * Get session name
     */
    public function getName(): string
    {
        return session_name();
    }
    
    /**
     * Check if connection is HTTPS
     */
    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               $_SERVER['SERVER_PORT'] == 443 ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
    
    /**
     * Set CSRF token
     */
    public function setCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->set('_csrf_token', $token);
        return $token;
    }
    
    /**
     * Get CSRF token
     */
    public function getCsrfToken(): ?string
    {
        return $this->get('_csrf_token');
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken(string $token): bool
    {
        $sessionToken = $this->getCsrfToken();
        return $sessionToken && hash_equals($sessionToken, $token);
    }
    
    /**
     * Store previous URL for redirects
     */
    public function setPreviousUrl(string $url): void
    {
        $this->set('_previous_url', $url);
    }
    
    /**
     * Get previous URL
     */
    public function getPreviousUrl(string $default = '/'): string
    {
        return $this->get('_previous_url', $default);
    }
}
