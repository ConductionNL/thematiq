<?php

/**
 * Thematiq own token store.
 *
 * An administrator's own tokens: names under `--nldesign-org-`, typed like the
 * editor's tokens, stored in appconfig `own_tokens` and rendered into every
 * custom overrides file after the editor's values.
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
 * @spec openspec/specs/own-tokens/spec.md#requirement-an-administrator-adds-an-own-token-from-the-token-editor
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use InvalidArgumentException;
use OCA\Thematiq\AppInfo\Application;
use OCP\IConfig;

/**
 * Store, check and render own tokens.
 *
 * @spec openspec/specs/own-tokens/spec.md#requirement-an-administrator-adds-an-own-token-from-the-token-editor
 */
class OwnTokenService {

	/**
	 * Every own token's name starts with this; nothing shipped uses it.
	 *
	 * @var string
	 */
	public const PREFIX = '--nldesign-org-';

	/**
	 * The appconfig key holding the tokens as a JSON object keyed by name.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'own_tokens';

	/**
	 * The types an own token can have: the editor's types.
	 *
	 * @var array<int, string>
	 */
	public const TYPES = ['color', 'text', 'duration', 'easing'];

	/**
	 * The slug rule: lower-case words joined by single hyphens, at most 48 characters.
	 *
	 * @var string
	 */
	private const SLUG_PATTERN = '/^(?=.{1,48}$)[a-z0-9]+(-[a-z0-9]+)*$/';

	/**
	 * The longest label and description.
	 *
	 * @var array<string, int>
	 */
	private const MAX_LENGTH = ['label' => 80, 'description' => 500];

	/**
	 * Constructor.
	 *
	 * @param IConfig             $config  The app config store.
	 * @param TokenValueValidator $values  The value grammar per type.
	 * @param DeprecationRecords  $records The deprecations, written as a comment above a deprecated token.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly TokenValueValidator $values,
		private readonly DeprecationRecords $records,
	) {
	}//end __construct()

	/**
	 * Every own token, by name.
	 *
	 * @return array<string, array<string, string>> Name => {label, type, value, darkValue?, description?, createdAt, updatedAt}.
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-an-administrator-adds-an-own-token-from-the-token-editor
	 */
	public function list(): array {
		$decoded = json_decode($this->config->getAppValue(Application::APP_ID, self::CONFIG_KEY, '{}'), true);
		if (is_array($decoded) === false) {
			return [];
		}

		return array_filter(
			$decoded,
			static fn ($token, $name): bool => is_array($token) === true && self::isOwnName(name: (string)$name),
			ARRAY_FILTER_USE_BOTH
		);
	}//end list()

	/**
	 * Whether an own token by this name exists.
	 *
	 * @param string $name The full name.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-an-administrator-adds-an-own-token-from-the-token-editor
	 */
	public function exists(string $name): bool {
		return isset($this->list()[$name]);
	}//end exists()

	/**
	 * Add a token.
	 *
	 * @param array<string, mixed> $input {slug, label, type, value, darkValue?, description?}.
	 *
	 * @return array<string, string> The stored token, with its `name`.
	 *
	 * @throws InvalidArgumentException 400 for a wrong field or a name already taken.
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-an-administrator-adds-an-own-token-from-the-token-editor
	 */
	public function create(array $input): array {
		$slug = (string)($input['slug'] ?? '');
		if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
			throw new InvalidArgumentException('name', 400);
		}

		$name   = self::PREFIX . $slug;
		$tokens = $this->list();
		if (isset($tokens[$name]) === true) {
			throw new InvalidArgumentException('duplicate', 400);
		}

		$now = gmdate(DATE_ATOM);
		$tokens[$name] = array_merge($this->checked(input: $input), ['createdAt' => $now, 'updatedAt' => $now]);
		$this->persist(tokens: $tokens);

