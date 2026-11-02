<?php

/**
 * Thematiq branding package import and export.
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
 * @spec openspec/specs/theme-as-code/spec.md
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use RuntimeException;

/**
 * Applies a branding package (bundle, fonts, DTCG sources) and writes one.
 *
 * The bundle half is the existing {@see ConfigBundleService}: this class adds
 * what a bare bundle cannot carry. A package is validated whole before
 * anything is written: the DTCG sources are converted, every font file is
 * checked by the existing {@see FontValidator}, and the bundle runs through
 * the bundle's own dry run. Any failure writes nothing.
 *
 * Fonts in the package are added, or replace a font with the same id. Fonts
 * the running server has that the package does not name are left alone.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 * @spec openspec/specs/config-portability/spec.md
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - the package is the meeting point of the bundle, fonts and converter.
 */
class BrandingPackageService {

	/**
	 * Constructor.
	 *
	 * @param BrandingPackageReader $reader Reads and writes the package files.
	 * @param ConfigBundleService $bundleService The existing bundle import and export.
	 * @param FontService $fontService Font storage.
	 * @param FontValidator $fontValidator The upload rules for fonts.
	 * @param PackageTokenSourceConverter $tokenSources Converts the DTCG sources into bundle custom sets.
	 */
	public function __construct(
		private readonly BrandingPackageReader $reader,
		private readonly ConfigBundleService $bundleService,
		private readonly FontService $fontService,
		private readonly FontValidator $fontValidator,
		private readonly PackageTokenSourceConverter $tokenSources,
	) {
	}//end __construct()

	/**
	 * Whether a path names a package (a directory or a ZIP) rather than a bare bundle file.
	 *
	 * @param string $path The path given to the import.
	 *
	 * @return bool True for a directory or a ZIP file.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function isPackage(string $path): bool {
		if (is_dir($path) === true) {
			return true;
		}

		if (is_file($path) === false || is_readable($path) === false) {
			return false;
		}

		$handle = fopen($path, 'rb');
		if ($handle === false) {
			return false;
		}

		$magic = (string)fread($handle, 4);
		fclose($handle);

		return ($magic === "PK\x03\x04");
	}//end isPackage()

	/**
	 * Export the running configuration as a package directory, fonts included.
	 *
	 * @param string $dir The target directory.
	 *
	 * @return array<int, string> The relative paths written.
	 *
	 * @throws RuntimeException When the directory cannot be written.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function export(string $dir): array {
		$bundle = $this->bundleService->export();
		$fonts = [];
		foreach (array_keys($this->fontService->getManifest()) as $id) {
			$bytes = $this->fontService->readFontBytes(id: (string)$id);
			if ($bytes !== null) {
				$fonts[(string)$id] = $bytes;
			}
		}

		$bundle['customFonts']['binariesIncluded'] = true;
		$bundle['customFonts']['note'] = 'Font files are in the fonts/ directory of this package.';

		return $this->reader->write(dir: $dir, bundle: $bundle, fonts: $fonts);
	}//end export()

	/**
	 * Validate a package and, unless `$dryRun`, apply it.
	 *
	 * @param string $path The package directory or ZIP.
	 * @param bool $dryRun When true, validate only.
	 *
	 * @return array<string, mixed> `{valid, dryRun, applied, sections?, errors?, revision, hash}`.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 * @spec openspec/specs/config-portability/spec.md
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) - mirrors ConfigBundleService::import(), the occ --dry-run switch.
	 */
	public function import(string $path, bool $dryRun = false): array {
		try {
			$package = $this->reader->read(path: $path);
		} catch (RuntimeException $e) {
			return $this->invalid(errors: [['section' => 'package', 'message' => $e->getMessage()]], dryRun: $dryRun);
		}

		$errors = [];
		$bundle = $this->tokenSources->intoBundle(package: $package, errors: $errors);
		$fonts = $this->validateFonts(bundle: $bundle, files: $package['fonts'], errors: $errors);

		$check = $this->bundleService->import(bundle: $bundle, dryRun: true);
		if ($check['valid'] === false) {
			$errors = array_merge($errors, $check['errors']);
		}

		if ($errors !== []) {
			return $this->invalid(errors: $errors, dryRun: $dryRun, package: $package);
		}

		$sections = ($check['sections'] ?? []);
		$sections['customFonts'] = [
			'applied' => ($dryRun === false),
			'binariesIncluded' => true,
			'count' => count($fonts),
			'ids' => array_keys($fonts),
		];

		if ($dryRun === false) {
			$this->bundleService->import(bundle: $bundle, dryRun: false);
			$this->applyFonts(fonts: $fonts);
		}

		return [
			'valid' => true,
			'dryRun' => $dryRun,
			'applied' => ($dryRun === false),
			'sections' => $sections,
			'revision' => $package['revision'],
			'hash' => $package['hash'],
		];
	}//end import()

