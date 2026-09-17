<?php

/**
 * The running instance's own stock theme, as --nldesign-* tokens.
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
 * @spec openspec/changes/component-playground/specs/nextcloud-variable-mapping/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Builds the `nextcloud` token set from the instance instead of from a file.
 *
 * WHY THIS EXISTS
 * ---------------
 * `css/tokens/nextcloud.css` is a hand-copied snapshot of Nextcloud's stock
 * values, and a snapshot of a moving target is wrong the moment the target
 * moves. Measured on 34.0.4 against the theme the server itself serves: of the
 * 29 values that map onto a live Nextcloud variable, 12 matched and 17 did
 * not. Several were not merely stale but INVERTED in role — Nextcloud turned
 * `--color-error` from a saturated fill into a pale background with its own
 * `-text` token, so the set that exists to look like stock was painting
 * NC29-era fills onto NC34. The file's own header records the previous round
 * of the same bug: it claimed `#0082c9` after NC29 moved to `#00679e`.
 *
 * A stylesheet cannot fix this by REFERENCING the live variable — writing
 * `--nldesign-color-primary: var(--color-primary)` closes a loop with
 * `overrides.css`, which already says `--color-primary: var(--nldesign-color-primary)`,
 * and CSS discards a custom property that depends on itself. So the values
 * have to be resolved before they reach the page, which is what this does.
 *
 * The source is `DefaultTheme::getCSSVariables()` — the same method that
 * produces `/apps/theming/theme/default.css`, so the tokens track whatever
 * version is installed with no file to maintain. Note that this is the
 * instance's stock theme, not Nextcloud's factory one: if an admin has set a
 * primary colour in core theming, that colour is what the browser is wearing
 * and therefore what this reports.
 *
 * Every failure path returns null so the caller can fall back to the shipped
 * file. A wrong stock theme is a cosmetic defect; no stock theme at all is a
 * blank page.
 *
 * @spec openspec/changes/component-playground/specs/nextcloud-variable-mapping/spec.md
 */
class StockTokensService {

	/**
	 * The theme class that computes Nextcloud's stock variables.
	 *
	 * Referenced as a string because `OCA\Theming` is another app's namespace:
	 * naming it in a `use` would make this file unparseable wherever the
	 * theming app is absent, and a missing class must degrade to the shipped
	 * snapshot rather than fatal.
	 */
	private const DEFAULT_THEME = '\\OCA\\Theming\\Themes\\DefaultTheme';

	/**
	 * Supplies the `--color-*` to `--nldesign-*` mapping, parsed from the
	 * stylesheet that actually makes the connection.
	 *
	 * @var TokenSetPreviewService
	 */
	private TokenSetPreviewService $sources;

	/**
	 * Records why a resolve failed, since the fallback is silent by design.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Resolved CSS for this request, or false when resolving already failed.
	 *
	 * The layer is asked for twice per request in the worst case — once by
	 * `inject()` and once by the stylesheet manifest — and the variable
	 * computation is not free.
	 *
	 * @var string|false|null
	 */
	private $memo = null;

	/**
	 * Survives the request, unlike the memo above.
	 *
	 * The `nextcloud` set is the DEFAULT set, and this layer is resolved on
	 * every page render including the login page — so without this, the work
	 * below (a stylesheet parse, plus the theming app's whole variable
	 * computation) runs once per request forever, on an instance that never
	 * chose a token set at all. Distributed rather than local, because every
	 * node of a cluster would otherwise recompute the same answer.
	 *
	 * @var ICache
	 */
	private ICache $cache;

	/**
	 * Reads the two values the cache key is made of.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * How long a resolved block is kept, in seconds.
	 *
	 * The key already contains everything that can change the answer, so this
	 * is a floor under unbounded growth rather than a correctness mechanism —
	 * an entry for a version nobody runs any more should not live forever.
	 */
	private const CACHE_TTL = 86400;

