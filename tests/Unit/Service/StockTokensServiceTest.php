<?php

/**
 * Tests for StockTokensService.
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

use OCA\Thematiq\Service\StockTokensService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The `nextcloud` token set, resolved from the instance instead of a file.
 *
 * The defect this guards is not a crash: a stale snapshot renders perfectly
 * and shows the wrong theme, which is why it survived from NC29 to NC34
 * unnoticed. So these tests are about VALUES — that what leaves here is what
 * the instance said, and that nothing leaves here at all when the instance
 * could not be asked.
 */
class StockTokensServiceTest extends TestCase {

	/**
	 * Build the service with a fixed stock-variable map.
	 *
	 * `stockVariables()` is the seam: it is the one method that reaches into
	 * the theming app, and overriding it is what lets the rest be asserted
	 * without a server.
	 *
	 * @param array<string, string> $stock   What the instance reports.
	 * @param array<string, string> $sources The --color-* => --nldesign-* map.
	 *
	 * @return StockTokensService The system under test.
	 */
	private function build(array $stock, array $sources): StockTokensService {
		$preview = $this->createMock(TokenSetPreviewService::class);
		$preview->method('getTokenSources')->willReturn($sources);

		$service = $this->getMockBuilder(StockTokensService::class)
			->setConstructorArgs([$preview, $this->createMock(LoggerInterface::class)])
			->onlyMethods(['stockVariables'])
			->getMock();

		$service->method('stockVariables')->willReturn($stock);

		return $service;
	}//end build()

	/**
	 * The instance's value reaches the token, under the token's name.
	 */
	public function testEmitsTheInstanceValueUnderTheTokenName(): void {
		$service = $this->build(
			['--color-primary' => '#00679e', '--color-error' => '#FFE7E7'],
			[
				'--color-primary' => '--nldesign-color-primary',
				'--color-error' => '--nldesign-color-error',
			]
		);

		$css = $service->getCss();

		$this->assertStringContainsString('--nldesign-color-primary:#00679e;', (string)$css);
		$this->assertStringContainsString('--nldesign-color-error:#FFE7E7;', (string)$css);
		$this->assertStringStartsWith(':root{', (string)$css);
	}//end testEmitsTheInstanceValueUnderTheTokenName()

	/**
	 * A `var()` reference is followed to the literal behind it.
	 *
	 * Passing the reference through would reopen the cycle the service exists
	 * to avoid — the token would point back at a variable `overrides.css` is
	 * itself rewriting, and the browser would discard both.
	 */
	public function testFollowsVarReferencesToALiteral(): void {
		$service = $this->build(
			[
				'--color-element-error' => '#c90000',
				'--color-border-error' => 'var(--color-element-error)',
			],
			['--color-border-error' => '--nldesign-color-border-error']
		);

		$css = (string)$service->getCss();

		$this->assertStringContainsString('--nldesign-color-border-error:#c90000;', $css);
		$this->assertStringNotContainsString('var(', $css);
	}//end testFollowsVarReferencesToALiteral()

	/**
	 * A value that cannot be reduced to a literal is dropped, not frozen.
	 *
	 * Gradients and `color-mix()` are resolved by the browser against whatever
	 * the cascade holds at paint time. Freezing one into a token would freeze
	 * the value it had in some other theme.
	 */
	public function testDropsCompositesItCannotResolve(): void {
		$service = $this->build(
			[
				'--color-primary' => '#00679e',
				'--gradient-primary-background' => 'linear-gradient(40deg, var(--color-primary) 0%, transparent 100%)',
				'--color-dangling' => 'var(--color-that-does-not-exist)',
			],
			[
				'--color-primary' => '--nldesign-color-primary',
				'--gradient-primary-background' => '--nldesign-gradient-primary-background',
				'--color-dangling' => '--nldesign-color-dangling',
			]
		);

		$css = (string)$service->getCss();

		$this->assertStringContainsString('--nldesign-color-primary:#00679e;', $css);
		$this->assertStringNotContainsString('--nldesign-gradient-primary-background', $css);
		$this->assertStringNotContainsString('--nldesign-color-dangling', $css);
	}//end testDropsCompositesItCannotResolve()

