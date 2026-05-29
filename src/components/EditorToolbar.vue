<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import { translate as t } from '@nextcloud/l10n'

defineProps<{
	filename: string
	isDirty: boolean
	isSaving: boolean
}>()

const emit = defineEmits<{
	save: []
}>()
</script>

<template>
	<div class="editor-toolbar">
		<span class="editor-toolbar__filename">
			{{ filename }}
			<span v-if="isDirty" class="editor-toolbar__dirty" :title="t('songbook', 'Unsaved changes')">●</span>
		</span>
		<NcButton
			:disabled="!isDirty || isSaving"
			:aria-label="t('songbook', 'Save')"
			type="primary"
			@click="emit('save')">
			{{ isSaving ? t('songbook', 'Saving…') : t('songbook', 'Save') }}
		</NcButton>
	</div>
</template>

<style scoped>
.editor-toolbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 8px 16px;
	border-bottom: 1px solid var(--color-border);
	background: var(--color-main-background);
	min-height: 50px;
	flex-shrink: 0;
}

.editor-toolbar__filename {
	font-weight: 600;
	font-size: 14px;
	color: var(--color-main-text);
	display: flex;
	align-items: center;
	gap: 6px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.editor-toolbar__dirty {
	color: var(--color-warning);
	font-size: 10px;
	line-height: 1;
}
</style>
