<?php

/**
 * Unit tests for ContrastController: auth posture, CSRF default-on, request
 * validation, and delegation to ContrastService::evaluate().
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/app-token-set-selection/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\ContrastController;
use OCA\Thematiq\Service\ContrastService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ContrastControllerTest extends TestCase {

	/**
	 * `evaluate()` MUST carry `#[NoAdminRequired]` — any authenticated user
	 * may evaluate contrast, not just admins.
	 *
	 * @return void
	 */
	public function testEvaluateHasNoAdminRequiredAttribute(): void {
		$method = new ReflectionMethod(ContrastController::class, 'evaluate');

		$this->assertCount(expectedCount: 1, haystack: $method->getAttributes(NoAdminRequired::class));
	}//end testEvaluateHasNoAdminRequiredAttribute()

	/**
	 * `evaluate()` MUST NOT carry `#[PublicPage]` — authenticated only.
	 *
	 * @return void
	 */
	public function testEvaluateHasNoPublicPageAttribute(): void {
		$method = new ReflectionMethod(ContrastController::class, 'evaluate');

		$this->assertCount(expectedCount: 0, haystack: $method->getAttributes(PublicPage::class));
	}//end testEvaluateHasNoPublicPageAttribute()

	/**
	 * `evaluate()` MUST NOT carry `#[NoCSRFRequired]` — it is a
	 * state-free POST from an authenticated, same-origin browser session
	 * that carries the Nextcloud request token automatically, so the
	 * framework's default CSRF requirement applies (design.md task 2.2).
	 *
	 * @return void
	 */
	public function testEvaluateHasNoCsrfExemption(): void {
		$method = new ReflectionMethod(ContrastController::class, 'evaluate');

		$this->assertCount(expectedCount: 0, haystack: $method->getAttributes(NoCSRFRequired::class));
		$this->assertStringNotContainsString(needle: '@NoCSRFRequired', haystack: (string)$method->getDocComment());
	}//end testEvaluateHasNoCsrfExemption()

	/**
	 * A well-formed request delegates to `ContrastService::evaluate()` and
	 * returns its result under a `results` key.
	 *
	 * @return void
	 */
	public function testEvaluateDelegatesToContrastServiceAndReturnsResults(): void {
		$expected = [
			['name' => 'primary', 'ratio' => 8.99, 'threshold' => 4.5, 'level' => 'AA', 'pass' => true],
		];

		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParams')->willReturn([
			'candidates' => [
				['name' => 'primary', 'value' => '#154273', 'role' => 'text'],
			],
			'background' => '#F5F6F7',
		]);

		$contrastService = $this->createMock(originalClassName: ContrastService::class);
		$contrastService->expects($this->once())
			->method('evaluate')
			->with(
				[['name' => 'primary', 'value' => '#154273', 'role' => 'text']],
				'#F5F6F7'
			)
			->willReturn($expected);

		$controller = new ContrastController(appName: 'nldesign', request: $request, contrastService: $contrastService);
		$response = $controller->evaluate();

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: ['results' => $expected], actual: $response->getData());
	}//end testEvaluateDelegatesToContrastServiceAndReturnsResults()

	/**
	 * A missing `background` returns 400, never a silent 200.
	 *
	 * @return void
	 */
	public function testMissingBackgroundReturns400(): void {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParams')->willReturn([
			'candidates' => [['name' => 'primary', 'value' => '#154273', 'role' => 'text']],
		]);

		$controller = new ContrastController(
			appName: 'nldesign',
			request: $request,
			contrastService: $this->createMock(originalClassName: ContrastService::class)
		);
		$response = $controller->evaluate();

		$this->assertSame(expected: 400, actual: $response->getStatus());
	}//end testMissingBackgroundReturns400()

	/**
	 * A non-array `candidates` returns 400.
	 *
	 * @return void
	 */
	public function testNonArrayCandidatesReturns400(): void {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParams')->willReturn([
			'candidates' => 'not-an-array',
			'background' => '#FFFFFF',
		]);

		$controller = new ContrastController(
			appName: 'nldesign',
			request: $request,
			contrastService: $this->createMock(originalClassName: ContrastService::class)
		);
		$response = $controller->evaluate();

		$this->assertSame(expected: 400, actual: $response->getStatus());
	}//end testNonArrayCandidatesReturns400()

	/**
	 * A candidate with an invalid `role` (not `text`/`ui`) returns 400.
	 *
	 * @return void
	 */
	public function testCandidateWithInvalidRoleReturns400(): void {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParams')->willReturn([
			'candidates' => [['name' => 'primary', 'value' => '#154273', 'role' => 'bogus']],
			'background' => '#FFFFFF',
		]);

		$controller = new ContrastController(
			appName: 'nldesign',
			request: $request,
			contrastService: $this->createMock(originalClassName: ContrastService::class)
		);
		$response = $controller->evaluate();

		$this->assertSame(expected: 400, actual: $response->getStatus());
	}//end testCandidateWithInvalidRoleReturns400()

	/**
	 * The response never carries a `blocked`/`allowed`/`verdict` key at the
	 * envelope level either — only whatever `ContrastService::evaluate()`
	 * returns under `results`.
	 *
	 * @return void
	 */
	public function testResponseEnvelopeHasNoVerdictKeys(): void {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParams')->willReturn([
			'candidates' => [['name' => 'primary', 'value' => '#154273', 'role' => 'text']],
			'background' => '#FFFFFF',
		]);

		$contrastService = $this->createMock(originalClassName: ContrastService::class);
		$contrastService->method('evaluate')->willReturn([]);

		$controller = new ContrastController(appName: 'nldesign', request: $request, contrastService: $contrastService);
		$data = $controller->evaluate()->getData();

		$this->assertArrayNotHasKey(key: 'blocked', array: $data);
		$this->assertArrayNotHasKey(key: 'allowed', array: $data);
		$this->assertArrayNotHasKey(key: 'verdict', array: $data);
	}//end testResponseEnvelopeHasNoVerdictKeys()
}//end class
