<?php
/**
 * Database Connection and Query Builder
 * PDO-based database abstraction layer
 */

declare(strict_types=1);

namespace Core;

use PDO;
use PDOStatement;
use PDOException;

class Database
{
    private PDO $connection;
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }
    
    /**
     * Establish database connection
     */
    private function connect(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->config['host'],
            $this->config['port'] ?? 3306,
            $this->config['name']
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        try {
            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $options
            );
        } catch (PDOException $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute a query
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new \Exception('Query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
        }
    }
    
    /**
     * Insert a record
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return (int)$this->connection->lastInsertId();
    }
    
    /**
     * Update records
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        return $this->query($sql, $params)->rowCount();
    }
    
    /**
     * Delete records
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params)->rowCount();
    }
    
    /**
     * Find a single record
     */
    public function find(string $table, array $conditions = [], string $columns = '*'): ?array
    {
        $where = '';
        $params = [];
        
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $column => $value) {
                $whereParts[] = "{$column} = :{$column}";
                $params[$column] = $value;
            }
            $where = 'WHERE ' . implode(' AND ', $whereParts);
        }
        
        $sql = "SELECT {$columns} FROM {$table} {$where} LIMIT 1";
        $result = $this->query($sql, $params)->fetch();
        
        return $result ?: null;
    }
    
    /**
     * Find multiple records
     */
    public function findAll(string $table, array $conditions = [], string $columns = '*', string $orderBy = '', int $limit = 0): array
    {
        $where = '';
        $params = [];
        
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $column => $value) {
                $whereParts[] = "{$column} = :{$column}";
                $params[$column] = $value;
            }
            $where = 'WHERE ' . implode(' AND ', $whereParts);
        }
        
        $order = $orderBy ? "ORDER BY {$orderBy}" : '';
        $limitClause = $limit > 0 ? "LIMIT {$limit}" : '';
        
        $sql = "SELECT {$columns} FROM {$table} {$where} {$order} {$limitClause}";
        return $this->query($sql, $params)->fetchAll();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->connection->rollback();
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }
    
    /**
     * Execute raw SQL
     */
    public function raw(string $sql): bool
    {
        return $this->connection->exec($sql) !== false;
    }
}
