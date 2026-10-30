<?php

/**
 * Thematiq Search Placeholder Service.
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
 * @spec openspec/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The words in the top bar's search field, per app.
 *
 * Nextcloud's unified search reads "Search apps, files, tags, messages …"
 * on every page. A workplace app draws its own words there (the DqKop board:
 * "Zoek zaken, documenten of contacten"). The text for the app being
 * rendered is, in this order:
 *
 * 1. the theme's entry for that app in `search_placeholders`, a JSON map of
 *    app id to text that an administrator or a config bundle sets;
 * 2. the app's own `search_placeholder` app config value, which an app
 *    declares in its own namespace (a config lexicon default needs no write);
 * 3. the theme's `*` entry.
 *
 * The text is an English source string translated through the app's own
 * catalogue (thematiq's for the `*` entry), so the field speaks the
 * person's language. With no text, nothing is injected and Nextcloud's own
 * words stay. Presentation, never a dependency: a failure is logged and the
 * page keeps Nextcloud's words.
 *
 * @spec openspec/specs/workplace-layout/spec.md
 */
class SearchPlaceholderService {

	/**
	 * The theme's map of app id to text, in thematiq's app config.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'search_placeholders';

	/**
	 * The key an app declares its own text under, in its own app config.
	 *
	 * @var string
	 */
	public const APP_KEY = 'search_placeholder';

	/**
	 * The initial state key js/search-placeholder.js reads.
	 *
	 * @var string
	 */
	public const STATE_KEY = 'search-placeholder';

	/**
	 * The script under `js/`, without the extension.
	 *
	 * @var string
	 */
	public const SCRIPT = 'search-placeholder';

	/**
	 * Constructor.
	 *
	 * @param IAppConfig      $appConfig    Reads the theme's map and the app's own text.
	 * @param IFactory        $l10nFactory  Translates through the app's catalogue.
	 * @param IInitialState   $initialState The initial state channel.
	 * @param LoggerInterface $logger       Records a swallowed failure.
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IFactory $l10nFactory,
		private readonly IInitialState $initialState,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The translated words for the search field on a page of this app, or
	 * null when neither the theme nor the app names any.
	 *
	 * @param string|null $appId The app being rendered; null on a page of no app.
	 *
	 * @return string|null The words.
	 *
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-an-app-or-the-theme-words-the-search-field
	 */
	public function placeholderFor(?string $appId): ?string {
		$map = $this->themeMap();
		$app = ($appId ?? '');

		if ($app !== '' && isset($map[$app]) === true) {
			return $this->translate(appId: $app, text: $map[$app]);
		}

		if ($app !== '') {
			$own = trim($this->appConfig->getValueString($app, self::APP_KEY, ''));
			if ($own !== '') {
				return $this->translate(appId: $app, text: $own);
			}
		}

		if (isset($map['*']) === true) {
			return $this->translate(appId: Application::APP_ID, text: $map['*']);
		}

		return null;
	}//end placeholderFor()

	/**
	 * Provide the words and load the script for a page of this app. Fails open.
	 *
	 * @param string|null $appId The app being rendered.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-an-app-or-the-theme-words-the-search-field
	 */
	public function inject(?string $appId): void {
		try {
			$text = $this->placeholderFor(appId: $appId);
			if ($text === null) {
				return;
			}

			$this->initialState->provideInitialState(self::STATE_KEY, $text);
			$this->emitScript();
		} catch (Throwable $e) {
			$this->logger->warning(
				'thematiq: the search field words failed for this render; the field keeps Nextcloud\'s words.',
				['app' => Application::APP_ID, 'exception' => $e]
			);
		}
	}//end inject()

	/**
	 * The theme's map, without empty or non-string entries.
	 *
	 * @return array<string, string> App id (or `*`) to text.
	 *
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-an-app-or-the-theme-words-the-search-field
	 */
	private function themeMap(): array {
		$raw = $this->appConfig->getValueString(Application::APP_ID, self::CONFIG_KEY, '');
		if ($raw === '') {
			return [];
		}

		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false) {
			return [];
		}

		$map = [];
		foreach ($decoded as $key => $text) {
			if (is_string($key) === true && is_string($text) === true && trim($text) !== '') {
				$map[$key] = trim($text);
			}
		}

		return $map;
	}//end themeMap()

	/**
	 * Translate a source string through an app's catalogue.
	 *
	 * @param string $appId The app whose catalogue holds the text.
	 * @param string $text  The English source string.
	 *
	 * @return string The words in the person's language, or the text itself.
	 *
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-an-app-or-the-theme-words-the-search-field
	 */
	private function translate(string $appId, string $text): string {
		$translated = $this->l10nFactory->get($appId)->t($text);
		if ($translated === '') {
			return $text;
		}

		return $translated;
	}//end translate()

	/**
	 * Load js/search-placeholder.js. A seam, so unit tests need no Nextcloud bootstrap.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - \OCP\Util is the Nextcloud API for asset injection.
	 *
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-an-app-or-the-theme-words-the-search-field
	 */
	protected function emitScript(): void {
		\OCP\Util::addScript(application: Application::APP_ID, file: self::SCRIPT);
	}//end emitScript()
}//end class
