<?php

/**
 * The committed docs/reference/token-sets pages match what the generator produces from the
 * token files, like the contrast report.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/token-reference/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenReferenceService;
use PHPUnit\Framework\TestCase;

/**
 * Staleness guard for the generated token reference pages.
 */
class TokenReferenceDocsTest extends TestCase {

	/**
	 * Every generated page exists and is current, and no stale page is left behind.
	 *
	 * @return void
	 */
	public function testTheCommittedPagesAreCurrent(): void {
		$root = \dirname(__DIR__, 2);
		$parser = new CssParserService();
		$service = new TokenReferenceService(new ShippedTokenSetAuditService(new ContrastService(), $parser), $parser);

		$pages = $service->docsPages(appPath: $root);
		$this->assertArrayHasKey('index.md', $pages);
		$this->assertArrayHasKey('amsterdam.md', $pages);

		foreach ($pages as $name => $content) {
			$path = $root . '/docs/reference/token-sets/' . $name;
			$this->assertFileExists($path, "docs/reference/token-sets/{$name} is missing. Run: composer docs:token-reference");
			$this->assertSame($content, file_get_contents($path), "docs/reference/token-sets/{$name} is stale. Run: composer docs:token-reference");
		}

		$committed = array_map('basename', glob($root . '/docs/reference/token-sets/*.md') ?: []);
		sort($committed);
		$expected = array_keys($pages);
		sort($expected);
		$this->assertSame($expected, $committed, 'docs/reference/token-sets holds a page no set produces. Run: composer docs:token-reference');
	}//end testTheCommittedPagesAreCurrent()

	/**
	 * The Amsterdam page names the primary colour, its value and what it paints.
	 *
	 * @return void
	 */
	public function testTheAmsterdamPageListsThePrimaryColour(): void {
		$page = (string)file_get_contents(\dirname(__DIR__, 2) . '/docs/reference/token-sets/amsterdam.md');

		$this->assertMatchesRegularExpression('/^\| `--nldesign-color-primary` \| .+ \| this set \| .*Primary element color.* \|$/m', $page);
		$this->assertStringContainsString('| defaults |', $page);
	}//end testTheAmsterdamPageListsThePrimaryColour()

	/**
	 * The user docs state the count the token editor states.
	 *
	 * @spec openspec/specs/token-editor-ui/spec.md
	 */
	public function testTheDocsStateTheEditableCount(): void {
		$count = \OCA\Thematiq\Service\TokenRegistry::countEditable();
		foreach (['docs/features/token-editor.md', 'docs/features/import-export.md'] as $page) {
			$text = (string)file_get_contents(\dirname(__DIR__, 2) . '/' . $page);
			$this->assertStringContainsString('<!-- editable-count -->' . $count . '<!-- /editable-count -->', $text, $page . ' states another count. Run: composer docs:token-reference');
			$this->assertDoesNotMatchRegularExpression('#<!-- editable-count -->(?!' . $count . '<)#', $text, $page);
		}
	}
}//end class
