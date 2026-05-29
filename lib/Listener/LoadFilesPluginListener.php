<?php

declare(strict_types=1);

namespace OCA\Songbook\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @template-implements IEventListener<LoadAdditionalScriptsEvent>
 * @psalm-suppress UnusedClass
 */
class LoadFilesPluginListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof LoadAdditionalScriptsEvent)) {
			return;
		}
		Util::addScript('songbook', 'songbook-files-action');
		Util::addInitScript('songbook', 'songbook-editor');
	}
}
