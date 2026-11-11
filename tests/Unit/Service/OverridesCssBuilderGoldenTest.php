<?php

/**
 * Golden test for OverridesCssBuilder::build(): the whole file for an empty
 * set, for light tokens only, with a motion token, and with derived, own and
 * administrator-token dark values.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/dark-mode/spec.md#requirement-editor-overrides-apply-the-same-way-for-every-dark-user
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\OverridesCssBuilder;
use OCA\Thematiq\Service\OwnTokenCss;
use OCA\Thematiq\Service\TokenValueValidator;
use PHPUnit\Framework\TestCase;

/**
 * Pins the built overrides file byte for byte over doubles of the dark palette and the validator.
 *
 * @spec openspec/specs/dark-mode/spec.md#requirement-editor-overrides-apply-the-same-way-for-every-dark-user
 */
final class OverridesCssBuilderGoldenTest extends TestCase {

	/**
	 * The builder over doubles: every colour derives `dark(<value>)`, the repair changes nothing,
	 * and only `--color-primary-element` takes an own dark value.
	 *
	 * @return OverridesCssBuilder
	 */
	private function builder(): OverridesCssBuilder {
		$dark = $this->createMock(DarkPaletteService::class);
		$dark->method('verifyAndRepair')->willReturnCallback(static fn (array $declarations, array $pairs): array => ['declarations' => $declarations]);
		$dark->method('deriveDarkValue')->willReturnCallback(static fn (string $token, string $lightValue, array $context): ?string => ($token === '--color-main-text' ? null : 'dark(' . $lightValue . ')'));
		$values = $this->createMock(TokenValueValidator::class);
		$values->method('hasDarkValue')->willReturnCallback(static fn (array $meta): bool => ($meta !== []));
		$values->method('hasDarkValueFor')->willReturnCallback(static fn (string $name): bool => $name === '--color-primary-element');

		return new OverridesCssBuilder(darkPalette: $dark, values: $values);
	}//end builder()

	/**
	 * The same inputs build the same file, byte for byte, as the recorded one.
	 *
	 * @return void
	 */
	public function testTheBuiltFilesAreUnchanged(): void {
		$builder = $this->builder();
		$motion = array_key_first(OverridesCssBuilder::MOTION_TWINS);
		$files = [
			'empty' => $builder->build(tokens: []),
			'light' => $builder->build(tokens: ['--color-primary-element' => '#154273', '--color-main-text' => '#222222']),
			'motion' => $builder->build(tokens: [$motion => '80ms']),
			'dark' => $builder->build(
				tokens: ['--color-primary-element' => '#154273', '--color-main-background' => '#ffffff'],
				darkTokens: ['--color-primary-element' => '#99ccff', '--color-main-background' => '#000000', '--color-unset' => '#111111']
			),
			'own' => $builder->build(tokens: [], own: new OwnTokenCss(light: ['--own-a' => '1px'], dark: ['--own-a' => '2px'])),
		];

		$golden = __DIR__ . '/../fixtures/golden/overrides-css-builder.golden.json';
		if (is_file($golden) === false) {
			file_put_contents($golden, json_encode($files, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
			$this->markTestSkipped('golden recorded');
		}

		$this->assertSame(expected: json_decode((string)file_get_contents($golden), true), actual: $files);
	}//end testTheBuiltFilesAreUnchanged()
}//end class
