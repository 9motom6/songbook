<?php

declare(strict_types=1);

namespace OCA\Songbook\Controller;

use OCA\Songbook\AppInfo\Application;
use OCA\Songbook\Service\ChordProBinaryManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class ConvertController extends Controller {
	public function __construct(
		IRequest $request,
		private IRootFolder $rootFolder,
		private ChordProBinaryManager $binaryManager,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Convert a ChordPro file to PDF and save it next to the source file.
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/convert')]
	public function convert(string $path): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$tmpInput = null;
		$tmpOutput = null;

		try {
			$userFolder = $this->rootFolder->getUserFolder($this->userId);
			$file = $userFolder->get($path);

			// Write the source file to a temp path so the AppImage can read it
			$tmpInput = tempnam(sys_get_temp_dir(), 'sb_in_') . '.cho';
			$tmpOutput = sys_get_temp_dir() . '/sb_out_' . uniqid() . '.pdf';
			file_put_contents($tmpInput, $file->getContent());

			$binary = $this->binaryManager->getBinaryPath();
			$appDir = $this->binaryManager->getAppDir();
			// Run the chordpro binary directly (bypassing AppRun which requires FUSE).
			// LD_LIBRARY_PATH → bundled .so files in squashfs-root/
			// PERL5LIB       → bundled Perl modules in squashfs-root/lib/
			$cmd = 'LD_LIBRARY_PATH=' . escapeshellarg($appDir) . ':$LD_LIBRARY_PATH'
				. ' PERL5LIB=' . escapeshellarg($appDir . '/lib')
				. ' ' . escapeshellarg($binary)
				. ' --generate=PDF '
				. escapeshellarg($tmpInput)
				. ' -o ' . escapeshellarg($tmpOutput)
				. ' 2>&1';

			exec($cmd, $cmdOutput, $exitCode);

			if ($exitCode !== 0 || !file_exists($tmpOutput)) {
				$details = implode("\n", $cmdOutput);
				$this->logger->error('Songbook: ChordPro conversion failed (exit ' . $exitCode . "): $details");
				return new JSONResponse(
					['error' => 'Conversion failed', 'details' => $details],
					Http::STATUS_INTERNAL_SERVER_ERROR,
				);
			}

			// Save the PDF next to the source file in Nextcloud
			$pdfName = pathinfo($file->getName(), PATHINFO_FILENAME) . '.pdf';
			$parentPath = dirname($path);
			$parentDir = ($parentPath === '/' || $parentPath === '.')
				? $userFolder
				: $userFolder->get($parentPath);

			/** @var \OCP\Files\Folder $parentDir */
			$pdfContent = file_get_contents($tmpOutput);

			try {
				$existing = $parentDir->get($pdfName);
				$existing->putContent($pdfContent);
			} catch (NotFoundException) {
				$parentDir->newFile($pdfName, $pdfContent);
			}

			$this->logger->info('Songbook: Converted ' . $file->getName() . ' → ' . $pdfName);
			return new JSONResponse(['pdfName' => $pdfName]);

		} catch (\Exception $e) {
			$this->logger->error('Songbook: Convert error: ' . $e->getMessage());
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		} finally {
			if ($tmpInput !== null && file_exists($tmpInput)) {
				unlink($tmpInput);
			}
			if ($tmpOutput !== null && file_exists($tmpOutput)) {
				unlink($tmpOutput);
			}
		}
	}
}
