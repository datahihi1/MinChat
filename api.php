<?php
session_start();

try {
    $pdo = new PDO('sqlite:messages.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? '';

    if ($action === 'send') {
        // Gửi tin nhắn
        $userId = $_SESSION['user_minchat']['id'] ?? 0;
        $message = trim($_POST['text'] ?? '');

        if (empty($message)) {
            echo json_encode(['ok' => false, 'error' => 'Tin nhắn không được để trống.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO messages (user_id, message) VALUES (:user_id, :message)");
        $stmt->execute([
            ':user_id' => $userId,
            ':message' => $message,
        ]);

        echo json_encode(['ok' => true, 'msg' => [
            'id' => $pdo->lastInsertId(),
            'user_id' => $userId,
            'text' => $message,
            'ts' => time(),
        ]]);
        exit;
    } elseif ($action === 'fetch') {
        // Lấy tin nhắn mới hoặc cũ
        $lastId = (int)($_GET['last_id'] ?? 0);
        $beforeId = (int)($_GET['before_id'] ?? 0);

        if ($beforeId > 0) {
            $stmt = $pdo->prepare("SELECT m.id, m.message AS text, strftime('%s', m.created_at) AS ts, u.username AS name
                                   FROM messages m
                                   JOIN users u ON m.user_id = u.id
                                   WHERE m.id < :before_id
                                   ORDER BY m.id ASC
                                   LIMIT 20");
            $stmt->execute([':before_id' => $beforeId]);
        } else {
            $stmt = $pdo->prepare("SELECT m.id, m.message AS text, strftime('%s', m.created_at) AS ts, u.username AS name
                                   FROM messages m
                                   JOIN users u ON m.user_id = u.id
                                   WHERE m.id > :last_id
                                   ORDER BY m.id ASC");
            $stmt->execute([':last_id' => $lastId]);
        }

        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'messages' => $messages]);
        exit;
    } else {
        echo json_encode(['ok' => false, 'error' => 'Hành động không hợp lệ.']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}