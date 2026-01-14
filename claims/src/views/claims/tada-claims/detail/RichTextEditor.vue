<template>
  <div class="rich-text-editor">




    <!-- Editor -->
    <div class="border rounded-lg">
      <!-- Rich Text Mode -->
      <div v-if="editorMode === 'rich'">

        <div class="border-b bg-gray-50 p-2">
          <div class="flex flex-wrap items-center gap-2">

            <button
              @click="formatText('bold')"
              class="toolbar-btn"
              title="Bold"
            >
              <Icon icon="mdi:format-bold" width="20" height="20" />
            </button>
            <button
              @click="formatText('italic')"
              class="toolbar-btn"
              title="Italic"
            >
              <Icon icon="mdi:format-italic" width="20" height="20" />
            </button>
            <button
              @click="formatText('underline')"
              class="toolbar-btn"
              title="Underline"
            >
              <Icon icon="mdi:format-underline" width="20" height="20" />
            </button>
            <button
              @click="formatText('strikeThrough')"
              class="toolbar-btn"
              title="Strikethrough"
            >
              <Icon icon="mdi:format-strikethrough" width="20" height="20" />
            </button>

            <!-- Text Color -->
            <div class="flex items-center gap-1">
              <input
                type="color"
                v-model="currentColor"
                @mousedown="saveSelection"
                @change="applyTextColor(currentColor)"
                class="w-8 h-8 border rounded cursor-pointer"
                title="Pick a text color"
              />
            </div>

            <div class="w-px h-6 bg-gray-300 mx-1"></div>

            <!-- headings -->
            <select
              v-model="currentHeading"
              @change="formatHeader(currentHeading)"
              class="text-sm border rounded px-2 py-1"
            >
              <option value="">Normal</option>
              <option value="h1">Heading 1</option>
              <option value="h2">Heading 2</option>
              <option value="h3">Heading 3</option>
              <option value="h4">Heading 4</option>
            </select>
            <!-- Font Size -->
<select
  v-model="currentFontSize"
  @change="formatFontSize(currentFontSize)"
  class="text-sm border rounded px-2 py-1"
>
  <option value="">Font Size</option>
  <option value="12px">12px</option>
  <option value="14px">14px</option>
  <option value="16px">16px</option>
  <option value="18px">18px</option>
  <option value="20px">20px</option>
  <option value="24px">24px</option>
  <option value="28px">28px</option>
  <option value="32px">32px</option>
  <option value="34px">34px</option>
  <option value="36px">36px</option>
  <option value="40px">40px</option>
</select>


            <!-- Font Family -->
            <select
              v-model="currentFont"
              @change="formatFont(currentFont)"
              class="text-sm border rounded px-2 py-1"
            >
              <option value="">Font Family</option>
              <option value="Arial, sans-serif">Arial</option>
              <option value="'Times New Roman', serif">Times New Roman</option>
              <option value="'Noto Sans Devanagari', Arial, sans-serif">
                Noto Sans 
              </option>
              <option value="'Courier New', monospace">Courier New</option>
              <option value="'Calibri', sans-serif">Calibri</option>
              <option value="'Georgia', serif">Georgia</option>
            </select>

            <div class="w-px h-6 bg-gray-300 mx-1"></div>

            <!-- Alignment -->
            <button
              @click="formatText('justifyLeft')"
              class="toolbar-btn"
              title="Align Left"
            >
              <Icon icon="mdi:format-align-left" width="20" height="20" />
            </button>
            <button
              @click="formatText('justifyCenter')"
              class="toolbar-btn"
              title="Align Center"
            >
              <Icon icon="mdi:format-align-center" width="20" height="20" />
            </button>
            <button
              @click="formatText('justifyRight')"
              class="toolbar-btn"
              title="Align Right"
            >
              <Icon icon="mdi:format-align-right" width="20" height="20" />
            </button>

            <div class="w-px h-6 bg-gray-300 mx-1"></div>

            <!-- Lists -->
            <button
              @click="formatText('insertOrderedList')"
              class="toolbar-btn"
              title="Numbered List"
            >
              <Icon icon="mdi:format-list-numbered" width="20" height="20" />
            </button>
            <button
              @click="formatText('insertUnorderedList')"
              class="toolbar-btn"
              title="Bullet List"
            >
              <Icon icon="mdi:format-list-bulleted" width="20" height="20" />
            </button>

            <div class="w-px h-6 bg-gray-300 mx-1"></div>
            <!-- Link -->
