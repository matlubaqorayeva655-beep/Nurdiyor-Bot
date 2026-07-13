<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>🎥 Birga Ko'rish</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
body{background:#0d0d0d;color:#fff;height:100dvh;display:flex;flex-direction:column;overflow:hidden}

/* Top bar */
#topbar{background:#161622;padding:10px 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid #2a2a3a;flex-shrink:0}
#topbar h1{font-size:15px;font-weight:700;color:#a78bfa;white-space:nowrap}
#url-input{flex:1;background:#0d0d1a;border:1px solid #2a2a3a;border-radius:8px;padding:8px 12px;color:#fff;font-size:13px;outline:none;min-width:0}
#url-input::placeholder{color:#555}
#load-btn{background:#7c3aed;border:none;border-radius:8px;padding:8px 14px;color:#fff;cursor:pointer;font-size:13px;white-space:nowrap;flex-shrink:0}
#load-btn:hover{background:#6d28d9}

/* Age modal */
#age-modal{position:fixed;inset:0;background:rgba(0,0,0,.9);display:flex;align-items:center;justify-content:center;z-index:999}
#age-box{background:#161622;border:1px solid #2a2a3a;border-radius:20px;padding:32px 24px;text-align:center;max-width:320px;width:90%}
#age-box h2{font-size:22px;margin-bottom:8px}
#age-box p{color:#888;font-size:14px;margin-bottom:24px;line-height:1.5}
.age-btn{padding:13px 28px;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;margin:0 6px}
.age-yes{background:#7c3aed;color:#fff}
.age-no{background:#2a2a3a;color:#aaa}

/* Main */
#main{display:flex;flex:1;overflow:hidden;min-height:0}

/* Video side */
#video-side{flex:1;display:flex;flex-direction:column;position:relative;background:#000;min-width:0}
#player-wrap{flex:1;position:relative;background:#000}
#player-wrap iframe,#player-wrap video{position:absolute;inset:0;width:100%;height:100%;border:none}
#no-video{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#333;gap:12px}
#no-video .icon{font-size:56px}
#no-video p{font-size:14px;color:#444;text-align:center;padding:0 24px}
#no-video small{font-size:12px;color:#333}

/* Controls bar */
#controls{background:#0d0d1a;padding:8px 14px;display:flex;align-items:center;gap:10px;border-top:1px solid #1a1a2e;flex-shrink:0}
#mic-btn{background:#2a2a3a;border:none;border-radius:10px;padding:8px 14px;color:#fff;cursor:pointer;font-size:13px;display:flex;align-items:center;gap:6px;transition:.2s}
#mic-btn.on{background:#16a34a}
#mic-btn.on::before{content:'🟢 '}
#status{font-size:12px;color:#555;flex:1}
#partner-status{font-size:12px;padding:4px 10px;border-radius:20px;background:#1a1a2e;color:#888}

/* Chat side */
#chat-side{width:240px;background:#111;display:flex;flex-direction:column;border-left:1px solid #1a1a2e;flex-shrink:0}
#chat-header{padding:10px 12px;background:#161622;font-size:12px;color:#888;border-bottom:1px solid #1a1a2e;flex-shrink:0}
#messages{flex:1;overflow-y:auto;padding:10px;display:flex;flex-direction:column;gap:6px;scroll-behavior:smooth}
#messages::-webkit-scrollbar{width:3px}
#messages::-webkit-scrollbar-thumb{background:#2a2a3a;border-radius:2px}
.msg{background:#1a1a2e;border-radius:10px;padding:7px 10px;font-size:12px}
.msg .who{color:#a78bfa;font-weight:600;margin-bottom:2px;font-size:11px}
.msg .txt{color:#ccc;word-break:break-word}
.msg.me{background:#1e1b4b}
#chat-input-row{padding:8px;display:flex;gap:6px;border-top:1px solid #1a1a2e;flex-shrink:0}
#chat-input{flex:1;background:#1a1a2e;border:none;border-radius:8px;padding:8px;color:#fff;font-size:13px;outline:none}
#send-btn{background:#7c3aed;border:none;border-radius:8px;padding:8px 12px;color:#fff;cursor:pointer;font-size:14px}

/* Mobile: hide chat, show toggle */
@media(max-width:600px){
  #chat-side{position:fixed;bottom:0;right:0;top:0;width:260px;transform:translateX(100%);transition:.3s;z-index:100}
  #chat-side.open{transform:translateX(0)}
  #chat-toggle{display:flex}
}
#chat-toggle{display:none;background:#2a2a3a;border:none;border-radius:8px;padding:7px 12px;color:#fff;cursor:pointer;font-size:13px;gap:4px;align-items:center}
#close-chat{display:none;position:absolute;top:10px;left:10px;background:#2a2a3a;border:none;border-radius:6px;padding:4px 8px;color:#fff;cursor:pointer;font-size:12px}
@media(max-width:600px){#close-chat{display:block}}
</style>
</head>
<body>

<!-- Yosh tekshiruvi -->
<div id="age-modal">
  <div id="age-box">
    <h2>🔞</h2>
    <p>Bu bo'limda 18+ kontent bo'lishi mumkin.<br>Yoshingizni tasdiqlang.</p>
    <button class="age-btn age-yes" onclick="confirmAge(true)">✅ 18 yoshdan kattaman</button>
    <button class="age-btn age-no" onclick="confirmAge(false)">❌ Yo'q</button>
  </div>
</div>

<!-- Top bar -->
<div id="topbar">
  <h1>🎥 Birga</h1>
  <input id="url-input" type="text" placeholder="YouTube linki yoki to'g'ridan-to'g'ri video URL...">
  <button id="load-btn" onclick="loadVideo()">▶ Ochish</button>
  <button id="chat-toggle" onclick="toggleChat()">💬</button>
</div>

<!-- Asosiy qism -->
<div id="main">
  <!-- Video -->
  <div id="video-side">
    <div id="player-wrap">
      <div id="no-video">
        <div class="icon">🎬</div>
        <p>YouTube linki yoki MP4/WebM URL kiriting</p>
        <small>Masalan: https://www.youtube.com/watch?v=...</small>
      </div>
    </div>
    <div id="controls">
      <button id="mic-btn" onclick="toggleMic()">🎤 Mikrofon</button>
      <span id="status">Ulangan</span>
      <span id="partner-status">👤 Kutilmoqda...</span>
    </div>
  </div>

  <!-- Chat -->
  <div id="chat-side" id="chat-panel">
    <div id="chat-header">
      <button id="close-chat" onclick="toggleChat()">✕</button>
      💬 Chat
    </div>
    <div id="messages"></div>
    <div id="chat-input-row">
      <input id="chat-input" type="text" placeholder="Yozing..." onkeydown="if(e=event,e.key==='Enter'&&!e.shiftKey)sendChat()">
      <button id="send-btn" onclick="sendChat()">➤</button>
    </div>
  </div>
</div>

<script>
// ─── Config ─────────────────────────────────────────────────────────────────
const token  = new URLSearchParams(location.search).get('token') || '';
const myId   = 'u' + Math.random().toString(36).slice(2, 8);
const myName = 'Foydalanuvchi ' + myId.slice(1, 4).toUpperCase();
const syncUrl = location.pathname.replace('index.php','') + 'sync.php?token=' + encodeURIComponent(token);

let lastTs      = 0;
let lastVideoUrl = '';
let micActive   = false;
let localStream = null;
let pc          = null;
let pollTimer   = null;
let signalRole  = null; // 'caller' | 'callee'

// ─── Age gate ────────────────────────────────────────────────────────────────
if (localStorage.getItem('age_ok') === '1') {
  document.getElementById('age-modal').style.display = 'none';
}
function confirmAge(yes) {
  if (yes) {
    localStorage.setItem('age_ok','1');
    document.getElementById('age-modal').style.display = 'none';
  } else {
    history.back();
  }
}

// ─── Chat toggle (mobile) ────────────────────────────────────────────────────
function toggleChat() {
  document.getElementById('chat-side').classList.toggle('open');
}

// ─── Load video ──────────────────────────────────────────────────────────────
function loadVideo() {
  const raw = document.getElementById('url-input').value.trim();
  if (!raw) return;
  applyVideo(raw);
  post('video', {url: raw});
}

function parseVideoUrl(url) {
  url = url.trim();

  // 1. YouTube — barcha formatlari
  let yt = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/|youtube\.com\/embed\/)([A-Za-z0-9_\-]{11})/);
  if (yt) return { type: 'iframe', src: `https://www.youtube.com/embed/${yt[1]}?autoplay=1&rel=0` };

  // 2. Google Drive — /file/d/ID/view yoki /open?id=ID
  let gd = url.match(/drive\.google\.com\/file\/d\/([a-zA-Z0-9_\-]+)/);
  if (!gd) gd = url.match(/drive\.google\.com\/open\?id=([a-zA-Z0-9_\-]+)/);
  if (gd) return { type: 'iframe', src: `https://drive.google.com/file/d/${gd[1]}/preview` };

  // 3. Yandex.Disk video havolasi
  if (/disk\.yandex\.(ru|com|kz|ua)/.test(url)) {
    // Yandex Disk iframe ruxsat bermaydi, ammo to'g'ridan-to'g'ri player linkiga o'giramiz
    const ydEncoded = encodeURIComponent(url);
    return { type: 'iframe', src: `https://yandex.ru/video/embed/?url=${ydEncoded}` };
  }

  // 4. VK Video
  let vk = url.match(/vk\.com\/video(-?\d+)_(\d+)/);
  if (vk) return { type: 'iframe', src: `https://vk.com/video_ext.php?oid=${vk[1]}&id=${vk[2]}&hd=2` };

  // 5. Rutube
  let rt = url.match(/rutube\.ru\/video\/([a-f0-9]+)/);
  if (rt) return { type: 'iframe', src: `https://rutube.ru/play/embed/${rt[1]}/` };

  // 6. Dailymotion
  let dm = url.match(/dailymotion\.com\/video\/([a-zA-Z0-9]+)/);
  if (dm) return { type: 'iframe', src: `https://www.dailymotion.com/embed/video/${dm[1]}` };

  // 7. To'g'ridan-to'g'ri video fayl (.mp4, .webm, .ogg, .m3u8)
  if (/\.(mp4|webm|ogg|mov|avi)(\?|$|#)/i.test(url)) {
    return { type: 'video', src: url };
  }

  // 8. Boshqa URL — iframe ichida sinab ko'ramiz
  return { type: 'iframe_unsafe', src: url };
}

function applyVideo(url) {
  if (!url) return;
  lastVideoUrl = url;
  const pw = document.getElementById('player-wrap');

  const parsed = parseVideoUrl(url);
  let embed = '';

  if (parsed.type === 'video') {
    embed = `<video src="${parsed.src}" controls autoplay playsinline style="width:100%;height:100%;background:#000"></video>`;
  } else if (parsed.type === 'iframe') {
    embed = `<iframe src="${parsed.src}" allow="autoplay;encrypted-media;fullscreen;picture-in-picture" allowfullscreen frameborder="0"></iframe>`;
  } else {
    // iframe_unsafe — boshqa saytlar (X-Frame-Options bloklanishi mumkin)
    embed = `<div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;background:#0d0d1a;color:#aaa;text-align:center;padding:24px">
      <div style="font-size:40px">🎬</div>
      <p style="font-size:14px">Bu sayt iframe ichida ochilmaydi.<br>To'g'ridan-to'g'ri brauzerda oching:</p>
      <a href="${url}" target="_blank" style="background:#7c3aed;color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none;font-size:13px">🔗 Videoni ochish</a>
      <p style="font-size:11px;color:#555;margin-top:8px">Yoki YouTube, Google Drive, VK, Rutube linklaridan foydalaning</p>
    </div>`;
  }

  pw.innerHTML = embed;
  document.getElementById('url-input').value = url;
}

// ─── Chat ────────────────────────────────────────────────────────────────────
function addMsg(name, text, mine) {
  const box  = document.getElementById('messages');
  const div  = document.createElement('div');
  div.className = 'msg' + (mine ? ' me' : '');
  div.innerHTML  = `<div class="who">${name}</div><div class="txt">${text}</div>`;
  box.appendChild(div);
  box.scrollTop = box.scrollHeight;
}

function sendChat() {
  const inp = document.getElementById('chat-input');
  const txt = inp.value.trim();
  if (!txt) return;
  inp.value = '';
  addMsg('Siz', txt, true);
  post('chat', {name: myName, text: txt});
}

// ─── Poll server ─────────────────────────────────────────────────────────────
async function poll() {
  try {
    const r = await fetch(syncUrl + '&since=' + lastTs + '&action=get');
    const d = await r.json();
    if (!d.ok) return;

    // New video URL
    if (d.video_url && d.video_url !== lastVideoUrl) {
      applyVideo(d.video_url);
    }

    // New messages
    if (d.messages && d.messages.length) {
      d.messages.forEach(m => {
        if (m.t > lastTs) {
          if (m.name !== myName) addMsg(m.name, m.text, false);
          lastTs = Math.max(lastTs, m.t);
        }
      });
    }

    // WebRTC signals
    if (d.signals && d.signals.length) {
      for (const s of d.signals) {
        if (s.from !== myId) await handleSignal(s.signal);
      }
      if (d.signals.length) post('clear_signals', {});
    }

  } catch(e) {}
}

// ─── WebRTC mic ──────────────────────────────────────────────────────────────
const iceConf = {iceServers:[{urls:'stun:stun.l.google.com:19302'}]};

async function toggleMic() {
  const btn = document.getElementById('mic-btn');
  if (micActive) {
    micActive = false;
    if (localStream) { localStream.getTracks().forEach(t=>t.stop()); localStream=null; }
    if (pc) { pc.close(); pc=null; }
    btn.textContent = '🎤 Mikrofon';
    btn.classList.remove('on');
    document.getElementById('status').textContent = 'Mikrofon o\'chirildi';
    return;
  }
  try {
    localStream = await navigator.mediaDevices.getUserMedia({audio:true, video:false});
    micActive = true;
    btn.classList.add('on');
    btn.textContent = 'Mikrofon (yoqiq)';
    document.getElementById('status').textContent = '🎤 Mikrofon yoqiq';
    await startRTC();
  } catch(e) {
    alert('Mikrofonga ruxsat kerak!');
  }
}

async function startRTC() {
  pc = new RTCPeerConnection(iceConf);
  if (localStream) localStream.getTracks().forEach(t=>pc.addTrack(t, localStream));

  pc.ontrack = e => {
    const aud = new Audio();
    aud.srcObject = e.streams[0];
    aud.play().catch(()=>{});
    document.getElementById('partner-status').textContent = '🟢 Do\'st ulangan';
  };

  pc.onicecandidate = e => {
    if (e.candidate) sendSignal({type:'candidate', candidate:e.candidate});
  };

  // Caller: create offer
  signalRole = 'caller';
  const offer = await pc.createOffer();
  await pc.setLocalDescription(offer);
  sendSignal({type:'offer', sdp:offer});
}

async function handleSignal(sig) {
  if (!sig) return;
  if (sig.type === 'offer') {
    if (!pc) {
      pc = new RTCPeerConnection(iceConf);
      if (localStream) localStream.getTracks().forEach(t=>pc.addTrack(t,localStream));
      pc.ontrack = e => {
        const aud = new Audio();
        aud.srcObject = e.streams[0];
        aud.play().catch(()=>{});
        document.getElementById('partner-status').textContent = '🟢 Do\'st ulangan';
      };
      pc.onicecandidate = e => {
        if (e.candidate) sendSignal({type:'candidate', candidate:e.candidate});
      };
    }
    await pc.setRemoteDescription(new RTCSessionDescription(sig.sdp));
    const answer = await pc.createAnswer();
    await pc.setLocalDescription(answer);
    sendSignal({type:'answer', sdp:answer});
    signalRole = 'callee';
  } else if (sig.type === 'answer' && pc) {
    await pc.setRemoteDescription(new RTCSessionDescription(sig.sdp)).catch(()=>{});
  } else if (sig.type === 'candidate' && pc) {
    await pc.addIceCandidate(new RTCIceCandidate(sig.candidate)).catch(()=>{});
  }
}

function sendSignal(signal) {
  post('signal', {from: myId, signal: JSON.stringify(signal)});
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function post(action, data) {
  const body = new URLSearchParams({action, token, ...data});
  return fetch(syncUrl.replace('?token='+encodeURIComponent(token),''), {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: body.toString()
  });
}

// ─── Start polling ────────────────────────────────────────────────────────────
setInterval(poll, 2000);
poll();
</script>
</body>
</html>
