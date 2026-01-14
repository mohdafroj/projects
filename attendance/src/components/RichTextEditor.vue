<template>
  <div>
    <div
      v-if="label"
      class="input-label mb-[6px] text-sm font-semibold text-gray-500 dark:text-slate-300"
    >
      {{ label }}
    </div>
    <div
      class="border rounded shadow-sm bg-white dark:bg-gray-800 transition-colors"
    >
      <!-- Toolbar -->
      <div
        class="border-b bg-gray-50 dark:bg-gray-900 p-2 flex flex-wrap items-center gap-1"
      >
        <!-- Mode Toggle -->
        <div class="flex items-center gap-1">
          <button
            @click="switchMode('rich')"
            :class="modeButtonClass('rich')"
            title="Rich Editor Mode"
            aria-label="Rich Editor Mode"
          >
            💻
          </button>
          <button
            @click="switchMode('html')"
            :class="modeButtonClass('html')"
            title="HTML Source Mode"
            aria-label="HTML Source Mode"
          >
            📝
          </button>
        </div>

        <div class="w-px h-6 bg-gray-300 dark:bg-gray-700 mx-2"></div>

        <!-- Rich Toolbar -->
        <template v-if="editorMode !== 'html'">
          <div class="flex items-center flex-wrap gap-1">
            <template v-for="btn in toolbarButtons" :key="btn.cmd">
              <button
                @click.prevent="formatText(btn.cmd, btn.value)"
                class="toolbar-btn"
                :title="btn.title"
                :aria-label="btn.title"
                tabindex="0"
              >
                <Icon :icon="btn.icon" width="20" height="20" />
              </button>
            </template>
          </div>

          <div class="w-px h-6 bg-gray-300 dark:bg-gray-700 mx-2"></div>

          <!-- Headers -->
          <select
            @change="formatHeader($event.target.value)"
            class="text-sm border rounded px-2 py-1 focus:outline-none focus:ring focus:border-blue-400 bg-white dark:bg-gray-700 dark:text-gray-100 dark:border-gray-600"
          >
            <option value="">Normal</option>
            <option value="h1">Heading 1</option>
            <option value="h2">Heading 2</option>
            <option value="h3">Heading 3</option>
            <option value="h4">Heading 4</option>
          </select>

          <div class="w-px h-6 bg-gray-300 dark:bg-gray-700 mx-2"></div>

          <!-- Colors -->
          <input
            type="color"
            @input="formatText('foreColor', $event.target.value)"
            title="Text Color"
            class="w-8 h-8 p-0 border rounded cursor-pointer bg-white dark:bg-gray-700 dark:border-gray-600"
          />
          <input
            type="color"
            @input="formatText('hiliteColor', $event.target.value)"
            title="Background Color"
            class="w-8 h-8 p-0 border rounded cursor-pointer bg-white dark:bg-gray-700 dark:border-gray-600"
          />

          <div class="w-px h-6 bg-gray-300 dark:bg-gray-700 mx-2"></div>

          <!-- Links -->
          <button
            @click="insertLink"
            class="toolbar-btn"
            title="Insert Link"
            aria-label="Insert Link"
          >
            <Icon icon="mdi:link-variant" width="20" height="20" />
          </button>
          <button
            @click="formatText('unlink')"
            class="toolbar-btn"
            title="Remove Link"
            aria-label="Remove Link"
          >
            <Icon icon="mdi:link-off" width="20" height="20" />
          </button>

          <div class="w-px h-6 bg-gray-300 dark:bg-gray-700 mx-2"></div>

          <!-- Misc -->
          <button
            @click="formatText('superscript')"
            class="toolbar-btn"
            title="Superscript"
            aria-label="Superscript"
          >
            <Icon icon="mdi:format-superscript" width="20" height="20" />
          </button>
          <button
            @click="formatText('subscript')"
            class="toolbar-btn"
            title="Subscript"
            aria-label="Subscript"
          >
            <Icon icon="mdi:format-subscript" width="20" height="20" />
          </button>
          <button
            @click="formatText('insertHorizontalRule')"
            class="toolbar-btn"
            title="Horizontal Line"
            aria-label="Horizontal Line"
          >
            <Icon icon="mdi:minus" width="20" height="20" />
          </button>
          <button
            @click="formatText('formatBlock', 'blockquote')"
            class="toolbar-btn"
            title="Blockquote"
            aria-label="Blockquote"
          >
            <Icon icon="mdi:format-quote-close" width="20" height="20" />
          </button>

          <div class="w-px h-6 bg-gray-300 dark:bg-gray-700 mx-2"></div>

          <!-- Indent/Outdent -->
          <button
            @click="formatText('indent')"
            class="toolbar-btn"
            title="Indent"
            aria-label="Indent"
          >
            <Icon icon="mdi:format-indent-increase" width="20" height="20" />
          </button>
          <button
            @click="formatText('outdent')"
            class="toolbar-btn"
            title="Outdent"
            aria-label="Outdent"
          >
            <Icon icon="mdi:format-indent-decrease" width="20" height="20" />
          </button>

          <div class="w-px h-6 bg-gray-300 dark:bg-gray-700 mx-2"></div>

          <!-- Undo / Redo -->
          <button
            @click="formatText('undo')"
            class="toolbar-btn"
            title="Undo"
            aria-label="Undo"
          >
            <Icon icon="mdi:undo" width="20" height="20" />
          </button>
          <button
            @click="formatText('redo')"
            class="toolbar-btn"
            title="Redo"
            aria-label="Redo"
          >
            <Icon icon="mdi:redo" width="20" height="20" />
          </button>

          <div class="w-px h-6 bg-gray-300 dark:bg-gray-700 mx-2"></div>

          <!-- Table -->
          <button
            @click="showTableModal = true"
            class="toolbar-btn bg-green-100 dark:bg-green-900"
            title="Insert Table"
            aria-label="Insert Table"
          >
            <Icon icon="mdi:table" width="20" height="20" />
          </button>

          <!-- Clear Formatting -->
          <button
            @click="formatText('removeFormat')"
            class="toolbar-btn"
            title="Clear Format"
            aria-label="Clear Format"
          >
            <Icon icon="mdi:format-clear" width="20" height="20" />
          </button>
          <div class="w-px h-6 bg-gray-300 dark:bg-gray-700 mx-2"></div>
        </template>
        <template v-for="action in visibleCustomActions" :key="action.name">
          <button
            class="toolbar-btn"
            @click.prevent="handleCustomAction(action)"
            :title="action.title"
            :aria-label="action.title"
            tabindex="0"
          >
            <Icon :icon="action.icon" width="20" height="20" />
          </button>
        </template>
      </div>

      <!-- Rich Editor -->
      <div
        v-if="editorMode === 'rich'"
        ref="richEditor"
        class="p-3 min-h-[200px] outline-none bg-white dark:bg-gray-800 dark:text-gray-100 transition-colors"
        contenteditable="true"
        dir="ltr"
        :style="props.style"
        style="direction: ltr; unicode-bidi: plaintext; text-align: left"
        @input="updateContentFromRich"
        @keydown.tab.prevent="insertTab"
        @keyup="handleKeyUp"
      ></div>

      <!-- HTML Source -->
      <textarea
        v-else
        v-model="localContent"
        @input="emitContent"
        class="p-3 w-full min-h-[200px] font-mono text-sm border-0 outline-none bg-white dark:bg-gray-800 dark:text-gray-100 transition-colors"
      ></textarea>
      <!-- Popup dropdown -->
      <div
        v-if="showList"
        class="absolute bg-white shadow-md border rounded text-sm w-48"
        :style="{ top: position.y + 'px', left: position.x + 'px' }"
      >
        <div
          v-for="opt in listItems"
          :key="opt.value"
          @mousedown.prevent="insertOption(opt)"
          class="p-1.5 hover:bg-blue-100 cursor-pointer"
        >
          {{ opt.label }}
        </div>
      </div>
      <!-- Table Insert Modal -->
      <div
        v-if="showTableModal"
        class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50"
      >
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 w-80">
          <h3
            class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-100"
          >
            Insert Table
          </h3>
          <div class="flex gap-4 mb-4">
            <input
              type="number"
              v-model.number="tableRows"
              placeholder="Rows"
              min="1"
              class="border px-2 py-1 rounded w-20 bg-white dark:bg-gray-700 dark:text-gray-100 dark:border-gray-600"
            />
            <input
              type="number"
              v-model.number="tableCols"
              placeholder="Cols"
              min="1"
              class="border px-2 py-1 rounded w-20 bg-white dark:bg-gray-700 dark:text-gray-100 dark:border-gray-600"
            />
          </div>
          <div class="flex justify-end gap-2">
            <button
              @click="showTableModal = false"
              class="px-3 py-1 border rounded dark:text-gray-100 dark:border-gray-600"
            >
              Cancel
            </button>
            <button
              @click="insertTable"
              class="px-3 py-1 bg-blue-500 text-white rounded"
            >
              Insert
            </button>
          </div>
        </div>
      </div>
    </div>
    <div v-if="error" class="mt-2 text-sm text-red-500">
      {{ error }}
    </div>
  </div>
