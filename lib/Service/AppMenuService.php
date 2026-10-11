<?php

/**
 * Thematiq App Menu Service.
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
 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md
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
 * The sections of the apps menu in the workplace top bar, per token set.
 *
 * Nextcloud's apps menu is one grid of every app. The AppMenu board groups
 * it: a section of the organisation's own apps ("Werk"), each with a short
 * description and an optional link beside the heading, then every other app
 * under its own heading ("Nextcloud"), and a footer with the keyboard hint
 * and "Apps beheren". Which apps form a section is not something Nextcloud
 * knows, so a token set carries it in an `app_menu` block in token-sets.json
 * and an administrator may replace any key in the app config key
 * `app_menu_config`. js/app-menu.js arranges the open menu by it.
 *
 * Texts follow {@see CopyValues}. Loaded with the workplace header style
 * only ({@see LayoutOptionsService::HEADER_WORKPLACE_STYLESHEET}).
 * Presentation, never a dependency: a failure is logged and leaves
 * Nextcloud's menu, which css/header-workplace.css still draws as the board.
 *
 * @SuppressWarnings(PHPMD.StaticAccess) - CopyValues holds the pure value rules shared with the login and not-found copy.
 *
 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md
 */
class AppMenuService {

	/**
	 * The initial state key js/app-menu.js reads.
	 *
	 * @var string
	 */
	public const STATE_KEY = 'app-menu';

	/**
	 * The script under `js/`, without the extension.
	 *
	 * @var string
	 */
	public const SCRIPT = 'app-menu';

	/**
	 * The app config key an administrator overrides the set's block in.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'app_menu_config';

	/**
	 * The text keys of the block; `groups` is read apart.
	 *
	 * @var array<int, string>
	 */
	public const KEYS = ['others_title', 'hint', 'manage_label'];

	/**
	 * The most sections, and the most apps in one.
	 *
	 * @var int
	 */
	public const MAX_GROUPS = 4;

	/**
	 * The most apps in one section.
	 *
	 * @var int
	 */
	public const MAX_APPS = 24;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config Reads the administrator's override.
	 * @param DesignSystemService $designSystemService Reads a set's manifest entry.
	 * @param IInitialState $initialState The initial state channel.
	 * @param IFactory $l10nFactory The language of this request.
	 * @param LoggerInterface $logger Records a swallowed failure.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly DesignSystemService $designSystemService,
		private readonly IInitialState $initialState,
		private readonly IFactory $l10nFactory,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The menu's sections for a page rendered with this token set, in this
	 * language: the set's `app_menu` block, then the override, key by key.
	 *
	 * @param string $tokenSet The token set the page renders with.
	 * @param string $language The request's language code.
	 *
	 * @return array<string, mixed> `othersTitle`, `hint`, `manageLabel` and `groups`
	 *                              (each `{title, link?: {label, url}, apps: [{id, description?}]}`).
	 *
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md#requirement-a-token-set-may-group-the-apps-menu
	 */
	public function resolveMenu(string $tokenSet, string $language): array {
		$meta = $this->designSystemService->getTokenSetMeta(tokenSetId: $tokenSet);
		$fromSet = ($meta['app_menu'] ?? null);
		$fromAdmin = json_decode($this->config->getAppValue(Application::APP_ID, self::CONFIG_KEY, ''), true);

		$menu = [];
		foreach ([$fromSet, $fromAdmin] as $source) {
			if (is_array($source) === false) {
				continue;
			}

			foreach (self::KEYS as $key) {
				$value = CopyValues::pick(value: ($source[$key] ?? null), language: $language);
				if ($value !== null) {
					$menu[CopyValues::camel(key: $key)] = $value;
				}
			}

			if (is_array($source['groups'] ?? null) === true) {
				$menu['groups'] = self::groups(rows: $source['groups'], language: $language);
			}
		}

		return $menu;
	}//end resolveMenu()