	/**
	 * Constructor.
	 *
	 * @param TokenSetPreviewService $sources      The variable-to-token mapping.
	 * @param LoggerInterface        $logger       Records resolve failures.
	 * @param ICacheFactory          $cacheFactory Creates the cross-request block cache.
	 * @param IConfig                $config       Reads the version and theming cachebuster.
	 *
	 * @spec openspec/changes/component-playground/specs/nextcloud-variable-mapping/spec.md
	 */
	public function __construct(
		TokenSetPreviewService $sources,
		LoggerInterface $logger,
		ICacheFactory $cacheFactory,
		IConfig $config,
	) {
		$this->sources = $sources;
		$this->logger = $logger;
		$this->cache = $cacheFactory->createDistributed(prefix: 'thematiq-stock-tokens');
		$this->config = $config;
	}//end __construct()

	/**
	 * The stock theme as one flat `:root { }` block of `--nldesign-*` tokens.
	 *
	 * @return string|null The stylesheet body, or null when the instance could
	 *                     not be read and the shipped file should be used.
	 *
	 * @spec openspec/changes/component-playground/specs/nextcloud-variable-mapping/spec.md
	 */
	public function getCss(): ?string {
		// The memo is false once a resolve has failed, a string once one has
		// succeeded, and null while neither has happened. Taken in that order
		// so what is returned second is a string and nothing else.
		if ($this->memo === false) {
			return null;
		}

		if ($this->memo !== null) {
			return $this->memo;
		}

		$key = $this->cacheKey();

		$cached = $this->cache->get($key);
		if (is_string($cached) === true) {
			$this->memo = $cached;

			return $cached;
		}

		$css = $this->build();

		// Only a success is kept. A failure is the theming app being absent or
		// throwing, and neither of those moves the cache key when it is fixed —
		// so a cached failure would outlive its cause and keep an instance on
		// the shipped snapshot after the thing that broke was put back.
		if ($css !== null) {
			$this->cache->set($key, $css, self::CACHE_TTL);
		}

		$this->memo = ($css ?? false);

		return $css;
	}//end getCss()

	/**
	 * Everything that can change the resolved block, in one string.
	 *
	 * The inputs are the installed Nextcloud version — the theme's variables
	 * are that release's code — and the theming app's cachebuster, which core
	 * bumps whenever an admin changes anything in its own theming settings.
	 * That is precisely the pair that makes this answer go stale, and both are
	 * cheap reads, so the cache invalidates itself rather than needing to be
	 * cleared by anything.
	 *
	 * @return string The cache key.
	 */
	private function cacheKey(): string {
		$version = $this->config->getSystemValueString('version', '0.0.0');
		$cachebuster = $this->config->getAppValue('theming', 'cachebuster', '0');

		return $version . ':' . $cachebuster;
	}//end cacheKey()

	/**
	 * Resolve the block, or null if any step cannot be completed.
	 *
	 * @return string|null The stylesheet body.
	 */
	private function build(): ?string {
		$stock = $this->flatten(vars: $this->stockVariables());
		if ($stock === []) {
			return null;
		}

		// The getTokenSources() map runs --color-X => --nldesign-Y, which is the
		// direction the cascade reads. Here the question is the other way
		// round — for this token, which Nextcloud variable holds its stock
		// value — so the map is inverted.
		//
		// The inverse is MANY-TO-ONE and the choice matters: four variables
		// read --nldesign-color-primary (overrides.css:24,27,35,37), and their
		// stock values are not the same — --color-primary is #00679e while
		// --color-primary-light-text is #00293f. Taking whichever happened to
		// come last gave the token the text colour, which is how the header
		// ends up painted in a shade nothing asked for.
		$candidates = [];
		foreach ($this->sources->getTokenSources() as $colorToken => $nldesignToken) {
			if (isset($stock[$colorToken]) === false) {
				continue;
			}

			$candidates[$nldesignToken][] = $colorToken;
		}

		$declarations = [];
		foreach ($candidates as $nldesignToken => $colorTokens) {
			$declarations[$nldesignToken] = $stock[$this->canonical(token: $nldesignToken, candidates: $colorTokens)];
		}

		if ($declarations === []) {
			return null;
		}

		ksort($declarations);

		$body = '';
		foreach ($declarations as $name => $value) {
			$body .= $name . ':' . $value . ';';
		}

		return ':root{' . $body . '}';
	}//end build()

