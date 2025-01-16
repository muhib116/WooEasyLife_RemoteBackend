<template>
    <div class="w-full">
        <div ref="container" class="h-[200px]"></div>
    </div>
</template>
<script setup lang="ts">
import { onMounted, onUnmounted, ref, toRefs } from "vue";
import * as monaco from "monaco-editor";

enum LANGUAGES {
    HTML = "html",
    CSS = "css",
    JS = "javascript",
    shortJs = "JS",
    json = "json",
}

enum STORAGE_NAMES {
    CODE = "code",
}

type PAYLOAD = {
    code: string;
    type: LANGUAGES;
};
import {
    useDebounceFn,
    useLocalStorage,
    useResizeObserver,
} from "@vueuse/core/index.cjs";

const props = withDefaults(
    defineProps<{
        type: LANGUAGES;
        displayName: LANGUAGES;
    }>(),
    {
        type: LANGUAGES.json,
        displayName: LANGUAGES.json,
    }
);

const { type, displayName } = toRefs(props);

const emit = defineEmits<(e: "code-change", payload: PAYLOAD) => void>();

let container = ref<HTMLElement>();

let editor: monaco.editor.IStandaloneCodeEditor;

let code = useLocalStorage(`${STORAGE_NAMES.CODE}-${type.value}`, "");

const modelValue = defineModel();

onMounted(() => {
    editor = monaco.editor.create(container.value as HTMLElement, {
        language: "json",
        theme: "vs-dark",
    });

    if (modelValue.value) {
        editor.setValue(modelValue.value);
    }

    emit("code-change", {
        type: type.value,
        code: code.value,
    });

    editor.onDidChangeModelContent(
        useDebounceFn(() => {
            if (code.value !== editor.getValue()) {
                code.value = editor.getValue();

                modelValue.value = code.value;

                emit("code-change", {
                    type: type.value,
                    code: code.value,
                });
            }
        }, 500)
    );
});

let resizer = useResizeObserver(container, () => {
    editor.layout();
});

onUnmounted(() => {
    // editor.dispose();
    // resizer.stop();
});
</script>
