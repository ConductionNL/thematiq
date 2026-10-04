<?php

/**
 * Thematiq token deprecations.
 *
 * A deprecation is a record about a token name: how serious it is, which token
 * replaces it, and when it may be removed. It never changes a value. Stored in
 * appconfig `token_deprecations`, read by consuming apps through
 * `GET /api/token-deprecations`, and written as a comment above a deprecated
 * own token in the overrides file.
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
 * @spec openspec/specs/token-deprecations/spec.md#requirement-an-administrator-deprecates-a-token
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use InvalidArgumentException;
use OCA\Thematiq\AppInfo\Application;
use OCP\App\IAppManager;

/**
 * Record, check and publish token deprecations.
 *
 * @spec openspec/specs/token-deprecations/spec.md#requirement-an-administrator-deprecates-a-token
 */
class TokenDeprecationService {

	/**
	 * The appconfig key holding the records as a JSON object keyed by token name.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = DeprecationRecords::CONFIG_KEY;

	/**
	 * The severities. Tokens Studio writes `warning` and `error`; `error` maps to `critical`.
	 *
	 * @var array<int, string>
	 */
	public const SEVERITIES = ['info', 'warning', 'critical'];

	/**
	 * Any thematiq token name can be deprecated.
	 *
	 * @var string
	 */
	private const NAME_PATTERN = '/^--nldesign-[a-z0-9]+(-[a-z0-9]+)*$/';

	/**
	 * Constructor.
	 *
	 * @param DeprecationRecords $records The stored records.
	 * @param OwnTokenService $ownTokens The own tokens, which can be replacements.
	 * @param IAppManager $appManager For the thematiq vocabulary in `defaults.css`.
	 * @param CssParserService $cssParser Reads that vocabulary.
	 */
	public function __construct(
		private readonly DeprecationRecords $records,
		private readonly OwnTokenService $ownTokens,
		private readonly IAppManager $appManager,
		private readonly CssParserService $cssParser,
	) {
	}//end __construct()

	/**
	 * Every record, with `due` (the removal date has passed) and `state`.
	 *
	 * @param string|null $today The date to compare against (`Y-m-d`), today when null.
	 *
	 * @return array<string, array<string, mixed>> Token name => record.
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-an-administrator-deprecates-a-token
	 */
	public function list(?string $today = null): array {
		$today = ($today ?? gmdate('Y-m-d'));
		$records = [];
		foreach ($this->records->all() as $token => $record) {
			$record['state'] = (string)($record['state'] ?? 'active');
			$record['due'] = (($record['removalDate'] ?? '') !== '' && (string)$record['removalDate'] < $today);
			$records[$token] = $record;
		}

		return $records;
	}//end list()

	/**
	 * The public shape for consuming apps: names and dates, no user data.
	 *
	 * @return array<int, array<string, mixed>> Shape: {token, severity, replacement, removalDate, message, deprecatedAt, due}.
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-consuming-apps-can-read-every-deprecation
	 */
	public function publicList(): array {
		$out = [];
		foreach ($this->list() as $token => $record) {
			$out[] = [
				'token' => $token,
				'severity' => (string)$record['severity'],
				'replacement' => ($record['replacement'] ?? null),
				'removalDate' => ($record['removalDate'] ?? null),
				'message' => ($record['message'] ?? null),
				'deprecatedAt' => (string)($record['deprecatedAt'] ?? ''),
				'due' => $record['due'],
				'state' => $record['state'],
			];
		}

		return $out;
	}//end publicList()

	/**
	 * Record or change a deprecation.
	 *
	 * @param string $token The deprecated name.
	 * @param array<string, mixed> $input {severity, replacement?, removalDate?, message?}.
	 * @param string $source `admin` or `import`.
	 * @param string|null $today The date a removal date may not lie before, today when null.
	 *
	 * @return array<string, mixed> The stored record.
	 *
	 * @throws InvalidArgumentException 400 naming the field that fails.
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-an-administrator-deprecates-a-token
	 */
	public function deprecate(string $token, array $input, string $source = 'admin', ?string $today = null): array {
		if (preg_match(self::NAME_PATTERN, $token) !== 1) {
			throw new InvalidArgumentException('token', 400);
		}

		$records = $this->records->all();
		$existing = ($records[$token] ?? []);
		$record = $this->checked(token: $token, input: $input, today: ($today ?? gmdate('Y-m-d')));
		$record['deprecatedAt'] = (string)($existing['deprecatedAt'] ?? gmdate(DATE_ATOM));
		$record['source'] = (string)($existing['source'] ?? $source);
		$record['state'] = (string)($existing['state'] ?? 'active');

		$records[$token] = $record;
		$this->records->save(records: $records);

		return $record;
	}//end deprecate()

