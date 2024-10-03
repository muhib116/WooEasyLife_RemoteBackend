<template>
    <Ckeditor
        v-model="modalValue"
        :editor="ClassicEditor"
        :config="editorConfig"
        height="500px"
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
    Base64UploadAdapter,
    CloudServices,
    CKBox,
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
    Markdown,
    List,
    MediaEmbed,
    Mention,
    Paragraph,
    PasteFromOffice,
    Table,
    TableColumnResize,
    TableToolbar,
    TextTransformation,
    EditorConfig,
} from "ckeditor5";
import { Ckeditor } from "@ckeditor/ckeditor5-vue";
const editorConfig: EditorConfig = {
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
        Base64UploadAdapter,
        Indent,
        IndentBlock,
        // Markdown,
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
            "code",
        ],
        shouldNotGroupWhenFull: true,
    },
    menuBar: {
        isVisible: true,
    },
    fontFamily: {
        options: [
            "default",
            "Ubuntu, Arial, sans-serif",
            "Ubuntu Mono, Courier New, Courier, monospace",
        ],
    },
    fontColor: {
        colorPicker: {
            format: "hex",
        },
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
};

const modalValue = defineModel<string>();
</script>
