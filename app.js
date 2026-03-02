(function () {
  'use strict';

  // DOM references
  const memberIdInput = document.getElementById('member-id');
  const memberNameInput = document.getElementById('member-name');
  const cameraFeed = document.getElementById('camera-feed');
  const photoCanvas = document.getElementById('photo-canvas');
  const photoPreview = document.getElementById('photo-preview');
  const cameraPlaceholder = document.getElementById('camera-placeholder');
  const btnStartCamera = document.getElementById('btn-start-camera');
  const btnCapture = document.getElementById('btn-capture');
  const btnRetake = document.getElementById('btn-retake');
  const btnCheckin = document.getElementById('btn-checkin');
  const checkinMessage = document.getElementById('checkin-message');
  const checkinLog = document.getElementById('checkin-log');

  const MAX_LOG_ENTRIES = 50;

  let cameraStream = null;
  let capturedPhotoDataUrl = null;
  let messageHideTimer = null;

  // ── Camera lifecycle ─────────────────────────────────────────────────────────

  async function startCamera() {
    try {
      cameraStream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
        audio: false,
      });
      cameraFeed.srcObject = cameraStream;
      cameraFeed.style.display = 'block';
      cameraPlaceholder.style.display = 'none';

      btnStartCamera.classList.add('hidden');
      btnCapture.classList.remove('hidden');
    } catch (err) {
      showMessage('Could not access camera: ' + err.message, 'error');
    }
  }

  function stopCamera() {
    if (cameraStream) {
      cameraStream.getTracks().forEach(function (track) {
        track.stop();
      });
      cameraStream = null;
    }
    cameraFeed.srcObject = null;
    cameraFeed.style.display = 'none';
  }

  function capturePhoto() {
    const width = cameraFeed.videoWidth;
    const height = cameraFeed.videoHeight;

    if (!width || !height) {
      showMessage('Camera is not ready yet. Please wait a moment and try again.', 'error');
      return;
    }

    photoCanvas.width = width;
    photoCanvas.height = height;

    const ctx = photoCanvas.getContext('2d');
    ctx.drawImage(cameraFeed, 0, 0, width, height);
    capturedPhotoDataUrl = photoCanvas.toDataURL('image/jpeg', 0.85);

    // Show the still image and stop the live feed
    photoPreview.src = capturedPhotoDataUrl;
    photoPreview.classList.remove('hidden');

    stopCamera();

    btnCapture.classList.add('hidden');
    btnRetake.classList.remove('hidden');

    updateCheckinButton();
  }

  function retakePhoto() {
    resetPhotoState();
  }

  // ── Check-in ─────────────────────────────────────────────────────────────────

  function resetPhotoState() {
    capturedPhotoDataUrl = null;
    photoPreview.src = '';
    photoPreview.classList.add('hidden');

    btnRetake.classList.add('hidden');
    btnStartCamera.classList.remove('hidden');
    cameraPlaceholder.style.display = '';

    updateCheckinButton();
  }

  function updateCheckinButton() {
    const hasName = memberNameInput.value.trim().length > 0;
    btnCheckin.disabled = !(hasName && capturedPhotoDataUrl);
  }

  function handleCheckin() {
    const memberId = memberIdInput.value.trim();
    const memberName = memberNameInput.value.trim();

    if (!memberName) {
      showMessage('Please enter a member name.', 'error');
      return;
    }

    if (!capturedPhotoDataUrl) {
      showMessage('Please capture a photo before checking in.', 'error');
      return;
    }

    addLogEntry(memberId, memberName, capturedPhotoDataUrl);
    showMessage(memberName + ' checked in successfully!', 'success');

    // Reset form
    memberIdInput.value = '';
    memberNameInput.value = '';
    resetPhotoState();
  }

  // ── Log ──────────────────────────────────────────────────────────────────────

  function addLogEntry(memberId, memberName, photoDataUrl) {
    const emptyMsg = checkinLog.querySelector('.empty-log');
    if (emptyMsg) {
      emptyMsg.remove();
    }

    const now = new Date();
    const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) +
      ', ' + now.toLocaleDateString([], { month: 'short', day: 'numeric' });

    const entry = document.createElement('div');
    entry.className = 'log-entry';

    const img = document.createElement('img');
    img.src = photoDataUrl;
    img.alt = 'Photo of ' + memberName;

    const info = document.createElement('div');
    info.className = 'log-entry-info';

    const nameEl = document.createElement('div');
    nameEl.className = 'log-entry-name';
    nameEl.textContent = memberName;

    const idEl = document.createElement('div');
    idEl.className = 'log-entry-id';
    idEl.textContent = memberId ? 'ID: ' + memberId : 'No ID provided';

    const timeEl = document.createElement('div');
    timeEl.className = 'log-entry-time';
    timeEl.textContent = timeStr;

    info.appendChild(nameEl);
    info.appendChild(idEl);
    info.appendChild(timeEl);

    entry.appendChild(img);
    entry.appendChild(info);

    // Prepend so newest appears at top
    checkinLog.insertBefore(entry, checkinLog.firstChild);

    // Enforce maximum log size to prevent memory accumulation
    const entries = checkinLog.querySelectorAll('.log-entry');
    for (let i = MAX_LOG_ENTRIES; i < entries.length; i++) {
      entries[i].remove();
    }
  }

  // ── UI helpers ────────────────────────────────────────────────────────────────

  function showMessage(text, type) {
    checkinMessage.textContent = text;
    checkinMessage.className = 'checkin-message ' + type;

    clearTimeout(messageHideTimer);
    messageHideTimer = setTimeout(function () {
      checkinMessage.classList.add('hidden');
    }, 4000);
  }

  // ── Event listeners ───────────────────────────────────────────────────────────

  btnStartCamera.addEventListener('click', startCamera);
  btnCapture.addEventListener('click', capturePhoto);
  btnRetake.addEventListener('click', retakePhoto);
  btnCheckin.addEventListener('click', handleCheckin);

  memberNameInput.addEventListener('input', updateCheckinButton);
}());