	/**
	 * Withdraw a deprecation.
	 *
	 * @param string $token The name.
	 *
	 * @return array<string, mixed> The withdrawn record.
	 *
	 * @throws InvalidArgumentException 404 for a name without a record.
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-an-administrator-deprecates-a-token
	 */
	public function withdraw(string $token): array {
		$records = $this->records->all();
		if (isset($records[$token]) === false) {
			throw new InvalidArgumentException('unknown', 404);
		}

		$old = $records[$token];
		unset($records[$token]);
		$this->records->save(records: $records);

		return $old;
	}//end withdraw()

	/**
	 * Mark a deprecated own token as removed. The record stays, so a consumer that
	 * missed the date can still read what happened.
	 *
	 * @param string $token The removed own token.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-an-administrator-deprecates-a-token
	 */
	public function markRemoved(string $token): void {
		$records = $this->records->all();
		if (isset($records[$token]) === false) {
			return;
		}

		$records[$token]['state'] = 'removed';
		$this->records->save(records: $records);
	}//end markRemoved()

	/**
	 * Record the deprecation notices of an upload. Each notice that names a CSS variable
	 * becomes a record with `source: import`; one already recorded is left as it is.
	 *
	 * @param array<int, array<string, mixed>> $notices The upload's `importWarnings`.
	 *
	 * @return array<int, string> The names recorded.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - the injection filter is a pure function shared with the CSS writer
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-imported-deprecation-notices-can-be-recorded
	 */
	public function adoptImportNotices(array $notices): array {
		$records = $this->records->all();
		$recorded = [];
		foreach ($notices as $notice) {
			$token = (string)($notice['token'] ?? '');
			if (preg_match(self::NAME_PATTERN, $token) !== 1 || isset($records[$token]) === true) {
				continue;
			}

			$record = ['severity' => 'warning', 'deprecatedAt' => gmdate(DATE_ATOM), 'source' => 'import', 'state' => 'active'];
			if (in_array(($notice['severity'] ?? ''), self::SEVERITIES, true) === true) {
				$record['severity'] = (string)$notice['severity'];
			}

			$message = trim((string)($notice['message'] ?? ''));
			if ($message !== '' && OverridesCssBuilder::isUnsafeValue(value: $message) === false) {
				$record['message'] = mb_substr($message, 0, 500);
			}

			$records[$token] = $record;
			$recorded[] = $token;
		}

		$this->records->save(records: $records);

		return $recorded;
	}//end adoptImportNotices()

	/**
	 * Check every record of a configuration bundle, without storing them. Replacements and
	 * dates are not re-checked: the bundle comes from an instance that checked them.
	 *
	 * @param array<string, mixed> $records Token name => record.
	 *
	 * @return array<string, array<string, mixed>> The kept fields per record.
	 *
	 * @throws InvalidArgumentException 400 for a record that is not one.
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-travel-with-the-configuration-bundle
	 */
	public function checkAll(array $records): array {
		$clean = [];
		foreach ($records as $token => $record) {
			if (preg_match(self::NAME_PATTERN, (string)$token) !== 1 || is_array($record) === false
				|| in_array(($record['severity'] ?? ''), self::SEVERITIES, true) === false
			) {
				throw new InvalidArgumentException('record', 400);
			}

			$fields = ['severity', 'replacement', 'removalDate', 'message', 'deprecatedAt', 'source', 'state'];
			$clean[(string)$token] = array_intersect_key($record, array_flip($fields));
		}

		return $clean;
	}//end checkAll()

