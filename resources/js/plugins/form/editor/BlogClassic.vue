<template>
    <Ckeditor
        v-model="modalValue"
        :editor="ClassicEditor"
        :config="editorConfig"
    />
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
    Heading,
    Image,
    ImageCaption,
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
import { computed } from "vue";

const props = withDefaults(
    defineProps<{
        uploadUrl?: string | null;
        minHeight?: string;
    }>(),
    {
        uploadUrl: null,
        minHeight: "420px",
    },
);

const modalValue = defineModel<string>();

class BlogUploadAdapter implements UploadAdapter {
    constructor(
        private readonly loader: FileLoader,
        private readonly url: string,
    ) {}

    upload(): Promise<{ default: string }> {
        return this.loader.file.then(
            (file) =>
                new Promise((resolve, reject) => {
                    if (!file) {
                        reject("No file selected");
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
                                reject("Upload response missing url");
                                return;
                            }
                            resolve({ default: url });
                        })
                        .catch((error) => {
                            reject(
                                error?.response?.data?.message ||
                                    error?.message ||
                                    "Upload failed",
                            );
                        });
                }),
        );
    }

    abort(): void {
        // no-op
    }
}

function createUploadPlugin(uploadUrl: string) {
    return function BlogUploadAdapterPlugin(editor: Editor) {
        editor.plugins.get("FileRepository").createUploadAdapter = (loader) =>
            new BlogUploadAdapter(loader, uploadUrl);
    };
}

const editorConfig = computed<EditorConfig>(() => ({
    extraPlugins: props.uploadUrl ? [createUploadPlugin(props.uploadUrl)] : [],
    plugins: [
        Autoformat,
        BlockQuote,
        Bold,
        CloudServices,
        Essentials,
        Heading,
        Image,
        ImageCaption,
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
            "uploadImage",
            "insertTable",
            "blockQuote",
            "mediaEmbed",
            "|",
            "bulletedList",
            "numberedList",
            "|",
            "outdent",
            "indent",
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
}));
</script>

<style scoped>
:deep(.ck-editor__editable_inline) {
    min-height: v-bind(minHeight);
}
</style>
