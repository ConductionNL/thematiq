<?php

/**
 * Unit tests for the words in the top bar's search field.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\SearchPlaceholderService;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The theme's entry for an app wins, then the app's own text, then the theme's
 * `*` entry; each is translated through the right catalogue, and with none the
 * page keeps Nextcloud's words.
 */
class SearchPlaceholderServiceTest extends TestCase {

	/**
	 * App config values by app and key.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $values = [];

	/**
	 * The initial state mock.
	 *
	 * @var IInitialState&MockObject
	 */
	private $initialState;

	/**
	 * The logger mock.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private $logger;

	/**
	 * How often the script was emitted.
	 *
	 * @var int
	 */
	private int $scripts = 0;

	/**
	 * The service, with the script seam counted.
	 *
	 * @return SearchPlaceholderService The service.
	 */
	private function service(): SearchPlaceholderService {
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = '') => ($this->values[$app][$key] ?? $default)
		);

		$factory = $this->createMock(originalClassName: IFactory::class);
		$factory->method('get')->willReturnCallback(function (string $app) {
			$l10n = $this->createMock(originalClassName: IL10N::class);
			$l10n->method('t')->willReturnCallback(fn (string $text) => $app.':'.$text);
			return $l10n;
		});

		$test = $this;
		return new class($appConfig, $factory, $this->initialState, $this->logger, $test) extends SearchPlaceholderService {
			/**
			 * Constructor.
			 *
			 * @param IAppConfig $appConfig App config.
			 * @param IFactory $factory L10n factory.
			 * @param IInitialState $initialState Initial state.
			 * @param LoggerInterface $logger Logger.
			 * @param SearchPlaceholderServiceTest $test The test counting scripts.
			 */
			public function __construct($appConfig, $factory, $initialState, $logger, private SearchPlaceholderServiceTest $test) {
				parent::__construct($appConfig, $factory, $initialState, $logger);
			}

			/**
			 * Count instead of loading.
			 *
			 * @return void
			 */
			protected function emitScript(): void {
				$this->test->countScript();
			}
		};
	}

	/**
	 * Count an emitted script.
	 *
	 * @return void
	 */
	public function countScript(): void {
		$this->scripts++;
	}

	/**
	 * Set up the mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->initialState = $this->createMock(originalClassName: IInitialState::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->values = [];
		$this->scripts = 0;
	}

	/**
	 * An app's own text, translated through the app's catalogue (DqKop).
	 *
	 * @return void
	 */
	public function testTheAppsOwnTextThroughItsCatalogue(): void {
		$this->values['dossiq']['search_placeholder'] = 'Search cases, documents or contacts';
		$this->assertSame(expected: 'dossiq:Search cases, documents or contacts', actual: $this->service()->placeholderFor('dossiq'));
	}

	/**
	 * The theme's entry for the app wins over the app's own text.
	 *
	 * @return void
	 */
	public function testTheThemesEntryWins(): void {
		$this->values['dossiq']['search_placeholder'] = 'Search cases, documents or contacts';
		$this->values['thematiq']['search_placeholders'] = json_encode(['dossiq' => 'Search the case files']);
		$this->assertSame(expected: 'dossiq:Search the case files', actual: $this->service()->placeholderFor('dossiq'));
	}

	/**
	 * The theme's `*` entry for an app that names none, through thematiq's catalogue.
	 *
	 * @return void
	 */
	public function testTheThemesDefault(): void {
		$this->values['thematiq']['search_placeholders'] = json_encode(['*' => 'Search everything']);
		$this->assertSame(expected: 'thematiq:Search everything', actual: $this->service()->placeholderFor('files'));
		$this->assertSame(expected: 'thematiq:Search everything', actual: $this->service()->placeholderFor(null));
	}

	/**
	 * With no words anywhere, or a broken map, nothing is named.
	 *
	 * @return void
	 */
	public function testNothingNamed(): void {
		$this->assertNull(actual: $this->service()->placeholderFor('dossiq'));
		$this->values['thematiq']['search_placeholders'] = '{not json';
		$this->assertNull(actual: $this->service()->placeholderFor('dossiq'));
	}

	/**
	 * Inject provides the words and loads the script; with none it does neither.
	 *
	 * @return void
	 */
	public function testInjectProvidesAndLoads(): void {
		$this->values['dossiq']['search_placeholder'] = 'Search cases, documents or contacts';
		$this->initialState->expects($this->once())->method('provideInitialState')
			->with('search-placeholder', 'dossiq:Search cases, documents or contacts');
		$this->service()->inject('dossiq');
		$this->assertSame(expected: 1, actual: $this->scripts);

		$this->service()->inject('files');
		$this->assertSame(expected: 1, actual: $this->scripts);
	}

	/**
	 * A failure is logged and the page keeps Nextcloud's words.
	 *
	 * @return void
	 */
	public function testFailsOpen(): void {
		$this->values['dossiq']['search_placeholder'] = 'Search cases';
		$this->initialState->method('provideInitialState')->willThrowException(new RuntimeException('boom'));
		$this->logger->expects($this->once())->method('warning');
		$this->service()->inject('dossiq');
		$this->assertSame(expected: 0, actual: $this->scripts);
	}
	/**
	 * The map keeps only string keys with non-blank string texts, trimmed; the app's own text is trimmed too.
	 *
	 * @return void
	 */
	public function testTheMapIsFilteredAndTheOrderHolds(): void {
		$this->values['thematiq']['search_placeholders'] = json_encode(['files' => '   ', 'deck' => 7, '*' => '  Search all  ', 'mail' => ' Search mail ', 0 => 'numbered']);
		$this->values['files']['search_placeholder'] = '  Own files text ';
		$this->values['deck']['search_placeholder'] = '   ';
		$cases = [
			'files' => 'files:Own files text',
			'deck' => 'thematiq:Search all',
			'mail' => 'mail:Search mail',
			'0' => 'thematiq:Search all',
			'' => 'thematiq:Search all',
		];
		foreach ($cases as $app => $expected) {
			$this->assertSame(expected: $expected, actual: $this->service()->placeholderFor((string)$app), message: (string)$app);
		}

		$this->values['thematiq']['search_placeholders'] = '"a string"';
		$this->assertSame(expected: 'files:Own files text', actual: $this->service()->placeholderFor('files'));
		$this->assertNull(actual: $this->service()->placeholderFor('deck'));
		$this->assertNull(actual: $this->service()->placeholderFor(''));
	}
}
