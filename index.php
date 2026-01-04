<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photo Booth Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>

<body>

    <div class="app-card">
        <div class="header">
            <h1>Photo Booth</h1>
            <p>Studio chụp ảnh tự động</p>
        </div>

        <div id="screen-1" class="screen active">
            <div class="camera-frame">
                <video id="video" autoplay playsinline></video>
                <div id="countdown" class="countdown-overlay">5</div>
                <div id="btn-manual" class="btn-snap" onclick="manualSnap()">
                    <i class="fas fa-camera"></i>
                </div>
            </div>

            <div style="margin-top: 25px; text-align: center;">
                <p id="status-text" style="color:#666; margin: 0 0 15px 0;">Sẵn sàng chụp 10 tấm (5s/tấm)</p>
                <button id="btn-start" class="btn btn-primary" onclick="startProcess()">
                    Bắt đầu chụp <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <div id="screen-2" class="screen">
            <div style="text-align: center; margin-bottom: 15px;">
                <h3 style="margin: 0; color: #333;">Chọn 4 tấm ưng ý</h3>
                <p style="margin: 5px 0 0 0; color: #888; font-size: 0.9rem;">
                    Đã chọn: <strong id="sel-count" style="color:var(--primary)">0</strong>/4
                </p>
            </div>

            <div class="gallery-wrapper" id="gallery"></div>

            <div style="display: flex; gap: 15px;">
                <button class="btn btn-outline" onclick="location.reload()">
                    <i class="fas fa-redo"></i> Chụp lại
                </button>
                <button id="btn-next" class="btn btn-primary" disabled onclick="toEditor()">
                    Tiếp tục <i class="fas fa-magic"></i>
                </button>
            </div>
        </div>

        <div id="screen-3" class="screen">
            <div class="editor-layout">
                <div class="preview-col">
                    <canvas id="canvas-final"
                        style="max-height: 100%; max-width: 100%; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-radius: 4px;"></canvas>
                </div>

                <div class="tools-col">
                    <div class="tool-group">
                        <h5><i class="fas fa-th-large"></i> Bố cục</h5>
                        <div class="btn-opt-row">
                            <div class="btn-opt active layout-opt" onclick="setLayout('grid', this)">
                                <i class="fas fa-border-all"></i> Grid 2x2
                            </div>
                            <div class="btn-opt layout-opt" onclick="setLayout('strip', this)">
                                <i class="fas fa-grip-lines-vertical"></i> Dọc 1x4
                            </div>
                        </div>
                    </div>

                    <div class="tool-group">
                        <h5><i class="fas fa-magic"></i> Bộ lọc màu</h5>
                        <div class="btn-opt-row">
                            <div class="btn-opt active filter-opt" onclick="setFilter('none', this)">Gốc</div>
                            <div class="btn-opt filter-opt" onclick="setFilter('grayscale(100%)', this)">B&W</div>
                            <div class="btn-opt filter-opt" onclick="setFilter('sepia(50%)', this)">Phim</div>
                            <div class="btn-opt filter-opt"
                                onclick="setFilter('contrast(110%) brightness(110%)', this)">Tươi</div>
                        </div>
                    </div>

                    <div class="tool-group">
                        <h5><i class="fas fa-palette"></i> Màu nền</h5>
                        <div class="color-row">
                            <div class="color-cir active" style="background:#fff" onclick="setColor('#fff', this)">
                            </div>
                            <div class="color-cir" style="background:#2d3436" onclick="setColor('#2d3436', this)"></div>
                            <div class="color-cir" style="background:#ff9ff3" onclick="setColor('#ff9ff3', this)"></div>
                            <div class="color-cir" style="background:#54a0ff" onclick="setColor('#54a0ff', this)"></div>
                            <div class="color-cir" style="background:#feca57" onclick="setColor('#feca57', this)"></div>
                        </div>
                    </div>

                    <div style="margin-top: auto;">
                        <button class="btn btn-accent" style="width: 100%;" onclick="processDownload()">
                            <i class="fas fa-download"></i> Xong & Tải về
                        </button>
                        <button class="btn btn-outline" style="width: 100%; margin-top: 10px;"
                            onclick="switchScreen('screen-2')">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="screen-4" class="screen">
            <h2 style="color: var(--primary-dark); margin-bottom: 20px;">Ảnh của bạn đây!</h2>
            <img id="img-result">
            <div style="display: flex; gap: 15px;">
                <a id="link-download" href="#" class="btn btn-accent" download>
                    <i class="fas fa-download"></i> Lưu về máy
                </a>
                <button class="btn btn-outline" onclick="location.reload()">
                    <i class="fas fa-camera"></i> Chụp lượt mới
                </button>
            </div>
        </div>
    </div>

    <script>
    // --- CẤU HÌNH ---
    const TIME_PER_SHOT = 5;
    const TOTAL_SHOTS = 10;

    // --- BIẾN ---
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
    // Cấu hình mặc định: có thêm filter
    let conf = {
        layout: 'grid',
        color: '#fff',
        filter: 'none'
    };
    let tmrInterval, tmrResolve;

    // 1. INIT CAMERA (Ưu tiên 4:3)
    navigator.mediaDevices.getUserMedia({
            video: {
                width: {
                    ideal: 1280
                },
                height: {
                    ideal: 960
                },
                aspectRatio: {
                    ideal: 1.3333
                }
            }
        })
        .then(s => {
            stream = s;
            vid.srcObject = s;
        })
        .catch(e => {
            navigator.mediaDevices.getUserMedia({
                video: true
            }).then(s => {
                stream = s;
                vid.srcObject = s;
            });
        });

    // 2. CHỤP ẢNH
    async function startProcess() {
        document.getElementById('btn-start').style.display = 'none';
        photos = [];

        for (let i = 1; i <= TOTAL_SHOTS; i++) {
            statusTxt.innerText = `Đang chụp tấm ${i}/${TOTAL_SHOTS}`;
            btnMan.style.display = 'flex';

            await runTimer(TIME_PER_SHOT);

            btnMan.style.display = 'none';

            // Capture
            cvsTemp.width = vid.videoWidth;
            cvsTemp.height = vid.videoHeight;
            const c = cvsTemp.getContext('2d');
            c.translate(cvsTemp.width, 0);
            c.scale(-1, 1);
            c.drawImage(vid, 0, 0);
            photos.push(cvsTemp.toDataURL('image/png'));

            if (i < TOTAL_SHOTS) {
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
            cnt.style.display = 'block';
            cnt.innerText = t;
            tmrInterval = setInterval(() => {
                t--;
                if (t <= 0) stopTimer();
                else cnt.innerText = t;
            }, 1000);
        });
    }

    function manualSnap() {
        if (tmrResolve) stopTimer();
    }

    function stopTimer() {
        clearInterval(tmrInterval);
        cnt.style.display = 'none';
        if (tmrResolve) {
            tmrResolve();
            tmrResolve = null;
        }
    }

    // 3. CHỌN ẢNH
    function renderGallery() {
        const g = document.getElementById('gallery');
        g.innerHTML = '';
        photos.forEach((src, i) => {
            const d = document.createElement('div');
            d.className = 'photo-thumb' + (selected.includes(i) ? ' selected' : '');
            d.innerHTML = `<img src="${src}"><div class="badge-check"><i class="fas fa-check"></i></div>`;
            d.onclick = () => {
                if (selected.includes(i)) selected = selected.filter(x => x !== i);
                else if (selected.length < 4) selected.push(i);
                renderGallery();
            };
            g.appendChild(d);
        });
        document.getElementById('sel-count').innerText = selected.length;
        document.getElementById('btn-next').disabled = (selected.length !== 4);
    }

    // 4. EDITOR & FILTER LOGIC
    function toEditor() {
        switchScreen('screen-3');
        drawCanvas();
    }

    // Các hàm chọn cấu hình
    function setLayout(l, el) {
        conf.layout = l;
        activeBtn('.layout-opt', el);
        drawCanvas();
    }

    function setFilter(f, el) {
        conf.filter = f;
        activeBtn('.filter-opt', el);
        drawCanvas();
    }

    function setColor(c, el) {
        conf.color = c;
        activeBtn('.color-cir', el);
        drawCanvas();
    }

    function activeBtn(sel, el) {
        document.querySelectorAll(sel).forEach(x => x.classList.remove('active'));
        el.classList.add('active');
    }

    // HÀM VẼ CHÍNH (XỬ LÝ FILTER + MÉO ẢNH)
    async function drawCanvas() {
        const wImg = 400,
            hImg = 300;
        const gap = 20,
            pad = 40;
        let w, h, pos;

        if (conf.layout === 'grid') {
            w = wImg * 2 + gap + pad * 2;
            h = hImg * 2 + gap + pad * 2 + 80;
            pos = [{
                x: pad,
                y: pad
            }, {
                x: pad + wImg + gap,
                y: pad
            }, {
                x: pad,
                y: pad + hImg + gap
            }, {
                x: pad + wImg + gap,
                y: pad + hImg + gap
            }];
        } else {
            w = wImg + pad * 2;
            h = hImg * 4 + gap * 3 + pad * 2 + 80;
            pos = [{
                x: pad,
                y: pad
            }, {
                x: pad,
                y: pad + hImg + gap
            }, {
                x: pad,
                y: pad + (hImg + gap) * 2
            }, {
                x: pad,
                y: pad + (hImg + gap) * 3
            }];
        }

        cvsFinal.width = w;
        cvsFinal.height = h;

        // Vẽ nền
        ctx.fillStyle = conf.color;
        ctx.fillRect(0, 0, w, h);

        // Vẽ ảnh
        for (let i = 0; i < 4; i++) {
            const img = await new Promise(r => {
                const im = new Image();
                im.onload = () => r(im);
                im.src = photos[selected[i]];
            });

            // --- ÁP DỤNG FILTER ---
            ctx.save();
            ctx.filter = conf.filter; // Áp dụng filter CSS (grayscale, sepia...)

            // Vẽ ảnh (không bị méo)
            drawImageCover(ctx, img, pos[i].x, pos[i].y, wImg, hImg);

            ctx.restore(); // Xóa filter để không ảnh hưởng viền
            // ---------------------

            // Viền mỏng
            ctx.strokeStyle = "rgba(0,0,0,0.05)";
            ctx.lineWidth = 1;
            ctx.strokeRect(pos[i].x, pos[i].y, wImg, hImg);
        }

        // Footer
        ctx.fillStyle = (['#000', '#000000', '#2d3436'].includes(conf.color)) ? '#fff' : '#333';
        ctx.textAlign = 'center';
        ctx.font = 'bold 24px Quicksand';
        ctx.fillText("PHOTO BOOTH", w / 2, h - 50);
        ctx.font = '16px Quicksand';
        ctx.fillText(new Date().toLocaleDateString('vi-VN'), w / 2, h - 25);
    }

    // Hàm cắt ảnh Center Crop
    function drawImageCover(ctx, img, x, y, w, h) {
        const ratioW = w / h;
        const ratioImg = img.width / img.height;
        let sx, sy, sWidth, sHeight;

        if (ratioImg > ratioW) {
            sHeight = img.height;
            sWidth = img.height * ratioW;
            sx = (img.width - sWidth) / 2;
            sy = 0;
        } else {
            sWidth = img.width;
            sHeight = img.width / ratioW;
            sx = 0;
            sy = (img.height - sHeight) / 2;
        }
        ctx.drawImage(img, sx, sy, sWidth, sHeight, x, y, w, h);
    }

    // 5. DOWNLOAD
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
    </script>

</body>

</html>