<?php

/**
 * Thematiq Login Copy Service.
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
 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
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
 * The words on the login page of the light workplace, per token set.
 *
 * Nextcloud's login page says "Log in to {name}" and has no subtitle, and its
 * footer joins the organisation name and the slogan with a dash. The NcLogin
 * boards say "Inloggen bij de werkplek", name who the page is for, and close
 * with a line of their own. None of that is a Nextcloud setting, so a token
 * set carries it in a `login` block in token-sets.json, an administrator may
 * override any part of it in the app config key `login_copy` (the same JSON
 * shape), and js/login-copy.js writes it into the page.
 *
 * Every value is either one string, used in every language, or a map from a
 * language code to a string. A language the map does not name keeps
 * Nextcloud's own words, so a Dutch set never puts Dutch on an English page.
 *
 * Presentation, never a dependency: only the light workplace loads it, the
 * script writes text and never markup, and a failure is logged and leaves
 * Nextcloud's own login page.
 *
 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
 */
class LoginCopyService {

	/**
	 * The initial state key js/login-copy.js reads.
	 *
	 * @var string
	 */
	public const STATE_KEY = 'login-copy';

	/**
	 * The script under `js/`, without the extension.
	 *
	 * @var string
	 */
	public const SCRIPT = 'login-copy';

	/**
	 * The app config key an administrator overrides the set's copy in.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'login_copy';

	/**
	 * The keys a `login` block may carry, as written in token-sets.json.
	 *
	 * @var array<int, string>
	 */
	public const KEYS = [
		'title',
		'subtitle',
		'sso_label',
		'username_label',
		'divider',
		'footer',
		'footer_hint',
		'footer_link_label',
		'footer_link_url',
	];

