<?php

namespace app\libraries\database;

use Psr\Log\AbstractLogger;

class QueryLogger extends AbstractLogger {
    /**
     * @var mixed[][]
     *
     * This is a 2D array containing database queries that have been logged.
     * Each element in a 2 element array containing the query and an array of parameters.
     */
    protected array $queries = [];

    /**
     * @param $level
     * @param \Stringable|string $message
     * @param array<mixed> $context
     * @return void
     */
    public function log($level, \Stringable|string $message, array $context = []): void {
        if ($level === "debug") { // SQL queries are in the debug level
            if (!isset($context['sql'])) {
                return; // Ignore if SQL is not set
            }
            $this->queries[] = [
                $context['sql'],
                $context['params']
            ];
        }
    }

    /**
     * @return mixed[][]
     */
    public function getQueries(): array {
        return $this->queries;
    }
}
