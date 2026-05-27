<?php

namespace OCA\Songbook\Service;

use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class ChordProBinaryManager {

	private const APPIMAGE_SHA256 = '6fdc3813585749ccc00628a5bf7ebbfa0b6caf38edb474bbbf4d9d19f5f50729';
	private const APPIMAGE_URL = 'https://github.com/ChordPro/chordpro/releases/download/R6.101.0/ChordPro-6.101.0-wk4.0.AppImage';

	private IClientService $httpClientService;
	private LoggerInterface $logger;

	/** Directory that holds both the .AppImage and the extracted squashfs-root/ */
	private string $binDir;

	/** The downloaded .AppImage — kept on disk for hash verification */
	private string $appImagePath;

	/** The actual chordpro binary after extraction: squashfs-root/chordpro */
	private string $appRunPath;

	public function __construct(IClientService $httpClientService, LoggerInterface $logger) {
		$this->httpClientService = $httpClientService;
		$this->logger = $logger;

		$this->binDir = __DIR__ . '/../../data/bin';
		$this->appImagePath = $this->binDir . '/chordpro.AppImage';
		// Use the chordpro binary directly — AppRun is broken without FUSE
		$this->appRunPath = $this->binDir . '/squashfs-root/chordpro';
	}

	/**
	 * Ensures the ChordPro binary is downloaded and extracted.
	 * Safe to call on every boot — exits immediately if already up to date.
	 */
	public function ensureBinaryExists(): void {
		$appImageOk = file_exists($this->appImagePath)
			&& hash_file('sha256', $this->appImagePath) === self::APPIMAGE_SHA256;

		if ($appImageOk && file_exists($this->appRunPath)) {
			$this->logger->info('Songbook: ChordPro already installed and verified, skipping.');
			return;
		}

		if (!$appImageOk) {
			$this->downloadAppImage();
		}

		$this->extractAppImage();
	}

	/**
	 * Returns the path to the extracted AppRun binary, installing it first if needed.
	 */
	public function getBinaryPath(): string {
		$this->ensureBinaryExists();
		return $this->appRunPath;
	}

	/**
	 * Returns the squashfs-root directory.
	 * Must be set as APPDIR env var when invoking AppRun outside of a FUSE-mounted AppImage.
	 */
	public function getAppDir(): string {
		return $this->binDir . '/squashfs-root';
	}

	private function downloadAppImage(): void {
		if (file_exists($this->appImagePath)) {
			$this->logger->warning('Songbook: AppImage checksum mismatch — re-downloading...');
			unlink($this->appImagePath);
			$this->removeExtractedDir();
		}

		if (!is_dir($this->binDir)) {
			mkdir($this->binDir, 0755, true);
		}

		$this->logger->warning('Songbook: Downloading ChordPro AppImage...');

		$client = $this->httpClientService->newClient();
		$client->get(self::APPIMAGE_URL, [
			'save_to' => $this->appImagePath,
			'timeout' => 90,
		]);

		$actual = hash_file('sha256', $this->appImagePath);
		if ($actual !== self::APPIMAGE_SHA256) {
			unlink($this->appImagePath);
			throw new \RuntimeException(
				'ChordPro AppImage SHA256 mismatch — expected ' . self::APPIMAGE_SHA256 . ', got ' . $actual,
			);
		}

		chmod($this->appImagePath, 0755);
		$this->logger->warning('Songbook: AppImage downloaded and verified.');
	}

	private function extractAppImage(): void {
		$this->removeExtractedDir();

		$this->logger->warning('Songbook: Extracting AppImage (no FUSE required after this)...');

		// --appimage-extract creates squashfs-root/ in CWD, so cd into binDir first
		$cmd = 'cd ' . escapeshellarg($this->binDir)
			. ' && ' . escapeshellarg($this->appImagePath)
			. ' --appimage-extract 2>&1';

		exec($cmd, $output, $exitCode);

		if ($exitCode !== 0 || !file_exists($this->appRunPath)) {
			throw new \RuntimeException(
				'AppImage extraction failed (exit ' . $exitCode . '): ' . implode("\n", $output),
			);
		}

		$this->logger->info('Songbook: ChordPro extracted successfully, ready to use.');
	}

	private function removeExtractedDir(): void {
		$dir = $this->binDir . '/squashfs-root';
		if (is_dir($dir)) {
			// Recursively delete the old extraction
			$this->rrmdir($dir);
		}
	}

	private function rrmdir(string $dir): void {
		foreach (scandir($dir) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			$path = $dir . '/' . $entry;
			is_dir($path) ? $this->rrmdir($path) : unlink($path);
		}
		rmdir($dir);
	}
}
