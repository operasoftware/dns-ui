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

global $config, $ldap, $database, $powerdns, $user_dir, $zone_dir, $template_dir, $replication_type_dir;

$base_path = dirname(__FILE__);
chdir($base_path);
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
require('bindzonefile.php');

if(!empty($config['ldap']['enabled'])) {
	$ldap_options = array();
	$ldap_options[LDAP_OPT_PROTOCOL_VERSION] = 3;
	$ldap_options[LDAP_OPT_REFERRALS] = !empty($config['ldap']['follow_referrals']);
	$ldap = new LDAP(
		isset($config['ldap']['host']) ? $config['ldap']['host'] : $config['ldap']['hostname'],
		isset($config['ldap']['starttls']) ? $config['ldap']['starttls'] : 1,
		$config['ldap']['bind_dn'],
		$config['ldap']['bind_password'],
		$ldap_options
	);
}
setup_database();

$relative_frontend_base_url = (string)parse_url($config['web']['baseurl'], PHP_URL_PATH);
$frontend_root_url = preg_replace('/'.preg_quote($relative_frontend_base_url, '/').'$/', '', $config['web']['baseurl']);

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
	}
}

// Setup database connection and models
function setup_database() {
	global $config, $database, $powerdns, $user_dir, $zone_dir, $template_dir, $replication_type_dir;
	$database = new PDO($config['database']['dsn'], $config['database']['username'], $config['database']['password']);
	$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$powerdns = new PowerDNS($config['powerdns']['api_url'], $config['powerdns']['api_key']);
	$migration_dir = new MigrationDirectory;
	$user_dir = new UserDirectory;
	$zone_dir = new ZoneDirectory;
	$template_dir = new TemplateDirectory;
	$replication_type_dir = new ReplicationTypeDirectory;
}

/**
 * Join a sequence of partial paths into a complete path
 * e.g. pathJoin("foo", "bar") -> foo/bar
 *      pathJoin("f/oo", "b/ar") -> f/oo/b/ar
 *      pathJoin("/foo/b/", "ar") -> "/foo/b/ar"
 * @param string part of path
 * @return string joined path
 */
