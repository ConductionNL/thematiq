<?php

/**
 * A set downloaded for Figma and uploaded again is the same set, for every token the format carries.
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
 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-round-trip-keeps-every-token-the-format-carries
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ColorSpaceConverter;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssColorParser;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\DesignTokensWriter;
use OCA\Thematiq\Service\FigmaTokenWriter;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\MultiBrandSource;
use OCA\Thematiq\Service\OpenProjectSetFile;
use OCA\Thematiq\Service\ShellRoleMapper;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Download, upload, compare, for every shipped set and every Figma format.
 *
 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-round-trip-keeps-every-token-the-format-carries
 */
final class FigmaTokenFilesRoundTripTest extends TestCase {

	/**
	 * The app directory.
	 *
	 * @return string
	 */
	private static function appPath(): string {
		return \dirname(__DIR__, 3);
	}//end appPath()

	/**
	 * Every shipped set, in each of the three Figma formats.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function setsAndFormats(): array {
		$out = [];
		foreach ((array)json_decode((string)file_get_contents(self::appPath() . '/token-sets.json'), true) as $set) {
			$id = (string)($set['id'] ?? '');
			if ($id === '' || is_file(self::appPath() . '/css/tokens/' . $id . '.css') === false) {
				continue;
			}

			// A set in another vocabulary (summer-breeze) has no token of ours to compare.
			if (preg_match('/^\s*--nldesign-[a-z0-9-]+\s*:/m', (string)file_get_contents(self::appPath() . '/css/tokens/' . $id . '.css')) !== 1) {
				continue;
			}

			foreach (['figma', 'tokens-studio', 'tokens-studio-legacy'] as $format) {
				$out[$id . ' as ' . $format] = [$id, $format];
			}
		}

		return $out;
	}//end setsAndFormats()

	/**
	 * Upload a document the way the admin upload does.
	 *
	 * @param string $json The file.
	 *
	 * @return array{declarations: array<string, string>, skipped: array<int, string>, imported: int, dark: array<string, string>}
	 */
	private function upload(string $json): array {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(self::appPath());
		$parser = new CssParserService();
		$converter = new TokenSetConverterService($appManager, $parser, new ContrastService(), new DesignTokensMapper(), $this->createMock(FontService::class), $this->createMock(LoggerInterface::class));
		$result = $converter->convert(content: $json, slug: 'kopie', displayName: 'Kopie');

		$skipped = [];
		foreach ($result['report'] as $entry) {
			if (($entry['action'] ?? '') === 'skipped') {
				$skipped[] = $entry['source'] . ': ' . $entry['reason'];
			}
		}

		return ['declarations' => $parser->parseRootBlock(css: (string)$result['css']), 'skipped' => $skipped, 'imported' => (int)$result['imported'], 'dark' => ($result['darkDeclarations'] ?? [])];
	}//end upload()

	/**
	 * A set's declarations as a download in one format.
	 *
	 * @param array<string, string> $declarations The declarations.
	 * @param string $format The format.
	 *
	 * @return array<string, mixed> The document.
	 */
	private function download(array $declarations, string $format): array {
		$document = (new DesignTokensWriter())->write(declarations: $declarations, setId: 'bron', setName: 'Bron', appVersion: 'test');

		return (new FigmaTokenWriter())->write(document: $document, declarations: $declarations, format: $format, setId: 'bron', setName: 'Bron');
	}//end download()

	/**
	 * A value with every var() to a name the set declares replaced by that value.
	 *
	 * @param string $value The value.
	 * @param array<string, string> $set The set's declarations.
	 * @param int $depth Recursion guard.
	 *
	 * @return string
	 */
	private function resolved(string $value, array $set, int $depth = 0): string {
		if ($depth > 10) {
			return $value;
		}

		return (string)preg_replace_callback(
			'/var\(\s*(--[A-Za-z0-9_-]+)\s*\)/',
			fn (array $m): string => isset($set[$m[1]]) === true ? $this->resolved(value: $set[$m[1]], set: $set, depth: ($depth + 1)) : $m[0],
			$value
		);
	}//end resolved()

	/**
	 * A value as compared: a colour as its sRGB hex, anything else with collapsed spaces.
	 *
	 * @param string $value The value.
	 *
	 * @return string
	 */
	private function normalise(string $value): string {
		$parsed = (new CssColorParser())->parse(value: $value);
		if ($parsed !== null) {
			$converter = new ColorSpaceConverter();

			return $converter->toHex(rgb: (array)$converter->toSrgb(space: $parsed['space'], components: $parsed['components']), alpha: $parsed['alpha']);
		}

		return (string)preg_replace('/\s+/', ' ', trim($value));
	}//end normalise()

