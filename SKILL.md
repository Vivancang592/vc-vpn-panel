---
name: kira-ai-api
description: Hướng dẫn tích hợp Kira AI API (Chat OpenAI-compatible, tạo ảnh và tạo video) vào các dự án NodeJS, PHP, Python và WordPress. Sử dụng skill này khi cần viết code gọi API của Kira AI.
---

# 🤖 Kira AI API Integration Skill

Skill này cung cấp các tiêu chuẩn kết nối, cấu trúc Payload, danh sách Model và các mẫu code tối ưu (NodeJS, PHP, Python, WordPress) để lập trình viên dễ dàng tích hợp dịch vụ **Kira AI** vào mọi hệ thống.

---

## 🎯 Khi nào nên sử dụng Skill này

- Khi dự án cần tích hợp tính năng **Trò chuyện (Chat / Assistant)** hoặc **Sinh nội dung tự động**.
- Khi dự án cần tính năng **Tạo ảnh nghệ thuật / Hình ảnh minh họa** (Featured Image, Banner, Inline Content Images).
- Khi dự án cần tính năng **Tạo video ngắn từ văn bản**.
- Khi tích hợp Kira AI vào các nền tảng quản trị nội dung như **WordPress**, **Laravel**, hoặc **NodeJS App**.

---

## 🔑 Thông tin API & Xác thực

- **Base URL chính thức**: `https://kiraai.vn`
- **Phương thức xác thực**: Gửi API Key qua Header `Authorization` dưới dạng Bearer Token:
  ```http
  Authorization: Bearer YOUR_KIRA_API_KEY
  ```

### 📋 Danh sách Models được hỗ trợ

| Model ID | Loại Model | Tính năng nổi bật |
| :--- | :--- | :--- |
| **`kira-mini-1.0`** | Chat / Text | Model AI miễn phí, đa năng, phù hợp cho hội thoại hàng ngày. Không tốn lượt token. |
| **`kira-3.5-pro`** | Chat / Text | Kira 3.5 Pro nhắm thẳng vào tốc độ phản hồi cực nhanh, khả năng lập trình chuyên sâu. |
| **`kira-3.5-flash`** | Chat / Text | Mô hình mặc định, cực kỳ đa năng, tốc độ phản hồi nhanh như chớp và thông minh vượt trội. |
| **`kira-2.5-pro`** | Chat / Text | Dòng mô hình pro, phù hợp cho hầu hết mọi nhu cầu lập trình, xử lý văn bản phức tạp. |
| **`kira-2.5-flash`** | Chat / Text | Dòng mô hình ổn định lâu dài, phù hợp cho hầu hết mọi nhu cầu xử lý thông tin thông thường và tạo giọng nói. |
| **`kira-3.0-image`** | Image | Tạo hình ảnh nghệ thuật tốc độ cao, cực nhanh, tối ưu tốt cho phác thảo ý tưởng. |
| **`kira-2.0-image`** | Image | Tạo hình ảnh nghệ thuật ổn định lâu dài, tối ưu tốt cho phác thảo ý tưởng. |
| **`kira-3.0-video`** | Video | Dựng chuyển động video độ phân giải cao và chân thực từ mô tả văn bản (LRO). |
| **`kira-3.0-video-flash`** | Video | Mô hình video tiên tiến, có khả năng tạo video dài 10s và chỉnh sửa video tiên tiến. |
| **`kira-3.0-flash-tts`** | Audio / TTS | Mô hình chuyển đổi giọng đọc thế hệ mới nhất, tối ưu ngữ điệu và phát âm tiếng Việt chuẩn phòng thu. |
| **`kira-2.0-flash-tts`** | Audio / TTS | Mô hình chuyển đổi văn bản thành giọng đọc tự nhiên (TTS), tối ưu tốc độ phản hồi. |

---

## 💻 Mẫu code tích hợp (Code Patterns)

### 1. NodeJS / JavaScript (Fetch & EventSource)
Chuẩn kết nối tiêu chuẩn hỗ trợ cả chế độ không Stream và Stream (Server-Sent Events) giúp hiển thị kết quả chữ chạy mượt mà theo thời gian thực.

