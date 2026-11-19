<?php

/**
 * The Rijkshuisstijl set carries the community tokens' values and declares which release it follows.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-set-licences/spec.md#requirement-every-shipped-organisation-theme-declares-its-licence
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\CssParserService;
use PHPUnit\Framework\TestCase;

/**
 * `css/tokens/rijkshuisstijl.css` is converted by `scripts/convert-nlds-theme.mjs`
 * from `@rijkshuisstijl-community/design-tokens` (`dist/index.css`), which
 * ships its colours as oklch(). These are the sRGB values that conversion
 * writes, so a drift in either direction (a hand edit, or a new upstream
 * release the manifest does not name) fails here instead of on a rendered
 * instance.
 */
class RijkshuisstijlTokenSetTest extends TestCase {

	/**
	 * The release the set follows, as the manifest records it.
	 */
	private const UPSTREAM_VERSION = '18.0.1';

	/**
	 * The converted release's values, in sRGB, under the `--nldesign-*` name
	 * the converter maps them to.
	 */
	private const UPSTREAM_VALUES = [
		'--nldesign-color-primary' => '#154273',
		'--nldesign-color-primary-hover' => '#162f50',
		'--nldesign-color-link' => '#01689b',
		'--nldesign-color-link-hover' => '#01496c',
		'--nldesign-color-text' => '#0f172a',
		'--nldesign-color-text-muted' => '#334155',
		'--nldesign-color-border' => '#154273',
		'--nldesign-color-border-dark' => '#738eab',
		'--nldesign-color-success' => '#39870c',
		'--nldesign-color-error' => '#d70d0d',
		'--nldesign-color-info' => '#154273',
		'--nldesign-border-radius' => '8px',
	];

	/**
	 * The app root.
	 * @return string The path.
	 */
	private function root(): string {
		return dirname(__DIR__, 2);
	}//end root()

	/**
	 * The set's light declarations.
	 * @return array<string, string> Name to value.
	 */
	private function declarations(): array {
		$css = (string)file_get_contents($this->root() . '/css/tokens/rijkshuisstijl.css');
		return (new CssParserService())->parseDeclarations($css);
	}//end declarations()

	/**
	 * The set's manifest entry.
	 * @return array<string, mixed> The entry.
	 */
	private function manifestEntry(): array {
		$sets = json_decode((string)file_get_contents($this->root() . '/token-sets.json'), true);
		foreach ($sets as $entry) {
			if ($entry['id'] === 'rijkshuisstijl') {
				return $entry;
			}
		}

		$this->fail(message: 'token-sets.json lists no rijkshuisstijl set');
	}//end manifestEntry()

	/**
	 * The set carries the community token values.
	 *
	 * @return void
	 */
	public function testTheSetCarriesTheCommunityTokenValues(): void {
		$declarations = $this->declarations();
		foreach (self::UPSTREAM_VALUES as $name => $value) {
			$this->assertArrayHasKey(key: $name, array: $declarations, message: $name . ' is not declared');
			$this->assertSame(expected: $value, actual: strtolower($declarations[$name]), message: $name);
		}
	}//end testTheSetCarriesTheCommunityTokenValues()

	/**
	 * The font stack names the restricted RijksSansVF and falls through to Fira Sans,
	 * and the manifest asks an entitled administrator to upload the real face.
	 *
	 * @return void
	 */
	public function testTheFontStackFallsThroughToFiraSans(): void {
		$family = $this->declarations()['--nldesign-font-family'] ?? '';
		$this->assertStringStartsWith(prefix: "'RijksSansVF', 'Fira Sans'", string: $family);

		$font = $this->manifestEntry()['font'] ?? [];
		$this->assertSame(expected: 'RijksSansVF', actual: ($font['family'] ?? null));
		$this->assertFalse(condition: ($font['selfHosted'] ?? true), message: 'the government font is not shipped');
		$this->assertSame(expected: 'restricted', actual: ($font['licence'] ?? null));
		$this->assertSame(expected: 'upload', actual: ($font['action'] ?? null));
	}//end testTheFontStackFallsThroughToFiraSans()

	/**
	 * The manifest names the upstream release.
	 *
	 * @return void
	 */
	public function testTheManifestNamesTheUpstreamRelease(): void {
		$licence = $this->manifestEntry()['licence'] ?? [];
		$this->assertSame(expected: '@rijkshuisstijl-community/design-tokens', actual: ($licence['package'] ?? null));
		$this->assertSame(expected: self::UPSTREAM_VERSION, actual: ($licence['version'] ?? null));
		$this->assertTrue(condition: ($licence['restricted'] ?? false), message: 'the house style stays behind the licence gate');
	}//end testTheManifestNamesTheUpstreamRelease()

	/**
	 * The licence table records the same release.
	 *
	 * @return void
	 */
	public function testTheLicenceTableRecordsTheSameRelease(): void {
		$table = (string)file_get_contents($this->root() . '/docs/reference/token-set-licences.md');
		$this->assertStringContainsString(
			needle: '| `rijkshuisstijl` | `@rijkshuisstijl-community/design-tokens` | ' . self::UPSTREAM_VERSION . ' |',
			haystack: $table,
		);
	}//end testTheLicenceTableRecordsTheSameRelease()
}//end class
