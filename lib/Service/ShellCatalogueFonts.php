<?php

/**
 * The font families the shipped token sets name, and what the repository says about each.
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
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-font-is-redistributable-only-under-a-stated-licence
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The font list of the shell catalogue.
 *
 * A family is redistributable only when thematiq ships files for it and
 * `REUSE.toml` names those files under a licence whose text allows passing
 * them on. It is not when the repository says so: a restriction written down
 * beside the files, or a set's own `font` block. Otherwise the answer is null.
 *
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-font-is-redistributable-only-under-a-stated-licence
 */
class ShellCatalogueFonts {

	/**
	 * Font families the repository says may not be passed on, whatever licence their files carry.
	 *
	 * @var array<string, array{source: string, quote: string}>
	 */
	private const RESTRICTED_FONTS = [
		'Marianne' => ['source' => 'MARIANNE-LICENCE.md', 'quote' => 'reserved for French State administrations'],
	];

	/**
	 * Licences whose text, in `LICENSES/`, allows passing a font file on, and the notice that travels with the files.
	 *
	 * @var array<string, string>
	 */
	private const FONT_LICENCE_NOTICES = ['OFL-1.1' => 'OFL.txt', 'Apache-2.0' => 'APACHE-2.0.txt'];

	/**
	 * Constructor.
	 *
	 * @param ReuseLicences $licences Reads `REUSE.toml`.
	 */
	public function __construct(
		private readonly ReuseLicences $licences = new ReuseLicences(),
	) {
	}//end __construct()

	/**
	 * The font families a stack names, without the system fonts and CSS generics.
	 *
	 * @param string $stack A CSS font stack.
	 *
	 * @return string[] The family names, in the stack's order.
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-font-is-redistributable-only-under-a-stated-licence
	 */
	public function familiesIn(string $stack): array {
		$families = [];
		foreach (explode(',', $stack) as $family) {
			$family = trim($family, " \t\n\r\"'");
			if ($family !== '' && in_array($family, TokenSetFontAuditService::SYSTEM_FAMILIES, true) === false) {
				$families[] = $family;
			}
		}

		return $families;
	}//end familiesIn()

	/**
	 * The font list: every family a set names that is not a system font.
	 *
	 * @param CatalogueEvidence $evidence The app root, the manifest and the `REUSE.toml` annotations.
	 * @param array<string, string[]> $families Family => the ids of the sets that name it.
	 *
	 * @return array<int, array<string, mixed>> The entries, by family name.
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-font-is-redistributable-only-under-a-stated-licence
	 */
	public function fontsOf(CatalogueEvidence $evidence, array $families): array {
		$shipped = $this->fontFiles(appPath: $evidence->appPath);
		ksort($families, SORT_STRING);

		$fonts = [];
		foreach ($families as $family => $ids) {
			$files = ($shipped[strtolower($family)] ?? []);
			$licence = $this->statedLicence(files: $files, annotations: $evidence->annotations);
			$font = new CatalogueFont(family: $family, sets: $ids, files: $files, licence: $licence);
			$fonts[] = $this->fontEntry(evidence: $evidence, font: $font);
		}

		return $fonts;
	}//end fontsOf()

	/**
	 * One entry of the font list.
	 *
	 * @param CatalogueEvidence $evidence The app root, the manifest and the `REUSE.toml` annotations.
	 * @param CatalogueFont $font The family.
	 *
	 * @return array<string, mixed> The entry.
	 */
	private function fontEntry(CatalogueEvidence $evidence, CatalogueFont $font): array {
		$verdict = $this->fontVerdict(evidence: $evidence, font: $font);

		return [
			'family' => $font->family,
			'sets' => $font->sets,
			'shipped' => ($font->files !== []),
			'files' => $font->files,
			'licence' => $verdict['licence'],
			'licence_files' => $verdict['licence_files'],
			'redistributable' => $verdict['redistributable'],
			'basis' => $verdict['basis'],
		];
	}//end fontEntry()

	/**
	 * What the repository says about one font family.
	 *
	 * @param CatalogueEvidence $evidence The app root, the manifest and the `REUSE.toml` annotations.
	 * @param CatalogueFont $font The family.
	 *
	 * @return array{licence: string|null, licence_files: string[], redistributable: bool|null, basis: string} The verdict.
	 */
	private function fontVerdict(CatalogueEvidence $evidence, CatalogueFont $font): array {
		return ($this->restrictedVerdict(evidence: $evidence, font: $font)
			?? $this->licensedVerdict(appPath: $evidence->appPath, font: $font)
			?? $this->declaredVerdict(evidence: $evidence, font: $font)
			?? [
				'licence' => $font->licence,
				'licence_files' => [],
				'redistributable' => null,
				'basis' => 'The repository neither ships this family under a stated licence nor says it may not be passed on.',
			]);
	}//end fontVerdict()

