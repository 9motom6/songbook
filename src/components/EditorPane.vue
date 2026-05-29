<script setup lang="ts">
defineProps<{
	modelValue: string
	disabled?: boolean
}>()

const emit = defineEmits<{
	'update:modelValue': [value: string]
}>()
</script>

<template>
	<div class="editor-pane">
		<textarea
			class="editor-pane__textarea"
			:value="modelValue"
			:disabled="disabled"
			spellcheck="false"
			autocomplete="off"
			autocorrect="off"
			autocapitalize="off"
			@input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)" />
	</div>
</template>

<style scoped>
.editor-pane {
	flex: 1;
	display: flex;
	overflow: hidden;
}

.editor-pane__textarea {
	flex: 1;
	width: 100%;
	height: 100%;
	padding: 16px;
	border: none;
	outline: none;
	resize: none;
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-family: 'Courier New', Courier, monospace;
	font-size: 14px;
	line-height: 1.6;
	tab-size: 4;
	white-space: pre;
	overflow-wrap: normal;
	overflow-x: auto;
}

.editor-pane__textarea:disabled {
	opacity: 0.6;
	cursor: not-allowed;
}
</style>
