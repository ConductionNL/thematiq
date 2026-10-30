<?php

/**
 * A set's action colour in its dark variant: treated as the primary is.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Service
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/dark-mode/spec.md#requirement-the-action-colour-is-treated-as-the-primary-in-a-dark-variant
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The generator is run on declaration maps, the way `generateForSet()` runs
 * it: derive, then verify and repair. No file is written.
 *
 * @spec openspec/specs/dark-mode/spec.md#requirement-the-action-colour-is-treated-as-the-primary-in-a-dark-variant
 */
class DarkPaletteActionColourTest extends TestCase {

	/**
	 * A small light set: a blue primary with white text on a white page.
	 *
	 * @var array<string, string>
	 */
	private const LIGHT = [
		'--nldesign-color-primary' => '#1a67a3',
		'--nldesign-color-primary-text' => '#ffffff',
		'--nldesign-color-primary-hover' => '#124872',
		'--nldesign-color-background' => '#ffffff',
		'--nldesign-color-text' => '#1f2328',
	];

	/**
	 * The generator, with nothing behind it but the colour arithmetic.
	 *
	 * @return DarkPaletteService The service.
	 */
	private function generator(): DarkPaletteService {
		return new DarkPaletteService(
			new ContrastService(),
			new CssParserService(),
			$this->createMock(IAppManager::class),
			$this->createMock(LoggerInterface::class)
		);
	}//end generator()

	/**
	 * The dark declarations of a light map, derived and repaired.
	 *
	 * @param array<string, string> $light The light declarations.
	 *
	 * @return array<string, string> The dark declarations.
	 */
	private function dark(array $light): array {
		$generator = $this->generator();

		return $generator->verifyAndRepair(declarations: $generator->deriveDarkDeclarations(lightDeclarations: $light))['declarations'];
	}//end dark()

	/**
	 * The contrast of two colours.
	 *
	 * @param string $foreground The text.
	 * @param string $background The fill.
	 *
	 * @return float The ratio.
	 */
	private function ratio(string $foreground, string $background): float {
		return (float)(new ContrastService())->measure(foreground: $foreground, background: $background);
	}//end ratio()

	/**
	 * A set without an action colour gets no action token in dark, and everything else as before.
	 *
	 * @return void
	 */
	public function testASetWithoutAnActionColourIsUntouched(): void {
		$dark = $this->dark(light: self::LIGHT);

		foreach (array_keys($dark) as $token) {
			$this->assertStringNotContainsString('color-action', $token);
		}

		$this->assertSame('#1a67a3', $dark['--nldesign-color-primary'], 'the primary keeps its light value: white reads on it');
	}//end testASetWithoutAnActionColourIsUntouched()

	/**
	 * An action colour its text reads on is kept as it is in dark, like a primary.
	 *
	 * @return void
	 */
	public function testAReadableActionColourKeepsItsLightValue(): void {
		$dark = $this->dark(light: (self::LIGHT + [
			'--nldesign-color-action' => '#1a7f37',
			'--nldesign-color-action-hover' => '#116329',
			'--nldesign-color-action-text' => '#ffffff',
		]));

		$this->assertSame('#1a7f37', $dark['--nldesign-color-action']);
		$this->assertSame('#116329', $dark['--nldesign-color-action-hover']);
		$this->assertSame('#ffffff', $dark['--nldesign-color-action-text']);
		$this->assertSame('#1a67a3', $dark['--nldesign-color-primary'], 'the primary is decided on its own pair');
	}//end testAReadableActionColourKeepsItsLightValue()

	/**
	 * An action colour declared without a text gets the primary's text to start from, as the light stylesheet reads it.
	 *
	 * @return void
	 */
	public function testAnActionColourWithoutATextGetsOne(): void {
		$dark = $this->dark(light: (self::LIGHT + ['--nldesign-color-action' => '#1a7f37']));

		$this->assertSame('#1a7f37', $dark['--nldesign-color-action']);
		$this->assertSame('#ffffff', $dark['--nldesign-color-action-text']);
		$this->assertArrayNotHasKey('--nldesign-color-action-hover', $dark, 'the stylesheet darkens the dark action colour itself');
	}//end testAnActionColourWithoutATextGetsOne()

	/**
	 * An action colour its text does not read on is derived, and its text is repaired until it reads.
	 *
	 * Before the action colour had a text of its own in dark, this case had
	 * no foreground to repair: the pair was reported and the repair read a
	 * token the dark declarations did not hold.
	 *
	 * @return void
	 */
	public function testAnUnreadableActionColourIsRepaired(): void {
		$light = (self::LIGHT + ['--nldesign-color-action' => '#7bd88f']);
		$this->assertLessThan(4.5, $this->ratio(foreground: '#ffffff', background: '#7bd88f'), 'white on this green does not read in light');

		$dark = $this->dark(light: $light);

		$this->assertNotSame('#7bd88f', $dark['--nldesign-color-action'], 'no brand exception without a readable pair');
		$this->assertGreaterThanOrEqual(
			4.5,
			$this->ratio(foreground: $dark['--nldesign-color-action-text'], background: $dark['--nldesign-color-action'])
		);
	}//end testAnUnreadableActionColourIsRepaired()
}//end class
