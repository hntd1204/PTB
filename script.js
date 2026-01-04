// --- CẤU HÌNH ---
const TIME_PER_SHOT = 5; 
const TOTAL_SHOTS = 10;

// --- BIẾN TOÀN CỤC ---
let stream;
const vid = document.getElementById('video');
const cnt = document.getElementById('countdown');
const statusTxt = document.getElementById('status-text');
const btnMan = document.getElementById('btn-manual');
const cvsTemp = document.createElement('canvas');
const cvsFinal = document.getElementById('canvas-final');
const ctx = cvsFinal.getContext('2d');

let photos = []; 
let selected = []; 
let loadedImages = []; // Cache ảnh đã load
let conf = { layout: 'grid', color: '#fff', filter: 'none' };
let overlayItems = []; // Sticker/Text/Logo
let tmrInterval, tmrResolve;

// Âm thanh Web Audio API
const audioCtx = new (window.AudioContext || window.webkitAudioContext)();

// --- SOUND EFFECTS ---
function playBeep() {
    if(audioCtx.state === 'suspended') audioCtx.resume();
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.connect(gain); gain.connect(audioCtx.destination);
    osc.frequency.value = 800; 
    osc.type = 'sine';
    gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + 0.1);
    osc.start(); osc.stop(audioCtx.currentTime + 0.1);
}

function playShutter() {
    if(audioCtx.state === 'suspended') audioCtx.resume();
    const bufferSize = audioCtx.sampleRate * 0.1; 
    const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
    const data = buffer.getChannelData(0);
    for (let i = 0; i < bufferSize; i++) data[i] = Math.random() * 2 - 1;
    
    const noise = audioCtx.createBufferSource();
    noise.buffer = buffer;
    const gain = audioCtx.createGain();
    gain.gain.setValueAtTime(0.5, audioCtx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.1);
    
    noise.connect(gain); gain.connect(audioCtx.destination);
    noise.start();
}

// --- INIT CAMERA ---
// Khởi chạy ngay khi load trang
window.addEventListener('load', () => {
    navigator.mediaDevices.getUserMedia({
        video: { width: { ideal: 1280 }, height: { ideal: 960 }, aspectRatio: { ideal: 1.3333 }}
    }).then(s => { stream = s; vid.srcObject = s; })
      .catch(e => {
          // Fallback nếu không được 4:3
          navigator.mediaDevices.getUserMedia({video: true}).then(s => { stream = s; vid.srcObject = s; });
      });
});

// --- QUY TRÌNH CHỤP ---
async function startProcess() {
    if(audioCtx.state === 'suspended') audioCtx.resume();
    document.getElementById('btn-start').style.display = 'none';
    photos = [];

    for (let i = 1; i <= TOTAL_SHOTS; i++) {
        statusTxt.innerText = `Đang chụp tấm ${i}/${TOTAL_SHOTS}`;
        btnMan.style.display = 'flex';
        
        await runTimer(TIME_PER_SHOT);
        
        btnMan.style.display = 'none';
        playShutter();
        
        // Capture
        cvsTemp.width = vid.videoWidth; cvsTemp.height = vid.videoHeight;
        const c = cvsTemp.getContext('2d');
        c.translate(cvsTemp.width, 0); c.scale(-1, 1);
        c.drawImage(vid, 0, 0);
        photos.push(cvsTemp.toDataURL('image/png'));

        if(i < TOTAL_SHOTS) {
            statusTxt.innerText = "Chờ chút...";
            await new Promise(r => setTimeout(r, 600));
        }
    }
    
    switchScreen('screen-2');
    renderGallery();
}

function runTimer(sec) {
    return new Promise(resolve => {
        tmrResolve = resolve;
        let t = sec;
        cnt.style.display = 'block'; cnt.innerText = t;
        playBeep();
        
        tmrInterval = setInterval(() => {
            t--;
            if(t <= 0) stopTimer();
            else { cnt.innerText = t; playBeep(); }
        }, 1000);
    });
}

function manualSnap() { if(tmrResolve) stopTimer(); }

function stopTimer() {
    clearInterval(tmrInterval);
    cnt.style.display = 'none';
    if(tmrResolve) { tmrResolve(); tmrResolve = null; }
}

