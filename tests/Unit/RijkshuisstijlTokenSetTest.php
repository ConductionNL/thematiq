<?php

/**
 * The Rijkshuisstijl set carries the community tokens' values and declares which release it follows.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/shipped-set-licences/specs/token-set-licences/spec.md#requirement-every-shipped-organisation-theme-declares-its-licence
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\CssParserService;
use PHPUnit\Framework\TestCase;

/**
 * `css/tokens/rijkshuisstijl.css` is hand-mapped from
 * `@rijkshuisstijl-community/design-tokens` (theme `lintblauw`), which ships
 * its colours as oklch(). These are the sRGB values of that release, so a
 * drift in either direction (a hand edit, or a new upstream release the
 * manifest does not name) fails here instead of on a rendered instance.
 */
class RijkshuisstijlTokenSetTest extends TestCase {

	/**
	 * The release the set follows, as the manifest records it.
	 */
	private const UPSTREAM_VERSION = '18.0.1';

	/**
	 * rhc.* tokens of the lintblauw theme, converted to sRGB, under the
	 * `--nldesign-*` name the set maps them to.
	 */
	private const UPSTREAM_VALUES = [
		'--nldesign-color-primary' => '#154273',
		'--nldesign-color-primary-hover' => '#162f50',
		'--nldesign-color-link' => '#01689b',
		'--nldesign-color-link-hover' => '#007bc7',
		'--nldesign-color-text' => '#0f172a',
		'--nldesign-color-text-muted' => '#334155',
		'--nldesign-color-border' => '#cbd5e1',
		'--nldesign-color-border-dark' => '#64748b',
		'--nldesign-color-success' => '#39870c',
		'--nldesign-color-error' => '#d52b1e',
		'--nldesign-color-info' => '#007bc7',
		'--nldesign-color-accent' => '#007bc7',
		'--nldesign-color-footer-background' => '#154273',
		'--nldesign-color-surface' => '#f1f5f9',
		'--nldesign-component-status-badge-info-color' => '#01496c',
		'--nldesign-component-status-badge-success-color' => '#2c5d12',
		'--nldesign-component-status-badge-warning-color' => '#673327',
		'--nldesign-component-status-badge-error-color' => '#9e1b16',
		'--nldesign-border-radius' => '0',
		'--nldesign-website-border-radius' => '2px',
		'--nldesign-website-border-radius-large' => '4px',
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

		$this->fail('token-sets.json lists no rijkshuisstijl set');
	}//end manifestEntry()

	public function testTheSetCarriesTheCommunityTokenValues(): void {
		$declarations = $this->declarations();
		foreach (self::UPSTREAM_VALUES as $name => $value) {
			$this->assertArrayHasKey($name, $declarations, $name . ' is not declared');
			$this->assertSame($value, strtolower($declarations[$name]), $name);
		}
	}//end testTheSetCarriesTheCommunityTokenValues()

	public function testTheFontStackFallsThroughToFiraSans(): void {
		$family = $this->declarations()['--nldesign-font-family'] ?? '';
		$this->assertStringStartsWith("'Fira Sans'", $family);
		$this->assertStringNotContainsStringIgnoringCase('Rijksoverheid', $family, 'the government fonts are restricted');
	}//end testTheFontStackFallsThroughToFiraSans()

	public function testTheManifestNamesTheUpstreamRelease(): void {
		$licence = $this->manifestEntry()['licence'] ?? [];
		$this->assertSame('@rijkshuisstijl-community/design-tokens', ($licence['package'] ?? null));
		$this->assertSame(self::UPSTREAM_VERSION, ($licence['version'] ?? null));
		$this->assertTrue(($licence['restricted'] ?? false), 'the house style stays behind the licence gate');
	}//end testTheManifestNamesTheUpstreamRelease()

	public function testTheLicenceTableRecordsTheSameRelease(): void {
		$table = (string)file_get_contents($this->root() . '/docs/reference/token-set-licences.md');
		$this->assertStringContainsString(
			'| `rijkshuisstijl` | `@rijkshuisstijl-community/design-tokens` | ' . self::UPSTREAM_VERSION . ' |',
			$table,
		);
	}//end testTheLicenceTableRecordsTheSameRelease()
}//end class
