<?php

declare(strict_types=1);

namespace OCA\Songbook\Listener;

use OCA\Viewer\Event\LoadViewer;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * Loads the ChordPro Viewer handler bundle when the Nextcloud Viewer app
 * initialises. The 'viewer' dependency ensures the Viewer app's own scripts
 * are fully loaded before ours, so OCA.Viewer.registerHandler() is available.
 *
 * @template-implements IEventListener<LoadViewer>
 * @psalm-suppress UnusedClass
 */
class LoadViewerListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof LoadViewer)) {
			return;
		}
		Util::addScript('songbook', 'songbook-viewer', 'viewer');
	}
}
