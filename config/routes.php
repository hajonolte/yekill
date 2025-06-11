<?php
/**
 * Application Routes
 */

use Core\Router;

// Get router instance
/** @var Router $router */

// Public routes (no authentication required)
$router->get('/', ['App\Controllers\HomeController', 'index']);
$router->get('/login', ['App\Controllers\AuthController', 'showLogin']);
$router->post('/login', ['App\Controllers\AuthController', 'login']);
$router->get('/register', ['App\Controllers\AuthController', 'showRegister']);
$router->post('/register', ['App\Controllers\AuthController', 'register']);
$router->get('/logout', ['App\Controllers\AuthController', 'logout']);
$router->get('/forgot-password', ['App\Controllers\AuthController', 'showForgotPassword']);
$router->post('/forgot-password', ['App\Controllers\AuthController', 'forgotPassword']);
$router->get('/reset-password/{token}', ['App\Controllers\AuthController', 'showResetPassword']);
$router->post('/reset-password', ['App\Controllers\AuthController', 'resetPassword']);

// Email tracking routes (public)
$router->get('/track/open/{campaign_id}/{contact_id}', ['App\Controllers\TrackingController', 'trackOpen']);
$router->get('/track/click/{campaign_id}/{contact_id}/{link_id}', ['App\Controllers\TrackingController', 'trackClick']);
$router->get('/unsubscribe/{token}', ['App\Controllers\SubscriptionController', 'unsubscribe']);
$router->post('/unsubscribe', ['App\Controllers\SubscriptionController', 'processUnsubscribe']);

// Subscription management (public)
$router->get('/subscribe/{list_id}', ['App\Controllers\SubscriptionController', 'showSubscribe']);
$router->post('/subscribe', ['App\Controllers\SubscriptionController', 'subscribe']);
$router->get('/confirm/{token}', ['App\Controllers\SubscriptionController', 'confirm']);

// Webhook endpoints (public but secured)
$router->post('/webhooks/bounce', ['App\Controllers\WebhookController', 'handleBounce']);
$router->post('/webhooks/complaint', ['App\Controllers\WebhookController', 'handleComplaint']);
$router->post('/webhooks/delivery', ['App\Controllers\WebhookController', 'handleDelivery']);

