<?php
##
## Copyright 2013-2018 Opera Software AS
##
## Licensed under the Apache License, Version 2.0 (the "License");
## you may not use this file except in compliance with the License.
## You may obtain a copy of the License at
##
## http://www.apache.org/licenses/LICENSE-2.0
##
## Unless required by applicable law or agreed to in writing, software
## distributed under the License is distributed on an "AS IS" BASIS,
## WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
## See the License for the specific language governing permissions and
## limitations under the License.
##

/**
* Class for detecting and applying migrations to the database.
*/
class MigrationDirectory extends DBDirectory {
	/**
	* Increment this constant to activate a new migration from the migrations directory
	*/
	const LAST_MIGRATION = 7;

	public function __construct() {
		parent::__construct();
		try {
			$stmt = $this->database->prepare('SELECT MAX(id) FROM migration');
			$stmt->execute();
			list($current_migration) = $stmt->fetch(PDO::FETCH_NUM);
		} catch(PDOException $e) {
			if($e->errorInfo[0] === '42P01') {
				$current_migration = 0;
			} else {
				throw $e;
			}
		}
		if($current_migration < self::LAST_MIGRATION) {
			$this->apply_pending_migrations($current_migration);
		}
	}

	private function apply_pending_migrations($current_migration) {
		openlog('dnsui', LOG_ODELAY, LOG_USER);
		for($migration_id = $current_migration + 1; $migration_id <= self::LAST_MIGRATION; $migration_id++) {
			$filename = str_pad($migration_id, 3, '0', STR_PAD_LEFT).'.php';
			syslog(LOG_INFO, "migration={$filename};object=database;action=apply");
			$migration_name = $filename;
			include('migrations/'.$filename);
			$stmt = $this->database->prepare('INSERT INTO migration VALUES (?, ?, NOW())');
			$stmt->bindParam(1, $migration_id, PDO::PARAM_INT);
			$stmt->bindParam(2, $migration_name, PDO::PARAM_STR);
			$stmt->execute();
		}
		closelog();
	}
}
