/**
 * manager-chat.js
 * Real-time chat interactions for AutoCare Workshop Manager:
 * Category tabs (Internal/Clients), thread switching, message sending,
 * image attachments, auto-reply simulation, and new message composer.
 */

let activeThreadId = 8; // Mike Davis
let currentTab = 'All';

document.addEventListener('DOMContentLoaded', () => {
  initChatTabs();
  initThreadSelection();
  initMessageSending();
  initNewChatButton();
  scrollToBottom();
  loadChatMessagesFromDB(activeThreadId);
});

window.addEventListener('autocare:store:synced', () => {
  loadChatMessagesFromDB(activeThreadId);
});

/**
 * 1. Category Filter Tabs (All / Internal / Clients)
 */
function initChatTabs() {
  const tabs = document.querySelectorAll('.threads-panel .tabs .tab');
  const threadItems = document.querySelectorAll('.threads-list .thread-item');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      const tabText = tab.innerText.trim();
      currentTab = tabText;

      threadItems.forEach(item => {
        const role = item.querySelector('.role') ? item.querySelector('.role').innerText.toLowerCase() : '';
        if (tabText === 'All') {
          item.style.display = 'flex';
        } else if (tabText === 'Internal') {
          item.style.display = role.includes('tech') || role.includes('bay') || role.includes('mechanic') ? 'flex' : 'none';
        } else if (tabText === 'Clients') {
          item.style.display = role.includes('fleet') || role.includes('owner') || role.includes('mgr') ? 'flex' : 'none';
        }
      });

      showToast(`Showing ${tabText} conversations`, 'info');
    });
  });
}

/**
 * 2. Thread Selection & Chat Window Header Update
 */
function initThreadSelection() {
  const threadItems = document.querySelectorAll('.threads-list .thread-item');
  const headerAvatar = document.querySelector('.chat-header .avatar-img');
  const headerTitle = document.querySelector('.chat-header .user-title');
  const headerJobChip = document.querySelector('.chat-header .job-chip');

  threadItems.forEach((item, index) => {
    item.addEventListener('click', () => {
      threadItems.forEach(t => t.classList.remove('active'));
      item.classList.add('active');

      const name = item.querySelector('.user-name').childNodes[0].nodeValue.trim();
      const jobTag = item.querySelector('.job-tag') ? item.querySelector('.job-tag').innerText.trim() : 'General';
      const avatarSrc = item.querySelector('.avatar-img') ? item.querySelector('.avatar-img').src : '';

      if (headerTitle) headerTitle.innerText = name;
      if (headerAvatar && avatarSrc) headerAvatar.src = avatarSrc;
      if (headerJobChip) {
        headerJobChip.style.cursor = 'pointer';
        headerJobChip.title = 'Open Job Card';
        headerJobChip.onclick = () => window.location.href = 'manager-jobCards.html';
        headerJobChip.innerHTML = `
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
          ${jobTag}
        `;
      }

      const input = document.querySelector('.composer-box input');
      if (input) input.placeholder = `Type a message to ${name}...`;

      const threadUserId = item.getAttribute('data-user-id') || (index === 0 ? 8 : (index === 1 ? 4 : (index === 2 ? 101 : 3)));
      activeThreadId = Number(threadUserId);
      loadChatMessagesFromDB(activeThreadId);

      showToast(`Switched conversation to ${name}`, 'info');
    });
  });
}

/**
 * 3. Message Sending & Auto-Reply Simulation
 */
