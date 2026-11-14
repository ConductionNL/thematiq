<?php

/**
 * Tests for token references: validation, writing and cycles.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 * @author   Conduction <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/token-references-stay-live/specs/token-editor-ui/spec.md#requirement-a-token-can-refer-to-another-token
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\OverridesCssBuilder;
use OCA\Thematiq\Service\TokenReference;
use OCA\Thematiq\Service\TokenValueValidator;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * A token refers to another token and stays a reference.
 *
 * @covers \OCA\Thematiq\Service\TokenReference
 * @covers \OCA\Thematiq\Service\TokenValueValidator
 * @covers \OCA\Thematiq\Service\OverridesCssBuilder
 * @covers \OCA\Thematiq\Service\DesignTokensMapper
 */
class TokenReferencesTest extends TestCase {

	/**
	 * The builder.
	 *
	 * @var OverridesCssBuilder
	 */
	private OverridesCssBuilder $builder;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(sys_get_temp_dir());
		$darkPalette = new DarkPaletteService(
			contrast: new ContrastService(),
			parser: new CssParserService(),
			appManager: $appManager,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$this->builder = new OverridesCssBuilder(darkPalette: $darkPalette, values: new TokenValueValidator());
	}//end setUp()

	/**
	 * A reference names its target; anything else is no reference.
	 *
	 * @return void
	 */
	public function testAReferenceNamesItsTarget(): void {
		$this->assertSame('--nldesign-color-primary', TokenReference::targetOf(value: ' var( --nldesign-color-primary ) '));
		$this->assertNull(TokenReference::targetOf(value: 'var(--color-primary)'));
		$this->assertNull(TokenReference::targetOf(value: 'var(--nldesign-color-primary, red)'));
		$this->assertNull(TokenReference::targetOf(value: '#a00078'));
		$this->assertNull(TokenReference::targetOf(value: null));
		$this->assertSame('var(--nldesign-color-link)', TokenReference::to(tokenName: '--nldesign-color-link'));
	}//end testAReferenceNamesItsTarget()

	/**
	 * A reference to a known token is a value of any type; an unknown target is refused.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/token-references-stay-live/specs/token-editor-ui/spec.md#requirement-a-token-can-refer-to-another-token
	 */
	public function testAReferenceIsAValueOfAnyTypeAndItsTargetMustExist(): void {
		$validator = new TokenValueValidator();
		$this->assertSame([], $validator->findRejected(tokens: ['--color-primary' => 'var(--nldesign-component-header-background-color)', '--nldesign-animation-easing' => 'var(--nldesign-component-header-color)']));
		$this->assertSame(
			['--color-primary' => 'refers to --nldesign-color-nonsense, which is not a token'],
			$validator->findRejected(tokens: ['--color-primary' => 'var(--nldesign-color-nonsense)'])
		);
	}//end testAReferenceIsAValueOfAnyTypeAndItsTargetMustExist()

	/**
	 * A reference that closes a cycle is refused, naming both tokens.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/token-references-stay-live/specs/token-editor-ui/spec.md#scenario-a-cycle-is-refused
	 */
	public function testACycleIsRefusedNamingBothTokens(): void {
		$header = '--nldesign-component-header-color';
		$slogan = '--nldesign-component-logo-slogan-color';
		$tokens = [$slogan => 'var(' . $header . ')', $header => 'var(' . $slogan . ')'];

		$this->assertSame(
			[
				$slogan => 'refers to itself through ' . $slogan . ' -> ' . $header . ' -> ' . $slogan,
				$header => 'refers to itself through ' . $header . ' -> ' . $slogan . ' -> ' . $header,
			],
			(new TokenValueValidator())->findRejected(tokens: $tokens)
		);
		$this->assertSame([$header => [$header, $header]], TokenReference::cycles(tokens: [$header => 'var(' . $header . ')']));
		$this->assertSame([], TokenReference::cycles(tokens: ['--color-primary' => 'var(--nldesign-b)', '--nldesign-b' => '#fff']));
		// A token that points into a cycle without being on it is not itself refused for the cycle.
		$this->assertArrayNotHasKey('--color-primary', TokenReference::cycles(tokens: ['--color-primary' => 'var(' . $header . ')', $header => 'var(' . $slogan . ')', $slogan => 'var(' . $header . ')']));
	}//end testACycleIsRefusedNamingBothTokens()

	/**
	 * The stored overrides keep a reference as `var(--...)`, unresolved, in light and dark.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/token-references-stay-live/specs/token-editor-ui/spec.md#scenario-an-edit-flows-through-a-reference
	 */
	public function testAReferenceIsWrittenUnresolved(): void {
		$css = $this->builder->build(
			tokens: [
				'--nldesign-color-primary' => '#a00078',
				'--color-primary' => 'var(--nldesign-color-primary)',
				'--color-main-text' => 'var(--nldesign-color-primary)',
			]
		);

		$this->assertStringContainsString('--color-primary: var(--nldesign-color-primary) !important;', $css);
		$this->assertStringContainsString('--color-main-text: var(--nldesign-color-primary) !important;', $css);
		$this->assertStringNotContainsString('--color-primary: #', $css);
	}//end testAReferenceIsWrittenUnresolved()
	/**
	 * An imported alias whose target maps onto a `--nldesign-*` token stays a
	 * reference; one whose target is unmapped or reference-only is resolved.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/token-references-stay-live/specs/token-editor-ui/spec.md#scenario-an-imported-alias-stays-a-reference
	 */
	public function testAnImportedAliasStaysAReference(): void {
		$document = [
			'color' => [
				'$type' => 'color',
				'primary' => ['$value' => '#a00078'],
				'action' => ['$value' => '{color.primary}'],
				'text' => ['$value' => '{palette.ink}'],
				'background' => ['$value' => '{source.paper}'],
			],
			'palette' => ['ink' => ['$type' => 'color', '$value' => '#111111']],
			'source' => ['paper' => ['$type' => 'color', '$value' => '#fafafa']],
		];

		$declarations = (new DesignTokensMapper())->map(document: $document, referenceOnlyPaths: ['source.paper'])['declarations'];

		$this->assertSame('#a00078', $declarations['--nldesign-color-primary']);
		$this->assertSame('var(--nldesign-color-primary)', $declarations['--nldesign-color-action']);
		$this->assertSame('#111111', $declarations['--nldesign-color-text']);
		$this->assertSame('#fafafa', $declarations['--nldesign-color-background']);
	}//end testAnImportedAliasStaysAReference()

	/**
	 * An alias whose resolved value its type refuses is still refused, not kept as a reference.
	 *
	 * @return void
	 */
	public function testAnAliasWithAnInvalidValueIsStillRefused(): void {
		$document = [
			'color' => [
				'primary' => ['$type' => 'color', '$value' => ['not' => 'a colour']],
				'action' => ['$type' => 'color', '$value' => '{color.primary}'],
			],
		];

		$mapping = (new DesignTokensMapper())->map(document: $document);
		$this->assertArrayNotHasKey('--nldesign-color-action', $mapping['declarations']);
		$this->assertContains('color.action', array_column($mapping['errors'], 'path'));
	}//end testAnAliasWithAnInvalidValueIsStillRefused()

}//end class
