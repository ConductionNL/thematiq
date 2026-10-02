<?php

/**
 * Thematiq image checks for uploaded brand images.
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

/**
 * The type of an uploaded image from its first bytes (never from the name or
 * the browser's claim), and whether an SVG holds active content.
 *
 * @spec openspec/specs/per-app-theming/spec.md
 */
class ImageSniffer {

	/**
	 * The image type of a file, from its first bytes.
	 *
	 * @param string $bytes The file.
	 *
	 * @return string|null `image/png`, `image/jpeg`, `image/webp` or `image/svg+xml`; null otherwise.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public static function type(string $bytes): ?string {
		if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n") === true) {
			return 'image/png';
		}

		if (str_starts_with($bytes, "\xFF\xD8\xFF") === true) {
			return 'image/jpeg';
		}

		if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
			return 'image/webp';
		}

		if (preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!DOCTYPE[^>]*>\s*)?<svg[\s>]/is', $bytes) === 1) {
			return 'image/svg+xml';
		}

		return null;
	}//end type()

	/**
	 * Whether an SVG holds no script, event handler, foreign object, entity or `javascript:` address.
	 *
	 * @param string $svg The SVG text.
	 *
	 * @return bool True when safe to serve as an image.
	 *
	 * @spec openspec/specs/per-app-theming/spec.md
	 */
	public static function isSafeSvg(string $svg): bool {
		$patterns = ['/<\s*script/i', '/<\s*foreignObject/i', '/\son[a-z]+\s*=/i', '/javascript\s*:/i', '/<!ENTITY/i', '/data:text\/html/i'];
		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $svg) === 1) {
				return false;
			}
		}

		return true;
	}//end isSafeSvg()
}//end class
