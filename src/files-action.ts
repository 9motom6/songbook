import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import type { ActionContext, ActionContextSingle, IFileAction } from '@nextcloud/files'
import { registerFileAction } from '@nextcloud/files'
import { getClient, getRootPath, resultToNode } from '@nextcloud/files/dav'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import type { FileStat, ResponseDataDetailed } from 'webdav'

const CHORDPRO_EXTENSIONS = ['.cho', '.crd', '.chopro', '.chordpro', '.pro']

const action: IFileAction = {
	id: 'songbook-to-pdf',
	displayName: () => t('songbook', 'Convert to PDF'),
	iconSvgInline: () => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" fill="currentColor"/></svg>',
	enabled: (context: ActionContext) =>
		context.nodes.length > 0
		&& context.nodes.every(node =>
			CHORDPRO_EXTENSIONS.some(ext => node.basename.toLowerCase().endsWith(ext)),
		),
	async exec(context: ActionContextSingle): Promise<boolean | null> {
		const node = context.nodes[0]
		try {
			const response = await axios.post(generateUrl('/apps/songbook/api/convert'), {
				path: node.path,
			})

			const pdfName: string = response.data.pdfName

			// Fetch the new PDF via WebDAV and emit a creation event so the
			// Files app inserts it into the list without requiring a page reload.
			try {
				const client = getClient()
				const pdfDavPath = `${getRootPath()}${node.dirname}/${pdfName}`
				const stat = await client.stat(pdfDavPath, { details: true }) as ResponseDataDetailed<FileStat>
				const newNode = resultToNode(stat.data)
				emit('files:node:created', newNode)
			} catch {
				// DAV fetch failed — conversion still succeeded, list will refresh on next load
			}

			showSuccess(t('songbook', '{name} converted to PDF', { name: node.basename }))
			return true
		} catch {
			showError(t('songbook', 'Failed to convert {name} to PDF', { name: node.basename }))
			return false
		}
	},
}

registerFileAction(action)
