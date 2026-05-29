<?php

declare(strict_types=1);

namespace OCA\Songbook\Migration;

use OCP\Files\IMimeTypeLoader;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Repair step that registers ChordPro MIME types so that:
 * - new file uploads are detected as text/x-chordpro (via config/mimetypemapping.json)
 * - existing filecache rows for ChordPro extensions are updated (NC 32+)
 *
 * Runs automatically on: occ app:install, occ maintenance:repair, occ upgrade
 */
class RegisterMimeTypes implements IRepairStep {
	private const EXTENSIONS = ['cho', 'crd', 'chopro', 'chordpro', 'pro'];
	private const MIME_TYPE = 'text/x-chordpro';

	public function __construct(private readonly IMimeTypeLoader $mimeTypeLoader) {
	}

	public function getName(): string {
		return 'Register ChordPro MIME types';
	}

	public function run(IOutput $output): void {
		$this->writeMappingConfig($output);
		$this->updateDatabase($output);
	}

	/**
	 * Merge ChordPro extension → MIME type entries into config/mimetypemapping.json.
	 * This is the file the PHP MIME detector reads at runtime, so from this point on
	 * any newly uploaded ChordPro file will be stored with the correct MIME type.
	 */
	private function writeMappingConfig(IOutput $output): void {
		$configFile = \OC::$configDir . 'mimetypemapping.json';

		$mapping = [];
		if (file_exists($configFile)) {
			$decoded = json_decode((string)file_get_contents($configFile), true);
			if (is_array($decoded)) {
				$mapping = $decoded;
			}
		}

		$changed = false;
		foreach (self::EXTENSIONS as $ext) {
			if (!isset($mapping[$ext]) || $mapping[$ext] !== [self::MIME_TYPE]) {
				$mapping[$ext] = [self::MIME_TYPE];
				$changed = true;
			}
		}

		if ($changed) {
			$json = json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
			$written = file_put_contents($configFile, $json);
			if ($written === false) {
				$output->warning(
					"Could not write {$configFile} — new ChordPro uploads will still work because "
					. "the app registers MIME types dynamically in Application::boot(), but you may "
					. "also run: occ maintenance:mimetype:update-db --repair-filecache"
				);
			} else {
				$output->info('ChordPro MIME type mappings written to config/mimetypemapping.json');
			}
		} else {
			$output->info('config/mimetypemapping.json already contains ChordPro mappings — skipped');
		}
	}

	/**
	 * Ensure text/x-chordpro exists in the mimetypes table and update any existing
	 * filecache rows whose filename ends with a ChordPro extension.
	 * updateFilecache() is available since NC 32; on older versions we only register
	 * the MIME type so that new uploads are stored correctly.
	 */
	private function updateDatabase(IOutput $output): void {
		$mimeTypeId = $this->mimeTypeLoader->getId(self::MIME_TYPE);

		if (!method_exists($this->mimeTypeLoader, 'updateFilecache')) {
			// NC 31 – type is now in the DB, filecache repair requires NC 32+
			return;
		}

		foreach (self::EXTENSIONS as $ext) {
			/** @var int $count */
			$count = $this->mimeTypeLoader->updateFilecache($ext, $mimeTypeId);
			if ($count > 0) {
				$output->info("Updated {$count} filecache row(s) for .{$ext} files to " . self::MIME_TYPE);
			}
		}
	}
}