	/**
	 * The longest value accepted; anything longer is dropped, not cut.
	 *
	 * @var int
	 */
	public const MAX_LENGTH = 300;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config Reads the administrator's override.
	 * @param DesignSystemService $designSystemService Reads a set's manifest entry.
	 * @param IInitialState $initialState The initial state channel.
	 * @param IFactory $l10nFactory The language of this request.
	 * @param LoggerInterface $logger Records a swallowed failure.
	 * @param GroupThemingService|null $groupTheming Resolves the login page's token set; null in unit tests of {@see self::inject()}.
	 * @param LayoutOptionsService|null $layoutOptions Resolves the login page's layout; null in unit tests of {@see self::inject()}.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly DesignSystemService $designSystemService,
		private readonly IInitialState $initialState,
		private readonly IFactory $l10nFactory,
		private readonly LoggerInterface $logger,
		private readonly ?GroupThemingService $groupTheming = null,
		private readonly ?LayoutOptionsService $layoutOptions = null,
	) {
	}//end __construct()

	/**
	 * The login page's entry: resolve the set the page renders with (the
	 * per-group resolution, no app brand: login is never an app page) and
	 * {@see self::inject()} for it. Fails open.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-the-light-workplace-draws-the-login-card-of-the-board
	 */
	public function injectForLogin(): void {
		if ($this->groupTheming === null || $this->layoutOptions === null) {
			return;
		}

		try {
			$tokenSet = $this->groupTheming->resolveTokenSetForRequest(appBrandSet: null);
		} catch (Throwable $e) {
			$this->logger->warning(
				'thematiq: the login copy could not resolve the token set; the login page keeps Nextcloud\'s words.',
				['app' => Application::APP_ID, 'exception' => $e]
			);
			return;
		}

		$this->inject(tokenSet: $tokenSet, layoutOptions: $this->layoutOptions);
	}//end injectForLogin()

	/**
	 * The copy for a page rendered with this token set, in this language.
	 *
	 * The set's `login` block first, then the administrator's override on top
	 * of it, key by key. Values that are not text, are empty, are too long,
	 * or (for the link) are not a web address are left out.
	 *
	 * @param string $tokenSet The token set the page renders with.
	 * @param string $language The request's language code, e.g. `nl` or `en_GB`.
	 *
	 * @return array<string, string> The resolved copy, keyed in camel case (`ssoLabel`).
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-login-copy
	 */
	public function resolveCopy(string $tokenSet, string $language): array {
		$meta = $this->designSystemService->getTokenSetMeta(tokenSetId: $tokenSet);
		$fromSet = ($meta['login'] ?? null);
		$fromAdmin = json_decode($this->config->getAppValue(Application::APP_ID, self::CONFIG_KEY, ''), true);

		$copy = [];
		foreach ([$fromSet, $fromAdmin] as $source) {
			if (is_array($source) === false) {
				continue;
			}

			foreach (self::KEYS as $key) {
				$value = self::pick(value: ($source[$key] ?? null), language: $language);
				if ($value !== null && self::acceptable(key: $key, value: $value) === true) {
					$copy[self::camel(key: $key)] = $value;
				}
			}
		}

		return $copy;
	}//end resolveCopy()

	/**
	 * Provide the copy and load the script, for a login page drawn by the
	 * light workplace. Fails open.
	 *
	 * The script also places the alternative logins (the SSO button) above
	 * the form with a divider, so it loads with the light layout even when
	 * the set names no copy; the divider then reads this app's "or".
	 *
	 * @param string $tokenSet The token set the page renders with.
	 * @param LayoutOptionsService $layoutOptions Resolves the page's layout.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-the-light-workplace-draws-the-login-card-of-the-board
	 */
	public function inject(string $tokenSet, LayoutOptionsService $layoutOptions): void {
		try {
			if ($layoutOptions->workplaceLayout(tokenSet: $tokenSet) !== LayoutOptionsService::LAYOUT_LIGHT) {
				return;
			}

			$l10n = $this->l10nFactory->get(Application::APP_ID);
			$copy = $this->resolveCopy(tokenSet: $tokenSet, language: $l10n->getLanguageCode());
			$copy['divider'] = ($copy['divider'] ?? $l10n->t('or'));

			$this->initialState->provideInitialState(self::STATE_KEY, $copy);
			$this->emitScript();
		} catch (Throwable $e) {
			$this->logger->warning(
				'thematiq: the login copy failed for this render; the login page keeps Nextcloud\'s words.',
				['app' => Application::APP_ID, 'exception' => $e]
			);
		}//end try
	}//end inject()

	/**
	 * The string a value stands for in a language: the value itself, or the
	 * map's entry for the language, else for its primary subtag (`nl` for
	 * `nl_NL`). Null when there is none.
	 *
	 * @param mixed $value A string or a map from language code to string.
	 * @param string $language The request's language code.
	 *
	 * @return string|null The trimmed string, or null.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-login-copy
	 */
	private static function pick(mixed $value, string $language): ?string {
		if (is_array($value) === true) {
			$primary = strtolower((string)preg_replace('/[-_].*$/', '', $language));
			$value = ($value[$language] ?? $value[$primary] ?? null);
		}

		if (is_string($value) === false) {
			return null;
		}

		$value = trim($value);
		if ($value === '') {
			return null;
		}

		return $value;
	}//end pick()

	/**
	 * Whether a value may be shown: not longer than {@see self::MAX_LENGTH},
	 * and for the link an absolute http(s) address or a path on this server.
	 *
	 * @param string $key The key, as written in token-sets.json.
	 * @param string $value The trimmed value.
	 *
	 * @return bool True when it may be shown.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-login-copy
	 */
	private static function acceptable(string $key, string $value): bool {
		if (mb_strlen($value) > self::MAX_LENGTH) {
			return false;
		}

		if ($key !== 'footer_link_url') {
			return true;
		}

		return (preg_match('#^(https?://[^\s]+|/(?!/)[^\s]*)$#i', $value) === 1);
	}//end acceptable()

	/**
	 * The camel-case name a key goes by in the initial state.
	 *
	 * @param string $key The key, as written in token-sets.json.
	 *
	 * @return string The camel-case name.
	 */
	private static function camel(string $key): string {
		return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
	}//end camel()

	/**
	 * Load js/login-copy.js. A seam, so unit tests need no Nextcloud bootstrap.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - \OCP\Util is the Nextcloud API for asset injection.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md#requirement-the-light-workplace-draws-the-login-card-of-the-board
	 */
	protected function emitScript(): void {
		\OCP\Util::addScript(application: Application::APP_ID, file: self::SCRIPT);
	}//end emitScript()
}//end class
