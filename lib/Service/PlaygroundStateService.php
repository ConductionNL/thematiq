<?php

/**
 * Component playground state.
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
 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
use OCP\App\IAppManager;
use OCP\IL10N;
use Throwable;

/**
 * Everything `js/playground.js` needs at boot, gathered in one place.
 *
 * The settings panel is already a long constructor, and the playground needs
 * three things it has no other reason to know about: the component inventory
 * file, the converter's reason vocabulary and the token values of the active
 * set. They are assembled here so `Settings\Admin` takes one collaborator
 * instead of three, and so the assembly can be tested without rendering a
 * settings form.
 *
 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
 */
class PlaygroundStateService {

	/**
	 * Resolves the app directory, so the inventory is read from the installed
	 * app rather than from a path guessed relative to this file.
	 *
	 * @var IAppManager
	 */
	private IAppManager $appManager;

	/**
	 * Resolves the token values and the variable-to-token map.
	 *
	 * @var TokenSetPreviewService
	 */
	private TokenSetPreviewService $previewValues;

	/**
	 * Owns the reason-code vocabulary the playground's token-less rows are
	 * worded from.
	 *
	 * @var TokenSetConverterService
	 */
	private TokenSetConverterService $converter;

	/**
	 * The running instance's own stock theme, as tokens.
	 *
	 * Only consulted for the stock set, whose values live in the instance rather
	 * than in a file — see {@see getExportTokens()}.
	 *
	 * @var StockTokensService
	 */
	private StockTokensService $stockTokens;

	/**
	 * Translates the inventory's own chrome: titles, subtitles, what each
	 * token paints and why a look has no token. See {@see translateInventory()}.
	 *
	 * @var IL10N
	 */
	private IL10N $l10n;

	/**
	 * Constructor.
	 *
	 * @param IAppManager              $appManager    Resolves the app directory.
	 * @param TokenSetPreviewService   $previewValues Resolves token values and sources.
	 * @param TokenSetConverterService $converter     The conversion reason vocabulary.
	 * @param StockTokensService       $stockTokens   The instance's own stock theme.
	 * @param IL10N                    $l10n          The app's translations.
	 * @param RuntimeFileStore|null    $store         Where uploaded sets and their dark files live.
	 * @param SetFileReader            $files         Reads a set file from the release or the store.
	 */
	public function __construct(
		IAppManager $appManager,
		TokenSetPreviewService $previewValues,
		TokenSetConverterService $converter,
		StockTokensService $stockTokens,
		IL10N $l10n,
		private ?RuntimeFileStore $store = null,
		private SetFileReader $files = new SetFileReader(),
	) {
		$this->appManager = $appManager;
		$this->previewValues = $previewValues;
		$this->converter = $converter;
		$this->stockTokens = $stockTokens;
		$this->l10n = $l10n;
	}//end __construct()

