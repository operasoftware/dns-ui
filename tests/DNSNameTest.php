<?php
declare(strict_types=1);

use PHPUnit\Framework\Testcase;

/**
* @covers DNSName
*/

final class DNSNameTest extends Testcase {
	public function testAbbreviate() {
		// Matching domain
		$this->assertEquals('foobar', DNSName::abbreviate('foobar.example.com.', 'example.com.'));
		// Non-matching domain
		$this->assertEquals('foobar.example.com.', DNSName::abbreviate('foobar.example.com.', 'example.org.'));
		// Non-matching domain (partial match)
		$this->assertEquals('foobar.example.com.', DNSName::abbreviate('foobar.example.com.', 'test.com.'));
		// Domain root
		$this->assertEquals('@', DNSName::abbreviate('foobar.example.com.', 'foobar.example.com.'));
	}

	public function testCanonify() {
		// Normal record
		$this->assertEquals('foobar.example.com.', DNSName::canonify('foobar', 'example.com.'));
		// Dot-qualified FQDN
		$this->assertEquals('foobar.example.org.', DNSName::canonify('foobar.example.org.', 'example.com.'));
		// Not dot-qualified FQDN
		$this->assertEquals('foobar.example.org.example.com.', DNSName::canonify('foobar.example.org', 'example.com.'));
		// Domain root record
		$this->assertEquals('foobar.example.com.', DNSName::canonify('@', 'foobar.example.com.'));
		// . origin
		$this->assertEquals('foobar.example.com.', DNSName::canonify('foobar.example.com', '.'));
	}
}
