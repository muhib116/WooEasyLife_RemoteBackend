<template>
    <div class="blog-ckeditor">
        <Ckeditor
            v-model="modalValue"
            :editor="ClassicEditor"
            :config="editorConfig"
            @ready="onEditorReady"
        />
        <p
            v-if="uploadUrl"
            class="mt-2 text-[11px] text-slate-500 dark:text-slate-400"
        >
            Use the <strong>image</strong> toolbar icon to upload — it inserts
            <code class="text-[10px]">&lt;img src="…"&gt;</code> HTML automatically.
            Toggle <strong>Source</strong> to edit raw HTML.
        </p>
        <p
            v-if="uploadError"
            class="mt-1 text-xs text-rose-600 dark:text-rose-300"
        >
            {{ uploadError }}
        </p>
    </div>
</template>

<script setup lang="ts">
import {
    ClassicEditor,
    Autoformat,
    Bold,
    Italic,
    Underline,
    BlockQuote,
    CloudServices,
    Essentials,
    GeneralHtmlSupport,
    Heading,
    Image,
    ImageCaption,
    ImageInsert,
    ImageResize,
    ImageStyle,
    ImageToolbar,
    ImageUpload,
    PictureEditing,
    Indent,
    IndentBlock,
    Link,
    List,
    MediaEmbed,
    Mention,
    Paragraph,
    PasteFromOffice,
    SourceEditing,
    Table,
    TableColumnResize,
    TableToolbar,
    TextTransformation,
    type EditorConfig,
    type Editor,
    type FileLoader,
    type UploadAdapter,
} from "ckeditor5";
import { Ckeditor } from "@ckeditor/ckeditor5-vue";
import axios from "axios";
import { computed, onBeforeUnmount, ref, shallowRef } from "vue";

const props = withDefaults(
    defineProps<{
        uploadUrl?: string | null;
        minHeight?: string;
    }>(),
    {
        uploadUrl: null,
        minHeight: "720px",
    },
);

const emit = defineEmits<{
    uploaded: [payload: { url: string; path?: string }];
    "upload-error": [message: string];
}>();

const modalValue = defineModel<string>();
const uploadError = ref("");
const editorInstance = shallowRef<Editor | null>(null);
const editorMinHeight = computed(() => props.minHeight || "720px");
const editorMaxHeight = computed(() => "min(80vh, 960px)");

class BlogUploadAdapter implements UploadAdapter {
    constructor(
        private readonly loader: FileLoader,
        private readonly url: string,
        private readonly onSuccess: (url: string, path?: string) => void,
        private readonly onError: (message: string) => void,
    ) {}

    upload(): Promise<{ default: string }> {
        return this.loader.file.then(
            (file) =>
                new Promise((resolve, reject) => {
                    if (!file) {
                        const message = "No file selected";
                        this.onError(message);
                        reject(message);
                        return;
                    }

                    const data = new FormData();
                    data.append("upload", file);

                    axios
                        .post(this.url, data, {
                            headers: { "Content-Type": "multipart/form-data" },
                        })
                        .then((response) => {
                            const url = response.data?.url;
                            if (!url) {
                                const message = "Upload response missing url";
                                this.onError(message);
                                reject(message);
                                return;
                            }
                            this.onSuccess(url, response.data?.path);
                            resolve({ default: url });
                        })
                        .catch((error) => {
                            const message =
                                error?.response?.data?.message ||
                                error?.message ||
                                "Upload failed";
                            this.onError(message);
                            reject(message);
                        });
                }),
        );
    }

    abort(): void {
        // no-op
    }
}

function createUploadPlugin(
    uploadUrl: string,
    onSuccess: (url: string, path?: string) => void,
    onError: (message: string) => void,
) {
    return function BlogUploadAdapterPlugin(editor: Editor) {
        editor.plugins.get("FileRepository").createUploadAdapter = (loader) =>
            new BlogUploadAdapter(loader, uploadUrl, onSuccess, onError);
    };
}

const onUploadSuccess = (url: string, path?: string) => {
    uploadError.value = "";
    emit("uploaded", { url, path });

    // Replace AI cover placeholders in source HTML when present.
    const current = modalValue.value || "";
    if (/YOUR_COVER_IMAGE_URL/i.test(current)) {
        modalValue.value = current.replace(/YOUR_COVER_IMAGE_URL/gi, url);
    }
};

const onUploadError = (message: string) => {
    uploadError.value = message;
    emit("upload-error", message);
};

