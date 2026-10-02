<?php

/**
 * The overrides file keeps every registry token and its dark value.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-import-export/spec.md
 * @spec openspec/specs/token-editor-ui/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\TokenValueValidator;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * A settable theme colour and an internal colour derive no dark value, so the
 * administrator's own is the only one they can have: it must reach the file.
 */
class CustomOverridesServiceEveryTokenTest extends TestCase {

	private string $dir;
	private DirectoryRuntimeFileStore $store;
	private CustomOverridesService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/thematiq-every-' . bin2hex(random_bytes(4));
		mkdir($this->dir, 0777, true);
		$this->store = new DirectoryRuntimeFileStore($this->dir);
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$parser = new CssParserService();
		$dark = new DarkPaletteService(new ContrastService(), $parser, $appManager, $this->createMock(LoggerInterface::class), $this->store);
		$this->service = new CustomOverridesService($this->store, $parser, $dark);
	}

	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg($this->dir));
		parent::tearDown();
	}

	/**
	 * The dark scopes of the file: everything after the light block.
	 */
	private function darkPart(): string {
		$css = (string)$this->store->read('css/custom-overrides.css');

		return substr($css, (int)strpos($css, '}'));
	}

	public function testASettableColourKeepsTheAdministratorsDarkValue(): void {
		$this->service->write(tokens: ['--color-mark' => '#ffe08a'], darkTokens: ['--color-mark' => '#5c4a00']);

		$dark = $this->darkPart();
		$this->assertSame(2, substr_count($dark, '--nldesign-nc-color-mark: #5c4a00 !important;'), 'both dark scopes, as the token the theme scopes read');
		$this->assertSame(['--color-mark' => '#5c4a00'], $this->service->readDark());
	}

	public function testNoDarkValueMeansNoDarkLine(): void {
		$this->service->write(tokens: ['--color-mark' => '#ffe08a']);

		$this->assertStringNotContainsString('--nldesign-nc-color-mark', $this->darkPart(), 'the reset leaves Nextcloud its own dark value');
	}

	public function testAnInternalTokenAndItsDarkValueSurviveTheRoundTrip(): void {
		$this->service->write(
			tokens: ['--nldesign-nc-dp-hover-color' => '#e8eef5', '--nldesign-nc-dp-font-size' => '1rem', '--color-primary' => '#24578f'],
			darkTokens: ['--nldesign-nc-dp-hover-color' => '#333333']
		);
		$file = (string)$this->store->read('css/custom-overrides.css');

		// Import is a read of the same file and a write of what was read.
		$this->store->delete('css/custom-overrides.css');
		$this->store->write('css/custom-overrides.css', $file);
		$read = $this->service->read();
		$this->assertSame('#e8eef5', $read['--nldesign-nc-dp-hover-color']);
		$this->assertSame('1rem', $read['--nldesign-nc-dp-font-size']);
		$this->assertSame(['--nldesign-nc-dp-hover-color' => '#333333'], $this->service->readDark(), 'a brand colour keeps its derived value, which is not reported as own');
	}

	public function testAFileFromBeforeThisChangeStillReads(): void {
		$this->store->write('css/custom-overrides.css', ":root {\n  --color-primary: #24578f !important;\n  --color-main-text: #1a1a1a !important;\n}\n");

		$this->assertSame(['--color-primary' => '#24578f', '--color-main-text' => '#1a1a1a'], $this->service->read());
	}

	public function testTheServerChecksAnInternalValueByItsType(): void {
		$values = new TokenValueValidator();

		$this->assertSame([], $values->findRejected(['--nldesign-nc-dp-hover-color' => '#e8eef5'], ['--nldesign-nc-dp-hover-color' => '#333333']));
		$this->assertArrayHasKey('--nldesign-nc-dp-hover-color', $values->findRejected(['--nldesign-nc-dp-hover-color' => 'not-a-colour']));
		$this->assertSame(
			['--nldesign-nc-dp-font-size' => 'no dark value for this token'],
			$values->findRejected(['--nldesign-nc-dp-font-size' => '1rem'], ['--nldesign-nc-dp-font-size' => '2rem'])
		);
		$this->assertTrue($values->hasDarkValueFor('--color-mark'));
		$this->assertTrue($values->hasDarkValueFor('--nldesign-nc-dp-hover-color'));
		$this->assertFalse($values->hasDarkValueFor('--nldesign-nc-dp-font-size'));
	}
}
