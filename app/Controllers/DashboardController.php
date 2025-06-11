<?php
/**
 * Dashboard Controller
 * Main admin dashboard with overview statistics
 */

declare(strict_types=1);

namespace App\Controllers;

use Core\Request;
use Core\Response;

class DashboardController extends BaseController
{
    /**
     * Display the dashboard
     */
    public function index(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $user = $this->getCurrentUser();
        
        // Get basic statistics
        $stats = $this->getDashboardStats($tenant['id']);
        
        $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ' . htmlspecialchars($tenant['name']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .header { background: #007cba; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-card h3 { margin: 0 0 10px 0; color: #666; font-size: 14px; text-transform: uppercase; }
        .stat-card .number { font-size: 2.5em; font-weight: bold; color: #007cba; margin: 10px 0; }
        .stat-card .change { font-size: 12px; color: #28a745; }
        .quick-actions { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .quick-actions h2 { margin-top: 0; }
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .action-btn { display: block; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; text-decoration: none; color: #333; text-align: center; transition: all 0.2s; }
        .action-btn:hover { background: #e9ecef; transform: translateY(-2px); }
        .nav { background: white; padding: 15px 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .nav a { color: #007cba; text-decoration: none; margin-right: 20px; }
        .nav a:hover { text-decoration: underline; }
        .logout { color: #dc3545; text-decoration: none; }
        .logout:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 ' . htmlspecialchars($tenant['name']) . ' Dashboard</h1>
        <div class="user-info">
            <span>Willkommen, ' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</span>
            <a href="/logout" class="logout">Abmelden</a>
        </div>
    </div>
    
    <div class="container">
        <div class="nav">
            <a href="/dashboard">Dashboard</a>
            <a href="/contacts">Kontakte</a>
            <a href="/lists">Listen</a>
            <a href="/campaigns">Kampagnen</a>
            <a href="/templates">Vorlagen</a>
            <a href="/automations">Automationen</a>
            <a href="/reports">Berichte</a>
            <a href="/settings">Einstellungen</a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Kontakte</h3>
                <div class="number">' . number_format($stats['contacts']) . '</div>
                <div class="change">Aktive Abonnenten</div>
            </div>
            <div class="stat-card">
                <h3>Listen</h3>
                <div class="number">' . number_format($stats['lists']) . '</div>
                <div class="change">Empfängerlisten</div>
            </div>
            <div class="stat-card">
                <h3>Kampagnen</h3>
                <div class="number">' . number_format($stats['campaigns']) . '</div>
                <div class="change">Gesendete Newsletter</div>
            </div>
            <div class="stat-card">
                <h3>Öffnungsrate</h3>
                <div class="number">' . number_format($stats['open_rate'], 1) . '%</div>
                <div class="change">Durchschnitt letzte 30 Tage</div>
            </div>
        </div>
        
        <div class="quick-actions">
            <h2>Schnellaktionen</h2>
            <div class="actions-grid">
                <a href="/campaigns/create" class="action-btn">
                    📧 Neue Kampagne erstellen
                </a>
                <a href="/contacts/create" class="action-btn">
                    👤 Kontakt hinzufügen
                </a>
                <a href="/lists/create" class="action-btn">
                    📋 Neue Liste erstellen
                </a>
                <a href="/templates/create" class="action-btn">
                    🎨 Vorlage erstellen
                </a>
                <a href="/contacts/import" class="action-btn">
                    📥 Kontakte importieren
                </a>
                <a href="/reports" class="action-btn">
                    📊 Berichte anzeigen
                </a>
            </div>
        </div>
    </div>
</body>
</html>';
        
        $this->response->html($html);
        $this->response->send();
    }
    
    /**
     * Get dashboard statistics
     */
    private function getDashboardStats(int $tenantId): array
    {
        $db = $this->getDatabase();
        
        // Count contacts
        $contactCount = $db->query(
            'SELECT COUNT(*) as count FROM contacts WHERE tenant_id = ? AND status = "active"',
            [$tenantId]
        )->fetch()['count'] ?? 0;
        
        // Count lists
        $listCount = $db->query(
            'SELECT COUNT(*) as count FROM contact_lists WHERE tenant_id = ? AND status = "active"',
            [$tenantId]
        )->fetch()['count'] ?? 0;
        
        // Count campaigns
        $campaignCount = $db->query(
            'SELECT COUNT(*) as count FROM campaigns WHERE tenant_id = ? AND status = "sent"',
            [$tenantId]
        )->fetch()['count'] ?? 0;
        
        // Calculate average open rate (last 30 days)
        $openRateData = $db->query(
            'SELECT 
                AVG(CASE WHEN recipient_count > 0 THEN (opened_count / recipient_count) * 100 ELSE 0 END) as avg_open_rate
             FROM campaigns 
             WHERE tenant_id = ? AND status = "sent" AND sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            [$tenantId]
        )->fetch();
        
        $openRate = $openRateData['avg_open_rate'] ?? 0;
        
        return [
            'contacts' => (int)$contactCount,
            'lists' => (int)$listCount,
            'campaigns' => (int)$campaignCount,
            'open_rate' => (float)$openRate,
        ];
    }
}
