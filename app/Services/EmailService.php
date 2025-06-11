<?php
/**
 * Email Service
 * Multi-SMTP email delivery with support for various providers
 */

declare(strict_types=1);

namespace App\Services;

use Core\Config;
use Core\Database;

class EmailService
{
    private Config $config;
    private Database $database;
    private array $providers = [];
    
    public function __construct(Config $config, Database $database)
    {
        $this->config = $config;
        $this->database = $database;
        $this->initializeProviders();
    }
    
    /**
     * Initialize email providers
     */
    private function initializeProviders(): void
    {
        $this->providers = [
            'smtp' => new SmtpProvider($this->config->get('mail.smtp', [])),
            'ses' => new SesProvider($this->config->get('mail.ses', [])),
            'mailgun' => new MailgunProvider($this->config->get('mail.mailgun', [])),
            'sendgrid' => new SendGridProvider($this->config->get('mail.sendgrid', [])),
            'postmark' => new PostmarkProvider($this->config->get('mail.postmark', [])),
        ];
    }
    
    /**
     * Send email using configured provider
     */
    public function send(EmailMessage $message, string $provider = null): bool
    {
        $provider = $provider ?: $this->config->get('mail.default', 'smtp');
        
        if (!isset($this->providers[$provider])) {
            throw new \Exception("Email provider '{$provider}' not found");
        }
        
        try {
            $result = $this->providers[$provider]->send($message);
            
            // Log email sending
            $this->logEmail($message, $provider, $result);
            
            return $result;
            
        } catch (\Exception $e) {
            // Log error
            $this->logEmailError($message, $provider, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send bulk emails (for campaigns)
     */
    public function sendBulk(array $messages, string $provider = null): array
    {
        $provider = $provider ?: $this->config->get('mail.default', 'smtp');
        $results = [];
        
        if (!isset($this->providers[$provider])) {
            throw new \Exception("Email provider '{$provider}' not found");
        }
        
        // Check if provider supports bulk sending
        if (method_exists($this->providers[$provider], 'sendBulk')) {
            return $this->providers[$provider]->sendBulk($messages);
        }
        
        // Fallback to individual sending with rate limiting
        $rateLimit = $this->config->get('mail.delivery.max_per_minute', 50);
        $sentCount = 0;
        $startTime = time();
        
        foreach ($messages as $message) {
            try {
                $result = $this->providers[$provider]->send($message);
                $results[] = ['success' => $result, 'message' => $message];
                $sentCount++;
                
                // Rate limiting
                if ($sentCount >= $rateLimit) {
                    $elapsed = time() - $startTime;
                    if ($elapsed < 60) {
                        sleep(60 - $elapsed);
                    }
                    $sentCount = 0;
                    $startTime = time();
                }
                
            } catch (\Exception $e) {
                $results[] = ['success' => false, 'message' => $message, 'error' => $e->getMessage()];
            }
        }
        
        return $results;
    }
    
    /**
     * Test email provider connection
     */
    public function testProvider(string $provider): bool
    {
        if (!isset($this->providers[$provider])) {
            throw new \Exception("Email provider '{$provider}' not found");
        }
        
        return $this->providers[$provider]->test();
    }
    
    /**
     * Get available providers
     */
    public function getAvailableProviders(): array
    {
        $available = [];
        
        foreach ($this->providers as $name => $provider) {
            $available[$name] = [
                'name' => $name,
                'configured' => $provider->isConfigured(),
                'status' => $provider->isConfigured() ? 'ready' : 'not_configured'
            ];
        }
        
        return $available;
    }
    
    /**
     * Log email sending
     */
    private function logEmail(EmailMessage $message, string $provider, bool $success): void
    {
        // This could be expanded to log to database or file
        error_log(sprintf(
            "[EMAIL] %s via %s to %s: %s",
            $success ? 'SUCCESS' : 'FAILED',
            $provider,
            $message->getTo(),
            $message->getSubject()
        ));
    }
    
    /**
     * Log email error
     */
    private function logEmailError(EmailMessage $message, string $provider, string $error): void
    {
        error_log(sprintf(
            "[EMAIL ERROR] %s via %s to %s: %s",
            $error,
            $provider,
            $message->getTo(),
            $message->getSubject()
        ));
    }
}

/**
 * Email Message Class
 */
class EmailMessage
{
    private string $to;
    private string $from;
    private string $fromName;
    private string $subject;
    private string $htmlBody;
    private string $textBody = '';
    private array $headers = [];
    private array $attachments = [];
    
    public function __construct(string $to, string $subject, string $htmlBody)
    {
        $this->to = $to;
        $this->subject = $subject;
        $this->htmlBody = $htmlBody;
    }
    
    public function setFrom(string $email, string $name = ''): self
    {
        $this->from = $email;
        $this->fromName = $name;
        return $this;
    }
    
    public function setTextBody(string $text): self
    {
        $this->textBody = $text;
        return $this;
    }
    
    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }
    
    public function addAttachment(string $path, string $name = ''): self
    {
        $this->attachments[] = ['path' => $path, 'name' => $name ?: basename($path)];
        return $this;
    }
    
    // Getters
    public function getTo(): string { return $this->to; }
    public function getFrom(): string { return $this->from; }
    public function getFromName(): string { return $this->fromName; }
    public function getSubject(): string { return $this->subject; }
    public function getHtmlBody(): string { return $this->htmlBody; }
    public function getTextBody(): string { return $this->textBody; }
    public function getHeaders(): array { return $this->headers; }
    public function getAttachments(): array { return $this->attachments; }
}

/**
 * Base Email Provider Interface
 */
abstract class EmailProvider
{
    protected array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    abstract public function send(EmailMessage $message): bool;
    abstract public function test(): bool;
    abstract public function isConfigured(): bool;
}

/**
 * SMTP Email Provider
 */
class SmtpProvider extends EmailProvider
{
    public function send(EmailMessage $message): bool
    {
        if (!$this->isConfigured()) {
            throw new \Exception('SMTP provider not configured');
        }
        
        // Create SMTP connection
        $smtp = $this->createSmtpConnection();
        
        try {
            // Build email headers
            $headers = $this->buildHeaders($message);
            
            // Send email
            $success = mail(
                $message->getTo(),
                $message->getSubject(),
                $message->getHtmlBody(),
                $headers
            );
            
            return $success;
            
        } catch (\Exception $e) {
            throw new \Exception('SMTP sending failed: ' . $e->getMessage());
        }
    }
    
    public function test(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        
        try {
            $connection = $this->createSmtpConnection();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['host']) && !empty($this->config['port']);
    }
    
    private function createSmtpConnection()
    {
        // Configure PHP mail settings for SMTP
        ini_set('SMTP', $this->config['host']);
        ini_set('smtp_port', $this->config['port']);
        
        if (!empty($this->config['username'])) {
            ini_set('auth_username', $this->config['username']);
            ini_set('auth_password', $this->config['password']);
        }
        
        return true;
    }
    
    private function buildHeaders(EmailMessage $message): string
    {
        $headers = [];
        
        // From header
        if ($message->getFromName()) {
            $headers[] = 'From: ' . $message->getFromName() . ' <' . $message->getFrom() . '>';
        } else {
            $headers[] = 'From: ' . $message->getFrom();
        }
        
        // Content type
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'MIME-Version: 1.0';
        
        // Additional headers
        foreach ($message->getHeaders() as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }
        
        return implode("\r\n", $headers);
    }
}

/**
 * Amazon SES Provider (placeholder)
 */
class SesProvider extends EmailProvider
{
    public function send(EmailMessage $message): bool
    {
        // TODO: Implement Amazon SES API integration
        throw new \Exception('Amazon SES provider not yet implemented');
    }
    
    public function test(): bool
    {
        return $this->isConfigured();
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['access_key']) && !empty($this->config['secret_key']);
    }
}

/**
 * Mailgun Provider (placeholder)
 */
class MailgunProvider extends EmailProvider
{
    public function send(EmailMessage $message): bool
    {
        // TODO: Implement Mailgun API integration
        throw new \Exception('Mailgun provider not yet implemented');
    }
    
    public function test(): bool
    {
        return $this->isConfigured();
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['domain']) && !empty($this->config['secret']);
    }
}

/**
 * SendGrid Provider (placeholder)
 */
class SendGridProvider extends EmailProvider
{
    public function send(EmailMessage $message): bool
    {
        // TODO: Implement SendGrid API integration
        throw new \Exception('SendGrid provider not yet implemented');
    }
    
    public function test(): bool
    {
        return $this->isConfigured();
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['api_key']);
    }
}

/**
 * Postmark Provider (placeholder)
 */
class PostmarkProvider extends EmailProvider
{
    public function send(EmailMessage $message): bool
    {
        // TODO: Implement Postmark API integration
        throw new \Exception('Postmark provider not yet implemented');
    }
    
    public function test(): bool
    {
        return $this->isConfigured();
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['token']);
    }
}
