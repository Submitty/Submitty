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
      const form = $('#edit-chatroom-form');
      form.css('display', 'block');
      document.getElementById('chatroom-edit-form').action = `${this.baseUrl}/${this.id}/edit`;
      document.getElementById('chatroom-title-input').value = this.title;
      document.getElementById('chatroom-description-input').value = this.description;
      document.getElementById('chatroom-anon-allow').checked = this.allowAsnon;
    },
    
    handleDeleteChatroom() {
      if (confirm(`This will delete chatroom '${this.title}'. Are you sure?`)) {
        const url = `${this.baseUrl}/delete`;
        const fd = new FormData();
        fd.append('csrf_token', this.csrfToken);
        fd.append('chatroom_id', this.id);
        $.ajax({
            url: url,
            type: 'POST',
            data: fd,
            processData: false,
            cache: false,
            contentType: false,
            success: function (data) {
                try {
                    const msg = JSON.parse(data);
                    if (msg.status !== 'success') {
                        console.error(msg);
                        window.alert('Something went wrong. Please try again.');
                    }
                    else {
                        window.location.reload();
                    }
                }
                catch (err) {
                    console.error(err);
                    window.alert('Something went wrong. Please try again.');
                }
            },
            error: function (err) {
                console.error(err);
                window.alert('Something went wrong. Please try again.');
            },
        });
      }
    },
    
    handleToggleSession() {
      const form = document.getElementById(`chatroom_toggle_form_${this.id}`);
      if (!this.isActive || confirm('This will close the chatroom. Are you sure?')) {
          form.submit();
      }
    }
  }
}
</script>

<template>
  <tr :id="`chatroom-row-${this.id}`">
    <!-- Admin view -->
    <template v-if="this.isAdmin">
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
          v-if="!this.isActive"
          href="javascript:void(0)" 
          @click="handleDeleteChatroom"
          class="fas fa-trash-alt black-btn" 
          data-testid="delete-chatroom"
        ></a>
      </td>
      
      <!-- Title -->
      <td>
        <span :title="this.title">
          {{ truncateText(title, 30) }}
        </span>
      </td>
      
      <!-- Description -->
      <td>
        <span :title="this.description">
          {{ truncateText(description, 45) }}
        </span>
      </td>
      
      <!-- Session toggle -->
      <td>
        <form 
          :id="`chatroom_toggle_form_${this.id}`" 
          :action="`${this.baseUrl}/${this.id}/toggleActiveStatus`" 
          method="post"
          @submit.prevent="handleToggleSession"
        >
          <input type="hidden" name="csrf_token" :value="this.csrfToken"/>
        </form>
        
        <button 
          v-if="!this.isActive"
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
          :href="`${this.baseUrl}/${this.id}`" 
          class="btn btn-primary" 
          data-testid="chat-join-btn"
        >
          Join
        </a>
        
        <template v-if="this.isAllowAnon">
          <i> or </i>
          <a 
            :href="`${this.baseUrl}/${this.id}/anonymous`" 
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
      <template v-if="this.isActive">
        <!-- Title -->
        <td>
          <span class="display-short" :title="this.title">
            {{ truncateText(this.title, 30) }}
          </span>
        </td>
        
        <!-- Host name -->
        <td>
          {{ this.hostName }}
        </td>
        
        <!-- Description -->
        <td>
          <span class="display-short" :title="this.description">
            {{ truncateText(this.description, 45) }}
          </span>
        </td>
        
        <!-- Join buttons -->
        <td>
          <a :href="`${this.baseUrl}/${this.id}`" class="btn btn-primary">Join</a>
          
          <template v-if="this.isAllowAnon">
            <i> or </i>
            <a 
              :href="`${this.baseUrl}/${this.id}/anonymous`" 
              class="btn btn-default"
            >
              Join As Anon.
            </a>
          </template>
        </td>
      </template>
    </template>
  </tr>
</template>

