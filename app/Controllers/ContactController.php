<?php
/**
 * Contact Controller
 * Manages contact CRUD operations and list subscriptions
 */

declare(strict_types=1);

namespace App\Controllers;

use Core\Request;
use Core\Response;
use Core\ValidationException;

class ContactController extends BaseController
{
    /**
     * Display contacts list
     */
    public function index(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $user = $this->getCurrentUser();
        
        // Get pagination parameters
        $page = max(1, (int)$request->getQuery('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;
        
        // Get search and filter parameters
        $search = $request->getQuery('search', '');
        $status = $request->getQuery('status', '');
        $listId = $request->getQuery('list_id', '');
        
        // Build query conditions
        $conditions = ['tenant_id' => $tenant['id']];
        $params = [$tenant['id']];
        $whereClause = 'WHERE c.tenant_id = ?';
        
        if (!empty($search)) {
            $whereClause .= ' AND (c.email LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)';
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        if (!empty($status)) {
            $whereClause .= ' AND c.status = ?';
            $params[] = $status;
        }
        
        if (!empty($listId)) {
            $whereClause .= ' AND EXISTS (SELECT 1 FROM contact_list_subscriptions cls WHERE cls.contact_id = c.id AND cls.list_id = ? AND cls.status = "subscribed")';
            $params[] = $listId;
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM contacts c {$whereClause}";
        $totalContacts = $this->getDatabase()->query($countSql, $params)->fetch()['total'];
        
        // Get contacts
        $sql = "
            SELECT c.*, 
                   COUNT(DISTINCT cls.list_id) as list_count,
                   GROUP_CONCAT(DISTINCT cl.name SEPARATOR ', ') as list_names
            FROM contacts c
            LEFT JOIN contact_list_subscriptions cls ON c.id = cls.contact_id AND cls.status = 'subscribed'
            LEFT JOIN contact_lists cl ON cls.list_id = cl.id
            {$whereClause}
            GROUP BY c.id
            ORDER BY c.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $contacts = $this->getDatabase()->query($sql, $params)->fetchAll();
        
        // Get available lists for filter
        $lists = $this->getDatabase()->findAll(
            'contact_lists',
            ['tenant_id' => $tenant['id'], 'status' => 'active'],
            '*',
            'name ASC'
        );
        
        // Calculate pagination
        $totalPages = ceil($totalContacts / $limit);
        
        $this->renderContactsIndex($contacts, $lists, $page, $totalPages, $totalContacts, $search, $status, $listId, $tenant, $user);
    }
    
    /**
     * Show create contact form
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
        
        $this->renderContactForm(null, $lists, $tenant, $user);
    }
    
    /**
     * Store new contact
     */
    public function store(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        
        try {
            // Validate input
            $data = $request->validate([
                'email' => 'required|email',
                'first_name' => 'max:100',
                'last_name' => 'max:100',
                'phone' => 'max:50',
                'status' => 'required'
            ]);
            
            // Check if email already exists for this tenant
            $existing = $this->getDatabase()->find('contacts', [
                'tenant_id' => $tenant['id'],
                'email' => $data['email']
            ]);
            
            if ($existing) {
                throw new \Exception('Ein Kontakt mit dieser E-Mail-Adresse existiert bereits.');
            }
            
            // Prepare contact data
            $contactData = [
                'tenant_id' => $tenant['id'],
                'email' => $data['email'],
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'status' => $data['status'],
                'subscribed_at' => $data['status'] === 'active' ? date('Y-m-d H:i:s') : null,
                'source' => 'manual',
                'ip_address' => $request->getIp(),
                'user_agent' => $request->getUserAgent(),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Handle custom fields
            $customFields = [];
            foreach ($request->all() as $key => $value) {
                if (str_starts_with($key, 'custom_')) {
                    $fieldName = substr($key, 7); // Remove 'custom_' prefix
                    $customFields[$fieldName] = $value;
                }
            }
            
            if (!empty($customFields)) {
                $contactData['custom_fields'] = json_encode($customFields);
            }
            
            // Handle tags
            $tags = $request->input('tags', '');
            if (!empty($tags)) {
                $tagArray = array_map('trim', explode(',', $tags));
                $contactData['tags'] = json_encode($tagArray);
            }
            
            $this->getDatabase()->beginTransaction();
            
            try {
                // Insert contact
                $contactId = $this->getDatabase()->insert('contacts', $contactData);
                
                // Subscribe to selected lists
                $selectedLists = $request->input('lists', []);
                if (!empty($selectedLists)) {
                    foreach ($selectedLists as $listId) {
                        $this->getDatabase()->insert('contact_list_subscriptions', [
                            'contact_id' => $contactId,
                            'list_id' => $listId,
                            'status' => 'subscribed',
                            'subscribed_at' => date('Y-m-d H:i:s'),
                            'source' => 'manual'
                        ]);
                    }
                }
                
                $this->getDatabase()->commit();
                
                $this->redirect('/contacts?success=created');
                
            } catch (\Exception $e) {
                $this->getDatabase()->rollback();
                throw $e;
            }
            
        } catch (ValidationException $e) {
            $this->redirect('/contacts/create?error=validation&errors=' . urlencode(json_encode($e->getErrors())));
        } catch (\Exception $e) {
            $this->redirect('/contacts/create?error=' . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Show contact details
     */
    public function show(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $contactId = $request->getRouteParam('id');
        
        // Get contact with subscription details
        $sql = "
            SELECT c.*,
                   GROUP_CONCAT(DISTINCT cl.name SEPARATOR ', ') as subscribed_lists
            FROM contacts c
            LEFT JOIN contact_list_subscriptions cls ON c.id = cls.contact_id AND cls.status = 'subscribed'
            LEFT JOIN contact_lists cl ON cls.list_id = cl.id
            WHERE c.id = ? AND c.tenant_id = ?
            GROUP BY c.id
        ";
        
        $contact = $this->getDatabase()->query($sql, [$contactId, $tenant['id']])->fetch();
        
        if (!$contact) {
            $this->redirect('/contacts?error=not_found');
            return;
        }
        
        // Get campaign statistics for this contact
        $campaignStats = $this->getDatabase()->query("
            SELECT 
                COUNT(*) as total_campaigns,
                SUM(CASE WHEN cr.opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened_campaigns,
                SUM(CASE WHEN cr.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked_campaigns,
                SUM(cr.open_count) as total_opens,
                SUM(cr.click_count) as total_clicks
            FROM campaign_recipients cr
            JOIN campaigns c ON cr.campaign_id = c.id
            WHERE cr.contact_id = ? AND c.tenant_id = ?
        ", [$contactId, $tenant['id']])->fetch();
        
        $this->renderContactDetails($contact, $campaignStats, $tenant, $this->getCurrentUser());
    }
    
    /**
     * Render contacts index page
     */
    private function renderContactsIndex($contacts, $lists, $page, $totalPages, $totalContacts, $search, $status, $listId, $tenant, $user): void
    {
        $html = $this->getBaseTemplate($tenant, $user, 'Kontakte');
        
        $html .= '<div class="container">
            <div class="page-header">
                <h1>📧 Kontakte verwalten</h1>
                <div class="actions">
                    <a href="/contacts/create" class="btn btn-primary">➕ Neuer Kontakt</a>
                    <a href="/contacts/import" class="btn btn-secondary">📥 Importieren</a>
                    <a href="/contacts/export" class="btn btn-secondary">📤 Exportieren</a>
                </div>
            </div>
            
            <div class="filters">
                <form method="get" class="filter-form">
                    <input type="text" name="search" placeholder="E-Mail, Name suchen..." value="' . htmlspecialchars($search) . '">
                    <select name="status">
                        <option value="">Alle Status</option>
                        <option value="active"' . ($status === 'active' ? ' selected' : '') . '>Aktiv</option>
                        <option value="inactive"' . ($status === 'inactive' ? ' selected' : '') . '>Inaktiv</option>
                        <option value="unsubscribed"' . ($status === 'unsubscribed' ? ' selected' : '') . '>Abgemeldet</option>
                        <option value="bounced"' . ($status === 'bounced' ? ' selected' : '') . '>Bounce</option>
                    </select>
                    <select name="list_id">
                        <option value="">Alle Listen</option>';
        
        foreach ($lists as $list) {
            $selected = $listId == $list['id'] ? ' selected' : '';
            $html .= '<option value="' . $list['id'] . '"' . $selected . '>' . htmlspecialchars($list['name']) . '</option>';
        }
        
        $html .= '</select>
                    <button type="submit" class="btn">🔍 Filtern</button>
                    <a href="/contacts" class="btn btn-light">↻ Zurücksetzen</a>
                </form>
            </div>
            
            <div class="stats-bar">
                <span>📊 Gesamt: ' . number_format($totalContacts) . ' Kontakte</span>
            </div>
            
            <div class="contacts-table">
                <table>
                    <thead>
                        <tr>
                            <th>E-Mail</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Listen</th>
                            <th>Erstellt</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($contacts as $contact) {
            $statusClass = match($contact['status']) {
                'active' => 'status-active',
                'inactive' => 'status-inactive',
                'unsubscribed' => 'status-unsubscribed',
                'bounced' => 'status-bounced',
                default => 'status-unknown'
            };
            
            $statusText = match($contact['status']) {
                'active' => '✅ Aktiv',
                'inactive' => '⏸️ Inaktiv',
                'unsubscribed' => '❌ Abgemeldet',
                'bounced' => '⚠️ Bounce',
                default => '❓ Unbekannt'
            };
            
            $fullName = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
            $listNames = $contact['list_names'] ?: 'Keine Listen';
            
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($contact['email']) . '</strong></td>
                <td>' . htmlspecialchars($fullName ?: '-') . '</td>
                <td><span class="status ' . $statusClass . '">' . $statusText . '</span></td>
                <td>' . htmlspecialchars($listNames) . '</td>
                <td>' . date('d.m.Y H:i', strtotime($contact['created_at'])) . '</td>
                <td class="actions">
                    <a href="/contacts/' . $contact['id'] . '" class="btn btn-sm">👁️ Anzeigen</a>
                    <a href="/contacts/' . $contact['id'] . '/edit" class="btn btn-sm">✏️ Bearbeiten</a>
                </td>
            </tr>';
        }
        
        $html .= '</tbody>
                </table>
            </div>';
        
        // Pagination
        if ($totalPages > 1) {
            $html .= '<div class="pagination">';
            
            if ($page > 1) {
                $html .= '<a href="?page=' . ($page - 1) . '&search=' . urlencode($search) . '&status=' . urlencode($status) . '&list_id=' . urlencode($listId) . '" class="btn">← Zurück</a>';
            }
            
            $html .= '<span>Seite ' . $page . ' von ' . $totalPages . '</span>';
            
            if ($page < $totalPages) {
                $html .= '<a href="?page=' . ($page + 1) . '&search=' . urlencode($search) . '&status=' . urlencode($status) . '&list_id=' . urlencode($listId) . '" class="btn">Weiter →</a>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>' . $this->getBaseTemplateFooter();
        
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
        .btn-secondary { background: #6c757d; }
        .btn-light { background: #f8f9fa; color: #333; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .filters { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .filter-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .filter-form input, .filter-form select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .stats-bar { background: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .contacts-table { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: bold; }
        .status { padding: 4px 8px; border-radius: 12px; font-size: 12px; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-unsubscribed { background: #fff3cd; color: #856404; }
        .status-bounced { background: #f5c6cb; color: #721c24; }
        .pagination { text-align: center; margin: 20px 0; }
        .pagination .btn { margin: 0 5px; }
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
