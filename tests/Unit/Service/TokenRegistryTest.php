<?php

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\TokenRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TokenRegistry.
 */
class TokenRegistryTest extends TestCase {
	/**
	 * Test that getTokens returns a non-empty array with correct structure.
	 *
	 * @return void
	 */
	public function testGetTokensReturnsNonEmptyArray(): void {
		$tokens = TokenRegistry::getTokens();

		$this->assertNotEmpty(actual: $tokens);
		$this->assertIsArray(actual: $tokens);
	}

	/**
	 * Test that every token entry has the required keys: tab, type, label.
	 *
	 * @return void
	 */
	public function testEveryTokenHasRequiredKeys(): void {
		$tokens = TokenRegistry::getTokens();

		foreach ($tokens as $name => $meta) {
			$this->assertArrayHasKey(key: 'tab', array: $meta, message: "Token {$name} is missing 'tab' key");
			$this->assertArrayHasKey(key: 'type', array: $meta, message: "Token {$name} is missing 'type' key");
			$this->assertArrayHasKey(key: 'label', array: $meta, message: "Token {$name} is missing 'label' key");
		}
	}

	/**
	 * The registry is two layers, and every entry says which one it is in.
	 *
	 * The editor renders the brand globals as the four-tab list and the
	 * component tokens under their component's own heading, so an entry with
	 * no `group` would land in neither. `primary` is the other half: it is
	 * what the `primary_drives_components` setting reads to decide which rows
	 * to lock.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 *
	 * @return void
	 */
	public function testEveryTokenDeclaresItsLayer(): void {
		$tokens = TokenRegistry::getTokens();

		foreach ($tokens as $name => $meta) {
			$this->assertArrayHasKey(key: 'group', array: $meta, message: "Token {$name} is missing 'group'");
			$this->assertArrayHasKey(key: 'primary', array: $meta, message: "Token {$name} is missing 'primary'");
			$this->assertIsBool(actual: $meta['primary'], message: "Token {$name} has a non-boolean 'primary'");
		}
	}//end testEveryTokenDeclaresItsLayer()

	/**
	 * The brand layer is grouped under `brand` and is never locked.
	 *
	 * The `primary_drives_components` setting exists to make these globals
	 * win, not to freeze them — a brand token flagged `primary` would lock the
	 * very control the setting hands authority to.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 *
	 * @return void
	 */
	public function testTheBrandLayerIsNeverLocked(): void {
		$brand = TokenRegistry::getBrandTokens();

		$this->assertNotEmpty(actual: $brand);
		foreach ($brand as $name => $meta) {
			$this->assertSame(expected: 'brand', actual: $meta['group'], message: "Brand token {$name} is not grouped under 'brand'");
			$this->assertFalse(condition: $meta['primary'], message: "Brand token {$name} would be locked by the setting");
		}
	}//end testTheBrandLayerIsNeverLocked()

	/**
	 * The component layer is read from the shared mapping table, and each
	 * entry carries the Nextcloud global it stands in for.
	 *
	 * That global is not decoration: a component token is deliberately
	 * undeclared until someone sets one, so the editor reads the global to
	 * show the colour the component is ACTUALLY wearing rather than a blank
	 * swatch. An entry without it renders an empty control.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 *
	 * @return void
	 */
	public function testTheComponentLayerNamesTheGlobalItReplaces(): void {
		$components = TokenRegistry::getComponentTokens();

		$this->assertNotEmpty(actual: $components, message: 'the mapping table produced no component tokens');

		foreach ($components as $name => $meta) {
			$this->assertStringStartsWith(prefix: '--nldesign-component-', string: $name);
			$this->assertNotSame(expected: 'brand', actual: $meta['group'], message: "Component token {$name} claims the brand layer");
			$this->assertArrayHasKey(key: 'global', array: $meta, message: "Component token {$name} names no global");
			$this->assertStringStartsWith(prefix: '--', string: $meta['global'], message: "Component token {$name} has a malformed global");
		}
	}//end testTheComponentLayerNamesTheGlobalItReplaces()

	/**
	 * getTokens() is both layers, and neither shadows the other.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 *
	 * @return void
	 */
	public function testGetTokensIsBothLayers(): void {
		$brand = TokenRegistry::getBrandTokens();
		$components = TokenRegistry::getComponentTokens();
		$all = TokenRegistry::getTokens();

		$this->assertSame(expected: [], actual: array_intersect_key($brand, $components), message: 'the two layers share a token name');
		$this->assertCount(expectedCount: count($brand) + count($components), haystack: $all);
	}//end testGetTokensIsBothLayers()

	/**
	 * Test that all token names start with '--'.
	 *
	 * @return void
	 */
	public function testTokenNamesStartWithDoubleDash(): void {
		$tokens = TokenRegistry::getTokens();

		foreach (array_keys($tokens) as $name) {
			$this->assertStringStartsWith(prefix: '--', string: $name, message: "Token name '{$name}' should start with '--'");
		}
	}