	/**
	 * Replace every record, as a configuration bundle import does.
	 *
	 * @param array<string, mixed> $records Token name => record.
	 *
	 * @return int The number stored.
	 *
	 * @throws InvalidArgumentException 400 for a record that is not one; nothing is stored then.
	 *
	 * @spec openspec/specs/own-tokens/spec.md#requirement-own-tokens-travel-with-the-configuration-bundle
	 */
	public function replaceAll(array $records): int {
		$clean = $this->checkAll(records: $records);
		$this->records->save(records: $clean);

		return count($clean);
	}//end replaceAll()

	/**
	 * The notice written above each deprecated token, as in
	 * `deprecated (warning): use --nldesign-org-brand-accent, removal 2027-03-01`.
	 *
	 * @return array<string, string> Token name => comment text.
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-the-served-stylesheet-names-each-deprecated-own-token
	 */
	public function comments(): array {
		return $this->records->comments();
	}//end comments()

	/**
	 * Whether a name is one a replacement may point at: an own token, an editor token,
	 * or a name the thematiq vocabulary (`defaults.css`) declares.
	 *
	 * @param string $name The name.
	 *
	 * @return bool
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - TokenRegistry uses static methods by design
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-an-administrator-deprecates-a-token
	 */
	public function isKnownName(string $name): bool {
		if ($this->ownTokens->exists(name: $name) === true || TokenRegistry::isEditable(tokenName: $name) === true) {
			return true;
		}

		$defaults = $this->appManager->getAppPath(Application::APP_ID) . '/css/systems/nldesign/defaults.css';
		if (is_readable($defaults) === false) {
			return false;
		}

		return isset($this->cssParser->parseRootBlock(css: (string)file_get_contents($defaults))[$name]);
	}//end isKnownName()

	/**
	 * The checked fields of a record.
	 *
	 * @param string $token The deprecated name.
	 * @param array<string, mixed> $input The submitted fields.
	 * @param string $today Today, `Y-m-d`.
	 *
	 * @return array<string, string> Shape: {severity, replacement?, removalDate?, message?}.
	 *
	 * @throws InvalidArgumentException 400 naming the field.
	 */
	private function checked(string $token, array $input, string $today): array {
		$severity = (string)($input['severity'] ?? '');
		if (in_array($severity, self::SEVERITIES, true) === false) {
			throw new InvalidArgumentException('severity', 400);
		}

		return array_filter(
			[
				'severity' => $severity,
				'replacement' => $this->checkedReplacement(token: $token, replacement: trim((string)($input['replacement'] ?? ''))),
				'removalDate' => $this->checkedDate(date: trim((string)($input['removalDate'] ?? '')), today: $today),
				'message' => $this->checkedMessage(message: trim((string)($input['message'] ?? ''))),
			],
			static fn (string $value): bool => $value !== ''
		);
	}//end checked()

	/**
	 * A replacement that exists and is not the token itself, or ''.
	 *
	 * @param string $token The deprecated name.
	 * @param string $replacement The submitted replacement.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException 400 naming the replacement.
	 */
	private function checkedReplacement(string $token, string $replacement): string {
		if ($replacement !== '' && ($replacement === $token || $this->isKnownName(name: $replacement) === false)) {
			throw new InvalidArgumentException('replacement', 400);
		}

		return $replacement;
	}//end checkedReplacement()

	/**
	 * A real `Y-m-d` date, today or later, or ''.
	 *
	 * @param string $date The submitted date.
	 * @param string $today Today, `Y-m-d`.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException 400 naming the removal date.
	 */
	private function checkedDate(string $date, string $today): string {
		if ($date === '') {
			return '';
		}

		$parsed = date_create_immutable_from_format('!Y-m-d', $date);
		if ($parsed === false || $parsed->format('Y-m-d') !== $date || $date < $today) {
			throw new InvalidArgumentException('removalDate', 400);
		}

		return $date;
	}//end checkedDate()

	/**
	 * A message of at most 500 characters that cannot break out of a CSS comment, or ''.
	 *
	 * @param string $message The submitted message.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException 400 naming the message.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - the injection filter is a pure function shared with the CSS writer
	 */
	private function checkedMessage(string $message): string {
		if (mb_strlen($message) > 500 || OverridesCssBuilder::isUnsafeValue(value: $message) === true) {
			throw new InvalidArgumentException('message', 400);
		}

		return $message;
	}//end checkedMessage()

}//end class
