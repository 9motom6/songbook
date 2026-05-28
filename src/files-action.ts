import { mdiFilePdfBox } from '@mdi/js'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import type { ActionContext, ActionContextSingle, IFileAction } from '@nextcloud/files'
import { registerFileAction } from '@nextcloud/files'
import { getClient, getDefaultPropfind, getRootPath, resultToNode } from '@nextcloud/files/dav'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import type { FileStat, ResponseDataDetailed } from 'webdav'

const CHORDPRO_EXTENSIONS = ['.cho', '.crd', '.chopro', '.chordpro', '.pro']

const action: IFileAction = {
	id: 'songbook-to-pdf',
	displayName: () => t('songbook', 'Convert to PDF'),
	iconSvgInline: () => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="${mdiFilePdfBox}" fill="currentColor"/></svg>`,
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

			// Fetch the new PDF via WebDAV and emit both created and updated events:
			// - files:node:created inserts a new row when the PDF didn't exist before
			// - files:node:updated refreshes an existing row when the PDF was overwritten
			try {
				const client = getClient()
				const pdfDavPath = `${getRootPath()}${node.dirname}/${pdfName}`
				const stat = await client.stat(pdfDavPath, { details: true, data: getDefaultPropfind() }) as ResponseDataDetailed<FileStat>
				const newNode = resultToNode(stat.data)
				emit('files:node:created', newNode)
				emit('files:node:updated', newNode)
			} catch {
				// DAV fetch failed — conversion still succeeded, list will refresh on next load
			}

			showSuccess(t('songbook', '{name} converted to PDF', { name: node.basename }))
			// Return null so the Files framework does not show its own generic success toast
			// on top of the one we already showed above.
			return null
		} catch {
			showError(t('songbook', 'Failed to convert {name} to PDF', { name: node.basename }))
			return null
		}
	},
}

registerFileAction(action)
