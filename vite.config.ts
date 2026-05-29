import { createAppConfig } from '@nextcloud/vite-config'
import { join, resolve } from 'path'

export default createAppConfig(
	{
		main: resolve(join('src', 'main.ts')),
		'files-action': resolve(join('src', 'files-action.ts')),
		editor: resolve(join('src', 'editor.ts')),
		viewer: resolve(join('src', 'viewer.js')),
	},
	{
		createEmptyCSSEntryPoints: true,
		extractLicenseInformation: true,
		thirdPartyLicense: false,
	},
)
