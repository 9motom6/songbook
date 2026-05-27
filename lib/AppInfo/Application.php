<?php

declare(strict_types=1);

namespace OCA\Songbook\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Songbook\Listener\LoadFilesPluginListener;
use OCA\Songbook\Service\ChordProBinaryManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Server;

class Application extends App implements IBootstrap {
	public const APP_ID = 'songbook';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadFilesPluginListener::class);
	}

	public function boot(IBootContext $context): void {
		/** @var ChordProBinaryManager $binaryManager */
		$binaryManager = Server::get(ChordProBinaryManager::class);
		$binaryManager->ensureBinaryExists();
	}
}