	/**
	 * The CSS names a Tokens Studio file has no place for: a name that is also the start of
	 * another name cannot be a token and a group at once.
	 *
	 * @param array<int, string> $names Every name of the set.
	 *
	 * @return array<string, true> The names without a place.
	 */
	private function withoutAPlace(array $names): array {
		$paths = [];
		foreach ($names as $name) {
			$paths[$name] = implode('.', array_filter(explode('-', ltrim($name, '-'), 3), static fn (string $part): bool => $part !== ''));
		}

		$out = [];
		foreach ($paths as $name => $path) {
			foreach ($paths as $other => $otherPath) {
				if ($name !== $other && ($path === $otherPath || str_starts_with($otherPath, $path . '.') === true || str_starts_with($path, $otherPath . '.') === true)) {
					$out[$name] = true;
				}
			}
		}

		return $out;
	}//end withoutAPlace()

	/**
	 * Scenario: every shipped set survives the round trip in every Figma format.
	 *
	 * @param string $id The set id.
	 * @param string $format The format.
	 *
	 * @return void
	 */
	#[DataProvider('setsAndFormats')]
	public function testShippedSetSurvivesTheRoundTrip(string $id, string $format): void {
		$original = (new CssParserService())->parseRootBlock(css: (string)file_get_contents(self::appPath() . '/css/tokens/' . $id . '.css'));
		$back = $this->upload(json: (string)json_encode($this->download(declarations: $original, format: $format)));

		$noPlace = [];
		if ($format !== 'figma') {
			$noPlace = $this->withoutAPlace(names: array_keys($original));
		}

		$differences = [];
		$compared = 0;
		foreach ($original as $name => $value) {
			if (preg_match('/^--nldesign-[a-z0-9-]+$/', $name) !== 1 || isset($noPlace[$name]) === true) {
				continue;
			}

			$compared++;
			$returned = '(missing)';
			if (isset($back['declarations'][$name]) === true) {
				$returned = $this->normalise(value: $this->resolved(value: $back['declarations'][$name], set: $back['declarations']));
			}

			if ($returned !== $this->normalise(value: $this->resolved(value: $value, set: $original))) {
				$differences[$name] = $value . ' => ' . $returned;
			}
		}

		$this->assertSame([], $differences, $id . ' as ' . $format);
		$this->assertGreaterThan(0, $compared, 'the set has tokens to compare');
	}//end testShippedSetSurvivesTheRoundTrip()

	/**
	 * Scenario: a file that went through a Figma variables tool keeps its colours.
	 *
	 * Such a tool keeps colours and numbers and drops every extension, so the CSS name
	 * has to come back from the path alone.
	 *
	 * @return void
	 */
	public function testColoursSurviveAToolThatDropsTheExtensions(): void {
		$original = (new CssParserService())->parseRootBlock(css: (string)file_get_contents(self::appPath() . '/css/tokens/zuiddrecht.css'));
		$document = (array)json_decode((string)json_encode($this->download(declarations: $original, format: 'figma')), true);
		$kept = $this->coloursOnly(node: $document);

		$back = $this->upload(json: (string)json_encode($kept));

		$this->assertSame(
			$this->normalise(value: $this->resolved(value: $original['--nldesign-color-primary'], set: $original)),
			$this->normalise(value: $this->resolved(value: $back['declarations']['--nldesign-color-primary'], set: $back['declarations']))
		);
		$this->assertGreaterThan(20, $back['imported']);
		$this->assertSame([], $back['skipped']);
	}//end testColoursSurviveAToolThatDropsTheExtensions()

	/**
	 * A document as a variables tool hands it back: colour tokens only, no extensions.
	 *
	 * @param array<string, mixed> $node A group.
	 *
	 * @return array<string, mixed> The group.
	 */
	private function coloursOnly(array $node): array {
		$out = [];
		foreach ($node as $key => $child) {
			if (is_array($child) === false || str_starts_with((string)$key, '$') === true) {
				continue;
			}

			if (array_key_exists('$value', $child) === false) {
				$group = $this->coloursOnly(node: $child);
				if ($group !== []) {
					$out[$key] = $group;
				}

				continue;
			}

			if (($child['$type'] ?? null) === 'color') {
				$out[$key] = ['$type' => 'color', '$value' => $child['$value']];
			}
		}

		return $out;
	}//end coloursOnly()

	/**
	 * Scenario: the fixture is read the same in both spellings.
	 *
	 * @return void
	 */
	public function testBothSpellingsGiveTheSameSet(): void {
		$dollar = $this->upload(json: (string)file_get_contents(self::appPath() . '/tests/fixtures/figma/tokens-studio-dtcg.json'));
		$legacy = $this->upload(json: (string)file_get_contents(self::appPath() . '/tests/fixtures/figma/tokens-studio-legacy.json'));

		$this->assertSame($dollar['declarations'], $legacy['declarations']);
		$this->assertSame('#0b5fff', $dollar['declarations']['--nldesign-color-primary']);
		$this->assertSame('#0b5fff', $dollar['declarations']['--nldesign-color-link'], 'a colour reference is stored as the colour it points at');
		$this->assertSame('#1f883d', $dollar['declarations']['--nldesign-color-action']);
		$this->assertSame('6px', $dollar['declarations']['--nldesign-border-radius']);
		$this->assertSame('var(--nldesign-border-radius)', $dollar['declarations']['--nldesign-border-radius-large']);
		$this->assertSame("'Fira Sans', sans-serif", $dollar['declarations']['--nldesign-font-family']);
		$this->assertSame('700', $dollar['declarations']['--nldesign-font-weight-bold']);
	}//end testBothSpellingsGiveTheSameSet()

