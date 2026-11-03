<?php

/**
 * Unit tests for MetricsController: admin-only auth posture and the
 * theming-audit counter metric.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/prometheus-metrics/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\MetricsController;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * Two concerns share this suite:
 *
 * 1. Auth posture (ADR-006 / REQ "Metrics endpoint requires an authenticated
 *    admin session"): there is no real `SecurityMiddleware`/route-dispatch
 *    layer in this standalone harness, so a live 401 assertion is impossible
 *    here — that is covered by the post-merge live curl check. What IS
 *    statically verifiable and regression-proof is the precondition the
 *    admin-only default depends on: `index()` must carry neither
 *    `#[PublicPage]` nor `#[NoAdminRequired]`.
 * 2. The `nldesign_audit_entries_total` counter emitted from the
 *    `audit_entries_total` app value (cast to int, default '0'), additive to
 *    the pre-existing metric families.
 */
class MetricsControllerTest extends TestCase {

	/**
	 * In-memory appconfig store: key => value.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * The controller under test.
	 *
	 * @var MetricsController
	 */
	private MetricsController $controller;

	/**
	 * The default each `getAppValue()` key was read with: key => default.
	 *
	 * @var array<string, mixed>
	 */
	private array $defaults = [];

	/**
	 * Set up the controller with mocked dependencies.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->controller = $this->buildController();
	}//end setUp()

	/**
	 * Build a controller whose collaborators can be made to fail.
	 *
	 * @param \Exception|null $tokenSetError Thrown by `TokenSetService::getAvailableTokenSets()`.
	 * @param \Exception|null $overridesError Thrown by `CustomOverridesService::read()`.
	 * @param LoggerInterface|null $logger The logger, so a test can expect warnings.
	 *
	 * @return MetricsController
	 */
	private function buildController(
		?\Exception $tokenSetError = null,
		?\Exception $overridesError = null,
		?LoggerInterface $logger = null,
	): MetricsController {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			function (string $app, string $key, $default = '') {
				$this->defaults[$key] = $default;
				return ($this->appConfig[$key] ?? $default);
			}
		);
		$config->method('getSystemValueString')->willReturn('34.0.0');

		$tokenSetService = $this->createMock(originalClassName: TokenSetService::class);
		if ($tokenSetError !== null) {
			$tokenSetService->method('getAvailableTokenSets')->willThrowException($tokenSetError);
		} else {
			$tokenSetService->method('getAvailableTokenSets')->willReturn([]);
		}

		$overridesService = $this->createMock(originalClassName: CustomOverridesService::class);
		if ($overridesError !== null) {
			$overridesService->method('read')->willThrowException($overridesError);
		} else {
			$overridesService->method('read')->willReturn([]);
		}

