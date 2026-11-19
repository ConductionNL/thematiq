<?php

/**
 * Unit tests for DesignTokensMapper.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\DesignTokensMapper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the W3C Design Tokens (DTCG) Format Module v2025.10 mapper.
 *
 * Covers the `custom-token-sets` spec's DTCG import contract: `$type`
 * resolution with group inheritance, typed object `$value` handling (color,
 * dimension, fontFamily, fontWeight, composite typography), transitive alias
 * resolution with cycle/dangling/depth guards, `$extensions`
 * passthrough-ignore, `$deprecated` warnings, package version extraction, and
 * the aggregated structured-diagnostics / leaf-accounting invariant.
 *
 * The 13-fixture synthetic corpus plus 2 real municipal package excerpts live
 * under `tests/Unit/fixtures/dtcg/` (see the README there for provenance).
 * Malformed JSON (fixture 13) is a controller-level concern (json_decode
 * failure before the mapper is ever invoked) and is not exercised here.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
class DesignTokensMapperTest extends TestCase {

	/**
	 * The mapper under test.
	 *
	 * @var DesignTokensMapper
	 */
	private DesignTokensMapper $mapper;

	/**
	 * Set up the mapper before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->mapper = new DesignTokensMapper();
	}//end setUp()

	/**
	 * Load and decode a fixture from tests/Unit/fixtures/dtcg/.
	 *
	 * @param string $name The fixture filename.
	 *
	 * @return array<string, mixed> The decoded document.
	 */
	private function loadFixture(string $name): array {
		$path = (__DIR__ . '/../fixtures/dtcg/' . $name);
		$decoded = json_decode((string)file_get_contents($path), true);
		$this->assertIsArray(actual: $decoded, message: 'Fixture ' . $name . ' must decode to an array.');

		return $decoded;
	}//end loadFixture()

	/**
	 * Count the accounting units a document is expected to fan out into: one
	 * per ordinary leaf, or one per sub-property for a token whose own
	 * `$type` is `typography` (mirroring the mapper's composite fan-out).
	 * Mirrors {@see DesignTokensMapper} sufficiently for every fixture in the
	 * corpus (none of which relies on a *group-inherited* typography type).
	 *
	 * @param mixed $node The current node (array or scalar).
	 *
	 * @return int The number of accounting units under this node.
	 */
	private function countProcessingUnits($node): int {
		if (is_array($node) === false) {
			return 0;
		}

		if (array_key_exists('$value', $node) === true) {
			if (($node['$type'] ?? null) === 'typography' && is_array($node['$value']) === true) {
				return count($node['$value']);
			}

			return 1;
		}

		$count = 0;
		foreach ($node as $key => $child) {
			if (is_string($key) === true && str_starts_with($key, '$') === true) {
				continue;
			}

			$count += $this->countProcessingUnits(node: $child);
		}

		return $count;
	}//end countProcessingUnits()

	/**
	 * Assert the leaf-accounting invariant: imported + |skipped| + |errors|
	 * equals the number of processing units in the source document.
	 *
	 * @param array<string, mixed> $document The source document.
	 * @param array<string, mixed> $result The mapper's result for that document.
	 * @param string $message Assertion failure context.
	 *
	 * @return void
	 */
	private function assertAccountingInvariant(array $document, array $result, string $message): void {
		$expected = $this->countProcessingUnits(node: $document);
		$actual = ($result['imported'] + count($result['skipped']) + count($result['errors']));

		$this->assertSame(expected: $expected, actual: $actual, message: $message);
	}//end assertAccountingInvariant()

	// -- Regression: pre-hardening behaviour is preserved -------------------

	/**
	 * DTCG color tokens map onto the --nldesign-* vocabulary (canonical scenario).
	 *
	 * @return void
	 */
	public function testColorTokensMapToNldesignVocabulary(): void {
		$document = [
			'color' => [
				'primary' => ['$type' => 'color', '$value' => '#154273'],
				'on-primary' => ['$type' => 'color', '$value' => '#ffffff'],
			],
		];

		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: '#154273', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertSame(expected: '#ffffff', actual: $result['declarations']['--nldesign-color-primary-text']);
		$this->assertSame(expected: 2, actual: $result['imported']);
		$this->assertSame(expected: [], actual: $result['skipped']);
		$this->assertSame(expected: [], actual: $result['errors']);
		$this->assertAccountingInvariant(document: $document, result: $result, message: 'canonical scenario');
	}//end testColorTokensMapToNldesignVocabulary()

	/**
	 * The fixture corpus's regression document imports identically to the
	 * pre-hardening mapper (imported: 2, skipped: 0, errors: 0).
	 *
	 * @return void
	 */
	public function testLegacyScalarRegressionFixtureIsUnchanged(): void {
		$document = $this->loadFixture(name: '01-legacy-scalar-regression.tokens.json');
		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: 2, actual: $result['imported']);
		$this->assertSame(expected: [], actual: $result['skipped']);
		$this->assertSame(expected: [], actual: $result['errors']);
	}//end testLegacyScalarRegressionFixtureIsUnchanged()

	/**
	 * Unmapped tokens degrade to a structured skipped entry, never an error.
	 *
	 * @return void
	 */
	public function testUnmappedTokensAreSkippedWithReason(): void {
		$document = [
			'color' => ['primary' => ['$type' => 'color', '$value' => '#154273']],
			'shadow' => ['elevation-1' => ['$type' => 'shadow', '$value' => '0 1px 2px']],
		];

		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: 1, actual: $result['imported']);
		$this->assertCount(expectedCount: 1, haystack: $result['skipped']);
		$this->assertSame(expected: 'shadow.elevation-1', actual: $result['skipped'][0]['path']);
		$this->assertSame(expected: 'unmapped-path', actual: $result['skipped'][0]['reason']);
		$this->assertAccountingInvariant(document: $document, result: $result, message: 'unmapped tokens degrade to skipped');
	}//end testUnmappedTokensAreSkippedWithReason()

	/**
	 * The longest matching suffix wins (primary-text over primary).
	 *
	 * @return void
	 */
	public function testLongestSuffixWins(): void {
		$document = [
			'theme' => [
				'color' => [
					'primary' => ['$type' => 'color', '$value' => '#111111'],
					'primary-text' => ['$type' => 'color', '$value' => '#eeeeee'],
				],
			],
		];

		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: '#111111', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertSame(expected: '#eeeeee', actual: $result['declarations']['--nldesign-color-primary-text']);
	}//end testLongestSuffixWins()

	/**
	 * Nested groups and $-prefixed metadata keys are handled.
	 *
	 * @return void
	 */
	public function testNestedGroupsAndMetadataKeys(): void {
		$document = [
			'$description' => 'My theme',
			'brand' => [
				'$type' => 'color',
				'primary' => ['$value' => '#007bc7'],
			],
			'typography' => [
				'font-family' => ['$type' => 'fontFamily', '$value' => 'Inter, sans-serif'],
			],
		];

		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: '#007bc7', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertSame(expected: 'Inter, sans-serif', actual: $result['declarations']['--nldesign-font-family']);
	}//end testNestedGroupsAndMetadataKeys()

	/**
	 * The published mapping table is exposed for transparency.
	 *
	 * @return void
	 */
	public function testMappingTableIsExposed(): void {
		$table = $this->mapper->getMappingTable();
		$this->assertArrayHasKey(key: 'color.primary', array: $table);
		$this->assertSame(expected: '--nldesign-color-primary', actual: $table['color.primary']);
	}//end testMappingTableIsExposed()

	/**
	 * The action colour has its own paths, and the longer path wins over `color.action`.
	 *
	 * @return void
	 */
	public function testTheActionColourMapsByPath(): void {
		$result = $this->mapper->map(document: [
			'color' => [
				'primary' => ['$type' => 'color', '$value' => '#1a67a3'],
				'action' => ['$type' => 'color', '$value' => '#1f883d'],
				'action-hover' => ['$type' => 'color', '$value' => '#197032'],
				'action-text' => ['$type' => 'color', '$value' => '#ffffff'],
			],
		]);

		$this->assertSame(expected: '#1a67a3', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertSame(expected: '#1f883d', actual: $result['declarations']['--nldesign-color-action']);
		$this->assertSame(expected: '#197032', actual: $result['declarations']['--nldesign-color-action-hover']);
		$this->assertSame(expected: '#ffffff', actual: $result['declarations']['--nldesign-color-action-text']);
		$this->assertSame(expected: '--nldesign-color-action-text', actual: $this->mapper->getMappingTable()['color.on-action']);
	}//end testTheActionColourMapsByPath()

	/**
	 * A later token colliding on an already-written target is skipped with
	 * `duplicate-target`, never overwriting the first-seen declaration.
	 *
	 * @return void
	 */
	public function testDuplicateTargetCollisionIsSkipped(): void {
		$document = [
			'color' => [
				'primary' => ['$type' => 'color', '$value' => '#111111'],
				'alternate' => ['$type' => 'color', '$value' => '#222222'],
			],
			'brand' => [
				'primary' => ['$type' => 'color', '$value' => '#333333'],
			],
		];

		$result = $this->mapper->map(document: $document);

		// color.primary and brand.primary both target --nldesign-color-primary.
		$this->assertSame(expected: '#111111', actual: $result['declarations']['--nldesign-color-primary']);
		$duplicate = array_values(array_filter($result['skipped'], fn ($s) => $s['reason'] === 'duplicate-target'));
		$this->assertCount(expectedCount: 1, haystack: $duplicate);
		$this->assertSame(expected: 'brand.primary', actual: $duplicate[0]['path']);
	}//end testDuplicateTargetCollisionIsSkipped()

	// -- $type resolution with group inheritance -----------------------------

	/**
	 * Group-level $type is inherited by descendant tokens.
	 *
	 * @return void
	 */
	public function testGroupTypeIsInheritedByDescendantTokens(): void {
		$document = $this->loadFixture(name: '02-group-type-inheritance.tokens.json');
		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: '#154273', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertSame(expected: 1, actual: $result['imported']);
		$this->assertAccountingInvariant(document: $document, result: $result, message: 'group type inheritance');
	}//end testGroupTypeIsInheritedByDescendantTokens()

	/**
	 * A token whose resolved type is absent is skipped with `missing-type`,
	 * never guessed from the value shape (a plausible hex string does NOT
	 * become `color` just because it looks like one).
	 *
	 * @return void
	 */
	public function testMissingTypeIsNeverGuessedFromValueShape(): void {
		$document = [
			'color' => ['primary' => ['$value' => '#154273']],
		];

		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: 0, actual: $result['imported']);
		$this->assertSame(expected: [], actual: $result['declarations']);
		$this->assertCount(expectedCount: 1, haystack: $result['errors']);
		$this->assertSame(expected: 'missing-type', actual: $result['errors'][0]['reason']);
		$this->assertSame(expected: 'color.primary', actual: $result['errors'][0]['path']);
	}//end testMissingTypeIsNeverGuessedFromValueShape()

	// -- Typed object $value handling -----------------------------------------

	/**
	 * v2025.10 object-form color and dimension values serialize to CSS; an
	 * unsupported color space is skipped with an actionable reason.
	 *
	 * @return void
	 */
	public function testObjectFormColorAndDimensionSerializeToCss(): void {
		$document = $this->loadFixture(name: '03-object-color-and-dimension.tokens.json');
		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: '#154273', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertSame(expected: '8px', actual: $result['declarations']['--nldesign-border-radius']);

		$this->assertCount(expectedCount: 1, haystack: $result['errors']);
		$this->assertSame(expected: 'color.accent', actual: $result['errors'][0]['path']);
		$this->assertSame(expected: 'unsupported-color-space', actual: $result['errors'][0]['reason']);
		$this->assertSame(expected: 'cmyk', actual: $result['errors'][0]['detail']);

		$this->assertAccountingInvariant(document: $document, result: $result, message: 'object color/dimension');
	}//end testObjectFormColorAndDimensionSerializeToCss()

	/**
	 * fontFamily string and array forms both serialize correctly; array
	 * entries containing whitespace are quoted, bare keywords are not.
	 *
	 * @return void
	 */
	public function testFontFamilyArraySerializesToQuotedFontStack(): void {
		$document = [
			'typography' => [
				'font-family' => [
					'$type' => 'fontFamily',
					'$value' => ['Fira Sans', 'sans-serif'],
				],
			],
		];

		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: "'Fira Sans', sans-serif", actual: $result['declarations']['--nldesign-font-family']);
	}//end testFontFamilyArraySerializesToQuotedFontStack()

	/**
	 * fontWeight accepts a number and a v2025.10 weight keyword, normalizing
	 * the keyword to its numeric CSS value; an unrecognized value is an
	 * `unsupported-value-shape` error (proving the shape, not just presence,
	 * is validated — there is no generic `--nldesign-*` fontWeight target
	 * today, so a *valid* fontWeight is observed landing in `skipped` with
	 * `unmapped-path` rather than `errors`).
	 *
	 * @return void
	 */
	public function testFontWeightNumberAndKeywordAreNormalized(): void {
		$document = [
			'weight' => [
				'numeric' => ['$type' => 'fontWeight', '$value' => 700],
				'keyword' => ['$type' => 'fontWeight', '$value' => 'Bold'],
				'invalid' => ['$type' => 'fontWeight', '$value' => 'extra-chonky'],
			],
		];

		$result = $this->mapper->map(document: $document);

		$skippedPaths = array_column($result['skipped'], 'reason', 'path');
		$this->assertSame(expected: 'unmapped-path', actual: ($skippedPaths['weight.numeric'] ?? null));
		$this->assertSame(expected: 'unmapped-path', actual: ($skippedPaths['weight.keyword'] ?? null));

		$errorPaths = array_column($result['errors'], 'reason', 'path');
		$this->assertSame(expected: 'unsupported-value-shape', actual: ($errorPaths['weight.invalid'] ?? null));
	}//end testFontWeightNumberAndKeywordAreNormalized()

	/**
	 * Composite typography token maps sub-values individually; sub-properties
	 * without an --nldesign-* target are counted skipped with their sub-path.
	 *
	 * @return void
	 */
	public function testCompositeTypographyMapsSubValuesIndividually(): void {
		$document = $this->loadFixture(name: '04-composite-typography.tokens.json');
		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: "'Fira Sans', sans-serif", actual: $result['declarations']['--nldesign-font-family']);

		$skippedPaths = array_column($result['skipped'], 'path');
		$this->assertContains(needle: 'typography.heading.fontSize', haystack: $skippedPaths);
		$this->assertContains(needle: 'typography.heading.fontWeight', haystack: $skippedPaths);
		$this->assertContains(needle: 'typography.heading.lineHeight', haystack: $skippedPaths);

		$this->assertAccountingInvariant(document: $document, result: $result, message: 'composite typography fan-out');
	}//end testCompositeTypographyMapsSubValuesIndividually()

	// -- $extensions passthrough-ignore ---------------------------------------

	/**
	 * $extensions subtrees are never descended into (not an error, never
	 * mapped) even when they contain a `$value`-shaped key; a sibling real
	 * token still imports normally.
	 *
	 * @return void
	 */
	public function testExtensionsAreIgnoredWithoutError(): void {
		$document = $this->loadFixture(name: '10-extensions-heavy.tokens.json');
		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: 1, actual: $result['imported']);
		$this->assertSame(expected: '#154273', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertSame(expected: [], actual: $result['errors']);
		$this->assertAccountingInvariant(document: $document, result: $result, message: 'extensions passthrough-ignore');
	}//end testExtensionsAreIgnoredWithoutError()

	// -- $deprecated -----------------------------------------------------------

	/**
	 * A deprecated token still imports AND surfaces a warning naming the
	 * token path and the deprecation message.
	 *
	 * @return void
	 */
	public function testDeprecatedTokenImportsWithWarning(): void {
		$document = $this->loadFixture(name: '09-deprecated.tokens.json');
		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: '#154273', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertCount(expectedCount: 1, haystack: $result['warnings']);
		$this->assertSame(expected: 'color.primary', actual: $result['warnings'][0]['path']);
		$this->assertSame(expected: 'Use color.brand.primary instead', actual: $result['warnings'][0]['message']);
	}//end testDeprecatedTokenImportsWithWarning()

	/**
	 * The warning names the CSS variable the token became, so it can be recorded as a
	 * deprecation; the Tokens Studio object form carries its message and severity.
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-imported-deprecation-notices-can-be-recorded
	 *
	 * @return void
	 */
	public function testDeprecatedWarningNamesTheTargetAndReadsTokensStudio(): void {
		$document = [
			'color' => [
				'primary' => ['$type' => 'color', '$value' => '#154273', '$deprecated' => 'Use color.brand.primary instead'],
				'background' => ['$type' => 'color', '$value' => '#ffffff', '$deprecated' => ['severity' => 'error', 'message' => 'Gone in 2027']],
			],
		];

		$warnings = array_column($this->mapper->map(document: $document)['warnings'], null, 'path');

		$this->assertSame(expected: '--nldesign-color-primary', actual: $warnings['color.primary']['token']);
		$this->assertSame(expected: 'Gone in 2027', actual: $warnings['color.background']['message']);
		$this->assertSame(expected: 'critical', actual: $warnings['color.background']['severity']);
	}//end testDeprecatedWarningNamesTheTargetAndReadsTokensStudio()

	/**
	 * A boolean `$deprecated: true` still warns, with a null message.
	 *
	 * @return void
	 */
	public function testDeprecatedBooleanTrueWarnsWithNullMessage(): void {
		$document = [
			'color' => [
				'primary' => ['$type' => 'color', '$value' => '#154273', '$deprecated' => true],
			],
		];

		$result = $this->mapper->map(document: $document);

		$this->assertCount(expectedCount: 1, haystack: $result['warnings']);
		$this->assertNull(actual: $result['warnings'][0]['message']);
	}//end testDeprecatedBooleanTrueWarnsWithNullMessage()

	/**
	 * A deprecated token that never actually imports (e.g. unmapped) does not
	 * surface a warning — deprecation warnings are only for imported tokens.
	 *
	 * @return void
	 */
	public function testDeprecatedButUnmappedTokenDoesNotWarn(): void {
		$document = [
			'shadow' => [
				'elevation-1' => ['$type' => 'shadow', '$value' => '0 1px 2px', '$deprecated' => true],
			],
		];

		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: [], actual: $result['warnings']);
	}//end testDeprecatedButUnmappedTokenDoesNotWarn()

	// -- Alias resolution ------------------------------------------------------

	/**
	 * Transitive alias chain resolves to the concrete value (canonical
	 * 2-hop scenario from the spec).
	 *
	 * @return void
	 */
	public function testTransitiveAliasChainResolvesToConcreteValue(): void {
		$document = [
			'color' => [
				'primary' => ['$value' => '{brand.blue}'],
			],
			'brand' => [
				'blue' => ['$value' => '{palette.blue-500}'],
			],
			'palette' => [
				'blue-500' => ['$type' => 'color', '$value' => '#154273'],
			],
		];

		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: '#154273', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertGreaterThanOrEqual(expected: 1, actual: $result['imported']);
	}//end testTransitiveAliasChainResolvesToConcreteValue()

	/**
	 * A longer (3-hop) alias chain from the fixture corpus also resolves.
	 *
	 * @return void
	 */
	public function testThreeHopAliasChainFixtureResolves(): void {
		$document = $this->loadFixture(name: '05-alias-chain-3-hop.tokens.json');
		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: '#154273', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertAccountingInvariant(document: $document, result: $result, message: '3-hop alias chain');
	}//end testThreeHopAliasChainFixtureResolves()

	/**
	 * An alias cycle produces an actionable per-token error naming the full
	 * cycle path, without aborting the import of an unrelated valid token.
	 *
	 * @return void
	 */
	public function testAliasCycleProducesActionableError(): void {
		$document = $this->loadFixture(name: '06-alias-cycle.tokens.json');
		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: '#154273', actual: $result['declarations']['--nldesign-color-primary']);

		$errorsByPath = [];
		foreach ($result['errors'] as $error) {
			$errorsByPath[$error['path']] = $error;
		}

		$this->assertSame(expected: 'alias-cycle', actual: $errorsByPath['a.x']['reason']);
		$this->assertSame(expected: 'a.x -> b.y -> a.x', actual: $errorsByPath['a.x']['detail']);
		$this->assertSame(expected: 'alias-cycle', actual: $errorsByPath['b.y']['reason']);

		$this->assertAccountingInvariant(document: $document, result: $result, message: 'alias cycle');
	}//end testAliasCycleProducesActionableError()

	/**
	 * A dangling alias is reported with its missing target path; no
	 * declaration is emitted for it.
	 *
	 * @return void
	 */
	public function testDanglingAliasReportsMissingTarget(): void {
		$document = $this->loadFixture(name: '07-alias-dangling.tokens.json');
		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: [], actual: $result['declarations']);
		$this->assertCount(expectedCount: 1, haystack: $result['errors']);
		$this->assertSame(expected: 'alias-target-missing', actual: $result['errors'][0]['reason']);
		$this->assertSame(expected: 'does.not.exist', actual: $result['errors'][0]['detail']);
	}//end testDanglingAliasReportsMissingTarget()

	/**
	 * An alias chain longer than the 10-hop bound fails with
	 * `alias-depth-exceeded` rather than resolving or hanging.
	 *
	 * @return void
	 */
	public function testAliasDepthExceededBeyondTenHops(): void {
		$document = $this->loadFixture(name: '08-alias-depth-bomb.tokens.json');
		$result = $this->mapper->map(document: $document);

		$errorsByPath = [];
		foreach ($result['errors'] as $error) {
			$errorsByPath[$error['path']] = $error;
		}

		$this->assertArrayHasKey(key: 'color.primary', array: $errorsByPath);
		$this->assertSame(expected: 'alias-depth-exceeded', actual: $errorsByPath['color.primary']['reason']);

		$this->assertAccountingInvariant(document: $document, result: $result, message: 'alias depth bomb');
	}//end testAliasDepthExceededBeyondTenHops()

	// -- Package version metadata ----------------------------------------------

	/**
	 * A top-level $version member is extracted verbatim.
	 *
	 * @return void
	 */
	public function testPackageVersionExtractedFromTopLevelVersion(): void {
		$document = $this->loadFixture(name: '11-version-carrying.tokens.json');
		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: '2.3.1', actual: $result['packageVersion']);
	}//end testPackageVersionExtractedFromTopLevelVersion()

	/**
	 * A recognized $extensions version convention is the second precedence tier.
	 *
	 * @return void
	 */
	public function testPackageVersionExtractedFromExtensionsConvention(): void {
		$document = [
			'$extensions' => ['nl.nldesign.version' => '1.9.0'],
			'color' => ['primary' => ['$type' => 'color', '$value' => '#154273']],
		];

		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: '1.9.0', actual: $result['packageVersion']);
	}//end testPackageVersionExtractedFromExtensionsConvention()

	/**
	 * The `org.nldesign.version` spelling of the extension is read too.
	 *
	 * @return void
	 */
	public function testPackageVersionExtractedFromTheOrgExtensionSpelling(): void {
		$document = [
			'$extensions' => ['org.nldesign.version' => '1.8.2'],
			'color' => ['primary' => ['$type' => 'color', '$value' => '#154273']],
		];

		$this->assertSame(expected: '1.8.2', actual: $this->mapper->map(document: $document)['packageVersion']);
	}//end testPackageVersionExtractedFromTheOrgExtensionSpelling()

	/**
	 * A plain top-level `version` string is the third precedence tier.
	 *
	 * @return void
	 */
	public function testPackageVersionExtractedFromPlainVersionField(): void {
		$document = [
			'version' => '0.4.2',
			'color' => ['primary' => ['$type' => 'color', '$value' => '#154273']],
		];

		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: '0.4.2', actual: $result['packageVersion']);
	}//end testPackageVersionExtractedFromPlainVersionField()

	/**
	 * $version takes precedence over both $extensions and plain `version`.
	 *
	 * @return void
	 */
	public function testTopLevelVersionTakesPrecedenceOverOtherConventions(): void {
		$document = [
			'$version' => '3.0.0',
			'$extensions' => ['nl.nldesign.version' => '1.0.0'],
			'version' => '0.0.1',
			'color' => ['primary' => ['$type' => 'color', '$value' => '#154273']],
		];

		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: '3.0.0', actual: $result['packageVersion']);
	}//end testTopLevelVersionTakesPrecedenceOverOtherConventions()

	/**
	 * A document declaring no version anywhere reports a null packageVersion
	 * — never fabricated.
	 *
	 * @return void
	 */
	public function testVersionLessDocumentReturnsNullPackageVersion(): void {
		$document = $this->loadFixture(name: '01-legacy-scalar-regression.tokens.json');
		$result = $this->mapper->map(document: $document);

		$this->assertNull(actual: $result['packageVersion']);
	}//end testVersionLessDocumentReturnsNullPackageVersion()

	// -- Zero-yield --------------------------------------------------------------

	/**
	 * A syntactically valid, fully-typed document that maps nothing imports
	 * zero declarations (the controller layer turns this into a 422).
	 *
	 * @return void
	 */
	public function testZeroYieldDocumentImportsNothing(): void {
		$document = $this->loadFixture(name: '12-zero-yield.tokens.json');
		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: 0, actual: $result['imported']);
		$this->assertSame(expected: [], actual: $result['declarations']);
		$this->assertAccountingInvariant(document: $document, result: $result, message: 'zero-yield document');
	}//end testZeroYieldDocumentImportsNothing()

	// -- Real municipal package excerpts -----------------------------------------

	/**
	 * A trimmed real @utrecht/design-tokens excerpt: group-type inheritance,
	 * a real semantic alias, and mostly-unmapped real paths all account for
	 * every leaf with zero unexplained drops.
	 *
	 * @return void
	 */
	public function testRealUtrechtExcerptAccountsForEveryLeaf(): void {
		$document = $this->loadFixture(name: '14-utrecht-real-excerpt.tokens.json');
		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: 'hsl(211 60% 35%)', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertGreaterThanOrEqual(expected: 1, actual: $result['imported']);
		$this->assertSame(expected: [], actual: $result['errors']);
		$this->assertAccountingInvariant(document: $document, result: $result, message: 'real utrecht excerpt');
	}//end testRealUtrechtExcerptAccountsForEveryLeaf()

	/**
	 * A trimmed real @amsterdam/design-system-tokens excerpt: a real
	 * array-form fontFamily token imports, real $extensions-typed tokens
	 * report missing-type, and every leaf is accounted for.
	 *
	 * @return void
	 */
	public function testRealAmsterdamExcerptAccountsForEveryLeaf(): void {
		$document = $this->loadFixture(name: '15-amsterdam-real-excerpt.tokens.json');
		$result = $this->mapper->map(document: $document);

		$this->assertSame(
			expected: "'Amsterdam Sans', Arial, sans-serif",
			actual: $result['declarations']['--nldesign-font-family']
		);

		$errorPaths = array_column($result['errors'], 'path');
		$this->assertContains(needle: 'ams.typography.hyphenate-limit-chars', haystack: $errorPaths);
		$this->assertContains(needle: 'ams.typography.italic.font-style', haystack: $errorPaths);
		foreach ($result['errors'] as $error) {
			$this->assertSame(expected: 'missing-type', actual: $error['reason']);
		}

		$this->assertAccountingInvariant(document: $document, result: $result, message: 'real amsterdam excerpt');
	}//end testRealAmsterdamExcerptAccountsForEveryLeaf()

	// -- Aggregated diagnostics / accounting invariant across the corpus ---------

	/**
	 * The leaf-accounting invariant (imported + |skipped| + |errors| = leaves
	 * processed) holds for every fixture in the corpus (excluding the
	 * malformed-JSON fixture, which never reaches the mapper).
	 *
	 * @param string $fixture The fixture filename.
	 *
	 * @dataProvider corpusFixtureProvider
	 *
	 * @return void
	 */
	public function testAccountingInvariantHoldsAcrossFullCorpus(string $fixture): void {
		$document = $this->loadFixture(name: $fixture);
		$result = $this->mapper->map(document: $document);

		$this->assertAccountingInvariant(document: $document, result: $result, message: $fixture);
	}//end testAccountingInvariantHoldsAcrossFullCorpus()

	// -- Colour components: srgb-linear and alpha (thematiq#695) -------------

	/**
	 * Map one object-form colour `$value` onto --nldesign-color-primary.
	 *
	 * @param array<string, mixed> $value The DTCG colour object.
	 *
	 * @return array<string, mixed> The mapper result.
	 */
	private function mapPrimaryColour(array $value): array {
		return $this->mapper->map(document: ['color' => ['primary' => ['$type' => 'color', '$value' => $value]]]);
	}//end mapPrimaryColour()

	/**
	 * Linear-light components go through the sRGB transfer function before
	 * scaling: a linear 0.5 grey is about #bcbcbc, not #808080.
	 *
	 * @return void
	 */
	public function testSrgbLinearAppliesTransferFunction(): void {
		$result = $this->mapPrimaryColour(value: ['colorSpace' => 'srgb-linear', 'components' => [0.5, 0.5, 0.5]]);

		$this->assertSame(expected: '#bcbcbc', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertSame(expected: [], actual: $result['errors']);
	}//end testSrgbLinearAppliesTransferFunction()

	/**
	 * The transfer function's linear segment near black is honoured too.
	 *
	 * @return void
	 */
	public function testSrgbLinearNearBlackUsesTheLinearSegment(): void {
		$result = $this->mapPrimaryColour(value: ['colorSpace' => 'srgb-linear', 'components' => [0.002, 0, 1]]);

		// 0.002 * 12.92 = 0.02584 -> 7 -> 07.
		$this->assertSame(expected: '#0700ff', actual: $result['declarations']['--nldesign-color-primary']);
	}//end testSrgbLinearNearBlackUsesTheLinearSegment()

	/**
	 * A colour's alpha is kept as the fourth pair of an 8-digit hex.
	 *
	 * @return void
	 */
	public function testAlphaKeptAsEightDigitHex(): void {
		$result = $this->mapPrimaryColour(value: ['colorSpace' => 'srgb', 'components' => [0, 0, 0], 'alpha' => 0.5]);

		$this->assertSame(expected: '#00000080', actual: $result['declarations']['--nldesign-color-primary']);
	}//end testAlphaKeptAsEightDigitHex()

	/**
	 * Alpha and srgb-linear together, the issue's own example.
	 *
	 * @return void
	 */
	public function testSrgbLinearWithAlpha(): void {
		$result = $this->mapPrimaryColour(value: ['colorSpace' => 'srgb-linear', 'components' => [0.5, 0.5, 0.5], 'alpha' => 0.5]);

		$this->assertSame(expected: '#bcbcbc80', actual: $result['declarations']['--nldesign-color-primary']);
	}//end testSrgbLinearWithAlpha()

	/**
	 * An alpha on the hex fallback is kept too, since the hex member carries no alpha.
	 *
	 * @return void
	 */
	public function testAlphaIsAddedToTheHexFallback(): void {
		$result = $this->mapPrimaryColour(value: ['colorSpace' => 'srgb', 'components' => [0, 0, 0], 'hex' => '#000000', 'alpha' => 0.25]);

		$this->assertSame(expected: '#00000040', actual: $result['declarations']['--nldesign-color-primary']);
	}//end testAlphaIsAddedToTheHexFallback()

	/**
	 * A fully opaque colour stays a 6-digit hex, so existing sets import unchanged.
	 *
	 * @return void
	 */
	public function testOpaqueColourStaysSixDigitHex(): void {
		$result = $this->mapPrimaryColour(value: ['colorSpace' => 'srgb', 'components' => [0.0823, 0.2588, 0.451], 'alpha' => 1]);

		$this->assertSame(expected: '#154273', actual: $result['declarations']['--nldesign-color-primary']);
	}//end testOpaqueColourStaysSixDigitHex()

	/**
	 * Every JSON fixture in the corpus (malformed JSON is a controller-level
	 * concern and is excluded here).
	 *
	 * @return array<int, array{0: string}>
	 */
	public static function corpusFixtureProvider(): array {
		return [
			['01-legacy-scalar-regression.tokens.json'],
			['02-group-type-inheritance.tokens.json'],
			['03-object-color-and-dimension.tokens.json'],
			['04-composite-typography.tokens.json'],
			['05-alias-chain-3-hop.tokens.json'],
			['06-alias-cycle.tokens.json'],
			['07-alias-dangling.tokens.json'],
			['08-alias-depth-bomb.tokens.json'],
			['09-deprecated.tokens.json'],
			['10-extensions-heavy.tokens.json'],
			['11-version-carrying.tokens.json'],
			['12-zero-yield.tokens.json'],
			['14-utrecht-real-excerpt.tokens.json'],
			['15-amsterdam-real-excerpt.tokens.json'],
		];
	}//end corpusFixtureProvider()

	/**
	 * A reference-only leaf resolves an alias but is never emitted, nor reported as skipped.
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-each-brand-is-converted-by-the-existing-pipeline
	 *
	 * @return void
	 */
	public function testReferenceOnlyLeafResolvesAliasButIsNotEmitted(): void {
		$document = [
			'color' => [
				'brand-blue' => ['$type' => 'color', '$value' => '#154273'],
				'primary' => ['$type' => 'color', '$value' => '{color.brand-blue}'],
			],
			'brand' => ['primary' => ['$type' => 'color', '$value' => '#000000']],
		];

		$result = $this->mapper->map(document: $document, referenceOnlyPaths: ['color.brand-blue', 'brand.primary']);

		$this->assertSame(expected: ['--nldesign-color-primary' => '#154273'], actual: $result['declarations']);
		$this->assertSame(expected: [], actual: $result['skipped']);
	}//end testReferenceOnlyLeafResolvesAliasButIsNotEmitted()

	// -- authoring-dtcg-export: colour spaces and the thematiq extension ---------

	/**
	 * Map one colour object at `color.primary`.
	 *
	 * @param array<string, mixed> $value The colour object.
	 * @param string $path The leaf name under `color`.
	 *
	 * @return array<string, mixed> The mapper result.
	 */
	private function mapColour(array $value, string $path = 'primary'): array {
		return $this->mapper->map(document: ['color' => [$path => ['$type' => 'color', '$value' => $value]]]);
	}//end mapColour()

	/**
	 * Scenario: an oklch brand colour is converted to sRGB and imported.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
	 *
	 * @return void
	 */
	public function testOklchConvertsToSrgb(): void {
		$result = $this->mapColour(value: ['colorSpace' => 'oklch', 'components' => [0.5, 0.1, 250]]);

		$this->assertSame(expected: '#32669a', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertSame(expected: 1, actual: $result['imported']);
		$this->assertSame(expected: [], actual: $result['adapted']);
	}//end testOklchConvertsToSrgb()

	/**
	 * Out of gamut with a hex: the hex, reported adapted.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
	 *
	 * @return void
	 */
	public function testOutOfGamutUsesHexFallback(): void {
		$result = $this->mapColour(value: ['colorSpace' => 'display-p3', 'components' => [1, 0, 0], 'hex' => '#fe0000']);

		$this->assertSame(expected: '#fe0000', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertSame(expected: 'out-of-gamut-hex-fallback', actual: $result['adapted'][0]['reason']);
	}//end testOutOfGamutUsesHexFallback()

	/**
	 * Scenario: a display-p3 colour outside sRGB is clipped and reported with the original.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
	 *
	 * @return void
	 */
	public function testOutOfGamutWithoutHexIsClipped(): void {
		$result = $this->mapColour(value: ['colorSpace' => 'display-p3', 'components' => [1, 0, 0]]);

		$this->assertSame(expected: '#ff0000', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertSame(expected: 'out-of-gamut-clipped', actual: $result['adapted'][0]['reason']);
		$this->assertSame(expected: ['colorSpace' => 'display-p3', 'components' => [1, 0, 0]], actual: $result['adapted'][0]['original']);
		$this->assertSame(expected: 'color.primary', actual: $result['adapted'][0]['path']);
	}//end testOutOfGamutWithoutHexIsClipped()

	/**
	 * Scenario: the thematiq extension names the target, where the suffix table has none.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
	 *
	 * @return void
	 */
	public function testExtensionNamesTheTarget(): void {
		$document = ['nldesign' => ['color' => ['error' => ['$type' => 'color', '$value' => '#d52b1e', '$extensions' => ['nl.conduction.thematiq' => ['cssVariable' => '--nldesign-color-error']]]]]];
		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: ['--nldesign-color-error' => '#d52b1e'], actual: $result['declarations']);
		$this->assertSame(expected: [], actual: $result['skipped']);
		$this->assertFalse(condition: $result['thematiqExport'], message: 'only a root setId marks a thematiq export');
	}//end testExtensionNamesTheTarget()

	/**
	 * The root cssOnly map is read as declarations; a token wins over it; a bad name is dropped.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md#requirement-w3c-design-tokens-json-import
	 *
	 * @return void
	 */
	public function testCssOnlyMapIsImported(): void {
		$document = [
			'color' => ['primary' => ['$type' => 'color', '$value' => '#154273']],
			'$extensions' => ['nl.conduction.thematiq' => ['setId' => 'x', 'cssOnly' => ['--nldesign-header-background' => 'linear-gradient(90deg, #154273, #01689b)', '--nldesign-color-primary' => '#000000', 'not a name' => 'x']]],
		];
		$result = $this->mapper->map(document: $document);

		$this->assertSame(expected: 'linear-gradient(90deg, #154273, #01689b)', actual: $result['declarations']['--nldesign-header-background']);
		$this->assertSame(expected: '#154273', actual: $result['declarations']['--nldesign-color-primary']);
		$this->assertCount(expectedCount: 2, haystack: $result['declarations']);
		$this->assertTrue(condition: $result['thematiqExport']);
	}//end testCssOnlyMapIsImported()
}//end class
