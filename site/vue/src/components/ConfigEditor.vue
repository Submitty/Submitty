<template>
  <div class="config-editor">
    <div class="config-editor-toolbar">
      <button @click="refreshTree">Refresh</button>
      <button @click="addFolder">Add Folder</button>
      <button @click="addFile">Add File</button>
      <button @click="downloadConfig">Download</button>
    </div>
    <div class="config-editor-main">
      <FileTree :tree="fileTree" @select="selectFile" @delete="deleteFileOrFolder" />
      <div v-if="selectedFile" class="config-editor-panel">
        <codemirror v-model="fileContent" :options="editorOptions" />
        <div class="config-editor-actions">
          <button @click="saveFile" :disabled="!isEdited">Save</button>
          <button @click="cancelEdit">Cancel</button>
        </div>
      </div>
    </div>
    <div v-if="statusMessage" class="config-editor-status">{{ statusMessage }}</div>
  </div>
</template>

<script>
import FileTree from './FileTree.vue';
import { codemirror } from 'vue-codemirror';
export default {
  name: 'ConfigEditor',
  components: { FileTree, codemirror },
  props: {
    context: { type: String, required: true },
    apiUrl: { type: String, required: true },
  },
  data() {
    return {
      fileTree: [],
      selectedFile: null,
      fileContent: '',
      isEdited: false,
      statusMessage: '',
      editorOptions: {
        mode: 'application/json',
        lineNumbers: true,
        tabSize: 2,
        theme: 'default',
      },
    };
  },
  methods: {
    async refreshTree() {
      try {
        const res = await fetch(this.apiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            context: this.context,
            action: 'list',
            path: '',
          }),
          credentials: 'same-origin',
        });
        const data = await res.json();
        if (data.status === 'success') {
          this.fileTree = data.data.files;
          this.statusMessage = '';
        } else {
          this.handleError(data.message || 'Failed to load file tree');
        }
      } catch (e) {
        this.handleError('Failed to load file tree');
      }
    },
    async selectFile(file) {
      try {
        const res = await fetch(this.apiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            context: this.context,
            action: 'load',
            path: file.path || file.name,
          }),
          credentials: 'same-origin',
        });
        const data = await res.json();
        if (data.status === 'success') {
          this.selectedFile = file.path || file.name;
          this.fileContent = data.data.content;
          this.isEdited = false;
          this.statusMessage = '';
        } else {
          this.handleError(data.message || 'Failed to load file');
        }
      } catch (e) {
        this.handleError('Failed to load file');
      }
    },
    async saveFile() {
      try {
        const res = await fetch(this.apiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            context: this.context,
            action: 'save',
            path: this.selectedFile,
            content: this.fileContent,
          }),
          credentials: 'same-origin',
        });
        const data = await res.json();
        if (data.status === 'success') {
          this.isEdited = false;
          this.statusMessage = 'File saved.';
          this.refreshTree();
        } else {
          this.handleError(data.message || 'Failed to save file');
        }
      } catch (e) {
        this.handleError('Failed to save file');
      }
    },
    cancelEdit() {
      this.selectedFile = null;
      this.fileContent = '';
      this.isEdited = false;
      this.statusMessage = '';
    },
    async addFolder() {
      const folderName = prompt('Enter folder name:');
      if (!folderName) return;
      try {
        const res = await fetch(this.apiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            context: this.context,
            action: 'add_folder',
            path: folderName,
          }),
          credentials: 'same-origin',
        });
        const data = await res.json();
        if (data.status === 'success') {
          this.statusMessage = 'Folder created.';
          this.refreshTree();
        } else {
          this.handleError(data.message || 'Failed to create folder');
        }
      } catch (e) {
        this.handleError('Failed to create folder');
      }
    },
    async addFile() {
      const fileInput = document.createElement('input');
      fileInput.type = 'file';
      fileInput.onchange = async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('context', this.context);
        formData.append('action', 'add_file');
        formData.append('path', file.name);
        formData.append('file', file);
        try {
          const res = await fetch(this.apiUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
          });
          const data = await res.json();
          if (data.status === 'success') {
            this.statusMessage = 'File uploaded.';
            this.refreshTree();
          } else {
            this.handleError(data.message || 'Failed to upload file');
          }
        } catch (e) {
          this.handleError('Failed to upload file');
        }
      };
      fileInput.click();
    },
    async deleteFileOrFolder(path, isFolder) {
      if (!confirm(`Are you sure you want to delete this ${isFolder ? 'folder' : 'file'}?`)) return;
      try {
        const res = await fetch(this.apiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            context: this.context,
            action: 'delete',
            path,
          }),
          credentials: 'same-origin',
        });
        const data = await res.json();
        if (data.status === 'success') {
          this.statusMessage = 'Deleted.';
          this.refreshTree();
        } else {
          this.handleError(data.message || 'Failed to delete');
        }
      } catch (e) {
        this.handleError('Failed to delete');
      }
    },
    downloadConfig() {
      // For now, just download the current file (not zip). You can expand to zip as needed.
      if (!this.selectedFile) {
        this.handleError('No file selected to download.');
        return;
      }
      const blob = new Blob([this.fileContent], { type: 'text/plain' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = this.selectedFile.split('/').pop();
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    },
    handleError(msg) {
      this.statusMessage = msg;
    },
  },
};
</script>

<style scoped>
.config-editor { display: flex; flex-direction: column; }
.config-editor-toolbar { margin-bottom: 1em; }
.config-editor-main { display: flex; }
.config-editor-panel { flex: 1; margin-left: 1em; }
.config-editor-status { color: red; margin-top: 1em; }
</style>
