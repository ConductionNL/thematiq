<?php

/**
 * A token value the converter changed, as a line of the conversion report.
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
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * One token the converter adapted: its name, the value it now has and the value
 * it had. The report entry says why.
 *
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 *
 * @phpstan-import-type ReportEntry from TokenSetConverterService
 * @psalm-import-type   ReportEntry from TokenSetConverterService
 */
final class AdaptedToken {

	/**
	 * Constructor.
	 *
	 * @param string $name The custom property.
	 * @param string $value The value it now has.
	 * @param string $original The value it had.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $value,
		public readonly string $original,
	) {
	}//end __construct()

	/**
	 * The report entry for a value brought to its contrast threshold.
	 *
	 * @return ReportEntry The entry.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function contrastRepaired(): array {
		return $this->entry(reason: 'contrast-repaired');
	}//end contrastRepaired()

	/**
	 * The report entry for a value that was not a visible colour and got one.
	 *
	 * @return ReportEntry The entry.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function notAColour(): array {
		return $this->entry(reason: 'not-a-colour');
	}//end notAColour()

	/**
	 * The report entry for a colour literal kept as it was meant but written the plain way.
	 *
	 * @return ReportEntry The entry.
	 *
	 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
	 */
	public function colourNormalised(): array {
		return [
			'source' => $this->name,
			'target' => '',
			'action' => 'kept',
			'reason' => 'colour-normalised',
			'value' => $this->value,
			'original' => $this->original,
		];
	}//end colourNormalised()

	/**
	 * The report entry with a reason.
	 *
	 * @param string $reason Why the value changed.
	 *
	 * @return ReportEntry The entry.
	 */
	private function entry(string $reason): array {
		return [
			'source' => $this->name,
			'target' => $this->name,
			'action' => 'adapted',
			'reason' => $reason,
			'value' => $this->value,
			'original' => $this->original,
		];
	}//end entry()
}//end class
