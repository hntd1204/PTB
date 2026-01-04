<?php
// Nhận dữ liệu JSON gửi lên
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['image'])) {
    $img = $data['image']; // Chuỗi base64: "data:image/png;base64,AAAFBfj42P..."

    // Loại bỏ phần header của base64 (data:image/png;base64,)
    $img = str_replace('data:image/png;base64,', '', $img);
    $img = str_replace(' ', '+', $img);

    // Giải mã thành binary
    $fileData = base64_decode($img);

    // Tạo tên file ngẫu nhiên
    $fileName = 'photo_' . time() . '_' . uniqid() . '.png';
    $filePath = 'uploads/' . $fileName;

    // Lưu file vào thư mục uploads
    if (file_put_contents($filePath, $fileData)) {
        echo json_encode([
            "success" => true,
            "filepath" => $filePath,
            "message" => "Lưu ảnh thành công!"
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Không thể ghi file. Kiểm tra quyền thư mục uploads."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Không có dữ liệu ảnh."]);
}
