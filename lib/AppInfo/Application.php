<?php

declare(strict_types=1);

namespace OCA\Songbook\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Songbook\Listener\LoadFilesPluginListener;
use OCA\Songbook\Listener\LoadViewerListener;
use OCA\Songbook\Migration\RegisterMimeTypes;
use OCA\Viewer\Event\LoadViewer;
use OCA\Songbook\Service\ChordProBinaryManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\IMimeTypeDetector;
use OCP\Server;

class Application extends App implements IBootstrap {
	public const APP_ID = 'songbook';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadFilesPluginListener::class);
		$context->registerEventListener(LoadViewer::class, LoadViewerListener::class);
		$context->registerRepairStep(RegisterMimeTypes::class);
	}

	public function boot(IBootContext $context): void {
		/** @var ChordProBinaryManager $binaryManager */
		$binaryManager = Server::get(ChordProBinaryManager::class);
		$binaryManager->ensureBinaryExists();

		// Register ChordPro extensions with the MIME detector so that any new
		// file upload is immediately stored with the correct type, without
		// requiring a writable config/mimetypemapping.json or a manual occ command.
		// registerTypeArray() is not in IMimeTypeDetector's interface but exists
		// on the concrete \OC\Files\Type\Detection class used at runtime.
		$detector = Server::get(IMimeTypeDetector::class);
		if (method_exists($detector, 'registerTypeArray')) {
			$detector->registerTypeArray([
				'cho'      => ['text/x-chordpro'],
				'crd'      => ['text/x-chordpro'],
				'chopro'   => ['text/x-chordpro'],
				'chordpro' => ['text/x-chordpro'],
				'pro'      => ['text/x-chordpro'],
			]);
		}
	}
}