// Protected routes (require authentication)
$router->group(['AuthMiddleware'], function($router) {
    
    // Dashboard
    $router->get('/dashboard', ['App\Controllers\DashboardController', 'index']);
    
    // Contact Management
    $router->get('/contacts', ['App\Controllers\ContactController', 'index']);
    $router->get('/contacts/create', ['App\Controllers\ContactController', 'create']);
    $router->post('/contacts', ['App\Controllers\ContactController', 'store']);
    $router->get('/contacts/{id}', ['App\Controllers\ContactController', 'show']);
    $router->get('/contacts/{id}/edit', ['App\Controllers\ContactController', 'edit']);
    $router->put('/contacts/{id}', ['App\Controllers\ContactController', 'update']);
    $router->delete('/contacts/{id}', ['App\Controllers\ContactController', 'delete']);
    $router->post('/contacts/import', ['App\Controllers\ContactController', 'import']);
    $router->get('/contacts/export', ['App\Controllers\ContactController', 'export']);
    
    // List Management
    $router->get('/lists', ['App\Controllers\ListController', 'index']);
    $router->get('/lists/create', ['App\Controllers\ListController', 'create']);
    $router->post('/lists', ['App\Controllers\ListController', 'store']);
    $router->get('/lists/{id}', ['App\Controllers\ListController', 'show']);
    $router->get('/lists/{id}/edit', ['App\Controllers\ListController', 'edit']);
    $router->put('/lists/{id}', ['App\Controllers\ListController', 'update']);
    $router->delete('/lists/{id}', ['App\Controllers\ListController', 'delete']);
    
    // Segments
    $router->get('/segments', ['App\Controllers\SegmentController', 'index']);
    $router->get('/segments/create', ['App\Controllers\SegmentController', 'create']);
    $router->post('/segments', ['App\Controllers\SegmentController', 'store']);
    $router->get('/segments/{id}', ['App\Controllers\SegmentController', 'show']);
    $router->get('/segments/{id}/edit', ['App\Controllers\SegmentController', 'edit']);
    $router->put('/segments/{id}', ['App\Controllers\SegmentController', 'update']);
    $router->delete('/segments/{id}', ['App\Controllers\SegmentController', 'delete']);
    
    // Campaign Management
    $router->get('/campaigns', ['App\Controllers\CampaignController', 'index']);
    $router->get('/campaigns/create', ['App\Controllers\CampaignController', 'create']);
    $router->post('/campaigns', ['App\Controllers\CampaignController', 'store']);
    $router->get('/campaigns/{id}', ['App\Controllers\CampaignController', 'show']);
    $router->get('/campaigns/{id}/edit', ['App\Controllers\CampaignController', 'edit']);
    $router->put('/campaigns/{id}', ['App\Controllers\CampaignController', 'update']);
    $router->delete('/campaigns/{id}', ['App\Controllers\CampaignController', 'delete']);
    $router->post('/campaigns/{id}/send', ['App\Controllers\CampaignController', 'send']);
    $router->post('/campaigns/{id}/test', ['App\Controllers\CampaignController', 'sendTest']);
    $router->get('/campaigns/{id}/preview', ['App\Controllers\CampaignController', 'preview']);
    $router->get('/campaigns/{id}/stats', ['App\Controllers\CampaignController', 'stats']);
    
    // Template Management
    $router->get('/templates', ['App\Controllers\TemplateController', 'index']);
    $router->get('/templates/create', ['App\Controllers\TemplateController', 'create']);
    $router->post('/templates', ['App\Controllers\TemplateController', 'store']);
    $router->get('/templates/{id}', ['App\Controllers\TemplateController', 'show']);
    $router->get('/templates/{id}/edit', ['App\Controllers\TemplateController', 'edit']);
    $router->put('/templates/{id}', ['App\Controllers\TemplateController', 'update']);
    $router->delete('/templates/{id}', ['App\Controllers\TemplateController', 'delete']);
    $router->get('/templates/{id}/versions', ['App\Controllers\TemplateController', 'versions']);
    
    // Automation/Workflows
    $router->get('/automations', ['App\Controllers\AutomationController', 'index']);
    $router->get('/automations/create', ['App\Controllers\AutomationController', 'create']);
    $router->post('/automations', ['App\Controllers\AutomationController', 'store']);
    $router->get('/automations/{id}', ['App\Controllers\AutomationController', 'show']);
    $router->get('/automations/{id}/edit', ['App\Controllers\AutomationController', 'edit']);
    $router->put('/automations/{id}', ['App\Controllers\AutomationController', 'update']);
    $router->delete('/automations/{id}', ['App\Controllers\AutomationController', 'delete']);
    $router->post('/automations/{id}/activate', ['App\Controllers\AutomationController', 'activate']);
    $router->post('/automations/{id}/deactivate', ['App\Controllers\AutomationController', 'deactivate']);
    
    // Reports and Analytics
    $router->get('/reports', ['App\Controllers\ReportController', 'index']);
    $router->get('/reports/campaigns', ['App\Controllers\ReportController', 'campaigns']);
    $router->get('/reports/contacts', ['App\Controllers\ReportController', 'contacts']);
    $router->get('/reports/automations', ['App\Controllers\ReportController', 'automations']);
    
    // Settings
    $router->get('/settings', ['App\Controllers\SettingsController', 'index']);
    $router->get('/settings/profile', ['App\Controllers\SettingsController', 'profile']);
    $router->put('/settings/profile', ['App\Controllers\SettingsController', 'updateProfile']);
    $router->get('/settings/smtp', ['App\Controllers\SettingsController', 'smtp']);
    $router->put('/settings/smtp', ['App\Controllers\SettingsController', 'updateSmtp']);
    $router->get('/settings/domain', ['App\Controllers\SettingsController', 'domain']);
    $router->put('/settings/domain', ['App\Controllers\SettingsController', 'updateDomain']);
    
    // User Management (Admin only)
    $router->group(['AdminMiddleware'], function($router) {
        $router->get('/users', ['App\Controllers\UserController', 'index']);
        $router->get('/users/create', ['App\Controllers\UserController', 'create']);
        $router->post('/users', ['App\Controllers\UserController', 'store']);
        $router->get('/users/{id}', ['App\Controllers\UserController', 'show']);
        $router->get('/users/{id}/edit', ['App\Controllers\UserController', 'edit']);
        $router->put('/users/{id}', ['App\Controllers\UserController', 'update']);
        $router->delete('/users/{id}', ['App\Controllers\UserController', 'delete']);
    });
});

// API Routes
$router->group(['ApiMiddleware'], function($router) {
    $router->get('/api/v1/contacts', ['App\Controllers\Api\ContactController', 'index']);
    $router->post('/api/v1/contacts', ['App\Controllers\Api\ContactController', 'store']);
    $router->get('/api/v1/contacts/{id}', ['App\Controllers\Api\ContactController', 'show']);
    $router->put('/api/v1/contacts/{id}', ['App\Controllers\Api\ContactController', 'update']);
    $router->delete('/api/v1/contacts/{id}', ['App\Controllers\Api\ContactController', 'delete']);
    
    $router->get('/api/v1/lists', ['App\Controllers\Api\ListController', 'index']);
    $router->post('/api/v1/lists', ['App\Controllers\Api\ListController', 'store']);
    $router->get('/api/v1/lists/{id}', ['App\Controllers\Api\ListController', 'show']);
    $router->put('/api/v1/lists/{id}', ['App\Controllers\Api\ListController', 'update']);
    $router->delete('/api/v1/lists/{id}', ['App\Controllers\Api\ListController', 'delete']);
    
    $router->get('/api/v1/campaigns', ['App\Controllers\Api\CampaignController', 'index']);
    $router->post('/api/v1/campaigns', ['App\Controllers\Api\CampaignController', 'store']);
    $router->get('/api/v1/campaigns/{id}', ['App\Controllers\Api\CampaignController', 'show']);
    $router->put('/api/v1/campaigns/{id}', ['App\Controllers\Api\CampaignController', 'update']);
    $router->delete('/api/v1/campaigns/{id}', ['App\Controllers\Api\CampaignController', 'delete']);
    $router->post('/api/v1/campaigns/{id}/send', ['App\Controllers\Api\CampaignController', 'send']);
    
    $router->get('/api/v1/stats', ['App\Controllers\Api\StatsController', 'index']);
    $router->get('/api/v1/stats/campaigns/{id}', ['App\Controllers\Api\StatsController', 'campaign']);
});
