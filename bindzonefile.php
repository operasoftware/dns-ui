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
* Class for parsing a Bind format zone file
*/
class BindZonefile {
	private $data;

	/**
	* Create the Bind_zonefile object with the provided data
	* @param string $data the raw zone file
	*/
	public function __construct($data) {
		$this->data = explode("\n", $data);
	}

	/**
	* Generate a new set of RRSets for the given zone from the Bind zone file data
	*/
	public function parse_into_rrsets(Zone $zone, $comment_handling) {
		global $active_user;
		$origin = $zone->name;
		$default_ttl = is_null($zone->soa) ? 60 : $zone->soa->default_ttl;
		$previous_name = null;
		$type = null;
		$in_record = false;
		$continuation = false;
		$line_number = 0;
		$new_rrsets = array();
		$new_rrset_comments = array();
		$recordtypes = 'A|AAAA|ALIAS|CAA|CNAME|DHCID|LOC|MX|NAPTR|NS|PTR|SOA|SRV|SSHFP|TXT';
		$mainregexp = '/^
					   (;)?                                    # Starting semicolon, indicating commented-out (disabled) record
					   (?:\s+|(\S+)\s+)                        # Record name, or whitespace to use same name as previous line
					   (?:((?:[0-9]+[SMHDW]?)+)\s+)?           # Optional TTL
					   (?:IN\s+)?                              # Optional class, not captured
					   ('.$recordtypes.')\s+                   # Record type
					   (.*)                                    # Record data
					   $/ix';
		$generateregexp = '/^
						   (;)?                                # Starting semicolon, indicating commented-out (disabled) record
						   \$GENERATE\s+                       # "$GENERATE" string
						   ([0-9]+)-([0-9]+)(?:\/([0-9]+))?\s+ # Range, start-stop and optional step
						   (\S+)\s+                            # Record name
						   (?:((?:[0-9]+[SMHDW]?)+)\s+)?       # Optional TTL
						   (?:IN\s+)?                          # Optional class, not captured
						   ('.$recordtypes.')\s+               # Record type
						   (.*)                                # Record data
						   $/ix';
		$generate_start = null;
		$generate_stop = null;
		$generate_step = null;
		foreach($this->data as $line) {
			$line_number++;
			$line = rtrim($line);
			if($continuation) {
				assert(isset($comment));
				assert(isset($content_parsed));
				try {
					list($content_parsed, $more_comment, $continuation) = $this->parse_content(trim($line), $type, $content_parsed);
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
					list($content_parsed, $comment, $continuation) = $this->parse_content($matches[8], $type);
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
					list($content_parsed, $comment, $continuation) = $this->parse_content($matches[5], $type);
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
						$generated_record->name = str_replace('\\$', '$', preg_replace_callback('/(?<!\\\\)\$(?:{(-?[0-9]+),([0-9]+),([doxXnN])})?/', function($matches) use($i) {return $this->parse_generate_modifiers($matches, $i);}, $generated_record->name));
						$generated_record->content = str_replace('\\$', '$', preg_replace_callback('/(?<!\\\\)\$(?:{(-?[0-9]+),([0-9]+),([doxXnN])})?/', function($matches) use($i) {return $this->parse_generate_modifiers($matches, $i);}, $generated_record->content));
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
						$content = DNSContent::encode($record->content, $type, $zone->name);
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
		foreach($new_rrset_comments as $index => $commenttext) {
			$comment = new Comment;
			$comment->content = $commenttext;
			$comment->account = $active_user->uid;
			$new_rrsets[$index]->add_comment($comment);
		}
		return $new_rrsets;
	}

	private function parse_content($input, $type, $content = array()) {
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
				assert(isset($string));
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
				if($char == '"' && $type == 'TXT') {
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
	private function parse_generate_modifiers($modifiers, $i) {
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
}

class UnterminatedString extends RuntimeException {}
class ZoneImportError extends RuntimeException {}
