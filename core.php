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

chdir(dirname(__FILE__));
mb_internal_encoding('UTF-8');
setlocale(LC_CTYPE, "en_US.UTF-8");
date_default_timezone_set('UTC');
set_error_handler('exception_error_handler');
spl_autoload_register('autoload_model');

require('pagesection.php');

$config_file = 'config/config.ini';
if(file_exists($config_file)) {
	$config = parse_ini_file($config_file, true);
} else {
	throw new Exception("Config file $config_file does not exist.");
}

require('router.php');
require('routes.php');
require('ldap.php');
require('powerdns.php');
require('email.php');

$ldap = new LDAP($config['ldap']['hostname'], $config['ldap']['bind_dn'], $config['ldap']['bind_password']);
setup_database();

// Convert all non-fatal errors into exceptions
function exception_error_handler($errno, $errstr, $errfile, $errline) {
	throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}

// Autoload needed model files
function autoload_model($classname) {
	global $base_path;
	$classname = preg_replace('/[^a-z]/', '', strtolower($classname)); # Prevent directory traversal and sanitize name
	$filename = path_join($base_path, 'model', $classname.'.php');
	if(file_exists($filename)) {
		include($filename);
	} else {
		eval("class $classname {}");
		throw new InvalidArgumentException("Attempted to load a class $classname that did not exist.");
	}
}

// Setup database connection and models
function setup_database() {
	global $config, $database, $powerdns, $user_dir, $zone_dir, $template_dir;
	$database = new PDO($config['database']['dsn'], $config['database']['username'], $config['database']['password']);
	$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$powerdns = new PowerDNS($config['powerdns']['api_url'], $config['powerdns']['api_key']);
	$user_dir = new UserDirectory;
	$zone_dir = new ZoneDirectory;
	$template_dir = new TemplateDirectory;
}

/**
 * Join a sequence of partial paths into a complete path
 * e.g. pathJoin("foo", "bar") -> foo/bar
 *      pathJoin("f/oo", "b/ar") -> f/oo/b/ar
 *      pathJoin("/foo/b/", "ar") -> "/foo/b/ar"
 * @param string part of path
 * @return string joined path
 */
function path_join() {
	$args = func_get_args();
	$parts = array();
	foreach($args as $arg) {
		$parts = array_merge($parts, explode("/", $arg));
	}
	$parts = array_filter($parts, function($x) {return (bool)($x);});
	$prefix = $args[0][0] == "/" ? "/" : "";
	return $prefix . implode("/", $parts);
}

define('ESC_HTML', 1);
define('ESC_URL', 2);
define('ESC_URL_ALL', 3);
define('ESC_NONE', 9);

function out($string, $escaping = ESC_HTML) {
	switch($escaping) {
	case ESC_HTML:
		echo htmlspecialchars($string);
		break;
	case ESC_URL:
		echo urlencode($string);
		break;
	case ESC_URL_ALL:
		echo rawurlencode($string);
		break;
	case ESC_NONE:
		echo $string;
		break;
	default:
		throw new InvalidArgumentException("Escaping format $escaping not known.");
	}
}

/**
 * Short-name HTML escape convenience function
 * @param string $string string to escape
 * @return string HTML-escaped string
 */
function hesc($string) {
	return htmlspecialchars($string);
}

function english_list($array) {
	if(count($array) == 1) return reset($array);
	else return implode(', ', array_slice($array, 0, -1)).' and '.end($array);
}

/**
 * Perform an HTTP redirect to the given URL (or the current URL if none given)
 * @param string|null $url URL to redirect to
 * @param string $type HTTP response code/name to use
 */
function redirect($url = null, $type = '303 See other') {
	global $absolute_request_url;
	if(is_null($url)) $url = $absolute_request_url;
	header("HTTP/1.1 $type");
	header("Location: $url");
	exit;
}

