/**
 * manager-chat.js
 * Real-time chat interactions for AutoCare Workshop Manager:
 * Category tabs (Internal/Clients), thread switching, message sending,
 * image attachments, auto-reply simulation, and new message composer.
 * Now Fully Synced to MySQL Database!
 */

let activeThreadId = null;
let currentTab = 'All';
let contactsList = [];

document.addEventListener('DOMContentLoaded', () => {
  initChatTabs();
  initMessageSending();
  initNewChatButton();
  
  fetchContactsAndMessages();

  // Background Auto-Sync
  setInterval(() => {
    fetchContactsAndMessages(true);
  }, 3000);
});

async function fetchContactsAndMessages(isBackground = false) {
  try {
    const res = await fetch(`../../api/manager-api/manager-chat-api.php?action=list_contacts&_t=${new Date().getTime()}`);
    const data = await res.json();
    if (data.success) {
      contactsList = data.contacts || [];
      if (!activeThreadId && contactsList.length > 0) {
        activeThreadId = contactsList[0].id;
      }
      renderContactsList();
      if (activeThreadId) {
        await loadChatMessagesFromDB(activeThreadId);
      }
    }
  } catch(err) {
    if (!isBackground) console.error('API Error', err);
  }
}

/**
 * 1. Render Left Sidebar Contacts
 */
