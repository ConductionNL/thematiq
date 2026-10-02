<?php

/**
 * Thematiq Environment Marker Service.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Service
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/environment-marker/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Marks every page of a non-production server with its environment.
 *
 * The environment is the system value `thematiq.environment` in config.php,
 * never app config: OTAP copies are made by restoring a database, and an app
 * config value would travel with it and label test as production. The marker
 * is a safety signal, not a theme, so the listener calls it before the
 * per-app exclusion guard, and its colours are fixed rather than taken from
 * the house style.
 *
 * @spec openspec/specs/environment-marker/spec.md
 */
class EnvironmentMarkerService {

	/**
	 * The system config key in config.php.
	 */
	public const CONFIG_KEY = Application::ENVIRONMENT_CONFIG_KEY;

	/**
	 * The command an administrator runs to declare the environment.
	 */
	public const OCC_SET_COMMAND = Application::ENVIRONMENT_OCC_COMMAND;

	/**
	 * App config key holding the unknown value last logged, so a typo is
	 * logged once and not on every render. Only that note lives in app
	 * config; the environment itself is never read from there.
	 */
	private const WARNED_KEY = 'environment_unknown_logged';

	/**
	 * Fixed stripe and label colours per style. Each pair reaches 4.5:1 and
	 * is mirrored in css/environment-marker.css (a unit test holds the two
	 * together). The stripe does not follow the colour scheme.
	 */
	public const STYLES = [
		'development' => ['background' => '#5B2A86', 'text' => '#FFFFFF'],
		'test' => ['background' => '#FFC83D', 'text' => '#000000'],
		'acceptance' => ['background' => '#0B5CAD', 'text' => '#FFFFFF'],
	];

	/**
	 * The system config.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * Hands the marker to js/environment-marker.js.
	 *
	 * @var IInitialState
	 */
	private IInitialState $initialState;

	/**
	 * Translates the labels.
	 *
	 * @var IL10N
	 */
	private IL10N $l10n;

	/**
	 * Records a typo in config.php and a swallowed failure.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The system config.
	 * @param IInitialState $initialState The initial state channel.
	 * @param IL10N $l10n The translator.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		IConfig $config,
		IInitialState $initialState,
		IL10N $l10n,
		LoggerInterface $logger,
	) {
		$this->config = $config;
		$this->initialState = $initialState;
		$this->l10n = $l10n;
		$this->logger = $logger;
	}//end __construct()

	/**
	 * The environment config.php declares, trimmed and lower-cased; '' when unset.
	 *
	 * @return string The declared value.
	 *
	 * @spec openspec/specs/environment-marker/spec.md
	 */
	public function getDeclared(): string {
		return strtolower(trim($this->config->getSystemValueString(self::CONFIG_KEY, '')));
	}//end getDeclared()

	/**
	 * Resolve the marker for this server, or null on production or unset.
	 *
	 * A value outside the allowed list is shown as "Unknown environment" with
	 * the test styling and logged once, so a typo never looks like production.
	 *
	 * @return array{environment: string, style: string, label: string, short: string}|null The marker.
	 *
	 * @spec openspec/specs/environment-marker/spec.md
	 */
	public function resolve(): ?array {
		$environment = $this->getDeclared();
		if ($environment === '' || $environment === 'production') {
			return null;
		}

		$labels = [
			'development' => [$this->l10n->t('Development environment'), $this->l10n->t('[Development]')],
			'test' => [$this->l10n->t('Test environment'), $this->l10n->t('[Test]')],
			'acceptance' => [$this->l10n->t('Acceptance environment'), $this->l10n->t('[Acceptance]')],
		];

		if (isset($labels[$environment]) === false) {
			$this->logUnknownOnce(environment: $environment);

			return [
				'environment' => $environment,
				'style' => 'test',
				'label' => $this->l10n->t('Unknown environment'),
				'short' => $this->l10n->t('[Unknown]'),
			];
		}

		return [
			'environment' => $environment,
			'style' => $environment,
			'label' => $labels[$environment][0],
			'short' => $labels[$environment][1],
		];
	}//end resolve()

	/**
	 * Log an unknown environment value once, not on every render.
	 *
	 * The marker is resolved on every rendered page, the login and public pages
	 * included, so a typo in config.php wrote one warning per request.
	 *
	 * @param string $environment The unknown value.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/environment-marker/spec.md
	 */
	private function logUnknownOnce(string $environment): void {
		if ($this->config->getAppValue(Application::APP_ID, self::WARNED_KEY, '') === $environment) {
			return;
		}

		$this->logger->warning(
			'thematiq: unknown environment {value} in config.php; allowed are development, test, acceptance and production.',
			['app' => Application::APP_ID, 'value' => $environment]
		);
		$this->config->setAppValue(Application::APP_ID, self::WARNED_KEY, $environment);
	}//end logUnknownOnce()

	/**
	 * Emit the marker assets and state for this render. Fails open: an error
	 * renders no marker and logs a warning, it never breaks the page.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/environment-marker/spec.md
	 */
	public function inject(): void {
		try {
			$marker = $this->resolve();
			if ($marker === null) {
				return;
			}

			$this->emitAssets();
			$this->initialState->provideInitialState('environment', $marker);
		} catch (Throwable $e) {
			$this->logger->warning(
				'thematiq: the environment marker failed for this render; the page was served without it.',
				['app' => Application::APP_ID, 'exception' => $e]
			);
		}
	}//end inject()

	/**
	 * Emit the marker's script and stylesheet. A seam so unit tests need no
	 * Nextcloud bootstrap (see ThemePreviewBannerService::emitPreviewAssets()).
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - \OCP\Util is the Nextcloud API for asset injection.
	 *
	 * @spec openspec/specs/environment-marker/spec.md
	 */
	protected function emitAssets(): void {
		\OCP\Util::addScript(application: Application::APP_ID, file: 'environment-marker');
		\OCP\Util::addStyle(application: Application::APP_ID, file: 'environment-marker');
	}//end emitAssets()
}//end class