```javascript
/**
 * Gọi Kira AI Chat Completions API
 * 
 * @param {string} baseUrl     - URL máy chủ Kira AI (ví dụ: 'https://kiraai.vn')
 * @param {string} apiKey      - Kira AI Developer API Key
 * @param {object} params      - Các tham số: messages, model, stream, temperature, max_tokens
 */
async function callKiraAIChat(baseUrl, apiKey, {
  messages,
  model = 'kira-3.5-flash',
  stream = false,
  temperature = 0.7,
  max_tokens = 4096
}) {
  const endpoint = `${baseUrl}/api/v1/chat/completions`;
  const payload = {
    model,
    messages,
    stream,
    temperature,
    max_tokens
  };

  const response = await fetch(endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${apiKey}`
    },
    body: JSON.stringify(payload)
  });

  if (!response.ok) {
    const errorData = await response.json();
    throw new Error(errorData.error?.message || `API Error Status: ${response.status}`);
  }

  // 1. Xử lý phản hồi Stream (Server-Sent Events)
  if (stream) {
    const reader = response.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let buffer = '';

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;

      buffer += decoder.decode(value, { stream: true });
      const lines = buffer.split('\n');
      buffer = lines.pop(); // Giữ lại dòng dang dở cuối cùng trong buffer

      for (const line of lines) {
        const trimmed = line.trim();
        if (!trimmed || !trimmed.startsWith('data: ')) continue;
        
        const dataStr = trimmed.substring(6);
        if (dataStr === '[DONE]') continue;

        try {
          const parsed = JSON.parse(dataStr);
          const chunkText = parsed.choices?.[0]?.delta?.content;
          if (chunkText) {
            // Callback hiển thị nội dung trực tiếp ra UI (ví dụ console.log hoặc render ra màn hình)
            process.stdout.write(chunkText);
          }
        } catch (e) {
          // Bỏ qua lỗi parse dòng SSE chưa hoàn chỉnh
        }
      }
    }
  } 
  // 2. Xử lý phản hồi thường (Non-stream)
  else {
    const data = await response.json();
    return data.choices[0].message.content;
  }
}
```

---

### 2. PHP / WordPress (Sử dụng `wp_remote_post`)
Hoàn hảo để viết các plugin WordPress tự động sinh bài viết, gợi ý tiêu đề, hoặc vẽ ảnh minh họa trực tiếp.

```php
/**
 * Gọi Kira AI Chat API trong WordPress
 *
 * @param string $prompt       Câu lệnh gửi cho AI
 * @param string $api_key      Kira AI API Key
 * @param string $system_msg   Chỉ thị hệ thống (System Prompt)
 * @param string $base_url     Base URL của máy chủ Kira AI
 * @return string|WP_Error     Nội dung trả về hoặc lỗi WP_Error
 */