	/**
	 * Test that all token types are either 'color' or 'text'.
	 *
	 * @return void
	 */
	public function testTokenTypesAreValid(): void {
		$tokens = TokenRegistry::getTokens();
		// `rgb`: a bare `r, g, b` triplet (the note cards' Nextcloud 32 fills),
		// edited with a colour picker that writes the triplet.
		$validTypes = ['color', 'text', 'rgb', 'duration', 'easing'];

		foreach ($tokens as $name => $meta) {
			$this->assertContains(
				needle: $meta['type'],
				haystack: $validTypes,
				message: "Token {$name} has invalid type '{$meta['type']}'"
			);
		}
	}

	/**
	 * Test that all token tabs are among the known set.
	 *
	 * @return void
	 */
	public function testTokenTabsAreValid(): void {
		$tokens = TokenRegistry::getTokens();
		$validTabs = ['login', 'content', 'status', 'typography'];

		foreach ($tokens as $name => $meta) {
			$this->assertContains(
				needle: $meta['tab'],
				haystack: $validTabs,
				message: "Token {$name} has invalid tab '{$meta['tab']}'"
			);
		}
	}

	/**
	 * Test getTabLabels returns labels for all known tabs.
	 *
	 * @return void
	 */
	public function testGetTabLabelsCoversAllTabs(): void {
		$tabLabels = TokenRegistry::getTabLabels();

		$this->assertArrayHasKey(key: 'login', array: $tabLabels);
		$this->assertArrayHasKey(key: 'content', array: $tabLabels);
		$this->assertArrayHasKey(key: 'status', array: $tabLabels);
		$this->assertArrayHasKey(key: 'typography', array: $tabLabels);
		$this->assertCount(expectedCount: 4, haystack: $tabLabels);
	}

	/**
	 * Test getTokenNames returns the same keys as getTokens.
	 *
	 * @return void
	 */
	public function testGetTokenNamesMatchesGetTokensKeys(): void {
		$names = TokenRegistry::getTokenNames();
		$tokenKeys = array_keys(TokenRegistry::getTokens());

		$this->assertSame(expected: $tokenKeys, actual: $names);
	}

	/**
	 * Test isEditable returns true for a known token.
	 *
	 * @return void
	 */
	public function testIsEditableReturnsTrueForKnownToken(): void {
		$this->assertTrue(condition: TokenRegistry::isEditable('--color-primary'));
		$this->assertTrue(condition: TokenRegistry::isEditable('--font-face'));
	}

	/**
	 * Test isEditable returns false for an unknown token.
	 *
	 * @return void
	 */
	public function testIsEditableReturnsFalseForUnknownToken(): void {
		$this->assertFalse(condition: TokenRegistry::isEditable('--nonexistent-token'));
		$this->assertFalse(condition: TokenRegistry::isEditable(''));
		$this->assertFalse(condition: TokenRegistry::isEditable('color-primary'));
	}

	/**
	 * Test getTokensByTab groups tokens correctly.
	 *
	 * @return void
	 */
	public function testGetTokensByTabGroupsCorrectly(): void {
		$grouped = TokenRegistry::getTokensByTab();

		// Should have exactly 4 tab groups.
		$this->assertCount(expectedCount: 4, haystack: $grouped);
		$this->assertArrayHasKey(key: 'login', array: $grouped);
		$this->assertArrayHasKey(key: 'content', array: $grouped);
		$this->assertArrayHasKey(key: 'status', array: $grouped);
		$this->assertArrayHasKey(key: 'typography', array: $grouped);

		// Every token within a group should have the matching tab value.
		foreach ($grouped as $tab => $tokens) {
			foreach ($tokens as $name => $meta) {
				$this->assertSame(
					expected: $tab,
					actual: $meta['tab'],
					message: "Token {$name} is in tab group '{$tab}' but has tab value '{$meta['tab']}'"
				);
			}
		}
	}

	/**
	 * Test that the total count of grouped tokens equals the total token count.
	 *
	 * @return void
	 */
	public function testGetTokensByTabPreservesAllTokens(): void {
		$grouped = TokenRegistry::getTokensByTab();
		$totalGrouped = 0;
		foreach ($grouped as $tokens) {
			$totalGrouped += count($tokens);
		}

		$this->assertSame(expected: count(TokenRegistry::getTokens()), actual: $totalGrouped);
	}

	/**
	 * Test that --color-primary exists and belongs to the login tab.
	 *
	 * @return void
	 */
	public function testColorPrimaryIsInLoginTab(): void {
		$tokens = TokenRegistry::getTokens();

		$this->assertArrayHasKey(key: '--color-primary', array: $tokens);
		$this->assertSame(expected: 'login', actual: $tokens['--color-primary']['tab']);
		$this->assertSame(expected: 'color', actual: $tokens['--color-primary']['type']);
	}
}
