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
 * @SuppressWarnings(PHPMD.LongVariable)             - $upstreamFreshnessEnabled is the SAME precise
 * name used for this bundle field throughout the rest of the codebase; shortening it here would
 * reduce cross-file consistency for a purely cosmetic 20-character sniff threshold.
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
	public const BUNDLE_VERSION = 4;

	/**
	 * The bundle versions import accepts. Version 1 predates planned switches
	 * (`config.scheduledSwitches`); a version 1 bundle, such as a version kept
	 * before the upgrade, imports and leaves the planned switches alone.
	 * Version 2 predates brands per app (`appBrands`); it imports and leaves
	 * the brands alone.
	 *
	 * @var array<int, int>
	 */
	public const SUPPORTED_VERSIONS = [1, 2, 3, 4];

	/**
	 * The `config` toggles stored as '1'/'0' app values: bundle name to app config key.
	 *
	 * @var array<string, string>
	 */
	private const array CONFIG_FLAG_KEYS = [
		'hideSlogan' => 'hide_slogan',
		'showMenuLabels' => 'show_menu_labels',
		'primaryDrivesComponents' => 'primary_drives_components',
	];

	/**
	 * Constructor.
	 *
	 * @param ConfigBundleAppSettings $appSettings The config, the app manager, the per-app theming and the freshness toggle.
	 * @param ConfigBundleTokenSources $tokenSources The token sets, the custom overrides, the planned switches and the CSS parser.
	 * @param ConfigBundleSections $sections The custom token sets, the extra sections and the lifecycle section.
	 * @param ConfigBundleBranding $branding The e-mail theming, the fonts and the logger.
	 */
	public function __construct(
		private readonly ConfigBundleAppSettings $appSettings,
		private readonly ConfigBundleTokenSources $tokenSources,
		private readonly ConfigBundleSections $sections,
		private readonly ConfigBundleBranding $branding,
	) {
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
				'version' => $this->appSettings->appManager->getAppVersion(Application::APP_ID),
			],
			'config' => $this->exportedConfig(),
			'emailFooter' => $this->branding->emailThemingService->getFooterConfig(),
			'customOverridesCss' => $this->tokenSources->overridesService->getRawContent(),
			'customTokenSets' => $this->sections->customTokenSets->export(),
			'customFonts' => $this->exportedFonts(),
		] + $this->sections->extraSections->export() + ($this->sections->lifecycle?->export() ?? []);
	}//end export()

	/**
	 * The bundle's `config` section: the active set, the toggles, the exclusions and the schedule.
	 *
	 * @return array<string, mixed> The section.
	 */
	private function exportedConfig(): array {
		$config = $this->appSettings->config;

		return [
			'tokenSet' => $config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud'),
			'hideSlogan' => ($config->getAppValue(Application::APP_ID, 'hide_slogan', '0') === '1'),
			'showMenuLabels' => ($config->getAppValue(Application::APP_ID, 'show_menu_labels', '0') === '1'),
			'primaryDrivesComponents' => ($config->getAppValue(Application::APP_ID, 'primary_drives_components', '0') === '1'),
			'layoutOptions' => $this->exportLayoutOptions(),
			'disabledApps' => $this->appSettings->appThemingService->getDisabledApps(),
			'upstreamFreshnessEnabled' => $this->appSettings->freshnessService->isEnabled(),
			'scheduledSwitches' => $this->tokenSources->scheduledSwitches->exportable(),
		];
	}//end exportedConfig()

	/**
	 * The bundle's `customFonts` section: the font metadata only.
	 *
	 * @return array<string, mixed> The section.
	 */
	private function exportedFonts(): array {
		return [
			'binariesIncluded' => false,
			'note' => 'Font binaries are not embedded in the bundle (see config-portability spec). '
				. 'Metadata only — re-upload font files by hand on the target environment.',
			'manifest' => $this->branding->fontService->getManifest(),
		];
	}//end exportedFonts()

	/**
	 * The stored layout choices, each as the administrator stored it: the
	 * empty string means "follow the theme" and travels as such, so the target
	 * follows its own set the way the source did.
	 *
	 * @return array<string, string> Camel-case option name to stored value.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/config-portability/spec.md#requirement-the-layout-options-travel-in-the-bundle
	 */
	private function exportLayoutOptions(): array {
		$export = [];
		foreach (LayoutOptionValues::BUNDLE_KEYS as $name => $key) {
			$export[$name] = $this->appSettings->config->getAppValue(Application::APP_ID, $key, '');
		}

		return $export;
	}//end exportLayoutOptions()

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
	 * between "validate only" and "validate then apply" (`occ thematiq:config:import --dry-run`);
	 * splitting into two public methods would duplicate phase 1 rather than remove a real flag.
	 */
	public function import(array $bundle, bool $dryRun = false): array {
		$validation = $this->validate(bundle: $bundle);

		if ($validation['valid'] === false) {
			return $this->rejected(errors: $validation['errors'], dryRun: $dryRun);
		}

		$report = new ConfigBundleImportReport(sections: $this->sections);
		$sections = $report->summary(resolved: $validation['resolved']);

		if ($dryRun === true) {
			return $report->accepted(dryRun: true, sections: $sections);
		}

		$this->apply(resolved: $validation['resolved']);

		$this->branding->logger->info('NL Design configuration bundle imported successfully.');

		return $report->accepted(dryRun: false, sections: $sections);
	}//end import()

	/**
	 * The result of an import refused in phase 1, which is logged.
	 *
	 * @param array<int, array<string, mixed>> $errors The section errors.
	 * @param bool $dryRun Whether the import was a dry run.
	 *
	 * @return array<string, mixed> The result.
	 */
	private function rejected(array $errors, bool $dryRun): array {
		$this->branding->logger->warning(
			'NL Design configuration bundle import rejected: ' . count($errors) . ' section error(s).'
		);

		return [
			'valid' => false,
			'dryRun' => $dryRun,
			'applied' => false,
			'errors' => $errors,
		];
	}//end rejected()

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
		$run = new BundleValidationRun(bundle: $bundle);
		$this->validateSections(run: $run);
		$this->validateAcrossSections(run: $run);

		return [
			'valid' => empty($run->errors),
			'errors' => $run->errors,
			'resolved' => $run->resolved,
		];
	}//end validate()

	/**
	 * Validate the envelope and each section on its own.
	 *
	 * @param BundleValidationRun $run The validation; gets each section's data and errors.
	 *
	 * @return void
	 */
	private function validateSections(BundleValidationRun $run): void {
		$bundle = $run->bundle;
		$errors = $run->errors;

		$this->validateEnvelope(bundle: $bundle, errors: $errors);

		$run->resolved['config'] = $this->validateConfigSection(bundle: $bundle, errors: $errors);
		$run->resolved['emailFooter'] = $this->validateEmailFooterSection(bundle: $bundle, errors: $errors);
		$run->resolved['customOverrides'] = $this->validateOverridesSection(bundle: $bundle, errors: $errors);
		$run->resolved['customTokenSets'] = $this->sections->customTokenSets->validate(bundle: $bundle, errors: $errors);
		$run->resolved['customFonts'] = $this->validateFontsSection(bundle: $bundle, errors: $errors);
		$run->errors = $errors;
	}//end validateSections()

	/**
	 * Validate what depends on more than one section: the active set, restricted sets, the schedule and the extra sections.
	 *
	 * @param BundleValidationRun $run The validation; gets the cross-section data and errors.
	 *
	 * @return void
	 */
	private function validateAcrossSections(BundleValidationRun $run): void {
		$errors = $run->errors;
		// The active token set must resolve against either the filesystem
		// (shipped or already-installed custom) OR one of THIS bundle's own
		// custom sets — only meaningful once customTokenSets parsed cleanly.
		$this->validateTokenSetResolution(resolved: $run->resolved, errors: $errors);
		$errors = array_merge(
			$errors,
			(new RestrictedSetsInBundle())->errors(bundle: $run->bundle, tokenSets: $this->tokenSources->tokenSetService, config: $this->appSettings->config)
		);

		$run->resolved['scheduledSwitches'] = $this->validateScheduledSwitches(bundle: $run->bundle, resolved: $run->resolved, errors: $errors);
		$run->resolved['lifecycle'] = $this->sections->lifecycle?->validate(bundle: $run->bundle, errors: $errors);
		$run->resolved['extra'] = $this->validateExtraSections(run: $run, errors: $errors);
		$run->errors = $errors;
	}//end validateAcrossSections()

	/**
	 * Validate the extra sections; a set they name exists when the bundle carries it or the instance has it.
	 *
	 * @param BundleValidationRun $run The validation so far.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return mixed The extra sections' resolved data.
	 */
	private function validateExtraSections(BundleValidationRun $run, array &$errors): mixed {
		$bundledIds = array_column($run->resolved['customTokenSets'], 'id');

		return $this->sections->extraSections->validate(
			bundle: $run->bundle,
			setExists: fn (string $id): bool => (
				in_array($id, $bundledIds, true) === true || $this->tokenSources->tokenSetService->isValidTokenSet(tokenSetId: $id) === true
			),
			errors: $errors
		);
	}//end validateExtraSections()

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

		if (in_array(($bundle['bundleVersion'] ?? null), self::SUPPORTED_VERSIONS, true) === false) {
			$errors[] = [
				'section' => 'envelope',
				'message' => 'Unsupported bundleVersion (expected ' . implode(' or ', self::SUPPORTED_VERSIONS) . ').',
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
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - LayoutOptionValues::fromBundle() is a pure lookup
	 */
	private function validateConfigSection(array $bundle, array &$errors): array {
		$config = $bundle['config'] ?? [];
		if (is_array($config) === false) {
			$errors[] = ['section' => 'config', 'message' => '"config" must be an object.'];

			return [];
		}

		$resolved = ['tokenSet' => $this->validateConfigTokenSet(config: $config, errors: $errors)];
		$flags = $this->flagValues(config: $config, flags: array_keys(self::CONFIG_FLAG_KEYS));
		$resolved += $this->validateConfigFlags(values: $flags, errors: $errors);
		$resolved['layoutOptions'] = LayoutOptionValues::fromBundle(options: ($config['layoutOptions'] ?? null), errors: $errors);
		$resolved['disabledApps'] = $this->validateDisabledApps(config: $config, errors: $errors);
		$flags = $this->flagValues(config: $config, flags: ['upstreamFreshnessEnabled']);

		return $resolved + $this->validateConfigFlags(values: $flags, errors: $errors);
	}//end validateConfigSection()

	/**
	 * Validate `config.tokenSet`: a non-empty string.
	 *
	 * @param array<string, mixed> $config The decoded `config` object.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return string|null The token set id, or null when it is not one.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateConfigTokenSet(array $config, array &$errors): ?string {
		$tokenSet = ($config['tokenSet'] ?? null);
		if (is_string($tokenSet) === false || $tokenSet === '') {
			$errors[] = ['section' => 'config', 'message' => '"config.tokenSet" must be a non-empty string.'];

			return null;
		}

		return $tokenSet;
	}//end validateConfigTokenSet()

	/**
	 * The values of some `config` toggles, false where absent.
	 *
	 * @param array<string, mixed> $config The decoded `config` object.
	 * @param array<int, string> $flags The toggle names, in validation order.
	 *
	 * @return array<string, mixed> Toggle name to its raw value.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function flagValues(array $config, array $flags): array {
		$values = [];
		foreach ($flags as $flag) {
			$values[$flag] = ($config[$flag] ?? false);
		}

		return $values;
	}//end flagValues()

	/**
	 * Validate `config` toggles: each must be a boolean.
	 *
	 * @param array<string, mixed> $values Toggle name to its raw value.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array<string, bool> Toggle name to its value; a toggle that is not a boolean is off.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateConfigFlags(array $values, array &$errors): array {
		$resolved = [];
		foreach ($values as $flag => $value) {
			if (is_bool($value) === false) {
				$errors[] = ['section' => 'config', 'message' => '"config.' . $flag . '" must be a boolean.'];
			}

			$resolved[$flag] = ($value === true);
		}

		return $resolved;
	}//end validateConfigFlags()

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
			$this->branding->emailThemingService->validateFooterConfig(
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

		$parsed = $this->tokenSources->cssParser->parseOverridesFile(css: $css);
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

		if ($this->tokenSources->tokenSetService->isValidTokenSet(tokenSetId: $tokenSet) === true) {
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
	 * Validate `config.scheduledSwitches`: times parse, windows do not
	 * overlap, and each set is installed or carried by this bundle. Absent
	 * (a version 1 bundle) means the planned switches are left alone.
	 *
	 * @param array<string, mixed> $bundle The decoded bundle.
	 * @param array<string, mixed> $resolved The per-section resolved data so far.
	 * @param array<int, array<string, mixed>> $errors Accumulator, appended to on failure.
	 *
	 * @return array<int, array<string, mixed>>|null The entries to store, or null to leave them alone.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateScheduledSwitches(array $bundle, array $resolved, array &$errors): ?array {
		$config = ($bundle['config'] ?? []);
		if (is_array($config) === false || array_key_exists('scheduledSwitches', $config) === false) {
			return null;
		}

		$bundledIds = array_column(($resolved['customTokenSets'] ?? []), 'id');
		$result = $this->tokenSources->scheduledSwitches->validateImport(
			raw: $config['scheduledSwitches'],
			setExists: fn (string $id): bool => (
				in_array($id, $bundledIds, true) === true || $this->tokenSources->tokenSetService->isValidTokenSet(tokenSetId: $id) === true
			)
		);

		foreach ($result['errors'] as $message) {
			$errors[] = ['section' => 'config', 'message' => $message];
		}

		return $result['entries'];
	}//end validateScheduledSwitches()

	/**
	 * Write the validated `config` section: the token set, the toggles, the
	 * layout options, the exclusion list and the freshness opt-in.
	 *
	 * @param array<string, mixed> $config The resolved `config` section.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function applyConfig(array $config): void {
		$this->appSettings->config->setAppValue(Application::APP_ID, 'token_set', $config['tokenSet']);
		foreach (self::CONFIG_FLAG_KEYS as $flag => $key) {
			$this->appSettings->config->setAppValue(Application::APP_ID, $key, (string)(int)$config[$flag]);
		}

		foreach ($config['layoutOptions'] as $key => $value) {
			$this->appSettings->config->setAppValue(Application::APP_ID, $key, $value);
		}

		$this->appSettings->appThemingService->setDisabledApps(appIds: $config['disabledApps']);
		$this->appSettings->freshnessService->setEnabled(enabled: $config['upstreamFreshnessEnabled']);
	}//end applyConfig()

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
		$this->applyConfig(config: $resolved['config']);

		$footer = $resolved['emailFooter'];
		$this->branding->emailThemingService->setFooterConfig(
			orgName: $footer['orgName'],
			accessibilityUrl: $footer['accessibilityUrl'],
			privacyUrl: $footer['privacyUrl']
		);

		// Own tokens first: the overrides file written next renders them.
		$this->sections->lifecycle?->apply(resolved: ($resolved['lifecycle'] ?? null));
		$this->tokenSources->overridesService->write(tokens: $resolved['customOverrides']['tokens']);
		$this->sections->customTokenSets->apply(resolved: $resolved['customTokenSets']);

		if ($resolved['scheduledSwitches'] !== null) {
			$this->tokenSources->scheduledSwitches->save(
				entries: $this->tokenSources->scheduledSwitches->mergeImported(imported: $resolved['scheduledSwitches'], now: time())
			);
		}

		$this->sections->extraSections->apply(resolved: $resolved['extra']);

		// CustomFonts is deliberately never applied — see class docblock.
	}//end apply()
}//end class
