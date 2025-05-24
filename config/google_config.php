<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// Google API Configuration
class GoogleConfig
{
    // ค่าที่ได้จาก Google Cloud Console
    private $client_id = 'your_cliend_id';
    private $client_secret = 'your_client_secret';

    // URI สำหรับ redirect หลังจาก auth กับ Google
    private $redirect_uri;

    // ตัวเลือกการกำหนด Redirect URI แบบ hardcoded
    // หากคุณมีปัญหากับการสร้าง URI อัตโนมัติ ให้เปลี่ยนเป็น true
    private $use_hardcoded_uri = true;
    private $hardcoded_redirect_uri = 'https://ssostudent.mbu.ac.th/index.php?page=google_callback';

    // ขอบเขตการเข้าถึงข้อมูลของ Google (เพิ่ม Gmail scope)
    private $scopes = [
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
        'openid',
        'https://www.googleapis.com/auth/gmail.readonly',  // เพิ่มการเข้าถึง Gmail
        'https://www.googleapis.com/auth/drive.readonly'
    ];

    public function __construct()
    {
        if ($this->use_hardcoded_uri && !empty($this->hardcoded_redirect_uri)) {
            // ใช้ค่า hardcoded หากกำหนดไว้
            $this->redirect_uri = $this->hardcoded_redirect_uri;
        } else {
            // กำหนด redirect URI ตาม domain ปัจจุบัน
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $base_path = dirname($_SERVER['PHP_SELF']);

            // ปรับแต่ง path ให้ถูกต้อง
            if ($base_path == '/' || $base_path == '\\') {
                $base_path = '';
            }

            // สร้าง redirect URI
            $this->redirect_uri = $protocol . "://" . $host . $base_path . "/index.php?page=google_callback";
        }
    }