	/**
	 * The sections that may be shown: a title and at least one valid app.
	 *
	 * @param array<mixed> $rows The `groups` list as written.
	 * @param string $language The request's language code.
	 *
	 * @return array<int, array<string, mixed>> The sections.
	 *
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md#requirement-a-token-set-may-group-the-apps-menu
	 */
	private static function groups(array $rows, string $language): array {
		$groups = [];
		foreach ($rows as $row) {
			$title = (is_array($row) === true) ? CopyValues::pick(value: ($row['title'] ?? null), language: $language) : null;
			$apps = (is_array($row) === true && is_array($row['apps'] ?? null) === true) ? self::apps(rows: $row['apps'], language: $language) : [];
			if ($title === null || $apps === []) {
				continue;
			}

			$group = ['title' => $title, 'apps' => $apps];
			$link = self::link(row: ($row['link'] ?? null), language: $language);
			if ($link !== null) {
				$group['link'] = $link;
			}

			$groups[] = $group;
		}

		return array_slice($groups, 0, self::MAX_GROUPS);
	}//end groups()

	/**
	 * The apps of a section: an app id (lower case, digits, underscores) and
	 * an optional description.
	 *
	 * @param array<mixed> $rows The `apps` list as written.
	 * @param string $language The request's language code.
	 *
	 * @return array<int, array{id: string, description?: string}> The apps.
	 *
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md#requirement-a-token-set-may-group-the-apps-menu
	 */
	private static function apps(array $rows, string $language): array {
		$apps = [];
		foreach ($rows as $row) {
			$id = (is_array($row) === true) ? ($row['id'] ?? null) : $row;
			if (is_string($id) === false || preg_match('/^[a-z0-9_]{1,64}$/', $id) !== 1) {
				continue;
			}

			$app = ['id' => $id];
			$description = (is_array($row) === true) ? CopyValues::pick(value: ($row['description'] ?? null), language: $language) : null;
			if ($description !== null) {
				$app['description'] = $description;
			}

			$apps[] = $app;
		}

		return array_slice($apps, 0, self::MAX_APPS);
	}//end apps()

	/**
	 * The link beside a section's heading: a label and an http(s) address or
	 * a path on this server, else null.
	 *
	 * @param mixed $row The `link` as written.
	 * @param string $language The request's language code.
	 *
	 * @return array{label: string, url: string}|null The link.
	 *
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md#requirement-a-token-set-may-group-the-apps-menu
	 */
	private static function link(mixed $row, string $language): ?array {
		if (is_array($row) === false) {
			return null;
		}

		$label = CopyValues::pick(value: ($row['label'] ?? null), language: $language);
		$url = CopyValues::pick(value: ($row['url'] ?? null), language: $language);
		if ($label === null || $url === null || CopyValues::isAddress(value: $url) === false) {
			return null;
		}

		return ['label' => $label, 'url' => $url];
	}//end link()

	/**
	 * Provide the sections and load the script, for a page whose header style
	 * resolves to `workplace`. Fails open.
	 *
	 * @param string $tokenSet The token set the page renders with.
	 * @param LayoutOptionsService $layoutOptions Resolves the page's layout stylesheets.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md#requirement-the-workplace-header-draws-the-apps-menu-of-the-board
	 */
	public function inject(string $tokenSet, LayoutOptionsService $layoutOptions): void {
		try {
			$sheets = $layoutOptions->stylesheets(tokenSet: $tokenSet);
			if (in_array(LayoutOptionsService::HEADER_WORKPLACE_STYLESHEET, $sheets, true) === false) {
				return;
			}

			$language = $this->l10nFactory->get(Application::APP_ID)->getLanguageCode();
			$this->initialState->provideInitialState(self::STATE_KEY, $this->resolveMenu(tokenSet: $tokenSet, language: $language));
			$this->emitScript();
		} catch (Throwable $e) {
			$this->logger->warning(
				'thematiq: the apps menu sections failed for this render; the menu keeps one grid.',
				['app' => Application::APP_ID, 'exception' => $e]
			);
		}
	}//end inject()

	/**
	 * Load js/app-menu.js. A seam, so unit tests need no Nextcloud bootstrap.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md#requirement-the-workplace-header-draws-the-apps-menu-of-the-board
	 */
	protected function emitScript(): void {
		\OCP\Util::addScript(application: Application::APP_ID, file: self::SCRIPT);
	}//end emitScript()
}//end class
