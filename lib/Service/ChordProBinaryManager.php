<?php

namespace OCA\Songbook\Service;

use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class ChordProBinaryManager {

	private const CHORDBOOK_APP_IMAGE_SHA256 = '6fdc3813585749ccc00628a5bf7ebbfa0b6caf38edb474bbbf4d9d19f5f50729';
	private const CHORDBOOK_APP_IMAGE_URL = 'https://github.com/ChordPro/chordpro/releases/download/R6.101.0/ChordPro-6.101.0-wk4.0.AppImage';


	private IClientService $httpClientService;
	private LoggerInterface $logger;
	private string $binDir;
	private string $binaryPath;

	public function __construct(IClientService $httpClientService, LoggerInterface $logger) {
		$this->httpClientService = $httpClientService;
		$this->logger = $logger;

		// Saves the binary inside your app's 'data/bin' folder
		$this->binDir = __DIR__ . '/../../data/bin';
		$this->binaryPath = $this->binDir . '/chordpro.AppImage';
	}

	/**
	 * Ensures the ChordPro binary exists, downloading it from GitHub if missing.
	 * Safe to call on every boot — exits immediately if already installed.
	 */
	public function ensureBinaryExists(): void {
		if (file_exists($this->binaryPath)) {
			if (hash_file('sha256', $this->binaryPath) === self::CHORDBOOK_APP_IMAGE_SHA256) {
				$this->logger->warning('Songbook: ChordPro binary already present and verified, skipping download.');
				return;
			}
			$this->logger->warning('Songbook: ChordPro binary checksum mismatch — re-downloading...');
			unlink($this->binaryPath);
		}

		if (!is_dir($this->binDir)) {
			mkdir($this->binDir, 0755, true);
		}

		$this->logger->warning('Songbook: ChordPro binary not found — starting download...');

		$client = $this->httpClientService->newClient();

		try {
			$client->get(self::CHORDBOOK_APP_IMAGE_URL, [
				'save_to' => $this->binaryPath,
				'timeout' => 90,
			]);

			if (file_exists($this->binaryPath)) {
				$actual = hash_file('sha256', $this->binaryPath);
				if ($actual !== self::CHORDBOOK_APP_IMAGE_SHA256) {
					unlink($this->binaryPath);
					$this->logger->error('Songbook: SHA256 mismatch — expected ' . self::CHORDBOOK_APP_IMAGE_SHA256 . ', got ' . $actual . '. File deleted.');
					throw new \RuntimeException('ChordPro binary SHA256 checksum mismatch.');
				}
				chmod($this->binaryPath, 0755);
				$this->logger->warning('Songbook: ChordPro engine installed and verified successfully.');
			}
		} catch (\Exception $e) {
			$this->logger->error('Songbook: Failed to download ChordPro binary: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Returns the absolute path to the binary, downloading it on the fly if missing.
	 */
	public function getBinaryPath(): string {
		$this->ensureBinaryExists();
		return $this->binaryPath;
	}
}
