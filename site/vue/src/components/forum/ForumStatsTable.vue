<script setup lang="ts">
import { computed, ref } from 'vue';
import SortableTableHeader from '../table_sorting/SortableTableHeader.vue';
import { colDataTypes, resolveSortToggle, type SortDirection } from '../../../../ts/sort-table-by-column';

interface ForumPost {
    id: number;
    timestamp: string;
    threadId: number;
    threadTitle: string;
    content: string;
}

interface ForumUserStats {
    userId: string;
    familyName: string;
    givenName: string;
    postCount: number;
    totalThreads: number;
    numDeleted: number;
    totalUpducks: number;
    posts: ForumPost[];
}

const props = defineProps<{
    users: ForumUserStats[];
}>();

const emit = defineEmits<{
    'navigate-to-thread': [payload: { threadId: number }];
}>();

type SortKey = 'user' | 'total_posts' | 'total_threads' | 'total_deleted' | 'total_upducks';

const sortKey = ref<SortKey | null>(null);
const sortDirection = ref<SortDirection>('ASC');
const expandedUserIds = ref<Set<string>>(new Set());

// sorting is handled internally and expanded rows are collapsed on sort.
const sortedUsers = computed(() => {
    if (sortKey.value === null) {
        return props.users;
    }
    const dir = sortDirection.value === 'ASC' ? 1 : -1;
    return [...props.users].sort((a, b) => {
        let cmp = 0;
        switch (sortKey.value) {
            case 'user':
                cmp = `${a.familyName}, ${a.givenName}`.localeCompare(`${b.familyName}, ${b.givenName}`);
                break;
            case 'total_posts':
                cmp = a.postCount - b.postCount;
                break;
            case 'total_threads':
                cmp = a.totalThreads - b.totalThreads;
                break;
            case 'total_deleted':
                cmp = a.numDeleted - b.numDeleted;
                break;
            case 'total_upducks':
                cmp = a.totalUpducks - b.totalUpducks;
                break;
        }
        return cmp * dir;
    });
});

function handleSort(payload: { sortKey: string }) {
    const next = resolveSortToggle(sortKey.value, payload.sortKey, sortDirection.value);
    sortKey.value = next.key as SortKey;
    sortDirection.value = next.direction;
    // Collapse all expanded rows when a sortable header is clicked
    expandedUserIds.value = new Set();
}

function isExpanded(userId: string): boolean {
    return expandedUserIds.value.has(userId);
}

function toggleExpand(userId: string) {
    const next = new Set(expandedUserIds.value);
    if (next.has(userId)) {
        next.delete(userId);
    }
    else {
        next.add(userId);
    }
    expandedUserIds.value = next;
}

function navigateToThread(threadId: number) {
    emit('navigate-to-thread', { threadId });
}
</script>

<template>
  <table class="table table-striped table-bordered persist-area">
    <colgroup>
      <col style="width:15%">
      <col>
      <col style="width:15%">
      <col style="width:15%">
      <col style="width:15%">
      <col style="width:25%">
    </colgroup>
    <thead>
      <tr>
        <th scope="col">
          <SortableTableHeader
            table-id="forum-stats-table"
            title="User"
            sort-key="user"
            :col-data-type="colDataTypes.String"
            :using-row-groups="true"
            :active="sortKey === 'user'"
            :sort-direction="sortDirection"
            @sort-table-column-click="handleSort"
          />
        </th>
        <th scope="col">
          <SortableTableHeader
            table-id="forum-stats-table"
            title="Total Posts (not deleted)"
            sort-key="total_posts"
            :col-data-type="colDataTypes.Number"
            :using-row-groups="true"
            :active="sortKey === 'total_posts'"
            :sort-direction="sortDirection"
            @sort-table-column-click="handleSort"
          />
        </th>
        <th scope="col">
          <SortableTableHeader
            table-id="forum-stats-table"
            title="Total Threads"
            sort-key="total_threads"
            :col-data-type="colDataTypes.Number"
            :using-row-groups="true"
            :active="sortKey === 'total_threads'"
            :sort-direction="sortDirection"
            @sort-table-column-click="handleSort"
          />
        </th>
        <th scope="col">
          <SortableTableHeader
            table-id="forum-stats-table"
            title="Total Deleted Posts"
            sort-key="total_deleted"
            :col-data-type="colDataTypes.Number"
            :using-row-groups="true"
            :active="sortKey === 'total_deleted'"
            :sort-direction="sortDirection"
            @sort-table-column-click="handleSort"
          />
        </th>
        <th scope="col">
          <SortableTableHeader
            table-id="forum-stats-table"
            title="Total Upducks"
            sort-key="total_upducks"
            :col-data-type="colDataTypes.Number"
            :using-row-groups="true"
            :active="sortKey === 'total_upducks'"
            :sort-direction="sortDirection"
            @sort-table-column-click="handleSort"
          />
        </th>
        <th scope="col">
          Show Posts
        </th>
      </tr>
    </thead>
    <tbody>
      <template
        v-for="user in sortedUsers"
        :key="user.userId"
      >
        <tr
          class="user_stat"
          data-testid="user-stat"
        >
          <td>{{ user.familyName }}, {{ user.givenName }}</td>
          <td>{{ user.postCount }}</td>
          <td>{{ user.totalThreads }}</td>
          <td>{{ user.numDeleted }}</td>
          <td
            class="upduck_stat"
            data-testid="upduck-stat"
          >
            {{ user.totalUpducks }}
          </td>
          <td>
            <button
              v-if="user.posts.length > 0"
              type="button"
              class="btn btn-default"
              data-testid="expand-button"
              :title="isExpanded(user.userId) ? 'Collapse posts' : 'Expand posts'"
              :aria-expanded="isExpanded(user.userId)"
              @click="toggleExpand(user.userId)"
            >
              {{ isExpanded(user.userId) ? 'Collapse' : 'Expand' }}
            </button>
            <span v-else>No Posts</span>
          </td>
        </tr>
        <template v-if="isExpanded(user.userId)">
          <tr
            v-for="post in user.posts"
            :key="post.id"
            class="forum-stats-detail-row"
            data-testid="post-detail-row"
          >
            <td />
            <td>{{ post.timestamp }}</td>
            <td
              style="cursor:pointer;"
              data-testid="post-thread-cell"
              @click="navigateToThread(post.threadId)"
            >
              <pre
                class="pre-forum"
                style="white-space: pre-wrap;"
              >{{ post.threadTitle }}</pre>
            </td>
            <td
              colspan="2"
              style="cursor:pointer; text-align:left;"
              data-testid="post-content-cell"
              @click="navigateToThread(post.threadId)"
            >
              <pre
                class="pre-forum"
                style="white-space: pre-wrap;"
              >{{ post.content }}</pre>
            </td>
            <td />
          </tr>
        </template>
      </template>
    </tbody>
  </table>
</template>