	/**
	 * The base an export writes: what the active set DECLARES, nothing else.
	 *
	 * Deliberately not `getResolvedTokens()`, which is what the instrument draws
	 * with. That map merges `css/systems/nldesign/defaults.css` UNDER the set —
	 * 200 tokens, 115 of them component tokens, all carrying Rijkshuisstijl
	 * values — so exporting from it wrote a theme full of decisions the admin
	 * never made and the set never held. `getDeclaredTokens()`'s own docblock
	 * says as much: a merged map "answers yes for every token, because the
	 * defaults declare them all".
	 *
	 * The stock set is the exception, and it is the whole reason this is a
	 * method rather than one more call in the list. Its values are not in a file
	 * — `css/tokens/nextcloud.css` is a snapshot of one Nextcloud version, kept
	 * as a fallback — they are in the running instance. Saving "the Nextcloud
	 * theme plus my change" has to write what THIS server is wearing, so the
	 * stock resolver answers for it and the file answers only if that fails.
	 *
	 * @param string $tokenSetId The token set the page is wearing.
	 *
	 * @return array<string, string> Map of `--nldesign-*` token name => declared value.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	private function getExportTokens(string $tokenSetId): array {
		if ($tokenSetId !== CssInjectionService::STOCK_TOKEN_SET) {
			return $this->previewValues->getDeclaredTokens(tokenSetId: $tokenSetId);
		}

		$stock = $this->stockTokens->getTokens();
		if ($stock !== []) {
			return $stock;
		}

		return $this->previewValues->getDeclaredTokens(tokenSetId: $tokenSetId);
	}//end getExportTokens()

	/**
	 * The initial-state keys the instrument reads.
	 *
	 * @param string $tokenSetId The token set the page is wearing.
	 *
	 * @return array<string, mixed> Map of initial-state key to value.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	public function getInitialState(string $tokenSetId): array {
		return [
			'playgroundInventory' => $this->getInventory(),
			'playgroundReasons' => $this->getReasons(),
			'playgroundTokens' => $this->previewValues->getResolvedTokens(tokenSetId: $tokenSetId),
			'playgroundExportTokens' => $this->getExportTokens(tokenSetId: $tokenSetId),
			'playgroundTokenSources' => $this->previewValues->getTokenSources(),
			'playgroundVersion' => $this->getServerMajor(),
			// The set these values came from, so an export is named after the
			// set it actually contains. Published rather than re-derived in the
			// script, because a session preview decides it and the script has
			// no business knowing that rule twice.
			'playgroundSet' => $tokenSetId,
			// The set's dark values, for the light and dark switch of "Your component".
			'playgroundDarkTokens' => $this->getDarkTokens(tokenSetId: $tokenSetId),
		];
	}//end getInitialState()

	/**
	 * The `--nldesign-*` values the set's generated dark stylesheet gives a user who chose
	 * the dark theme: the `body[data-theme-dark]` block of `css/tokens/dark/{set}.css`.
	 *
	 * @param string $tokenSetId The token set.
	 *
	 * @return array<string, string> Token => dark value; empty when the set has no dark file.
	 *
	 * @spec openspec/specs/own-component-preview/spec.md#requirement-the-frame-can-show-the-dark-theme
	 */
	private function getDarkTokens(string $tokenSetId): array {
		if (preg_match('/^[a-z0-9-]+$/', $tokenSetId) !== 1) {
			return [];
		}

		// An uploaded set's dark file lives in the runtime store, a shipped one in the release.
		$css = (string)$this->files->read(
			appPath: $this->appManager->getAppPath(Application::APP_ID),
			name: 'css/tokens/dark/' . $tokenSetId . '.css',
			store: $this->store
		);
		if (preg_match('/body\[data-theme-dark\][^{]*\{([^}]*)\}/', $css, $block) !== 1) {
			return [];
		}

		preg_match_all('/(--nldesign-[a-z0-9-]+)\s*:\s*([^;]+);/', $block[1], $matches, PREG_SET_ORDER);
		$tokens = [];
		foreach ($matches as $match) {
			$tokens[$match[1]] = trim((string)preg_replace('/\s*!important\s*$/', '', $match[2]));
		}

		return $tokens;
	}//end getDarkTokens()