	/**
	 * Scenario: what a file holds and cannot be used is reported, not stored.
	 *
	 * @return void
	 */
	public function testWhatIsLeftOutIsReported(): void {
		$back = $this->upload(json: (string)file_get_contents(self::appPath() . '/tests/fixtures/figma/tokens-studio-dtcg.json'));

		$this->assertContains('concept: token-set-disabled', $back['skipped']);
		$this->assertContains('nldesign.spacing.loop-a: alias-cycle', $back['skipped']);
		$this->assertContains('nldesign.spacing.loop-b: alias-cycle', $back['skipped']);
		$this->assertContains('nldesign.spacing.double: value-not-a-css-value', $back['skipped']);
		$this->assertArrayNotHasKey('--nldesign-spacing-loop-a', $back['declarations']);
		$this->assertArrayNotHasKey('--nldesign-spacing-double', $back['declarations']);
		$this->assertArrayNotHasKey('--nldesign-color-concept', $back['declarations'], 'a switched off set is not read');
	}//end testWhatIsLeftOutIsReported()

	/**
	 * The files the OpenProject plugin downloads, for two of its design systems.
	 *
	 * Real files: built on 13 October 2026 from the plugin's own code
	 * (`ConductionNL/openproject-thematiq`, development at `451c453e`,
	 * `OpenProject::Thematiq::Figma.export`) in a throwaway Ruby container. Each
	 * `roles.json` beside them holds the values the plugin exported.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function pluginFiles(): array {
		$out = [];
		foreach (['cunningham', 'zuiddrecht'] as $id) {
			foreach (['w3c.tokens.json', 'tokens-studio.json', 'tokens-studio-legacy.json'] as $file) {
				$out[$id . ' ' . $file] = [$id, $file];
			}
		}

		return $out;
	}//end pluginFiles()

	/**
	 * Scenario: a file the OpenProject plugin downloads uploads into thematiq, and every
	 * shell role both products know has the plugin's value.
	 *
	 * @param string $id The plugin's design system.
	 * @param string $file The downloaded file.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-the-openproject-plugin-uploads-into-thematiq
	 */
	#[DataProvider('pluginFiles')]
	public function testAPluginDownloadKeepsEveryRole(string $id, string $file): void {
		$directory = self::appPath() . '/tests/fixtures/figma/plugin/';
		$expected = json_decode((string)file_get_contents($directory . $id . '.roles.json'), true)['tokens'];
		$back = $this->upload(json: (string)file_get_contents($directory . $id . '.' . $file));
		$roles = (new ShellRoleMapper(colours: new ContrastService()))->map(tokens: $back['declarations']);

		$differences = [];
		foreach ($expected as $role => $value) {
			if (array_key_exists($role, $roles) === false) {
				$differences[$role] = 'not a role of thematiq';
				continue;
			}

			if ($this->normalise(value: (string)$roles[$role]) !== $this->normalise(value: (string)$value)) {
				$differences[$role] = $value . ' => ' . $roles[$role];
			}
		}

		$this->assertSame([], $differences, $id . ' ' . $file);
		$this->assertCount(18, $expected, 'the plugin exported eighteen values for this design system');
		$this->assertSame($this->normalise(value: (string)$expected['text']), $this->normalise(value: $back['declarations']['--thematiq-shell-text']));

		// The dark colours are the set's dark values (dark-colours-from-a-file), in every format;
		// a light and dark file is not taken for two brands, and the dark set is not laid over the light one.
		$expectedDark = [];
		foreach ((array)(json_decode((string)file_get_contents($directory . $id . '.roles.json'), true)['dark'] ?? []) as $role => $value) {
			$expectedDark[OpenProjectSetFile::ROLE_TOKENS[$role]] = $value;
		}

		ksort($expectedDark);
		$dark = $back['dark'];
		ksort($dark);
		$this->assertNotSame([], $expectedDark, 'the plugin exported dark colours for this design system');
		$this->assertSame($expectedDark, array_map('strtolower', $dark), $id . ' ' . $file . ' dark');
		$this->assertSame([], $back['skipped']);
		if ($file !== 'w3c.tokens.json') {
			$this->assertSame([], (new MultiBrandSource())->detectBrands(content: (string)file_get_contents($directory . $id . '.' . $file)));
		}
	}//end testAPluginDownloadKeepsEveryRole()
}//end class