		return ['name' => $name] + $tokens[$name];
	}//end create()

	/**
	 * Change a token's label, type, value, dark value or description. The name stays.
	 *
	 * @param string               $name  The full name.
	 * @param array<string, mixed> $input {label, type, value, darkValue?, description?}.
	 *
	 * @return array<string, string> The stored token, with its `name`.
	 *
	 * @throws InvalidArgumentException 400 for a wrong field, 404 for an unknown name.
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-an-administrator-adds-an-own-token-from-the-token-editor
	 */
	public function update(string $name, array $input): array {
		$tokens = $this->list();
		if (isset($tokens[$name]) === false) {
			throw new InvalidArgumentException('unknown', 404);
		}

		$tokens[$name] = array_merge(
			$this->checked(input: $input),
			['createdAt' => (string)($tokens[$name]['createdAt'] ?? gmdate(DATE_ATOM)), 'updatedAt' => gmdate(DATE_ATOM)]
		);
		$this->persist(tokens: $tokens);

		return ['name' => $name] + $tokens[$name];
	}//end update()

	/**
	 * Remove a token.
	 *
	 * @param string $name The full name.
	 *
	 * @return array<string, string> The removed token.
	 *
	 * @throws InvalidArgumentException 404 for an unknown name.
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-an-administrator-adds-an-own-token-from-the-token-editor
	 */
	public function remove(string $name): array {
		$tokens = $this->list();
		if (isset($tokens[$name]) === false) {
			throw new InvalidArgumentException('unknown', 404);
		}

		$removed = $tokens[$name];
		unset($tokens[$name]);
		$this->persist(tokens: $tokens);

		return $removed;
	}//end remove()

	/**
	 * Check a whole store, as a configuration bundle carries it, without storing it.
	 *
	 * @param array<string, mixed> $tokens Name => token.
	 *
	 * @return array<string, array<string, string>> The checked tokens.
	 *
	 * @throws InvalidArgumentException 400 when any token fails its checks.
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-travel-with-the-configuration-bundle
	 */
	public function checkAll(array $tokens): array {
		$clean = [];
		$now   = gmdate(DATE_ATOM);
		foreach ($tokens as $name => $token) {
			if (self::isOwnName(name: (string)$name) === false || is_array($token) === false) {
				throw new InvalidArgumentException('name', 400);
			}

			$clean[(string)$name] = array_merge(
				$this->checked(input: $token),
				['createdAt' => (string)($token['createdAt'] ?? $now), 'updatedAt' => (string)($token['updatedAt'] ?? $now)]
			);
		}

		return $clean;
	}//end checkAll()

	/**
	 * Replace the whole store, as a configuration bundle import does. Each token is checked.
	 *
	 * @param array<string, mixed> $tokens Name => token.
	 *
	 * @return int The number stored.
	 *
	 * @throws InvalidArgumentException 400 when any token fails its checks; nothing is stored then.
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-travel-with-the-configuration-bundle
	 */
	public function replaceAll(array $tokens): int {
		$clean = $this->checkAll(tokens: $tokens);
		$this->persist(tokens: $clean);

		return count($clean);
	}//end replaceAll()

	/**
	 * The tokens as they go into an overrides file, each deprecated one with its notice.
	 *
	 * @return OwnTokenCss The light and dark values, with the notices.
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-are-served-in-both-themes
	 */
	public function css(): OwnTokenCss {
		$light = [];
		$dark  = [];
		foreach ($this->list() as $name => $token) {
			$light[$name] = (string)($token['value'] ?? '');
			if (($token['darkValue'] ?? '') !== '') {
				$dark[$name] = (string)$token['darkValue'];
			}
		}

		return new OwnTokenCss(light: $light, dark: $dark, comments: array_intersect_key($this->records->comments(), $light));
	}//end css()

	/**
	 * Whether a name is an own token name: the prefix and a valid slug. Checked before any
	 * name reaches the CSS writer.
	 *
	 * @param string $name The full name.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-an-administrator-adds-an-own-token-from-the-token-editor
	 */
	public static function isOwnName(string $name): bool {
		return str_starts_with($name, self::PREFIX) === true
			&& preg_match(self::SLUG_PATTERN, substr($name, strlen(self::PREFIX))) === 1;
	}//end isOwnName()

	/**
	 * The checked fields of a token.
	 *
	 * @param array<string, mixed> $input The submitted fields.
	 *
	 * @return array<string, string> Shape: {label, type, value, darkValue?, description?}.
	 *
	 * @throws InvalidArgumentException 400 naming the field that fails.
	 */
	private function checked(array $input): array {
		$label = trim((string)($input['label'] ?? ''));
		if ($label === '' || mb_strlen($label) > self::MAX_LENGTH['label']) {
			throw new InvalidArgumentException('label', 400);
		}

		$type = (string)($input['type'] ?? '');
		if (in_array($type, self::TYPES, true) === false) {
			throw new InvalidArgumentException('type', 400);
		}

		$token = ['label' => $label, 'type' => $type, 'value' => $this->checkedValue(value: $input['value'] ?? null, type: $type, field: 'value')];

		$dark = trim((string)($input['darkValue'] ?? ''));
		if ($dark !== '') {
			// Only a colour has a dark value.
			$token['darkValue'] = $this->checkedValue(value: $dark, type: 'color', field: 'darkValue');
			if ($type !== 'color') {
				throw new InvalidArgumentException('darkValue', 400);
			}
		}

		$description = trim((string)($input['description'] ?? ''));
		if (mb_strlen($description) > self::MAX_LENGTH['description']) {
			throw new InvalidArgumentException('description', 400);
		}

		if ($description !== '') {
			$token['description'] = $description;
		}

		return $token;
	}//end checked()

	/**
	 * A value that passes the injection filter and its type.
	 *
	 * @param mixed  $value The submitted value.
	 * @param string $type  The type to check against.
	 * @param string $field The field name, for the error.
	 *
	 * @return string The trimmed value.
	 *
	 * @throws InvalidArgumentException 400 naming the field.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - the injection filter is a pure function shared with the CSS writer
	 */
	private function checkedValue(mixed $value, string $type, string $field): string {
		if (is_string($value) === false || OverridesCssBuilder::isUnsafeValue(value: $value) === true || str_contains($value, "\n") === true) {
			throw new InvalidArgumentException($field, 400);
		}

		if ($this->values->isValid(type: $type, value: $value) === false) {
			throw new InvalidArgumentException($field, 400);
		}

		return trim($value);
	}//end checkedValue()

	/**
	 * Store the tokens.
	 *
	 * @param array<string, array<string, string>> $tokens Name => token.
	 *
	 * @return void
	 */
	private function persist(array $tokens): void {
		ksort($tokens);
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_KEY, (string)json_encode($tokens, JSON_UNESCAPED_SLASHES));
	}//end persist()
}//end class
