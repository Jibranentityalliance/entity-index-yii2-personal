<?php

/**
 * Read-only connection to the EIN skip-tracing production database (source of `manual_companies`).
 * Console only (registered in config/console.php). Used by `php yii ein-sync/companies`.
 *
 * Every session is forced READ ONLY and gets a short per-statement timeout, so nothing this app runs can write to
 * or tie up the production server. Never call schema methods (getTableSchema() etc.) on it: write explicit SQL.
 */

return [
    'class' => \yii\db\Connection::class,
    'dsn' => env('DB_DSN_EIN_SKIP_TRACING'),
    'username' => env('DB_USER_EIN_SKIP_TRACING'),
    'password' => env('DB_PASS_EIN_SKIP_TRACING', ''),
    'charset' => 'utf8mb4',
    'enableSchemaCache' => false,
    'attributes' => [
        PDO::ATTR_TIMEOUT => 5, // connect timeout, seconds
    ],
    'on afterOpen' => function (\yii\base\Event $event) {
        /** @var \yii\db\Connection $db */
        $db = $event->sender;
        $db->createCommand('SET SESSION TRANSACTION READ ONLY')->execute();
        // Abort any statement that runs longer than 5 seconds. MariaDB and MySQL spell this differently.
        $db->createCommand(stripos($db->getServerVersion(), 'mariadb') !== false
            ? 'SET SESSION max_statement_time = 5'
            : 'SET SESSION MAX_EXECUTION_TIME = 5000')->execute();
    },
];
