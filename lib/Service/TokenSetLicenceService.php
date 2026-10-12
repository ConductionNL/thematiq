<?php

/**
 * Thematiq Token Set Licence Service.
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

use DateTimeInterface;
use InvalidArgumentException;
use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\Exception\RestrictedSetInUseException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * What an administrator does with a restricted token set: read its licence,
 * confirm that this organisation may use it, and withdraw that confirmation.
 *
 * Both writes leave an entry in the theming audit trail and a line in the
 * Nextcloud log, with who and which set.
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md
 */
class TokenSetLicenceService {

	/**
	 * Constructor.
	 *
	 * @param TokenSetService $tokenSets Knows the sets and hands out the licence gate.
	 * @param IConfig $config Reads where a set is applied.
	 * @param ScheduledSwitchStore $switches The planned switches.
	 * @param ThemingAuditService $audit Records a confirmation and a withdrawal.
	 * @param ITimeFactory $time The clock.
	 * @param LoggerInterface $logger The Nextcloud log.
	 */
	public function __construct(
		private readonly TokenSetService $tokenSets,
		private readonly IConfig $config,
		private readonly ScheduledSwitchStore $switches,
		private readonly ThemingAuditService $audit,
		private readonly ITimeFactory $time,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * A set's licence as the settings page shows it.
	 *
	 * @param string $tokenSetId The set id.
	 *
	 * @return array<string, mixed>|null The declaration, the full text, the confirmation and where the
	 *                                   set is applied; null when the set declares no licence.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-the-picker-shows-the-warning-and-the-licence-text
	 */
	public function describe(string $tokenSetId): ?array {
		$gate = $this->tokenSets->licenceGate();
		$licence = $gate->licenceOf(tokenSetId: $tokenSetId);
		if ($licence === null) {
			return null;
		}

		return [
			'tokenSet' => $tokenSetId,
			'licence' => $licence,
			'text' => $gate->licenceText(tokenSetId: $tokenSetId),
			'confirmation' => $gate->confirmation(tokenSetId: $tokenSetId),
			'appliedIn' => $this->appliedIn(tokenSetId: $tokenSetId),
		];
	}//end describe()

	/**
	 * Every restricted set the settings page lists: its name, licence and confirmation.
	 *
	 * @return list<array{id: string, name: string, licence: array<string, mixed>, confirmation: array{uid: string, at: string}|null}>
	 *                                                                                                                                 Sorted by name.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-the-picker-shows-the-warning-and-the-licence-text
	 */
	public function restrictedSets(): array {
		$gate = $this->tokenSets->licenceGate();
		$sets = [];
		foreach ($this->tokenSets->getAvailableTokenSets() as $set) {
			$id = (string)$set['id'];
			if ($gate->isRestricted(tokenSetId: $id) === false) {
				continue;
			}

			$sets[] = [
				'id' => $id,
				'name' => (string)$set['name'],
				'licence' => (array)$gate->licenceOf(tokenSetId: $id),
				'confirmation' => $gate->confirmation(tokenSetId: $id),
			];
		}

		return $sets;
	}//end restrictedSets()

	/**
	 * Record that this organisation is the owner of a restricted set, or has its permission.
	 *
	 * @param string $tokenSetId The restricted set.
	 * @param string $uid The confirming administrator.
	 *
	 * @return array{uid: string, at: string} The stored confirmation.
	 *
	 * @throws InvalidArgumentException When the set does not exist or is not restricted, or no administrator is named.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-an-administrator-confirms-a-restricted-set
	 */
	public function confirm(string $tokenSetId, string $uid): array {
		$gate = $this->tokenSets->licenceGate();
		if ($uid === ''
			|| $this->tokenSets->isValidTokenSet(tokenSetId: $tokenSetId) === false
			|| $gate->isRestricted(tokenSetId: $tokenSetId) === false
		) {
			throw new InvalidArgumentException('Not a restricted token set: ' . $tokenSetId);
		}

		$existing = $gate->confirmation(tokenSetId: $tokenSetId);
		if ($existing !== null) {
			return $existing;
		}

		$confirmedAt = $this->time->getDateTime()->format(DateTimeInterface::ATOM);
		$gate->storeConfirmation(tokenSetId: $tokenSetId, uid: $uid, confirmedAt: $confirmedAt);

		$licence = $gate->licenceOf(tokenSetId: $tokenSetId);
		$this->audit->log(
			action: 'restricted_set_confirmed',
			context: ['old' => null, 'new' => $tokenSetId, 'actor' => $uid, 'licence' => ($licence['id'] ?? ''), 'holder' => ($licence['holder'] ?? '')]
		);
		$this->logger->warning(
			'thematiq: {uid} confirmed that this organisation may use the restricted token set {set}',
			['uid' => $uid, 'set' => $tokenSetId, 'app' => Application::APP_ID]
		);

		return ['uid' => $uid, 'at' => $confirmedAt];
	}//end confirm()

	/**
	 * Take a confirmation back. Refused while the set is applied anywhere:
	 * the administrator first chooses another set there, so nobody's screen
	 * changes as a side effect of a checkbox.
	 *
	 * @param string $tokenSetId The restricted set.
	 * @param string $uid The administrator withdrawing.
	 *
	 * @return void
	 *
	 * @throws RestrictedSetInUseException When the set is still applied; it names where.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-confirmation-can-be-withdrawn-once-the-set-is-no-longer-applied
	 */
	public function withdraw(string $tokenSetId, string $uid): void {
		$gate = $this->tokenSets->licenceGate();
		$existing = $gate->confirmation(tokenSetId: $tokenSetId);
		if ($existing === null) {
			return;
		}

		$appliedIn = $this->appliedIn(tokenSetId: $tokenSetId);
		if ($appliedIn !== []) {
			throw new RestrictedSetInUseException(tokenSetId: $tokenSetId, appliedIn: $appliedIn);
		}

		$gate->dropConfirmation(tokenSetId: $tokenSetId);
		$this->audit->log(
			action: 'restricted_set_confirmation_withdrawn',
			context: ['old' => $tokenSetId, 'new' => null, 'actor' => $uid, 'confirmedBy' => $existing['uid'], 'confirmedAt' => $existing['at']]
		);
		$this->logger->warning(
			'thematiq: {uid} withdrew the confirmation for the restricted token set {set}',
			['uid' => $uid, 'set' => $tokenSetId, 'app' => Application::APP_ID]
		);
	}//end withdraw()

	/**
	 * Every place a set is applied: as the instance's set, for a group, as an
	 * app's brand, or in a planned switch that has not finished.
	 *
	 * @param string $tokenSetId The set id.
	 *
	 * @return list<array{kind: string, id: string}> One entry per place; empty when it is applied nowhere.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-confirmation-can-be-withdrawn-once-the-set-is-no-longer-applied
	 */
	public function appliedIn(string $tokenSetId): array {
		$places = [];
		if ($this->config->getAppValue(Application::APP_ID, ActiveTokenSetService::CONFIG_KEY, ActiveTokenSetService::DEFAULT_SET) === $tokenSetId) {
			$places[] = ['kind' => 'instance', 'id' => ''];
		}

		foreach (['group' => 'group_token_sets', 'app' => AppBrandService::CONFIG_KEY] as $kind => $key) {
			$places = array_merge($places, $this->placesIn(kind: $kind, entries: $this->stored(key: $key), tokenSetId: $tokenSetId));
		}

		foreach ($this->switches->all() as $switch) {
			$open = in_array(($switch['status'] ?? ''), ['planned', 'running'], true);
			if ($open === true && in_array($tokenSetId, [($switch['tokenSet'] ?? null), ($switch['revertTo'] ?? null)], true) === true) {
				$places[] = ['kind' => 'switch', 'id' => (string)($switch['id'] ?? '')];
			}
		}

		return $places;
	}//end appliedIn()

	/**
	 * The entries of a stored mapping that name a set: a group mapping is a
	 * list whose entries name their group, a brand mapping is keyed by app id.
	 *
	 * @param string $kind `group` or `app`.
	 * @param array<int|string, mixed> $entries The stored mapping.
	 * @param string $tokenSetId The set id.
	 *
	 * @return list<array{kind: string, id: string}> One entry per place.
	 */
	private function placesIn(string $kind, array $entries, string $tokenSetId): array {
		$places = [];
		foreach ($entries as $key => $entry) {
			if (is_array($entry) === true && ($entry['tokenSet'] ?? null) === $tokenSetId) {
				$places[] = ['kind' => $kind, 'id' => (string)($entry['group'] ?? $key)];
			}
		}

		return $places;
	}//end placesIn()

	/**
	 * Restricted sets that are applied and that nobody confirmed. That only
	 * happens to an instance that applied the set before it was marked
	 * restricted; the set stays applied and the settings page asks for the
	 * confirmation.
	 *
	 * @return list<array{tokenSet: string, appliedIn: list<array{kind: string, id: string}>}> One entry per set.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-a-set-applied-before-it-was-restricted-stays-applied-and-asks-for-confirmation
	 */
	public function awaitingConfirmation(): array {
		$gate = $this->tokenSets->licenceGate();
		$awaiting = [];
		foreach ($gate->restrictedIds() as $id) {
			if ($gate->confirmation(tokenSetId: $id) !== null) {
				continue;
			}

			$appliedIn = $this->appliedIn(tokenSetId: $id);
			if ($appliedIn !== []) {
				$awaiting[] = ['tokenSet' => $id, 'appliedIn' => $appliedIn];
			}
		}

		return $awaiting;
	}//end awaitingConfirmation()

	/**
	 * A stored JSON app config value as an array.
	 *
	 * @param string $key The app config key.
	 *
	 * @return array<int|string, mixed> Empty when nothing is stored or the value is unreadable.
	 */
	private function stored(string $key): array {
		$decoded = json_decode((string)$this->config->getAppValue(Application::APP_ID, $key, ''), true);
		if (is_array($decoded) === false) {
			return [];
		}

		return $decoded;
	}//end stored()
}//end class
