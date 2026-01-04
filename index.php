<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pro Photobooth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="container py-4">
        <div class="glass-card">

            <div id="camera-screen">
                <div class="row">
                    <div class="col-md-8">
                        <div class="video-wrapper">
                            <video id="video" autoplay playsinline></video>
                            <div id="frame-overlay" class="frame-overlay"></div>
                            <div id="flash" class="flash-overlay"></div>
                            <div id="countdown">3</div>
                        </div>
                    </div>

                    <div class="col-md-4 text-center d-flex flex-column justify-content-center">
                        <h3 class="mb-3 text-white">Chọn khung ảnh</h3>
                        <div class="d-flex justify-content-center gap-2 mb-4">
                            <button class="btn btn-outline-light" onclick="setFrame(null)">Không khung</button>
                            <button class="btn btn-outline-light" onclick="setFrame('frames/frame1.png')">Khung Hồng</button>
                            <button class="btn btn-outline-light" onclick="setFrame('frames/frame2.png')">Khung Đen</button>
                        </div>

                        <h3 class="mb-3 text-white">Chế độ chụp</h3>
                        <button id="btn-single" class="btn btn-light btn-lg mb-2 w-100 rounded-pill">
                            <i class="fas fa-camera"></i> Chụp 1 tấm
                        </button>
                        <button id="btn-burst" class="btn btn-warning btn-lg w-100 rounded-pill fw-bold">
                            <i class="fas fa-images"></i> Chụp liên hoàn (10 tấm)
                        </button>
                    </div>
                </div>
            </div>

            <div id="selection-screen" style="display:none;">
                <h3 class="text-center text-white mb-3">
                    Chọn <span id="count-selected" class="text-warning">0</span> tấm ảnh ưng ý nhất
                </h3>
                <div class="gallery-grid" id="gallery">
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-secondary me-2" onclick="resetApp()">Chụp lại từ đầu</button>
                    <button class="btn btn-success btn-lg" onclick="processCollage()">
                        <i class="fas fa-magic"></i> Ghép ảnh & Tải về
                    </button>
                </div>
            </div>

            <div id="result-screen" class="text-center" style="display:none;">
                <h3 class="text-white mb-3">Tác phẩm của bạn!</h3>
                <img id="final-result" class="img-fluid border border-3 border-white rounded shadow mb-3" style="max-height: 80vh;">
                <div>
                    <a id="download-btn" href="#" class="btn btn-light btn-lg" download="photobooth.png">
                        <i class="fas fa-download"></i> Lưu về máy
                    </a>
                    <button class="btn btn-outline-light btn-lg ms-2" onclick="resetApp()">Làm mới</button>
                </div>
            </div>

        </div>
        <canvas id="canvas" style="display:none;"></canvas>
        <canvas id="collage-canvas" style="display:none;"></canvas>
    </div>

    <script>
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const collageCanvas = document.getElementById('collage-canvas');
        const overlay = document.getElementById('frame-overlay');
        const gallery = document.getElementById('gallery');
        const countdownEl = document.getElementById('countdown');

        let currentFrame = null; // Đường dẫn ảnh khung
        let capturedPhotos = []; // Mảng chứa 10 ảnh gốc
        let selectedIndices = []; // Mảng chứa index các ảnh được chọn

        // 1. Khởi động Camera
        navigator.mediaDevices.getUserMedia({
                video: {
                    width: 1280,
                    height: 720
                }
            })
            .then(stream => video.srcObject = stream)
            .catch(err => alert("Lỗi Camera: " + err));

        // 2. Chức năng chọn khung
        function setFrame(path) {
            currentFrame = path;
            if (path) {
                overlay.style.backgroundImage = `url('${path}')`;
            } else {
                overlay.style.backgroundImage = 'none';
            }
        }

        // 3. Chức năng chụp 1 tấm (Cơ bản)
        document.getElementById('btn-single').addEventListener('click', () => {
            takeShot().then(imgData => {
                // Nếu có khung, cần ghép khung ngay lập tức (Code đơn giản hóa: coi như chụp xong)
                saveToServer(imgData);
            });
        });

        // 4. CHỨC NĂNG CHỤP LIÊN HOÀN (BURST MODE)
        document.getElementById('btn-burst').addEventListener('click', async () => {
            capturedPhotos = []; // Reset
            selectedIndices = [];

            // Đếm ngược 3s trước khi bắt đầu
            await runCountdown(3);

            // Chụp 10 tấm, mỗi tấm cách nhau 1 giây
            for (let i = 1; i <= 10; i++) {
                flashEffect();
                const imgData = await takeShot(false); // false = không ghép khung ngay
                capturedPhotos.push(imgData);

                // Hiển thị đếm ngược nhỏ hoặc thông báo
                countdownEl.innerText = `${i}/10`;
                countdownEl.style.display = 'block';
                await new Promise(r => setTimeout(r, 800)); // Đợi 0.8s
            }
            countdownEl.style.display = 'none';

            // Chuyển sang màn hình chọn ảnh
            showSelectionScreen();
        });

        // Hàm chụp ảnh từ video -> base64
        function takeShot(applyFrame = false) {
            return new Promise(resolve => {
                const ctx = canvas.getContext('2d');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;

                // Lật ảnh
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                // Reset transform để vẽ khung (nếu cần)
                ctx.setTransform(1, 0, 0, 1, 0, 0);

                if (applyFrame && currentFrame) {
                    const frameImg = new Image();
                    frameImg.src = currentFrame;
                    frameImg.onload = () => {
                        ctx.drawImage(frameImg, 0, 0, canvas.width, canvas.height);
                        resolve(canvas.toDataURL('image/png'));
                    };
                    frameImg.onerror = () => resolve(canvas.toDataURL('image/png')); // Lỗi ảnh thì trả về ảnh gốc
                } else {
                    resolve(canvas.toDataURL('image/png'));
                }
            });
        }

        // 5. Hiển thị màn hình chọn ảnh
        function showSelectionScreen() {
            document.getElementById('camera-screen').style.display = 'none';
            document.getElementById('selection-screen').style.display = 'block';
            gallery.innerHTML = '';

            capturedPhotos.forEach((src, index) => {
                const div = document.createElement('div');
                div.className = 'gallery-item';
                div.innerHTML = `<img src="${src}">`;
                div.onclick = () => toggleSelect(div, index);
                gallery.appendChild(div);
            });
            updateCount();
        }

        function toggleSelect(el, index) {
            if (selectedIndices.includes(index)) {
                // Bỏ chọn
                selectedIndices = selectedIndices.filter(i => i !== index);
                el.classList.remove('selected');
            } else {
                // Chọn mới (Chỉ cho phép tối đa 8)
                if (selectedIndices.length < 8) {
                    selectedIndices.push(index);
                    el.classList.add('selected');
                } else {
                    alert("Chỉ được chọn tối đa 8 tấm thôi nhé!");
                }
            }
            updateCount();
        }

        function updateCount() {
            document.getElementById('count-selected').innerText = selectedIndices.length;
        }

        // 6. GHÉP ẢNH (COLLAGE)
        function processCollage() {
            if (selectedIndices.length === 0) {
                alert("Hãy chọn ít nhất 1 tấm ảnh!");
                return;
            }

            const ctx = collageCanvas.getContext('2d');
            const imgCount = selectedIndices.length;

            // Cấu hình kích thước (Ví dụ layout 2 cột)
            const singleW = 400;
            const singleH = 300; // Tỉ lệ 4:3
            const gap = 20;
            const cols = 2;
            const rows = Math.ceil(imgCount / cols);

            // Tính toán kích thước canvas tổng
            const totalW = (singleW * cols) + (gap * (cols + 1));
            const totalH = (singleH * rows) + (gap * (rows + 1)) + 100; // +100px cho footer (ngày tháng)

            collageCanvas.width = totalW;
            collageCanvas.height = totalH;

            // Vẽ nền trắng
            ctx.fillStyle = "#ffffff";
            ctx.fillRect(0, 0, totalW, totalH);

            // Tải và vẽ từng ảnh
            let loaded = 0;
            selectedIndices.forEach((imgIndex, i) => {
                const img = new Image();
                img.src = capturedPhotos[imgIndex];
                img.onload = () => {
                    const col = i % cols;
                    const row = Math.floor(i / cols);
                    const x = gap + (col * (singleW + gap));
                    const y = gap + (row * (singleH + gap));

                    // Vẽ ảnh chụp
                    ctx.drawImage(img, x, y, singleW, singleH);

                    // Nếu có khung đã chọn ban đầu, vẽ đè khung lên từng ảnh nhỏ (Optional)
                    // Ở đây ta bỏ qua vẽ khung lên từng ảnh nhỏ cho đơn giản, 
                    // hoặc bạn có thể vẽ một khung viền đơn giản:
                    ctx.lineWidth = 5;
                    ctx.strokeStyle = "#333";
                    ctx.strokeRect(x, y, singleW, singleH);

                    loaded++;
                    if (loaded === imgCount) {
                        finalizeCollage(ctx, totalW, totalH);
                    }
                };
            });
        }

        function finalizeCollage(ctx, w, h) {
            // Thêm chữ ký / ngày tháng
            ctx.fillStyle = "#000";
            ctx.font = "bold 30px Arial";
            ctx.textAlign = "center";
            ctx.fillText("MY PHOTOBOOTH - " + new Date().toLocaleDateString(), w / 2, h - 40);

            // Xuất ra ảnh cuối cùng
            const finalData = collageCanvas.toDataURL('image/png');
            saveToServer(finalData);
        }

        // 7. Gửi về Server và hiện kết quả
        function saveToServer(base64) {
            fetch('save.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        image: base64
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('selection-screen').style.display = 'none';
                        document.getElementById('camera-screen').style.display = 'none';
                        document.getElementById('result-screen').style.display = 'block';

                        document.getElementById('final-result').src = data.filepath;
                        document.getElementById('download-btn').href = data.filepath;
                    }
                });
        }

        // Tiện ích
        function flashEffect() {
            const flash = document.getElementById('flash');
            flash.style.opacity = 1;
            setTimeout(() => flash.style.opacity = 0, 100);
        }

        function runCountdown(seconds) {
            return new Promise(resolve => {
                countdownEl.style.display = 'block';
                let c = seconds;
                countdownEl.innerText = c;
                const timer = setInterval(() => {
                    c--;
                    if (c <= 0) {
                        clearInterval(timer);
                        countdownEl.style.display = 'none';
                        resolve();
                    } else {
                        countdownEl.innerText = c;
                    }
                }, 1000);
            });
        }

        function resetApp() {
            location.reload();
        }
    </script>

</body>

</html>             resolve();
                } else {
                    countdownEl.innerText = c;
                }
            }, 1000);
        });
    }

    function resetApp() {
        location.reload();
    }
    </script>

</body>

</html>