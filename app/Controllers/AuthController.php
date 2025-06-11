<?php
/**
 * Authentication Controller
 * Handles user login, registration, and password reset
 */

declare(strict_types=1);

namespace App\Controllers;

use Core\Request;
use Core\Response;

class AuthController extends BaseController
{
    /**
     * Show login form
     */
    public function showLogin(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        
        // If already authenticated, redirect to dashboard
        if ($this->app->isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }
        
        $tenant = $this->getCurrentTenant();
        $tenantName = $tenant ? $tenant['name'] : 'YeKill Newsletter';
        
        $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmelden - ' . htmlspecialchars($tenantName) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 100%; max-width: 400px; }
        .login-container h1 { text-align: center; margin-bottom: 30px; color: #333; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; box-sizing: border-box; }
        .form-group input:focus { outline: none; border-color: #007cba; }
        .btn { width: 100%; padding: 12px; background: #007cba; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
        .btn:hover { background: #005a87; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .links { text-align: center; margin-top: 20px; }
        .links a { color: #007cba; text-decoration: none; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>🔐 Anmelden</h1>
        <p style="text-align: center; color: #666; margin-bottom: 30px;">' . htmlspecialchars($tenantName) . '</p>
        
        <form method="post" action="/login">
            <div class="form-group">
                <label for="email">E-Mail-Adresse:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Passwort:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Anmelden</button>
        </form>
        
        <div class="links">
            <a href="/forgot-password">Passwort vergessen?</a>
        </div>
    </div>
</body>
</html>';
        
        $this->response->html($html);
        $this->response->send();
    }
    
    /**
     * Process login
     */
    public function login(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        
        $email = $request->input('email');
        $password = $request->input('password');
        
        if (empty($email) || empty($password)) {
            $this->redirect('/login?error=missing_credentials');
            return;
        }
        
        $tenant = $this->getCurrentTenant();
        if (!$tenant) {
            $this->redirect('/login?error=no_tenant');
            return;
        }
        
        // Find user
        $user = $this->getDatabase()->query(
            'SELECT * FROM users WHERE email = ? AND tenant_id = ? AND status = "active"',
            [$email, $tenant['id']]
        )->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            $this->redirect('/login?error=invalid_credentials');
            return;
        }
        
        // Update last login
        $this->getDatabase()->update(
            'users',
            ['last_login_at' => date('Y-m-d H:i:s'), 'login_attempts' => 0],
            'id = ?',
            [$user['id']]
        );
        
        // Set session
        $session = $this->app->getSession();
        $session->set('user_id', $user['id']);
        $session->set('tenant_id', $tenant['id']);
        $session->regenerate();
        
        $this->redirect('/dashboard');
    }
    
    /**
     * Logout user
     */
    public function logout(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        
        $this->app->getSession()->destroy();
        $this->redirect('/');
    }
    
    /**
     * Show registration form (placeholder)
     */
    public function showRegister(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        
        $this->response->html('<h1>Registration</h1><p>Registration is currently disabled. Please contact your administrator.</p>');
        $this->response->send();
    }
    
    /**
     * Process registration (placeholder)
     */
    public function register(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        
        $this->redirect('/register');
    }
    
    /**
     * Show forgot password form (placeholder)
     */
    public function showForgotPassword(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        
        $this->response->html('<h1>Password Reset</h1><p>Password reset functionality will be implemented in a future version.</p>');
        $this->response->send();
    }
    
    /**
     * Process forgot password (placeholder)
     */
    public function forgotPassword(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        
        $this->redirect('/forgot-password');
    }
    
    /**
     * Show reset password form (placeholder)
     */
    public function showResetPassword(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        
        $this->response->html('<h1>Reset Password</h1><p>Password reset functionality will be implemented in a future version.</p>');
        $this->response->send();
    }
    
    /**
     * Process reset password (placeholder)
     */
    public function resetPassword(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        
        $this->redirect('/login');
    }
}
