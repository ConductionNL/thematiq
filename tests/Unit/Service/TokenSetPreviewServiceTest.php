<?php

/**
 * Tests for TokenSetPreviewService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\TokenSetPreviewService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;

/**
 * The stylesheets read as data: the token layer of a set, and the map that
 * says which Nextcloud variable reads which token.
 *
 * Every collaborator of this class mocks it out, so nothing else in the suite
 * executes these methods — and they are the ones whose answers are hardest to
 * eyeball. `getTokenSources()` is the map `StockTokensService::build()`
 * INVERTS to decide what colour the header is painted, and `getResolvedTokens()`
 * is the sole source of the values the playground exports. Both parse real CSS
 * out of a file, so the fixtures here are CSS files on disk rather than arrays:
 * a test that skipped the parser would not be testing the part that can be
 * wrong.
 */
class TokenSetPreviewServiceTest extends TestCase {

	/**
	 * The fake app directory these tests write their fixtures into.
	 *
	 * @var string
	 */
	private string $appPath = '';

	/**
	 * Create the app directory layout the service reads from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appPath = sys_get_temp_dir() . '/thematiq-preview-' . uniqid();
		mkdir($this->appPath . '/css/systems/nldesign', 0777, true);
		mkdir($this->appPath . '/css/tokens', 0777, true);
	}//end setUp()

	/**
	 * Remove the fixtures again.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$files = glob($this->appPath . '/css/{systems/nldesign,tokens}/*.css', GLOB_BRACE);
		foreach (match ($files) {
			false => [], default => $files
		} as $file) {
			unlink($file);
		}

		foreach (
			[
				$this->appPath . '/css/systems/nldesign',
				$this->appPath . '/css/systems',
				$this->appPath . '/css/tokens',
				$this->appPath . '/css',
				$this->appPath,
			] as $directory
		) {
			if (is_dir($directory) === true) {
				rmdir($directory);
			}
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * The service, pointed at the fixture directory.
	 *
	 * @return TokenSetPreviewService The system under test.
	 */
	private function build(): TokenSetPreviewService {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appPath);

