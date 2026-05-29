import type { IFileAction, Node } from '@nextcloud/files'
import { DefaultType, registerFileAction } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'

import { CHORDPRO_EXTENSIONS, CHORDPRO_MIME_TYPES } from './constants.ts'

// Fallback default action for ChordPro files.
// Filters by extension so it works even when the filecache still stores the
// file as application/octet-stream (before the MIME type has been corrected).
// Passes an explicit mime so the Viewer finds our handler regardless.
const openAction: IFileAction = {
	id: 'songbook-open-editor',
	displayName: () => t('songbook', 'Open in ChordPro editor'),
	iconSvgInline: () => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12 3v10.55A4 4 0 1 0 14 17V7h4V3z"/></svg>',
	enabled: (nodes: Node[]) =>
		nodes.length === 1
		&& CHORDPRO_EXTENSIONS.some(ext =>
			nodes[0].basename.toLowerCase().endsWith(ext),
		),
	default: DefaultType.DEFAULT,
	async exec(node: Node): Promise<boolean | null> {
		const fileInfo = {
			filename: node.path,
			basename: node.basename,
			fileid: node.fileid,
			mime: CHORDPRO_MIME_TYPES[0],
			hasPreview: false,
		}
		// @ts-expect-error – OCA.Viewer is a global provided by the Viewer app
		window.OCA?.Viewer?.open({ fileInfo, list: [fileInfo] })
		return null
	},
}

registerFileAction(openAction)
