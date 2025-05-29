<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

class HelpdeskConfig {
    // ใส่ Webhook URL ที่ได้จาก Google Chat ที่นี่
    private static $google_chat_webhook_url = 'YOUR_GOOGLE_CHAT_WEBHOOK_URL';

    public static function getGoogleChatWebhookUrl() {
        return self::$google_chat_webhook_url;
    }

    // ฟังก์ชันสำหรับส่งการแจ้งเตือน
    public static function sendGoogleChatNotification($ticket_id, $student_name, $subject, $ticket_link) {
        $webhook_url = self::getGoogleChatWebhookUrl();
        if (empty($webhook_url) || $webhook_url === 'YOUR_GOOGLE_CHAT_WEBHOOK_URL') {
            // ไม่ต้องทำอะไรถ้ายังไม่ได้ตั้งค่า Webhook
            return false;
        }

        $message = [
            'cardsV2' => [
                [
                    'cardId' => 'helpdesk-ticket-' . time(),
                    'card' => [
                        'header' => [
                            'title' => '🚨 New Helpdesk Ticket',
                            'subtitle' => 'Ticket ID: ' . htmlspecialchars($ticket_id),
                            'imageUrl' => 'https://img.icons8.com/color/48/000000/service.png',
                            'imageType' => 'CIRCLE'
                        ],
                        'sections' => [
                            [
                                'widgets' => [
                                    [
                                        'decoratedText' => [
                                            'startIcon' => ['knownIcon' => 'PERSON'],
                                            'text' => '<b>Student:</b> ' . htmlspecialchars($student_name)
                                        ]
                                    ],
                                    [
                                        'decoratedText' => [
                                            'startIcon' => ['knownIcon' => 'DESCRIPTION'],
                                            'text' => '<b>Subject:</b> ' . htmlspecialchars($subject)
                                        ]
                                    ]
                                ]
                            ],
                            [
                                'widgets' => [
                                    [
                                        'buttonList' => [
                                            'buttons' => [
                                                [
                                                    'text' => 'View Ticket',
                                                    'onClick' => [
                                                        'openLink' => ['url' => $ticket_link]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $json_data = json_encode($message);

        $ch = curl_init($webhook_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $http_code === 200;
    }
}
?>
