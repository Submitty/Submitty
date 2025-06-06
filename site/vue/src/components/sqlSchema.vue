<script setup lang="ts">
const { data } = defineProps<{
    data: {
        name: string;
        columns: {
            name: string;
            type: string;
        }[];
    }[];
}>();

function toggle(event: MouseEvent) {
    const element = (event.target as HTMLElement).nextElementSibling as HTMLElement;
    if (element) {
        element.hidden = element.hidden ? false : true;
    }
}
</script>

<template>
  <div class="database-schema">
    <button
      id="sql-database-schema"
      class="btn btn-primary"
      @click="toggle"
    >
      Database Schema Documentation
    </button>
    <div
      id="sql-database-schema-content"
      hidden
    >
      <p>Click on each table to see columns</p>
      <ul style="margin-left: 30px;">
        <li
          v-for="row in data"
          :key="row.name"
        >
          <a
            class="sql-database-table"
            @click="toggle"
          >{{ row.name }}</a>
          <div
            class="sql-database-columns"
            hidden
          >
            <ul style="margin-left: 15px">
              <li
                v-for="column in row.columns"
                :key="column.name"
              >
                {{ column.name }} - {{ column.type }}
              </li>
            </ul>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