	/**
	 * The bundle of a package as it would be applied, with its DTCG sources converted.
	 *
	 * @param string $path The package directory or ZIP.
	 *
	 * @return array<string, mixed> The bundle.
	 *
	 * @throws RuntimeException When the package cannot be read.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function readBundle(string $path): array {
		$errors = [];

		return $this->tokenSources->intoBundle(package: $this->reader->read(path: $path), errors: $errors);
	}//end readBundle()

	/**
	 * Check every `customFonts` entry against its file in the package.
	 *
	 * @param array<string, mixed> $bundle The bundle.
	 * @param array<string, string> $files Font bytes keyed by id.
	 * @param array<int, array<string, mixed>> $errors Accumulator.
	 *
	 * @return array<string, array{name: string, role: string, bytes: string}> The fonts to store, keyed by id.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateFonts(array $bundle, array $files, array &$errors): array {
		$manifest = ($bundle['customFonts']['manifest'] ?? []);
		if (is_array($manifest) === false) {
			return [];
		}

		$fonts = [];
		foreach ($manifest as $id => $meta) {
			$font = $this->validateFont(id: (string)$id, meta: $meta, bytes: ($files[(string)$id] ?? null), errors: $errors);
			if ($font !== null) {
				$fonts[(string)$id] = $font;
			}
		}

		$kept = array_diff(array_keys($this->fontService->getManifest()), array_keys($fonts));
		if ((count($kept) + count($fonts)) > FontValidator::MAX_FONTS) {
			$errors[] = ['section' => 'customFonts', 'message' => 'The package would take the server over ' . FontValidator::MAX_FONTS . ' fonts.'];
		}

		return $fonts;
	}//end validateFonts()

	/**
	 * Check one font entry and its file.
	 *
	 * @param string $id The font id.
	 * @param mixed $meta The manifest entry.
	 * @param string|null $bytes The file, or null when the package lacks it.
	 * @param array<int, array<string, mixed>> $errors Accumulator.
	 *
	 * @return array{name: string, role: string, bytes: string}|null The font, or null on failure.
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function validateFont(string $id, $meta, ?string $bytes, array &$errors): ?array {
		if ($bytes === null) {
			$errors[] = ['section' => 'customFonts', 'id' => $id, 'message' => 'Missing font file fonts/' . $id . '.woff2.'];

			return null;
		}

		if (is_array($meta) === false) {
			$meta = [];
		}

		$name = (string)($meta['name'] ?? '');
		$role = (string)($meta['role'] ?? '');
		try {
			$this->fontValidator->validateDisplayName(name: $name);
			$this->fontValidator->validateRole(role: $role);
			$this->fontValidator->validateMagicBytes(bytes: $bytes);
			$this->fontValidator->validateSize(size: strlen($bytes));
		} catch (RuntimeException $e) {
			$errors[] = ['section' => 'customFonts', 'id' => $id, 'message' => 'fonts/' . $id . '.woff2: ' . $e->getMessage()];

			return null;
		}

		if (FontService::ID_PREFIX . $this->fontService->slugify(name: $name) !== $id) {
			$errors[] = ['section' => 'customFonts', 'id' => $id, 'message' => 'The font id ' . $id . ' does not match its name "' . $name . '".'];

			return null;
		}

		return ['name' => $name, 'role' => $role, 'bytes' => $bytes];
	}//end validateFont()

	/**
	 * Store the package fonts, replacing a font with the same id.
	 *
	 * @param array<string, array{name: string, role: string, bytes: string}> $fonts The validated fonts.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/config-portability/spec.md
	 */
	private function applyFonts(array $fonts): void {
		foreach ($fonts as $id => $font) {
			if ($this->fontService->getEntry(id: $id) !== null) {
				$this->fontService->delete(id: $id);
			}

			$this->fontService->store(
				displayName: $font['name'],
				role: $font['role'],
				bytes: $font['bytes'],
				reportedSize: strlen($font['bytes'])
			);
		}
	}//end applyFonts()

	/**
	 * The result of a refused package.
	 *
	 * @param array<int, array<string, mixed>> $errors The errors.
	 * @param bool $dryRun Whether this was a dry run.
	 * @param array<string, mixed>|null $package The read package, when it could be read.
	 *
	 * @return array<string, mixed> The result.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	private function invalid(array $errors, bool $dryRun, ?array $package = null): array {
		return [
			'valid' => false,
			'dryRun' => $dryRun,
			'applied' => false,
			'errors' => $errors,
			'revision' => ($package['revision'] ?? null),
			'hash' => ($package['hash'] ?? null),
		];
	}//end invalid()
}//end class
