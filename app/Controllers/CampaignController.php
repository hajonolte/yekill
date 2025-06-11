<?php
/**
 * Campaign Controller
 * Manages newsletter campaigns and email sending
 */

declare(strict_types=1);

namespace App\Controllers;

use Core\Request;
use Core\Response;
use Core\ValidationException;
use App\Services\EmailService;
use App\Services\EmailMessage;

class CampaignController extends BaseController
{
    /**
     * Display campaigns list
     */
    public function index(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $user = $this->getCurrentUser();
        
        // Get campaigns with statistics
        $sql = "
            SELECT c.*,
                   CASE 
                       WHEN c.recipient_count > 0 THEN ROUND((c.opened_count / c.recipient_count) * 100, 1)
                       ELSE 0 
                   END as open_rate,
                   CASE 
                       WHEN c.recipient_count > 0 THEN ROUND((c.clicked_count / c.recipient_count) * 100, 1)
                       ELSE 0 
                   END as click_rate
            FROM campaigns c
            WHERE c.tenant_id = ?
            ORDER BY c.created_at DESC
            LIMIT 50
        ";
        
        $campaigns = $this->getDatabase()->query($sql, [$tenant['id']])->fetchAll();
        
        $this->renderCampaignsIndex($campaigns, $tenant, $user);
    }
    
    /**
     * Show create campaign form
     */
    public function create(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $user = $this->getCurrentUser();
        
        // Get available lists
        $lists = $this->getDatabase()->findAll(
            'contact_lists',
            ['tenant_id' => $tenant['id'], 'status' => 'active'],
            '*',
            'name ASC'
        );
        
        // Get available templates
        $templates = $this->getDatabase()->findAll(
            'email_templates',
            ['tenant_id' => $tenant['id'], 'status' => 'active'],
            '*',
            'name ASC'
        );
        
        $this->renderCampaignForm(null, $lists, $templates, $tenant, $user);
    }
    