<button
  @click="createLink"
  class="toolbar-btn"
  title="Insert Link"
>
  <Icon icon="mdi:link-variant" width="20" height="20" />
</button>

<!-- Unlink -->
<button
  @click="formatText('unlink')"
  class="toolbar-btn"
  title="Remove Link"
>
  <Icon icon="mdi:link-off" width="20" height="20" />
</button>

<div class="w-px h-6 bg-gray-300 mx-1"></div>

<!-- Subscript -->
<button
  @click="formatText('subscript')"
  class="toolbar-btn"
  title="Subscript"
>
  <Icon icon="ph:text-subscript-bold" width="20" height="20" />
</button>

<!-- Superscript -->
<button
  @click="formatText('superscript')"
  class="toolbar-btn"
  title="Superscript"
>
  <Icon icon="ph:text-superscript-bold" width="20" height="20" />
</button>

<!-- Horizontal Line -->
<button
  @click="insertHR"
  class="toolbar-btn"
  title="Insert Line Break"
>
  <Icon icon="mdi:minus" width="20" />
</button>

<!-- Blockquote -->
<button
  @click="formatText('formatBlock', '<blockquote>')"
  class="toolbar-btn"
  title="Blockquote"
>
  <Icon icon="mdi:format-quote-close" width="20" />
</button>

<div class="w-px h-6 bg-gray-300 mx-1"></div>

<!-- Increase Indent  @click="formatText('indent')"-->
<button
   @click="customIndent"
  class="toolbar-btn"
  title="Increase Indent"
>
  <Icon icon="mdi:format-indent-increase" width="20" />
</button>

<!-- Decrease Indent --> <!-- @click="formatText('outdent')" -->
<button

 @click="customOutdent"
  class="toolbar-btn"
  title="Decrease Indent"
>
  <Icon icon="mdi:format-indent-decrease" width="20" />
</button>

<div class="w-px h-6 bg-gray-300 mx-1"></div>
<!-- Highlight / Marker -->
<button
  @click="applyHighlight"
  class="toolbar-btn"
  title="Highlight"
>
  <Icon icon="mdi:marker" width="20" height="20" />
</button>

<!-- Undo -->
<button
  @click="undoAction"
  class="toolbar-btn"
  title="Undo"
>
  <Icon icon="mdi:undo" width="20" />
</button>

<!-- Redo -->
<button
  @click="redoAction"
  class="toolbar-btn"
  title="Redo"
>
  <Icon icon="mdi:redo" width="20" />
</button>

<div class="w-px h-6 bg-gray-300 mx-1"></div>

<!-- Copy Formatting -->
<!-- <button
  @click="copyFormatting"
  class="toolbar-btn"
  title="Copy Formatting"
>
  <Icon icon="mdi:content-copy" width="20" />
</button> -->

<!-- Apply Formatting -->
<!-- <button
  @click="applyCopiedFormatting"
  class="toolbar-btn"
  title="Apply Copied Formatting"
>
  <Icon icon="mdi:content-paste" width="20" />
</button> -->

<!-- Clear Block Formatting -->
<!-- <button
  @click="clearBlockFormatting"
  class="toolbar-btn"
  title="Clear Paragraph Formatting"
>
  <Icon icon="mdi:format-clear" width="20" />
</button> -->



<div class="w-px h-6 bg-gray-300 mx-1"></div>


            <!-- Table -->
            <button
              @click="insertTable"
              class="toolbar-btn bg-green-100"
              title="Insert Table"
            >
              <Icon icon="mdi:table" width="20" height="20" />
            </button>

            <div class="w-px h-6 bg-gray-300 mx-1"></div>

            <!-- Utilities -->
            <button
              @click="formatText('removeFormat')"
              class="toolbar-btn"
              title="Clear Format"
            >
              <Icon icon="mdi:format-clear" width="20" height="20" />
            </button>

            <!-- View Mode Switcher -->
