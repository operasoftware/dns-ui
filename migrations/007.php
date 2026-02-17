<?php
$migration_name = 'Add zone super administrator role';

$this->database->exec("ALTER TYPE access_level ADD VALUE IF NOT EXISTS 'zone-super-administrator'");
