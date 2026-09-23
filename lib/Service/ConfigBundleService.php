<?php

/**
 * NL Design Configuration Bundle Service.
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
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\Exception\FooterValidationException;
use OCP\App\IAppManager;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Exports/imports the COMPLETE nldesign configuration as a single JSON
 * bundle, for OTAP (dev/test/acceptatie/productie) promotion between
 * environments.
 *
 * Bundle v1 covers every instance-wide nldesign configuration value present
 * in the codebase at the time this service was written — a superset of the
 * six parts named in the original `config-portability` proposal, because
 * three more configuration surfaces (`email-theming` footer keys,
 * `custom-fonts`, `upstream-freshness`) shipped in this app between when
 * that proposal was authored and when this service was built. Per the
 * proposal's ratchet rule ("every future instance-wide nldesign
 * configuration value MUST be added to the bundle in the same change that
 * introduces the value"), all three join bundleVersion 1 rather than
 * shipping a v1 that is already incomplete on day one:
 * - `config.upstreamFreshnessEnabled` ({@see UpstreamFreshnessService::isEnabled()}).
 * - `emailFooter` (`email_footer_org_name`/`_accessibility_url`/`_privacy_url`,
 *   {@see EmailThemingService::getFooterConfig()}). The `mail_template_class`
 *   enable/disable toggle itself is a system config value (config.php), not
 *   an app value, and is deliberately NOT in the bundle — same rationale as
 *   excluding Nextcloud core `theming` app values.
 * - `customFonts` — metadata only (id, name, role, size, uploadedAt, rev).
 *   The `.woff2` binaries are NOT embedded: at up to 2 MB × 20 fonts, base64
 *   would blow well past the controller's 256 KB bundle-upload cap
 *   (`token-import-export`-consistent) and bloat every occ export. The
 *   manifest is exported for operator visibility only and is NEVER applied
 *   on import (a manifest entry with no backing file would make FontService
 *   serve broken `@font-face` URLs) — the admin must re-upload fonts by hand
 * on the target environment. This is stated in the bundle's `customFonts`
 *   section on both export and every import result.
 *
 * Import is validate-everything-first, then write: {@see import()} phase 1
 * validates every section using the EXISTING validators
 * (CustomTokenSetValidator, the CustomOverridesService/TokenRegistry
 * editable-token whitelist, TokenSetService::isValidTokenSet(),
 * EmailThemingService::validateFooterConfig()) and aborts the ENTIRE import
 * with zero writes on any hard failure. Phase 2 (skipped under `--dry-run`)
 * applies every section and reports per-section results.
 *
 * @spec openspec/specs/config-portability/spec.md
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) - This is a validate-every-section-first,
 * then-apply-every-section orchestrator over five independent configuration surfaces (token
 * set/toggles/exclusions, email footer, overrides CSS, custom token sets, upstream-freshness
 * toggle); each surface's validate/apply pair is its own small method, so the complexity is
 * the enumerated surface count, not tangled branching within any one method.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)   - By design this is the ONE service that
 * reuses every existing per-surface validator/service (CustomTokenSetValidator,
 * CustomOverridesService, TokenSetService, AppThemingService, EmailThemingService, FontService,
 * UpstreamFreshnessService) rather than reimplementing any of their validation rules — the
 * `config-portability` proposal requires exactly this reuse, so the coupling is the point.
 * @SuppressWarnings(PHPMD.LongVariable)             - $customTokenSetService/$customTokenSetValidator/
 * $upstreamFreshnessEnabled are the SAME precise names used for these collaborators/bundle
 * fields throughout the rest of the codebase; shortening them here would reduce cross-file
 * consistency for a purely cosmetic 20-character sniff threshold.
 */
class ConfigBundleService {

	/**
	 * The bundle envelope `format` value.
	 *
	 * @var string
	 */
	public const FORMAT = 'nldesign-config-bundle';

	/**
	 * The current bundle schema version.
	 *
	 * @var int
	 */
	public const BUNDLE_VERSION = 1;

	/**
	 * The application configuration service.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The app manager, for the informational app version and the app path.
	 *
	 * @var IAppManager
	 */
	private IAppManager $appManager;

	/**
	 * The token set discovery/validation service.
	 *
	 * @var TokenSetService
	 */
	private TokenSetService $tokenSetService;

