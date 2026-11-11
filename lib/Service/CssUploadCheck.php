<?php

/**
 * Whether an uploaded file may be read as a stylesheet.
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
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-11
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * Checks the overrides import's upload: present, at most 256 KB, named .css and detected as text.
 *
 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-11
 */
final class CssUploadCheck {

	/**
	 * Why the uploaded file is refused, or null when it may be read.
	 *
	 * @param mixed $file The uploaded file's entry.
	 *
	 * @return UploadRefusal|null The refusal, or null.
	 *
	 * @spec openspec/changes/archive/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-11
	 */
	public function refusal(mixed $file): UploadRefusal|null {
		if (empty($file) === true || isset($file['tmp_name']) === false) {
			return new UploadRefusal(status: 400, error: 'No file uploaded');
		}

		$maxSize = (256 * 1024);
		if ($file['size'] > $maxSize) {
			return new UploadRefusal(status: 413, error: 'File exceeds the 256 KB size limit');
		}

		return $this->typeRefusal(file: $file);
	}//end refusal()

	/**
	 * Refuse an upload that is not a .css file by its name or, detected on the server, by its content.
	 *
	 * @param mixed $file The uploaded file's entry.
	 *
	 * @return UploadRefusal|null A 415 refusal, or null when it is a stylesheet.
	 */
	private function typeRefusal(mixed $file): UploadRefusal|null {
		// Validate file extension.
		$originalName = $file['name'] ?? '';
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		if ($extension !== 'css') {
			return new UploadRefusal(status: 415, error: 'Only .css files are accepted');
		}

		// Validate MIME type via server-side detection (ignore client-provided type).
		$tmpName = $file['tmp_name'];
		$mimeType = mime_content_type($tmpName);
		// Accept text/css, text/plain (editors often send this for .css), and
		// application/octet-stream (generic fallback from some browsers).
		$allowedMimes = ['text/css', 'text/plain', 'application/octet-stream'];
		if (in_array($mimeType, $allowedMimes, true) === false) {
			return new UploadRefusal(status: 415, error: 'File does not appear to be a CSS file');
		}

		return null;
	}//end typeRefusal()
}//end class