function wp_call_kira_ai_chat( $prompt, $api_key, $system_msg = 'Bạn là chuyên gia SEO viết bài', $base_url = 'https://kiraai.vn' ) {
    $endpoint = rtrim( $base_url, '/' ) . '/api/v1/chat/completions';
    
    // Xây dựng mảng hội thoại đúng chuẩn OpenAI
    $messages = [];
    if ( ! empty( $system_msg ) ) {
        $messages[] = [
            'role'    => 'system',
            'content' => $system_msg
        ];
    }
    $messages[] = [
        'role'    => 'user',
        'content' => $prompt
    ];

    $payload = [
        'model'       => 'kira-3.5-flash',
        'messages'    => $messages,
        'stream'      => false,
        'temperature' => 0.7
    ];

    $response = wp_remote_post( $endpoint, [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ],
        'body'    => wp_json_encode( $payload ),
        'timeout' => 90 // Đề xuất timeout cao để hỗ trợ sinh bài viết dài
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( isset( $data['error'] ) ) {
        return new WP_Error( 'kira_ai_error', $data['error']['message'] ?? 'Lỗi không xác định từ Kira AI.' );
    }

    return $data['choices'][0]['message']['content'] ?? '';
}
```

---

### 3. Python (Requests & Sinh ảnh / Video minh họa)
Thích hợp cho các Script tự động hóa, xử lý dữ liệu hàng loạt hoặc AI agent.

```python
import requests
import time

class KiraAIClient:
    def __init__(self, api_key, base_url="https://kiraai.vn"):
        self.api_key = api_key
        self.base_url = base_url.rstrip('/')
        self.headers = {
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json"
        }

    def generate_image(self, prompt, aspect_ratio="16:9", model="kira-3.0-image"):
        """
        Sinh ảnh từ văn bản bằng Imagen thông qua Kira AI.
        Trả về ảnh định dạng Base64 và thông tin MimeType.
        """
        url = f"{self.base_url}/api/v1/images/generations"
        payload = {
            "prompt": prompt,
            "model": model,
            "aspect_ratio": aspect_ratio
        }
        
        response = requests.post(url, json=payload, headers=self.headers, timeout=60)
        response.raise_for_status()
        data = response.json()
        
        # Lấy hình ảnh đầu tiên trong mảng dữ liệu trả về
        image_info = data.get("data", [])[0]
        return image_info["b64_json"], image_info["mime_type"]

    def generate_video_sync(self, prompt, aspect_ratio="16:9", duration_seconds=6, model="kira-3.0-video"):
        """
        Sinh video ngắn từ văn bản. Do sinh video là tác vụ chạy ngầm (async),
        hàm này sẽ tự động gọi API kiểm tra trạng thái (polling) cho tới khi hoàn tất.
        """
        # 1. Khởi tạo tác vụ sinh video
        init_url = f"{self.base_url}/api/v1/videos/generations"
        payload = {
            "prompt": prompt,
            "aspect_ratio": aspect_ratio,
            "duration_seconds": duration_seconds,
            "model": model
        }
        
        res = requests.post(init_url, json=payload, headers=self.headers, timeout=30)
        res.raise_for_status()
        operation = res.json()
        
        op_id = operation["id"]
        status = operation["status"]
        
        print(f"Bắt đầu khởi tạo Video. ID tiến trình: {op_id}. Đang xử lý...")
        
        # 2. Polling kiểm tra trạng thái
        status_url = f"{self.base_url}/api/v1/videos/operations/{op_id}"
        while True:
            time.sleep(10) # Chờ 10 giây mỗi lượt check
            check_res = requests.get(status_url, headers=self.headers, timeout=20)
            check_res.raise_for_status()
            status_data = check_res.json()
            
            if status_data.get("status") == "completed":
                print("Video đã sinh thành công!")
                video_info = status_data.get("data", [])[0]
                return video_info["b64_json"], video_info["mime_type"]
            elif status_data.get("status") == "failed":
                raise Exception(f"Sinh video thất bại: {status_data.get('error')}")
            else:
                print("Đang xử lý video...")
```

### 4. Gửi yêu cầu Multimodal (Phân tích hình ảnh)

Các model sinh text như `kira-3.5-flash` hỗ trợ xử lý hình ảnh (Multimodal). Bạn có thể gửi yêu cầu phân tích ảnh theo một trong hai định dạng sau:

#### Cách 1: Chuẩn OpenAI Multimodal (Khuyên dùng)
Truyền `content` dưới dạng mảng các phần tử text và `image_url` chứa chuỗi Base64 dưới dạng Data URI:

```javascript
const response = await fetch('https://kiraai.vn/api/v1/chat/completions', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer YOUR_KIRA_API_KEY'
  },
  body: JSON.stringify({
    model: 'kira-3.5-flash',
    messages: [
      {
        role: 'user',
        content: [
          {
            type: 'text',
            text: 'Hãy mô tả chi tiết bức ảnh này.'
          },
          {
            type: 'image_url',
            image_url: {
              url: 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQE...' // Data URI base64 của ảnh
            }
          }
        ]
      }
    ]
  })
});
const data = await response.json();
console.log(data.choices[0].message.content);
```

#### Cách 2: Chuẩn Gemini-native (Dạng parts)
Kira AI cũng hỗ trợ cấu trúc `parts` và `inlineData` gốc của Gemini trực tiếp trong mảng `messages`:

```json
{
  "model": "kira-3.5-flash",
  "messages": [
    {
      "role": "user",
      "parts": [
        {
          "text": "Hãy mô tả hình ảnh này..."
        },
        {
          "inlineData": {
            "mimeType": "image/jpeg",
            "data": "/9j/4AAQSkZJRgABAQE..."
          }
        }
      ]
    }
  ]
}
```

---

### 5. Hướng dẫn sử dụng Text to Speech API (TTS)

API Text-to-Speech của Kira AI tương thích hoàn toàn với chuẩn OpenAI tại endpoint `/api/v1/audio/speech`.

#### Tham số chính:
- **Model**: `kira-3.0-flash-tts` hoặc `kira-2.0-flash-tts`
- **Voice**: `alloy` (Kore), `echo` (Fenrir), `fable` (Puck), `onyx` (Charon), `nova` (Aoede)
- **Input**: Đoạn văn bản cần chuyển đổi thành âm thanh.

#### NodeJS / JavaScript Example:
```javascript
import fs from "fs";
import OpenAI from "openai";

const openai = new OpenAI({
  baseURL: "https://kiraai.vn/api/v1",
  apiKey: "YOUR_KIRA_API_KEY"
});

const mp3 = await openai.audio.speech.create({
  model: "kira-3.0-flash-tts",
  voice: "alloy",
  input: "Chào mừng bạn đến với hệ sinh thái trí tuệ nhân tạo Kira AI."
});

const buffer = Buffer.from(await mp3.arrayBuffer());
await fs.promises.writeFile("output.mp3", buffer);
```

#### Python Example:
```python
import requests

