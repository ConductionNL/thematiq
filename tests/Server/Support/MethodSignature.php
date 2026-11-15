<?php

/**
 * A comparable description of a method's signature.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Server\Support;

/**
 * What a test double has to match of a server method: visibility, static, parameters and return type.
 */
final class MethodSignature {
	/**
	 * Describe a method.
	 *
	 * @param \ReflectionMethod $method The method.
	 *
	 * @return array<string, mixed>
	 */
	public static function of(\ReflectionMethod $method): array {
		$parameters = [];
		foreach ($method->getParameters() as $parameter) {
			$parameters[] = [
				'name' => $parameter->getName(),
				'type' => (string)$parameter->getType(),
				'optional' => $parameter->isOptional(),
				'default' => match ($parameter->isDefaultValueAvailable()) {
					true => var_export($parameter->getDefaultValue(), true),
					default => null,
				},
				'promoted' => $parameter->isPromoted(),
			];
		}

		return [
			'public' => $method->isPublic(),
			'static' => $method->isStatic(),
			'parameters' => $parameters,
			'return' => (string)$method->getReturnType(),
		];
	}//end of()
}//end class
