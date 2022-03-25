<?php

use jeffpacks\substractor\Substractor;
use PHPUnit\Framework\TestCase;

class SubstractorTest extends TestCase {

	public function testMatches() {

		$this->assertTrue(Substractor::matches(
			'https://example.test/welcome.html https://example.test https://sub.example.test/index.html',
			'https://*.*.*/'
		));

		$this->assertTrue(Substractor::matches(
			'https://example.test/welcome.html',
			'https://*.*.*'
		));

		$this->assertFalse(Substractor::matches(
			'https://example.test/welcome.html',
			'https://*.*.*/'
		));

		$pattern = 'http*://example.test';
		foreach ([
			'http://example.test',
			'https://example.test'
		] as $string) {
			$this->assertTrue(Substractor::matches($string, $pattern));
		}

		$this->assertTrue(Substractor::matches('1.2.10', '*.*.??'));

		$this->assertTrue(Substractor::matches('1.2.3-beta.1', '*.*.*-*'));

		$this->assertTrue(Substractor::matches('[Foo Bar](https://example.test/)', '[*](*)', ' '));

	}

	public function testSubs() {

		$this->assertCount(3, Substractor::subs(
			'example.com/welcome.html example.com example.net/index.php sub.example.com/index.html',
			['*.com/*.html', '*.*.com/*.html']
		));

		$this->assertEquals(['mailto:jeffpacks@varen.no'], Substractor::subs('[e-mail](mailto:jeffpacks@varen.no)', 'mailto:*@*', ')'));

	}

	public function testMacros() {

		$this->assertEquals(
			['a' => 'foo', 'b' => 'bar'],
			Substractor::macros('foo:bar hurf:durf', '{a}:{b}')
		);

		$this->assertEquals(
			['a' => 'foo', 'b' => 'bar', 'c' => 'hurf', 'd' => 'durf'],
			Substractor::macros('foo:bar hurf:durf', '{a}:{b} {c}:{d}')
		);

		$this->assertEquals(
			['a' => 'foo', 'b' => 'bar', 'c' => 'hurf', 'd' => 'durf'],
			Substractor::macros('foo:bar:hurf:durf', '{a}:{b}:{c}:{d}')
		);

		$this->assertEquals(
			['protocol' => 'https', 'domain' => 'example', 'top' => 'test'],
			Substractor::macros('https://example.test/index.html', '{protocol}://{domain}.{top}/*')
		);

		$this->assertEquals(
			['domain' => 'example', 'subdomain' => 'jeffpacks'],
			Substractor::macros('jeffpacks.example.com/index.html', '{subdomain}.{domain}.*')
		);

		$this->assertEquals(
			['major' => '2', 'minor' => '0', 'patch' => '12'],
			Substractor::macros('2.0.12-beta.1', '{major}.{minor}.{patch}-*')
		);

		$this->assertEquals(
			['major' => '2', 'minor' => '0', 'patch' => '12'],
			Substractor::macros('This is version 2.0.12 inside a sentence', '* {major}.{minor}.{patch} *')
		);

		$patterns = [
			'*.*.*-alpha.*' => '{major}.{minor}.{patch}-*.{alpha}',
			'*.*.*-beta.*' => '{major}.{minor}.{patch}-*.{beta}',
		];

		$this->assertEquals(
			['major' => '1', 'minor' => '2', 'patch' => '3', 'alpha' => '1'],
			Substractor::macros('1.2.3-alpha.1', $patterns)
		);

		$this->assertEquals(
			['major' => '1', 'minor' => '2', 'patch' => '3', 'beta' => '1'],
			Substractor::macros('1.2.3-beta.1', $patterns)
		);

		$this->assertEquals(
			['one' => '', 'two' => '', 'three' => '', 'four' => 'ok'],
			Substractor::macros('...ok', [
				'{one}.{two}.{three}.{four}',
			])
		);

		$string = "1.4.2-beta.2";
		Substractor::matches($string, '*.*.*-beta.*');
		Substractor::macros($string, '{major}.{minor}.{patch}-{preRelease}.{betaVersion}'); # => ['betaVersion' => '2']

		$this->assertEquals(
			[
				'text' => 'Foo Bar',
				'url' => 'https://example.test/'
			],
			Substractor::macros(
				'[Foo Bar](https://example.test/)',
				'[{text}]({url})',
				' '
			)
		);

	}

}