function renderContactsList() {
  const container = document.querySelector('.threads-list');
  if (!container) return;

  let html = '';
  contactsList.forEach((c, index) => {
    // Filter logic
    let isVisible = false;
    if (currentTab === 'All') isVisible = true;
    else if (currentTab === 'Internal' && c.contact_type === 'Internal') isVisible = true;
    else if (currentTab === 'Clients' && c.contact_type === 'Clients') isVisible = true;

    if (!isVisible) return;

    const isActive = c.id == activeThreadId ? 'active' : '';
    const latestMsg = c.latest_message || 'No messages yet';
    const latestTime = c.latest_time || '';

    html += `
      <div class="thread-item ${isActive}" data-user-id="${c.id}" onclick="switchThread(${c.id})">
        <img class="avatar-img" src="${c.avatar_url || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&q=80'}" alt="${c.name}">
        <div class="thread-content">
          <div class="thread-top">
            <span class="user-name">${c.name} <span class="role">(${c.role})</span></span>
            <span class="time">${latestTime}</span>
          </div>
          <div class="thread-preview">
            ${c.job_tag && c.job_tag !== 'General' ? `<span class="job-tag">${c.job_tag}</span>` : ''}
            <span class="text">${escapeHtml(latestMsg).substring(0, 40)}${latestMsg.length > 40 ? '...' : ''}</span>
          </div>
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}

window.switchThread = function(contactId) {
  activeThreadId = contactId;
  renderContactsList();
  loadChatMessagesFromDB(contactId);
  const contact = contactsList.find(c => c.id == contactId);
  if (contact) {
    showToast(`Switched conversation to ${contact.name}`, 'info');
  }
};

/**
 * 2. Category Filter Tabs (All / Internal / Clients)
 */
function initChatTabs() {
  const tabs = document.querySelectorAll('.threads-panel .tabs .tab');
  
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      currentTab = tab.innerText.trim();
      renderContactsList();
      showToast(`Showing ${currentTab} conversations`, 'info');
    });
  });
}

/**
 * 3. Load Messages for Active Thread
 */
async function loadChatMessagesFromDB(contactId) {
  try {
    const res = await fetch(`../../api/manager-api/manager-chat-api.php?action=get_messages&contact_id=${contactId}&_t=${new Date().getTime()}`);
    const data = await res.json();
    if (data.success && data.contact) {
      
      // Update Header
      const headerTitle = document.querySelector('.chat-header .user-title');
      const headerAvatar = document.querySelector('.chat-header .avatar-img');
      const headerJobChip = document.querySelector('.chat-header .job-chip');
      const input = document.querySelector('.composer-box input');

      if (headerTitle) headerTitle.innerText = data.contact.name;
      if (headerAvatar && data.contact.avatar_url) headerAvatar.src = data.contact.avatar_url;
      if (input) input.placeholder = `Type a message to ${data.contact.name}...`;

      if (headerJobChip) {
        headerJobChip.style.cursor = 'pointer';
        headerJobChip.title = 'Open Job Card';
        headerJobChip.onclick = () => window.location.href = 'manager-jobCards.html';
        headerJobChip.innerHTML = `
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
          ${data.contact.job_tag || 'General'}
        `;
      }

      // Update Messages
      const container = document.querySelector('.chat-messages');
      if (!container) return;

      container.innerHTML = `<div class="date-divider"><span>TODAY</span></div>`;
      
      data.messages.forEach(m => {
        appendMessageToView(m.message, m.is_incoming == 1, m.time, data.contact.name, data.contact.avatar_url);
      });
      
      scrollToBottom();
    }
  } catch(err) {
    console.error(err);
  }
}

/**
 * 4. Message Sending & Auto-Reply Simulation
 */
function initMessageSending() {
  const sendBtn = document.querySelector('.composer-box .send-btn');
  const input = document.querySelector('.composer-box input');

  if (!sendBtn || !input) return;

  const sendMessage = async () => {
    const text = input.value.trim();
    if (!text || !activeThreadId) return;

    input.value = '';
    
    try {
      const res = await fetch('../../api/manager-api/manager-chat-api.php?action=send_message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ contact_id: activeThreadId, message: text, is_incoming: 0 })
      });
      const data = await res.json();
      if (data.success) {
        await loadChatMessagesFromDB(activeThreadId);
        
        // Auto-Reply simulation after 1.5 seconds if it's an internal tech
        const contact = contactsList.find(c => c.id == activeThreadId);
        if (contact && contact.contact_type === 'Internal') {
          setTimeout(async () => {
            const replies = [
              "Copy that, boss! I'm on it.",
              "Got it! Let me inspect the vehicle right away.",
              "Understood. Will update the job card.",
              "I am pulling the parts from inventory right now."
            ];
            const randomReply = replies[Math.floor(Math.random() * replies.length)];
            
            await fetch('../../api/manager-api/manager-chat-api.php?action=send_message', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ contact_id: activeThreadId, message: randomReply, is_incoming: 1 })
            });
            
            await loadChatMessagesFromDB(activeThreadId);
            showToast(`New message from ${contact.name}`, 'info');
          }, 2000);
        }
      }
    } catch(err) {
      showToast('Offline mode', 'warning');
    }
  };

  sendBtn.addEventListener('click', sendMessage);

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });
}

function appendMessageToView(text, isIncoming, timeStr, contactName, avatarUrl) {
  const container = document.querySelector('.chat-messages');
  if (!container) return;

  const msgDiv = document.createElement('div');
  msgDiv.className = `message ${isIncoming ? 'incoming' : 'outgoing'}`;

  // Check if text indicates image attachments hack
  let mediaHtml = '';
  if (text.includes('Attaching high-resolution') || text.includes('Sending pics now')) {
    mediaHtml = `
      <div class="media-attachments" style="display: flex; gap: 8px; margin-top: 8px;">
        <img src="../../assets/images/engine.jpg" alt="Attached Photo" style="width: 120px; height: 90px; object-fit: cover; border-radius: 6px;">
        <img src="../../assets/images/shop.jpg" alt="Attached Photo" style="width: 120px; height: 90px; object-fit: cover; border-radius: 6px;">
      </div>
    `;
  }

  if (isIncoming) {
    msgDiv.innerHTML = `
      <img class="avatar-img" src="${avatarUrl || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&q=80'}" alt="Sender">
      <div class="message-body">
        <div class="message-sender">${contactName} <span class="msg-time">${timeStr}</span></div>
        <div class="bubble">${escapeHtml(text)}</div>
        ${mediaHtml}
      </div>
    `;
  } else {
    msgDiv.innerHTML = `
      <div class="message-body">
        <div class="message-sender"><span class="msg-time">${timeStr}</span> You</div>
        <div class="bubble">${escapeHtml(text)}</div>
        ${mediaHtml}
      </div>
      <img class="avatar-img" src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=80&q=80" alt="You">
    `;
  }

  container.appendChild(msgDiv);
}

function scrollToBottom() {
  const container = document.querySelector('.chat-messages');
  if (container) {
    container.scrollTop = container.scrollHeight;
  }
}

/**
 * 5. "+ New Message" Button
 */
function initNewChatButton() {
  const newChatBtn = document.querySelector('.threads-header .btn-new-chat');
  if (!newChatBtn) return;

  newChatBtn.addEventListener('click', async () => {
    
    let optionsHtml = '';
    
    // Fetch Mechanics
    try {
      const resM = await fetch(`../../api/manager-api/manager-mechanics-api.php?action=list&_t=${new Date().getTime()}`);
      const dataM = await resM.json();
      if (dataM.success && dataM.mechanics) {
        optionsHtml += `<optgroup label="Internal (Mechanics)">`;
        dataM.mechanics.forEach(m => {
          optionsHtml += `<option value="${m.id}|${m.name}|${m.specialty}|Internal">${m.name} (${m.specialty})</option>`;
        });
        optionsHtml += `</optgroup>`;
      }
    } catch(e) {}
    
    // Fetch Clients
    try {
      const resC = await fetch(`../../api/manager-api/manager-cost-estimation-api.php?action=job_cards&_t=${new Date().getTime()}`);
      const dataC = await resC.json();
      if (dataC.success && dataC.job_cards) {
        optionsHtml += `<optgroup label="Clients (Job Cards)">`;
        dataC.job_cards.forEach(c => {
          optionsHtml += `<option value="${c.customer_id}|${c.customer_name}|Client|Clients">${c.customer_name} (${c.vehicle_title || 'Vehicle'})</option>`;
        });
        optionsHtml += `</optgroup>`;
      }
    } catch(e) {}

    openModal({
      title: 'Start New Conversation',
      content: `
        <div>
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Select Contact</label>
          <select id="modal-chat-user" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
            ${optionsHtml}
          </select>
          <div style="margin-top: 14px;">
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Initial Message</label>
            <input type="text" id="modal-chat-first-msg" placeholder="Type opening greeting or task..." style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
          </div>
        </div>
      `,
      confirmText: 'Start Chat',
      onConfirm: async () => {
        const val = document.getElementById('modal-chat-user').value;
        const msg = document.getElementById('modal-chat-first-msg').value.trim();
        
        if (val && msg) {
          const [id, name, role, type] = val.split('|');
          
          try {
            // 1. "Create" the new contact (just returns the ID in the new schema)
            const res1 = await fetch('../../api/manager-api/manager-chat-api.php?action=new_contact', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ id, name, role, type })
            });
            const data1 = await res1.json();
            
            if (data1.success) {
              const newId = data1.contact_id;
              
              // 2. Send the initial message
              await fetch('../../api/manager-api/manager-chat-api.php?action=send_message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ contact_id: newId, message: msg, is_incoming: 0 })
              });
              
              showToast(`Started conversation with ${name}!`, 'success');
              
              // Switch to new thread
              activeThreadId = newId;
              await fetchContactsAndMessages();
            }
          } catch(err) {
            showToast('Error creating chat', 'warning');
          }
        }
      }
    });
  });
}

function escapeHtml(str) {
  if (typeof str !== 'string') return String(str || '');
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
