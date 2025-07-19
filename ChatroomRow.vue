<template>
  <tr :id="`chatroom-row-${id}`" v-if="isAdmin || isActive">
    <!-- Admin view -->
    <template v-if="isAdmin">
      <!-- Edit button -->
      <td>
        <a 
          href="javascript:void(0)" 
          @click="handleEditChatroom"
          class="fas fa-pencil-alt black-btn"
        ></a>
      </td>
      
      <!-- Delete button -->
      <td>
        <a 
          v-if="!isActive"
          href="javascript:void(0)" 
          @click="handleDeleteChatroom"
          class="fas fa-trash-alt black-btn" 
          data-testid="delete-chatroom"
        ></a>
      </td>
      
      <!-- Title -->
      <td>
        <span :title="title">
          {{ truncateText(title, 30) }}
        </span>
      </td>
      
      <!-- Description -->
      <td>
        <span :title="description">
          {{ truncateText(description, 45) }}
        </span>
      </td>
      
      <!-- Session toggle -->
      <td>
        <form 
          :id="`chatroom_toggle_form_${id}`" 
          :action="`${baseUrl}/${id}/toggleActiveStatus`" 
          method="post"
          @submit.prevent="handleToggleSession"
        >
          <input type="hidden" name="csrf_token" :value="csrfToken"/>
        </form>
        
        <button 
          v-if="!isActive"
          class="btn btn-primary" 
          @click="handleToggleSession"
        >
          Start Session
        </button>
        
        <button 
          v-else
          class="btn btn-danger" 
          @click="handleToggleSession"
        >
          <i class="fas fa-pause"></i>
          End Session
        </button>
      </td>
      
      <!-- Join buttons -->
      <td>
        <a 
          :href="`${baseUrl}/${id}`" 
          class="btn btn-primary" 
          data-testid="chat-join-btn"
        >
          Join
        </a>
        
        <template v-if="isAllowAnon">
          <i> or </i>
          <a 
            :href="`${baseUrl}/${id}/anonymous`" 
            class="btn btn-default" 
            data-testid="anon-chat-join-btn"
          >
            Join As Anon.
          </a>
        </template>
      </td>
    </template>
    
    <!-- Student view (only for active chatrooms) -->
    <template v-else>
      <!-- Title -->
      <td>
        <span class="display-short" :title="title">
          {{ truncateText(title, 30) }}
        </span>
      </td>
      
      <!-- Host name -->
      <td>
        {{ hostName }}
      </td>
      
      <!-- Description -->
      <td>
        <span class="display-short" :title="description">
          {{ truncateText(description, 45) }}
        </span>
      </td>
      
      <!-- Join buttons -->
      <td>
        <a :href="`${baseUrl}/${id}`" class="btn btn-primary">Join</a>
        
        <template v-if="isAllowAnon">
          <i> or </i>
          <a 
            :href="`${baseUrl}/${id}/anonymous`" 
            class="btn btn-default"
          >
            Join As Anon.
          </a>
        </template>
      </td>
    </template>
  </tr>
</template>

<script>
export default {
  name: 'ChatroomRow',
  props: {
    id: {
      type: [String, Number],
      required: true
    },
    title: {
      type: String,
      required: true
    },
    description: {
      type: String,
      required: true
    },
    isActive: {
      type: Boolean,
      default: false
    },
    isAllowAnon: {
      type: Boolean,
      default: false
    },
    isAdmin: {
      type: Boolean,
      default: false
    },
    baseUrl: {
      type: String,
      required: true
    },
    hostName: {
      type: String,
      default: ''
    },
    csrfToken: {
      type: String,
      required: true
    }
  },
  
  methods: {
    truncateText(text, maxLength) {
      if (text.length > maxLength) {
        return text.slice(0, maxLength) + '...';
      }
      return text;
    },
    
    handleEditChatroom() {
      // Call the original editChatroomForm function or emit an event
      if (typeof editChatroomForm === 'function') {
        editChatroomForm(this.id, this.baseUrl, this.title, this.description, this.isAllowAnon);
      } else {
        // Emit event for parent component to handle
        this.$emit('edit-chatroom', {
          id: this.id,
          baseUrl: this.baseUrl,
          title: this.title,
          description: this.description,
          isAllowAnon: this.isAllowAnon
        });
      }
    },
    
    handleDeleteChatroom() {
      // Call the original deleteChatroomForm function or emit an event
      if (typeof deleteChatroomForm === 'function') {
        deleteChatroomForm(this.id, this.title, this.baseUrl);
      } else {
        // Emit event for parent component to handle
        this.$emit('delete-chatroom', {
          id: this.id,
          title: this.title,
          baseUrl: this.baseUrl
        });
      }
    },
    
    handleToggleSession() {
      // Call the original toggleChatroom function or emit an event
      if (typeof toggleChatroom === 'function') {
        toggleChatroom(this.id, this.isActive);
      } else {
        // Emit event for parent component to handle
        this.$emit('toggle-session', {
          id: this.id,
          isActive: this.isActive
        });
      }
    }
  }
}
</script>

<style scoped>
/* Add any component-specific styles here */
.black-btn {
  color: black;
  text-decoration: none;
}

.black-btn:hover {
  color: #333;
}

/* Add any additional component-specific styles here */
</style>
