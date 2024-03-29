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

		$this->assertTrue(Substractor::matches('Filename.1.0.0.json', '*.*.*.*.json'));

		$this->assertTrue(Substractor::matches('Filename.1.0.0.json', '*.*.json'));

	}

	public function testSubs() {

		$this->assertCount(3, Substractor::subs(
			'example.com/welcome.html example.com example.net/index.php sub.example.com/index.html',
			['*.com/*.html', '*.*.com/*.html']
		));

		# [Contact me](mailto:jeffpacks@varen.no)
		# [     *    ](mailto:   *     @   *    )
		$this->assertEquals(
			['[Contact me]'],
			Substractor::subs(
				'[Contact me](mailto:jeffpacks@varen.no)',
				'[*]',
				' '
			)
		);

		$this->assertEquals(
			['Contact me'],
			Substractor::subs(
				'[Contact me](mailto:jeffpacks@varen.no)',
				'[*]',
				[
					' ',
					'[' => false,
					']' => false
				]
			)
		);

		$this->assertEquals(
			['mailto:jeffpacks@varen.no)'],
			Substractor::subs(
				'[Contact me](mailto:jeffpacks@varen.no)',
				'mailto:*@*'
			)
		);

		$this->assertEquals(
			['mailto:jeffpacks@varen.no'],
			Substractor::subs(
				'[Contact me](mailto:jeffpacks@varen.no)',
				'mailto:*@*',
				[
					')' => false
				]
			)
		);

		$this->assertEquals(
			[
				'[Foo Bar](https://example.test/)'
			],
			Substractor::subs(
				'[Foo Bar](https://example.test/)',
				'[*](*)',
				' '
			)
		);

		$this->assertEquals(
			['FileA.json', 'FileB.json', 'FileC.json'],
			Substractor::subs(
				'"File": "FileA.json", "Example": "FileB.json", "File": "FileC.json"',
				[
					'"File": "*.json"' => '*.json'
				],
				['"' => false]
			)
		);

		$this->assertEmpty(
			Substractor::subs(
				'"Example": "FileB.json"',
				[
					'"File": "*.json"' => '*.json'
				]
			)
		);

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

		$this->assertEquals(
			[
				'text' => 'e-mail me',
				'uri' => 'jeffpacks@varen.no'
			],
			Substractor::macros(
				'You can [e-mail me](mailto:jeffpacks@varen.no) or reach me on [Github](https://github.com/jeffpacks',
				'[{text}]({uri})',
				[
					' ',
					'mailto:' => false
				]
			)
		);

	}

	public function testMacrosAll() {

		$this->assertEquals(
			[
				'a' => ['foo', 'hurf'],
				'b' => ['bar', 'durf']
			],
			Substractor::macrosAll('foo:bar hurf:durf', '{a}:{b}')
		);

	}

	public function testPluck(): void {

		$this->assertEquals(
			'foo',
			Substractor::pluck('foo:bar hurf:durf', '{a}:{b}', 'a')
		);

	}

	public function testPluckAll(): void {

		$this->assertEquals(
			['foo', 'hurf'],
			Substractor::pluckAll('foo:bar hurf:durf', '{a}:{b}', 'a')
		);

	}

	public function testReplace(): void {

		$string = 'http://foo:foo@example.test:80:/';

		$expected = 'https://foo:' . md5('foo') . '@example.test:22:/';

		$this->assertEquals(
			$expected,
			(string) Substractor::replace($string, '{protocol}://{username}:{password}@{host}:{port}:/')->protocol('https')->password(fn(string $password) => md5($password))->port(22)
		);

	}

}