</template>

<script setup>
import { ref, watch, nextTick, computed, onMounted, onUnmounted } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  label: { type: String, default: '' },
  modelValue: { type: String, default: '' },
  error: { type: String, default: '' },
  customAction: { type: Array, default: [] },
  mentionPlugins: {
    type: Array,
    default: () => [],
  },
  style: { type: String, default: '' },
});
const selectionChanged = ref(0);

onMounted(() => {
  document.addEventListener('selectionchange', () => {
    selectionChanged.value++;
  });
});
onUnmounted(() => {
  document.removeEventListener('selectionchange', () => {
    selectionChanged.value++;
  });
});
const visibleCustomActions = computed(() => {
  const selection = window.getSelection();
  const hasSelection = selection && selection.toString().length > 0;
  selectionChanged.value; // trigger recompute
  return props.customAction.filter(
    a => a.showWhen === 'always' || (a.showWhen === 'selection' && hasSelection)
  );
});

function handleCustomAction(action) {
  const selection = window.getSelection();
  const range = selection.rangeCount ? selection.getRangeAt(0) : null;

  // helper: safely insert HTML
  const insertHTML = html => {
    if (!html) return;
    document.execCommand('insertHTML', false, html);
    updateContentFromRich();
  };

  // helper: execCommand wrapper
  const execCommand = (cmd, val) => {
    document.execCommand(cmd, false, val);
    updateContentFromRich();
  };

  // call user-defined handler
  if (typeof action.onAction === 'function') {
    action.onAction(selection, insertHTML, execCommand);
  }
}
const emit = defineEmits(['update:modelValue']);