	/**
	 * Which of the variables reading this token holds its defining value.
	 *
	 * A token's own name says what it is: `--nldesign-color-primary` is the
	 * primary colour, so `--color-primary` defines it and the three other
	 * variables that happen to read it (element, light-text,
	 * element-light-text) merely consume it. Where no variable carries the
	 * token's own name, the candidates are genuinely interchangeable, and the
	 * first in sorted order is taken so the output does not depend on the order
	 * declarations appear in `overrides.css`.
	 *
	 * @param string             $token      The `--nldesign-*` token name.
	 * @param array<int, string> $candidates The `--color-*` names reading it (non-empty).
	 *
	 * @return string The variable whose stock value the token should take.
	 */
	private function canonical(string $token, array $candidates): string {
		// Anchored, like the JS twin's `replace(/^--nldesign-/, '')`, rather
		// than a fixed-length chop. Every mapping in overrides.css targets a
		// --nldesign-* token today, so the two agree — but parseMappings()
		// captures any --* as the target, so a mapping onto a differently
		// prefixed token would leave this one silently eating eleven
		// characters of a name it does not own, while the JS half left it
		// alone. The two halves read one map in opposite directions and have
		// to agree about what a name means.
		$prefix = '--nldesign-';
		$stem = $token;
		if (str_starts_with($token, $prefix) === true) {
			$stem = substr($token, strlen($prefix));
		}

		$sameName = '--' . $stem;
		if (in_array($sameName, $candidates, true) === true) {
			return $sameName;
		}

		sort($candidates);

		return $candidates[0];
	}//end canonical()

	/**
	 * The theming app's default theme, out of the container.
	 *
	 * Its own method so a test can hand asInstance() a theme without a running
	 * server — the colour-per-user question this class has to get right is
	 * about which FIELD is read, and that is answerable with a stand-in.
	 *
	 * @return object The theme.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - another app's class, see the note
	 *                                         on DEFAULT_THEME: a constructor
	 *                                         type-hint would make this file
	 *                                         unloadable without the theming app.
	 *
	 * @spec openspec/changes/component-playground/specs/nextcloud-variable-mapping/spec.md
	 */
	protected function resolveTheme(): object {
		return \OCP\Server::get(self::DEFAULT_THEME);
	}//end resolveTheme()

