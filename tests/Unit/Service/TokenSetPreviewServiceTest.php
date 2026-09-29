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
		foreach (($files ?: []) as $file) {
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
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appPath);

		return new TokenSetPreviewService($appManager);
	}//end build()

	/**
	 * Write one of the stylesheets the service reads.
	 *
	 * @param string $relativePath Path under the fake app directory.
	 * @param string $css          The file body.
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
	 */
	public function testTokenSourcesRunFromVariableToToken(): void {
		$this->writeCss(
			'css/systems/nldesign/overrides.css',
			":root {\n"
			. "\t--color-primary: var(--nldesign-color-primary) !important;\n"
			. "\t--border-radius-element: var(--nldesign-border-radius-element);\n"
			. "}\n"
		);

		$sources = $this->build()->getTokenSources();

		$this->assertSame(
			[
				'--color-primary' => '--nldesign-color-primary',
				'--border-radius-element' => '--nldesign-border-radius-element',
			],
			$sources
		);
	}//end testTokenSourcesRunFromVariableToToken()

	/**
	 * Several variables may read ONE token, and all of them are reported.
	 *
	 * This is the case that makes the inverse many-to-one, which is the thing
	 * `StockTokensService::canonical()` exists to decide. A map that silently
	 * kept one of the four would hide the decision rather than settle it.
	 */
	public function testEveryVariableReadingATokenIsReported(): void {
		$this->writeCss(
			'css/systems/nldesign/overrides.css',
			":root {\n"
			. "\t--color-primary: var(--nldesign-color-primary) !important;\n"
			. "\t--color-primary-element: var(--nldesign-color-primary) !important;\n"
			. "\t--color-primary-light-text: var(--nldesign-color-primary);\n"
			. "}\n"
		);

		$sources = $this->build()->getTokenSources();

		$this->assertCount(3, $sources);
		$this->assertSame('--nldesign-color-primary', $sources['--color-primary']);
		$this->assertSame('--nldesign-color-primary', $sources['--color-primary-element']);
		$this->assertSame('--nldesign-color-primary', $sources['--color-primary-light-text']);
	}//end testEveryVariableReadingATokenIsReported()

	/**
	 * A declaration that is not a plain `var()` reference is not a mapping.
	 *
	 * A commented-out line records a variable the app deliberately does not
	 * map, and a literal or a composite is a value rather than a connection.
	 * Reporting either as a mapping would tell the stock resolver to write a
	 * token the cascade never reads.
	 */
	public function testOnlyPlainVarReferencesAreMappings(): void {
		$this->writeCss(
			'css/systems/nldesign/overrides.css',
			":root {\n"
			. "\t--color-primary: var(--nldesign-color-primary) !important;\n"
			. "\t/* --color-loading-light: var(--nldesign-color-loading-light); */\n"
			. "\t--color-box-shadow: rgba(0, 0, 0, 0.1);\n"
			. "\t--gradient: linear-gradient(var(--nldesign-color-primary), #fff);\n"
			. "}\n"
		);

		$sources = $this->build()->getTokenSources();

		$this->assertSame(['--color-primary' => '--nldesign-color-primary'], $sources);
	}//end testOnlyPlainVarReferencesAreMappings()

	/**
	 * A missing `overrides.css` is an empty map, not a fatal.
	 */
	public function testMissingOverridesGivesAnEmptyMap(): void {
		$this->assertSame([], $this->build()->getTokenSources());
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
	 */
	public function testResolvedTokensMergeTheSetOverTheDefaults(): void {
		$this->writeCss(
			'css/systems/nldesign/defaults.css',
			":root {\n"
			. "\t--nldesign-color-primary: #0082c9;\n"
			. "\t--nldesign-color-error: #e9322d;\n"
			. "}\n"
		);
		$this->writeCss(
			'css/tokens/utrecht.css',
			":root {\n\t--nldesign-color-primary: #cc0000;\n}\n"
		);

		$tokens = $this->build()->getResolvedTokens(tokenSetId: 'utrecht');

		$this->assertSame('#cc0000', $tokens['--nldesign-color-primary']);
		$this->assertSame('#e9322d', $tokens['--nldesign-color-error']);
	}//end testResolvedTokensMergeTheSetOverTheDefaults()

	/**
	 * A set with no file of its own resolves to the defaults alone.
	 */
	public function testResolvedTokensFallBackToTheDefaultsAlone(): void {
		$this->writeCss(
			'css/systems/nldesign/defaults.css',
			":root {\n\t--nldesign-color-primary: #0082c9;\n}\n"
		);

		$tokens = $this->build()->getResolvedTokens(tokenSetId: 'not-a-set');

		$this->assertSame(['--nldesign-color-primary' => '#0082c9'], $tokens);
	}//end testResolvedTokensFallBackToTheDefaultsAlone()

	/**
	 * A token declared as a `var()` reference is carried through as written.
	 *
	 * The semantic layer is the token set's own vocabulary, and a set is
	 * entitled to declare one token in terms of another. Resolving it here
	 * would export a value the set never wrote.
	 */
	public function testResolvedTokensCarryAVarReferenceThrough(): void {
		$this->writeCss(
			'css/systems/nldesign/defaults.css',
			":root {\n\t--nldesign-color-primary: #0082c9;\n}\n"
		);
		$this->writeCss(
			'css/tokens/utrecht.css',
			":root {\n"
			. "\t--nldesign-color-primary: #cc0000;\n"
			. "\t--nldesign-color-primary-hover: var(--nldesign-color-primary);\n"
			. "}\n"
		);

		$tokens = $this->build()->getResolvedTokens(tokenSetId: 'utrecht');

		$this->assertSame(
			'var(--nldesign-color-primary)',
			$tokens['--nldesign-color-primary-hover']
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
	 */
	public function testDeclaredTokensAreTheSetsOwnFileOnly(): void {
		$this->writeCss(
			'css/systems/nldesign/defaults.css',
			":root {\n"
			. "\t--nldesign-color-primary: #0082c9;\n"
			. "\t--nldesign-color-error: #e9322d;\n"
			. "}\n"
		);
		$this->writeCss(
			'css/tokens/utrecht.css',
			":root {\n\t--nldesign-color-primary: #cc0000;\n}\n"
		);

		$declared = $this->build()->getDeclaredTokens(tokenSetId: 'utrecht');

		$this->assertSame(['--nldesign-color-primary' => '#cc0000'], $declared);
		$this->assertArrayNotHasKey('--nldesign-color-error', $declared);
	}//end testDeclaredTokensAreTheSetsOwnFileOnly()

	/**
	 * A set that has no file declares nothing.
	 */
	public function testDeclaredTokensOfAMissingSetAreEmpty(): void {
		$this->assertSame([], $this->build()->getDeclaredTokens(tokenSetId: 'not-a-set'));
	}//end testDeclaredTokensOfAMissingSetAreEmpty()

	/**
	 * Brand-prefixed palette steps are raw material, not vocabulary.
	 *
	 * A set file may declare its own `--{slug}-*` scale to build its tokens
	 * out of. The design system does not read those, so an export that carried
	 * them would hand another instance names nothing resolves.
	 */
	public function testTheSemanticLayerDropsBrandPrefixedDeclarations(): void {
		$this->writeCss(
			'css/tokens/utrecht.css',
			":root {\n"
			. "\t--utrecht-red-500: #cc0000;\n"
			. "\t--nldesign-color-primary: var(--utrecht-red-500);\n"
			. "}\n"
		);

		$declared = $this->build()->getDeclaredTokens(tokenSetId: 'utrecht');

		$this->assertSame(
			['--nldesign-color-primary' => 'var(--utrecht-red-500)'],
			$declared
		);
	}//end testTheSemanticLayerDropsBrandPrefixedDeclarations()

	/**
	 * The semantic layer comes back sorted by name.
	 *
	 * An export is a file an admin diffs against another export; ordering it
	 * by whatever order the source file happened to use would make every diff
	 * report changes nobody made.
	 */
	public function testTheSemanticLayerIsSortedByName(): void {
		$this->writeCss(
			'css/tokens/utrecht.css',
			":root {\n"
			. "\t--nldesign-color-primary: #cc0000;\n"
			. "\t--nldesign-color-error: #e9322d;\n"
			. "\t--nldesign-border-radius-element: 4px;\n"
			. "}\n"
		);

		$declared = $this->build()->getDeclaredTokens(tokenSetId: 'utrecht');

		$this->assertSame(
			[
				'--nldesign-border-radius-element',
				'--nldesign-color-error',
				'--nldesign-color-primary',
			],
			array_keys($declared)
		);
	}//end testTheSemanticLayerIsSortedByName()
}//end class
