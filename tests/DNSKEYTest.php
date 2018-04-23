<?php
declare(strict_types=1);

use PHPUnit\Framework\Testcase;

/**
* @covers DNSKEY
*/

final class DNSKEYTest extends Testcase {
	public function testGetTag() {
		$this->assertEquals('50036', DNSKEY::get_tag(257, 3, 13, '+lIB+O45g/Uea2u5v8mhWaW9pi4CaKEKiPK3AbYH5Uja9GW7+m/vUOBCHwICf3hLtZ5PXgorjP/td9qutBneFw=='));
	}
}