    // สร้าง URL สำหรับการเริ่มกระบวนการ OAuth
    public function getAuthUrl()
    {
        $url = 'https://accounts.google.com/o/oauth2/v2/auth';
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes),
            'access_type' => 'offline',  // เปลี่ยนเป็น offline เพื่อรับ refresh token
            'prompt' => 'consent'        // เปลี่ยนเป็น consent เพื่อขออนุญาตใหม่
        ];

        return $url . '?' . http_build_query($params);
    }

    // แลกเปลี่ยน authorization code เพื่อรับ access token
    public function getAccessToken($code)
    {
        $url = 'https://oauth2.googleapis.com/token';

        $data = [
            'code' => $code,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri,
            'grant_type' => 'authorization_code'
        ];

        // ใช้ cURL แทน file_get_contents เพื่อความเสถียร
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        if (curl_error($ch)) {
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        if ($response === FALSE) {
            return null;
        }

        return json_decode($response, true);
    }

    // ดึงข้อมูลผู้ใช้จาก access token
    public function getUserInfo($access_token)
    {
        $url = 'https://www.googleapis.com/oauth2/v3/userinfo';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        if (curl_error($ch)) {
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        if ($response === FALSE) {
            return null;
        }

        return json_decode($response, true);
    }

    // เพิ่มเมธอดสำหรับ Gmail API

    /**
     * ดึงจำนวนอีเมลที่ยังไม่ได้อ่าน
     */
    public function getGmailUnreadCount($access_token)
    {
        $url = 'https://www.googleapis.com/gmail/v1/users/me/messages?q=is:unread';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_error($ch)) {
            curl_close($ch);
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        if ($http_code === 401) {
            throw new Exception('Access token หมดอายุ กรุณาเชื่อมต่อ Google ใหม่');
        }

        if ($http_code !== 200) {
            throw new Exception('ไม่สามารถดึงข้อมูลอีเมลได้ (HTTP ' . $http_code . ')');
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('ข้อมูลที่ได้รับไม่ถูกต้อง');
        }

        return isset($data['resultSizeEstimate']) ? (int)$data['resultSizeEstimate'] : 0;
    }

    /**
     * ดึงอีเมลล่าสุด
     */
    public function getGmailRecentEmails($access_token, $limit = 5)
    {
        // ดึงรายการ ID ของอีเมล
        $url = "https://www.googleapis.com/gmail/v1/users/me/messages?maxResults=" . (int)$limit;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_error($ch)) {
            curl_close($ch);
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        if ($http_code === 401) {
            throw new Exception('Access token หมดอายุ กรุณาเชื่อมต่อ Google ใหม่');
        }

        if ($http_code !== 200) {
            throw new Exception('ไม่สามารถดึงข้อมูลอีเมลได้ (HTTP ' . $http_code . ')');
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('ข้อมูลที่ได้รับไม่ถูกต้อง');
        }

        if (!isset($data['messages']) || empty($data['messages'])) {
            return [];
        }

        // ดึงรายละเอียดของแต่ละอีเมล
        $emails = [];
        foreach ($data['messages'] as $message) {
            try {
                $email_detail = $this->getGmailMessage($access_token, $message['id']);
                if ($email_detail) {
                    $emails[] = $email_detail;
                }
            } catch (Exception $e) {
                // ข้ามอีเมลที่มีปัญหา
                error_log("Error fetching email {$message['id']}: " . $e->getMessage());
                continue;
            }
        }

        return $emails;
    }

    /**
     * ดึงรายละเอียดของอีเมลเฉพาะ
     */
    private function getGmailMessage($access_token, $message_id)
    {
        $url = "https://www.googleapis.com/gmail/v1/users/me/messages/" . urlencode($message_id);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_error($ch)) {
            curl_close($ch);
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('ไม่สามารถดึงรายละเอียดอีเมลได้');
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE || !$data || !isset($data['payload'])) {
            throw new Exception('ข้อมูลอีเมลไม่ถูกต้อง');
        }

        // ดึงข้อมูลสำคัญจากอีเมล
        $headers = $data['payload']['headers'] ?? [];
        $subject = '';
        $from = '';
        $date = '';
        $to = '';

        foreach ($headers as $header) {
            switch (strtolower($header['name'])) {
                case 'subject':
                    $subject = $header['value'];
                    break;
                case 'from':
                    $from = $header['value'];
                    break;
                case 'date':
                    $date = $header['value'];
                    break;
                case 'to':
                    $to = $header['value'];
                    break;
            }
        }

        // ตรวจสอบว่าเป็นอีเมลที่ยังไม่ได้อ่านหรือไม่
        $labelIds = $data['labelIds'] ?? [];
        $isUnread = in_array('UNREAD', $labelIds);
        $isImportant = in_array('IMPORTANT', $labelIds);

        // จัดรูปแบบวันที่
        $formattedDate = '';
        if ($date) {
            try {
                $dateObj = new DateTime($date);
                $formattedDate = $dateObj->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $formattedDate = $date; // ใช้วันที่เดิมถ้าไม่สามารถ parse ได้
            }
        }

        // ทำความสะอาดและตัดข้อความ snippet
        $snippet = isset($data['snippet']) ? $this->cleanSnippet($data['snippet']) : '';

        return [
            'id' => $data['id'],
            'threadId' => $data['threadId'] ?? '',
            'subject' => $this->cleanText($subject),
            'from' => $this->cleanText($from),
            'to' => $this->cleanText($to),
            'date' => $date,
            'formatted_date' => $formattedDate,
            'unread' => $isUnread,
            'important' => $isImportant,
            'snippet' => $snippet,
            'labels' => $labelIds
        ];
    }

    /**
     * ทำความสะอาดข้อความ
     */
    private function cleanText($text)
    {
        if (empty($text)) {
            return '';
        }

        // ลบ HTML tags
        $text = strip_tags($text);

        // ลบ whitespace ที่ไม่จำเป็น
        $text = trim(preg_replace('/\s+/', ' ', $text));

        // ป้องกัน XSS
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        return $text;
    }

    /**
     * ทำความสะอาด snippet
     */
    private function cleanSnippet($snippet)
    {
        if (empty($snippet)) {
            return '';
        }

        // ลบ HTML tags และ entities
        $snippet = html_entity_decode(strip_tags($snippet));

        // ลบ whitespace ที่ไม่จำเป็น
        $snippet = trim(preg_replace('/\s+/', ' ', $snippet));

        // ตัดข้อความถ้ายาวเกินไป
        if (mb_strlen($snippet) > 150) {
            $snippet = mb_substr($snippet, 0, 147) . '...';
        }

        // ป้องกัน XSS
        $snippet = htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8');

        return $snippet;
    }

    /**
     * ตรวจสอบสถานะ access token
     */
    public function validateAccessToken($access_token)
    {
        $url = 'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . urlencode($access_token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_error($ch)) {
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        return $http_code === 200;
    }

    // เข้าถึง client ID
    public function getClientId()
    {
        return $this->client_id;
    }

    // เข้าถึง redirect URI
    public function getRedirectUri()
    {
        return $this->redirect_uri;
    }


    // ดึงไฟล์ล่าสุด 5 รายการจาก Google Drive

    public function getRecentDriveFiles($access_token, $limit = 5)
    {
        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query([
            'orderBy' => 'modifiedTime desc',
            'pageSize' => $limit,
            'fields' => 'files(id,name,mimeType,size,modifiedTime,webViewLink,webContentLink,thumbnailLink,iconLink)'
        ]);

        $options = [
            'http' => [
                'header' => "Authorization: Bearer $access_token\r\n",
                'method' => 'GET'
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            return false;
        }

        $data = json_decode($response, true);
        return isset($data['files']) ? $data['files'] : [];
    }

    /**
     * ดึงข้อมูลไฟล์เฉพาะจาก Google Drive
     */
    public function getDriveFileInfo($access_token, $file_id)
    {
        $url = "https://www.googleapis.com/drive/v3/files/$file_id?" . http_build_query([
            'fields' => 'id,name,mimeType,size,modifiedTime,webViewLink,webContentLink,thumbnailLink,iconLink,description,owners'
        ]);

        $options = [
            'http' => [
                'header' => "Authorization: Bearer $access_token\r\n",
                'method' => 'GET'
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            return false;
        }

        return json_decode($response, true);
    }

    /**
     * ค้นหาไฟล์ใน Google Drive
     */
    public function searchDriveFiles($access_token, $query, $limit = 10)
    {
        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query([
            'q' => $query,
            'orderBy' => 'modifiedTime desc',
            'pageSize' => $limit,
            'fields' => 'files(id,name,mimeType,size,modifiedTime,webViewLink,webContentLink,thumbnailLink,iconLink)'
        ]);

        $options = [
            'http' => [
                'header' => "Authorization: Bearer $access_token\r\n",
                'method' => 'GET'
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            return false;
        }

        $data = json_decode($response, true);
        return isset($data['files']) ? $data['files'] : [];
    }

    /**
     * แปลง MIME type เป็นชื่อประเภทไฟล์ที่อ่านง่าย
     */
    public function getMimeTypeDescription($mimeType)
    {
        $types = [
            'application/pdf' => 'PDF',
            'application/vnd.google-apps.document' => 'Google Docs',
            'application/vnd.google-apps.spreadsheet' => 'Google Sheets',
            'application/vnd.google-apps.presentation' => 'Google Slides',
            'application/vnd.google-apps.folder' => 'โฟลเดอร์',
            'image/jpeg' => 'รูปภาพ JPEG',
            'image/png' => 'รูปภาพ PNG',
            'image/gif' => 'รูปภาพ GIF',
            'text/plain' => 'ไฟล์ข้อความ',
            'application/msword' => 'Word Document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word Document',
            'application/vnd.ms-excel' => 'Excel Spreadsheet',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel Spreadsheet',
            'application/vnd.ms-powerpoint' => 'PowerPoint Presentation',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PowerPoint Presentation',
            'application/zip' => 'ZIP Archive',
            'video/mp4' => 'MP4 Video',
            'audio/mpeg' => 'MP3 Audio'
        ];

        return isset($types[$mimeType]) ? $types[$mimeType] : 'ไฟล์อื่น ๆ';
    }

    /**
     * แปลงขนาดไฟล์เป็นรูปแบบที่อ่านง่าย
     */
    public function formatFileSize($bytes)
    {
        if (!$bytes) return 'ไม่ทราบขนาด';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
