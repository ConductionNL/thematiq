<?php

/**
 * Tests for BrandFormService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/simple-brand-form/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Thematiq\Service\BrandFormService;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The derivation over the real rules file and the real defaults layer of this repo.
 *
 * @spec openspec/specs/simple-brand-form/spec.md
 */
class BrandFormServiceTest extends TestCase {

	/**
	 * The repo root.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * The service.
	 *
	 * @var BrandFormService
	 */
	private BrandFormService $service;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->root = \dirname(__DIR__, 3);
		$this->service = new BrandFormService(contrast: new ContrastService(), parser: new CssParserService());
	}//end setUp()

	/**
	 * Task 1.1: every required token has a rule.
	 *
	 * @return void
	 */
	public function testEveryRequiredTokenHasARule(): void {
		$rules = $this->service->inputs(appPath: $this->root)['rules'];

		foreach (TokenSetVocabularyAuditService::REQUIRED_TOKENS as $token) {
			$this->assertArrayHasKey(key: $token, array: $rules, message: $token . ' has no rule in ' . BrandFormService::RULES);
		}
	}//end testEveryRequiredTokenHasARule()

	/**
	 * Scenario: an administrator creates a house style from a red and a white.
	 *
	 * @return void
	 */
	public function testRedAndWhiteGiveACompleteSetWithReadableText(): void {
		$result = $this->service->derive(appPath: $this->root, primary: '#C8102E', background: '#fff');

		$this->assertSame(expected: '#c8102e', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertSame(expected: '#ffffff', actual: $result['declarations']['--nldesign-color-primary-text']);
		$this->assertSame(expected: '#ffffff', actual: $result['declarations']['--nldesign-color-nav-background']);
		$this->assertGreaterThanOrEqual(expected: 4.5, actual: $result['textRatio']);
		foreach (TokenSetVocabularyAuditService::REQUIRED_TOKENS as $token) {
			$this->assertNotSame(expected: '', actual: ($result['declarations'][$token] ?? ''), message: $token);
		}
	}//end testRedAndWhiteGiveACompleteSetWithReadableText()

	/**
	 * Scenario: a light brand colour gets dark text. The yellow is no link colour on white, so the link keeps the default.
	 *
	 * @return void
	 */
	public function testLightPrimaryGetsBlackText(): void {
		$result = $this->service->derive(appPath: $this->root, primary: '#ffd200', background: '#ffffff');
		$defaults = $this->service->inputs(appPath: $this->root)['defaults'];

		$this->assertSame(expected: '#000000', actual: $result['textOnPrimary']);
		$this->assertSame(expected: '#000000', actual: $result['declarations']['--nldesign-color-primary-text']);
		$this->assertGreaterThanOrEqual(expected: 4.5, actual: $result['textRatio']);
		$this->assertSame(expected: $defaults['--nldesign-color-link'], actual: $result['declarations']['--nldesign-color-link']);
	}//end testLightPrimaryGetsBlackText()

	/**
	 * Task 1.3, a mid-tone and a dark primary: the higher of black and white wins.
	 *
	 * @return void
	 */
	public function testMidToneAndDarkPrimaries(): void {
		$this->assertSame(
			expected: '#000000',
			actual: $this->service->derive(appPath: $this->root, primary: '#808080', background: '#ffffff')['textOnPrimary']
		);
		$this->assertSame(
			expected: '#ffffff',
			actual: $this->service->derive(appPath: $this->root, primary: '#154273', background: '#ffffff')['textOnPrimary']
		);
	}//end testMidToneAndDarkPrimaries()

	/**
	 * Primary on background under 3:1 is reported through the ratio, not refused.
	 *
	 * @return void
	 */
	public function testLowUiContrastIsReportedNotRefused(): void {
		$result = $this->service->derive(appPath: $this->root, primary: '#ffd200', background: '#ffffff');

		$this->assertLessThan(expected: 3.0, actual: $result['uiRatio']);
	}//end testLowUiContrastIsReportedNotRefused()

	/**
	 * A colour that is not hex is refused.
	 *
	 * @return void
	 */
	public function testNonHexColourIsRefused(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->service->derive(appPath: $this->root, primary: 'red', background: '#ffffff');
	}//end testNonHexColourIsRefused()

	/**
	 * Task 1.2: the committed fixture is what PHP derives; the vitest twin checks the JS mirror against the same file.
	 *
	 * @return void
	 */
	public function testParityFixtureMatchesPhp(): void {
		$fixture = json_decode((string)file_get_contents($this->root . '/tests/Unit/fixtures/brand-form-parity.json'), true);

		$this->assertSame(
			expected: $this->service->inputs(appPath: $this->root),
			actual: $fixture['inputs'],
			message: 'Regenerate the fixture: rules or defaults changed'
		);
		foreach ($fixture['cases'] as $case) {
			$this->assertSame(
				expected: $case['expected'],
				actual: $this->service->derive(
					appPath: $this->root,
					primary: $case['primary'],
					background: $case['background'],
					action: $case['action']
				),
				message: $case['primary'] . ' on ' . $case['background'] . ' with ' . ($case['action'] ?? 'no action colour')
			);
		}
	}//end testParityFixtureMatchesPhp()

	/**
	 * Without an action colour the set holds no action token, and is what the form made before.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/brand-form-action-colour/specs/simple-brand-form/spec.md#requirement-the-form-may-give-the-main-button-its-own-colour
	 */
	public function testWithoutAnActionColourTheSetHoldsNoActionToken(): void {
		$plain = $this->service->derive(appPath: $this->root, primary: '#1a67a3', background: '#ffffff');
		$empty = $this->service->derive(appPath: $this->root, primary: '#1a67a3', background: '#ffffff', action: '');

		$this->assertSame(expected: $plain, actual: $empty, message: 'an empty field is no action colour');
		$this->assertNull(actual: $plain['textOnAction']);
		$this->assertNull(actual: $plain['actionTextRatio']);
		foreach (array_keys($plain['declarations']) as $token) {
			$this->assertStringNotContainsString(needle: 'color-action', haystack: $token);
		}
	}//end testWithoutAnActionColourTheSetHoldsNoActionToken()

	/**
	 * With an action colour the set holds it, a hover step and the text that reads best on it.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/brand-form-action-colour/specs/simple-brand-form/spec.md#requirement-the-form-may-give-the-main-button-its-own-colour
	 */
	public function testAnActionColourBringsItsHoverAndText(): void {
		$plain = $this->service->derive(appPath: $this->root, primary: '#1a67a3', background: '#ffffff');
		$green = $this->service->derive(appPath: $this->root, primary: '#1a67a3', background: '#ffffff', action: '#1F883D');

		$this->assertSame(expected: '#1f883d', actual: $green['declarations']['--nldesign-color-action']);
		$this->assertSame(
			expected: '#1a7434',
			actual: $green['declarations']['--nldesign-color-action-hover'],
			message: '15% darker, the step the stylesheet would derive'
		);
		$this->assertSame(expected: $green['textOnAction'], actual: $green['declarations']['--nldesign-color-action-text']);
		$this->assertContains(needle: $green['textOnAction'], haystack: ['#ffffff', '#000000']);
		$this->assertGreaterThanOrEqual(expected: 4.5, actual: $green['actionTextRatio']);

		// Everything else is what the form made without it.
		$rest = array_diff_key(
			$green['declarations'],
			array_flip(['--nldesign-color-action', '--nldesign-color-action-hover', '--nldesign-color-action-text'])
		);
		$this->assertSame(expected: $plain['declarations'], actual: $rest);
		$this->assertSame(
			expected: '#1a67a3',
			actual: $green['declarations']['--nldesign-color-header-background'],
			message: 'the header stays with the primary'
		);

		$yellow = $this->service->derive(appPath: $this->root, primary: '#1a67a3', background: '#ffffff', action: '#ffd200');
		$this->assertSame(expected: '#000000', actual: $yellow['declarations']['--nldesign-color-action-text'], message: 'black reads on yellow');

		$this->expectException(exception: \InvalidArgumentException::class);
		$this->service->derive(appPath: $this->root, primary: '#1a67a3', background: '#ffffff', action: 'green');
	}//end testAnActionColourBringsItsHoverAndText()

	/**
	 * Task 3.1: the vocabulary audit finds no required token missing in a stored generated set.
	 *
	 * @return void
	 */
	public function testAuditFindsNothingMissing(): void {
		$declarations = $this->service->derive(appPath: $this->root, primary: '#c8102e', background: '#ffffff')['declarations'];
		$tree = $this->appTreeWith(css: (new CustomTokenSetValidator())->serialize(declarations: $declarations));

		try {
			$audit = (new TokenSetVocabularyAuditService(parser: new CssParserService()))->auditSet(
				appPath: $tree,
				id: 'custom-brand-form',
				meta: []
			);
			$this->assertSame(expected: [], actual: $audit['missingRequired']);
		} finally {
			exec('rm -rf ' . escapeshellarg($tree));
		}
	}//end testAuditFindsNothingMissing()

	/**
	 * Task 3.2: the derived dark variant keeps text on primary readable.
	 *
	 * @return void
	 */
	public function testDarkVariantKeepsTextOnPrimaryReadable(): void {
		$contrast = new ContrastService();
		$dark = (new DarkPaletteService(
			contrast: $contrast,
			parser: new CssParserService(),
			appManager: $this->createMock(originalClassName: IAppManager::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		))
			->deriveDarkDeclarations(lightDeclarations: $this->service->derive(
				appPath: $this->root,
				primary: '#c8102e',
				background: '#ffffff'
			)['declarations']);

		$primary = $contrast->parseColor(value: $dark['--nldesign-color-primary']);
		$text = $contrast->parseColor(value: $dark['--nldesign-color-primary-text']);
		$this->assertNotNull(actual: $primary);
		$this->assertNotNull(actual: $text);
		$this->assertGreaterThanOrEqual(expected: 4.5, actual: $contrast->ratio(first: $primary, second: $text));
	}//end testDarkVariantKeepsTextOnPrimaryReadable()

	/**
	 * A temp app tree: this repo, with css/tokens holding only the given set.
	 *
	 * @param string $css The set's file.
	 *
	 * @return string The tree root.
	 */
	private function appTreeWith(string $css): string {
		$tree = sys_get_temp_dir() . '/thematiq-brand-form-' . uniqid();
		mkdir($tree . '/css/tokens', 0777, true);
		foreach (scandir($this->root) as $entry) {
			if (in_array($entry, ['.', '..', 'css'], true) === false) {
				symlink($this->root . '/' . $entry, $tree . '/' . $entry);
			}
		}

		foreach (scandir($this->root . '/css') as $entry) {
			if (in_array($entry, ['.', '..', 'tokens'], true) === false) {
				symlink($this->root . '/css/' . $entry, $tree . '/css/' . $entry);
			}
		}

		file_put_contents($tree . '/css/tokens/custom-brand-form.css', $css);

		return $tree;
	}//end appTreeWith()
}//end class