/**
 * Given a set of defaults and an array of querystring data, convert to a simpler
 * easy-to-read form and redirect if any conversion was done.  Also return array
 * combining defaults with any querysting parameters that do not match defaults.
 * @param array $defaults associative array of default values
 * @param array $values associative array of querystring data
 * @return array result of combining defaults and querystring data
 */
function simplify_search($defaults, $values) {
	global $absolute_request_url;
	$simplify = false;
	$simplified = array();
	foreach($defaults as $key => $default) {
		if(!isset($values[$key])) {
			// No value provided, use default
			$values[$key] = $default;
		} elseif(is_array($values[$key])) {
			if($values[$key] == $default) {
				// Parameter not needed in URL if it matches the default
			} else {
				// Simplify array to semicolon-separated string in URL
				$simplified[] = urlencode($key).'='.implode(';', array_map('urlencode', $values[$key]));
			}
			$simplify = true;
		} elseif($values[$key] == $default) {
			// Parameter not needed in URL if it matches the default
			$simplify = true;
		} else {
			// Pass value as-is to simplified array
			$simplified[] = urlencode($key).'='.urlencode($values[$key]);
			if(is_array($default)) {
				// We expect an array; extract array values from semicolon-separated string
				$values[$key] = explode(';', $values[$key]);
			}
		}
	}
	if($simplify) {
		$url = preg_replace('/\?.*$/', '', $absolute_request_url);
		if(count($simplified) > 0) $url .= '?'.implode('&', $simplified);
		redirect($url);
	} else {
		return $values;
	}
}

class DNSName {
	public static function abbreviate($name, $zonename) {
		if($name === $zonename) {
			return '@';
		} elseif(strrpos($name, '.'.$zonename) === strlen($name) - strlen('.'.$zonename)) {
			return substr($name, 0, -1 - strlen($zonename));
		} else {
			return "$name.";
		}
	}
	public static function canonify($name, $zonename) {
		if($name === '@') return $zonename;
		if(substr($name, -1) === '.') return substr($name, 0, -1);
		return "$name.$zonename";
	}
}

class DNSTime {
	public static function abbreviate($time) {
		// Although formats like "3w12h" are allowed, we're discouraging that use by only returning 1 unit
		if($time % 60 != 0) return $time;
		if($time % (60 * 60) != 0) return ($time / 60).'M';
		if($time % (60 * 60 * 24) != 0) return ($time / 60 / 60).'H';
		if($time % (60 * 60 * 24 * 7) != 0) return $time / (60 * 60 * 24).'D';
		return $time / (60 * 60 * 24 * 7).'W';
	}
	public static function expand($time) {
		if(preg_match('/^([0-9]+[smhdw])+$/i', $time)) {
			preg_match_all('/([0-9]+)([smhdw])/i', $time, $matches, PREG_SET_ORDER);
			$total = 0;
			foreach($matches as $match) {
				switch(strtolower($match[2])) {
				case 's':
					$total += (int)$match[1];
					break;
				case 'm':
					$total += (int)$match[1] * 60;
					break;
				case 'h':
					$total += (int)$match[1] * 60 * 60;
					break;
				case 'd':
					$total += (int)$match[1] * 60 * 60 * 24;
					break;
				case 'w':
					$total += (int)$match[1] * 60 * 60 * 24 * 7;
					break;
				}
			}
			return $total;
		} elseif(preg_match('/^[0-9]+$/', $time)) {
			return (int)$time;
		}
	}
}

