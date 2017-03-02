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

try {
	$zone = $zone_dir->get_zone_by_name($router->vars['name'].'.');
} catch(ZoneNotFound $e) {
	require('views/error404.php');
	exit;
}

if(!$active_user->admin && !$active_user->access_to($zone)) {
	require('views/error403.php');
	exit;
}

if(isset($_FILES['zonefile'])) {
	$lines = file($_FILES['zonefile']['tmp_name']);
	try {
		$modifications = import_bind9_zonefile($zone, $lines, $_POST['comment_handling']);
	} catch(ZoneImportError $e) {
		$content = new PageSection('zone_update_failed');
		$content->set('message', $e->getMessage());
	}
	if(!isset($content)) {
		$content = new PageSection('zoneimport');
		$content->set('zone', $zone);
		$content->set('modifications', $modifications);
	}
} else {
	redirect('/zones/'.urlencode($zone->name));
}

$page = new PageSection('base');
$page->set('title', 'Import preview for '.DNSZoneName::unqualify(punycode_to_utf8($zone->name)).' zone update');
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());

echo $page->generate();

function import_bind9_zonefile($zone, $lines, $comment_handling) {
	global $active_user;
	$rrsets = $zone->list_resource_record_sets();
	$old_rrsets = $rrsets;
	$origin = $zone->name;
	$default_ttl = $zone->soa->default_ttl;
	$previous_name = null;
	$in_record = false;
	$continuation = false;
	$line_number = 0;
	$new_rrsets = array();
	$new_rrset_comments = array();
	$mainregexp = '/^
	               (;)?                                        # Starting semicolon, indicating commented-out (disabled) record
	               (?:\s+|(\S+)\s+)                            # Record name, or whitespace to use same name as previous line
	               (?:((?:[0-9]+[SMHDW]?)+)\s+)?               # Optional TTL
	               (?:IN\s+)?                                  # Optional class, not captured
	               (A|AAAA|CNAME|LOC|MX|NS|PTR|SOA|SRV|TXT)\s+ # Record type
	               (.*)                                        # Record data
	               $/ix';
	$generateregexp = '/^
	                   (;)?                                        # Starting semicolon, indicating commented-out (disabled) record
	                   \$GENERATE\s+                               # "$GENERATE" string
	                   ([0-9]+)-([0-9]+)(?:\/([0-9]+))?\s+         # Range, start-stop and optional step
	                   (\S+)\s+                                    # Record name
	                   (?:((?:[0-9]+[SMHDW]?)+)\s+)?               # Optional TTL
	                   (?:IN\s+)?                                  # Optional class, not captured
	                   (A|AAAA|CNAME|LOC|MX|NS|PTR|SOA|SRV|TXT)\s+ # Record type
	                   (.*)                                        # Record data
	                   $/ix';
	$generate_start = null;
	$generate_stop = null;
	$generate_step = null;
	foreach($lines as $line) {
		$line_number++;
		$line = rtrim($line);
		if($continuation) {
			try {
				list($content_parsed, $more_comment, $continuation) = parse_content(trim($line), $content_parsed);
				$comment .= " $more_comment";
			} catch(UnterminatedString $e) {
				throw new ZoneImportError("Unterminated string on line $line_number");
			}
		} elseif(preg_match('/^\$ORIGIN\s+(\S+)\s*(?:;.*)?$/', $line, $matches)) {
			// $ORIGIN directive
			$origin = $matches[1];
		} elseif(preg_match('/^\$TTL\s+(\S+)\s*(?:;.*)?$/', $line, $matches)) {
			// $TTL directive
			$default_ttl = DNSTime::expand($matches[1]);
		} elseif(preg_match($generateregexp, $line, $matches)) {
			// $GENERATE directive
			// Syntax: $GENERATE range lhs [ttl] [class] type rhs [comment]
			// range: This can be one of two forms: start-stop or start-stop/step. If
			//        the first form is used, then step is set to 1. start, stop and
			//        step must be positive integers between 0 and (2^31)-1. start must
			//        not be larger than stop.
			// lhs: This describes the owner name of the resource records to be created.
			//      Any single $ (dollar sign) symbols within the lhs string are
			//      replaced by the iterator value. To get a $ in the output, you need
			//      to escape the $ using a backslash \, e.g. \$. The $ may optionally
			//      be followed by modifiers which change the offset from the iterator,
			//      field width and base. Modifiers are introduced by a { (left brace)
			//      immediately following the $ as ${offset[,width[,base]]}. For
			//      example, ${-20,3,d} subtracts 20 from the current value, prints the
			//      result as a decimal in a zero-padded field of width 3. Available
			//      output forms are decimal (d), octal (o), hexadecimal (x or X for
			//      uppercase) and nibble (n or N for uppercase). The default modifier
			//      is ${0,0,d}. If the lhs is not absolute, the current $ORIGIN is
			//      appended to the name.
			// ttl, class, type: As normal
			// rhs: rhs, optionally, quoted string.
			$disabled = ($matches[1] == ';');
			$generate_start = $matches[2];
			$generate_stop = $matches[3];
			$generate_step = ($matches[4] == '' ? 1 : $matches[4]);
			$name = $matches[5];
			$ttl = empty($matches[6]) ? $default_ttl : $matches[6];
			$type = $matches[7];
			try {
				list($content_parsed, $comment, $continuation) = parse_content($matches[8]);
			} catch(UnterminatedString $e) {
				throw new ZoneImportError("Unterminated string on line $line_number");
			}
			$in_record = true;
		} elseif(preg_match($mainregexp, $line, $matches)) {
			// Standard resource record
			$disabled = ($matches[1] == ';');
			$name = $matches[2];
			$ttl = empty($matches[3]) ? $default_ttl : $matches[3];
			$type = $matches[4];
			try {
				list($content_parsed, $comment, $continuation) = parse_content($matches[5]);
			} catch(UnterminatedString $e) {
				throw new ZoneImportError("Unterminated string on line $line_number");
			}
			$in_record = true;
		} elseif(preg_match('/^\s*;/', $line)) {
			// Generic comment line
		} elseif($line == '') {
			// Blank line
		} else {
			throw new ZoneImportError("Unrecognized line '$line' on line $line_number");
		}
		if($in_record && !$continuation) {
			$in_record = false;
			$content = '';
			$ignore_whitespace = false;
			foreach($content_parsed as $block) {
				if($block['type'] == 'quoted') {
					$content .= $block['value'];
					$ignore_whitespace = true;
				} else {
					if($ignore_whitespace == false || trim($block['value']) != '') {
						if($content != '') $content .= ' ';
						$content .= trim($block['value']);
					}
				}
			}
			$record = new StdClass;
			$record->disabled = $disabled;
			$record->name = $name == '' ? $previous_name : strtolower($name);
			$record->ttl = $ttl;
			$record->type = strtoupper($type);
			$record->content = $content;
			$record->comment = $comment;
			$previous_name = $record->name;
			$records = array();
			if(is_null($generate_start)) {
				$records[] = $record;
			} else {
				for($i = $generate_start; $i <= $generate_stop; $i += $generate_step) {
					$generated_record = clone $record;
					$generated_record->name = str_replace('\\$', '$', preg_replace_callback('/(?<!\\\\)\$(?:{(-?[0-9]+),([0-9]+),([doxXnN])})?/', function($matches) use($i) {return parse_generate_modifiers($matches, $i);}, $generated_record->name));
					$generated_record->content = str_replace('\\$', '$', preg_replace_callback('/(?<!\\\\)\$(?:{(-?[0-9]+),([0-9]+),([doxXnN])})?/', function($matches) use($i) {return parse_generate_modifiers($matches, $i);}, $generated_record->content));
					$records[] = $generated_record;
				}
			}
			foreach($records as $record) {
				$disabled = $record->disabled;
				if($disabled && $comment_handling == 'ignore') continue;
				$name = DNSName::canonify($record->name, $origin);
				$ttl = DNSTime::expand($record->ttl);
				$type = $record->type;
				try {
					$content = DNSContent::encode(DNSContent::from_bind9($record->content, $type, $zone->name), $type);
				} catch(ErrorException $e) {
					if($disabled) {
						// We tried to parse something that looked like a commented-out record, but it was probably just a comment
						continue;
					}
					throw new ZoneImportError("Error parsing resource record on line $line_number");
				}
				if(!isset($new_rrsets[$name.' '.$type])) {
					$rrset = new ResourceRecordSet;
					$rrset->name = $name;
					$rrset->type = $type;
					$rrset->ttl = $ttl;
					$new_rrsets[$name.' '.$type] = $rrset;
				}
				if($type == 'SOA') $record->comment = '';
				$rr = new ResourceRecord;
				$rr->content = $content;
				$rr->disabled = $disabled;
				$new_rrsets[$name.' '.$type]->add_resource_record($rr);
				$new_rrset_comments[$name.' '.$type] = $record->comment;
			}
			$generate_start = null;
			$generate_stop = null;
			$generate_step = null;
		}
	}

	// Compare existing content with new content and collate the differences
	$modifications = array('add' => array(), 'update' => array(), 'delete' => array());
	foreach($new_rrsets as $ref => $new_rrset) {
		if($new_rrset->type == 'SOA') continue;
		if(isset($old_rrsets[$ref])) {
			$old_rrset = $old_rrsets[$ref];
			$old_rrs = $old_rrset->list_resource_records();
			$new_rrs = $new_rrset->list_resource_records();
			$old_comment = $old_rrset->merge_comment_text();
			$new_comment = $new_rrset_comments[$ref];
			$rrset_modifications = array();
			if($old_rrset->ttl != $new_rrset->ttl) {
				$rrset_modifications[] = 'TTL changed from '.DNSTime::abbreviate($old_rrset->ttl).' to '.DNSTime::abbreviate($new_rrset->ttl);
			}
			foreach($new_rrs as $new_rr) {
				$rr_match = false;
				foreach($old_rrs as $rr_ref => $old_rr) {
					if($new_rr->content == $old_rr->content) {
						$rr_match = true;
						unset($old_rrs[$rr_ref]);
						break;
					}
				}
				if($rr_match) {
					if($new_rr->disabled && !$old_rr->disabled) {
						$rrset_modifications[] = 'Disabled RR: '.$new_rr->content;
					}
					if(!$new_rr->disabled && $old_rr->disabled) {
						$rrset_modifications[] = 'Enabled RR: '.$new_rr->content;
					}
				} else {
					// New RR
					$rrset_modifications[] = 'New RR: '.$new_rr->content;
				}
			}
			foreach($old_rrs as $old_rr) {
				// Deleted RR
				$rrset_modifications[] = 'Deleted RR: '.$old_rr->content;
			}
			if($old_comment == $new_comment) {
				foreach($old_rrset->list_comments() as $comment) {
					$new_rrset->add_comment($comment);
				}
			} else {
				$new_rrset->clear_comments();
				if($old_comment != '') $rrset_modifications[] = 'Deleted comment: '.$old_comment;
				if($new_comment != '') {
					$rrset_modifications[] = 'New comment: '.$new_comment;
					$comment = new Comment;
					$comment->content = $new_comment;
					$comment->account = $active_user->uid;
					$new_rrset->add_comment($comment);
				}
			}
			if(count($rrset_modifications) > 0) {
				$modifications['update'][$ref] = array();
				$modifications['update'][$ref]['new'] = $new_rrset;
				$modifications['update'][$ref]['changelist'] = $rrset_modifications;
				$modifications['update'][$ref]['json'] = build_json('update', $new_rrset, $zone->name);
			}
		} else {
			// New RRSet
			$new_comment = $new_rrset_comments[$ref];
			if($new_comment != '') {
				$comment = new Comment;
				$comment->content = $new_comment;
				$comment->account = $active_user->uid;
				$new_rrset->add_comment($comment);
			}
			$modifications['add'][$ref] = array();
			$modifications['add'][$ref]['new'] = $new_rrset;
			$modifications['add'][$ref]['json'] = build_json('add', $new_rrset, $zone->name);
		}
	}
	foreach($old_rrsets as $ref => $old_rrset) {
		if($old_rrset->type == 'SOA') continue;
		if(!isset($new_rrsets[$ref])) {
			// Deleted RRSet
			$modifications['delete'][$ref] = array();
			$modifications['delete'][$ref]['old'] = $old_rrset;
			$modifications['delete'][$ref]['json'] = build_json('delete', $old_rrset, $zone->name);
		}
	}
	return $modifications;
}

