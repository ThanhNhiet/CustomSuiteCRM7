# SuiteCRM 7 Custom Extensions - Hướng dẫn cài đặt

Hệ thống custom API cho SuiteCRM 7 với OAuth2, push notifications và mobile integration.

## 🚀 Cài đặt nhanh

### 📦 Bước 1: Giải nén và ghi đè

```bash
# Giải nén file zip vào thư mục SuiteCRM (GHI ĐÈ thư mục custom/)
unzip -o suitecrm-custom.zip -d /path/to/suitecrm7/

# Hoặc: Copy toàn bộ files vào custom/ (ghi đè hoàn toàn)
```

**Files được giữ lại**:
- `custom/modules/*/logic_hooks.php` - Logic hooks module-specific
- Các thư mục auto-generated sẽ bị bỏ qua: `application/Ext/*`, `Extension/`, `history/`, `working/`

**Files bị ghi đè**:
- `custom/modules/logic_hooks.php` - Có chứa Push Notification Hook

**Hành động cần chú ý**:
- Copy `custom/Api/index.php` sang root project có thể backup lại file `Api/index.php` cũ cho chắc chắn

### 🔑 Bước 2: OAuth2 Client (BẮT BUỘC)

**2.1. Tạo private key và public key:**

Nguồn tham khảo https://docs.suitecrm.com/developer/api/developer-setup-guide/json-api

```bash
cd your-path\xampp\apache\bin
```

```bash
openssl genrsa -out private.key 2048
```
Lệnh này có nghĩa là "Sử dụng OpenSSL để tạo (gen) một khóa RSA".
- openssl: Tên của công cụ bạn đang dùng.
- genrsa: Lệnh con, là viết tắt của "Generate RSA" (Tạo khóa RSA).
- -out private.key: Chỉ định file đầu ra (output). Kết quả của lệnh này (chính là khóa riêng tư) sẽ được lưu vào một tệp có tên là private.key.
- 2048: Đây là độ dài của khóa (key length), tính bằng bit. 2048 bit là độ dài tiêu chuẩn, an toàn cho hầu hết các ứng dụng hiện nay. Khóa càng dài, càng an toàn, nhưng cũng xử lý chậm hơn một chút.

```bash
openssl rsa -in private.key -pubout -out public.key
```
Lệnh này có nghĩa là "Sử dụng công cụ quản lý RSA của OpenSSL để xử lý một khóa".
- openssl: Tên công cụ.
- rsa: Lệnh con, dùng để đọc, xử lý hoặc chuyển đổi các khóa RSA đã có.
- -in private.key: Chỉ định tệp đầu vào (input). Nó sẽ đọc tệp private.key mà bạn vừa tạo.
- -pubout: Đây là tùy chọn quan trọng nhất, là viết tắt của "Public Key Output". Nó ra lệnh "Tôi không muốn xem toàn bộ khóa, hãy chỉ trích xuất phần công khai ra thôi".
- -out public.key: Chỉ định file đầu ra (output). Phần khóa công khai được trích xuất sẽ được lưu vào tệp public.key.
- Kết quả: Bạn có một tệp public.key chỉ chứa thông tin công khai, được trích xuất từ tệp private.key.

**2.2. Tạo OAuth2 Client:**
1. **SuiteCRM Admin** → `OAuth2 Clients and Tokens`
2. **New Password Client:**
   - Name: `Mobile App Client`
   - Is Confidential: ✅ **Yes**  
   - Grant Types: `password`
3. **Lưu** và copy **Client ID** + **Client Secret**

**2.3. Lưu credentials:** 

**Tạo file (Nên làm khi muốn test để tiết kiệm thời gian):** `custom/public/data/client_secret.json`
```json
{
    "client_id": "297f6b87-63a7-1711-35ee-686228178a94",
    "client_secret": "your-actual-oauth2-client-secret"
}
```

**Hoặc qua API:**
```bash
POST {website}/Api/V8/custom/setup/save-secret/{admin_id}
Authorization: Oauth + access_token
Content-Type: application/json

{
    "client_id": "your-client-id",
    "client_secret": "your-client-secret"
}
```

### 📋 Bước 3: Module Permissions (Tuỳ chọn)