const editorConfig = computed<EditorConfig>(() => ({
    extraPlugins: props.uploadUrl
        ? [createUploadPlugin(props.uploadUrl, onUploadSuccess, onUploadError)]
        : [],
    plugins: [
        Autoformat,
        BlockQuote,
        Bold,
        CloudServices,
        Essentials,
        GeneralHtmlSupport,
        Heading,
        Image,
        ImageCaption,
        ImageInsert,
        ImageResize,
        ImageStyle,
        ImageToolbar,
        ImageUpload,
        Indent,
        IndentBlock,
        Italic,
        Link,
        List,
        MediaEmbed,
        Mention,
        Paragraph,
        PasteFromOffice,
        PictureEditing,
        SourceEditing,
        Table,
        TableColumnResize,
        TableToolbar,
        TextTransformation,
        Underline,
    ],
    toolbar: {
        items: [
            "undo",
            "redo",
            "|",
            "heading",
            "|",
            "bold",
            "italic",
            "underline",
            "|",
            "link",
            "insertImage",
            "insertTable",
            "blockQuote",
            "mediaEmbed",
            "|",
            "bulletedList",
            "numberedList",
            "|",
            "outdent",
            "indent",
            "|",
            "sourceEditing",
        ],
        shouldNotGroupWhenFull: true,
    },
    heading: {
        options: [
            { model: "paragraph", title: "Paragraph", class: "ck-heading_paragraph" },
            { model: "heading2", view: "h2", title: "Heading 2", class: "ck-heading_heading2" },
            { model: "heading3", view: "h3", title: "Heading 3", class: "ck-heading_heading3" },
            { model: "heading4", view: "h4", title: "Heading 4", class: "ck-heading_heading4" },
        ],
    },
    menuBar: {
        isVisible: true,
    },
    image: {
        insert: {
            integrations: ["upload", "url"],
            type: "auto",
        },
        resizeOptions: [
            {
                name: "resizeImage:original",
                label: "Default image width",
                value: null,
            },
            {
                name: "resizeImage:50",
                label: "50% page width",
                value: "50",
            },
            {
                name: "resizeImage:75",
                label: "75% page width",
                value: "75",
            },
        ],
        toolbar: [
            "imageTextAlternative",
            "toggleImageCaption",
            "|",
            "imageStyle:inline",
            "imageStyle:wrapText",
            "imageStyle:breakText",
            "|",
            "resizeImage",
        ],
    },
    htmlSupport: {
        allow: [
            {
                name: "img",
                attributes: true,
                classes: true,
                styles: true,
            },
            {
                name: /^(div|section|figure|figcaption|span|p|h2|h3|h4|ul|ol|li|a|table|thead|tbody|tr|th|td)$/,
                attributes: true,
                classes: true,
                styles: true,
            },
        ],
    },
}));

const onEditorReady = (editor: Editor) => {
    editorInstance.value = editor;
};

const escapeAttr = (value: string) =>
    String(value || "")
        .replace(/&/g, "&amp;")
        .replace(/"/g, "&quot;")
        .replace(/</g, "&lt;");

/**
 * Insert an image (or arbitrary HTML snippet) at the current selection.
 * Falls back to appending when the editor is in source mode / unavailable.
 */
const insertHtml = (html: string) => {
    const editor = editorInstance.value;
    const snippet = String(html || "").trim();
    if (!snippet) {
        return;
    }

    if (!editor) {
        modalValue.value = `${modalValue.value || ""}${snippet}`;
        return;
    }

    // Source editing mode: patch the raw HTML string.
    const sourceEditing = editor.plugins.get("SourceEditing") as
        | { isSourceEditingMode?: boolean }
        | undefined;
    if (sourceEditing?.isSourceEditingMode) {
        modalValue.value = `${modalValue.value || ""}${snippet}`;
        return;
    }

    editor.model.change(() => {
        const viewFragment = editor.data.processor.toView(snippet);
        const modelFragment = editor.data.toModel(viewFragment);
        editor.model.insertContent(modelFragment);
    });
};

const insertImage = (url: string, alt = "") => {
    const safeUrl = escapeAttr(url);
    const safeAlt = escapeAttr(alt);
    insertHtml(`<p><img src="${safeUrl}" alt="${safeAlt}"></p>`);
};

onBeforeUnmount(() => {
    editorInstance.value = null;
});

defineExpose({
    insertHtml,
    insertImage,
    getEditor: () => editorInstance.value,
});
</script>

<style scoped>
.blog-ckeditor :deep(.ck.ck-editor) {
    width: 100%;
}

.blog-ckeditor :deep(.ck.ck-editor__main > .ck-editor__editable),
.blog-ckeditor :deep(.ck-editor__editable_inline),
.blog-ckeditor :deep(.ck-editor__editable.ck-focused),
.blog-ckeditor :deep(.ck-editor__editable.ck-blurred) {
    min-height: v-bind(editorMinHeight) !important;
    height: auto;
    max-height: v-bind(editorMaxHeight);
    overflow-y: auto;
    line-height: 1.65;
    padding: 1rem 1.15rem;
}

.blog-ckeditor :deep(.ck-source-editing-area),
.blog-ckeditor :deep(.ck-source-editing-area textarea) {
    min-height: v-bind(editorMinHeight) !important;
    max-height: v-bind(editorMaxHeight);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12.5px;
    line-height: 1.55;
}

.blog-ckeditor :deep(.ck-content h2) {
    font-size: 1.35rem;
    font-weight: 700;
    margin: 1rem 0 0.5rem;
}

.blog-ckeditor :deep(.ck-content h3) {
    font-size: 1.15rem;
    font-weight: 700;
    margin: 0.85rem 0 0.4rem;
}
</style>
