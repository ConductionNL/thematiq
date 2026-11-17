<?php

/**
 * Thematiq Bundled Font Library
 *
 * The approved fonts the app ships: every family the nldesign font stylesheet
 * declares with `@font-face`, whose files `REUSE.toml` names under one
 * licence. An administrator can assign one of these to the body or heading
 * role without uploading a file.
 *
 * @category Service
 * @package  OCA\Thematiq\Service
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/custom-fonts/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Reads the bundled font families, their faces and their licence.
 *
 * @spec openspec/specs/custom-fonts/spec.md
 */
class BundledFontLibrary {

	/**
	 * The stylesheet that declares the bundled faces, relative to the app root.
	 *
	 * @var string
	 */
	public const STYLESHEET = 'css/systems/nldesign/fonts.css';

	/**
	 * The directory the stylesheet's relative `url()`s resolve against.
	 *
	 * @var string
	 */
	public const FONT_DIR = 'css/systems/nldesign/';

	/**
	 * The app root path.
	 *
	 * @var string
	 */
	private string $appPath;

	/**
	 * The library, read once per request.
	 *
	 * @var array<string, array{name: string, licence: string, faces: list<array{file: string, weight: string, style: string}>}>|null
	 */
	private ?array $families = null;

	/**
	 * Constructor.
	 *
	 * @param string        $appPath  The app root path; empty means this app's own root.
	 * @param ReuseLicences $licences Reads `REUSE.toml`.
	 */
	public function __construct(
		string $appPath = '',
		private readonly ReuseLicences $licences = new ReuseLicences(),
	) {
		$this->appPath = $appPath;
		if ($appPath === '') {
			$this->appPath = dirname(__DIR__, 2);
		}
	}//end __construct()

	/**
	 * The approved families, by name, each with its licence and faces.
	 *
	 * A family is approved when `REUSE.toml` names every one of its files under
	 * one and the same licence; a family whose files carry no stated licence,
	 * or two different ones, is left out.
	 *
	 * @return array<string, array{name: string, licence: string, faces: list<array{file: string, weight: string, style: string}>}>
	 *
	 * @spec openspec/specs/custom-fonts/spec.md#requirement-an-administrator-picks-a-font-from-an-approved-library
	 */
	public function families(): array {
		if ($this->families !== null) {
			return $this->families;
		}

		$annotations = $this->licences->annotations(appPath: $this->appPath);
		$families = [];
		foreach ($this->facesByFamily() as $name => $faces) {
			$licence = $this->licenceOf(faces: $faces, annotations: $annotations);
			if ($licence === null) {
				continue;
			}

			$families[$name] = ['name' => $name, 'licence' => $licence, 'faces' => $faces];
		}

		ksort($families, SORT_STRING | SORT_FLAG_CASE);
		$this->families = $families;

		return $families;
	}//end families()

	/**
	 * One approved family, or null when the library has none by that name.
	 *
	 * @param string $name The family name, as listed.
	 *
	 * @return array{name: string, licence: string, faces: list<array{file: string, weight: string, style: string}>}|null
	 *
	 * @spec openspec/specs/custom-fonts/spec.md#requirement-an-administrator-picks-a-font-from-an-approved-library
	 */
	public function family(string $name): ?array {
		return ($this->families()[$name] ?? null);
	}//end family()

	/**
	 * The WOFF2 faces the stylesheet declares, grouped by family.
	 *
	 * @return array<string, list<array{file: string, weight: string, style: string}>>
	 */
	private function facesByFamily(): array {
		$css = $this->licences->read(path: $this->appPath . '/' . self::STYLESHEET);
		preg_match_all('/@font-face\s*\{([^}]*)\}/', $css, $blocks);

		$families = [];
		foreach ($blocks[1] as $block) {
			$face = $this->faceIn(block: $block);
			if ($face === null) {
				continue;
			}

			$families[$face['family']][] = ['file' => $face['file'], 'weight' => $face['weight'], 'style' => $face['style']];
		}

		return $families;
	}//end facesByFamily()

	/**
	 * The family, WOFF2 file, weight and style of one `@font-face` block.
	 *
	 * @param string $block The block's body.
	 *
	 * @return array{family: string, file: string, weight: string, style: string}|null Null without a family or a WOFF2 file.
	 */
	private function faceIn(string $block): ?array {
		if (preg_match('/font-family:\s*[\'"]([^\'"]+)[\'"]/', $block, $family) !== 1
			|| preg_match('/url\([\'"]?([^\'")]+\.woff2)[\'"]?\)/', $block, $file) !== 1
		) {
			return null;
		}

		$weight = '400';
		if (preg_match('/font-weight:\s*([0-9 ]+);/', $block, $match) === 1) {
			$weight = trim($match[1]);
		}

		$style = 'normal';
		if (preg_match('/font-style:\s*([a-z]+);/', $block, $match) === 1) {
			$style = $match[1];
		}

		return ['family' => $family[1], 'file' => self::FONT_DIR . $file[1], 'weight' => $weight, 'style' => $style];
	}//end faceIn()

	/**
	 * The one licence `REUSE.toml` states for all of a family's files.
	 *
	 * @param list<array{file: string, weight: string, style: string}> $faces       The faces.
	 * @param array<int, array{paths: string[], licence: string, override: bool}> $annotations The annotations.
	 *
	 * @return string|null Null when a file has no stated licence or the files disagree.
	 */
	private function licenceOf(array $faces, array $annotations): ?string {
		$licence = null;
		foreach ($faces as $face) {
			$found = $this->licences->licenceOf(file: $face['file'], annotations: $annotations);
			if ($found['stated'] === false || ($licence !== null && $found['licence'] !== $licence)) {
				return null;
			}

			$licence = $found['licence'];
		}

		return $licence;
	}//end licenceOf()
}//end class
