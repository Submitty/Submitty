<script setup lang="ts">
import { ref, computed } from 'vue';

interface Notification {
    id: number;
    component: string;
    metadata: string;
    content: string;
    seen: boolean;
    elapsed_time: number;
    created_at: string;
    notify_time: string,
    semester: string;
    course: string;
    notification_url: string;
}

const props = defineProps<{
    notifications: Notification[];
}>();

const showingMore = ref(false);
const visibleCount = computed(() => showingMore.value ? 10 : 5);

const visibleNotifications = computed(() =>
    props.notifications.slice(0, visibleCount.value)
);
</script>
<template>
    <div class="notification-body">
        <h1 class="notifications-header">Notifications</h1>
        <p class="no-recent" id="no-recent-notifications" v-if="notifications.length === 0">No recent notifications.</p>
        <div id="recent-notifications" v-else>
            <div class="notification" v-for="n in visibleNotifications" :key="n.id" :class="{ unseen: !n.seen }">
                    <i class="fas fa-comments notification-type" title="Forum"></i>
                <div class="notification-content">
                        <div class="notification-contents">
                            <div class="notification-content">
                                <a class="notification-link" href="{{ n.notification_url }}/{{ n.id }}?seen={{ n.seen ? 1 : 0 }}">
                                    {{ n.content }}
                                </a>
                            </div>
                        </div>
                    <div class="notification-time">
                        {{ n.course }} - {{ n.notify_time }}
                    </div>
                </div>
            </div>
            <a
                v-if="props.notifications.length > 5 && !showingMore"
                @click="showingMore = true"
                class="show-more"
                >
                Show More
            </a>
            <a
                v-else-if="props.notifications.length > 5 && showingMore"
                @click="showingMore = false"
                class="show-more"
                >
                Show Less
            </a>
        </div>
    </div>
</template>
<style scoped>
.notifications-header {
    padding-bottom: 5px;
}

.notification-body {
    background-color: var(--home-panel-gray);
    padding: 20px;
    height: auto;
}

.no-recent {
    padding-top: 10px;
    font-style: italic;
    margin-bottom: 30px;
}

#notifications {
    display: table;
    width: 100%;
    border-collapse: collapse;
}

.notification {
    display: flex;
    border-bottom: 1px solid var(--standard-light-gray);
    padding: 9px 0;
    align-items: center;
    padding-left: 20px;
    padding-right: 20px;
}

.notification.unseen {
    background-color: var(--viewed-content);
}

div.notification:last-of-type {
    border-bottom: none;
}

.notification > * {
    display: block;
    align-items: center;
}

.notification-type {
    margin-right: 1em;
}

.notification-contents {
    display: flex;
    flex-wrap: wrap;
    flex-grow: 1;
}

.notification-content {
    flex: 1;
}

.notification-time {
    font-size: 0.9rem;
    color: var(--standard-medium-dark-gray);
    flex: 1 0 90%;
    margin-top: 2px;
}

.notification-seen {
    text-align: center;
    flex: 0 0 auto;
    padding: 10px 16px;
}

a.show-more {
    display: inline-block;
    margin-top: 10px;
}
</style>