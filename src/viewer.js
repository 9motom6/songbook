/**
 * Viewer integration for ChordPro files.
 *
 * The Nextcloud Viewer app uses Vue 2 internally. Our editor uses Vue 3.
 * To bridge the two, we register a thin Vue 2-compatible component
 * (ViewerBridge) as the Viewer handler. ViewerBridge renders an empty <div>
 * and, in mounted(), creates a separate Vue 3 application inside that div
 * hosting the real ChordProEditor.
 *
 * Prop updates from Vue 2 (e.g. navigating to the next file) are forwarded
 * to Vue 3 via a reactive bridge object. The Vue 3 emit for update:loaded is
 * forwarded back to Vue 2's $emit so the Viewer's :loaded.sync binding works.
 */

/* global OCA */

import { createApp, reactive, h } from 'vue'

import ChordProEditor from './components/ChordProEditor.vue'
import { CHORDPRO_MIME_TYPES } from './constants.ts'

/**
 * Vue 2-compatible component options object.
 * This is rendered by the Viewer's Vue 2 runtime as a thin shell;
 * our actual Vue 3 editor lives inside the div it creates.
 */
const ViewerBridge = {
	name: 'ChordProViewerBridge',
	inheritAttrs: false,

	props: {
		filename: { type: String, default: null },
		basename: { type: String, default: null },
		fileid: { default: null },
		mime: { type: String, default: null },
		active: { default: false },
		loaded: { default: false },
	},

	/**
	 * Vue 2 render function.
	 * The `h2` argument is Vue 2's createElement — keep it separate from the
	 * Vue 3 `h` imported at the top of this module.
	 */
	render(h2) {
		return h2('div', {
			style: 'width:100%;height:100%;display:flex;flex-direction:column;overflow:hidden;',
		})
	},

	mounted() {
		const vm = this

		// Vue 3 reactive object bridges prop changes from Vue 2 → Vue 3.
		const state = reactive({
			filename: this.filename,
			basename: this.basename,
			mime: this.mime,
			active: this.active,
		})
		this._state = state

		// Mount the Vue 3 editor inside the div created by render().
		this._vue3App = createApp({
			setup() {
				return () =>
					h(ChordProEditor, {
						filename: state.filename,
						basename: state.basename,
						mime: state.mime,
						active: state.active,
						// Bridge update:loaded from Vue 3 → Vue 2 $emit
						// so the Viewer's :loaded.sync binding picks it up.
						'onUpdate:loaded': (value) => vm.$emit('update:loaded', value),
					})
			},
		})

		this._vue3App.mount(this.$el)
	},

	beforeDestroy() {
		if (this._vue3App) {
			this._vue3App.unmount()
		}
	},

	watch: {
		filename(v) { if (this._state) this._state.filename = v },
		basename(v) { if (this._state) this._state.basename = v },
		mime(v)     { if (this._state) this._state.mime = v },
		active(v)   { if (this._state) this._state.active = v },
	},
}

if (typeof OCA !== 'undefined' && OCA.Viewer) {
	OCA.Viewer.registerHandler({
		id: 'chordpro-editor',
		mimes: CHORDPRO_MIME_TYPES,
		component: ViewerBridge,
		theme: 'default',
	})
} else {
	console.warn('[Songbook] Nextcloud Viewer app is not available — ChordPro files will not open in the Viewer.')
}