<!-- <button
  @click="editorMode = 'rich'"
  class="toolbar-btn"
  :class="{ 'bg-blue-200': editorMode === 'rich' }"
  title="Rich Text Mode"
>
  <Icon icon="mdi:eye" width="20" />
</button> -->

<button
  @click="toggleCodeView"
  class="toolbar-btn"
  :class="{ 'bg-blue-200': editorMode === 'html' }"
  title="HTML Code Mode"
>
  <Icon icon="mdi:code-tags" width="20" />
</button>

<div class="w-px h-6 bg-gray-300 mx-1"></div>

          </div>
        </div>

        <!-- ContentEditable Rich Text Area -->
        <div
          ref="richEditor"
          class="rich-editor-content p-4 min-h-[100px] focus:outline-none"
          contenteditable="true"
          @input="onRichEditorInput"
          @keydown="onKeyDown"
          @paste="onRichEditorPaste"
        ></div>
      </div>

      <!-- HTML Source Mode -->
      <div v-else>
        <div class="bg-gray-50 px-4 py-2 text-sm font-medium border-b flex items-center justify-between">
  
  

  <!-- Back to Rich Mode Button -->
  <button
    @click="switchToRich"
    class="px-3 py-1 text-sm border border-gray-300 rounded bg-white hover:bg-gray-100 flex items-center gap-1"
    title="Back to Rich Editor"
  >
    <Icon icon="mdi:eye" width="18" />
    <span>Rich View</span>
  </button>
  <div>
    HTML Source Editor
   
  </div>

</div>

        <textarea
          v-model="content"
          @input="onContentChange"
          class="w-full h-[500px] p-4 font-mono text-sm border-0 resize-none focus:outline-none"
          placeholder="Enter HTML content..."
        ></textarea>
      </div>
    </div>

    <!-- Table Modal -->
    <div
      v-if="showTableModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
      <div class="bg-white rounded-lg p-6 w-96">
        <h3 class="text-lg font-semibold mb-4">Insert Table</h3>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium mb-1">Rows:</label>
            <input
              v-model.number="tableRows"
              type="number"
              min="1"
              max="20"
              class="w-full p-2 border rounded"
            />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Columns:</label>
            <input
              v-model.number="tableCols"
              type="number"
              min="1"
              max="10"
              class="w-full p-2 border rounded"
            />
          </div>
        </div>

        <div class="mb-4">
          <label class="flex items-center">
            <input v-model="tableHasHeader" type="checkbox" class="mr-2" />
            Include header row
          </label>
        </div>

        <div class="flex justify-end space-x-2">
          <button
            @click="showTableModal = false"
            class="px-4 py-2 bg-gray-300 text-gray-700 rounded"
          >
            Cancel
          </button>
          <button
            @click="createTable"
            class="px-4 py-2 bg-blue-600 text-white rounded"
          >
            Insert
          </button>
        </div>
      </div>
    </div>

    <!-- Add PDFGenerator component -->
    <!-- <PDFGenerator :content="content" filename="document.pdf" ref="pdfGenerator" /> -->

    <!-- Status -->
    <!-- <div class="flex justify-between items-center mt-3 text-sm text-gray-500">
      <div>
        <span>{{ wordCount }} words</span>
        <span class="ml-4">{{ characterCount }} characters</span>
        <span v-if="hasChanges" class="ml-4 text-orange-600">● Unsaved</span>
      </div>
    </div> -->
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from "vue";
import { Icon } from "@iconify/vue";
// import PDFGenerator from './PDFGenerator.vue';

//  Reactive State
let savedRange = null;

const currentFont = ref("");
const currentHeading = ref("");
const currentColor = ref("#000000");
const currentFontSize = ref('');



