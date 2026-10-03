<?php

/**
 * TokenDeprecationService: replacements must exist, dates may not lie in the past, a passed
 * date is flagged and never acted on, and imported notices are recorded only when asked.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/token-deprecations/spec.md#requirement-an-administrator-deprecates-a-token
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DeprecationRecords;
use OCA\Thematiq\Service\OwnTokenService;
use OCA\Thematiq\Service\TokenDeprecationService;
use OCA\Thematiq\Service\TokenValueValidator;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * Over an in-memory app config and this repo's defaults.css.
 *
 * @spec openspec/specs/token-deprecations/spec.md#requirement-an-administrator-deprecates-a-token
 */
final class TokenDeprecationServiceTest extends TestCase {

	/**
	 * The stored app values.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The own tokens.
	 *
	 * @var OwnTokenService
	 */
	private OwnTokenService $ownTokens;

	/**
	 * The service under test.
	 *
	 * @var TokenDeprecationService
	 */
	private TokenDeprecationService $service;

	/**
	 * Set up two own tokens.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->stored = [];
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->stored[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(function (string $app, string $key, $value): void {
			$this->stored[$key] = (string)$value;
		});
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$records = new DeprecationRecords($config);
		$this->ownTokens = new OwnTokenService($config, new TokenValueValidator(), $records);
		$this->service = new TokenDeprecationService($records, $this->ownTokens, $appManager, new CssParserService());
		$this->ownTokens->create(input: ['slug' => 'old-accent', 'label' => 'Old', 'type' => 'color', 'value' => '#aa0000']);
		$this->ownTokens->create(input: ['slug' => 'brand-accent', 'label' => 'Brand', 'type' => 'color', 'value' => '#e17000']);
	}//end setUp()

	/**
	 * Scenario: a replacement that does not exist is refused, naming the replacement.
	 *
	 * @return void
	 */
	public function testReplacementMustExist(): void {
		try {
			$this->service->deprecate(token: '--nldesign-org-old-accent', input: ['severity' => 'warning', 'replacement' => '--nldesign-org-missing'], today: '2026-10-02');
			$this->fail('expected a refusal');
		} catch (InvalidArgumentException $e) {
			$this->assertSame('replacement', $e->getMessage());
		}

		$this->assertSame([], $this->service->list());

		// An own token, an editor token and a defaults.css name all count as existing.
		foreach (['--nldesign-org-brand-accent', '--color-primary', '--nldesign-color-primary'] as $replacement) {
			$this->service->deprecate(token: '--nldesign-org-old-accent', input: ['severity' => 'warning', 'replacement' => $replacement], today: '2026-10-02');
		}

		$this->assertSame('--nldesign-color-primary', $this->service->list()['--nldesign-org-old-accent']['replacement']);
	}//end testReplacementMustExist()

	/**
	 * A removal date in the past, or not a date, is refused; today is allowed.
	 *
	 * @return void
	 */
	public function testPastDateRefused(): void {
		foreach (['2026-10-01', '2026-02-30', 'tomorrow'] as $date) {
			try {
				$this->service->deprecate(token: '--nldesign-org-old-accent', input: ['severity' => 'info', 'removalDate' => $date], today: '2026-10-02');
				$this->fail('expected a refusal for ' . $date);
			} catch (InvalidArgumentException $e) {
				$this->assertSame('removalDate', $e->getMessage());
			}
		}

		$record = $this->service->deprecate(token: '--nldesign-org-old-accent', input: ['severity' => 'info', 'removalDate' => '2026-10-02'], today: '2026-10-02');
		$this->assertSame('2026-10-02', $record['removalDate']);
	}//end testPastDateRefused()

	/**
	 * Scenario: a passed removal date is flagged as due; the record and the token stay.
	 *
	 * @return void
	 */
	public function testDueAfterDate(): void {
		$this->service->deprecate(token: '--nldesign-org-old-accent', input: ['severity' => 'warning', 'removalDate' => '2027-03-01'], today: '2026-10-02');

		$this->assertFalse($this->service->list(today: '2027-03-01')['--nldesign-org-old-accent']['due']);
		$this->assertTrue($this->service->list(today: '2027-03-02')['--nldesign-org-old-accent']['due']);
		$this->assertTrue($this->ownTokens->exists(name: '--nldesign-org-old-accent'));
	}//end testDueAfterDate()

	/**
	 * A deprecation never changes the token's value.
	 *
	 * @return void
	 */
	public function testValueNeverChanged(): void {
		$before = $this->ownTokens->list();
		$this->service->deprecate(token: '--nldesign-org-old-accent', input: ['severity' => 'critical', 'replacement' => '--nldesign-org-brand-accent'], today: '2026-10-02');

		$this->assertSame($before, $this->ownTokens->list());
	}//end testValueNeverChanged()

	/**
	 * The notice above a deprecated token reads as the spec says.
	 *
	 * @return void
	 */
	public function testCommentText(): void {
		$this->service->deprecate(token: '--nldesign-org-old-accent', input: ['severity' => 'warning', 'replacement' => '--nldesign-org-brand-accent', 'removalDate' => '2027-03-01'], today: '2026-10-02');
		$this->service->deprecate(token: '--nldesign-color-primary-light', input: ['severity' => 'info'], today: '2026-10-02');

		$this->assertSame(
			[
				'--nldesign-color-primary-light' => 'deprecated (info)',
				'--nldesign-org-old-accent' => 'deprecated (warning): use --nldesign-org-brand-accent, removal 2027-03-01',
			],
			$this->service->comments()
		);
	}//end testCommentText()

	/**
	 * Removing a deprecated own token keeps its record with the state `removed`.
	 *
	 * @return void
	 */
	public function testRemovedTokenKeepsItsRecord(): void {
		$this->service->deprecate(token: '--nldesign-org-old-accent', input: ['severity' => 'warning'], today: '2026-10-02');
		$this->ownTokens->remove(name: '--nldesign-org-old-accent');
		$this->service->markRemoved(token: '--nldesign-org-old-accent');

		$this->assertSame('removed', $this->service->publicList()[0]['state']);
	}//end testRemovedTokenKeepsItsRecord()

	/**
	 * Only thematiq names can be deprecated; Nextcloud's own variables cannot.
	 *
	 * @return void
	 */
	public function testOnlyThematiqNames(): void {
		$this->expectExceptionMessage('token');
		$this->service->deprecate(token: '--color-primary', input: ['severity' => 'info']);
	}//end testOnlyThematiqNames()

	/**
	 * Scenario: the notices of an upload become import records with severity warning and the message.
	 *
	 * @return void
	 */
	public function testImportNoticesAdopted(): void {
		$recorded = $this->service->adoptImportNotices(
			notices: [
				['path' => 'color.primary', 'message' => 'Use color.brand.primary instead', 'token' => '--nldesign-color-primary'],
				['path' => 'typography.body', 'message' => 'no single variable'],
			]
		);

		$this->assertSame(['--nldesign-color-primary'], $recorded);
		$record = $this->service->list()['--nldesign-color-primary'];
		$this->assertSame('warning', $record['severity']);
		$this->assertSame('import', $record['source']);
		$this->assertSame('Use color.brand.primary instead', $record['message']);
	}//end testImportNoticesAdopted()
}//end class
