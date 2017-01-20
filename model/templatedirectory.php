<?php
##
## Copyright 2013-2017 Opera Software AS
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

class TemplateDirectory extends DBDirectory {
	public function add_template(Template $template) {
		switch(get_class($template)) {
		case 'SOATemplate':
			$stmt = $this->database->prepare('INSERT INTO "soa_template" (name, primary_ns, contact, refresh, retry, expire, default_ttl, soa_ttl) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
			$stmt->bindParam(1, $template->name, PDO::PARAM_STR);
			$stmt->bindParam(2, $template->primary_ns, PDO::PARAM_STR);
			$stmt->bindParam(3, $template->contact, PDO::PARAM_STR);
			$stmt->bindParam(4, $template->refresh, PDO::PARAM_INT);
			$stmt->bindParam(5, $template->retry, PDO::PARAM_INT);
			$stmt->bindParam(6, $template->expire, PDO::PARAM_INT);
			$stmt->bindParam(7, $template->default_ttl, PDO::PARAM_INT);
			$stmt->bindParam(8, $template->soa_ttl, PDO::PARAM_INT);
			$stmt->execute();
			$template->id = $this->database->lastInsertId('soa_template_id_seq');
			break;
		case 'NSTemplate':
			$stmt = $this->database->prepare('INSERT INTO "ns_template" (name, nameservers) VALUES (?, ?)');
			$stmt->bindParam(1, $template->name, PDO::PARAM_STR);
			$stmt->bindParam(2, $template->nameservers, PDO::PARAM_STR);
			$stmt->execute();
			$template->id = $this->database->lastInsertId('ns_template_id_seq');
			break;
		}
	}

	public function list_soa_templates() {
		$stmt = $this->database->prepare('
			SELECT soa_template.*, CASE WHEN config.id IS NULL THEN 0 ELSE 1 END AS default
			FROM soa_template
			LEFT JOIN config ON config.default_soa_template = soa_template.id
			ORDER BY name
		');
		$stmt->execute();
		$templates = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$templates[] = new SOATemplate($row['id'], $row);
		}
		return $templates;
	}

	public function list_ns_templates() {
		$stmt = $this->database->prepare('
			SELECT ns_template.*, CASE WHEN config.id IS NULL THEN 0 ELSE 1 END AS default
			FROM ns_template
			LEFT JOIN config ON config.default_ns_template = ns_template.id
			ORDER BY name
		');
		$stmt->execute();
		$templates = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$templates[] = new NSTemplate($row['id'], $row);
		}
		return $templates;
	}

	public function get_soa_template_by_id($name) {
		$stmt = $this->database->prepare('
			SELECT soa_template.*, CASE WHEN config.id IS NULL THEN 0 ELSE 1 END AS default
			FROM soa_template
			LEFT JOIN config ON config.default_soa_template = soa_template.id
			WHERE soa_template.id = ?
		');
		$stmt->bindParam(1, $name, PDO::PARAM_INT);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			return new SOATemplate($row['id'], $row);
		} else {
			throw new TemplateNotFound;
		}
	}

	public function get_ns_template_by_id($name) {
		$stmt = $this->database->prepare('
			SELECT ns_template.*, CASE WHEN config.id IS NULL THEN 0 ELSE 1 END AS default
			FROM ns_template
			LEFT JOIN config ON config.default_ns_template = ns_template.id
			WHERE ns_template.id = ?
		');
		$stmt->bindParam(1, $name, PDO::PARAM_INT);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			return new NSTemplate($row['id'], $row);
		} else {
			throw new TemplateNotFound;
		}
	}

	public function get_soa_template_by_name($name) {
		$stmt = $this->database->prepare('
			SELECT soa_template.*, CASE WHEN config.id IS NULL THEN 0 ELSE 1 END AS default
			FROM soa_template
			LEFT JOIN config ON config.default_soa_template = soa_template.id
			WHERE name = ?
		');
		$stmt->bindParam(1, $name, PDO::PARAM_STR);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			return new SOATemplate($row['id'], $row);
		} else {
			throw new TemplateNotFound;
		}
	}

	public function get_ns_template_by_name($name) {
		$stmt = $this->database->prepare('
			SELECT ns_template.*, CASE WHEN config.id IS NULL THEN 0 ELSE 1 END AS default
			FROM ns_template
			LEFT JOIN config ON config.default_ns_template = ns_template.id
			WHERE name = ?
		');
		$stmt->bindParam(1, $name, PDO::PARAM_STR);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			return new NSTemplate($row['id'], $row);
		} else {
			throw new TemplateNotFound;
		}
	}

	public function set_default_template(Template $template) {
		switch(get_class($template)) {
		case 'SOATemplate':
			$stmt = $this->database->prepare('UPDATE config SET default_soa_template = ?');
			break;
		case 'NSTemplate':
			$stmt = $this->database->prepare('UPDATE config SET default_ns_template = ?');
			break;
		}
		$stmt->bindParam(1, $template->id, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function delete_template(Template $template) {
		switch(get_class($template)) {
		case 'SOATemplate':
			$stmt = $this->database->prepare('DELETE FROM soa_template WHERE id = ?');
			break;
		case 'NSTemplate':
			$stmt = $this->database->prepare('DELETE FROM ns_template WHERE id = ?');
			break;
		}
		$stmt->bindParam(1, $template->id, PDO::PARAM_INT);
		$stmt->execute();
	}
}

class TemplateNotFound extends RuntimeException {}
