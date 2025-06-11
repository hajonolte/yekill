<?php
/**
 * HTTP Response Handler
 * Manages outgoing HTTP responses
 */

declare(strict_types=1);

namespace Core;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $content = '';
    private bool $sent = false;
    
    /**
     * Set HTTP status code
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }
    
    /**
     * Get HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    
    /**
     * Set response header
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }
    
    /**
     * Set multiple headers
     */
    public function setHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
        return $this;
    }
    
    /**
     * Get response headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
    
    /**
     * Set response content
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }
    
    /**
     * Get response content
     */
    public function getContent(): string
    {
        return $this->content;
    }
    
    /**
     * Send JSON response
     */
    public function json(array $data, int $statusCode = 200): self
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Content-Type', 'application/json');
        $this->setContent(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $this;
    }
    
    /**
     * Send HTML response
     */
    public function html(string $content, int $statusCode = 200): self
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Content-Type', 'text/html; charset=utf-8');
        $this->setContent($content);
        return $this;
    }
    
    /**
     * Send plain text response
     */
    public function text(string $content, int $statusCode = 200): self
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->setContent($content);
        return $this;
    }
    
    /**
     * Send redirect response
     */
    public function redirect(string $url, int $statusCode = 302): self
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Location', $url);
        return $this;
    }
    
    /**
     * Send file download response
     */
    public function download(string $filePath, string $filename = null): self
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }
        
        $filename = $filename ?: basename($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        
        $this->setHeader('Content-Type', $mimeType);
        $this->setHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
        $this->setHeader('Content-Length', (string)filesize($filePath));
        $this->setContent(file_get_contents($filePath));
        
        return $this;
    }
    
    /**
     * Send error response
     */
    public function error(string $message, int $statusCode = 500): self
    {
        $this->setStatusCode($statusCode);
        
        if ($this->isJsonRequest()) {
            return $this->json(['error' => $message], $statusCode);
        } else {
            return $this->html("<h1>Error {$statusCode}</h1><p>{$message}</p>", $statusCode);
        }
    }
    
    /**
     * Check if request expects JSON
     */
    private function isJsonRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json') || 
               str_contains($_SERVER['HTTP_CONTENT_TYPE'] ?? '', 'application/json');
    }
    
    /**
     * Send the response
     */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }
        
        // Send status code
        http_response_code($this->statusCode);
        
        // Send headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        
        // Send content
        echo $this->content;
        
        $this->sent = true;
    }
    
    /**
     * Check if response has been sent
     */
    public function isSent(): bool
    {
        return $this->sent;
    }
    
    /**
     * Set cookie
     */
    public function setCookie(
        string $name,
        string $value,
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true
    ): self {
        setcookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
        return $this;
    }
    
    /**
     * Delete cookie
     */
    public function deleteCookie(string $name, string $path = '/', string $domain = ''): self
    {
        setcookie($name, '', time() - 3600, $path, $domain);
        return $this;
    }
}
