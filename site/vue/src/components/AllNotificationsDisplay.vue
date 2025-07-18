<script>

</script>
<template>
    <div class="notification-body">
        <h1 class="notifications-header">Notifications</h1>
        <!--<p class="no-recent" id="no-recent-notifications">No recent notifications.</p>-->
        <div id="recent-notifications">
            <div class="notification" v-for="n in 12" :key="n">
                    <i class="fas fa-comments notification-type" title="Forum"></i>
                <div class="notification-content">
                        <div class="notification-contents">
                            <div class="notification-content">
                                This is the content of the notification
                            </div>
                        </div>
                    <div class="notification-time">
                        41 minutes ago
                    </div>
                </div>
                <a class="notification-seen black-btn" title="Mark as seen" aria-label="Mark as seen">
                    <i class="fas fa-external-link"></i>
                </a>
                <a class="notification-seen black-btn" title="Mark as seen" aria-label="Mark as seen">
                    <i class="far fa-envelope-open"></i>
                </a>
            </div>
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
  max-height: 500px;
  overflow-y: scroll;
}

.no-recent {
    padding-top: 10px;
    font-style: italic;
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
}

.notification:nth-last-child(1) {
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
</style>