class DNSContent {
	public static function encode($content, $type) {
		switch($type) {
		case 'SOA':
			$parts = preg_split('/\s/', $content);
			$parts[3] = DNSTime::expand($parts[3]);
			$parts[4] = DNSTime::expand($parts[4]);
			$parts[5] = DNSTime::expand($parts[5]);
			$parts[6] = DNSTime::expand($parts[6]);
			$content = implode(' ', $parts);
			break;
		case 'TXT':
			$content = '"'.str_replace('"', '\\"', str_replace('\\', '\\\\', $content)).'"';
			break;
		}
		return $content;
	}
	public static function decode($content, $type) {
		switch($type) {
		case 'TXT':
			$content = str_replace('\\\\', '\\', str_replace('\\"', '"', substr($content, 1, -1)));
			break;
		}
		return $content;
	}
	public static function bind9_format($content, $type, $zonename) {
		switch($type) {
		case 'SOA':
			$parts = preg_split('/\s+/', $content);
			$spacer = str_repeat(' ', 45);
			$out = DNSName::abbreviate($parts[0], $zonename).' '.str_replace('@', '.', $parts[1])." (\n";
			$out .= $spacer.str_pad($parts[2], 11)."; serial\n";
			$out .= $spacer.str_pad(DNSTime::abbreviate($parts[3]), 11)."; refresh\n";
			$out .= $spacer.str_pad(DNSTime::abbreviate($parts[4]), 11)."; retry\n";
			$out .= $spacer.str_pad(DNSTime::abbreviate($parts[5]), 11)."; expire\n";
			$out .= $spacer.str_pad(DNSTime::abbreviate($parts[6]), 11)."; default ttl\n";
			$out .= $spacer.")\n";
			return $out;
		case 'MX':
			$parts = preg_split('/\s+/', $content, 2);
			$out = $parts[0].' '.DNSName::abbreviate($parts[1], $zonename);
			return $out;
		case 'SRV':
			$parts = preg_split('/\s+/', $content, 4);
			$out = $parts[0].' '.$parts[1].' '.$parts[2].' '.DNSName::abbreviate($parts[3], $zonename);
			return $out;
		case 'CNAME':
		case 'DNAME':
		case 'NS':
		case 'PTR':
			return DNSName::abbreviate($content, $zonename);
		case 'A':
		case 'AAAA':
		case 'TXT':
		default:
			return $content;
		}
	}
	public static function from_bind9($content, $type, $zonename) {
		switch($type) {
		case 'SOA':
			$parts = preg_split('/\s+/', $content);
			$spacer = str_repeat(' ', 45);
			$out = DNSName::canonify($parts[0], $zonename).' '.$parts[1]." ".$parts[2]." ".DNSTime::expand($parts[3])." ".DNSTime::expand($parts[4])." ".DNSTime::expand($parts[5])." ".DNSTime::expand($parts[6]);
			return $out;
		case 'MX':
			$parts = preg_split('/\s+/', $content, 2);
			$out = $parts[0].' '.DNSName::canonify($parts[1], $zonename);
			return $out;
		case 'SRV':
			$parts = preg_split('/\s+/', $content, 4);
			$out = $parts[0].' '.$parts[1].' '.$parts[2].' '.DNSName::canonify($parts[3], $zonename);
			return $out;
		case 'CNAME':
		case 'DNAME':
		case 'NS':
		case 'PTR':
			return DNSName::canonify($content, $zonename);
		case 'A':
		case 'AAAA':
		case 'TXT':
		default:
			return $content;
		}
	}
}

function ipv6_address_expand($address) {
	if(strpos($address, '::') !== false) {
		$address = str_replace('::', ':'.str_repeat('0:', 8 - substr_count($address, ':')), $address);
	}
	$parts = explode(':', $address);
	foreach($parts as &$part) {
		$part = str_pad($part, 4, '0', STR_PAD_LEFT);
	}
	$address = implode(':', $parts);
	return $address;
}

function ipv4_reverse_zone_to_range($zonename) {
	// eg. 3.2.1.in-addr.arpa
	$result = substr($zonename, 0, -13); // Chop off .in-addr.arpa = 3.2.1
	$result = explode('.', $result);     // Split by .             = 3, 2, 1
	$result = array_reverse($result);    // Reverse chunks         = 1, 2, 3
	$result = implode('.', $result).'.'; // Combine with .         = 1.2.3.
	return $result;
}

