<?php
/**
 * List Controller
 * Manages contact lists and subscriber management
 */

declare(strict_types=1);

namespace App\Controllers;

use Core\Request;
use Core\Response;
use Core\ValidationException;

class ListController extends BaseController
{
    /**
     * Display lists overview
     */
    public function index(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $user = $this->getCurrentUser();
        
        // Get lists with subscriber counts
        $sql = "
            SELECT cl.*,
                   COUNT(DISTINCT cls.contact_id) as subscriber_count,
                   COUNT(DISTINCT CASE WHEN cls.status = 'subscribed' THEN cls.contact_id END) as active_subscribers,
                   COUNT(DISTINCT CASE WHEN cls.status = 'pending' THEN cls.contact_id END) as pending_subscribers
            FROM contact_lists cl
            LEFT JOIN contact_list_subscriptions cls ON cl.id = cls.list_id
            WHERE cl.tenant_id = ?
            GROUP BY cl.id
            ORDER BY cl.created_at DESC
        ";
        
        $lists = $this->getDatabase()->query($sql, [$tenant['id']])->fetchAll();
        
        $this->renderListsIndex($lists, $tenant, $user);
    }
    
    /**
     * Show create list form
     */
    public function create(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $user = $this->getCurrentUser();
        
        $this->renderListForm(null, $tenant, $user);
    }
    