    /**
     * Store new campaign
     */
    public function store(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $user = $this->getCurrentUser();
        
        try {
            // Validate input
            $data = $request->validate([
                'name' => 'required|max:255',
                'subject' => 'required|max:500',
                'from_name' => 'required|max:255',
                'from_email' => 'required|email',
                'content' => 'required'
            ]);
            
            // Prepare campaign data
            $campaignData = [
                'tenant_id' => $tenant['id'],
                'name' => $data['name'],
                'subject' => $data['subject'],
                'from_name' => $data['from_name'],
                'from_email' => $data['from_email'],
                'reply_to' => $request->input('reply_to') ?: $data['from_email'],
                'content' => $data['content'],
                'type' => 'regular',
                'status' => 'draft',
                'settings' => json_encode([
                    'track_opens' => $request->input('track_opens', true),
                    'track_clicks' => $request->input('track_clicks', true),
                    'lists' => $request->input('lists', [])
                ]),
                'created_by' => $user['id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $campaignId = $this->getDatabase()->insert('campaigns', $campaignData);
            
            $this->redirect('/campaigns/' . $campaignId . '?success=created');
            
        } catch (ValidationException $e) {
            $this->redirect('/campaigns/create?error=validation&errors=' . urlencode(json_encode($e->getErrors())));
        } catch (\Exception $e) {
            $this->redirect('/campaigns/create?error=' . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Show campaign details
     */
    public function show(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $campaignId = $request->getRouteParam('id');
        
        // Get campaign details
        $campaign = $this->getDatabase()->find('campaigns', [
            'id' => $campaignId,
            'tenant_id' => $tenant['id']
        ]);
        
        if (!$campaign) {
            $this->redirect('/campaigns?error=not_found');
            return;
        }
        
        // Get campaign statistics
        $stats = $this->getCampaignStats($campaignId);
        
        $this->renderCampaignDetails($campaign, $stats, $tenant, $this->getCurrentUser());
    }
    
    /**
     * Send test email
     */
    public function sendTest(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $campaignId = $request->getRouteParam('id');
        
        $campaign = $this->getDatabase()->find('campaigns', [
            'id' => $campaignId,
            'tenant_id' => $tenant['id']
        ]);
        
        if (!$campaign) {
            $this->json(['success' => false, 'message' => 'Kampagne nicht gefunden'], 404);
            return;
        }
        
        $testEmail = $request->input('test_email');
        if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'Gültige E-Mail-Adresse erforderlich'], 400);
            return;
        }
        
        try {
            // Create email service
            $emailService = new EmailService($this->app->getConfig(), $this->getDatabase());
            
            // Create test message
            $message = new EmailMessage(
                $testEmail,
                '[TEST] ' . $campaign['subject'],
                $this->processEmailContent($campaign['content'], [
                    'first_name' => 'Test',
                    'last_name' => 'Empfänger',
                    'email' => $testEmail
                ])
            );
            
            $message->setFrom($campaign['from_email'], $campaign['from_name']);
            
            // Send test email
            $success = $emailService->send($message);
            
            if ($success) {
                $this->json(['success' => true, 'message' => 'Test-E-Mail erfolgreich gesendet']);
            } else {
                $this->json(['success' => false, 'message' => 'Fehler beim Senden der Test-E-Mail']);
            }
            
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Send campaign to all recipients
     */
    public function send(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $campaignId = $request->getRouteParam('id');
        
        $campaign = $this->getDatabase()->find('campaigns', [
            'id' => $campaignId,
            'tenant_id' => $tenant['id']
        ]);
        
        if (!$campaign) {
            $this->json(['success' => false, 'message' => 'Kampagne nicht gefunden'], 404);
            return;
        }
        
        if ($campaign['status'] !== 'draft') {
            $this->json(['success' => false, 'message' => 'Kampagne kann nicht gesendet werden'], 400);
            return;
        }
        
        try {
            $this->getDatabase()->beginTransaction();
            
            // Update campaign status
            $this->getDatabase()->update('campaigns', [
                'status' => 'sending',
                'sent_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$campaignId]);
            
            // Get recipients from selected lists
            $settings = json_decode($campaign['settings'], true);
            $selectedLists = $settings['lists'] ?? [];
            
            if (empty($selectedLists)) {
                throw new \Exception('Keine Listen ausgewählt');
            }
            
            // Get all active subscribers from selected lists
            $listIds = implode(',', array_map('intval', $selectedLists));
            $sql = "
                SELECT DISTINCT c.id, c.email, c.first_name, c.last_name
                FROM contacts c
                JOIN contact_list_subscriptions cls ON c.id = cls.contact_id
                WHERE cls.list_id IN ({$listIds})
                  AND cls.status = 'subscribed'
                  AND c.status = 'active'
                  AND c.tenant_id = ?
            ";
            
            $recipients = $this->getDatabase()->query($sql, [$tenant['id']])->fetchAll();
            
            if (empty($recipients)) {
                throw new \Exception('Keine aktiven Empfänger gefunden');
            }
            
            // Create campaign recipients records
            foreach ($recipients as $recipient) {
                $this->getDatabase()->insert('campaign_recipients', [
                    'campaign_id' => $campaignId,
                    'contact_id' => $recipient['id'],
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Update recipient count
            $this->getDatabase()->update('campaigns', [
                'recipient_count' => count($recipients)
            ], 'id = ?', [$campaignId]);
            
            $this->getDatabase()->commit();
            
            // Start background sending process (simplified for demo)
            $this->processCampaignSending($campaignId);
            
            $this->json([
                'success' => true, 
                'message' => 'Kampagne wird gesendet an ' . count($recipients) . ' Empfänger'
            ]);
            
        } catch (\Exception $e) {
            $this->getDatabase()->rollback();
            $this->json(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Process campaign sending (simplified version)
     */
    private function processCampaignSending(int $campaignId): void
    {
        // In a real implementation, this would be handled by a queue system
        // For demo purposes, we'll send a few emails immediately
        
        $campaign = $this->getDatabase()->find('campaigns', ['id' => $campaignId]);
        $emailService = new EmailService($this->app->getConfig(), $this->getDatabase());
        
        // Get pending recipients (limit to 10 for demo)
        $sql = "
            SELECT cr.*, c.email, c.first_name, c.last_name
            FROM campaign_recipients cr
            JOIN contacts c ON cr.contact_id = c.id
            WHERE cr.campaign_id = ? AND cr.status = 'pending'
            LIMIT 10
        ";
        
        $recipients = $this->getDatabase()->query($sql, [$campaignId])->fetchAll();
        
        foreach ($recipients as $recipient) {
            try {
                // Create personalized message
                $message = new EmailMessage(
                    $recipient['email'],
                    $campaign['subject'],
                    $this->processEmailContent($campaign['content'], $recipient)
                );
                
                $message->setFrom($campaign['from_email'], $campaign['from_name']);
                
                // Add tracking headers
                $message->addHeader('X-Campaign-ID', (string)$campaignId);
                $message->addHeader('X-Contact-ID', (string)$recipient['contact_id']);
                
                // Send email
                $success = $emailService->send($message);
                
                // Update recipient status
                $this->getDatabase()->update('campaign_recipients', [
                    'status' => $success ? 'sent' : 'failed',
                    'sent_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$recipient['id']]);
                
                if ($success) {
                    // Update campaign delivered count
                    $this->getDatabase()->query(
                        'UPDATE campaigns SET delivered_count = delivered_count + 1 WHERE id = ?',
                        [$campaignId]
                    );
                }
                
            } catch (\Exception $e) {
                // Update recipient as failed
                $this->getDatabase()->update('campaign_recipients', [
                    'status' => 'failed'
                ], 'id = ?', [$recipient['id']]);
            }
        }
        
        // Check if all recipients are processed
        $pendingCount = $this->getDatabase()->query(
            'SELECT COUNT(*) as count FROM campaign_recipients WHERE campaign_id = ? AND status = "pending"',
            [$campaignId]
        )->fetch()['count'];
        
        if ($pendingCount == 0) {
            // Mark campaign as sent
            $this->getDatabase()->update('campaigns', [
                'status' => 'sent'
            ], 'id = ?', [$campaignId]);
        }
    }
    
    /**
     * Process email content with personalization
     */
    private function processEmailContent(string $content, array $contact): string
    {
        // Simple template variable replacement
        $replacements = [
            '{{first_name}}' => $contact['first_name'] ?? '',
            '{{last_name}}' => $contact['last_name'] ?? '',
            '{{email}}' => $contact['email'] ?? '',
            '{{full_name}}' => trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''))
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
    
    /**
     * Get campaign statistics
     */
    private function getCampaignStats(int $campaignId): array
    {
        return $this->getDatabase()->query("
            SELECT 
                COUNT(*) as total_recipients,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened_count,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked_count,
                SUM(open_count) as total_opens,
                SUM(click_count) as total_clicks
            FROM campaign_recipients
            WHERE campaign_id = ?
        ", [$campaignId])->fetch();
    }
    
    /**
     * Render campaigns index page
     */
    private function renderCampaignsIndex($campaigns, $tenant, $user): void
    {
        $html = $this->getBaseTemplate($tenant, $user, 'Kampagnen');
        
        $html .= '<div class="container">
            <div class="page-header">
                <h1>📧 Newsletter-Kampagnen</h1>
                <div class="actions">
                    <a href="/campaigns/create" class="btn btn-primary">➕ Neue Kampagne</a>
                </div>
            </div>
            
            <div class="campaigns-table">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Betreff</th>
                            <th>Status</th>
                            <th>Empfänger</th>
                            <th>Öffnungsrate</th>
                            <th>Klickrate</th>
                            <th>Erstellt</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($campaigns as $campaign) {
            $statusClass = match($campaign['status']) {
                'draft' => 'status-draft',
                'sending' => 'status-sending',
                'sent' => 'status-sent',
                'paused' => 'status-paused',
                'cancelled' => 'status-cancelled',
                default => 'status-unknown'
            };
            
            $statusText = match($campaign['status']) {
                'draft' => '📝 Entwurf',
                'sending' => '📤 Wird gesendet',
                'sent' => '✅ Gesendet',
                'paused' => '⏸️ Pausiert',
                'cancelled' => '❌ Abgebrochen',
                default => '❓ Unbekannt'
            };
            
            $html .= '<tr>
                <td><strong><a href="/campaigns/' . $campaign['id'] . '">' . htmlspecialchars($campaign['name']) . '</a></strong></td>
                <td>' . htmlspecialchars($campaign['subject']) . '</td>
                <td><span class="status ' . $statusClass . '">' . $statusText . '</span></td>
                <td>' . number_format($campaign['recipient_count']) . '</td>
                <td>' . $campaign['open_rate'] . '%</td>
                <td>' . $campaign['click_rate'] . '%</td>
                <td>' . date('d.m.Y H:i', strtotime($campaign['created_at'])) . '</td>
                <td class="actions">
                    <a href="/campaigns/' . $campaign['id'] . '" class="btn btn-sm">👁️ Anzeigen</a>';
            
            if ($campaign['status'] === 'draft') {
                $html .= '<a href="/campaigns/' . $campaign['id'] . '/edit" class="btn btn-sm">✏️ Bearbeiten</a>';
            }
            
            $html .= '</td>
            </tr>';
        }
        
        if (empty($campaigns)) {
            $html .= '<tr><td colspan="8" class="text-center">Noch keine Kampagnen erstellt</td></tr>';
        }
        
        $html .= '</tbody>
                </table>
            </div>
        </div>' . $this->getBaseTemplateFooter();
        
        $this->response->html($html);
        $this->response->send();
    }
    
    /**
     * Get base template
     */
    private function getBaseTemplate($tenant, $user, $title): string
    {
        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . ' - ' . htmlspecialchars($tenant['name']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .header { background: #007cba; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.2em; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .nav { background: white; padding: 15px 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .nav a { color: #007cba; text-decoration: none; margin-right: 20px; }
        .nav a:hover { text-decoration: underline; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .page-header h1 { margin: 0; }
        .actions { display: flex; gap: 10px; }
        .btn { display: inline-block; padding: 8px 16px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .btn:hover { background: #005a87; }
        .btn-primary { background: #28a745; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .campaigns-table { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: bold; }
        .status { padding: 4px 8px; border-radius: 12px; font-size: 12px; }
        .status-draft { background: #fff3cd; color: #856404; }
        .status-sending { background: #cce5ff; color: #004085; }
        .status-sent { background: #d4edda; color: #155724; }
        .status-paused { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #f5c6cb; color: #721c24; }
        .text-center { text-align: center; }
        .logout { color: #dc3545; text-decoration: none; }
        .logout:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 ' . htmlspecialchars($tenant['name']) . ' - ' . htmlspecialchars($title) . '</h1>
        <div class="user-info">
            <span>' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</span>
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
        </div>';
    }
    
    /**
     * Get base template footer
     */
    private function getBaseTemplateFooter(): string
    {
        return '</div></body></html>';
    }
}
