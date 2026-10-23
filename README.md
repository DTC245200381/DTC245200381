# Đề tài 29 — Website Công thức Nấu ăn

## 1. Công nghệ
- Website PHP 8.3 + MySQL 8.4
- Nginx reverse proxy, header bảo mật
- phpMyAdmin
- Prometheus, Grafana, Nginx Prometheus Exporter, MySQL Exporter, cAdvisor
- Loki + Promtail
- Docker Compose trên Windows + Docker Desktop

## 2. Chạy trên Windows (PowerShell trong VS Code)
1. Cài Docker Desktop, Git for Windows và VS Code.
2. Sao chép `.env.example` thành `.env`.
3. Mở `.env`, đổi tất cả giá trị mật khẩu thành mật khẩu mạnh, không dùng dấu cách.
4. Chạy `docker compose config` để kiểm tra cấu hình.
5. Chạy `docker compose up -d --build`.
6. Chạy `docker compose ps` và đợi MySQL healthy.
7. Mở các địa chỉ trong mục 3.

## 3. Địa chỉ
- Website: http://localhost:8088
- phpMyAdmin: http://localhost:8080
- Prometheus: http://localhost:9090
- Grafana: http://localhost:3000
- Loki: chỉ truy cập nội bộ qua Grafana Explore hoặc mạng Docker

## 4. Tài khoản
- phpMyAdmin: dùng `recipe_user` và `DB_PASSWORD` trong `.env`; không cần đăng nhập root.
- Grafana: dùng `GRAFANA_USER` và `GRAFANA_PASSWORD` trong `.env`.
- Không commit file `.env` lên GitHub.

## 5. Lệnh kiểm tra
```powershell
docker compose config
docker compose ps
docker compose logs --tail 100
docker compose logs nginx
docker compose logs web
docker compose logs mysql
docker compose logs promtail
docker compose logs loki
```

## 6. Kiểm tra metrics
Mở http://localhost:9090/targets. Các job nginx, mysql, containers và prometheus nên ở trạng thái UP.
Mở http://localhost:3000, đăng nhập, vào Explore. Chọn Prometheus và thử:
- `up`
- `nginx_connections_active`
- `mysql_up`
- `container_memory_usage_bytes`

## 7. Truy vấn log LogQL
Trong Grafana → Explore → chọn Loki:
```logql
{service="nginx"}
{service="web"}
{service="mysql"} |= "error"
```
Tên label thực tế có thể thay đổi theo phiên bản Docker Desktop và metadata Promtail. Nếu không có dữ liệu, kiểm tra `docker compose logs promtail` và `docker compose logs loki`.

## 8. Ba commit gợi ý
Sau khi đã kiểm tra từng phần:
```powershell
git init
git add .
git commit -m "Commit 1: Xay dung website va co so du lieu"

git add nginx docker-compose.yml
git commit -m "Commit 2: Cau hinh Nginx reverse proxy va monitoring"

git add prometheus grafana loki promtail README.md
git commit -m "Commit 3: Bo sung logging, hardening va tai lieu"
```
Trước khi commit, kiểm tra `git status` và chắc chắn `.env` không bị theo dõi.

## 9. Lưu ý bảo mật và giới hạn
- Các cổng host được bind vào `127.0.0.1` để chỉ truy cập từ máy local.
- MySQL không publish cổng ra Windows; chỉ các mạng Docker được khai báo mới truy cập được.
- Đổi mật khẩu mặc định trong `.env` trước khi chạy.
- Tài khoản exporter được tạo bởi `database/02-create-exporter.sh` trong lần khởi tạo MySQL đầu tiên. Đặt mật khẩu `EXPORTER_PASSWORD` chỉ gồm chữ và số để tránh lỗi escape SQL. Các script trong `docker-entrypoint-initdb.d` chỉ chạy khi volume MySQL được khởi tạo lần đầu; nếu cần chạy lại từ đầu, sao lưu dữ liệu rồi mới cân nhắc `docker compose down -v`.
- cAdvisor và Docker socket có quyền quan sát hệ thống khá rộng. Đây là môi trường học tập chạy local, không nên công khai các cổng ra Internet. Với triển khai thực tế cần đánh giá lại quyền và cấu hình.
- `docker compose down` dừng và xóa container/network nhưng giữ dữ liệu trong volume. `docker compose down -v` sẽ xóa cả dữ liệu cơ sở dữ liệu và dữ liệu giám sát.
- HTTPS tự ký chưa được bật trong cấu hình cơ bản này; có thể bổ sung ở bước nâng cao sau khi toàn bộ hệ thống chạy ổn định.
