<template>
  <ul class="file-tree">
    <li v-for="item in tree" :key="item.name + (item.path || '')">
      <div class="file-tree-item">
        <span v-if="item.is_dir" @click="toggle(item)" class="folder-toggle">
          <span v-if="item._open">&#9660;</span>
          <span v-else>&#9654;</span>
        </span>
        <span
          :class="{'folder': item.is_dir, 'file': !item.is_dir, 'selected': isSelected(item)}"
          @click="select(item)"
        >
          {{ item.name }}
        </span>
        <button @click.stop="deleteItem(item)">Delete</button>
      </div>
      <FileTree
        v-if="item.is_dir && item._open && item.children && item.children.length"
        :tree="item.children"
        :selected-path="selectedPath"
        @select="$emit('select', $event)"
        @delete="$emit('delete', $event)"
      />
    </li>
  </ul>
</template>

<script>
export default {
  name: 'FileTree',
  props: {
    tree: { type: Array, required: true },
    selectedPath: { type: String, default: '' }
  },
  methods: {
    select(item) {
      this.$emit('select', item);
    },
    deleteItem(item) {
      this.$emit('delete', item.path || item.name, item.is_dir);
    },
    toggle(item) {
      this.$set(item, '_open', !item._open);
      if (item._open && !item.children) {
        // Optionally, fetch children from backend here if lazy loading
        this.$set(item, 'children', []);
      }
    },
    isSelected(item) {
      return (item.path || item.name) === this.selectedPath;
    }
  }
};
</script>

<style scoped>
.file-tree { list-style: none; padding-left: 1em; }
.file-tree-item { display: flex; align-items: center; }
.folder { font-weight: bold; cursor: pointer; }
.file { cursor: pointer; }
.selected { background: #e0e0e0; }
.folder-toggle { cursor: pointer; margin-right: 0.3em; }
button { margin-left: 0.5em; }
</style>