	/**
	 * The verdict for a family the repository says may not be passed on, while it still says so.
	 *
	 * @param CatalogueEvidence $evidence The app root, the manifest and the `REUSE.toml` annotations.
	 * @param CatalogueFont $font The family.
	 *
	 * @return array{licence: string|null, licence_files: string[], redistributable: bool, basis: string}|null The verdict, or null when it is not restricted.
	 */
	private function restrictedVerdict(CatalogueEvidence $evidence, CatalogueFont $font): ?array {
		$restricted = (self::RESTRICTED_FONTS[$font->family] ?? null);
		if ($restricted === null || str_contains($this->licences->read(path: $evidence->appPath . '/' . $restricted['source']), $restricted['quote']) === false) {
			return null;
		}

		return [
			'licence' => $font->licence,
			'licence_files' => [],
			'redistributable' => false,
			'basis' => $restricted['source'] . ': "' . $restricted['quote'] . '"',
		];
	}//end restrictedVerdict()

	/**
	 * The verdict for a family whose files carry a licence that allows passing them on.
	 *
	 * @param string $appPath The app root path.
	 * @param CatalogueFont $font The family.
	 *
	 * @return array{licence: string, licence_files: string[], redistributable: bool, basis: string}|null The verdict, or null when its licence does not allow it.
	 */
	private function licensedVerdict(string $appPath, CatalogueFont $font): ?array {
		$licence = $font->licence;
		if ($licence === null || isset(self::FONT_LICENCE_NOTICES[$licence]) === false) {
			return null;
		}

		return [
			'licence' => $licence,
			'licence_files' => $this->noticesFor(appPath: $appPath, font: $font),
			'redistributable' => true,
			'basis' => 'REUSE.toml names these files ' . $licence . ', and LICENSES/' . $licence . '.txt allows redistribution with its notice.',
		];
	}//end licensedVerdict()

	/**
	 * What a set's `font` block says about a family thematiq does not ship.
	 *
	 * @param CatalogueEvidence $evidence The app root, the manifest and the `REUSE.toml` annotations.
	 * @param CatalogueFont $font The family.
	 *
	 * @return array{licence: string, licence_files: string[], redistributable: bool, basis: string}|null The verdict, or null when it is shipped or no set describes it.
	 */
	private function declaredVerdict(CatalogueEvidence $evidence, CatalogueFont $font): ?array {
		$declared = $this->declaredBy(evidence: $evidence, font: $font);
		if ($font->files !== []) {
			return null;
		}

		return $declared;
	}//end declaredVerdict()

	/**
	 * The one licence `REUSE.toml` names for all of a family's files.
	 *
	 * @param string[] $files The font files.
	 * @param array<int, array{paths: string[], licence: string, override: bool}> $annotations The `REUSE.toml` annotations.
	 *
	 * @return string|null The SPDX identifier, or null when a file has none of its own or they differ.
	 */
	private function statedLicence(array $files, array $annotations): ?string {
		$licence = null;
		foreach ($files as $file) {
			$found = $this->ownLicenceOf(file: $file, annotations: $annotations);
			if ($found === null || ($licence !== null && $found !== $licence)) {
				return null;
			}

			$licence = $found;
		}

		return $licence;
	}//end statedLicence()

	/**
	 * The licence `REUSE.toml` names for one file by name.
	 *
	 * @param string $file The font file.
	 * @param array<int, array{paths: string[], licence: string, override: bool}> $annotations The `REUSE.toml` annotations.
	 *
	 * @return string|null The SPDX identifier, or null when the file has none of its own.
	 */
	private function ownLicenceOf(string $file, array $annotations): ?string {
		$found = $this->licences->licenceOf(file: $file, annotations: $annotations);
		if ($found['stated'] === false || $found['licence'] === null) {
			return null;
		}

		return $found['licence'];
	}//end ownLicenceOf()

	/**
	 * What a set's `font` block in `token-sets.json` says about a family it names and thematiq does not ship.
	 *
	 * @param CatalogueEvidence $evidence The app root, the manifest and the `REUSE.toml` annotations.
	 * @param CatalogueFont $font The family.
	 *
	 * @return array{licence: string, licence_files: string[], redistributable: bool, basis: string}|null The verdict, or null when no set describes it.
	 */
	private function declaredBy(CatalogueEvidence $evidence, CatalogueFont $font): ?array {
		foreach ($font->sets as $id) {
			$declared = ($evidence->manifest[$id]['font'] ?? null);
			if (is_array($declared) === false || strcasecmp((string)($declared['family'] ?? ''), $font->family) !== 0) {
				continue;
			}

			return [
				'licence' => trim((string)($declared['licence'] ?? '') . ', ' . (string)($declared['licenceHolder'] ?? ''), ', '),
				'licence_files' => [],
				'redistributable' => false,
				'basis' => 'token-sets.json (' . $id . '): the family is not shipped; an administrator uploads it.',
			];
		}

		return null;
	}//end declaredBy()