function ipv4_reverse_zone_to_subnet($zonename) {
	// eg. 3.2.1.in-addr.arpa
	$result = substr($zonename, 0, -13);   // Chop off .in-addr.arpa = 3.2.1
	$result = explode('.', $result);       // Split by .             = 3, 2, 1
	$result = array_reverse($result);      // Reverse chunks         = 1, 2, 3
	$prefix_len = count($result) * 8;
	$result = array_pad($result, 4, '0');  // Pad with zero chunks   = 1, 2, 3, 0
	$result = implode('.', $result);       // Combine with .         = 1.2.3.0
	$result .= '/'.$prefix_len;            // Append prefix length   = 1.2.3.0/24
	return $result;
}

function ipv6_reverse_zone_to_range($zonename) {
	// eg. 2.2.8.b.d.0.1.0.0.2.ip6.arpa
	$result = substr($zonename, 0, -9);       // Chop off .ip6.arpa         = 2.2.8.b.d.0.1.0.0.2
	$result = explode('.', $result);          // Split by . separators      = 2, 2, 8, b, d, 0, 1, 0, 0, 2
	$result = array_reverse($result);         // Reverse chunks             = 2, 0, 0, 1, 0, d, b, 8, 2, 2
	$result = implode('', $result);           // Combine into single string = 20010db822
	$result = str_pad($result, ceil(strlen($result) / 4) * 4, '*', STR_PAD_RIGHT);
	                                          // Pad up to multiple of 4    = 20010db822**
	$result = chunk_split($result, 4, ':');   // Split every 4 chars with : = 2001:0db8:22**:
	$result = str_replace('*', "·", $result); // Replace * with U+00B7      = 2001:0db8:22··:
	return $result;
}

function ipv6_reverse_zone_to_subnet($zonename) {
	// eg. 2.2.8.b.d.0.1.0.0.2.ip6.arpa
	$result = substr($zonename, 0, -9);       // Chop off .ip6.arpa         = 2.2.8.b.d.0.1.0.0.2
	$result = explode('.', $result);          // Split by . separators      = 2, 2, 8, b, d, 0, 1, 0, 0, 2
	$result = array_reverse($result);         // Reverse chunks             = 2, 0, 0, 1, 0, d, b, 8, 2, 2
	$result = implode('', $result);           // Combine into single string = 20010db822
	$prefix_len = strlen($result) * 4;
	$result = str_pad($result, ceil(strlen($result) / 4) * 4, '0', STR_PAD_RIGHT);
	                                          // Pad up to multiple of 4    = 20010db82200
	$sections = str_split($result, 4);        // Split into lengths of 4    = 2001, 0db8, 2200
	array_walk($sections, function(&$a, $b) { $a = ltrim($a, '0');});
	                                          // Remove leading zeroes      = 2001, db8, 2200
	$result = implode(':', $sections);        // Combine with :             = 2001:db8:2200
	$result .= '::/'.$prefix_len;             // Append prefix length       = 2001:db8:2200::/40
	return $result;
}

class OutputFormatter {
	public function changeset_comment_format($text) {
		return hesc($text);
	}
}

$output_formatter = new OutputFormatter;

if(file_exists('/usr/share/php-geshi/geshi.php')) {
	include('/usr/share/php-geshi/geshi.php');
	function syntax_highlight($text, $language) {
		$geshi = new GeSHi($text, $language);
		$geshi->enable_classes();
		echo $geshi->parse_code();
	}
} else {
	function syntax_highlight($text, $language) {
		echo '<pre>'.hesc($text).'</pre>';
	}
}

function syslog_report($level, $text) {
	global $active_user;
	openlog('dnsui', LOG_ODELAY, LOG_USER);
	foreach(explode("\n", $text) as $line) {
		syslog($level, 'client_ip='.$_SERVER['REMOTE_ADDR'].';uid='.$active_user->uid.';'.$line);
	}
	closelog();
}

function log_exception($e) {
	$error_number = time();
	foreach(explode("\n", $e) as $line) {
		error_log("$error_number: $line");
	}
	return $error_number;
}

class AccessDenied extends RuntimeException {}
class BadData extends RuntimeException {}
class InvalidJSON extends RuntimeException {}

foreach(glob("extensions/*.php") as $filename) {
    include $filename;
}