const editorMode = ref('rich');
const richEditor = ref(null);
const localContent = ref(props.modelValue);
const showTableModal = ref(false);
const tableRows = ref('');
const tableCols = ref('');

const toolbarButtons = [
  { cmd: 'bold', icon: 'mdi:format-bold', title: 'Bold' },
  { cmd: 'italic', icon: 'mdi:format-italic', title: 'Italic' },
  { cmd: 'underline', icon: 'mdi:format-underline', title: 'Underline' },
  // { cmd: 'insertUnorderedList', icon: 'mdi:format-list-bulleted', title: 'Bulleted List' },
  // { cmd: 'insertOrderedList', icon: 'mdi:format-list-numbered', title: 'Numbered List' },
  { cmd: 'justifyLeft', icon: 'mdi:format-align-left', title: 'Align Left' },
  {
    cmd: 'justifyCenter',
    icon: 'mdi:format-align-center',
    title: 'Align Center',
  },
  { cmd: 'justifyRight', icon: 'mdi:format-align-right', title: 'Align Right' },
];

function switchMode(mode) {
  editorMode.value = mode;
  // When switching to rich, sync content
  if (mode === 'rich' && richEditor.value) {
    nextTick(() => {
      richEditor.value.innerHTML = localContent.value || '';
      richEditor.value.focus();
    });
  }
}

function modeButtonClass(mode) {
  return [
    'px-2 py-1 rounded border focus:outline-none focus:ring-2 focus:ring-blue-400',
    editorMode.value === mode
      ? 'bg-blue-500 text-white border-blue-500'
      : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-100 border-gray-300 dark:border-gray-600 hover:bg-blue-100 dark:hover:bg-gray-600',
  ];
}

function emitContent() {
  emit('update:modelValue', localContent.value);
  // emit('update:modelValue', editor.value.innerHTML)
}

function saveSelection() {
  const sel = window.getSelection();
  return sel.rangeCount > 0 ? sel.getRangeAt(0) : null;
}

function restoreSelection(range) {
  if (range) {
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
  }
}

function updateContentFromRich() {
  const range = saveSelection();
  localContent.value = richEditor.value.innerHTML;

  emitContent();
  nextTick(() => restoreSelection(range));
}

function formatText(cmd, value = null) {
  document.execCommand(cmd, false, value);
  updateContentFromRich();
}

function formatHeader(tag) {
  document.execCommand('formatBlock', false, tag || 'p');
  updateContentFromRich();
}

function insertLink() {
  const url = prompt('Enter URL:');
  if (url) {
    document.execCommand('createLink', false, url);
    updateContentFromRich();
  }
}

function insertTable() {
  if (tableRows.value > 0 && tableCols.value > 0) {
    let table = '<table style="border-collapse: collapse; width: 100%;">';
    for (let r = 0; r < tableRows.value; r++) {
      table += '<tr>';
      for (let c = 0; c < tableCols.value; c++) {
        table +=
          '<td style="border: 1px solid #ccc; padding: 4px;">&nbsp;</td>';
      }
      table += '</tr>';
    }
    table += '</table>';
    document.execCommand('insertHTML', false, table);
    updateContentFromRich();
  }
  showTableModal.value = false;
}

