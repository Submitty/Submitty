<script>

</script>
<template>
    <div class="notification-content">
        <h1>Notifications</h1>
        <p class="no-recent" id="no-recent-notifications">No recent notifications.</p>
        <div id="recent-notifications">

        </div>
    </div>
</template>
<style scoped>
.notification-content {
  grid-column: 2;
  background-color: #3d3d3d;
  padding: 20px;
  height: 500px;
}
.no-recent {
    padding-top: 10px;
    font-style: italic;
}
</style>