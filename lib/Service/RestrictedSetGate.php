<?php

/**
 * Thematiq Restricted Set Gate.
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
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCP\IConfig;

/**
 * Reads the licence a shipped token set declares, and decides whether a set
 * may be applied.
 *
 * A set declares its licence in `token-sets.json` (`licence`: an identifier,
 * the source package and its version, and whether use is limited to the
 * owner). The licence text ships beside the set as
 * `css/tokens/<id>.LICENSE.md`. A set whose licence limits use to its owner is
 * restricted: it may be applied only after an administrator confirmed, for
 * that set, that this organisation is the owner or has its permission.
 *
 * Only the shipped manifest is read. An uploaded set (`custom-*`) carries no
 * declaration and is never restricted: the administrator who uploads a set
 * answers for it.
 *
 * The gate fails closed: a `licence` block whose `restricted` is anything
 * other than the literal `false` counts as restricted.
 *
 * {@see TokenSetService::licenceGate()} hands this out, so every service that
 * can apply a set already holds the way to ask.
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md
 */
class RestrictedSetGate {

	/**
	 * The app config key holding the confirmations, a JSON object by set id.
	 *
	 * @var string
	 */
	public const CONFIG_KEY = 'restricted_set_confirmations';

	/**
	 * The shipped manifest, indexed by set id; null until first read.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private ?array $manifest = null;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service holding the confirmations.
	 * @param string $appPath The app directory the manifest and licence files are read from.
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly string $appPath,
	) {
	}//end __construct()

	/**
	 * The licence a shipped set declares.
	 *
	 * @param string $tokenSetId The set id.
	 *
	 * @return array{id: string, package: string, version: string, holder: string, restricted: bool}|null
	 *                                                                                                    Null when the set declares none.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-token-set-declares-its-licence
	 */
	public function licenceOf(string $tokenSetId): ?array {
		$licence = ($this->manifest()[$tokenSetId]['licence'] ?? null);
		if (is_array($licence) === false) {
			return null;
		}

		return [
			'id' => (string)($licence['id'] ?? ''),
			'package' => (string)($licence['package'] ?? ''),
			'version' => (string)($licence['version'] ?? ''),
			'holder' => (string)($licence['holder'] ?? ''),
			'restricted' => (($licence['restricted'] ?? null) !== false),
		];
	}//end licenceOf()