const props = defineProps({
  title: { type: String, default: "Rich Text Editor" },
  modelValue: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue", "save"]);

const richEditor = ref(null);
const editorMode = ref("rich");
const content = ref("");
const originalContent = ref("");
const showTableModal = ref(false);
const tableRows = ref(3);
const tableCols = ref(3);
const tableHasHeader = ref(true);
// const pdfGenerator = ref(null);

let lastEmittedContent = "";
let isUpdatingFromProps = false;

//  Computed Properties

const hasChanges = computed(() => content.value !== originalContent.value);
const wordCount = computed(
  () =>
    content.value
      .replace(/<[^>]*>/g, "")
      .trim()
      .split(/\s+/)
      .filter(Boolean).length
);
const characterCount = computed(
  () => content.value.replace(/<[^>]*>/g, "").length
);


const emitContent = () => {
  if (!richEditor.value) return;

  const currentContent = richEditor.value.innerHTML;
  const cleaned = cleanHTML(currentContent);

  if (cleaned !== lastEmittedContent) {
    lastEmittedContent = cleaned;
    content.value = cleaned;
    emit("update:modelValue", cleaned);
  }
};



const onRichEditorInput = () => emitContent();
const onKeyDown = (event) => {
  if (event.key === "Enter") setTimeout(() => emitContent(), 0);
};
const onRichEditorPaste = () => setTimeout(() => emitContent(), 10);
const onContentChange = () => emit("update:modelValue", content.value);


const formatText = (command, value = null) => {
  if (!richEditor.value) return;
  try {
    richEditor.value.focus();
    document.execCommand(command, false, value);
    setTimeout(() => emitContent(), 10);
  } catch (error) {
    console.warn("Format command failed:", command, error);
  }
};

const formatHeader = (tag) => {
  if (!richEditor.value) return;

  richEditor.value.focus();

  // Default paragraph when clearing heading
  const commandValue = tag ? `<${tag}>` : "<p>";

  try {
    document.execCommand("formatBlock", false, commandValue);
    emitContent();
  } catch (err) {
    console.warn("Heading formatting failed:", err);
  }
};


//switcher
const toggleCodeView = () => {
  if (editorMode.value === "rich") {
    // Move WYSIWYG content → textarea
    content.value = richEditor.value.innerHTML;
    editorMode.value = "html";
  } else {
    // Move textarea → WYSIWYG
    editorMode.value = "rich";
    nextTick(() => {
      richEditor.value.innerHTML = content.value;
      lastEmittedContent = content.value;
    });
  }
};
const switchToRich = () => {
  editorMode.value = "rich";

  nextTick(() => {
    if (richEditor.value) {
      richEditor.value.innerHTML = content.value;
      lastEmittedContent = content.value;
      richEditor.value.focus();
    }
  });
};



//hr
const insertHR = () => {
  if (!richEditor.value) return;
  richEditor.value.focus();

  document.execCommand("insertHorizontalRule", false, null);
  emitContent();
};
//quote
const insertBlockquote = () => {
  if (!richEditor.value) return;

  richEditor.value.focus();

  try {
    document.execCommand("formatBlock", false, "<blockquote>");
    emitContent();
  } catch (err) {
    console.warn("Blockquote failed:", err);
  }
};

//indent outdent
const increaseIndent = () => {
  richEditor.value.focus();
  document.execCommand("indent");
  emitContent();
};

const decreaseIndent = () => {
  richEditor.value.focus();
  document.execCommand("outdent");
  emitContent();
};

// Highlight / Marker
const applyHighlight = () => {
  if (!richEditor.value) return;

  richEditor.value.focus();

  const selection = window.getSelection();
  if (!selection.rangeCount) return;

  const range = selection.getRangeAt(0);
  const selectedContent = range.cloneContents();

  const span = document.createElement("span");
  span.style.backgroundColor = "yellow";
  span.appendChild(selectedContent);

  range.deleteContents();
  range.insertNode(span);

  // move cursor after highlighted selection
  const newRange = document.createRange();
  newRange.setStartAfter(span);
  newRange.collapse(true);
  selection.removeAllRanges();
  selection.addRange(newRange);

  emitContent();
};

// undo redo
const undoAction = () => {
  richEditor.value?.focus();
  document.execCommand("undo");
  emitContent();
};

const redoAction = () => {
  richEditor.value?.focus();
  document.execCommand("redo");
  emitContent();
};


//  Table Insertion

const insertTable = () => (showTableModal.value = true);

const createTable = () => {
  let tableHtml = `<table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse: collapse; margin: 1em 0;">\n`;
  if (tableHasHeader.value) {
    tableHtml += "  <thead>\n    <tr>\n";
    for (let j = 0; j < tableCols.value; j++) {
      tableHtml += `      <th style="border: 1px solid #ccc; padding: 8px; background-color: #f5f5f5;">Header ${
        j + 1
      }</th>\n`;
    }
    tableHtml += "    </tr>\n  </thead>\n";
  }
  tableHtml += "  <tbody>\n";
  const startRow = tableHasHeader.value ? 1 : 0;
  for (let i = startRow; i < tableRows.value; i++) {
    tableHtml += "    <tr>\n";
    for (let j = 0; j < tableCols.value; j++) {
      tableHtml += `      <td style="border: 1px solid #ccc; padding: 8px;">Cell ${
        i + 1
      }-${j + 1}</td>\n`;
    }
    tableHtml += "    </tr>\n";
  }
  tableHtml += "  </tbody>\n</table>\n";

  if (editorMode.value === "rich" && richEditor.value) {
    try {
      richEditor.value.focus();
      const selection = window.getSelection();
      if (selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        const tableElement = document.createElement("div");
        tableElement.innerHTML = tableHtml;
        range.insertNode(tableElement.firstChild);
      } else {
        richEditor.value.innerHTML += tableHtml;
      }
      setTimeout(() => emitContent(), 10);
    } catch (error) {
      console.warn("Insert table error:", error);
      content.value += tableHtml;
      emit("update:modelValue", content.value);
    }
  } else {
    content.value += tableHtml;
    emit("update:modelValue", content.value);
  }
  showTableModal.value = false;
};


//  PDF Placeholder

const triggerPDFGeneration = () => {
  if (pdfGenerator.value) {
    pdfGenerator.value.generatePDF();
  }
};

// link unlink
const createLink = () => {
  if (!richEditor.value) return;

  richEditor.value.focus();

  const url = prompt("Enter URL:", "https://");

  if (url && url.trim() !== "") {
    document.execCommand("createLink", false, url.trim());

    // Force link color to blue
    nextTick(() => {
      const selection = window.getSelection();
      if (!selection.rangeCount) return;

      const anchor = selection.anchorNode.parentElement.closest("a");
      if (anchor) {
        anchor.style.color = "#0066cc"; 
        // anchor.style.textDecoration = "underline";
      }
    });

    emitContent();
  }
};

//indent outdent
const customIndent = () => {
  const selection = window.getSelection();
  if (!selection.rangeCount) return;
  
  let block = selection.anchorNode.parentElement.closest("p, div, li, h1, h2, h3, h4");
  if (!block) return;

  let current = parseInt(block.style.marginLeft || 0);
  block.style.marginLeft = (current + 20) + "px";

  emitContent();
};

const customOutdent = () => {
  const selection = window.getSelection();
  if (!selection.rangeCount) return;

  let block = selection.anchorNode.parentElement.closest("p, div, li, h1, h2, h3, h4");
  if (!block) return;

  let current = parseInt(block.style.marginLeft || 0);
  block.style.marginLeft = Math.max(0, current - 20) + "px";

  emitContent();
};



//  Font Family

const formatFont = (fontName) => {
  if (!richEditor.value) return;
  try {
    richEditor.value.focus();
    const selection = window.getSelection();
    if (!selection.rangeCount) return;

    const range = selection.getRangeAt(0);
    const selectedContent = range.cloneContents();

    const wrapper = document.createElement("span");
    wrapper.style.fontFamily = fontName;
    wrapper.appendChild(selectedContent);

    range.deleteContents();
    range.insertNode(wrapper);

    const newRange = document.createRange();
    newRange.setStartAfter(wrapper);
    newRange.collapse(true);
    selection.removeAllRanges();
    selection.addRange(newRange);

    emitContent();
  } catch (error) {
    console.warn("Font change failed safely:", error);
  }
};


//  Font Size Formatting

const formatFontSize = (size) => {
  if (!richEditor.value) return;
  try {
    richEditor.value.focus();
    const selection = window.getSelection();
    if (!selection.rangeCount) return;

    const range = selection.getRangeAt(0);
    const selectedContent = range.cloneContents();

    const span = document.createElement("span");
    span.style.fontSize = size;
    span.appendChild(selectedContent);

    range.deleteContents();
    range.insertNode(span);

    // Recreate selection at end of inserted span
    const newRange = document.createRange();
    newRange.setStartAfter(span);
    newRange.collapse(true);
    selection.removeAllRanges();
    selection.addRange(newRange);

    currentFontSize.value = size;

    emitContent();
  } catch (error) {
    console.warn("Font size change failed:", error);
  }
};

const handleSelectionChange = () => {
  updateCurrentFont();
  updateCurrentHeading();
  updateCurrentColor();
  updateCurrentFontSize();
};

const updateCurrentFontSize = () => {
  try {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    const node = selection.anchorNode;
    if (!node) return;
    const element = node.nodeType === 1 ? node : node.parentElement;
    if (!element) return;
    const computedSize = window.getComputedStyle(element).fontSize;
    if (computedSize) currentFontSize.value = computedSize;
  } catch (error) {
    console.warn('Font size detection failed:', error);
  }
};
const normalizeFontSize = (size) => {
  const num = parseFloat(size);
  return isNaN(num) ? size : `${num}px`;
};



//  Text Color Formatting

//  Save current selection before color picker opens
const saveSelection = () => {
  const selection = window.getSelection();
  if (selection && selection.rangeCount > 0) {
    savedRange = selection.getRangeAt(0);
  }
};

//  Apply color to previously saved selection
const applyTextColor = (color) => {
  if (!richEditor.value) return;
  try {
    richEditor.value.focus();

    const selection = window.getSelection();

    // Restore previous range if user clicked color input
    if (savedRange) {
      selection.removeAllRanges();
      selection.addRange(savedRange);
      savedRange = null;
    }

    if (!selection.rangeCount) return;
    const range = selection.getRangeAt(0);
    const selectedContent = range.cloneContents();

    const span = document.createElement("span");
    span.style.color = color;
    span.appendChild(selectedContent);

    range.deleteContents();
    range.insertNode(span);

    const newRange = document.createRange();
    newRange.setStartAfter(span);
    newRange.collapse(true);
    selection.removeAllRanges();
    selection.addRange(newRange);

    currentColor.value = color;
    emitContent();
  } catch (error) {
    console.warn("Color change failed:", error);
  }
};

const updateCurrentColor = () => {
  try {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    const node = selection.anchorNode;
    if (!node) return;
    const element = node.nodeType === 1 ? node : node.parentElement;
    if (!element) return;
    const computedColor = window.getComputedStyle(element).color;
    if (computedColor) currentColor.value = rgbToHex(computedColor);
  } catch (error) {
    console.warn("Color detection failed:", error);
  }
};

//  convert rgb() → hex
const rgbToHex = (rgb) => {
  const match = rgb.match(/\d+/g);
  if (!match) return rgb;
  const [r, g, b] = match.map(Number);
  return (
    "#" +
    [r, g, b]
      .map((x) => {
        const hex = x.toString(16);
        return hex.length === 1 ? "0" + hex : hex;
      })
      .join("")
  );
};


//  Font + Heading Detection

const normalizeFontName = (font) => {
  if (font.includes("Noto Sans"))
    return "'Noto Sans Devanagari', Arial, sans-serif";
  if (font.includes("Times New Roman")) return "'Times New Roman', serif";
  if (font.includes("Courier New")) return "'Courier New', monospace";
  if (font.includes("Georgia")) return "'Georgia', serif";
  if (font.includes("Calibri")) return "'Calibri', sans-serif";
  if (font.includes("Arial")) return "Arial, sans-serif";
  return font;
};

const updateCurrentFont = () => {
  try {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    const node = selection.anchorNode;
    if (!node) return;
    const element = node.nodeType === 1 ? node : node.parentElement;
    if (!element) return;
    const computedFont = window.getComputedStyle(element).fontFamily;
    if (computedFont) {
      currentFont.value = normalizeFontName(computedFont);
    }
  } catch (error) {
    console.warn("Font detection failed:", error);
  }
};

const updateCurrentHeading = () => {
  try {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    const node = selection.anchorNode;
    if (!node) return;
    const element = node.nodeType === 1 ? node : node.parentElement;
    if (!element) return;
    const heading = element.closest("h1,h2,h3,h4");
    currentHeading.value = heading ? heading.tagName.toLowerCase() : "";
  } catch (error) {
    console.warn("Heading detection failed:", error);
  }
};



onMounted(() => {
  nextTick(() => initializeEditor());
  document.addEventListener("selectionchange", handleSelectionChange);

  // Initial sync once editor is ready
  nextTick(() => {
    updateCurrentFont();
    updateCurrentHeading();
  });
});

onUnmounted(() => {
  document.removeEventListener("selectionchange", handleSelectionChange);
});

const initializeEditor = () => {
  if (richEditor.value && props.modelValue) {
    isUpdatingFromProps = true;
    richEditor.value.innerHTML = props.modelValue;
    content.value = props.modelValue;
    originalContent.value = props.modelValue;
    lastEmittedContent = props.modelValue;
    isUpdatingFromProps = false;
  }
};

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue !== lastEmittedContent && !isUpdatingFromProps) {
      if (richEditor.value) {
        isUpdatingFromProps = true;
        richEditor.value.innerHTML = newValue;
        content.value = newValue;
        lastEmittedContent = newValue;
        isUpdatingFromProps = false;
      }
    }
  }
);

