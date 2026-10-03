<?php

/**
 * Thematiq own component store.
 *
 * The components an administrator builds in the playground's "Your component" stage:
 * one JSON file per component in the app's data folder `playground-components`, at most
 * 20, at most 64 KB of HTML and CSS together. The text is stored raw and cleaned every
 * time it is rendered (js/lib/markupSanitizer.js), so a saved component can never skip
 * the cleaning. Nothing here changes what any user sees, so there is no audit entry.
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
 * @spec openspec/specs/own-component-preview/spec.md#requirement-an-administrator-saves-own-components
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use InvalidArgumentException;
use OCA\Thematiq\AppInfo\Application;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

/**
 * Store and list own components.
 *
 * @spec openspec/specs/own-component-preview/spec.md#requirement-an-administrator-saves-own-components
 */
class OwnComponentService {

	/**
	 * The app data folder.
	 *
	 * @var string
	 */
	public const FOLDER = 'playground-components';

	/**
	 * The most components kept.
	 *
	 * @var int
	 */
	public const MAX_COMPONENTS = 20;

	/**
	 * The most bytes of HTML and CSS together, per component.
	 *
	 * @var int
	 */
	public const MAX_BYTES = 65536;

	/**
	 * The slug rule: lower-case words joined by single hyphens, at most 40 characters.
	 *
	 * @var string
	 */
	private const SLUG_PATTERN = '/^(?=.{1,40}$)[a-z0-9]+(-[a-z0-9]+)*$/';

	/**
	 * Constructor.
	 *
	 * @param IAppDataFactory $appDataFactory The app data factory.
	 */
	public function __construct(
		private readonly IAppDataFactory $appDataFactory,
	) {
	}//end __construct()

	/**
	 * The slug of a component name: lower case, letters and digits joined by dashes.
	 *
	 * @param string $name The name.
	 *
	 * @return string The slug; '' when nothing usable is left.
	 *
	 * @spec openspec/specs/own-component-preview/spec.md#requirement-an-administrator-saves-own-components
	 */
	public static function slugFor(string $name): string {
		$ascii = (string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
		$slug  = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');

		return substr($slug, 0, 40);
	}//end slugFor()

	/**
	 * Whether a slug follows the rule.
	 *
	 * @param string $slug The slug.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/own-component-preview/spec.md#requirement-an-administrator-saves-own-components
	 */
	public static function isSlug(string $slug): bool {
		return preg_match(self::SLUG_PATTERN, $slug) === 1;
	}//end isSlug()

	/**
	 * Every saved component, by name.
	 *
	 * @return array<int, array{name: string, slug: string, html: string, css: string, updatedAt: string}>
	 *
	 * @spec openspec/specs/own-component-preview/spec.md#requirement-an-administrator-saves-own-components
	 */
	public function list(): array {
		$out = [];
		foreach ($this->folder()->getDirectoryListing() as $file) {
			$data = json_decode($file->getContent(), true);
			if (is_array($data) === true && self::isSlug(slug: (string)($data['slug'] ?? '')) === true) {
				$out[] = [
					'name' => (string)($data['name'] ?? ''),
					'slug' => (string)$data['slug'],
					'html' => (string)($data['html'] ?? ''),
					'css' => (string)($data['css'] ?? ''),
					'updatedAt' => (string)($data['updatedAt'] ?? ''),
				];
			}
		}

		usort($out, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

		return $out;
	}//end list()

	/**
	 * Save a component by name, replacing one with the same slug.
	 *
	 * @param string $name The name.
	 * @param string $html The HTML, raw.
	 * @param string $css  The CSS, raw.
	 *
	 * @return array{name: string, slug: string, html: string, css: string, updatedAt: string} The stored component.
	 *
	 * @throws InvalidArgumentException 400: `name`, `size` or `count`.
	 *
	 * @spec openspec/specs/own-component-preview/spec.md#requirement-an-administrator-saves-own-components
	 */
	public function save(string $name, string $html, string $css): array {
		$name = trim($name);
		$slug = self::slugFor(name: $name);
		if ($name === '' || mb_strlen($name) > 80 || self::isSlug(slug: $slug) === false) {
			throw new InvalidArgumentException('name', 400);
		}

		if ((strlen($html) + strlen($css)) > self::MAX_BYTES) {
			throw new InvalidArgumentException('size', 400);
		}

		$folder = $this->folder();
		$file   = $slug . '.json';
		if ($folder->fileExists($file) === false && count($folder->getDirectoryListing()) >= self::MAX_COMPONENTS) {
			throw new InvalidArgumentException('count', 400);
		}

		$component = ['name' => $name, 'slug' => $slug, 'html' => $html, 'css' => $css, 'updatedAt' => gmdate(DATE_ATOM)];
		$json      = (string)json_encode($component, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if ($folder->fileExists($file) === true) {
			$folder->getFile($file)->putContent($json);
			return $component;
		}

		$folder->newFile($file, $json);

		return $component;
	}//end save()

	/**
	 * Remove a component.
	 *
	 * @param string $slug The slug.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException 404 for an unknown slug.
	 *
	 * @spec openspec/specs/own-component-preview/spec.md#requirement-an-administrator-saves-own-components
	 */
	public function delete(string $slug): void {
		$folder = $this->folder();
		if (self::isSlug(slug: $slug) === false || $folder->fileExists($slug . '.json') === false) {
			throw new InvalidArgumentException('unknown', 404);
		}

		$folder->getFile($slug . '.json')->delete();
	}//end delete()

	/**
	 * The components folder, created on first use.
	 *
	 * @return ISimpleFolder
	 */
	private function folder(): ISimpleFolder {
		$root = $this->appDataFactory->get(Application::APP_ID);

		try {
			return $root->getFolder(self::FOLDER);
		} catch (NotFoundException $e) {
			return $root->newFolder(self::FOLDER);
		}
	}//end folder()
}//end class
