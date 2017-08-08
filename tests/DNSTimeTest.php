<?php
declare(strict_types=1);

use PHPUnit\Framework\Testcase;

/**
* @covers DNSTime
*/

final class DNSTimeTest extends Testcase {
	public function testAbbreviate() {
		$minute = 60;
		$hour = 60 * $minute;
		$day = 24 * $hour;
		$week = 7 * $day;
		// Zero
		$this->assertEquals('0', DNSTime::abbreviate(0));
		// Seconds
		$this->assertEquals('1', DNSTime::abbreviate(1));
		$this->assertEquals('59', DNSTime::abbreviate(59));
		// Minutes
		$this->assertEquals('1M', DNSTime::abbreviate(1 * $minute));
		$this->assertEquals('59M', DNSTime::abbreviate(59 * $minute));
		// Combined minutes + seconds
		$this->assertEquals('61', DNSTime::abbreviate(1 * $minute + 1));
		// Hours
		$this->assertEquals('1H', DNSTime::abbreviate(1 * $hour));
		$this->assertEquals('23H', DNSTime::abbreviate(23 * $hour));
		// Combined hours + minutes
		$this->assertEquals('61M', DNSTime::abbreviate(1 * $hour + 1 * $minute));
		// Combined hours + minutes + seconds
		$this->assertEquals('3661', DNSTime::abbreviate(1 * $hour + 1 * $minute + 1));
		// Days
		$this->assertEquals('1D', DNSTime::abbreviate(1 * $day));
		$this->assertEquals('6D', DNSTime::abbreviate(6 * $day));
		// Weeks
		$this->assertEquals('1W', DNSTime::abbreviate(1 * $week));
		$this->assertEquals('99W', DNSTime::abbreviate(99 * $week));
	}

	public function testExpand() {
		$minute = 60;
		$hour = 60 * $minute;
		$day = 24 * $hour;
		$week = 7 * $day;
		// Zero
		$this->assertEquals(0, DNSTime::expand('0'));
		// Seconds
		$this->assertEquals(1, DNSTime::expand('1'));
		$this->assertEquals(59, DNSTime::expand('59'));
		// Minutes
		$this->assertEquals(1 * $minute, DNSTime::expand('1M'));
		$this->assertEquals(59 * $minute, DNSTime::expand('59M'));
		// Combined minutes + seconds
		$this->assertEquals(1 * $minute + 1, DNSTime::expand('1M1S'));
		// Hours
		$this->assertEquals(1 * $hour, DNSTime::expand('1H'));
		$this->assertEquals(23 * $hour, DNSTime::expand('23H'));
		// Combined hours + minutes
		$this->assertEquals(1 * $hour + 1 * $minute, DNSTime::expand('1H1M'));
		// Combined hours + minutes + seconds
		$this->assertEquals(1 * $hour + 1 * $minute + 1, DNSTime::expand('1H1M1S'));
		// Days
		$this->assertEquals(1 * $day, DNSTime::expand('1D'));
		$this->assertEquals(6 * $day, DNSTime::expand('6D'));
		// Weeks
		$this->assertEquals(1 * $week, DNSTime::expand('1W'));
		$this->assertEquals(99 * $week, DNSTime::expand('99W'));
	}
}