		return new MetricsController(
			appName: 'nldesign',
			request: $this->createMock(originalClassName: IRequest::class),
			config: $config,
			tokenSetService: $tokenSetService,
			overridesSvc: $overridesService,
			logger: ($logger ?? $this->createMock(originalClassName: LoggerInterface::class))
		);
	}//end buildController()

	/**
	 * `index()` MUST NOT carry `#[PublicPage]` — its presence would make the
	 * endpoint reachable by any unauthenticated caller, defeating the
	 * admin-auth requirement (ADR-006).
	 */
	public function testIndexHasNoPublicPageAttribute(): void {
		$method = new ReflectionMethod(MetricsController::class, 'index');

		$this->assertCount(
			expectedCount: 0,
			haystack: $method->getAttributes(PublicPage::class),
			message: '#[PublicPage] must be absent so the SecurityMiddleware admin-only default applies.'
		);
	}//end testIndexHasNoPublicPageAttribute()

	/**
	 * `index()` MUST NOT carry `#[NoAdminRequired]` — its presence would
	 * allow any authenticated (non-admin) user through, still short of the
	 * admin-only requirement (ADR-006).
	 */
	public function testIndexHasNoNoAdminRequiredAttribute(): void {
		$method = new ReflectionMethod(MetricsController::class, 'index');

		$this->assertCount(
			expectedCount: 0,
			haystack: $method->getAttributes(NoAdminRequired::class),
			message: '#[NoAdminRequired] must be absent so the SecurityMiddleware admin-only default applies.'
		);
	}//end testIndexHasNoNoAdminRequiredAttribute()

	/**
	 * The CSRF exemption is orthogonal to the admin-auth requirement and
	 * MUST remain — a Prometheus scraper authenticates via HTTP Basic/an
	 * app-password and cannot present a CSRF token.
	 */
	public function testIndexDeclaresNoCsrfRequired(): void {
		$method = new ReflectionMethod(MetricsController::class, 'index');

		$this->assertStringContainsString(
			needle: '@NoCSRFRequired',
			haystack: (string)$method->getDocComment(),
			message: 'index() must remain CSRF-exempt even though it now requires an admin session.'
		);
	}//end testIndexDeclaresNoCsrfRequired()

	/**
	 * Sanity/regression check: the auth posture is enforced by middleware,
	 * never invoked when calling the method directly — the metrics body
	 * itself must still be produced unchanged.
	 */
	public function testIndexStillReturnsMetricsBody(): void {
		$response = $this->controller->index();

		$this->assertInstanceOf(expected: TextPlainResponse::class, actual: $response);
		$this->assertStringContainsString(needle: 'nldesign_up 1', haystack: $response->render());
	}//end testIndexStillReturnsMetricsBody()

	/**
	 * The audit counter defaults to zero when no entry has ever been
	 * written (fresh install / unset app value).
	 */
	public function testAuditCounterDefaultsToZero(): void {
		$body = $this->controller->index()->render();

		$this->assertStringContainsString(
			needle: '# HELP nldesign_audit_entries_total Total theming audit entries written',
			haystack: $body
		);
		$this->assertStringContainsString(needle: '# TYPE nldesign_audit_entries_total counter', haystack: $body);
		$this->assertStringContainsString(needle: 'nldesign_audit_entries_total 0', haystack: $body);
	}//end testAuditCounterDefaultsToZero()

	/**
	 * The audit counter reflects the current `audit_entries_total` app
	 * value (cast to int).
	 */
	public function testAuditCounterReflectsAppValue(): void {
		$this->appConfig['audit_entries_total'] = '12';

		$body = $this->controller->index()->render();

		$this->assertStringContainsString(needle: 'nldesign_audit_entries_total 12', haystack: $body);
	}//end testAuditCounterReflectsAppValue()

	/**
	 * The new counter is additive — pre-existing metric families still
	 * appear in the same response.
	 */
	public function testAuditCounterIsAdditiveToExistingFamilies(): void {
		$body = $this->controller->index()->render();

		$this->assertStringContainsString(needle: 'nldesign_info{', haystack: $body);
		$this->assertStringContainsString(needle: 'nldesign_up 1', haystack: $body);
		$this->assertStringContainsString(needle: '# TYPE nldesign_theming_syncs_total counter', haystack: $body);
	}//end testAuditCounterIsAdditiveToExistingFamilies()

	/**
	 * A failing token-set service reports `nldesign_token_sets_total 0`, logs
	 * one warning with the exception message, and leaves every other family
	 * in place.
	 *
	 * Proves openspec/specs/prometheus-metrics/spec.md, scenarios "Token set
	 * count error handled gracefully" and "Token set metrics fail, other
	 * metrics succeed".
	 */
	public function testTokenSetFailureFallsBackToZeroAndLogs(): void {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with(
			'Could not collect token set metrics',
			['exception' => 'token dir unreadable']
		);

		$body = $this->buildController(
			tokenSetError: new \RuntimeException('token dir unreadable'),
			logger: $logger
		)->index()->render();

		$this->assertStringContainsString(needle: "\nnldesign_token_sets_total 0\n", haystack: $body);
		$this->assertStringContainsString(needle: 'nldesign_info{', haystack: $body);
		$this->assertStringContainsString(needle: "\nnldesign_up 1\n", haystack: $body);
		$this->assertStringContainsString(needle: "\nnldesign_custom_overrides_total 0\n", haystack: $body);
		$this->assertStringContainsString(needle: "\nnldesign_theming_syncs_total 0\n", haystack: $body);
	}//end testTokenSetFailureFallsBackToZeroAndLogs()

	/**
	 * The active-token-set gauge sits inside the same try block as the count,
	 * so a failing token-set service omits it entirely.
	 *
	 * Proves openspec/specs/prometheus-metrics/spec.md, scenario "Active token
	 * set in error recovery".
	 */
	public function testTokenSetFailureOmitsTheActiveTokenSetGauge(): void {
		$body = $this->buildController(tokenSetError: new \RuntimeException('boom'))->index()->render();

		$this->assertStringNotContainsString(needle: 'nldesign_active_token_set', haystack: $body);
		$this->assertSame(expected: 1, actual: substr_count($body, 'nldesign_token_sets_total 0'));
	}//end testTokenSetFailureOmitsTheActiveTokenSetGauge()

	/**
	 * A failing overrides service reports `nldesign_custom_overrides_total 0`,
	 * logs one warning, and leaves every other family in place.
	 *
	 * Proves openspec/specs/prometheus-metrics/spec.md, scenarios "Override
	 * count error handled gracefully" and "Custom overrides fail, other
	 * metrics succeed".
	 */
	public function testOverrideFailureFallsBackToZeroAndLogs(): void {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with(
			'Could not collect override metrics',
			['exception' => 'overrides unreadable']
		);

		$body = $this->buildController(
			overridesError: new \RuntimeException('overrides unreadable'),
			logger: $logger
		)->index()->render();

		$this->assertStringContainsString(needle: "\nnldesign_custom_overrides_total 0\n", haystack: $body);
		$this->assertStringContainsString(needle: 'nldesign_info{', haystack: $body);
		$this->assertStringContainsString(needle: "\nnldesign_up 1\n", haystack: $body);
		$this->assertStringContainsString(needle: "\nnldesign_token_sets_total 0\n", haystack: $body);
		$this->assertStringContainsString(needle: 'nldesign_active_token_set{name="rijkshuisstijl"} 1', haystack: $body);
		$this->assertStringContainsString(needle: "\nnldesign_theming_syncs_total 0\n", haystack: $body);
	}//end testOverrideFailureFallsBackToZeroAndLogs()

	/**
	 * Both collectors failing are handled independently: two warnings, two
	 * zero fallbacks, and info, up and the syncs counter still present.
	 *
	 * Proves openspec/specs/prometheus-metrics/spec.md, scenario "Multiple
	 * failures handled independently".
	 */
	public function testBothFailuresAreHandledIndependently(): void {
		$warnings = [];
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->exactly(count: 2))->method('warning')->willReturnCallback(
			function (string $message) use (&$warnings): void {
				$warnings[] = $message;
			}
		);

		$body = $this->buildController(
			tokenSetError: new \RuntimeException('a'),
			overridesError: new \RuntimeException('b'),
			logger: $logger
		)->index()->render();

		$this->assertSame(
			expected: ['Could not collect token set metrics', 'Could not collect override metrics'],
			actual: $warnings
		);
		$this->assertStringContainsString(needle: "\nnldesign_token_sets_total 0\n", haystack: $body);
		$this->assertStringContainsString(needle: "\nnldesign_custom_overrides_total 0\n", haystack: $body);
		$this->assertStringContainsString(needle: 'nldesign_info{', haystack: $body);
		$this->assertStringContainsString(needle: "\nnldesign_up 1\n", haystack: $body);
		$this->assertStringContainsString(needle: "\nnldesign_theming_syncs_total 0\n", haystack: $body);
	}//end testBothFailuresAreHandledIndependently()

	/**
	 * With no `token_set` app value the gauge reports the default this
	 * controller reads with, `rijkshuisstijl`.
	 *
	 * Proves openspec/specs/prometheus-metrics/spec.md, scenario "Default
	 * token set reported when not configured". Note: the rest of the app
	 * defaults `token_set` to `nextcloud`; the spec records that mismatch.
	 */
	public function testActiveTokenSetDefaultWhenUnset(): void {
		$body = $this->controller->index()->render();

		$this->assertSame(expected: 'rijkshuisstijl', actual: $this->defaults['token_set']);
		$this->assertStringContainsString(needle: 'nldesign_active_token_set{name="rijkshuisstijl"} 1', haystack: $body);
	}//end testActiveTokenSetDefaultWhenUnset()

	/**
	 * The syncs counter is read with default `'0'` and cast to int.
	 *
	 * Proves openspec/specs/prometheus-metrics/spec.md, scenarios "No theming
	 * syncs performed" and "Syncs counter is read from IConfig".
	 */
	public function testThemingSyncsCounterDefaultsToZeroAndIsCastToInt(): void {
		$unset = $this->controller->index()->render();
		$this->assertSame(expected: '0', actual: $this->defaults['theming_syncs_total']);
		$this->assertStringContainsString(needle: "\nnldesign_theming_syncs_total 0\n", haystack: $unset);

		$this->appConfig['theming_syncs_total'] = '3';
		$set = $this->controller->index()->render();
		$this->assertStringContainsString(needle: "\nnldesign_theming_syncs_total 3\n", haystack: $set);
	}//end testThemingSyncsCounterDefaultsToZeroAndIsCastToInt()

	/**
	 * The four collaborators arrive as private readonly promoted constructor
	 * parameters of the expected types.
	 *
	 * Proves openspec/specs/prometheus-metrics/spec.md, scenario "Dependencies
	 * injected".
	 */
	public function testDependenciesArePrivateReadonlyPromotedParameters(): void {
		$expected = [
			'config' => IConfig::class,
			'tokenSetService' => TokenSetService::class,
			'overridesSvc' => CustomOverridesService::class,
			'logger' => LoggerInterface::class,
		];

		$constructor = new \ReflectionMethod(MetricsController::class, '__construct');
		$params = [];
		foreach ($constructor->getParameters() as $param) {
			$params[$param->getName()] = $param;
		}

		foreach ($expected as $name => $type) {
			$this->assertArrayHasKey(key: $name, array: $params, message: "constructor parameter \${$name}");
			$this->assertTrue(condition: $params[$name]->isPromoted(), message: "\${$name} must be promoted");
			$this->assertSame(expected: $type, actual: (string)$params[$name]->getType());

			$property = new \ReflectionProperty(MetricsController::class, $name);
			$this->assertTrue(condition: $property->isPrivate(), message: "\${$name} must be private");
			$this->assertTrue(condition: $property->isReadOnly(), message: "\${$name} must be readonly");
		}
	}//end testDependenciesArePrivateReadonlyPromotedParameters()

	/**
	 * The controller never builds or looks up its services itself.
	 *
	 * Proves openspec/specs/prometheus-metrics/spec.md, scenario "No direct
	 * service instantiation".
	 */
	public function testSourceHasNoDirectServiceInstantiation(): void {
		$source = (string)file_get_contents(__DIR__ . '/../../../lib/Controller/MetricsController.php');

		$this->assertDoesNotMatchRegularExpression(pattern: '/\bnew\s+\\\\?[\w\\\\]*Service\b/', string: $source);
		$this->assertStringNotContainsString(needle: '\\OC::$server', haystack: $source);
		$this->assertStringNotContainsString(needle: 'Server::get(', haystack: $source);
	}//end testSourceHasNoDirectServiceInstantiation()
}//end class
