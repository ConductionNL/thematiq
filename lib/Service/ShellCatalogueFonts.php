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
	 * @param string $appPath The app root path.
	 * @param array<string, string[]> $families Family => the ids of the sets that name it.
	 * @param array<string, array<string, mixed>> $manifest The manifest entries, by id.
	 * @param array<int, array{paths: string[], licence: string, override: bool}> $annotations The `REUSE.toml` annotations.
	 *
	 * @return array<int, array<string, mixed>> The entries, by family name.
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-font-is-redistributable-only-under-a-stated-licence
	 */
	public function fontsOf(string $appPath, array $families, array $manifest, array $annotations): array {
		$shipped = $this->fontFiles(appPath: $appPath);
		ksort($families, SORT_STRING);

		$fonts = [];
		foreach ($families as $family => $ids) {
			$files = ($shipped[strtolower($family)] ?? []);
			$verdict = $this->fontVerdict(appPath: $appPath, family: $family, files: $files, ids: $ids, manifest: $manifest, annotations: $annotations);
			$fonts[] = [
				'family' => $family,
				'sets' => $ids,
				'shipped' => ($files !== []),
				'files' => $files,
				'licence' => $verdict['licence'],
				'licence_files' => $verdict['licence_files'],
				'redistributable' => $verdict['redistributable'],
				'basis' => $verdict['basis'],
			];
		}

		return $fonts;
	}//end fontsOf()

	/**
	 * What the repository says about one font family.
	 *
	 * @param string $appPath The app root path.
	 * @param string $family The family name.
	 * @param string[] $files The font files thematiq ships for it.
	 * @param string[] $ids The sets that name it.
	 * @param array<string, array<string, mixed>> $manifest The manifest entries, by id.
	 * @param array<int, array{paths: string[], licence: string, override: bool}> $annotations The `REUSE.toml` annotations.
	 *
	 * @return array{licence: string|null, licence_files: string[], redistributable: bool|null, basis: string} The verdict.
	 */
	private function fontVerdict(string $appPath, string $family, array $files, array $ids, array $manifest, array $annotations): array {
		$licence = $this->statedLicence(files: $files, annotations: $annotations);

		$restricted = (self::RESTRICTED_FONTS[$family] ?? null);
		if ($restricted !== null && str_contains($this->licences->read(path: $appPath . '/' . $restricted['source']), $restricted['quote']) === true) {
			return [
				'licence' => $licence,
				'licence_files' => [],
				'redistributable' => false,
				'basis' => $restricted['source'] . ': "' . $restricted['quote'] . '"',
			];
		}

		if ($licence !== null && isset(self::FONT_LICENCE_NOTICES[$licence]) === true) {
			return [
				'licence' => $licence,
				'licence_files' => $this->noticesFor(appPath: $appPath, files: $files, notice: self::FONT_LICENCE_NOTICES[$licence]),
				'redistributable' => true,
				'basis' => 'REUSE.toml names these files ' . $licence . ', and LICENSES/' . $licence . '.txt allows redistribution with its notice.',
			];
		}

		$declared = $this->declaredBy(family: $family, ids: $ids, manifest: $manifest);
		if ($declared !== null && $files === []) {
			return $declared;
		}

		return [
			'licence' => $licence,
			'licence_files' => [],
			'redistributable' => null,
			'basis' => 'The repository neither ships this family under a stated licence nor says it may not be passed on.',
		];
	}//end fontVerdict()

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
			$found = $this->licences->licenceOf(file: $file, annotations: $annotations);
			if ($found['stated'] === false || $found['licence'] === null || ($licence !== null && $found['licence'] !== $licence)) {
				return null;
			}

			$licence = $found['licence'];
		}

		return $licence;
	}//end statedLicence()

	/**
	 * What a set's `font` block in `token-sets.json` says about a family it names and thematiq does not ship.
	 *
	 * @param string $family The family name.
	 * @param string[] $ids The sets that name it.
	 * @param array<string, array<string, mixed>> $manifest The manifest entries, by id.
	 *
	 * @return array{licence: string, licence_files: string[], redistributable: bool, basis: string}|null The verdict, or null when no set describes it.
	 */
	private function declaredBy(string $family, array $ids, array $manifest): ?array {
		foreach ($ids as $id) {
			$declared = ($manifest[$id]['font'] ?? null);
			if (is_array($declared) === false || strcasecmp((string)($declared['family'] ?? ''), $family) !== 0) {
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
	 * The licence notices that must travel with a set of font files: the notice file in each of their directories.
	 *
	 * @param string $appPath The app root path.
	 * @param string[] $files The font files.
	 * @param string $notice The notice's file name.
	 *
	 * @return string[] The repository-relative paths, sorted.
	 */
	private function noticesFor(string $appPath, array $files, string $notice): array {
		$notices = [];
		foreach ($files as $file) {
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
		$sheets = glob($appPath . '/css/systems/*/*.css');
		if ($sheets === false) {
			$sheets = [];
		}

		$sheets[] = $appPath . '/css/fonts.css';
		sort($sheets, SORT_STRING);

		$families = [];
		foreach ($sheets as $sheet) {
			$css = (string)preg_replace('#/\*.*?\*/#s', '', $this->licences->read(path: $sheet));
			$directory = dirname(substr($sheet, (strlen($appPath) + 1)));
			preg_match_all('/@font-face\s*\{([^}]*)\}/', $css, $faces);
			foreach ($faces[1] as $face) {
				if (preg_match('/font-family:\s*[\'"]?([^\'";]+)[\'"]?\s*;/', $face, $name) !== 1) {
					continue;
				}

				preg_match_all('/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/', $face, $urls);
				foreach ($urls[1] as $url) {
					$file = $directory . '/' . $url;
					if (str_contains($url, '..') === false && is_file($appPath . '/' . $file) === true) {
						$families[strtolower(trim($name[1]))][$file] = true;
					}
				}
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
}//end class
