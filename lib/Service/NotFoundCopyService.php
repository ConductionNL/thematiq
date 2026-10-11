<?php

/**
 * Thematiq Not Found Copy Service.
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
 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The words and the ways on from Nextcloud's "page not found" page, per token set.
 *
 * Nextcloud's 404 page shows an icon, "Page not found", one sentence and a
 * button back to the start page. The NietGevonden board shows an error code
 * above the title, a longer explanation, "Of ga verder naar" with a short
 * list of links, and a closing line. A token set carries that copy in a
 * `not_found` block in token-sets.json; an administrator may override any
 * key in the app config key `not_found_copy` (the same shape, key by key).
 * js/not-found.js writes it into the page.
 *
 * Every text follows {@see CopyValues}: one string, or a map per language;
 * a language the map does not name keeps Nextcloud's own words. `links` is a
 * list of `{label, url}`; a link without a label or with an address that is
 * not http(s) or a path on this server is dropped.
 *
 * Presentation, never a dependency: only the light workplace loads it, and a
 * failure is logged and leaves Nextcloud's own page.
 *
 * @SuppressWarnings(PHPMD.StaticAccess) - CopyValues holds the pure value rules this service shares with the login page.
 *
 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md
 */
class NotFoundCopyService {

	/**
	 * The initial state key js/not-found.js reads.
	 *
	 * @var string
	 */
	public const STATE_KEY = 'not-found-copy';

	/**
	 * The script under `js/`, without the extension.
	 *
	 * @var string
	 */
	public const SCRIPT = 'not-found';

	/**
	 * The app config key an administrator overrides the set's copy in.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'not_found_copy';

	/**
	 * The text keys a `not_found` block may carry; `links` is read apart.
	 *
	 * @var array<int, string>
	 */
	public const KEYS = ['eyebrow', 'title', 'text', 'links_heading', 'hint'];

	/**
	 * The most links shown.
	 *
	 * @var int
	 */
	public const MAX_LINKS = 6;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config Reads the administrator's override.
	 * @param DesignSystemService $designSystemService Reads a set's manifest entry.
	 * @param IInitialState $initialState The initial state channel.
	 * @param IFactory $l10nFactory The language of this request.
	 * @param LoggerInterface $logger Records a swallowed failure.
	 * @param GroupThemingService $groupTheming Resolves the page's token set.
	 * @param LayoutOptionsService $layoutOptions Resolves the page's layout.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly DesignSystemService $designSystemService,
		private readonly IInitialState $initialState,
		private readonly IFactory $l10nFactory,
		private readonly LoggerInterface $logger,
		private readonly GroupThemingService $groupTheming,
		private readonly LayoutOptionsService $layoutOptions,
	) {
	}//end __construct()

	/**
	 * The copy for a page rendered with this token set, in this language:
	 * the set's `not_found` block, then the administrator's override, key by key.
	 *
	 * @param string $tokenSet The token set the page renders with.
	 * @param string $language The request's language code, e.g. `nl` or `en_GB`.
	 *
	 * @return array<string, string|array<int, array{label: string, url: string}>> The copy, keyed in camel case.
	 *
	 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-not-found-copy
	 */
	public function resolveCopy(string $tokenSet, string $language): array {
		$meta = $this->designSystemService->getTokenSetMeta(tokenSetId: $tokenSet);
		$fromSet = ($meta['not_found'] ?? null);
		$fromAdmin = json_decode($this->config->getAppValue(Application::APP_ID, self::CONFIG_KEY, ''), true);

		$copy = [];
		foreach ([$fromSet, $fromAdmin] as $source) {
			if (is_array($source) === false) {
				continue;
			}

			foreach (self::KEYS as $key) {
				$value = CopyValues::pick(value: ($source[$key] ?? null), language: $language);
				if ($value !== null) {
					$copy[CopyValues::camel(key: $key)] = $value;
				}
			}

			if (is_array($source['links'] ?? null) === true) {
				$copy['links'] = self::links(rows: $source['links'], language: $language);
			}
		}

		return $copy;
	}//end resolveCopy()

	/**
	 * The links that may be shown, at most {@see self::MAX_LINKS}.
	 *
	 * @param array<mixed> $rows The `links` list as written.
	 * @param string $language The request's language code.
	 *
	 * @return array<int, array{label: string, url: string}> The links.
	 *
	 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-not-found-copy
	 */
	private static function links(array $rows, string $language): array {
		$links = [];
		foreach ($rows as $row) {
			if (is_array($row) === false) {
				continue;
			}

			$label = CopyValues::pick(value: ($row['label'] ?? null), language: $language);
			$url = CopyValues::pick(value: ($row['url'] ?? null), language: $language);
			if ($label !== null && $url !== null && CopyValues::isAddress(value: $url) === true) {
				$links[] = ['label' => $label, 'url' => $url];
			}
		}

		return array_slice($links, 0, self::MAX_LINKS);
	}//end links()

	/**
	 * Provide the copy and load the script on a not-found page drawn by the
	 * light workplace. Fails open.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md#requirement-the-light-workplace-draws-the-not-found-page-of-the-board
	 */
	public function inject(): void {
		try {
			$tokenSet = $this->groupTheming->resolveTokenSetForRequest(appBrandSet: null);
			if ($this->layoutOptions->workplaceLayout(tokenSet: $tokenSet) !== LayoutOptionsService::LAYOUT_LIGHT) {
				return;
			}

			$language = $this->l10nFactory->get(Application::APP_ID)->getLanguageCode();
			$this->initialState->provideInitialState(self::STATE_KEY, $this->resolveCopy(tokenSet: $tokenSet, language: $language));
			$this->emitScript();
		} catch (Throwable $e) {
			$this->logger->warning(
				'thematiq: the not-found copy failed for this render; the page keeps Nextcloud\'s words.',
				['app' => Application::APP_ID, 'exception' => $e]
			);
		}
	}//end inject()

	/**
	 * Load js/not-found.js. A seam, so unit tests need no Nextcloud bootstrap.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md#requirement-the-light-workplace-draws-the-not-found-page-of-the-board
	 */
	protected function emitScript(): void {
		\OCP\Util::addScript(application: Application::APP_ID, file: self::SCRIPT);
	}//end emitScript()
}//end class
