<?php
declare(strict_types=1);

use PHPUnit\Framework\Testcase;

/**
* @covers DNSContent
*/

final class DNSContentTest extends Testcase {
	public function testEncodeSoa() {
		// SOA should have times expanded
		$this->assertEquals('ns1.example.com. hostmaster.example.com. 2017080806 10800 3600 1209600 3600', DNSContent::encode('ns1.example.com. hostmaster.example.com. 2017080806 3H 1H 2W 1H', 'SOA', 'example.com.'));
	}
	public function testEncodeTxt() {
		// TXT should be escaped and quoted
		$this->assertEquals('"hello \"world\""', DNSContent::encode('hello "world"', 'TXT', 'example.com.'));
	}
	public function testEncodeMx() {
		// MX hostname should be canonified
		$this->assertEquals('10 example.com.', DNSContent::encode('10 @', 'MX', 'example.com.'));
		$this->assertEquals('10 test.example.com.', DNSContent::encode('10 test', 'MX', 'example.com.'));
		$this->assertEquals('10 test.example.org.', DNSContent::encode('10 test.example.org.', 'MX', 'example.com.'));
	}
	public function testEncodeSrv() {
		// SRV hostname should be canonified
		$this->assertEquals('0 5 5060 example.com.', DNSContent::encode('0 5 5060 @', 'SRV', 'example.com.'));
		$this->assertEquals('0 5 5060 sipserver.example.com.', DNSContent::encode('0 5 5060 sipserver', 'SRV', 'example.com.'));
		$this->assertEquals('0 5 5060 sipserver.example.org.', DNSContent::encode('0 5 5060 sipserver.example.org.', 'SRV', 'example.com.'));
	}
	public function testEncodeCname() {
		// CNAME should be canonified
		$this->assertEquals('example.com.', DNSContent::encode('@', 'CNAME', 'example.com.'));
		$this->assertEquals('test.example.com.', DNSContent::encode('test', 'CNAME', 'example.com.'));
		$this->assertEquals('test.example.org.', DNSContent::encode('test.example.org.', 'CNAME', 'example.com.'));
	}
	public function testEncodeA() {
		// A records should be untouched
		$this->assertEquals('192.0.2.1', DNSContent::encode('192.0.2.1', 'A', 'example.com.'));
	}

	public function testDecodeTxt() {
		// TXT should be unquoted and unescaped
		$this->assertEquals('hello "world"', DNSContent::decode('"hello \"world\""', 'TXT', 'example.com.'));
	}
	public function testDecodeMx() {
		// MX hostname should be abbreviated
		$this->assertEquals('10 @', DNSContent::decode('10 example.com.', 'MX', 'example.com.'));
		$this->assertEquals('10 test', DNSContent::decode('10 test.example.com.', 'MX', 'example.com.'));
		$this->assertEquals('10 test.example.org.', DNSContent::decode('10 test.example.org.', 'MX', 'example.com.'));
	}
	public function testDecodeSrv() {
		// SRV hostname should be abbreviated
		$this->assertEquals('0 5 5060 @', DNSContent::decode('0 5 5060 example.com.', 'SRV', 'example.com.'));
		$this->assertEquals('0 5 5060 sipserver', DNSContent::decode('0 5 5060 sipserver.example.com.', 'SRV', 'example.com.'));
		$this->assertEquals('0 5 5060 sipserver.example.org.', DNSContent::decode('0 5 5060 sipserver.example.org.', 'SRV', 'example.com.'));
	}
	public function testDecodeCname() {
		// CNAME should be abbreviated
		$this->assertEquals('@', DNSContent::decode('example.com.', 'CNAME', 'example.com.'));
		$this->assertEquals('test', DNSContent::decode('test.example.com.', 'CNAME', 'example.com.'));
		$this->assertEquals('test.example.org.', DNSContent::decode('test.example.org.', 'CNAME', 'example.com.'));
	}
	public function testDecodeA() {
		// A records should be untouched
		$this->assertEquals('192.0.2.1', DNSContent::decode('192.0.2.1', 'A', 'example.com.'));
	}

	public function testBind9FormatSoa() {
		// SOA record should be formatted nicely
		$nice_format  = "ns1 hostmaster.example.com. (\n";
		$nice_format .= "                                             2017080806 ; serial\n";
		$nice_format .= "                                             3H         ; refresh\n";
		$nice_format .= "                                             1H         ; retry\n";
		$nice_format .= "                                             2W         ; expire\n";
		$nice_format .= "                                             1H         ; default ttl\n";
		$nice_format .= "                                             )\n";
		$this->assertEquals($nice_format, DNSContent::bind9_format('ns1.example.com. hostmaster.example.com. 2017080806 10800 3600 1209600 3600', 'SOA', 'example.com.'));
	}
	public function testBind9FormatTxt() {
		// TXT records should be untouched (assumes already encoded)
		$this->assertEquals('"hello \"world\""', DNSContent::bind9_format('"hello \"world\""', 'TXT', 'example.com.'));
	}
	public function testBind9FormatA() {
		// A records should be untouched
		$this->assertEquals('192.0.2.1', DNSContent::bind9_format('192.0.2.1', 'A', 'example.com.'));
	}
}