**Tạo file:** `custom/public/data/list_of_modules.json`
```json
{
    "Accounts": {
        "label": "Accounts",
        "access": ["access", "view", "list", "edit", "delete", "import", "export", "massupdate"]
    },
    "Contacts": {
        "label": "Contacts",
        "access": ["access", "view", "list", "edit", "delete", "import", "export", "massupdate"]
    },
    "Opportunities": {
        "label": "Opportunities", 
        "access": ["access", "view", "list", "edit", "delete", "import", "export", "massupdate"]
    },
    "Tasks": {
        "label": "Tasks",
        "access": ["access", "view", "list", "edit", "delete", "import", "export", "massupdate"]
    }
}
```

**Hoặc qua API:**
```bash
POST {website}/Api/V8/custom/setup/save-modules-list/{admin_id}
Authorization: Oauth + access_token
Content-Type: application/json

# Body: JSON object với modules như trên
```


### ✅ Bước 4: Kiểm tra

```bash
# 1. Test OAuth credentials
GET {website}/custom/public/api/get_secret.php
# Response: {"client_id": "...", "client_secret": "..."}

# 2. Test modules config  
GET {website}/Api/V8/custom/setup/get-modules-list
# (Cần OAuth token)
```

### Nếu không dùng được api, hãy Rebuild Cache

**Admin Panel:**
- `Admin` → `Repair` → `Quick Repair and Rebuild` → **Execute All**


## 📱 Sử dụng APIs (dành cho phát triển)

### Lấy OAuth Token (nếu muốn dùng các api đang được bảo vệ)

```javascript
// 1. Lấy credentials từ SuiteCRM
const { client_id, client_secret } = await fetch('/custom/public/api/get_secret.php').then(r => r.json());

// 2. Authenticate user
const tokenResponse = await fetch('/Api/access_token', {
    method: 'POST',
    headers: {'Content-Type': 'application/vnd.api+json'},
    body: JSON.stringify({
        grant_type: 'password',
        client_id, client_secret,
        username: 'admin',
        password: 'password',
        scope: ''
    })
});

const { access_token } = await tokenResponse.json();

// 3. Sử dụng token cho API calls
const data = await fetch('/Api/V8/custom/Accounts', {
    headers: {'Authorization': `Bearer ${access_token}`}
}).then(r => r.json());
```

### Đăng ký Push Token

```javascript
// Đăng ký device cho push notifications
const response = await fetch('/Api/V8/custom/expo-token/save', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${access_token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        user_id: 'user-guid',
        expo_token: 'ExponentPushToken[xxxxxx]',
        platform: 'ios' // hoặc 'android', 'web'  
    })
});

// Response: {status: 'saved'|'updated'|'already_exists', id: 'guid'}
```

---

## 🔔 Push Notifications

**Tự động gửi khi:**
1. ✅ Module `Alerts` được tạo/cập nhật
2. ✅ `assigned_user_id` ≠ `created_by`  
3. ✅ User có push token trong database
4. ✅ `assigned_user_id` thay đổi (nếu update)

**Notification format:**
```json
{
    "title": "New notification sent to you",
    "body": "Alert: [name]\nTarget: [module]\nDetails: [description]\nTarget ID: [id]",
    "data": {
        "module": "Accounts",
        "targetId": "record-guid", 
        "type": "crm_notification"
    }
}
```

---

## 🌐 API Endpoints Chính

### Public APIs (không cần OAuth)
- `GET /custom/public/api/get_secret.php` - Lấy OAuth credentials
- `GET /custom/public/api/get_languages.php` - Danh sách languages

### Protected APIs (cần OAuth Bearer token)
- `GET /Api/V8/custom/{module}?q=search` - Tìm kiếm records
- `GET /Api/V8/custom/{module}/list-fields` - Fields cho list view
- `GET /Api/V8/custom/{module}/detail-fields` - Fields cho detail view  
- `GET /Api/V8/custom/enum/{module}?fields=status,type` - Enum options
- `GET /Api/V8/custom/relate/{module}?fields=parent_type` - Relate data
- `GET /Api/V8/custom/system/language/lang=vi_vn` - System language
- `POST /Api/V8/custom/expo-token/save` - Đăng ký push token
- `GET /Api/V8/custom/expo-token/{user_id}` - Lấy push token

### Admin APIs (cần admin user)
- `POST /Api/V8/custom/setup/save-secret/{admin_user_id}` - Lưu OAuth config
- `POST /Api/V8/custom/setup/save-modules-list/{admin_user_id}` - Lưu module permissions

---

**Version:** 1.0.0 | **SuiteCRM:** 7.x | **Updated:** 26/01/2025
