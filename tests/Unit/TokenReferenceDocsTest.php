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
use OCA\Thematiq\Tests\Unit\Support\FileList;
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
		$service = new TokenReferenceService(
			audit: new ShippedTokenSetAuditService(contrast: new ContrastService(), parser: $parser),
			parser: $parser
		);

		$pages = $service->docsPages(appPath: $root);
		$this->assertArrayHasKey(key: 'index.md', array: $pages);
		$this->assertArrayHasKey(key: 'amsterdam.md', array: $pages);

		foreach ($pages as $name => $content) {
			$path = $root . '/docs/reference/token-sets/' . $name;
			$this->assertFileExists(filename: $path, message: "docs/reference/token-sets/{$name} is missing. Run: composer docs:token-reference");
			$this->assertSame(
				expected: $content,
				actual: file_get_contents($path),
				message: "docs/reference/token-sets/{$name} is stale. Run: composer docs:token-reference"
			);
		}

		$committed = array_map('basename', FileList::glob(pattern: $root . '/docs/reference/token-sets/*.md'));
		sort($committed);
		$expected = array_keys($pages);
		sort($expected);
		$this->assertSame(
			expected: $expected,
			actual: $committed,
			message: 'docs/reference/token-sets holds a page no set produces. Run: composer docs:token-reference'
		);
	}//end testTheCommittedPagesAreCurrent()

	/**
	 * The Amsterdam page names the primary colour, its value and what it paints.
	 *
	 * @return void
	 */
	public function testTheAmsterdamPageListsThePrimaryColour(): void {
		$page = (string)file_get_contents(\dirname(__DIR__, 2) . '/docs/reference/token-sets/amsterdam.md');

		$this->assertMatchesRegularExpression(
			pattern: '/^\| `--nldesign-color-primary` \| .+ \| this set \| .*Primary element color.* \|$/m',
			string: $page
		);
		$this->assertStringContainsString(needle: '| defaults |', haystack: $page);
	}//end testTheAmsterdamPageListsThePrimaryColour()

	/**
	 * The user docs state the count the token editor states.
	 *
	 * @spec openspec/specs/token-editor-ui/spec.md
	 *
	 * @return void
	 */
	public function testTheDocsStateTheEditableCount(): void {
		$count = \OCA\Thematiq\Service\TokenRegistry::countEditable();
		foreach (['docs/features/token-editor.md', 'docs/features/import-export.md'] as $page) {
			$text = (string)file_get_contents(\dirname(__DIR__, 2) . '/' . $page);
			$this->assertStringContainsString(
				needle: '<!-- editable-count -->' . $count . '<!-- /editable-count -->',
				haystack: $text,
				message: $page . ' states another count. Run: composer docs:token-reference'
			);
			$this->assertDoesNotMatchRegularExpression(pattern: '#<!-- editable-count -->(?!' . $count . '<)#', string: $text, message: $page);
		}
	}
}//end class