    /**
     * Store new list
     */
    public function store(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        
        try {
            // Validate input
            $data = $request->validate([
                'name' => 'required|max:255',
                'description' => 'max:1000',
                'double_optin' => 'required'
            ]);
            
            // Check if list name already exists for this tenant
            $existing = $this->getDatabase()->find('contact_lists', [
                'tenant_id' => $tenant['id'],
                'name' => $data['name']
            ]);
            
            if ($existing) {
                throw new \Exception('Eine Liste mit diesem Namen existiert bereits.');
            }
            
            // Prepare list data
            $listData = [
                'tenant_id' => $tenant['id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => 'active',
                'double_optin' => (int)$data['double_optin'],
                'settings' => json_encode([
                    'welcome_email' => $request->input('welcome_email', false),
                    'goodbye_email' => $request->input('goodbye_email', false),
                ]),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $listId = $this->getDatabase()->insert('contact_lists', $listData);
            
            $this->redirect('/lists?success=created');
            
        } catch (ValidationException $e) {
            $this->redirect('/lists/create?error=validation&errors=' . urlencode(json_encode($e->getErrors())));
        } catch (\Exception $e) {
            $this->redirect('/lists/create?error=' . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Show list details
     */
    public function show(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $listId = $request->getRouteParam('id');
        
        // Get list details
        $list = $this->getDatabase()->find('contact_lists', [
            'id' => $listId,
            'tenant_id' => $tenant['id']
        ]);
        
        if (!$list) {
            $this->redirect('/lists?error=not_found');
            return;
        }
        
        // Get pagination parameters
        $page = max(1, (int)$request->getQuery('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;
        
        // Get search parameter
        $search = $request->getQuery('search', '');
        
        // Build query for subscribers
        $whereClause = 'WHERE cls.list_id = ? AND cls.status = "subscribed"';
        $params = [$listId];
        
        if (!empty($search)) {
            $whereClause .= ' AND (c.email LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)';
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        // Get total subscriber count
        $countSql = "
            SELECT COUNT(*) as total 
            FROM contact_list_subscriptions cls
            JOIN contacts c ON cls.contact_id = c.id
            {$whereClause}
        ";
        $totalSubscribers = $this->getDatabase()->query($countSql, $params)->fetch()['total'];
        
        // Get subscribers
        $sql = "
            SELECT c.*, cls.subscribed_at, cls.source
            FROM contact_list_subscriptions cls
            JOIN contacts c ON cls.contact_id = c.id
            {$whereClause}
            ORDER BY cls.subscribed_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $subscribers = $this->getDatabase()->query($sql, $params)->fetchAll();
        
        // Get list statistics
        $stats = $this->getDatabase()->query("
            SELECT 
                COUNT(DISTINCT cls.contact_id) as total_subscribers,
                COUNT(DISTINCT CASE WHEN cls.status = 'subscribed' THEN cls.contact_id END) as active_subscribers,
                COUNT(DISTINCT CASE WHEN cls.status = 'pending' THEN cls.contact_id END) as pending_subscribers,
                COUNT(DISTINCT CASE WHEN cls.status = 'unsubscribed' THEN cls.contact_id END) as unsubscribed_count,
                COUNT(DISTINCT CASE WHEN c.status = 'bounced' THEN cls.contact_id END) as bounced_count
            FROM contact_list_subscriptions cls
            LEFT JOIN contacts c ON cls.contact_id = c.id
            WHERE cls.list_id = ?
        ", [$listId])->fetch();
        
        // Calculate pagination
        $totalPages = ceil($totalSubscribers / $limit);
        
        $this->renderListDetails($list, $subscribers, $stats, $page, $totalPages, $search, $tenant, $this->getCurrentUser());
    }
    
    /**
     * Show edit list form
     */
    public function edit(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $listId = $request->getRouteParam('id');
        
        $list = $this->getDatabase()->find('contact_lists', [
            'id' => $listId,
            'tenant_id' => $tenant['id']
        ]);
        
        if (!$list) {
            $this->redirect('/lists?error=not_found');
            return;
        }
        
        $this->renderListForm($list, $tenant, $this->getCurrentUser());
    }
    
    /**
     * Update list
     */
    public function update(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $listId = $request->getRouteParam('id');
        
        // Check if list exists and belongs to tenant
        $list = $this->getDatabase()->find('contact_lists', [
            'id' => $listId,
            'tenant_id' => $tenant['id']
        ]);
        
        if (!$list) {
            $this->redirect('/lists?error=not_found');
            return;
        }
        
        try {
            // Validate input
            $data = $request->validate([
                'name' => 'required|max:255',
                'description' => 'max:1000',
                'double_optin' => 'required',
                'status' => 'required'
            ]);
            
            // Check if list name already exists for this tenant (excluding current list)
            $existing = $this->getDatabase()->query(
                'SELECT id FROM contact_lists WHERE tenant_id = ? AND name = ? AND id != ?',
                [$tenant['id'], $data['name'], $listId]
            )->fetch();
            
            if ($existing) {
                throw new \Exception('Eine Liste mit diesem Namen existiert bereits.');
            }
            
            // Prepare update data
            $updateData = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
                'double_optin' => (int)$data['double_optin'],
                'settings' => json_encode([
                    'welcome_email' => $request->input('welcome_email', false),
                    'goodbye_email' => $request->input('goodbye_email', false),
                ]),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->getDatabase()->update('contact_lists', $updateData, 'id = ?', [$listId]);
            
            $this->redirect('/lists/' . $listId . '?success=updated');
            
        } catch (ValidationException $e) {
            $this->redirect('/lists/' . $listId . '/edit?error=validation&errors=' . urlencode(json_encode($e->getErrors())));
        } catch (\Exception $e) {
            $this->redirect('/lists/' . $listId . '/edit?error=' . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Delete list
     */
    public function delete(Request $request, Response $response): void
    {
        $this->setDependencies($GLOBALS['app'], $request, $response);
        $this->requireAuth();
        
        $tenant = $this->getCurrentTenant();
        $listId = $request->getRouteParam('id');
        
        // Check if list exists and belongs to tenant
        $list = $this->getDatabase()->find('contact_lists', [
            'id' => $listId,
            'tenant_id' => $tenant['id']
        ]);
        
        if (!$list) {
            $this->redirect('/lists?error=not_found');
            return;
        }
        
        try {
            $this->getDatabase()->beginTransaction();
            
            // Delete all subscriptions first (due to foreign key constraints)
            $this->getDatabase()->delete('contact_list_subscriptions', 'list_id = ?', [$listId]);
            
            // Delete the list
            $this->getDatabase()->delete('contact_lists', 'id = ?', [$listId]);
            
            $this->getDatabase()->commit();
            
            $this->redirect('/lists?success=deleted');
            
        } catch (\Exception $e) {
            $this->getDatabase()->rollback();
            $this->redirect('/lists?error=' . urlencode('Fehler beim Löschen der Liste: ' . $e->getMessage()));
        }
    }
    
    /**
     * Render lists index page
     */
    private function renderListsIndex($lists, $tenant, $user): void
    {
        $html = $this->getBaseTemplate($tenant, $user, 'Listen');
        
        $html .= '<div class="container">
            <div class="page-header">
                <h1>📋 Empfängerlisten</h1>
                <div class="actions">
                    <a href="/lists/create" class="btn btn-primary">➕ Neue Liste</a>
                </div>
            </div>
            
            <div class="lists-grid">';
        
        foreach ($lists as $list) {
            $statusClass = $list['status'] === 'active' ? 'status-active' : 'status-inactive';
            $statusText = $list['status'] === 'active' ? '✅ Aktiv' : '⏸️ Inaktiv';
            $doubleOptinText = $list['double_optin'] ? '🔒 Double Opt-in' : '📧 Single Opt-in';
            
            $html .= '<div class="list-card">
                <div class="list-header">
                    <h3><a href="/lists/' . $list['id'] . '">' . htmlspecialchars($list['name']) . '</a></h3>
                    <span class="status ' . $statusClass . '">' . $statusText . '</span>
                </div>
                
                <div class="list-description">
                    ' . htmlspecialchars($list['description'] ?: 'Keine Beschreibung') . '
                </div>
                
                <div class="list-stats">
                    <div class="stat">
                        <span class="number">' . number_format($list['active_subscribers']) . '</span>
                        <span class="label">Aktive Abonnenten</span>
                    </div>
                    <div class="stat">
                        <span class="number">' . number_format($list['pending_subscribers']) . '</span>
                        <span class="label">Ausstehend</span>
                    </div>
                </div>
                
                <div class="list-meta">
                    <span>' . $doubleOptinText . '</span>
                    <span>Erstellt: ' . date('d.m.Y', strtotime($list['created_at'])) . '</span>
                </div>
                
                <div class="list-actions">
                    <a href="/lists/' . $list['id'] . '" class="btn btn-sm">👁️ Anzeigen</a>
                    <a href="/lists/' . $list['id'] . '/edit" class="btn btn-sm">✏️ Bearbeiten</a>
                    <a href="/lists/' . $list['id'] . '/delete" class="btn btn-sm btn-danger" onclick="return confirm(\'Liste wirklich löschen?\')">🗑️ Löschen</a>
                </div>
            </div>';
        }
        
        if (empty($lists)) {
            $html .= '<div class="empty-state">
                <h3>📋 Noch keine Listen erstellt</h3>
                <p>Erstellen Sie Ihre erste Empfängerliste, um Newsletter zu versenden.</p>
                <a href="/lists/create" class="btn btn-primary">➕ Erste Liste erstellen</a>
            </div>';
        }
        
        $html .= '</div></div>' . $this->getBaseTemplateFooter();
        
        $this->response->html($html);
        $this->response->send();
    }
    
    /**
     * Get base template with additional CSS for lists
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
        .btn-danger { background: #dc3545; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .lists-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        .list-card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; }
        .list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .list-header h3 { margin: 0; }
        .list-header h3 a { color: #333; text-decoration: none; }
        .list-header h3 a:hover { color: #007cba; }
        .list-description { color: #666; margin-bottom: 15px; font-size: 14px; }
        .list-stats { display: flex; gap: 20px; margin-bottom: 15px; }
        .stat { text-align: center; }
        .stat .number { display: block; font-size: 1.5em; font-weight: bold; color: #007cba; }
        .stat .label { font-size: 12px; color: #666; }
        .list-meta { display: flex; justify-content: space-between; font-size: 12px; color: #666; margin-bottom: 15px; }
        .list-actions { display: flex; gap: 5px; }
        .status { padding: 4px 8px; border-radius: 12px; font-size: 12px; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
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