	/**
	 * The major Nextcloud version this instance is running.
	 *
	 * The header is the one component whose MARKUP, not just its colours,
	 * differs between the versions this app supports: 34 replaced the row of
	 * app entries with a waffle, a popover grid and a current-app button, and
	 * deleted `AppMenuEntry.vue` outright. The instrument draws every supported
	 * version so an admin can see what an upgrade does to their theme before
	 * they take it — and this is the one it opens on, because the version you
	 * are running is the one you are asking about first.
	 *
	 * Resolved through the deprecated array-returning `\OCP\Util::getVersion()`
	 * for the same reason `ComplianceReportService` does: a type-hinted
	 * `\OCP\ServerVersion` constructor dependency is a DI problem on older
	 * servers, and this value is read once to pick a default.
	 *
	 * @return int The major version, or 0 when it cannot be read.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - see rationale above.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	protected function getServerMajor(): int {
		try {
			$version = \OCP\Util::getVersion();

			return (int)($version[0] ?? 0);
		} catch (Throwable $e) {
			// Only reachable without a full Nextcloud bootstrap, which in
			// practice means an isolated unit test. Zero is not a version the
			// switch offers, so the instrument falls back to the newest one it
			// knows — a working switch either way, and nothing worth a
			// dependency on a logger to say.
			return 0;
		}
	}//end getServerMajor()

	/**
	 * The component inventory that decides which chips the instrument offers
	 * and which tokens each one filters the editor down to.
	 *
	 * A missing or malformed file yields an empty component list rather than an
	 * exception: the instrument then simply does not build, and the settings
	 * panel it is built into keeps working, which is the failure an admin can
	 * still do their job through.
	 *
	 * @return array<string, mixed> The decoded inventory, or an empty one.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	public function getInventory(): array {
		$path = $this->appManager->getAppPath(Application::APP_ID) . '/js/playground/components.json';

		$raw = false;
		if (is_file($path) === true) {
			$raw = file_get_contents($path);
		}

		if ($raw === false) {
			return ['version' => 0, 'tabs' => [], 'components' => []];
		}

		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false || is_array($decoded['components'] ?? null) === false) {
			return ['version' => 0, 'tabs' => [], 'components' => []];
		}

		return $this->translateInventory(inventory: $decoded);
	}//end getInventory()

	/**
	 * The inventory with its chrome in the admin's language.
	 *
	 * The panel's own words live in this data file rather than in `t()` calls:
	 * a component's title and subtitle, what each token paints, what a look
	 * with no token is and why, and what differs per Nextcloud version. The
	 * frontend renders them as they arrive, so they are translated here, from
	 * the same `thematiq` catalogue the rest of the panel reads. The keys are
	 * the English strings in the file; tests/Unit/Service/PlaygroundStateServiceTest.php
	 * fails on any of them that has no Dutch entry in l10n/nl.json.
	 *
	 * Ids, class names, token names and reason codes are data and stay as
	 * they are.
	 *
	 * @param array<string, mixed> $inventory The decoded inventory.
	 *
	 * @return array<string, mixed> The same inventory, its chrome translated.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	private function translateInventory(array $inventory): array {
		foreach ($inventory['components'] as $index => $component) {
			if (is_array($component) === false) {
				continue;
			}

			$component = $this->translateFields(entry: $component, fields: ['title', 'subtitle']);
			foreach (['tokens' => ['paints'], 'fixed' => ['what', 'why'], 'versionNotes' => ['text']] as $list => $fields) {
				if (is_array($component[$list] ?? null) === false) {
					continue;
				}

				foreach ($component[$list] as $at => $entry) {
					if (is_array($entry) === true) {
						$component[$list][$at] = $this->translateFields(entry: $entry, fields: $fields);
					}
				}
			}

			$inventory['components'][$index] = $component;
		}

		return $inventory;
	}//end translateInventory()

	/**
	 * Translate the named string fields of one entry, leaving the rest alone.
	 *
	 * @param array<string, mixed> $entry  One inventory entry.
	 * @param array<int, string>   $fields The fields that are chrome.
	 *
	 * @return array<string, mixed> The entry, those fields translated.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	private function translateFields(array $entry, array $fields): array {
		foreach ($fields as $field) {
			if (is_string($entry[$field] ?? null) === true && $entry[$field] !== '') {
				$entry[$field] = $this->l10n->t($entry[$field]);
			}
		}

		return $entry;
	}//end translateFields()

	/**
	 * The reason-code vocabulary, so a row with no token and a token an import
	 * had to skip are explained in the same words.
	 *
	 * @return array<string, string> Map of reason code to sentence.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	public function getReasons(): array {
		try {
			// Translated here, for the panel: the converter's own callers write
			// the English sentence into import reports.
			$reasons = $this->converter->getReasons();
			foreach ($reasons as $code => $reason) {
				if (is_string($reason) === true) {
					$reasons[$code] = $this->l10n->t($reason);
				}
			}

			return $reasons;
		} catch (Throwable $e) {
			// The mapping table is the converter's own dependency. Without it
			// every row still renders; only the explanatory sentence falls back
			// to the one the inventory carries itself.
			return [];
		}
	}//end getReasons()
}//end class