function initMessageSending() {
  const sendBtn = document.querySelector('.composer-box .send-btn');
  const input = document.querySelector('.composer-box input');
  const attachBtns = document.querySelectorAll('.attachment-actions .attach-btn');

  if (!sendBtn || !input) return;

  const sendMessage = () => {
    const text = input.value.trim();
    if (!text) return;

    appendMessageToView(text, false);
    input.value = '';

    if (typeof AutoCareStore !== 'undefined') {
      AutoCareStore.sendChatMessage(text, [], activeThreadId);
    }

    scrollToBottom();

    // Auto-Reply simulation after 1.5 seconds
    setTimeout(() => {
      const replies = [
        "Copy that, boss! I'm on it.",
        "Got it! Let me inspect the vehicle right away and update the report.",
        "Understood. Will add the new estimate to the job card.",
        "Parts received from the inventory room. Starting teardown now."
      ];
      const randomReply = replies[Math.floor(Math.random() * replies.length)];
      appendMessageToView(randomReply, true);

      if (typeof AutoCareStore !== 'undefined') {
        AutoCareStore.simulateMechanicReply(randomReply, activeThreadId);
      }

      scrollToBottom();
      showToast('New message from Mike Davis', 'info');
    }, 1500);
  };

  sendBtn.addEventListener('click', sendMessage);

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  // Attach Image Button
  if (attachBtns.length >= 2) {
    const imgAttachBtn = attachBtns[1];
    imgAttachBtn.addEventListener('click', () => {
      appendMessageToView("Attaching high-resolution engine diagnostics photos:", false, [
        '../../assets/images/engine.jpg'
      ]);
      scrollToBottom();
      showToast('Image attached to conversation!', 'success');
    });

    const fileAttachBtn = attachBtns[0];
    fileAttachBtn.addEventListener('click', () => {
      appendMessageToView("Attached document: 📄 Diagnostic_Report_WO-8492.pdf", false);
      scrollToBottom();
      showToast('Diagnostic PDF attached!', 'info');
    });
  }
}

function appendMessageToView(text, isIncoming, attachments = []) {
  const container = document.querySelector('.chat-messages');
  if (!container) return;

  const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  const msgDiv = document.createElement('div');
  msgDiv.className = `message ${isIncoming ? 'incoming' : 'outgoing'}`;

  let mediaHtml = '';
  if (attachments.length > 0) {
    mediaHtml = `
      <div class="media-attachments" style="display: flex; gap: 8px; margin-top: 8px;">
        ${attachments.map(src => `<img src="${src}" alt="Attached Photo" style="width: 120px; height: 90px; object-fit: cover; border-radius: 6px;">`).join('')}
      </div>
    `;
  }

  if (isIncoming) {
    msgDiv.innerHTML = `
      <img class="avatar-img" src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&q=80" alt="Sender">
      <div class="message-body">
        <div class="message-sender">Mike Davis <span class="msg-time">${now}</span></div>
        <div class="bubble">${text}</div>
        ${mediaHtml}
      </div>
    `;
  } else {
    msgDiv.innerHTML = `
      <div class="message-body">
        <div class="message-sender"><span class="msg-time">${now}</span> You</div>
        <div class="bubble">${text}</div>
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
 * 4. "+ New Message" Button
 */
function initNewChatButton() {
  const newChatBtn = document.querySelector('.threads-header .btn-new-chat');
  if (!newChatBtn) return;

  newChatBtn.addEventListener('click', () => {
    const users = AutoCareStore ? AutoCareStore.getUsers().filter(u => u.role !== 'Admin' && u.id !== 2) : [];
    const options = users.map(u => `<option value="${u.id}">${u.name || u.username} (${u.role})</option>`).join('');

    openModal({
      title: 'Start New Conversation',
      content: `
        <div>
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Select Contact</label>
          <select id="modal-chat-user" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
            ${options}
          </select>
          <div style="margin-top: 14px;">
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Initial Message</label>
            <input type="text" id="modal-chat-first-msg" placeholder="Type opening greeting or task..." style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
          </div>
        </div>
      `,
      confirmText: 'Send Message',
      onConfirm: () => {
        const uId = document.getElementById('modal-chat-user').value;
        const msg = document.getElementById('modal-chat-first-msg').value.trim();
        const user = users.find(u => u.id === Number(uId));

        if (user && msg) {
          appendMessageToView(msg, false);
          scrollToBottom();
          if (typeof AutoCareStore !== 'undefined') {
            AutoCareStore.sendChatMessage(msg, [], user.id);
          }
          showToast(`Started conversation with ${user.name || user.username}!`, 'success');
        }
      }
    });
  });
}

async function loadChatMessagesFromDB(userId = 8) {
  if (!window.managerApi) return;
  const res = await window.managerApi.getChat(userId);
  if (!res || !res.messages) return;
  const container = document.querySelector('.chat-messages');
  if (!container) return;
  container.innerHTML = '';
  res.messages.forEach(m => {
    appendMessageToView(m.message, m.is_incoming, m.attachments || []);
  });
  scrollToBottom();
}