url = "https://kiraai.vn/api/v1/audio/speech"
headers = {
    "Authorization": "Bearer YOUR_KIRA_API_KEY",
    "Content-Type": "application/json"
}
payload = {
    "model": "kira-3.0-flash-tts",
    "input": "Chào mừng bạn đến với hệ sinh thái trí tuệ nhân tạo Kira AI.",
    "voice": "alloy"
}

response = requests.post(url, json=payload, headers=headers)
with open("output.mp3", "wb") as f:
    f.write(response.content)
```
---

### 6. Lấy Danh sách mô hình (Models List API)

API này cho phép lập trình viên truy vấn danh sách toàn bộ các mô hình đang hoạt động trên hệ thống Kira AI, bao gồm thông tin về loại mô hình, trạng thái, tính năng và giá cả.

* **Endpoint:** `GET /api/v1/models`
* **Xác thực:** Không bắt buộc (API công khai) hoặc truyền Header `Authorization: Bearer YOUR_KIRA_API_KEY`
* **Phản hồi mẫu:**
  ```json
  {
    "object": "list",
    "data": [
      {
        "id": "kira-3.5-flash",
        "object": "model",
        "created": 1782806352,
        "owned_by": "kira-ai",
        "type": "chat",
        "is_free": false,
        "is_partner": true,
        "name": "Kira 3.5 Pro",
        "description": "Model nhanh nhất, tối ưu SEO vượt trội.",
        "tags": ["PRO", "VIP"],
        "status": "active",
        "price_input_vnd": 20000,
        "price_output_vnd": 150000,
        "icon_url": "https://kiraai.vn/images/logo.png",
        "usage_count": 45
      }
    ]
  }
  ```

---

### 7. Lấy danh sách giọng đọc của Model TTS (Voices List API)

API này cung cấp danh sách toàn bộ các giọng đọc được hỗ trợ trên hệ thống, bao gồm giới tính, ngôn ngữ và ánh xạ tương ứng của chúng.

* **Endpoint:** `GET /api/v1/audio/voices`
* **Xác thực:** Không bắt buộc (API công khai)
* **Phản hồi mẫu:**
  ```json
  {
    "object": "list",
    "data": [
      {
        "id": "alloy",
        "name": "Alloy",
        "mapped_to": "Kore",
        "gender": "Nữ (Female)",
        "description": "Giọng nữ miền Bắc tự nhiên, nhẹ nhàng, tốc độ vừa phải.",
        "language": "vi-VN"
      },
      {
        "id": "echo",
        "name": "Echo",
        "mapped_to": "Fenrir",
        "gender": "Nam (Male)",
        "description": "Giọng nam miền Bắc trầm ấm, rõ ràng, truyền cảm.",
        "language": "vi-VN"
      }
    ]
  }
  ```

---

## 🔒 Bảo mật & Quy tắc Vàng (Golden Rules)

1. **🔒 Tuyệt đối không để lộ API Key ở Client-side:**
   Không bao giờ lưu API Key hoặc thực hiện gọi trực tiếp từ giao diện Web phía Client (React, Vue, HTML/JS thuần) không có lớp bảo vệ. Luôn định tuyến các yêu cầu thông qua Backend trung gian (Proxy/Controller) để bảo mật API Key.
   
2. **⚡ Sử dụng Chế độ Stream cho các văn bản dài:**
   Model `kira-3.5-flash` hỗ trợ tính năng tự động liên kết tiếp nối cực kỳ mạnh mẽ (Auto-Continuation) giúp tự động sinh các bài viết siêu dài vượt mức giới hạn token của API đơn lẻ. Sử dụng `stream: true` kết hợp EventSource để mang lại trải nghiệm thời gian thực tuyệt vời cho người dùng.

3. **🖼️ Xử lý định dạng Base64 thông minh:**
   API sinh ảnh và video của Kira AI trả về dữ liệu hình ảnh trực tiếp dưới dạng chuỗi Base64 (`b64_json`). Khi lưu vào cơ sở dữ liệu hoặc CMS (như WordPress), hãy chuyển đổi chuỗi Base64 này thành file vật lý trước để tối ưu hóa hiệu năng tải trang:
   - *Trong PHP/WordPress*: Giải mã chuỗi bằng `base64_decode`, ghi vào thư mục uploads bằng `file_put_contents`, rồi đăng ký vào thư viện bằng `wp_insert_attachment`.

4. **⌛ Cấu hình Timeout phù hợp:**
   Vì các tác vụ AI đặc biệt như phân tích nội dung chuyên sâu hoặc tạo ảnh chất lượng cao cần thời gian xử lý lớn, hãy đảm bảo đặt tham số kết nối timeout tối thiểu **60 giây** đến **90 giây** đối với các HTTP Client của bạn.