function parse_content($input, $content = array()) {
	// Content parsing
	$in_string = false;
	$escape = false;
	$in_comment = false;
	$comment = '';
	$continuation = (count($content) > 0);
	foreach(str_split($input) as $char) {
		if($in_comment) {
			$comment .= $char;
		} elseif($in_string) {
			if($escape) {
				$string .= $char;
				$escape = false;
			} elseif($char == '\\') {
				$escape = true;
			} elseif($char == '"') {
				$in_string = false;
				$content[] = array('type' => 'quoted', 'value' => $string);
			} else {
				$string .= $char;
			}
		} else {
			if($char == '"') {
				if(isset($block)) {
					$content[] = $block;
					unset($block);
				}
				$in_string = true;
				$string = '';
			} elseif($char == '(') {
				$continuation = true;
			} elseif($char == ')') {
				$continuation = false;
			} elseif($char == ';') {
				$in_comment = true;
			} elseif(isset($block)) {
				$block['value'] .= $char;
			} else {
				$block = array('type' => 'unquoted', 'value' => $char);
			}
		}
	}
	if(isset($block)) {
		$content[] = $block;
	}
	if($in_string) {
		throw new UnterminatedString;
	}
	return array($content, trim($comment), $continuation);
}

/**
* Modifiers are introduced by a { (left brace) immediately following the $ as
* ${offset[,width[,base]]}. For example, ${-20,3,d} subtracts 20 from the
* current value, prints the result as a decimal in a zero-padded field of width
* 3. Available output forms are decimal (d), octal (o), hexadecimal (x or X for
* uppercase) and nibble (n or N for uppercase). The default modifier is
* ${0,0,d}.
*
* In nibble mode the value will be treated as if it was a reversed hexadecimal
* string with each hexadecimal digit as a separate label. The width field
* includes the label separator.
*/
function parse_generate_modifiers($modifiers, $i) {
	if(!isset($modifiers[1])) return $i;
	$offset = intval($modifiers[1]);
	$width = $modifiers[2];
	$base = $modifiers[3];
	$val = $i + $offset;
	switch($base) {
	case 'o':
		$val = decoct($val);
		break;
	case 'x':
		$val = dechex($val);
		break;
	case 'X':
		$val = strtoupper(dechex($val));
		break;
	case 'n':
		$val = implode('.', str_split(dechex($val)));
		break;
	case 'N':
		$val = strtoupper(implode('.', str_split(dechex($val))));
		break;
	}
	$val = str_pad($val, $width, '0', STR_PAD_LEFT);
	return $val;
}

function build_json($action, $rrset, $zonename) {
	$data = new StdClass;
	$data->action = $action;
	$data->name = DNSName::abbreviate($rrset->name, $zonename);
	$data->type = $rrset->type;
	$data->ttl = $rrset->ttl;
	if($action != 'add') {
		$data->oldname = $data->name;
		$data->oldtype = $data->type;
	}
	if($action != 'delete') {
		$data->records = array();
		foreach($rrset->list_resource_records() as $rr) {
			$rr_data = new StdClass;
			$rr_data->content = DNSContent::decode($rr->content, $rrset->type);
			$rr_data->enabled = $rr->disabled ? 'No' : 'Yes';
			$data->records[] = $rr_data;
		}
		$data->comment = $rrset->merge_comment_text();
	}
	return json_encode($data);
}

class UnterminatedString extends RuntimeException {}
class ZoneImportError extends RuntimeException {}