	/**
	 * The licence text that ships beside a set.
	 *
	 * @param string $tokenSetId The set id.
	 *
	 * @return string|null The text, or null when the set ships none.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-token-set-declares-its-licence
	 */
	public function licenceText(string $tokenSetId): ?string {
		if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $tokenSetId) !== 1) {
			return null;
		}

		$path = $this->appPath . '/css/tokens/' . $tokenSetId . '.LICENSE.md';
		if (is_file($path) === false) {
			return null;
		}

		$text = file_get_contents($path);
		if ($text === false) {
			return null;
		}

		return $text;
	}//end licenceText()

	/**
	 * Whether a set's licence limits its use to its owner.
	 *
	 * @param string $tokenSetId The set id.
	 *
	 * @return bool True for a restricted set.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-token-set-declares-its-licence
	 */
	public function isRestricted(string $tokenSetId): bool {
		return (($this->licenceOf(tokenSetId: $tokenSetId)['restricted'] ?? false) === true);
	}//end isRestricted()

	/**
	 * Every shipped set whose licence limits its use to its owner.
	 *
	 * @return list<string> The set ids.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-token-set-declares-its-licence
	 */
	public function restrictedIds(): array {
		$ids = [];
		foreach (array_keys($this->manifest()) as $id) {
			if ($this->isRestricted(tokenSetId: (string)$id) === true) {
				$ids[] = (string)$id;
			}
		}

		return $ids;
	}//end restrictedIds()

	/**
	 * The stored confirmation of a set.
	 *
	 * @param string $tokenSetId The set id.
	 *
	 * @return array{uid: string, at: string}|null Who confirmed and when (ISO 8601), or null.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-an-administrator-confirms-a-restricted-set
	 */
	public function confirmation(string $tokenSetId): ?array {
		$entry = ($this->confirmations()[$tokenSetId] ?? null);
		if (is_array($entry) === false || is_string(($entry['uid'] ?? null)) === false || is_string(($entry['at'] ?? null)) === false) {
			return null;
		}

		if ($entry['uid'] === '' || $entry['at'] === '') {
			return null;
		}

		return ['uid' => $entry['uid'], 'at' => $entry['at']];
	}//end confirmation()

	/**
	 * Whether a set may be applied: it is not restricted, or it is confirmed.
	 *
	 * @param string $tokenSetId The set id.
	 *
	 * @return bool True when applying it is allowed.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-restricted-set-is-refused-until-an-administrator-confirms-it
	 */
	public function mayApply(string $tokenSetId): bool {
		if ($this->isRestricted(tokenSetId: $tokenSetId) === false) {
			return true;
		}

		return ($this->confirmation(tokenSetId: $tokenSetId) !== null);
	}//end mayApply()

	/**
	 * Refuse a restricted set that is not confirmed.
	 *
	 * A set that is already applied in the place being written stays there:
	 * saving a mapping again, or choosing the active set a second time, is not
	 * a new use. That keeps an instance that applied a set before it was
	 * marked restricted from losing its theme on an unrelated save.
	 *
	 * @param string $tokenSetId The set about to be applied.
	 * @param string|null $alreadyApplied The set this place holds now, if any.
	 *
	 * @return void
	 *
	 * @throws RestrictedSetNotConfirmedException When the set is restricted, not confirmed and not already there.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-restricted-set-is-refused-until-an-administrator-confirms-it
	 */
	public function assertMayApply(string $tokenSetId, ?string $alreadyApplied = null): void {
		if ($tokenSetId === $alreadyApplied || $this->mayApply(tokenSetId: $tokenSetId) === true) {
			return;
		}

		throw new RestrictedSetNotConfirmedException(tokenSetId: $tokenSetId);
	}//end assertMayApply()

	/**
	 * Store a confirmation. Use {@see TokenSetLicenceService::confirm()}, which
	 * checks the set and writes the audit entry.
	 *
	 * @param string $tokenSetId The set id.
	 * @param string $uid The confirming administrator.
	 * @param string $at When, ISO 8601.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-an-administrator-confirms-a-restricted-set
	 */
	public function storeConfirmation(string $tokenSetId, string $uid, string $at): void {
		$all = $this->confirmations();
		$all[$tokenSetId] = ['uid' => $uid, 'at' => $at];
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_KEY, (string)json_encode($all, JSON_UNESCAPED_SLASHES));
	}//end storeConfirmation()

	/**
	 * Drop a confirmation. Use {@see TokenSetLicenceService::withdraw()}, which
	 * refuses while the set is applied and writes the audit entry.
	 *
	 * @param string $tokenSetId The set id.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-confirmation-can-be-withdrawn-once-the-set-is-no-longer-applied
	 */
	public function dropConfirmation(string $tokenSetId): void {
		$all = $this->confirmations();
		unset($all[$tokenSetId]);
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_KEY, (string)json_encode((object)$all, JSON_UNESCAPED_SLASHES));
	}//end dropConfirmation()

	/**
	 * The stored confirmations by set id.
	 *
	 * @return array<string, mixed> Empty when nothing is stored or the value is unreadable.
	 */
	private function confirmations(): array {
		$decoded = json_decode((string)$this->config->getAppValue(Application::APP_ID, self::CONFIG_KEY, '{}'), true);
		if (is_array($decoded) === false) {
			return [];
		}

		return $decoded;
	}//end confirmations()

	/**
	 * The shipped manifest by set id.
	 *
	 * @return array<string, array<string, mixed>> Empty when the manifest is missing or unreadable.
	 */
	private function manifest(): array {
		if ($this->manifest !== null) {
			return $this->manifest;
		}

		$this->manifest = [];
		$path = $this->appPath . '/token-sets.json';
		if (is_file($path) === false) {
			return $this->manifest;
		}

		$data = json_decode((string)file_get_contents($path), true);
		if (is_array($data) === false) {
			return $this->manifest;
		}

		foreach ($data as $entry) {
			if (is_array($entry) === true && is_string(($entry['id'] ?? null)) === true) {
				$this->manifest[$entry['id']] = $entry;
			}
		}

		return $this->manifest;
	}//end manifest()
}//end class