watch(editorMode, async (newMode) => {
  if (newMode === "rich" && richEditor.value) {
    await nextTick();
    isUpdatingFromProps = true;
    richEditor.value.innerHTML = content.value;
    lastEmittedContent = content.value;
    isUpdatingFromProps = false;
    richEditor.value.focus();
  } else if (newMode === "html" && richEditor.value) {
    content.value = richEditor.value.innerHTML;
  }
});
//clear all on delete
// const cleaned = cleanHTML(currentContent);
const cleanHTML = (html) => {
  if (!html) return "";

  // Remove leading/trailing whitespace
  let cleaned = html.trim();

  // Remove empty tags like <div></div>, <p></p>, <span></span>
  cleaned = cleaned.replace(/<(\w+)(\s[^>]*)?>\s*<\/\1>/g, "");

  // Remove <br> completely
  cleaned = cleaned.replace(/<br\s*\/?>/g, "").trim();

  // If only wrapper DIVs remain → treat as empty
  const temp = document.createElement("div");
  temp.innerHTML = cleaned;

  // If all text content is empty:
  if (temp.innerText.trim() === "") {
    return "";
  }

  return cleaned;
};


</script>

<style scoped>
.rich-editor-content {
  font-family: "Noto Sans Devanagari", Arial, sans-serif;
  line-height: 1.6;
  color: #333;
  background: #fff;
}

.rich-editor-content:focus {
  outline: none;
  box-shadow: inset 0 0 0 2px #3b82f6;
}


.toolbar-btn {
  @apply px-2 py-1 text-sm border border-gray-300 rounded hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500;
  min-width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: white;
  transition: background-color 0.2s;
}

.toolbar-btn:hover {
  background-color: #f3f4f6;
}

.toolbar-btn:active {
  background-color: #e5e7eb;
}


/* .rich-editor-content * {
  color: inherit;
  font-size: inherit;
  text-align: inherit;
  line-height: inherit;
} */
.rich-editor-content
  *:not(h1):not(h2):not(h3):not(h4):not(th):not(td):not(ul):not(ol):not(li) {
  color: inherit;
  font-size: inherit;
  text-align: inherit;
  line-height: inherit;
}


.rich-editor-content b,
.rich-editor-content strong {
  font-weight: 700;
}

.rich-editor-content u {
  text-decoration: underline;
}

.rich-editor-content s,
.rich-editor-content del,
.rich-editor-content strike {
  text-decoration: line-through;
}
</style>