// --- GALLERY ---
function renderGallery() {
    const g = document.getElementById('gallery'); g.innerHTML = '';
    photos.forEach((src, i) => {
        const d = document.createElement('div');
        d.className = 'photo-thumb' + (selected.includes(i) ? ' selected' : '');
        d.innerHTML = `<img src="${src}"><div class="badge-check"><i class="fas fa-check"></i></div>`;
        d.onclick = () => {
            if(selected.includes(i)) selected = selected.filter(x => x !== i);
            else if(selected.length < 4) selected.push(i);
            renderGallery();
        };
        g.appendChild(d);
    });
    document.getElementById('sel-count').innerText = selected.length;
    document.getElementById('btn-next').disabled = (selected.length !== 4);
}

// --- EDITOR & PRELOAD ---
async function toEditor() {
    switchScreen('screen-3');
    await preloadSelectedImages(); // Load ảnh trước để tránh lag khi kéo thả
    drawCanvas(); 
}

function preloadSelectedImages() {
    return new Promise(async (resolve) => {
        loadedImages = [];
        for(let i=0; i<4; i++) {
            const img = new Image();
            img.src = photos[selected[i]];
            await new Promise(r => img.onload = r);
            loadedImages.push(img);
        }
        resolve();
    });
}

// --- DRAWING CANVAS ---
function drawCanvas() {
    const wImg=400, hImg=300; 
    const gap=20, pad=40;
    let w, h, pos;

    if (conf.layout === 'grid') {
        w = wImg*2 + gap + pad*2; h = hImg*2 + gap + pad*2 + 80;
        pos = [{x:pad, y:pad}, {x:pad+wImg+gap, y:pad}, {x:pad, y:pad+hImg+gap}, {x:pad+wImg+gap, y:pad+hImg+gap}];
    } else {
        w = wImg + pad*2; h = hImg*4 + gap*3 + pad*2 + 80;
        pos = [{x:pad, y:pad}, {x:pad, y:pad+hImg+gap}, {x:pad, y:pad+(hImg+gap)*2}, {x:pad, y:pad+(hImg+gap)*3}];
    }

    cvsFinal.width = w; cvsFinal.height = h;

    // 1. Background
    ctx.fillStyle = conf.color; ctx.fillRect(0, 0, w, h);

    // 2. Photos (Dùng ảnh đã cache)
    for(let i=0; i<loadedImages.length; i++) {
        const img = loadedImages[i];
        
        ctx.save();
        ctx.filter = conf.filter;
        drawImageCover(ctx, img, pos[i].x, pos[i].y, wImg, hImg);
        ctx.restore();
        
        ctx.strokeStyle = "rgba(0,0,0,0.05)"; ctx.lineWidth = 1; ctx.strokeRect(pos[i].x, pos[i].y, wImg, hImg);
    }

    // 3. Footer
    ctx.fillStyle = (['#000','#000000','#2d3436'].includes(conf.color)) ? '#fff' : '#333';
    ctx.textAlign = 'center'; ctx.font = 'bold 24px Quicksand';
    ctx.fillText("PHOTO BOOTH", w/2, h - 50);
    ctx.font = '16px Quicksand';
    ctx.fillText(new Date().toLocaleDateString('vi-VN'), w/2, h - 25);

    // 4. Overlays (Sticker/Text/Logo)
    overlayItems.forEach(item => {
        if(item.type === 'text') {
            ctx.font = item.isEmoji ? `${item.size}px serif` : `bold ${item.size}px Quicksand`;
            ctx.fillStyle = item.isEmoji ? '#000' : (['#000','#000000'].includes(conf.color) ? '#fff' : '#ff0055');
            ctx.textAlign = 'center';
            ctx.fillText(item.content, item.x, item.y);
        } else if(item.type === 'image') {
            ctx.drawImage(item.img, item.x, item.y, item.w, item.h);
        }
    });
}