function insertTab(e) {
  document.execCommand('insertText', false, '\t');
  updateContentFromRich();
}

watch(editorMode, async newMode => {
  if (newMode === 'rich') {
    await nextTick();
    if (richEditor.value) {
      richEditor.value.innerHTML = localContent.value || '';
      richEditor.value.focus();
    }
  }
});

watch(
  () => props.modelValue,
  newVal => {
    if (newVal !== localContent.value) {
      localContent.value = newVal;
      if (editorMode.value === 'rich' && richEditor.value) {
        richEditor.value.innerHTML = newVal;
      }
    }
  }
);

nextTick(() => {
  if (richEditor.value) {
    richEditor.value.innerHTML = localContent.value;
  }
});
const showList = ref(false);
const listItems = ref([]);
const position = ref({ x: 0, y: 0 });
const activePlugin = ref(null);
const query = ref('');

async function handleKeyUp(e) {
  const sel = window.getSelection();
  const range = sel.rangeCount ? sel.getRangeAt(0) : null;
  if (!range) return;

  const text = range.startContainer.textContent || '';
  let matchedPlugin = null;

  // search among props.mentionPlugins
  for (const plugin of props.mentionPlugins) {
    if (text.includes(plugin.trigger)) {
      matchedPlugin = plugin;
      break;
    }
  }

  if (!matchedPlugin) {
    showList.value = false;
    return;
  }

  const atIndex = text.lastIndexOf(matchedPlugin.trigger);
  const typed = text.slice(atIndex + 1);
  query.value = typed.trim();
  activePlugin.value = matchedPlugin;

  const items = await matchedPlugin.fetchOptions(query.value);
  listItems.value = items;
  showList.value = items.length > 0;

  if (showList.value) {
    nextTick(() => {
      const rect = range.getBoundingClientRect();
      position.value = {
        x: rect.left + window.scrollX,
        y: rect.bottom + window.scrollY,
      };
    });
  }
}

function insertOption(opt) {
  if (!activePlugin.value) return;

  const sel = window.getSelection();
  if (!sel.rangeCount) return;

  const range = sel.getRangeAt(0);
  const trigger = activePlugin.value.trigger;
  const container = range.startContainer;
  const text = container.textContent || '';
  const caretPos = range.startOffset;

  // Find where trigger started
  const atIndex = text.lastIndexOf(trigger, caretPos - 1);
  if (atIndex === -1) return;

  // --- Split around the mention text ---
  const beforeText = text.slice(0, atIndex);
  const mentionText = text.slice(atIndex, caretPos); // includes trigger
  const afterText = text.slice(caretPos);

  // Replace the mention text segment with our custom node
  // Step 1: collapse to trigger start
  const newRange = document.createRange();
  newRange.setStart(container, atIndex);
  newRange.setEnd(container, caretPos);
  newRange.deleteContents();

  // Step 2: insert the custom element
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = activePlugin.value.insertTemplate(opt);
  const mentionEl = tempDiv.firstChild;
  newRange.insertNode(mentionEl);

  // Step 3: add a space after mention if next char isn’t space or punctuation
  const nextChar = afterText.charAt(0);
  if (
    nextChar !== ' ' &&
    nextChar !== '' &&
    !['.', ',', ';'].includes(nextChar)
  ) {
    const spaceNode = document.createTextNode(' ');
    mentionEl.after(spaceNode);
  }

  // Step 4: place cursor after inserted mention
  const selRange = document.createRange();
  selRange.setStartAfter(mentionEl);
  selRange.collapse(true);
  sel.removeAllRanges();
  sel.addRange(selRange);

  showList.value = false;
  updateContentFromRich();
}
</script>

<style scoped>
.mention {
  background-color: #e0f2fe;
  color: #0369a1;
  border-radius: 4px;
  padding: 0 4px;
}
.tag {
  background-color: #fef3c7;
  color: #92400e;
  border-radius: 4px;
  padding: 0 4px;
}

.toolbar-btn {
  padding: 4px;
  border-radius: 4px;
  border: 1px solid transparent;
  transition:
    background-color 0.15s,
    border-color 0.15s;
  background: transparent;
  color: inherit;
  outline: none;
}
.toolbar-btn:focus {
  border-color: #3b82f6;
  background-color: #e0e7ff;
}
.toolbar-btn:hover {
  background-color: #f0f0f0;
}
.dark .toolbar-btn:hover {
  background-color: #374151;
}
</style>