	/**
	 * The licence notices that must travel with a family's files: the notice file of its licence in each of their directories.
	 *
	 * @param string $appPath The app root path.
	 * @param CatalogueFont $font The family; its licence has a notice.
	 *
	 * @return string[] The repository-relative paths, sorted.
	 */
	private function noticesFor(string $appPath, CatalogueFont $font): array {
		$notice = self::FONT_LICENCE_NOTICES[(string)$font->licence];
		$notices = [];
		foreach ($font->files as $file) {
			$candidate = dirname($file) . '/' . $notice;
			if (is_file($appPath . '/' . $candidate) === true) {
				$notices[$candidate] = true;
			}
		}

		ksort($notices, SORT_STRING);

		return array_keys($notices);
	}//end noticesFor()

	/**
	 * The font files each family has an `@font-face` for, in the stylesheets under `css/`.
	 *
	 * @param string $appPath The app root path.
	 *
	 * @return array<string, string[]> Lower-cased family => repository-relative files that exist, sorted.
	 */
	private function fontFiles(string $appPath): array {
		$families = [];
		foreach ($this->stylesheets(appPath: $appPath) as $sheet) {
			foreach ($this->facesIn(sheet: new FontStylesheet(appPath: $appPath, path: $sheet)) as $family => $files) {
				$families[$family] = (($families[$family] ?? []) + $files);
			}
		}

		return array_map(
			static function (array $files): array {
				ksort($files, SORT_STRING);

				return array_keys($files);
			},
			$families
		);
	}//end fontFiles()

	/**
	 * The stylesheets that may declare font faces, sorted.
	 *
	 * @param string $appPath The app root path.
	 *
	 * @return string[] The absolute paths.
	 */
	private function stylesheets(string $appPath): array {
		$sheets = glob($appPath . '/css/systems/*/*.css');
		if ($sheets === false) {
			$sheets = [];
		}

		$sheets[] = $appPath . '/css/fonts.css';
		sort($sheets, SORT_STRING);

		return $sheets;
	}//end stylesheets()

	/**
	 * The font files one stylesheet has an `@font-face` for.
	 *
	 * @param FontStylesheet $sheet The stylesheet.
	 *
	 * @return array<string, array<string, true>> Lower-cased family => repository-relative files that exist, in the sheet's order.
	 */
	private function facesIn(FontStylesheet $sheet): array {
		$css = (string)preg_replace('#/\*.*?\*/#s', '', $this->licences->read(path: $sheet->path));
		preg_match_all('/@font-face\s*\{([^}]*)\}/', $css, $faces);

		$families = [];
		foreach ($faces[1] as $face) {
			$family = $this->familyOf(face: $face);
			if ($family === null) {
				continue;
			}

			foreach ($this->shippedFilesOf(sheet: $sheet, face: $face) as $file) {
				$families[$family][$file] = true;
			}
		}

		return $families;
	}//end facesIn()

	/**
	 * The lower-cased family an `@font-face` body declares.
	 *
	 * @param string $face The body of the `@font-face` rule.
	 *
	 * @return string|null The family, or null when it declares none.
	 */
	private function familyOf(string $face): ?string {
		if (preg_match('/font-family:\s*[\'"]?([^\'";]+)[\'"]?\s*;/', $face, $name) !== 1) {
			return null;
		}

		return strtolower(trim($name[1]));
	}//end familyOf()

	/**
	 * The files an `@font-face` body points at that exist inside the repository.
	 *
	 * @param FontStylesheet $sheet The stylesheet the rule is in.
	 * @param string $face The body of the `@font-face` rule.
	 *
	 * @return string[] The repository-relative paths, in the rule's order.
	 */
	private function shippedFilesOf(FontStylesheet $sheet, string $face): array {
		preg_match_all('/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/', $face, $urls);

		$files = [];
		foreach ($urls[1] as $url) {
			$file = $sheet->directory() . '/' . $url;
			if (str_contains($url, '..') === false && is_file($sheet->appPath . '/' . $file) === true) {
				$files[] = $file;
			}
		}

		return $files;
	}//end shippedFilesOf()
}//end class
