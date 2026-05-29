<script setup lang="ts">
import { showError, showSuccess } from '@nextcloud/dialogs'
import { getClient, getRootPath } from '@nextcloud/files/dav'
import { translate as t } from '@nextcloud/l10n'
import { computed, onMounted, ref, watch } from 'vue'

import EditorPane from './EditorPane.vue'
import EditorToolbar from './EditorToolbar.vue'

// Props are injected by the Viewer mixin via the Vue 2/3 bridge in viewer.js.
// filename: user-relative path, e.g. /Songbook/my-song.chopro
// basename: display name, e.g. my-song.chopro
const props = defineProps<{
	filename: string
	basename?: string
	mime: string
	active?: boolean
	// loaded?: boolean  TODO: remove this
}>()

// Signals the Viewer (via the bridge) to hide its loading spinner.
const emit = defineEmits<{
	'update:loaded': [value: boolean]
}>()

const content = ref('')
const originalContent = ref('')
const isLoading = ref(false)
const isSaving = ref(false)
const hasError = ref(false)

const isDirty = computed(() => content.value !== originalContent.value)
const displayName = computed(() => props.basename ?? props.filename.split('/').pop() ?? props.filename)
const davPath = computed(() => `${getRootPath()}${props.filename}`)

async function loadFile() {
	isLoading.value = true
	hasError.value = false
	try {
		const client = getClient()
		const raw = await client.getFileContents(davPath.value, { format: 'text' }) as string
		content.value = raw
		originalContent.value = raw
	} catch {
		hasError.value = true
		showError(t('songbook', 'Failed to load {name}', { name: displayName.value }))
	} finally {
		isLoading.value = false
		emit('update:loaded', true)
	}
}

async function saveFile() {
	if (!isDirty.value) {
		return
	}
	isSaving.value = true
	try {
		const client = getClient()
		await client.putFileContents(davPath.value, content.value, { overwrite: true })
		originalContent.value = content.value
		showSuccess(t('songbook', '{name} saved', { name: displayName.value }))
	} catch {
		showError(t('songbook', 'Failed to save {name}', { name: displayName.value }))
	} finally {
		isSaving.value = false
	}
}

function onKeydown(event: KeyboardEvent) {
	if ((event.ctrlKey || event.metaKey) && event.key === 's') {
		event.preventDefault()
		saveFile()
	}
}

// Tell the Viewer to hide its spinner as soon as our component is mounted.
// We show our own loading state while the file fetches.
onMounted(() => {
	emit('update:loaded', true)
})

// Reload whenever the Viewer navigates to a different file.
watch(
	() => props.filename,
	() => loadFile(),
	{ immediate: true },
)
</script>

<template>
	<div class="chordpro-editor" @keydown="onKeydown">
		<div v-if="isLoading" class="chordpro-editor__status">
			{{ t('songbook', 'Loading…') }}
		</div>
		<div v-else-if="hasError" class="chordpro-editor__status">
			{{ t('songbook', 'Could not load file.') }}
		</div>
		<template v-else>
			<EditorToolbar
				:filename="displayName"
				:is-dirty="isDirty"
				:is-saving="isSaving"
				@save="saveFile" />
			<EditorPane
				v-model="content"
				:disabled="isSaving" />
		</template>
	</div>
</template>

<style scoped>
.chordpro-editor {
	display: flex;
	flex-direction: column;
	width: 100%;
	height: 100%;
	background: var(--color-main-background);
	overflow: hidden;
}

.chordpro-editor__status {
	display: flex;
	align-items: center;
	justify-content: center;
	flex: 1;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
}
</style>
