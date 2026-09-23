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
	 */
	public function testGetTokensReturnsNonEmptyArray(): void {
		$tokens = TokenRegistry::getTokens();

		$this->assertNotEmpty($tokens);
		$this->assertIsArray($tokens);
	}

	/**
	 * Test that every token entry has the required keys: tab, type, label.
	 */
	public function testEveryTokenHasRequiredKeys(): void {
		$tokens = TokenRegistry::getTokens();

		foreach ($tokens as $name => $meta) {
			$this->assertArrayHasKey('tab', $meta, "Token {$name} is missing 'tab' key");
			$this->assertArrayHasKey('type', $meta, "Token {$name} is missing 'type' key");
			$this->assertArrayHasKey('label', $meta, "Token {$name} is missing 'label' key");
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
	 */
	public function testEveryTokenDeclaresItsLayer(): void {
		$tokens = TokenRegistry::getTokens();

		foreach ($tokens as $name => $meta) {
			$this->assertArrayHasKey('group', $meta, "Token {$name} is missing 'group'");
			$this->assertArrayHasKey('primary', $meta, "Token {$name} is missing 'primary'");
			$this->assertIsBool($meta['primary'], "Token {$name} has a non-boolean 'primary'");
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
	 */
	public function testTheBrandLayerIsNeverLocked(): void {
		$brand = TokenRegistry::getBrandTokens();

		$this->assertNotEmpty($brand);
		foreach ($brand as $name => $meta) {
			$this->assertSame('brand', $meta['group'], "Brand token {$name} is not grouped under 'brand'");
			$this->assertFalse($meta['primary'], "Brand token {$name} would be locked by the setting");
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
	 */
	public function testTheComponentLayerNamesTheGlobalItReplaces(): void {
		$components = TokenRegistry::getComponentTokens();

		$this->assertNotEmpty($components, 'the mapping table produced no component tokens');

		foreach ($components as $name => $meta) {
			$this->assertStringStartsWith('--nldesign-component-', $name);
			$this->assertNotSame('brand', $meta['group'], "Component token {$name} claims the brand layer");
			$this->assertArrayHasKey('global', $meta, "Component token {$name} names no global");
			$this->assertStringStartsWith('--', $meta['global'], "Component token {$name} has a malformed global");
		}
	}//end testTheComponentLayerNamesTheGlobalItReplaces()

	/**
	 * getTokens() is both layers, and neither shadows the other.
	 *
	 * @spec openspec/specs/component-tokens/spec.md
	 */
	public function testGetTokensIsBothLayers(): void {
		$brand = TokenRegistry::getBrandTokens();
		$components = TokenRegistry::getComponentTokens();
		$all = TokenRegistry::getTokens();

		$this->assertSame([], array_intersect_key($brand, $components), 'the two layers share a token name');
		$this->assertCount(count($brand) + count($components), $all);
	}//end testGetTokensIsBothLayers()

	/**
	 * Test that all token names start with '--'.
	 */
	public function testTokenNamesStartWithDoubleDash(): void {
		$tokens = TokenRegistry::getTokens();

		foreach (array_keys($tokens) as $name) {
			$this->assertStringStartsWith('--', $name, "Token name '{$name}' should start with '--'");
		}
	}

	/**
	 * Test that all token types are either 'color' or 'text'.
	 */
	public function testTokenTypesAreValid(): void {
		$tokens = TokenRegistry::getTokens();
		$validTypes = ['color', 'text'];

		foreach ($tokens as $name => $meta) {
			$this->assertContains(
				$meta['type'],
				$validTypes,
				"Token {$name} has invalid type '{$meta['type']}'"
			);
		}
	}

	/**
	 * Test that all token tabs are among the known set.
	 */
	public function testTokenTabsAreValid(): void {
		$tokens = TokenRegistry::getTokens();
		$validTabs = ['login', 'content', 'status', 'typography'];

		foreach ($tokens as $name => $meta) {
			$this->assertContains(
				$meta['tab'],
				$validTabs,
				"Token {$name} has invalid tab '{$meta['tab']}'"
			);
		}
	}

	/**
	 * Test getTabLabels returns labels for all known tabs.
	 */
	public function testGetTabLabelsCoversAllTabs(): void {
		$tabLabels = TokenRegistry::getTabLabels();

		$this->assertArrayHasKey('login', $tabLabels);
		$this->assertArrayHasKey('content', $tabLabels);
		$this->assertArrayHasKey('status', $tabLabels);
		$this->assertArrayHasKey('typography', $tabLabels);
		$this->assertCount(4, $tabLabels);
	}

	/**
	 * Test getTokenNames returns the same keys as getTokens.
	 */
	public function testGetTokenNamesMatchesGetTokensKeys(): void {
		$names = TokenRegistry::getTokenNames();
		$tokenKeys = array_keys(TokenRegistry::getTokens());

		$this->assertSame($tokenKeys, $names);
	}

	/**
	 * Test isEditable returns true for a known token.
	 */
	public function testIsEditableReturnsTrueForKnownToken(): void {
		$this->assertTrue(TokenRegistry::isEditable('--color-primary'));
		$this->assertTrue(TokenRegistry::isEditable('--font-face'));
	}

	/**
	 * Test isEditable returns false for an unknown token.
	 */
	public function testIsEditableReturnsFalseForUnknownToken(): void {
		$this->assertFalse(TokenRegistry::isEditable('--nonexistent-token'));
		$this->assertFalse(TokenRegistry::isEditable(''));
		$this->assertFalse(TokenRegistry::isEditable('color-primary'));
	}

	/**
	 * Test getTokensByTab groups tokens correctly.
	 */
	public function testGetTokensByTabGroupsCorrectly(): void {
		$grouped = TokenRegistry::getTokensByTab();

		// Should have exactly 4 tab groups.
		$this->assertCount(4, $grouped);
		$this->assertArrayHasKey('login', $grouped);
		$this->assertArrayHasKey('content', $grouped);
		$this->assertArrayHasKey('status', $grouped);
		$this->assertArrayHasKey('typography', $grouped);

		// Every token within a group should have the matching tab value.
		foreach ($grouped as $tab => $tokens) {
			foreach ($tokens as $name => $meta) {
				$this->assertSame(
					$tab,
					$meta['tab'],
					"Token {$name} is in tab group '{$tab}' but has tab value '{$meta['tab']}'"
				);
			}
		}
	}

	/**
	 * Test that the total count of grouped tokens equals the total token count.
	 */
	public function testGetTokensByTabPreservesAllTokens(): void {
		$grouped = TokenRegistry::getTokensByTab();
		$totalGrouped = 0;
		foreach ($grouped as $tokens) {
			$totalGrouped += count($tokens);
		}

		$this->assertSame(count(TokenRegistry::getTokens()), $totalGrouped);
	}

	/**
	 * Test that --color-primary exists and belongs to the login tab.
	 */
	public function testColorPrimaryIsInLoginTab(): void {
		$tokens = TokenRegistry::getTokens();

		$this->assertArrayHasKey('--color-primary', $tokens);
		$this->assertSame('login', $tokens['--color-primary']['tab']);
		$this->assertSame('color', $tokens['--color-primary']['type']);
	}
}