	/**
	 * The theme as the INSTANCE wears it, not as the current user does.
	 *
	 * `DefaultTheme::__construct()` stores two colours: `defaultPrimaryColor`
	 * from `getDefaultColorPrimary()`, which reads the admin's app config only,
	 * and `primaryColor` from `getColorPrimary()`, which returns the signed-in
	 * user's own `primary_color` when they have set one — and user theming is
	 * ON by default (`isUserThemingDisabled()` defaults to false). Everything
	 * `generatePrimaryVariables()` emits is computed from the second one, which
	 * is all twelve `--color-primary*` variables `overrides.css` maps.
	 *
	 * That makes the raw resolve per USER, and this service's output is not:
	 * it is a token set, injected instance-wide, and — since the previous
	 * commit — cached under a key of the Nextcloud version and the app-level
	 * theming cachebuster. A personal colour moves neither. So the first user
	 * to warm that cache after setting one would have dressed every other user,
	 * and the anonymous login page, in their own colour until it expired.
	 *
	 * Swapping the admin colour in makes the resolve genuinely instance-wide,
	 * which is what `nextcloud-variable-mapping` says this set is, and what
	 * makes the existing cache key correct rather than merely cheap. Keying the
	 * cache per user instead would be wrong for the login page — which has no
	 * user — and would multiply the entries by the account count.
	 *
	 * Only the primary family needs this. The other user-dependent variables
	 * are the four `generateUserBackgroundVariables()` emits, and `overrides.css`
	 * maps none of them, so none reaches a token.
	 *
	 * A CLONE, because the theme comes from the container and is the same
	 * instance the page around us is being rendered with.
	 *
	 * @param object $theme The theming app's default theme.
	 *
	 * @return object A theme that resolves the admin's colours.
	 *
	 * @throws RuntimeException When the theme does not carry the admin colour,
	 *                           so a user-dependent block can never be cached.
	 */
	private function asInstance(object $theme): object {
		if (
			property_exists($theme, 'primaryColor') === false
			|| property_exists($theme, 'defaultPrimaryColor') === false
		) {
			// Refused rather than resolved: this is the check that keeps one
			// account's colour out of everyone else's page, so a theming app
			// whose shape no longer allows it must reach the shipped file, not
			// the user's own theme.
			throw new RuntimeException(
				'the theming app no longer exposes an admin-level primary colour'
			);
		}

		$instance = clone $theme;
		$instance->primaryColor = $instance->defaultPrimaryColor;

		return $instance;
	}//end asInstance()

	/**
	 * Replace every `var()` reference with the literal it resolves to, and
	 * drop what cannot be resolved.
	 *
	 * Emitting a reference would reopen the cycle this service exists to
	 * avoid: Nextcloud declares `--color-border-error: var(--color-element-error)`,
	 * and passing that through unchanged would make an `--nldesign-*` token
	 * point back at a variable `overrides.css` may itself be rewriting. Only
	 * literals leave here.
	 *
	 * @param array<string, string> $vars The raw stock variable map.
	 *
	 * @return array<string, string> Literal values only.
	 */
	private function flatten(array $vars): array {
		$flat = [];

		foreach ($vars as $name => $value) {
			$value = trim((string)$value);
			$seen = 0;

			// A chain longer than this is either pathological or a loop; either
			// way the token is dropped rather than guessed at.
			while (preg_match('/^var\(\s*(--[\w-]+)\s*\)$/', $value, $match) === 1 && $seen < 5) {
				if (isset($vars[$match[1]]) === false) {
					$value = '';
					break;
				}

				$value = trim((string)$vars[$match[1]]);
				$seen++;
			}

			// Anything still carrying a var() is a composite the cascade has to
			// resolve in the browser (gradients, color-mix), and those cannot be
			// frozen into a token without freezing the wrong thing.
			if ($value === '' || str_contains($value, 'var(') === true) {
				continue;
			}

			$flat[$name] = $value;
		}

		return $flat;
	}//end flatten()

	/**
	 * Nextcloud's stock variables, as the theming app computes them.
	 *
	 * Indirected for the same reason the emit helpers in `CssInjectionService`
	 * are: it reaches into another app through the container, and a test can
	 * stand in for that only if it is overridable.
	 *
	 * @return array<string, string> Variable name => declared value.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - another app's class, see the note
	 *                                         on DEFAULT_THEME: a constructor
	 *                                         type-hint would make this file
	 *                                         unloadable without the theming app.
	 *
	 * @spec openspec/changes/component-playground/specs/nextcloud-variable-mapping/spec.md
	 */
	protected function stockVariables(): array {
		try {
			return $this->asInstance(theme: $this->resolveTheme())->getCSSVariables();
		} catch (Throwable $e) {
			$this->logger->debug(
				'thematiq: the stock theme could not be read from the instance, '
				. 'so the nextcloud token set falls back to its shipped file.',
				['exception' => $e]
			);

			return [];
		}
	}//end stockVariables()
}//end class