function path_join(...$args) {
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

/**
* Output the given string, HTML-escaped by default
* @param string $string to output
* @param integer $escaping method of escaping to use
*/
function out($string, $escaping = ESC_HTML) {
	if(is_null($string)) return '';
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
* Generate a root-relative URL from the base URL and the given base-relative URL
* @param string $url base-relative URL
* @return string root-relative URL
*/
function rrurl($url) {
	global $relative_frontend_base_url;
	return $relative_frontend_base_url.$url;
}

/**
* Output a root-relative URL from the base URL and the given base-relative URL
* @param string $url relative URL
*/
function outurl($url) {
	out(rrurl($url));
}

/**
 * Short-name HTML escape convenience function
 * @param string $string string to escape
 * @return string HTML-escaped string
 */
function hesc($string) {
	return htmlspecialchars($string ?? '');
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
	global $absolute_request_url, $relative_frontend_base_url;
	if(is_null($url)) {
		// Redirect is to current URL
		$url = $absolute_request_url;
	} else {
		$url = $relative_frontend_base_url.$url;
	}
	header("HTTP/1.1 $type");
	header("Location: $url");
	print("\n");
	exit;
}

/**
 * Given a set of defaults and an array of querystring data, convert to a simpler
 * easy-to-read form and redirect if any conversion was done.  Also return array
 * combining defaults with any querystring parameters that do not match defaults.
 * @param array $defaults associative array of default values
 * @param array $values associative array of querystring data
 * @return array result of combining defaults and querystring data
 */
function simplify_search($defaults, $values) {
	global $relative_request_url;
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
		$url = preg_replace('/\?.*$/', '', $relative_request_url);
		if(count($simplified) > 0) $url .= '?'.implode('&', $simplified);
		redirect($url);
	} else {
		return $values;
	}
}

class DNSZoneName {
	public static function unqualify($name) {
		return rtrim($name, '.');
	}
}

class DNSName {
	public static function abbreviate($name, $zonename) {
		if($name === $zonename) {
			return '@';
		} elseif(strrpos($name, '.'.$zonename) === strlen($name) - strlen('.'.$zonename)) {
			return substr($name, 0, -1 - strlen($zonename));
		} else {
			return $name;
		}
	}
	public static function canonify($name, $zonename) {
		if($name === '@') return $zonename;
		if(substr($name, -1) === '.') return $name;
		if($zonename === '.') return "$name.";
		return "$name.$zonename";
	}
}

class DNSTime {
	public static function abbreviate($time) {
		// Although formats like "3w12h" are allowed, we're discouraging that use by only returning 1 unit
		if($time % 60 != 0 || $time == 0) return $time;
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
	public static function encode($content, $type, $zonename) {
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
		case 'MX':
			$parts = preg_split('/\s+/', $content, 2);
			$content = $parts[0].' '.DNSName::canonify($parts[1], $zonename);
			break;
		case 'SRV':
			$parts = preg_split('/\s+/', $content, 4);
			$content = $parts[0].' '.$parts[1].' '.$parts[2].' '.DNSName::canonify($parts[3], $zonename);
			break;
		case 'CNAME':
		case 'DNAME':
		case 'NS':
		case 'PTR':
			$content = DNSName::canonify($content, $zonename);
			break;
		}
		return $content;
	}
	public static function decode($content, $type, $zonename) {
		switch($type) {
		case 'TXT':
			$content = str_replace('\\\\', '\\', str_replace('\\"', '"', substr($content, 1, -1)));
			break;
		case 'MX':
			$parts = preg_split('/\s+/', $content, 2);
			$content = $parts[0].' '.DNSName::abbreviate($parts[1], $zonename);
			break;
		case 'SRV':
			$parts = preg_split('/\s+/', $content, 4);
			$content = $parts[0].' '.$parts[1].' '.$parts[2].' '.DNSName::abbreviate($parts[3], $zonename);
			break;
		case 'CNAME':
		case 'DNAME':
		case 'NS':
		case 'PTR':
			$content = DNSName::abbreviate($content, $zonename);
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
		case 'TXT':
			$content = self::decode($content, $type, $zonename);
			$split = array();
			while(mb_strlen($content) != 0) {
				// Using mb_strcut to ensure that multi-byte characters are not cut in half
				// (mb_substr would give 255 chars instead of 255 bytes)
				$split[] = mb_strcut($content, 0, 255);
				$content = mb_strcut($content, 255);
			}
			foreach($split as &$line) {
				$line = self::encode($line, $type, $zonename);
			}
			return implode(" ", $split);
		default:
			return DNSContent::decode($content, $type, $zonename);
		}
	}
}

class DNSKEY {
	public static function get_tag($dnskey_flags, $dnskey_protocol, $dnskey_algorithm, $dnskey_keydata) {
		// Reconstruct the DNSKEY RDATA wire format (https://tools.ietf.org/html/rfc4034#section-2.1)
		// by merging the flags, protocol, algorithm, and the base64-decoded key data
		$wire_format = pack("nCC", $dnskey_flags, $dnskey_protocol, $dnskey_algorithm).base64_decode($dnskey_keydata);
		// Split data into (zero-indexed) array of bytes
		$keyvalues = array_values(unpack("C*", $wire_format));
		// Follow algorithm from RFC 4034 Appendix B (https://tools.ietf.org/html/rfc4034#appendix-B)
		$ac = 0;
		foreach($keyvalues as $i => $keyvalue) {
			$ac += ($i & 1) ? $keyvalue : $keyvalue << 8;
		}
		$ac += ($ac >> 16) & 0xFFFF;
		return $ac & 0xFFFF;
	}
}

class DS {
	public static function get_digest_type($ds) {
		// List sourced from https://www.iana.org/assignments/ds-rr-types/ds-rr-types.xhtml
		$digest_types = array(
			1 => 'SHA-1',
			2 => 'SHA-256',
			3 => 'GOST R 34.11-94',
			4 => 'SHA-384'
		);
		list($key_tag, $algorithm, $digest_type, $digest) = explode(' ', $ds);
		return $digest_types[$digest_type];
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
	// eg. 3.2.1.in-addr.arpa.
	$result = substr($zonename, 0, -14); // Chop off .in-addr.arpa. = 3.2.1
	if(!preg_match('/^[0-9\.]*$/', $result)) return "";
	$result = explode('.', $result);     // Split by .              = 3, 2, 1
	$result = array_reverse($result);    // Reverse chunks          = 1, 2, 3
	$result = implode('.', $result).'.'; // Combine with .          = 1.2.3.
	return $result;
}

function ipv4_reverse_zone_to_subnet($zonename) {
	// eg. 3.2.1.in-addr.arpa.
	$result = substr($zonename, 0, -14);   // Chop off .in-addr.arpa. = 3.2.1
	if(!preg_match('/^[0-9\.]*$/', $result)) return "";
	$result = explode('.', $result);       // Split by .              = 3, 2, 1
	$result = array_reverse($result);      // Reverse chunks          = 1, 2, 3
	$prefix_len = count($result) * 8;
	$result = array_pad($result, 4, '0');  // Pad with zero chunks    = 1, 2, 3, 0
	$result = implode('.', $result);       // Combine with .          = 1.2.3.0
	$result .= '/'.$prefix_len;            // Append prefix length    = 1.2.3.0/24
	return $result;
}

function ipv6_reverse_zone_to_range($zonename) {
	// eg. 2.2.8.b.d.0.1.0.0.2.ip6.arpa.
	$result = substr($zonename, 0, -10);      // Chop off .ip6.arpa.        = 2.2.8.b.d.0.1.0.0.2
	if(!preg_match('/^[0-9a-f\.]*$/i', $result)) return "";
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
	// eg. 0.0.0.2.2.8.b.d.0.1.0.0.2.ip6.arpa.
	$result = substr($zonename, 0, -10);      // Chop off .ip6.arpa.        = 0.0.0.2.2.8.b.d.0.1.0.0.2
	if(!preg_match('/^[0-9a-f\.]*$/i', $result)) return "";
	$result = explode('.', $result);          // Split by . separators      = 0, 0, 0, 2, 2, 8, b, d, 0, 1, 0, 0, 2
	$result = array_reverse($result);         // Reverse chunks             = 2, 0, 0, 1, 0, d, b, 8, 2, 2, 0, 0, 0
	$result = implode('', $result);           // Combine into single string = 20010db822000
	$prefix_len = strlen($result) * 4;
	$result = str_pad($result, ceil(strlen($result) / 4) * 4, '0', STR_PAD_RIGHT);
	                                          // Pad up to multiple of 4    = 20010db822000000
	$sections = str_split($result, 4);        // Split into lengths of 4    = 2001, 0db8, 2200, 0000
	array_walk($sections, function(&$a, $b) { $a = ltrim($a, '0');});
	                                          // Remove leading zeroes      = 2001, db8, 2200, ''
	$result = implode(':', $sections);        // Combine with :             = 2001:db8:2200:
	$result = preg_replace('/:+$/', '', $result); // Chop off trailing :    = 2001:db8:2200
	$result .= '::/'.$prefix_len;             // Append prefix length       = 2001:db8:2200::/52
	return $result;
}

function punycode_to_utf8($string) {
	return idn_to_utf8($string, 0, INTL_IDNA_VARIANT_UTS46);
}

function utf8_to_punycode($string) {
	return idn_to_ascii($string, 0, INTL_IDNA_VARIANT_UTS46);
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

function parse_postgres_date($date_str) {
	// If the date's microsecond part is exactly zero, it is omitted by
	// postgres from its string representation and '.u' must be removed
	// from the format, or parsing will fail.
	$date_format = strpos($date_str, '.') !== false ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';
	return DateTime::createFromFormat($date_format, $date_str);
}

class AccessDenied extends RuntimeException {}
class BadData extends RuntimeException {}
class InvalidJSON extends RuntimeException {}

foreach(glob("extensions/*.php") as $filename) {
    include $filename;
}
