<?php
/**
 * Home Controller
 * Handles the main landing page and public routes
 */

declare(strict_types=1);

namespace App\Controllers;

use Core\Request;
use Core\Response;

class HomeController extends BaseController
{
    /**
     * Display the home page
     */
    public function index(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        
        // If user is authenticated, redirect to dashboard
        if ($this->app->isAuthenticated()) {
            $this->redirect('/dashboard');
            return;
        }
        
        // Check if we have a tenant context
        $tenant = $this->getCurrentTenant();
        
        if (!$tenant) {
            // No tenant found - show generic landing page or setup
            $this->showGenericLanding();
            return;
        }
        
        // Show tenant-specific landing page
        $this->showTenantLanding($tenant);
    }
    
    /**
     * Show generic landing page (no tenant context)
     */
    private function showGenericLanding(): void
    {
        $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YeKill Newsletter System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .container { max-width: 1200px; margin: 0 auto; padding: 50px 20px; text-align: center; }
        .hero { margin-bottom: 50px; }
        .hero h1 { font-size: 3em; margin-bottom: 20px; }
        .hero p { font-size: 1.2em; margin-bottom: 30px; }
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin: 50px 0; }
        .feature { background: rgba(255,255,255,0.1); padding: 30px; border-radius: 10px; }
        .feature h3 { margin-bottom: 15px; }
        .cta { margin: 50px 0; }
        .btn { display: inline-block; padding: 15px 30px; background: #ff6b6b; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .btn:hover { background: #ff5252; }
    </style>
</head>
<body>
    <div class="container">
        <div class="hero">
            <h1>🚀 YeKill Newsletter System</h1>
            <p>Professionelles mehrmandantenfähiges Newsletter-System mit erweiterten Marketing-Funktionen</p>
        </div>
        
        <div class="features">
            <div class="feature">
                <h3>🏢 Multi-Tenant</h3>
                <p>Vollständige Mandantentrennung mit unbegrenzten Kunden und individuellen Domains</p>
            </div>
            <div class="feature">
                <h3>📧 Drag & Drop Editor</h3>
                <p>Intuitiver visueller Editor für professionelle Newsletter ohne Programmierkenntnisse</p>
            </div>
            <div class="feature">
                <h3>🎯 Segmentierung</h3>
                <p>Dynamische Kontaktsegmente mit Tags und erweiterten Filteroptionen</p>
            </div>
            <div class="feature">
                <h3>🤖 Automation</h3>
                <p>Visuelle Workflow-Erstellung mit Trigger-basierter Automatisierung</p>
            </div>
            <div class="feature">
                <h3>📊 Analytics</h3>
                <p>Umfassendes Reporting mit Öffnungs-, Klick- und Konversionsraten</p>
            </div>
            <div class="feature">
                <h3>🔗 API-First</h3>
                <p>Vollständige RESTful API für Integrationen und Custom-Entwicklungen</p>
            </div>
        </div>
        
        <div class="cta">
            <a href="/login" class="btn">Zum Admin-Login</a>
        </div>
        
        <div style="margin-top: 50px; font-size: 0.9em; opacity: 0.8;">
            <p>YeKill Newsletter System v1.0.0 | PHP 8.4+ | Multi-SMTP Support</p>
        </div>
    </div>
</body>
</html>';
        
        $this->response->html($html);
        $this->response->send();
    }
    
    /**
     * Show tenant-specific landing page
     */
    private function showTenantLanding(array $tenant): void
    {
        $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($tenant['name']) . ' - Newsletter</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 800px; margin: 50px auto; padding: 40px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .logo { margin-bottom: 30px; }
        .logo h1 { color: #333; margin-bottom: 10px; }
        .login-box { background: #f9f9f9; padding: 30px; border-radius: 5px; margin: 30px 0; }
        .btn { display: inline-block; padding: 12px 25px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; margin: 10px; }
        .btn:hover { background: #005a87; }
        .features { text-align: left; margin: 30px 0; }
        .features ul { list-style: none; padding: 0; }
        .features li { padding: 8px 0; }
        .features li:before { content: "✓ "; color: #28a745; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>' . htmlspecialchars($tenant['name']) . '</h1>
            <p>Newsletter Management System</p>
        </div>
        
        <div class="login-box">
            <h2>Admin-Zugang</h2>
            <p>Melden Sie sich an, um Ihre Newsletter zu verwalten</p>
            <a href="/login" class="btn">Anmelden</a>
        </div>
        
        <div class="features">
            <h3>Verfügbare Funktionen:</h3>
            <ul>
                <li>Kontakt- und Listenverwaltung</li>
                <li>Newsletter-Editor mit Vorlagen</li>
                <li>Kampagnen-Management</li>
                <li>Automatisierte Workflows</li>
                <li>Detaillierte Statistiken</li>
                <li>DSGVO-konforme Verwaltung</li>
            </ul>
        </div>
    </div>
</body>
</html>';
        
        $this->response->html($html);
        $this->response->send();
    }
}