	/**
	 * When several variables read one token, the token takes its value from
	 * the variable that shares its name.
	 *
	 * Four variables read `--nldesign-color-primary` (overrides.css:24, 27, 35,
	 * 37) and their stock values differ — `--color-primary` is the brand blue,
	 * `--color-primary-light-text` is nearly black. Letting the last mapping
	 * win handed the token the text colour, which paints the header in a shade
	 * nothing asked for. Measured live on 34.0.4 before the fix: #00293f
	 * instead of #00679e.
	 */
	public function testTakesTheValueOfTheVariableSharingTheTokenName(): void {
		$service = $this->build(
			[
				'--color-primary' => '#00679e',
				'--color-primary-element' => '#00679e',
				'--color-primary-light-text' => '#00293f',
				'--color-primary-element-light-text' => '#00293f',
			],
			[
				// Deliberately ordered so the same-named variable is neither
				// first nor last: neither position may be what decides.
				'--color-primary-light-text' => '--nldesign-color-primary',
				'--color-primary' => '--nldesign-color-primary',
				'--color-primary-element' => '--nldesign-color-primary',
				'--color-primary-element-light-text' => '--nldesign-color-primary',
			]
		);

		$this->assertStringContainsString('--nldesign-color-primary:#00679e;', (string)$service->getCss());
	}//end testTakesTheValueOfTheVariableSharingTheTokenName()

	/**
	 * With no same-named variable, the pick is still deterministic.
	 *
	 * Otherwise the emitted theme would depend on the order declarations
	 * happen to appear in `overrides.css`, and reordering that file for
	 * readability would silently change what the page looks like.
	 */
	public function testPicksDeterministicallyWithoutANameMatch(): void {
		$sources = [
			'--color-zebra' => '--nldesign-color-unnamed',
			'--color-apple' => '--nldesign-color-unnamed',
		];
		$stock = ['--color-zebra' => '#111111', '--color-apple' => '#222222'];

		$first = $this->build($stock, $sources)->getCss();
		$second = $this->build($stock, array_reverse($sources, true))->getCss();

		$this->assertSame($first, $second);
		$this->assertStringContainsString('--nldesign-color-unnamed:#222222;', (string)$first);
	}//end testPicksDeterministicallyWithoutANameMatch()

	/**
	 * A variable the mapping does not mention is not invented into a token.
	 *
	 * The token set's vocabulary is `overrides.css`'s to define; this service
	 * only fills it in.
	 */
	public function testEmitsOnlyMappedVariables(): void {
		$service = $this->build(
			['--color-primary' => '#00679e', '--color-unmapped' => '#123456'],
			['--color-primary' => '--nldesign-color-primary']
		);

		$css = (string)$service->getCss();

		$this->assertStringNotContainsString('#123456', $css);
	}//end testEmitsOnlyMappedVariables()

	/**
	 * When the instance cannot be read, the answer is null — never an empty
	 * `:root {}`.
	 *
	 * Null is what makes the caller fall back to the shipped file. An empty
	 * block would be accepted as a valid layer and would leave the page with no
	 * token values at all, which is the one outcome worse than a stale theme.
	 */
	public function testReturnsNullWhenTheInstanceCannotBeRead(): void {
		$service = $this->build([], ['--color-primary' => '--nldesign-color-primary']);

		$this->assertNull($service->getCss());
	}//end testReturnsNullWhenTheInstanceCannotBeRead()

	/**
	 * Nothing overlapping means nothing to emit, and that is also null.
	 */
	public function testReturnsNullWhenNothingMaps(): void {
		$service = $this->build(
			['--color-primary' => '#00679e'],
			['--color-something-else' => '--nldesign-color-something-else']
		);

		$this->assertNull($service->getCss());
	}//end testReturnsNullWhenNothingMaps()

	/**
	 * The instance is asked once per request, however often the layer is.
	 *
	 * `inject()` and the stylesheet manifest both build the layer list, so an
	 * unmemoised resolve would recompute the whole theme twice on a settings
	 * page load.
	 */
	public function testAsksTheInstanceOnlyOnce(): void {
		$preview = $this->createMock(TokenSetPreviewService::class);
		$preview->method('getTokenSources')->willReturn(['--color-primary' => '--nldesign-color-primary']);

		$service = $this->getMockBuilder(StockTokensService::class)
			->setConstructorArgs([$preview, $this->createMock(LoggerInterface::class)])
			->onlyMethods(['stockVariables'])
			->getMock();

		$service->expects($this->once())
			->method('stockVariables')
			->willReturn(['--color-primary' => '#00679e']);

		$service->getCss();
		$service->getCss();
	}//end testAsksTheInstanceOnlyOnce()

	/**
	 * A failed resolve is not retried either, for the same reason.
	 */
	public function testDoesNotRetryAFailedResolve(): void {
		$preview = $this->createMock(TokenSetPreviewService::class);
		$preview->method('getTokenSources')->willReturn(['--color-primary' => '--nldesign-color-primary']);

		$service = $this->getMockBuilder(StockTokensService::class)
			->setConstructorArgs([$preview, $this->createMock(LoggerInterface::class)])
			->onlyMethods(['stockVariables'])
			->getMock();

		$service->expects($this->once())->method('stockVariables')->willReturn([]);

		$this->assertNull($service->getCss());
		$this->assertNull($service->getCss());
	}//end testDoesNotRetryAFailedResolve()
}//end class