function drawImageCover(ctx, img, x, y, w, h) {
    const ratioW = w / h; const ratioImg = img.width / img.height;
    let sx, sy, sWidth, sHeight;
    if (ratioImg > ratioW) { sHeight = img.height; sWidth = img.height * ratioW; sx = (img.width - sWidth) / 2; sy = 0; }
    else { sWidth = img.width; sHeight = img.width / ratioW; sx = 0; sy = (img.height - sHeight) / 2; }
    ctx.drawImage(img, sx, sy, sWidth, sHeight, x, y, w, h);
}

// --- TOOLS: LAYOUT, COLOR, FILTER ---
function setLayout(l, el) { conf.layout = l; activeBtn('.layout-opt', el); drawCanvas(); }
function setFilter(f, el) { conf.filter = f; activeBtn('.filter-opt', el); drawCanvas(); }
function setColor(c, el) { conf.color = c; activeBtn('.color-cir', el); drawCanvas(); }
function activeBtn(sel, el) { document.querySelectorAll(sel).forEach(x=>x.classList.remove('active')); el.classList.add('active'); }

// --- TOOLS: STICKER & LOGO ---
function addSticker(emoji) {
    overlayItems.push({ type: 'text', content: emoji, x: cvsFinal.width/2, y: cvsFinal.height/2, size: 50, isEmoji: true });
    drawCanvas();
}
function addTextSticker() {
    const inp = document.getElementById('inp-text');
    const txt = inp.value.trim();
    if(txt) {
        overlayItems.push({ type: 'text', content: txt, x: cvsFinal.width/2, y: cvsFinal.height/2 + 50, size: 30, isEmoji: false });
        inp.value = '';
        drawCanvas();
    }
}
function uploadLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = () => {
                // Resize logo nếu quá to
                let w = 100; let h = 100 * (img.height/img.width);
                overlayItems.push({ type: 'image', img: img, x: 50, y: cvsFinal.height - 150, w: w, h: h });
                drawCanvas();
            };
            img.src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
function clearStickers() { overlayItems = []; drawCanvas(); }

// --- DRAG & DROP LOGIC ---
let isDragging = false;
let dragIndex = -1;

cvsFinal.addEventListener('mousedown', (e) => {
    const rect = cvsFinal.getBoundingClientRect();
    const mouseX = (e.clientX - rect.left) * (cvsFinal.width / rect.width);
    const mouseY = (e.clientY - rect.top) * (cvsFinal.height / rect.height);
    
    // Check từ trên xuống để lấy item nằm trên cùng
    for(let i = overlayItems.length - 1; i >= 0; i--) {
        const item = overlayItems[i];
        let hit = false;
        if(item.type === 'text') {
            const w = item.size * (item.content.length || 1); 
            const h = item.size;
            // Vùng ước lượng text
            if(mouseX >= item.x - w && mouseX <= item.x + w && mouseY >= item.y - h && mouseY <= item.y + h) hit = true;
        } else {
            if(mouseX >= item.x && mouseX <= item.x + item.w && mouseY >= item.y && mouseY <= item.y + item.h) hit = true;
        }
        if(hit) { isDragging = true; dragIndex = i; break; }
    }
});

cvsFinal.addEventListener('mousemove', (e) => {
    if(!isDragging || dragIndex === -1) return;
    const rect = cvsFinal.getBoundingClientRect();
    const mouseX = (e.clientX - rect.left) * (cvsFinal.width / rect.width);
    const mouseY = (e.clientY - rect.top) * (cvsFinal.height / rect.height);
    
    const item = overlayItems[dragIndex];
    if(item.type === 'text') { item.x = mouseX; item.y = mouseY; }
    else { item.x = mouseX - item.w/2; item.y = mouseY - item.h/2; }
    
    drawCanvas(); // Vẽ lại ngay lập tức (mượt vì ảnh đã cache)
});

window.addEventListener('mouseup', () => { isDragging = false; dragIndex = -1; });

// --- UTILS & DOWNLOAD ---
function processDownload() {
    const url = cvsFinal.toDataURL('image/png');
    document.getElementById('img-result').src = url;
    const link = document.getElementById('link-download');
    link.href = url;
    link.download = `PhotoBooth_${Date.now()}.png`;
    switchScreen('screen-4');
}

function switchScreen(id) {
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
}