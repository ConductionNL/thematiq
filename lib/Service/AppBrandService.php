<?php

/**
 * Thematiq brand per app: an app's own token set and logos.
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
 * @spec openspec/specs/per-app-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\Exception\AppBrandException;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IURLGenerator;

/**
 * Maps an app to a token set and, optionally, a large and a small logo.
 * Stored as `app_brands` next to the exclusion list (`disabled_apps`); logos
 * in app data `app-brands/`. An app must be installed, themed, and not one of
 * the protected ids, so the settings pages always look like the organisation.
 * The app's name stays Nextcloud's.
 *
 * @spec openspec/specs/per-app-theming/spec.md
 */
class AppBrandService {

	/**
	 * App config key.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'app_brands';

	/**
	 * The logo sizes.
	 *
	 * @var array<int, string>
	 */
	public const SIZES = ['large', 'small'];

	/**
	 * Constructor.
	 *
	 * @param IConfig           $config     App config.
	 * @param IAppManager       $appManager Installed apps.
	 * @param AppThemingService $appTheming The exclusion list and the protected ids.
	 * @param TokenSetService   $tokenSets  Which sets exist.
	 * @param AppBrandLogoStore $logos      Logo storage.
	 * @param IURLGenerator     $urls       Logo URLs.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly IAppManager $appManager,
		private readonly AppThemingService $appTheming,
		private readonly TokenSetService $tokenSets,
		private readonly AppBrandLogoStore $logos,
		private readonly IURLGenerator $urls,
	) {
	}//end __construct()

	/**
	 * Every stored brand, keyed by app id, with a `stale` flag for one that no longer applies.
	 *
	 * @return array<string, array{tokenSet: string, logoLarge: array<string, mixed>|null, logoSmall: array<string, mixed>|null, stale: bool}> Brands.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function getBrands(): array {
		$result = [];
		foreach ($this->stored() as $appId => $brand) {
			$brand['stale'] = ($this->problem(appId: $appId, tokenSet: $brand['tokenSet']) !== null);
			$result[$appId] = $brand;
		}

		return $result;
	}//end getBrands()

	/**
	 * Map an app to a token set, keeping its logos.
	 *
	 * @param string $appId The app id.
	 * @param string $tokenSet The token set id.
	 *
	 * @return array<string, mixed> The brand.
	 *
	 * @throws AppBrandException When the app or set cannot be branded.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function setBrand(string $appId, string $tokenSet): array {
		$problem = $this->problem(appId: $appId, tokenSet: $tokenSet);
		if ($problem !== null) {
			throw new AppBrandException(reason: $problem);
		}

		$brands = $this->stored();
		$brands[$appId] = [
			'tokenSet' => $tokenSet,
			'logoLarge' => ($brands[$appId]['logoLarge'] ?? null),
			'logoSmall' => ($brands[$appId]['logoSmall'] ?? null),
		];
		$this->save(brands: $brands);

		return $brands[$appId];
	}//end setBrand()

	/**
	 * Remove an app's brand and its logos.
	 *
	 * @param string $appId The app id.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function removeBrand(string $appId): void {
		$brands = $this->stored();
		if (isset($brands[$appId]) === false) {
			return;
		}

		foreach (self::SIZES as $size) {
			$this->logos->remove(appId: $appId, size: $size);
		}

		unset($brands[$appId]);
		$this->save(brands: $brands);
	}//end removeBrand()

	/**
	 * Store a logo for a branded app.
	 *
	 * @param string $appId The app id; it must have a brand.
	 * @param string $size `large` or `small`.
	 * @param string $bytes The image.
	 *
	 * @return array<string, mixed> The brand.
	 *
	 * @throws AppBrandException 404 without a brand or size, 413 too large, 422 not an accepted image.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function storeLogo(string $appId, string $size, string $bytes): array {
		$brands = $this->stored();
		if (isset($brands[$appId]) === false || in_array($size, self::SIZES, true) === false) {
			throw new AppBrandException(reason: AppBrandException::NOT_INSTALLED, status: 404);
		}

		$brands[$appId]['logo' . ucfirst($size)] = $this->logos->store(appId: $appId, size: $size, bytes: $bytes);
		$this->save(brands: $brands);

		return $brands[$appId];
	}//end storeLogo()

	/**
	 * Read a stored logo.
	 *
	 * @param string $appId The app id.
	 * @param string $size `large` or `small`.
	 *
	 * @return array{bytes: string, mime: string}|null The logo, or null when there is none.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function readLogo(string $appId, string $size): ?array {
		$meta = ($this->stored()[$appId]['logo' . ucfirst($size)] ?? null);
		if (in_array($size, self::SIZES, true) === false || is_array($meta) === false) {
			return null;
		}

		$bytes = $this->logos->read(appId: $appId, size: $size, mime: (string)$meta['mime']);
		if ($bytes === null) {
			return null;
		}

		return ['bytes' => $bytes, 'mime' => (string)$meta['mime']];
	}//end readLogo()

	/**
	 * The brand that applies on a page of an app, or null.
	 *
	 * @param string|null $appId The rendered app.
	 *
	 * @return array{tokenSet: string, large: string|null, small: string|null}|null The set and the logo URLs.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function brandFor(?string $appId): ?array {
		if ($appId === null || $appId === '') {
			return null;
		}

		$brand = ($this->stored()[$appId] ?? null);
		if ($brand === null || $this->problem(appId: $appId, tokenSet: $brand['tokenSet']) !== null) {
			return null;
		}

		return [
			'tokenSet' => $brand['tokenSet'],
			'large' => $this->logoUrl(appId: $appId, size: 'large', meta: $brand['logoLarge']),
			'small' => $this->logoUrl(appId: $appId, size: 'small', meta: $brand['logoSmall']),
		];
	}//end brandFor()

	/**
	 * The configuration bundle section: set per app, logos as metadata only.
	 *
	 * @return array<string, array<string, mixed>> The brands.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function exportBundle(): array {
		return $this->stored();
	}//end exportBundle()

	/**
	 * Validate the bundle's `appBrands` section. Absent leaves the brands alone; logos are never applied.
	 *
	 * @param mixed $section The section, or null.
	 * @param callable(string): bool $setExists Whether a set id exists on the target or in the bundle.
	 *
	 * @return array{errors: array<int, string>, value: array<string, string>|null} The errors and the app => set map to apply.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function validateBundle(mixed $section, callable $setExists): array {
		if ($section === null) {
			return ['errors' => [], 'value' => null];
		}

		if ($section instanceof \stdClass) {
			// The export writes an empty map as an object, so it reads back as `{}`.
			$section = (array)$section;
		}

		if (is_array($section) === false) {
			return ['errors' => ['"appBrands" must be an object keyed by app id.'], 'value' => null];
		}

		$value = [];
		foreach ($section as $appId => $brand) {
			$tokenSet = ($this->arrayOrNull(value: $brand)['tokenSet'] ?? null);
			if (is_string($tokenSet) === false || $setExists($tokenSet) === false) {
				return ['errors' => ['"appBrands.' . $appId . '" names no available token set.'], 'value' => null];
			}

			$value[(string)$appId] = $tokenSet;
		}

		return ['errors' => [], 'value' => $value];
	}//end validateBundle()

	/**
	 * Apply a validated `appBrands` section: the set per app; existing logos stay, new apps get none.
	 * An app that is not installed here is kept, and shows as stale until it is.
	 *
	 * @param array<string, string> $value App id => set id.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function applyBundle(array $value): void {
		$stored = $this->stored();
		$brands = [];
		foreach ($value as $appId => $tokenSet) {
			$brands[$appId] = [
				'tokenSet' => $tokenSet,
				'logoLarge' => ($stored[$appId]['logoLarge'] ?? null),
				'logoSmall' => ($stored[$appId]['logoSmall'] ?? null),
			];
		}

		$this->save(brands: $brands);
	}//end applyBundle()

	/**
	 * Why an app cannot carry a brand with this set, or null when it can.
	 *
	 * @param string $appId The app id.
	 * @param string $tokenSet The set id.
	 *
	 * @return string|null One of the AppBrandException reasons.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	private function problem(string $appId, string $tokenSet): ?string {
		if (in_array($appId, AppThemingService::PROTECTED_IDS, true) === true) {
			return AppBrandException::PROTECTED;
		}

		if ($appId === '' || $this->appManager->isInstalled($appId) === false) {
			return AppBrandException::NOT_INSTALLED;
		}

		if ($this->appTheming->isThemingDisabledFor(appId: $appId) === true) {
			return AppBrandException::EXCLUDED;
		}

		if ($this->tokenSets->isValidTokenSet(tokenSetId: $tokenSet) === false) {
			return AppBrandException::UNKNOWN_SET;
		}

		return null;
	}//end problem()

	/**
	 * The stored brands, cleaned.
	 *
	 * @return array<string, array{tokenSet: string, logoLarge: array<string, mixed>|null, logoSmall: array<string, mixed>|null}> The brands.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	private function stored(): array {
		$raw = json_decode((string)$this->config->getAppValue(Application::APP_ID, self::CONFIG_KEY, '{}'), true);
		if (is_array($raw) === false) {
			return [];
		}

		$brands = [];
		foreach ($raw as $appId => $brand) {
			if (is_array($brand) === false || is_string($brand['tokenSet'] ?? null) === false) {
				continue;
			}

			$brands[(string)$appId] = [
				'tokenSet' => $brand['tokenSet'],
				'logoLarge' => $this->arrayOrNull(value: ($brand['logoLarge'] ?? null)),
				'logoSmall' => $this->arrayOrNull(value: ($brand['logoSmall'] ?? null)),
			];
		}

		return $brands;
	}//end stored()

	/**
	 * An array, or null for anything else.
	 *
	 * @param mixed $value The value.
	 *
	 * @return array<string, mixed>|null The array, or null.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	private function arrayOrNull(mixed $value): ?array {
		if (is_array($value) === true) {
			return $value;
		}

		return null;
	}//end arrayOrNull()

	/**
	 * Persist the brands.
	 *
	 * @param array<string, mixed> $brands The brands.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	private function save(array $brands): void {
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_KEY, (string)json_encode((object)$brands));
	}//end save()

	/**
	 * The logo variable for a branded app's pages: the large logo, and the small
	 * one below 1024 px, the breakpoint at which Nextcloud's header goes narrow.
	 * Unquoted, like the other logo URLs of CssInjectionService::logoUrlLayer().
	 *
	 * @param string $large The large logo URL.
	 * @param string|null $small The small logo URL, or null to use the large one at every width.
	 *
	 * @return string The stylesheet body.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public function logoCss(string $large, ?string $small): string {
		$css = ':root{--nldesign-logo-url:url(' . $large . ');--nldesign-logo-filter:none}';
		if ($small !== null) {
			$css .= '@media (max-width:1024px){:root{--nldesign-logo-url:url(' . $small . ')}}';
		}

		return $css;
	}//end logoCss()

	/**
	 * A logo URL, versioned by upload time so a new logo is not served from cache.
	 *
	 * @param string $appId The app id.
	 * @param string $size The size.
	 * @param array<string, mixed>|null $meta The logo metadata.
	 *
	 * @return string|null The URL, or null without a logo.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	private function logoUrl(string $appId, string $size, ?array $meta): ?string {
		if ($meta === null) {
			return null;
		}

		return $this->urls->linkToRoute(
			'thematiq.appBrand.logo',
			['appId' => $appId, 'size' => $size, 'v' => (int)($meta['uploadedAt'] ?? 0)]
		);
	}//end logoUrl()
}//end class
