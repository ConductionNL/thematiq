<?php

/**
 * Thematiq ErrorStatusTrait
 *
 * @category Controller
 * @package  OCA\Thematiq\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git_id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use Throwable;

/**
 * Turns a service exception's code into an HTTP error status.
 *
 * Services in this app signal the response status through the exception code
 * (409 for a clash, 413 for an oversized upload, 422 for invalid input). Only a
 * code that is a real HTTP error status is passed on; anything else (0, a
 * library's own error number) becomes the caller's fallback, so a stray code can
 * never reach the client as a status line.
 */
trait ErrorStatusTrait {

	/**
	 * The HTTP error statuses a JSONResponse can carry.
	 */
	private const ERROR_STATUSES = [
		400, 401, 402, 403, 404, 405, 406, 407, 408, 409, 410, 411, 412, 413, 414,
		415, 416, 417, 418, 422, 423, 424, 426, 428, 429, 431, 500, 501, 502, 503,
		504, 505, 506, 507, 508, 509, 510, 511,
	];

	/**
	 * The exception's code when it is an HTTP error status, else the fallback.
	 *
	 * @param Throwable $exception The service exception.
	 * @param 422|500   $fallback  The status when the code is not an error status.
	 *
	 * @return 400|401|402|403|404|405|406|407|408|409|410|411|412|413|414|415|416|417|418|422|423|424|426|428|429|431|500|501|502|503|504|505|506|507|508|509|510|511
	 *
	 * @spec exclude Response-status plumbing shared by the upload controllers; the
	 *   statuses themselves are specified with each endpoint.
	 */
	private function errorStatus(Throwable $exception, int $fallback): int {
		$code = $exception->getCode();
		foreach (self::ERROR_STATUSES as $status) {
			if ($code === $status) {
				return $status;
			}
		}

		return $fallback;
	}//end errorStatus()
}//end trait