		return new TokenSetPreviewService(appManager: $appManager);
	}//end build()

	/**
	 * Write one of the stylesheets the service reads.
	 *
	 * @param string $relativePath Path under the fake app directory.
	 * @param string $css The file body.
	 *
	 * @return void
	 */
	private function writeCss(string $relativePath, string $css): void {
		file_put_contents($this->appPath . '/' . $relativePath, $css);
	}//end writeCss()

	/* ---------------------------------------------------------------- */
	/* getTokenSources()                                                 */
	/* ---------------------------------------------------------------- */

	/**
	 * The map runs Nextcloud variable => token, which is the direction the
	 * cascade reads and the direction `overrides.css` declares.
	 *
	 * @return void
	 */
	public function testTokenSourcesRunFromVariableToToken(): void {
		$this->writeCss(
			relativePath: 'css/systems/nldesign/overrides.css',
			css: ":root {\n"
			. "\t--color-primary: var(--nldesign-color-primary) !important;\n"
			. "\t--border-radius-element: var(--nldesign-border-radius-element);\n"
			. "}\n"
		);

		$sources = $this->build()->getTokenSources();

		$this->assertSame(
			expected: [
				'--color-primary' => '--nldesign-color-primary',
				'--border-radius-element' => '--nldesign-border-radius-element',
			],
			actual: $sources
		);
	}//end testTokenSourcesRunFromVariableToToken()

	/**
	 * Several variables may read ONE token, and all of them are reported.
	 *
	 * This is the case that makes the inverse many-to-one, which is the thing
	 * `StockTokensService::canonical()` exists to decide. A map that silently
	 * kept one of the four would hide the decision rather than settle it.
	 *
	 * @return void
	 */
	public function testEveryVariableReadingATokenIsReported(): void {
		$this->writeCss(
			relativePath: 'css/systems/nldesign/overrides.css',
			css: ":root {\n"
			. "\t--color-primary: var(--nldesign-color-primary) !important;\n"
			. "\t--color-primary-element: var(--nldesign-color-primary) !important;\n"
			. "\t--color-primary-light-text: var(--nldesign-color-primary);\n"
			. "}\n"
		);

		$sources = $this->build()->getTokenSources();

		$this->assertCount(expectedCount: 3, haystack: $sources);
		$this->assertSame(expected: '--nldesign-color-primary', actual: $sources['--color-primary']);
		$this->assertSame(expected: '--nldesign-color-primary', actual: $sources['--color-primary-element']);
		$this->assertSame(expected: '--nldesign-color-primary', actual: $sources['--color-primary-light-text']);
	}//end testEveryVariableReadingATokenIsReported()

	/**
	 * A declaration that is not a plain `var()` reference is not a mapping.
	 *
	 * A commented-out line records a variable the app deliberately does not
	 * map, and a literal or a composite is a value rather than a connection.
	 * Reporting either as a mapping would tell the stock resolver to write a
	 * token the cascade never reads.
	 *
	 * @return void
	 */
	public function testOnlyPlainVarReferencesAreMappings(): void {
		$this->writeCss(
			relativePath: 'css/systems/nldesign/overrides.css',
			css: ":root {\n"
			. "\t--color-primary: var(--nldesign-color-primary) !important;\n"
			. "\t/* --color-loading-light: var(--nldesign-color-loading-light); */\n"
			. "\t--color-box-shadow: rgba(0, 0, 0, 0.1);\n"
			. "\t--gradient: linear-gradient(var(--nldesign-color-primary), #fff);\n"
			. "}\n"
		);

		$sources = $this->build()->getTokenSources();

		$this->assertSame(expected: ['--color-primary' => '--nldesign-color-primary'], actual: $sources);
	}//end testOnlyPlainVarReferencesAreMappings()

	/**
	 * A missing `overrides.css` is an empty map, not a fatal.
	 *
	 * @return void
	 */
	public function testMissingOverridesGivesAnEmptyMap(): void {
		$this->assertSame(expected: [], actual: $this->build()->getTokenSources());
	}//end testMissingOverridesGivesAnEmptyMap()

	/* ---------------------------------------------------------------- */
	/* getResolvedTokens()                                               */
	/* ---------------------------------------------------------------- */

	/**
	 * The set's own file wins over the defaults behind it, and a token the set
	 * never mentions still comes back with its default.
	 *
	 * This is what makes the result a COMPLETE set, which is what an export
	 * has to be.
	 *
	 * @return void
	 */
	public function testResolvedTokensMergeTheSetOverTheDefaults(): void {
		$this->writeCss(
			relativePath: 'css/systems/nldesign/defaults.css',
			css: ":root {\n"
			. "\t--nldesign-color-primary: #0082c9;\n"
			. "\t--nldesign-color-error: #e9322d;\n"
			. "}\n"
		);
		$this->writeCss(
			relativePath: 'css/tokens/utrecht.css',
			css: ":root {\n\t--nldesign-color-primary: #cc0000;\n}\n"
		);

		$tokens = $this->build()->getResolvedTokens(tokenSetId: 'utrecht');

		$this->assertSame(expected: '#cc0000', actual: $tokens['--nldesign-color-primary']);
		$this->assertSame(expected: '#e9322d', actual: $tokens['--nldesign-color-error']);
	}//end testResolvedTokensMergeTheSetOverTheDefaults()

	/**
	 * A set with no file of its own resolves to the defaults alone.
	 *
	 * @return void
	 */
	public function testResolvedTokensFallBackToTheDefaultsAlone(): void {
		$this->writeCss(
			relativePath: 'css/systems/nldesign/defaults.css',
			css: ":root {\n\t--nldesign-color-primary: #0082c9;\n}\n"
		);

		$tokens = $this->build()->getResolvedTokens(tokenSetId: 'not-a-set');

		$this->assertSame(expected: ['--nldesign-color-primary' => '#0082c9'], actual: $tokens);
	}//end testResolvedTokensFallBackToTheDefaultsAlone()

	/**
	 * A token declared as a `var()` reference is carried through as written.
	 *
	 * The semantic layer is the token set's own vocabulary, and a set is
	 * entitled to declare one token in terms of another. Resolving it here
	 * would export a value the set never wrote.
	 *
	 * @return void
	 */
	public function testResolvedTokensCarryAVarReferenceThrough(): void {
		$this->writeCss(
			relativePath: 'css/systems/nldesign/defaults.css',
			css: ":root {\n\t--nldesign-color-primary: #0082c9;\n}\n"
		);
		$this->writeCss(
			relativePath: 'css/tokens/utrecht.css',
			css: ":root {\n"
			. "\t--nldesign-color-primary: #cc0000;\n"
			. "\t--nldesign-color-primary-hover: var(--nldesign-color-primary);\n"
			. "}\n"
		);

		$tokens = $this->build()->getResolvedTokens(tokenSetId: 'utrecht');

		$this->assertSame(
			expected: 'var(--nldesign-color-primary)',
			actual: $tokens['--nldesign-color-primary-hover']
		);
	}//end testResolvedTokensCarryAVarReferenceThrough()

	/* ---------------------------------------------------------------- */
	/* getDeclaredTokens() and the semantic layer                        */
	/* ---------------------------------------------------------------- */

	/**
	 * What the set DECIDED, without the defaults behind it.
	 *
	 * The difference from the resolved map is the whole point: only this one
	 * can answer "is this component touched by this set at all", because a
	 * merged map answers yes for every token.
	 *
	 * @return void
	 */
	public function testDeclaredTokensAreTheSetsOwnFileOnly(): void {
		$this->writeCss(
			relativePath: 'css/systems/nldesign/defaults.css',
			css: ":root {\n"
			. "\t--nldesign-color-primary: #0082c9;\n"
			. "\t--nldesign-color-error: #e9322d;\n"
			. "}\n"
		);
		$this->writeCss(
			relativePath: 'css/tokens/utrecht.css',
			css: ":root {\n\t--nldesign-color-primary: #cc0000;\n}\n"
		);

		$declared = $this->build()->getDeclaredTokens(tokenSetId: 'utrecht');

		$this->assertSame(expected: ['--nldesign-color-primary' => '#cc0000'], actual: $declared);
		$this->assertArrayNotHasKey(key: '--nldesign-color-error', array: $declared);
	}//end testDeclaredTokensAreTheSetsOwnFileOnly()

	/**
	 * A set that has no file declares nothing.
	 *
	 * @return void
	 */
	public function testDeclaredTokensOfAMissingSetAreEmpty(): void {
		$this->assertSame(expected: [], actual: $this->build()->getDeclaredTokens(tokenSetId: 'not-a-set'));
	}//end testDeclaredTokensOfAMissingSetAreEmpty()

	/**
	 * Brand-prefixed palette steps are raw material, not vocabulary.
	 *
	 * A set file may declare its own `--{slug}-*` scale to build its tokens
	 * out of. The design system does not read those, so an export that carried
	 * them would hand another instance names nothing resolves.
	 *
	 * @return void
	 */
	public function testTheSemanticLayerDropsBrandPrefixedDeclarations(): void {
		$this->writeCss(
			relativePath: 'css/tokens/utrecht.css',
			css: ":root {\n"
			. "\t--utrecht-red-500: #cc0000;\n"
			. "\t--nldesign-color-primary: var(--utrecht-red-500);\n"
			. "}\n"
		);

		$declared = $this->build()->getDeclaredTokens(tokenSetId: 'utrecht');

		$this->assertSame(
			expected: ['--nldesign-color-primary' => 'var(--utrecht-red-500)'],
			actual: $declared
		);
	}//end testTheSemanticLayerDropsBrandPrefixedDeclarations()

	/**
	 * The semantic layer comes back sorted by name.
	 *
	 * An export is a file an admin diffs against another export; ordering it
	 * by whatever order the source file happened to use would make every diff
	 * report changes nobody made.
	 *
	 * @return void
	 */
	public function testTheSemanticLayerIsSortedByName(): void {
		$this->writeCss(
			relativePath: 'css/tokens/utrecht.css',
			css: ":root {\n"
			. "\t--nldesign-color-primary: #cc0000;\n"
			. "\t--nldesign-color-error: #e9322d;\n"
			. "\t--nldesign-border-radius-element: 4px;\n"
			. "}\n"
		);

		$declared = $this->build()->getDeclaredTokens(tokenSetId: 'utrecht');

		$this->assertSame(
			expected: [
				'--nldesign-border-radius-element',
				'--nldesign-color-error',
				'--nldesign-color-primary',
			],
			actual: array_keys($declared)
		);
	}//end testTheSemanticLayerIsSortedByName()

	/* ---------------------------------------------------------------- */
	/* getResolvedColors() and the memo                                  */
	/* ---------------------------------------------------------------- */

	/**
	 * The map is read from disk once per request.
	 *
	 * The settings page asks for it several times while it builds one
	 * response. A second read that saw a different file would give one
	 * response two answers.
	 *
	 * @return void
	 */
	public function testTokenSourcesAreReadOnce(): void {
		$this->writeCss(
			relativePath: 'css/systems/nldesign/overrides.css',
			css: ":root {\n\t--color-primary: var(--nldesign-color-primary) !important;\n}\n"
		);

		$service = $this->build();
		$first = $service->getTokenSources();

		$this->writeCss(
			relativePath: 'css/systems/nldesign/overrides.css',
			css: ":root {\n\t--color-primary: var(--nldesign-color-action) !important;\n}\n"
		);

		$this->assertSame(expected: ['--color-primary' => '--nldesign-color-primary'], actual: $first);
		$this->assertSame(expected: $first, actual: $service->getTokenSources());
	}//end testTokenSourcesAreReadOnce()

	/**
	 * A token whose value names another token resolves to that token's value.
	 *
	 * This is the shape the primary element variables have again now that
	 * they read the primary: the preview shows the colour, not the reference.
	 *
	 * @return void
	 */
	public function testResolvedColoursFollowATokenThatNamesAnotherToken(): void {
		$this->writeCss(
			relativePath: 'css/systems/nldesign/defaults.css',
			css: ":root {\n"
			. "\t--nldesign-color-primary: #0b5fff;\n"
			. "\t--nldesign-color-primary-element: var(--nldesign-color-primary);\n"
			. "}\n"
		);
		$this->writeCss(
			relativePath: 'css/systems/nldesign/overrides.css',
			css: ":root {\n"
			. "\t--color-primary: var(--nldesign-color-primary) !important;\n"
			. "\t--color-primary-element: var(--nldesign-color-primary-element) !important;\n"
			. "}\n"
		);

		$colours = $this->build()->getResolvedColors(tokenSetId: 'no-such-set');

		$this->assertSame(expected: '#0b5fff', actual: $colours['--color-primary']);
		$this->assertSame(expected: '#0b5fff', actual: $colours['--color-primary-element']);
	}//end testResolvedColoursFollowATokenThatNamesAnotherToken()

	/**
	 * A variable that reads a token nothing declares keeps the token's name.
	 *
	 * An empty value would read as "no colour". The name tells the reader
	 * which token is missing.
	 *
	 * @return void
	 */
	public function testAResolvedColourWithoutAValueKeepsItsTokenName(): void {
		$this->writeCss(
			relativePath: 'css/systems/nldesign/overrides.css',
			css: ":root {\n\t--color-primary: var(--nldesign-color-primary) !important;\n}\n"
		);

		$colours = $this->build()->getResolvedColors(tokenSetId: 'no-such-set');

		$this->assertSame(expected: ['--color-primary' => '--nldesign-color-primary'], actual: $colours);
	}//end testAResolvedColourWithoutAValueKeepsItsTokenName()

	/**
	 * Without the bundle's stylesheets there is nothing to resolve.
	 *
	 * @return void
	 */
	public function testResolvedColoursAreEmptyWithoutTheStylesheets(): void {
		$this->assertSame(expected: [], actual: $this->build()->getResolvedColors(tokenSetId: 'no-such-set'));
	}//end testResolvedColoursAreEmptyWithoutTheStylesheets()
}//end class
