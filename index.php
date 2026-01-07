<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photo Booth Pro Studio</title>
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

                <div class="cam-tools">
                    <button class="btn-icon-circle" onclick="toggleMirror()" title="Lật camera">
                        <i class="fas fa-exchange-alt"></i>
                    </button>
                </div>
                <div id="countdown" class="countdown-overlay">5</div>
                <div id="btn-manual" class="btn-snap" onclick="manualSnap()">
                    <i class="fas fa-camera"></i>
                </div>
            </div>

            <div style="margin-top: 20px; text-align: center;">
                <div class="timer-opts">
                    <button class="btn-timer" onclick="setTimer(3, this)">3 giây</button>
                    <button class="btn-timer active" onclick="setTimer(5, this)">5 giây</button>
                    <button class="btn-timer" onclick="setTimer(10, this)">10 giây</button>
                </div>

                <p id="status-text" style="color:#666; margin: 0 0 10px 0; font-size: 0.9rem;">
                    Sẵn sàng chụp 10 tấm
                </p>

                <button id="btn-start" class="btn btn-primary" onclick="startProcess()"
                    style="width: auto; padding: 12px 40px;">
                    Bắt đầu <i class="fas fa-play"></i>
                </button>
            </div>
        </div>

        <div id="screen-2" class="screen">
            <div style="text-align: center; margin-bottom: 10px;">
                <h3 style="margin: 0; color: #333;">Chọn 4 tấm</h3>
                <p style="margin: 2px 0 0 0; color: #888; font-size: 0.9rem;">
                    Đã chọn: <strong id="sel-count" style="color:var(--primary)">0</strong>/4
                </p>
            </div>

            <div class="gallery-wrapper" id="gallery"></div>

            <div style="display: flex; gap: 15px; width: 100%; max-width: 400px;">
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
                    <canvas id="canvas-final"></canvas>
                    <div
                        style="position: absolute; bottom: 10px; font-size: 0.8rem; color: #888; pointer-events: none;">
                        * Kéo thả để di chuyển Sticker/Chữ
                    </div>
                </div>

                <div class="tools-col">
                    <div class="tool-section">
                        <h5><i class="fas fa-layer-group"></i> Giao diện</h5>
                        <div class="btn-opt-row mb-2" style="margin-bottom: 8px;">
                            <div class="btn-opt active layout-opt" onclick="setLayout('grid', this)">Grid 2x2</div>
                            <div class="btn-opt layout-opt" onclick="setLayout('strip', this)">Dọc 1x4</div>
                        </div>
                        <div class="color-row">
                            <div class="color-cir active" style="background:#fff" onclick="setColor('#fff', this)">
                            </div>
                            <div class="color-cir" style="background:#2d3436" onclick="setColor('#2d3436', this)"></div>
                            <div class="color-cir" style="background:#ff9ff3" onclick="setColor('#ff9ff3', this)"></div>
                            <div class="color-cir" style="background:#54a0ff" onclick="setColor('#54a0ff', this)"></div>
                            <div class="color-cir" style="background:#feca57" onclick="setColor('#feca57', this)"></div>
                        </div>
                    </div>

                    <div class="tool-section">
                        <h5><i class="fas fa-magic"></i> Bộ lọc màu</h5>
                        <div class="btn-opt-row">
                            <div class="btn-opt active filter-opt" onclick="setFilter('none', this)">Gốc</div>
                            <div class="btn-opt filter-opt" onclick="setFilter('grayscale(100%)', this)">B&W</div>
                            <div class="btn-opt filter-opt" onclick="setFilter('sepia(50%)', this)">Phim</div>
                            <div class="btn-opt filter-opt"
                                onclick="setFilter('contrast(110%) brightness(110%)', this)">Tươi</div>
                        </div>
                    </div>

                    <div class="tool-section">
                        <h5><i class="fas fa-icons"></i> Thêm Sticker</h5>
                        <div class="sticker-grid">
                            <div class="sticker-item" onclick="addSticker('❤️')">❤️</div>
                            <div class="sticker-item" onclick="addSticker('⭐')">⭐</div>
                            <div class="sticker-item" onclick="addSticker('🎀')">🎀</div>
                            <div class="sticker-item" onclick="addSticker('🔥')">🔥</div>
                            <div class="sticker-item" onclick="addSticker('👑')">👑</div>
                            <div class="sticker-item" onclick="addSticker('📷')">📷</div>
                            <div class="sticker-item" onclick="addSticker('🌸')">🌸</div>
                            <div class="sticker-item" onclick="addSticker('😎')">😎</div>
                            <div class="sticker-item" onclick="addSticker('✨')">✨</div>
                            <div class="sticker-item" onclick="addSticker('🐱')">🐱</div>
                        </div>
                        <button class="btn btn-outline btn-sm" onclick="clearStickers()"
                            style="margin-top: 5px; width: 100%;">
                            <i class="fas fa-trash"></i> Xóa hết Sticker
                        </button>
                    </div>

                    <div class="tool-section">
                        <h5><i class="fas fa-signature"></i> Chữ & Logo</h5>
                        <div class="input-group" style="margin-bottom: 8px;">
                            <input type="text" id="inp-text" class="form-control" placeholder="Nhập tên/lời chúc...">
                            <button class="btn btn-primary btn-sm" onclick="addTextSticker()"
                                style="width: auto;">Thêm</button>
                        </div>
                        <div>
                            <label class="btn btn-outline btn-sm" style="width: 100%; cursor: pointer;">
                                <i class="fas fa-upload"></i> Tải ảnh Logo lên
                                <input type="file" hidden accept="image/*" onchange="uploadLogo(this)">
                            </label>
                        </div>
                    </div>

                    <div style="margin-top: auto;">
                        <button class="btn btn-accent" onclick="processDownload()">
                            <i class="fas fa-download"></i> Xong & Tải về
                        </button>
                        <button class="btn btn-outline" style="margin-top: 8px;" onclick="switchScreen('screen-2')">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="screen-4" class="screen">
            <h2 style="color: var(--primary-dark); margin-bottom: 15px;">Thành quả của bạn!</h2>
            <img id="img-result">
            <div style="display: flex; gap: 15px; width: 100%; max-width: 400px;">
                <a id="link-download" href="#" class="btn btn-accent" download>
                    <i class="fas fa-download"></i> Lưu về máy
                </a>
                <button class="btn btn-outline" onclick="location.reload()">
                    <i class="fas fa-camera"></i> Mới
                </button>
            </div>
        </div>
    </div>

    <script src="script.js?v=<?php echo time(); ?>"></script>
    <div id="flash-effect" class="flash-overlay"></div>

</body>

</html>