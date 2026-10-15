<?php

/**
 * Puts the workspace's mobile logo and touch icon on every page.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Listener
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 */

declare(strict_types=1);

namespace OCA\Thematiq\Listener;

use OCA\Thematiq\Service\WorkspaceIconService;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Called by ThemeInjectionListener on every page and the login page: a `<link rel="apple-touch-icon">` when a touch icon is set,
 * and a `<style>` that draws the mobile logo below 1024 px when one is set. Nothing when neither
 * is. Fails open: an image that cannot be read never breaks a page.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/changes/mobile-logo-and-touch-icon/specs/mobile-logo-and-touch-icon/spec.md#requirement-the-mobile-logo-and-the-touch-icon-reach-every-page
 */
class WorkspaceIconListener implements IEventListener {

	/**
	 * The id of the inline style block.
	 *
	 * @var string
	 */
	public const STYLE_ID = 'thematiq-mobile-logo';

	/**
	 * Constructor.
	 *
	 * @param WorkspaceIconService $icons The stored images.
	 * @param LoggerInterface $logger Logs an image that could not be read.
	 */
	public function __construct(
		private readonly WorkspaceIconService $icons,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Add the tags for the images that are set.
	 *
	 * @param Event $event The template event.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/mobile-logo-and-touch-icon/specs/mobile-logo-and-touch-icon/spec.md#requirement-the-mobile-logo-and-the-touch-icon-reach-every-page
	 */
	public function handle(Event $event): void {
		if (($event instanceof BeforeTemplateRenderedEvent) === false && ($event instanceof BeforeLoginTemplateRenderedEvent) === false) {
			return;
		}

		try {
			$status = $this->icons->status();
		} catch (Throwable $e) {
			$this->logger->warning('Thematiq: the workspace icons could not be read.', ['exception' => $e]);

			return;
		}

		if ($status['touch-icon'] !== null) {
			$this->emit(tag: 'link', attributes: ['rel' => 'apple-touch-icon', 'sizes' => '180x180', 'href' => $status['touch-icon']], text: '');
		}

		if ($status['mobile-logo'] !== null) {
			$this->emit(tag: 'style', attributes: ['id' => self::STYLE_ID], text: $this->icons->mobileLogoCss(url: $status['mobile-logo']));
		}
	}//end handle()

	/**
	 * Add one tag to the page head. Overridable, so a test can capture it.
	 *
	 * @param string $tag The tag.
	 * @param array<string, string> $attributes Its attributes.
	 * @param string $text Its content.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) \OCP\Util::addHeader() is the Nextcloud API for header injection.
	 *
	 * @spec openspec/changes/mobile-logo-and-touch-icon/specs/mobile-logo-and-touch-icon/spec.md#requirement-the-mobile-logo-and-the-touch-icon-reach-every-page
	 */
	protected function emit(string $tag, array $attributes, string $text): void {
		\OCP\Util::addHeader(tag: $tag, attributes: $attributes, text: $text);
	}//end emit()
}//end class
