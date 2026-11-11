<?php

/**
 * Unit tests for CustomTokenSetsBundleSection::validate(): every refusal of a
 * bundle's custom token set with its error, and the entry a valid set
 * resolves to.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetsBundleSection;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;

/**
 * Pins the custom token set section's validation.
 *
 * @spec openspec/specs/config-portability/spec.md
 */
final class CustomTokenSetsBundleSectionTest extends TestCase {

	/**
	 * The section over the real validator and parser and the repository's design systems.
	 *
	 * @return CustomTokenSetsBundleSection
	 */
	private function section(): CustomTokenSetsBundleSection {
		$sets = $this->createMock(CustomTokenSetService::class);
		$sets->method('isCustomId')->willReturnCallback(static fn (string $id): bool => preg_match('/^custom-[a-z0-9-]+$/', $id) === 1);
		$apps = $this->createMock(IAppManager::class);
		$apps->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		return new CustomTokenSetsBundleSection(customSets: $sets, validator: new CustomTokenSetValidator(), cssParser: new CssParserService(), appManager: $apps);
	}//end section()

	/**
	 * A section that is not a list is one error and no sets.
	 *
	 * @return void
	 */
	public function testASectionThatIsNotAListIsRefused(): void {
		$errors = [];

		$this->assertSame([], $this->section()->validate(['customTokenSets' => 'nope'], $errors));
		$this->assertSame([['section' => 'customTokenSets', 'message' => '"customTokenSets" must be a list.']], $errors);
	}//end testASectionThatIsNotAListIsRefused()

	/**
	 * Each wrong set is one error naming its position and id; the valid ones resolve.
	 *
	 * @return void
	 */
	public function testEachSetIsValidatedAndResolved(): void {
		$css = ':root { --nldesign-color-primary: #154273; }';
		$bundle = [
			'customTokenSets' => [
				'x' => 'not an object',
				['id' => 'bad id', 'name' => 'A', 'css' => $css],
				['id' => 'custom-a', 'name' => '  ', 'css' => $css],
				['id' => 'custom-b', 'name' => 'B', 'css' => ''],
				['id' => 'custom-c', 'name' => 'C', 'css' => '.x { color: red; }'],
				['id' => 'custom-d', 'name' => 'D', 'css' => ':root { --nldesign-color-primary: expression(alert(1)); }'],
				[
					'id' => 'custom-e',
					'name' => ' Gemeente E ',
					'css' => $css,
					'description' => 'Huisstijl',
					'theming' => ['primary_color' => '#154273'],
					'warnings' => ['w'],
					'version' => '1.2.0',
					'importWarnings' => ['i'],
					'design_system' => 'nldesign',
				],
				['id' => 'custom-f', 'name' => 'F', 'css' => $css, 'theming' => 'not a map', 'warnings' => 'no list', 'importWarnings' => 'no', 'design_system' => 'evil'],
			],
		];
		$errors = [];

		$resolved = $this->section()->validate($bundle, $errors);

		$this->assertSame(
			[
				['section' => 'customTokenSets', 'index' => 0, 'message' => 'Entry must be an object.'],
				['section' => 'customTokenSets', 'index' => 1, 'id' => 'bad id', 'message' => 'Invalid custom token set id.'],
				['section' => 'customTokenSets', 'index' => 2, 'id' => 'custom-a', 'message' => 'Missing "name".'],
				['section' => 'customTokenSets', 'index' => 3, 'id' => 'custom-b', 'message' => 'Missing or invalid "css".'],
				['section' => 'customTokenSets', 'index' => 4, 'id' => 'custom-c', 'message' => 'The CSS contains a selector or at-rule other than :root.'],
			],
			array_slice($errors, 0, 5)
		);
		$this->assertCount(6, $errors);
		$this->assertSame(['section', 'index', 'id', 'message'], array_keys($errors[5]));
		$this->assertSame([5, 'custom-d'], [$errors[5]['index'], $errors[5]['id']]);
		$this->assertSame(
			[
				[
					'id' => 'custom-e',
					'entry' => [
						'name' => 'Gemeente E',
						'description' => 'Huisstijl',
						'theming' => ['primary_color' => '#154273'],
						'warnings' => ['w'],
						'version' => '1.2.0',
						'importWarnings' => ['i'],
						'design_system' => 'nldesign',
					],
					'css' => (new CustomTokenSetValidator())->serialize(declarations: ['--nldesign-color-primary' => '#154273']),
				],
				[
					'id' => 'custom-f',
					'entry' => ['name' => 'F', 'description' => '', 'theming' => []],
					'css' => (new CustomTokenSetValidator())->serialize(declarations: ['--nldesign-color-primary' => '#154273']),
				],
			],
			$resolved
		);
	}//end testEachSetIsValidatedAndResolved()
}//end class
