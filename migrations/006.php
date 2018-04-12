<?php
$migration_name = 'Add PHP_AUTH support';

// Add new value PHP_AUTH to enum type auth_realm.
$this->database->exec("ALTER TYPE auth_realm ADD VALUE 'PHP_AUTH'");
