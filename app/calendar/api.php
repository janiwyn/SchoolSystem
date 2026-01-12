<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['admin', 'principal', 'bursar']);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// GET all events
if ($action === 'load') {
    $query = "SELECT id, title, category, start_date, end_date, show_badge, badge_color 
              FROM calendar_events 
              ORDER BY start_date ASC";
    $result = $mysqli->query($query);
    $events = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    echo json_encode(['success' => true, 'events' => $events]);
    exit;
}

// ADD event
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $mysqli->prepare("INSERT INTO calendar_events (title, category, start_date, end_date, show_badge, badge_color, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        'ssssisi',
        $data['title'],
        $data['category'],
        $data['start'],
        $data['end'],
        $data['showBadge'],
        $data['color'],
        $_SESSION['user_id']
    );
    
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
    exit;
}

// UPDATE event
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $mysqli->prepare("UPDATE calendar_events SET title=?, category=?, start_date=?, end_date=?, show_badge=?, badge_color=? WHERE id=?");
    $stmt->bind_param(
        'ssssisi',
        $data['title'],
        $data['category'],
        $data['start'],
        $data['end'],
        $data['showBadge'],
        $data['color'],
        $data['id']
    );
    
    echo json_encode(['success' => $stmt->execute()]);
    $stmt->close();
    exit;
}

// DELETE event
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    
    if ($id > 0) {
        $stmt = $mysqli->prepare("DELETE FROM calendar_events WHERE id = ?");
        $stmt->bind_param('i', $id);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
