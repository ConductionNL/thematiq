<?php

/**
 * Parity of the EMailTemplate test double with the server's class.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/email-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Server\Mail;

use OC\Mail\EMailTemplate;
use OCA\Thematiq\Tests\Server\Support\MethodSignature;
use OCP\Defaults;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

/**
 * Checks tests/doubles/OC/Mail/EMailTemplate.php against the real
 * OC\Mail\EMailTemplate of the booted server, so tests/Unit/Mail keeps
 * testing against the class the server actually provides.
 */
class EMailTemplateDoubleParityTest extends TestCase {
	/**
	 * The double, loaded under a namespace of its own next to the real class.
	 */
	private const DOUBLE_CLASS = 'OCA\\Thematiq\\Tests\\Server\\Mail\\Double\\EMailTemplate';

	/**
	 * Load the double under DOUBLE_CLASS and require the real class from the server.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->assertTrue(
			condition: class_exists(class: 'OC', autoload: false),
			message: 'This suite needs a booted Nextcloud server; run it through phpunit.xml inside a server tree.'
		);
		$this->assertStringContainsString(
			needle: '/lib/private/Mail/EMailTemplate.php',
			haystack: (string)(new \ReflectionClass(objectOrClass: EMailTemplate::class))->getFileName()
		);

		if (class_exists(class: self::DOUBLE_CLASS, autoload: false) === true) {
			return;
		}

		$source = (string)file_get_contents(filename: __DIR__ . '/../../doubles/OC/Mail/EMailTemplate.php');
		$renamed = str_replace(search: 'namespace OC\\Mail;', replace: 'namespace OCA\\Thematiq\\Tests\\Server\\Mail\\Double;', subject: $source);
		$this->assertNotSame(expected: $source, actual: $renamed);

		$file = tempnam(directory: sys_get_temp_dir(), prefix: 'thematiq-email-double');
		file_put_contents(filename: $file, data: $renamed);
		require_once $file;
		unlink(filename: $file);
	}//end setUp()

	/**
	 * Every public and protected method of the server class exists in the double with the same signature.
	 *
	 * @return void
	 */
	public function testTheDoubleDeclaresEveryMethodWithTheSameSignature(): void {
		$real = new \ReflectionClass(objectOrClass: EMailTemplate::class);
		$double = new \ReflectionClass(objectOrClass: self::DOUBLE_CLASS);

		foreach ($real->getMethods() as $method) {
			if ($method->isPrivate() === true || $method->getDeclaringClass()->getName() !== EMailTemplate::class) {
				continue;
			}

			$name = $method->getName();
			$this->assertTrue(condition: $double->hasMethod(name: $name), message: 'missing method ' . $name);
			$this->assertSame(
				expected: MethodSignature::of(method: $method),
				actual: MethodSignature::of(method: $double->getMethod(name: $name)),
				message: 'signature of ' . $name
			);
		}
	}//end testTheDoubleDeclaresEveryMethodWithTheSameSignature()

	/**
	 * Every public and protected property of the server class exists in the double with the same type.
	 *
	 * @return void
	 */
	public function testTheDoubleDeclaresEveryPropertyWithTheSameType(): void {
		$real = new \ReflectionClass(objectOrClass: EMailTemplate::class);
		$double = new \ReflectionClass(objectOrClass: self::DOUBLE_CLASS);

		foreach ($real->getProperties() as $property) {
			if ($property->isPrivate() === true) {
				continue;
			}

			$name = $property->getName();
			$this->assertTrue(condition: $double->hasProperty(name: $name), message: 'missing property ' . $name);
			$twin = $double->getProperty(name: $name);
			$this->assertSame(expected: $property->isProtected(), actual: $twin->isProtected(), message: 'visibility of $' . $name);
			$this->assertSame(expected: (string)$property->getType(), actual: (string)$twin->getType(), message: 'type of $' . $name);
		}
	}//end testTheDoubleDeclaresEveryPropertyWithTheSameType()

	/**
	 * The same calls give the same subject, plain text and HTML text values on both classes.
	 *
	 * @return void
	 */
	public function testTheDoubleRendersTheSamePlainTextAsTheServer(): void {
		$real = $this->render(class: EMailTemplate::class);
		$double = $this->render(class: self::DOUBLE_CLASS);

		$this->assertSame(expected: $real['subject'], actual: $double['subject']);
		$this->assertSame(expected: $real['text'], actual: $double['text']);
		$fragments = [
			'#0082c9',
			'https://cloud.example.com/core/img/logo/logo.svg',
			'width="32" height="16"',
			'A &amp; B',
			'x &lt;y&gt;',
			'Item &lt;1&gt;',
			'meta &amp; info',
		];
		foreach ($fragments as $fragment) {
			$this->assertStringContainsString(needle: $fragment, haystack: $real['html']);
			$this->assertStringContainsString(needle: $fragment, haystack: $double['html']);
		}
	}//end testTheDoubleRendersTheSamePlainTextAsTheServer()

	/**
	 * Build one email with every part through the given template class.
	 *
	 * @param string $class The template class.
	 *
	 * @return array{subject: string, text: string, html: string}
	 */
	private function render(string $class): array {
		$defaults = $this->createMock(originalClassName: Defaults::class);
		$defaults->method('getDefaultColorPrimary')->willReturn('#0082c9');
		$defaults->method('getDefaultTextColorPrimary')->willReturn('#ffffff');
		$defaults->method('getLogo')->willReturn('/core/img/logo/logo.svg');
		$defaults->method('getName')->willReturn('Nextcloud');
		$defaults->method('getSlogan')->willReturn('a safe home');

		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('getAbsoluteURL')->willReturnCallback(fn (string $url): string => 'https://cloud.example.com' . $url);

		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$factory = $this->createMock(originalClassName: IFactory::class);
		$factory->method('get')->willReturn($l10n);

		$template = new $class($defaults, $urls, $factory, 32, 16, 'parity', []);
		$template->setSubject('Subject');
		$template->addHeader();
		$template->addHeading('A & B');
		$template->addBodyText('x <y>');
		$template->addBodyText('<b>html</b>', 'plain');
		$template->addBodyListItem('Item <1>', 'meta & info');
		$template->addBodyListItem("two\nlines", 'label', '', '', '', 6);
		$template->addBodyButtonGroup('Left', 'https://example.org/l', 'Right', 'https://example.org/r');
		$template->addBodyButton('Go', 'https://example.org/go');
		$template->addFooter();

		return ['subject' => $template->renderSubject(), 'text' => $template->renderText(), 'html' => $template->renderHtml()];
	}//end render()
}//end class
