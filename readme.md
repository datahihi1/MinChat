# MinChat

MinChat là ứng dụng chat realtime đơn giản sử dụng PHP, SQLite và AJAX long-polling. Dự án này phù hợp cho demo, học tập hoặc làm nền tảng chat nội bộ nhỏ.

## Tính năng

- Đăng ký, đăng nhập, xác minh tài khoản qua email
- Chat realtime (long-polling AJAX, không cần WebSocket)
- Lưu trữ tin nhắn và người dùng bằng SQLite
- Giao diện responsive, hỗ trợ trình duyệt cũ (Safari iOS6, Chrome 30+)
- Ghi nhớ đăng nhập bằng cookie
- Reset mật khẩu qua email (giả lập)

## Cài đặt

1. **Yêu cầu:**
   - PHP >= 7.2
   - SQLite3 extension
   - Máy chủ web (Apache, Nginx, hoặc dùng [Laragon](https://laragon.org/))

2. **Clone dự án:**

3. **Khởi tạo database:**
   - Chạy file `pdo_sqlite.php` để tạo các bảng cần thiết:
     ```
     php pdo_sqlite.php
     ```

4. **Cấu hình máy chủ:**
   - Đặt thư mục dự án vào thư mục web (VD: `c:\laragon\www\MinChat`)
   - Truy cập qua trình duyệt:  
     ```
     http://localhost/MinChat/
     ```

## Cấu trúc thư mục

- `index.php` — Trang chat chính
- `login.php` — Đăng nhập
- `register.php` — Đăng ký
- `verify.php` — Xác minh/reset tài khoản
- `api.php` — API xử lý gửi/lấy tin nhắn
- `pdo_sqlite.php` — Script khởi tạo database
- `messages.db` — File SQLite database

## Lưu ý

- Chức năng gửi email xác minh/reset mật khẩu chỉ là giả lập (`mail()`), cần cấu hình SMTP thực tế nếu dùng ngoài demo.
- Không khuyến nghị dùng cho sản phẩm lớn hoặc môi trường production.

## Tác giả

- Github: [datahihi1](https://github.com/datahihi1)
- Dự án mẫu cho học tập PHP + SQLite + AJAX

---