	/**
	 * The custom overrides (custom-overrides.css) read/write service.
	 *
	 * @var CustomOverridesService
	 */
	private CustomOverridesService $overridesService;

	/**
	 * The custom token set storage/lifecycle service.
	 *
	 * @var CustomTokenSetService
	 */
	private CustomTokenSetService $customTokenSetService;

	/**
	 * The custom token set declaration validator.
	 *
	 * @var CustomTokenSetValidator
	 */
	private CustomTokenSetValidator $customTokenSetValidator;

	/**
	 * The per-app theming exclusion list service.
	 *
	 * @var AppThemingService
	 */
	private AppThemingService $appThemingService;

	/**
	 * The CSS parser service.
	 *
	 * @var CssParserService
	 */
	private CssParserService $cssParser;

	/**
	 * The email theming footer service.
	 *
	 * @var EmailThemingService
	 */
	private EmailThemingService $emailThemingService;

	/**
	 * The custom font metadata service (manifest export only, see class docblock).
	 *
	 * @var FontService
	 */
	private FontService $fontService;

	/**
	 * The upstream token freshness opt-in toggle service.
	 *
	 * @var UpstreamFreshnessService
	 */
	private UpstreamFreshnessService $freshnessService;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param IAppManager $appManager The app manager.
	 * @param TokenSetService $tokenSetService The token set service.
	 * @param CustomOverridesService $overridesService The custom overrides service.
	 * @param CustomTokenSetService $customTokenSetService The custom token set service.
	 * @param CustomTokenSetValidator $customTokenSetValidator The custom token set validator.
	 * @param AppThemingService $appThemingService The per-app theming service.
	 * @param CssParserService $cssParser The CSS parser service.
	 * @param EmailThemingService $emailThemingService The email theming footer service.
	 * @param FontService $fontService The custom font metadata service.
	 * @param UpstreamFreshnessService $freshnessService The upstream freshness toggle service.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveParameterList) - Each dependency backs exactly one bundle
	 * section's EXISTING validator/service (config-portability's core reuse requirement); NC's DI
	 * container supplies them, and a synthetic parameter-object split would not reduce the real
	 * one-collaborator-per-section coupling.
	 */
	public function __construct(
		IConfig $config,
		IAppManager $appManager,
		TokenSetService $tokenSetService,
		CustomOverridesService $overridesService,
		CustomTokenSetService $customTokenSetService,
		CustomTokenSetValidator $customTokenSetValidator,
		AppThemingService $appThemingService,
		CssParserService $cssParser,
		EmailThemingService $emailThemingService,
		FontService $fontService,
		UpstreamFreshnessService $freshnessService,
		LoggerInterface $logger,
	) {
		$this->config = $config;
		$this->appManager = $appManager;
		$this->tokenSetService = $tokenSetService;
		$this->overridesService = $overridesService;
		$this->customTokenSetService = $customTokenSetService;
		$this->customTokenSetValidator = $customTokenSetValidator;
		$this->appThemingService = $appThemingService;
		$this->cssParser = $cssParser;
		$this->emailThemingService = $emailThemingService;
		$this->fontService = $fontService;
		$this->freshnessService = $freshnessService;
		$this->logger = $logger;
	}//end __construct()

	/**
	 * Export the complete nldesign configuration as a bundle array.
	 *
	 * @return array<string, mixed> The v1 bundle.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	public function export(): array {
		return [
			'format' => self::FORMAT,
			'bundleVersion' => self::BUNDLE_VERSION,
			'exportedAt' => gmdate('c'),
			'app' => [
				'id' => Application::APP_ID,
				'version' => $this->appManager->getAppVersion(Application::APP_ID),
			],
			'config' => [
				'tokenSet' => $this->config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud'),
				'hideSlogan' => ($this->config->getAppValue(Application::APP_ID, 'hide_slogan', '0') === '1'),
				'showMenuLabels' => ($this->config->getAppValue(Application::APP_ID, 'show_menu_labels', '0') === '1'),
				'primaryDrivesComponents' => ($this->config->getAppValue(Application::APP_ID, 'primary_drives_components', '0') === '1'),
				'disabledApps' => $this->appThemingService->getDisabledApps(),
				'upstreamFreshnessEnabled' => $this->freshnessService->isEnabled(),
			],
			'emailFooter' => $this->emailThemingService->getFooterConfig(),
			'customOverridesCss' => $this->overridesService->getRawContent(),
			'customTokenSets' => $this->exportCustomTokenSets(),
			'customFonts' => [
				'binariesIncluded' => false,
				'note' => 'Font binaries are not embedded in the bundle (see config-portability spec). '
					. 'Metadata only — re-upload font files by hand on the target environment.',
				'manifest' => $this->fontService->getManifest(),
			],
		];
	}//end export()

	/**
	 * Build the `customTokenSets` export list.
	 *
	 * @return array<int, array<string, mixed>> One entry per custom set: id + the full manifest entry + css.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function exportCustomTokenSets(): array {
		$result = [];
		foreach ($this->customTokenSetService->list() as $set) {
			$id = (string)($set['id'] ?? '');
			$css = $this->customTokenSetService->getRawContent(id: $id);
			if ($css === null) {
				continue;
			}

			$entry = $set;
			$entry['css'] = $css;
			$result[] = $entry;
		}

		return $result;
	}//end exportCustomTokenSets()

	/**
	 * Import a bundle: phase 1 validates every section, phase 2 (unless
	 * `$dryRun`) applies them. Any phase-1 failure aborts with zero writes.
	 *
	 * @param array<string, mixed> $bundle The decoded bundle.
	 * @param bool $dryRun When true, validate only — never write.
	 *
	 * @return array<string, mixed> `{valid, dryRun, applied, sections?, errors?}`.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) - `$dryRun` IS the spec-required distinction
	 * between "validate only" and "validate then apply" (`occ nldesign:config:import --dry-run`);
	 * splitting into two public methods would duplicate phase 1 rather than remove a real flag.
	 */
	public function import(array $bundle, bool $dryRun = false): array {
		$validation = $this->validate(bundle: $bundle);

		if ($validation['valid'] === false) {
			$this->logger->warning(
				'NL Design configuration bundle import rejected: ' . count($validation['errors']) . ' section error(s).'
			);

			return [
				'valid' => false,
				'dryRun' => $dryRun,
				'applied' => false,
				'errors' => $validation['errors'],
			];
		}

		$sections = $this->buildSectionSummary(resolved: $validation['resolved']);

		if ($dryRun === true) {
			return [
				'valid' => true,
				'dryRun' => true,
				'applied' => false,
				'sections' => $sections,
			];
		}

		$this->apply(resolved: $validation['resolved']);

		$this->logger->info('NL Design configuration bundle imported successfully.');

		return [
			'valid' => true,
			'dryRun' => false,
			'applied' => true,
			'sections' => $sections,
		];
	}//end import()

	/**
	 * Phase 1: validate every section of a bundle.
	 *
	 * @param array<string, mixed> $bundle The decoded bundle.
	 *
	 * `resolved` carries the normalised, ready-to-apply data for phase 2 (only meaningful when `valid` is
	 * true).
	 *
	 * @return array{valid: bool, errors: array<int, array<string, mixed>>, resolved: array<string, mixed>}
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validate(array $bundle): array {
		$errors = [];
		$resolved = [];

		$this->validateEnvelope(bundle: $bundle, errors: $errors);

		$resolved['config'] = $this->validateConfigSection(bundle: $bundle, errors: $errors);
		$resolved['emailFooter'] = $this->validateEmailFooterSection(bundle: $bundle, errors: $errors);
		$resolved['customOverrides'] = $this->validateOverridesSection(bundle: $bundle, errors: $errors);
		$resolved['customTokenSets'] = $this->validateCustomTokenSetsSection(bundle: $bundle, errors: $errors);
		$resolved['customFonts'] = $this->validateFontsSection(bundle: $bundle, errors: $errors);

		// The active token set must resolve against either the filesystem
		// (shipped or already-installed custom) OR one of THIS bundle's own
		// custom sets — only meaningful once customTokenSets parsed cleanly.
		$this->validateTokenSetResolution(resolved: $resolved, errors: $errors);

		return [
			'valid' => empty($errors),
			'errors' => $errors,
			'resolved' => $resolved,
		];
	}//end validate()

	/**
	 * Validate the bundle envelope (`format`, `bundleVersion`).
	 *
	 * @param array<string, mixed> $bundle The decoded bundle.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateEnvelope(array $bundle, array &$errors): void {
		if ((($bundle['format'] ?? null) === self::FORMAT) === false) {
			$errors[] = [
				'section' => 'envelope',
				'message' => 'Unrecognized bundle format (expected "' . self::FORMAT . '").',
			];
		}

		if ((($bundle['bundleVersion'] ?? null) === self::BUNDLE_VERSION) === false) {
			$errors[] = [
				'section' => 'envelope',
				'message' => 'Unsupported bundleVersion (expected ' . self::BUNDLE_VERSION . ').',
			];
		}
	}//end validateEnvelope()

	/**
	 * Validate the `config` section (toggles + exclusion list shape).
	 *
	 * @param array<string, mixed> $bundle The decoded bundle.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array<string, mixed> The normalised config values.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateConfigSection(array $bundle, array &$errors): array {
		$config = $bundle['config'] ?? [];
		if (is_array($config) === false) {
			$errors[] = ['section' => 'config', 'message' => '"config" must be an object.'];

			return [];
		}

		$tokenSet = $config['tokenSet'] ?? null;
		if (is_string($tokenSet) === false || $tokenSet === '') {
			$errors[] = ['section' => 'config', 'message' => '"config.tokenSet" must be a non-empty string.'];
			$tokenSet = null;
		}

		$hideSlogan = ($config['hideSlogan'] ?? false);
		if (is_bool($hideSlogan) === false) {
			$errors[] = ['section' => 'config', 'message' => '"config.hideSlogan" must be a boolean.'];
		}

		$showMenuLabels = ($config['showMenuLabels'] ?? false);
		if (is_bool($showMenuLabels) === false) {
			$errors[] = ['section' => 'config', 'message' => '"config.showMenuLabels" must be a boolean.'];
		}

		$primaryDrivesComponents = ($config['primaryDrivesComponents'] ?? false);
		if (is_bool($primaryDrivesComponents) === false) {
			$errors[] = ['section' => 'config', 'message' => '"config.primaryDrivesComponents" must be a boolean.'];
		}

		$disabledApps = $this->validateDisabledApps(config: $config, errors: $errors);

		$upstreamFreshnessEnabled = ($config['upstreamFreshnessEnabled'] ?? false);
		if (is_bool($upstreamFreshnessEnabled) === false) {
			$errors[] = ['section' => 'config', 'message' => '"config.upstreamFreshnessEnabled" must be a boolean.'];
		}

		return [
			'tokenSet' => $tokenSet,
			'hideSlogan' => ($hideSlogan === true),
			'showMenuLabels' => ($showMenuLabels === true),
			'primaryDrivesComponents' => ($primaryDrivesComponents === true),
			'disabledApps' => $disabledApps,
			'upstreamFreshnessEnabled' => ($upstreamFreshnessEnabled === true),
		];
	}//end validateConfigSection()

	/**
	 * Validate the `config.disabledApps` shape: a list of strings.
	 *
	 * @param array<string, mixed> $config The decoded `config` object.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return string[] The validated exclusion list (empty on failure).
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateDisabledApps(array $config, array &$errors): array {
		$disabledApps = ($config['disabledApps'] ?? []);
		if (is_array($disabledApps) === false) {
			$errors[] = ['section' => 'config', 'message' => '"config.disabledApps" must be a list of strings.'];

			return [];
		}

		foreach ($disabledApps as $appId) {
			if (is_string($appId) === false) {
				$errors[] = ['section' => 'config', 'message' => '"config.disabledApps" must be a list of strings.'];

				return [];
			}
		}

		return array_values($disabledApps);
	}//end validateDisabledApps()

	/**
	 * Validate the `emailFooter` section via
	 * {@see EmailThemingService::validateFooterConfig()} — the SAME rule
	 * set the settings-panel save path enforces, reused rather than
	 * reimplemented.
	 *
	 * @param array<string, mixed> $bundle The decoded bundle.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array{orgName: string, accessibilityUrl: string, privacyUrl: string} The normalised footer config.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateEmailFooterSection(array $bundle, array &$errors): array {
		$footer = ($bundle['emailFooter'] ?? []);
		if (is_array($footer) === false) {
			$errors[] = ['section' => 'emailFooter', 'message' => '"emailFooter" must be an object.'];

			return ['orgName' => '', 'accessibilityUrl' => '', 'privacyUrl' => ''];
		}

		$orgName = (string)($footer['orgName'] ?? '');
		$accessibilityUrl = (string)($footer['accessibilityUrl'] ?? '');
		$privacyUrl = (string)($footer['privacyUrl'] ?? '');

		try {
			$this->emailThemingService->validateFooterConfig(
				orgName: $orgName,
				accessibilityUrl: $accessibilityUrl,
				privacyUrl: $privacyUrl
			);
		} catch (FooterValidationException $e) {
			$errors[] = ['section' => 'emailFooter', 'message' => $e->getMessage()];
		}

		return [
			'orgName' => $orgName,
			'accessibilityUrl' => $accessibilityUrl,
			'privacyUrl' => $privacyUrl,
		];
	}//end validateEmailFooterSection()

	/**
	 * Validate the `customOverridesCss` section: it must be a string; parsed
	 * declarations that are not in the editable whitelist are skipped and
	 * counted — never a hard error (matches `token-import-export`).
	 *
	 * @param array<string, mixed> $bundle The decoded bundle.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array{tokens: array<string, string>, skipped: string[]} The editable tokens to write, and the skipped names.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - TokenRegistry uses static methods by design
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateOverridesSection(array $bundle, array &$errors): array {
		$css = ($bundle['customOverridesCss'] ?? '');
		if (is_string($css) === false) {
			$errors[] = ['section' => 'customOverridesCss', 'message' => '"customOverridesCss" must be a string.'];

			return ['tokens' => [], 'skipped' => []];
		}

		$parsed = $this->cssParser->parseDeclarations(content: $css);
		if ($parsed === null) {
			$parsed = [];
		}

		$tokens = [];
		$skipped = [];
		foreach ($parsed as $name => $value) {
			if (TokenRegistry::isEditable(tokenName: $name) === false) {
				$skipped[] = $name;
				continue;
			}

			$tokens[$name] = $value;
		}

		return [
			'tokens' => $tokens,
			'skipped' => $skipped,
		];
	}//end validateOverridesSection()

	/**
	 * Validate the `customTokenSets` section: each entry's id namespace and
	 * declarations (via {@see CustomTokenSetValidator}).
	 *
	 * @param array<string, mixed> $bundle The decoded bundle.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array<int, array<string, mixed>> The resolved, ready-to-write custom set entries.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateCustomTokenSetsSection(array $bundle, array &$errors): array {
		$sets = ($bundle['customTokenSets'] ?? []);
		if (is_array($sets) === false) {
			$errors[] = ['section' => 'customTokenSets', 'message' => '"customTokenSets" must be a list.'];

			return [];
		}

		$resolved = [];
		foreach (array_values($sets) as $index => $set) {
			$entry = $this->validateCustomTokenSetEntry(set: $set, index: $index, errors: $errors);
			if ($entry !== null) {
				$resolved[] = $entry;
			}
		}

		return $resolved;
	}//end validateCustomTokenSetsSection()

	/**
	 * Validate one `customTokenSets[]` entry: shape, then declarations.
	 * Split across two focused helpers (see {@see validateCustomTokenSetShape()},
	 * {@see validateCustomTokenSetDeclarations()}) rather than one large method,
	 * so each validation stage has an independently readable failure path.
	 *
	 * @param mixed $set The raw entry.
	 * @param int $index The entry's position, for error messages.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array<string, mixed>|null The resolved entry, or null on hard failure.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateCustomTokenSetEntry($set, int $index, array &$errors): ?array {
		$shape = $this->validateCustomTokenSetShape(set: $set, index: $index, errors: $errors);
		if ($shape === null) {
			return null;
		}

		$accepted = $this->validateCustomTokenSetDeclarations(
			id: $shape['id'],
			css: $shape['css'],
			index: $index,
			errors: $errors
		);
		if ($accepted === null) {
			return null;
		}

		return $this->buildCustomTokenSetResolvedEntry(set: $set, shape: $shape, accepted: $accepted);
	}//end validateCustomTokenSetEntry()

	/**
	 * Validate a `customTokenSets[]` entry's basic shape: it is an object,
	 * `id` is a valid custom-set id, `name` is non-empty, and `css` is a
	 * non-empty string.
	 *
	 * @param mixed $set The raw entry.
	 * @param int $index The entry's position, for error messages.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array{id: string, name: string, css: string}|null The shape, or null on hard failure.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateCustomTokenSetShape($set, int $index, array &$errors): ?array {
		if (is_array($set) === false) {
			$errors[] = ['section' => 'customTokenSets', 'index' => $index, 'message' => 'Entry must be an object.'];

			return null;
		}

		$id = (string)($set['id'] ?? '');
		if ($this->customTokenSetService->isCustomId(id: $id) === false) {
			$errors[] = ['section' => 'customTokenSets', 'index' => $index, 'id' => $id, 'message' => 'Invalid custom token set id.'];

			return null;
		}

		$name = trim((string)($set['name'] ?? ''));
		if ($name === '') {
			$errors[] = ['section' => 'customTokenSets', 'index' => $index, 'id' => $id, 'message' => 'Missing "name".'];

			return null;
		}

		$css = ($set['css'] ?? null);
		if (is_string($css) === false || $css === '') {
			$errors[] = ['section' => 'customTokenSets', 'index' => $index, 'id' => $id, 'message' => 'Missing or invalid "css".'];

			return null;
		}

		return ['id' => $id, 'name' => $name, 'css' => $css];
	}//end validateCustomTokenSetShape()

	/**
	 * Validate a `customTokenSets[]` entry's CSS: no disallowed selector,
	 * then {@see CustomTokenSetValidator::validateDeclarations()}.
	 *
	 * @param string $id The (already shape-validated) custom set id.
	 * @param string $css The (already shape-validated) CSS content.
	 * @param int $index The entry's position, for error messages.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array<string, string>|null The accepted declarations, or null on hard failure.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateCustomTokenSetDeclarations(string $id, string $css, int $index, array &$errors): ?array {
		if ($this->customTokenSetValidator->hasDisallowedSelector(css: $css) === true) {
			$errors[] = [
				'section' => 'customTokenSets',
				'index' => $index,
				'id' => $id,
				'message' => 'The CSS contains a selector or at-rule other than :root.',
			];

			return null;
		}

		$slug = substr($id, strlen(CustomTokenSetService::ID_PREFIX));
		$declarations = $this->cssParser->parseRootBlock(css: $css);
		$split = $this->customTokenSetValidator->validateDeclarations(declarations: $declarations, slug: $slug);
		if ($split === null) {
			$validatorError = $this->customTokenSetValidator->getLastError();
			$errors[] = [
				'section' => 'customTokenSets',
				'index' => $index,
				'id' => $id,
				'message' => ($validatorError['message'] ?? 'Declaration validation failed.'),
			];

			return null;
		}

		return $split['accepted'];
	}//end validateCustomTokenSetDeclarations()

	/**
	 * Build the resolved, ready-to-write entry from a shape-and-declaration-valid set.
	 *
	 * @param array<string, mixed> $set The raw entry (for optional passthrough fields).
	 * @param array{id: string, name: string, css: string} $shape The validated shape.
	 * @param array<string, string> $accepted The validated, accepted declarations.
	 *
	 * @return array<string, mixed> The resolved entry: `{id, entry, css}`.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function buildCustomTokenSetResolvedEntry(array $set, array $shape, array $accepted): array {
		$theming = [];
		if (is_array($set['theming'] ?? null) === true) {
			$theming = $set['theming'];
		}

		$entry = [
			'name' => $shape['name'],
			'description' => (string)($set['description'] ?? ''),
			'theming' => $theming,
		];

		if (isset($set['warnings']) === true && is_array($set['warnings']) === true) {
			$entry['warnings'] = $set['warnings'];
		}

		if (isset($set['version']) === true) {
			$entry['version'] = $set['version'];
		}

		if (isset($set['importWarnings']) === true && is_array($set['importWarnings']) === true) {
			$entry['importWarnings'] = $set['importWarnings'];
		}

		return [
			'id' => $shape['id'],
			'entry' => $entry,
			'css' => $this->customTokenSetValidator->serialize(declarations: $accepted),
		];
	}//end buildCustomTokenSetResolvedEntry()

	/**
	 * Validate the `customFonts` section shape. Never applied (see class
	 * docblock) — validated only so a corrupt bundle is still rejected.
	 *
	 * @param array<string, mixed> $bundle The decoded bundle.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array<string, mixed> The (informational only) fonts manifest.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateFontsSection(array $bundle, array &$errors): array {
		$fonts = ($bundle['customFonts'] ?? []);
		$manifest = [];
		if (is_array($fonts) === true) {
			$manifest = ($fonts['manifest'] ?? []);
		}

		if (is_array($fonts) === false || is_array($manifest) === false) {
			$errors[] = ['section' => 'customFonts', 'message' => '"customFonts" must be an object with a "manifest" object.'];

			return [];
		}

		return $manifest;
	}//end validateFontsSection()

	/**
	 * Cross-section check: the active token set must resolve against the
	 * filesystem OR one of this bundle's own custom sets.
	 *
	 * @param array<string, mixed> $resolved The per-section resolved data so far.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateTokenSetResolution(array $resolved, array &$errors): void {
		$tokenSet = ($resolved['config']['tokenSet'] ?? null);
		if ($tokenSet === null) {
			// Already reported by validateConfigSection().
			return;
		}

		if ($this->tokenSetService->isValidTokenSet(tokenSetId: $tokenSet) === true) {
			return;
		}

		foreach (($resolved['customTokenSets'] ?? []) as $set) {
			if (($set['id'] ?? null) === $tokenSet) {
				return;
			}
		}

		$errors[] = [
			'section' => 'config',
			'message' => 'Unresolvable token set "' . $tokenSet . '": neither shipped/installed nor present in this bundle.',
		];
	}//end validateTokenSetResolution()

	/**
	 * Build the per-section result summary (used for both the dry-run
	 * report and the post-apply report — identical shape either way).
	 *
	 * @param array<string, mixed> $resolved The validated, resolved bundle data.
	 *
	 * @return array<string, mixed> The per-section summary.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function buildSectionSummary(array $resolved): array {
		return [
			'config' => [
				'tokenSet' => $resolved['config']['tokenSet'],
				'hideSlogan' => $resolved['config']['hideSlogan'],
				'showMenuLabels' => $resolved['config']['showMenuLabels'],
				'primaryDrivesComponents' => $resolved['config']['primaryDrivesComponents'],
				'disabledAppsCount' => count($resolved['config']['disabledApps']),
				'upstreamFreshnessEnabled' => $resolved['config']['upstreamFreshnessEnabled'],
			],
			'emailFooter' => ['applied' => true],
			'customOverridesCss' => [
				'written' => count($resolved['customOverrides']['tokens']),
				'skipped' => count($resolved['customOverrides']['skipped']),
			],
			'customTokenSets' => [
				'count' => count($resolved['customTokenSets']),
				'ids' => array_column($resolved['customTokenSets'], 'id'),
			],
			'customFonts' => [
				'applied' => false,
				'binariesIncluded' => false,
				'recorded' => count($resolved['customFonts']),
				'note' => 'Font metadata recorded for information only — binaries are not part of the '
					. 'bundle and must be re-uploaded by hand on the target environment.',
			],
		];
	}//end buildSectionSummary()

	/**
	 * Phase 2: apply every validated section.
	 *
	 * @param array<string, mixed> $resolved The validated, resolved bundle data.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function apply(array $resolved): void {
		$config = $resolved['config'];

		$hideSloganValue = '0';
		if ($config['hideSlogan'] === true) {
			$hideSloganValue = '1';
		}

		$showMenuLabelsValue = '0';
		if ($config['showMenuLabels'] === true) {
			$showMenuLabelsValue = '1';
		}

		$primaryDrivesComponentsValue = '0';
		if ($config['primaryDrivesComponents'] === true) {
			$primaryDrivesComponentsValue = '1';
		}

		$this->config->setAppValue(Application::APP_ID, 'token_set', $config['tokenSet']);
		$this->config->setAppValue(Application::APP_ID, 'hide_slogan', $hideSloganValue);
		$this->config->setAppValue(Application::APP_ID, 'show_menu_labels', $showMenuLabelsValue);
		$this->config->setAppValue(Application::APP_ID, 'primary_drives_components', $primaryDrivesComponentsValue);
		$this->appThemingService->setDisabledApps(appIds: $config['disabledApps']);
		$this->freshnessService->setEnabled(enabled: $config['upstreamFreshnessEnabled']);

		$footer = $resolved['emailFooter'];
		$this->emailThemingService->setFooterConfig(
			orgName: $footer['orgName'],
			accessibilityUrl: $footer['accessibilityUrl'],
			privacyUrl: $footer['privacyUrl']
		);

		$this->overridesService->write(tokens: $resolved['customOverrides']['tokens']);

		foreach ($resolved['customTokenSets'] as $set) {
			$this->customTokenSetService->replace(id: $set['id'], entry: $set['entry'], css: $set['css']);
		}

		// CustomFonts is deliberately never applied — see class docblock.
	}//end apply()
}//end class
