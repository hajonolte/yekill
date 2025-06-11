<?php
/**
 * Custom Autoloader for YeKill Newsletter System
 * PSR-4 compatible autoloader without Composer dependency
 */

declare(strict_types=1);

namespace Core;

class Autoloader
{
    private array $prefixes = [];
    
    public function __construct()
    {
        // Register default namespaces
        $this->addNamespace('Core', APP_ROOT . '/core');
        $this->addNamespace('App', APP_ROOT . '/app');
        $this->addNamespace('Database', APP_ROOT . '/database');
    }
    
    /**
     * Register the autoloader
     */
    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }
    
    /**
     * Add a namespace prefix
     */
    public function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . '/';
        
        if (!isset($this->prefixes[$prefix])) {
            $this->prefixes[$prefix] = [];
        }
        
        array_push($this->prefixes[$prefix], $baseDir);
    }
    
    /**
     * Load a class file
     */
    public function loadClass(string $class): bool
    {
        $prefix = $class;
        
        while (false !== $pos = strrpos($prefix, '\\')) {
            $prefix = substr($class, 0, $pos + 1);
            $relativeClass = substr($class, $pos + 1);
            
            $mappedFile = $this->loadMappedFile($prefix, $relativeClass);
            if ($mappedFile) {
                return $mappedFile;
            }
            
            $prefix = rtrim($prefix, '\\');
        }
        
        return false;
    }
    
    /**
     * Load the mapped file for a namespace prefix and relative class
     */
    private function loadMappedFile(string $prefix, string $relativeClass): bool
    {
        if (!isset($this->prefixes[$prefix])) {
            return false;
        }
        
        foreach ($this->prefixes[$prefix] as $baseDir) {
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            
            if ($this->requireFile($file)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Require a file if it exists
     */
    private function requireFile(string $file): bool
    {
        if (file_exists($file)) {
            require $file;
            return true;
        }
        
        return false;
    }
}
