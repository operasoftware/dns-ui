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
* Class for reading/writing to the list of Template (SOATemplate & NSTemplate) objects in the database.
* Actually stored in separate tables for each type.
*/
class TemplateDirectory extends DBDirectory {
	/**
	* Create the new template in the database.
	* @param Template $template object to add
	*/
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

	/**
	* List all templates of type SOATemplate in the database.
	* @return array of SOATemplate objects
	*/
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

	/**
	* List all templates of type NSTemplate in the database.
	* @return array of NSTemplate objects
	*/
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

	/**
	* Get an SOATemplate from the database by its id.
	* @param int $id of template
	* @return SOATemplate with specified id
	* @throws TemplateNotFound if no SOATemplate with that id exists
	*/
	public function get_soa_template_by_id($id) {
		$stmt = $this->database->prepare('
			SELECT soa_template.*, CASE WHEN config.id IS NULL THEN 0 ELSE 1 END AS default
			FROM soa_template
			LEFT JOIN config ON config.default_soa_template = soa_template.id
			WHERE soa_template.id = ?
		');
		$stmt->bindParam(1, $id, PDO::PARAM_INT);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			return new SOATemplate($row['id'], $row);
		} else {
			throw new TemplateNotFound;
		}
	}

	/**
	* Get an NSTemplate from the database by its id.
	* @param int $id of template
	* @return NSTemplate with specified id
	* @throws TemplateNotFound if no NSTemplate with that id exists
	*/
	public function get_ns_template_by_id($id) {
		$stmt = $this->database->prepare('
			SELECT ns_template.*, CASE WHEN config.id IS NULL THEN 0 ELSE 1 END AS default
			FROM ns_template
			LEFT JOIN config ON config.default_ns_template = ns_template.id
			WHERE ns_template.id = ?
		');
		$stmt->bindParam(1, $id, PDO::PARAM_INT);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			return new NSTemplate($row['id'], $row);
		} else {
			throw new TemplateNotFound;
		}
	}

	/**
	* Get an SOATemplate from the database by its name.
	* @param string $name of template
	* @return SOATemplate with specified name
	* @throws TemplateNotFound if no SOATemplate with that name exists
	*/
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

	/**
	* Get an NSTemplate from the database by its name.
	* @param string $name of template
	* @return NSTemplate with specified name
	* @throws TemplateNotFound if no NSTemplate with that name exists
	*/
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

	/**
	* Set the provided SOA template as the default.
	* @param SOATemplate $template to be set as default
	*/
	public function set_default_soa_template(SOATemplate $template = null) {
		$stmt = $this->database->prepare('UPDATE config SET default_soa_template = ?');
		if(is_null($template)) {
			$stmt->bindParam(1, $template, PDO::PARAM_INT);
		} else {
			$stmt->bindParam(1, $template->id, PDO::PARAM_INT);
		}
		$stmt->execute();
	}

	/**
	* Set the provided NS template as the default.
	* @param NSTemplate $template to be set as default
	*/
	public function set_default_ns_template(NSTemplate $template = null) {
		$stmt = $this->database->prepare('UPDATE config SET default_ns_template = ?');
		if(is_null($template)) {
			$stmt->bindParam(1, $template, PDO::PARAM_INT);
		} else {
			$stmt->bindParam(1, $template->id, PDO::PARAM_INT);
		}
		$stmt->execute();
	}

	/**
	* Delete the template from the database.
	* @param Template $template to be deleted
	*/
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
