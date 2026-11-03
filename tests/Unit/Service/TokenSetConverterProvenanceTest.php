<?php

/**
 * Unit tests for the provenance guard: nothing from outside may spell a CSS
 * comment terminator in the emitted file.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * The guard on the provenance block was written twice, and the first version
 * passed a test suite that asserted the terminator was absent from the output.
 * It was still bypassable: it deleted the two-character sequence, and
 * `preg_replace()` consumes non-overlapping matches without re-examining what
 * the deletion brought together, so `**​/​/` lost its inner pair and the outer
 * `*` and `/` closed up into a fresh terminator. A control byte between the two
 * halves did the same thing once the byte was stripped.
 *
 * Example-based tests cannot close that, because the bug is precisely the case
 * nobody thought to write down. So this asserts the INVARIANT instead — that
 * neither `*` nor `/` survives — and proves it EXHAUSTIVELY over every string
 * up to four characters drawn from the alphabet that matters: the two halves of
 * a terminator, a byte the guard strips, and an ordinary character. 340 inputs,
 * covering every interleaving and doubling that fits.
 *
 * That is a stronger claim than "no terminator in the output": there is no
 * arrangement of characters that are not there.
 *
 * Reaching a private method by reflection is deliberate. The invariant belongs
 * to this one function, an exhaustive sweep through `convert()` would take
 * minutes rather than milliseconds, and the end-to-end consequence is already
 * covered by `CustomTokenSetUploadWritesTest` on both reachable sources.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */
class TokenSetConverterProvenanceTest extends TestCase {

	/**
	 * The guard under test, made reachable.
	 *
	 * @var ReflectionMethod
	 */
	private ReflectionMethod $commentSafe;

	/**
	 * The converter the guard belongs to.
	 *
	 * @var TokenSetConverterService
	 */
	private TokenSetConverterService $converter;

	/**
	 * Build the converter and open up `commentSafe()`.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		$this->converter = new TokenSetConverterService(
			appManager: $appManager,
			cssParser: new CssParserService(),
			contrast: new ContrastService(),
			dtcgMapper: new DesignTokensMapper(),
			fonts: $this->createMock(originalClassName: FontService::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$this->commentSafe = new ReflectionMethod(TokenSetConverterService::class, 'commentSafe');
		$this->commentSafe->setAccessible(true);
	}//end setUp()

	/**
	 * Run the guard.
	 *
	 * @param string $value The untrusted value.
	 *
	 * @return string The guarded value.
	 */
	private function guard(string $value): string {
		return (string)$this->commentSafe->invoke($this->converter, $value);
	}//end guard()

	/**
	 * No string over the terminator alphabet, up to four characters, can leave
	 * an asterisk or a slash in the output — so none can spell `*​/`, however
	 * the deletions rearrange it.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 *
	 * @return void
	 */
	public function testNoShortStringCanLeaveATerminatorBehind(): void {
		$alphabet = ['*', '/', "\x00", 'a'];
		$inputs = [''];
		$frontier = [''];

		for ($length = 1; $length <= 4; $length++) {
			$next = [];
			foreach ($frontier as $prefix) {
				foreach ($alphabet as $character) {
					$next[] = ($prefix . $character);
				}
			}

			$inputs = array_merge($inputs, $next);
			$frontier = $next;
		}

		// 4 + 16 + 64 + 256, plus the empty string.
		$this->assertCount(expectedCount: 341, haystack: $inputs);

		foreach ($inputs as $input) {
			$guarded = $this->guard(value: $input);

			$this->assertStringNotContainsString(
				needle: '*',
				haystack: $guarded,
				message: 'asterisk survived ' . var_export($input, true)
			);
			$this->assertStringNotContainsString(
				needle: '/',
				haystack: $guarded,
				message: 'slash survived ' . var_export($input, true)
			);
		}
	}//end testNoShortStringCanLeaveATerminatorBehind()

	/**
	 * The spellings that actually defeated the first guard, named so the
	 * regression is legible rather than buried in the sweep above.
	 *
	 * @param string $payload The terminator spelling.
	 *
	 * @dataProvider reconstructionProvider
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 *
	 * @return void
	 */
	public function testTheSpellingsThatDefeatedTheFirstGuard(string $payload): void {
		$this->assertStringNotContainsString(needle: '*/', haystack: $this->guard(value: $payload));
	}//end testTheSpellingsThatDefeatedTheFirstGuard()

	/**
	 * Terminator spellings that survive a sequence-wise delete.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function reconstructionProvider(): array {
		return [
			'doubled' => ['**//'],
			'split by a control byte' => ["*\x00/"],
			'doubled and split' => ["*\x00*\x00//"],
			'nested' => ['*/*/'],
			'trailing half' => ['**/'],
			'leading half' => ['*//'],
		];
	}//end reconstructionProvider()

	/**
	 * The guard is lossy, not blanking: an ordinary filename still reaches the
	 * provenance line intact, which is the whole point of recording it.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 *
	 * @return void
	 */
	public function testOrdinaryValuesSurviveUnchanged(): void {
		$this->assertSame(expected: 'gemeente-voorbeeld-tokens.css', actual: $this->guard(value: 'gemeente-voorbeeld-tokens.css'));
		$this->assertSame(expected: '2.3.1', actual: $this->guard(value: '2.3.1'));
		$this->assertSame(expected: '1.0.0-beta+build.7', actual: $this->guard(value: '1.0.0-beta+build.7'));
		$this->assertSame(expected: '(pasted)', actual: $this->guard(value: '(pasted)'));
	}//end testOrdinaryValuesSurviveUnchanged()

	/**
	 * A value that is nothing BUT stripped characters still has to render as
	 * something, or the provenance line loses its shape.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 *
	 * @return void
	 */
	public function testAFullyStrippedValueFallsBackToAPlaceholder(): void {
		$this->assertSame(expected: '(unnamed)', actual: $this->guard(value: '*/'));
		$this->assertSame(expected: '(unnamed)', actual: $this->guard(value: "\x00\x01\x02"));
		$this->assertSame(expected: '(unnamed)', actual: $this->guard(value: ''));
	}//end testAFullyStrippedValueFallsBackToAPlaceholder()
}//end class
