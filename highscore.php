<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$game = preg_replace('/[^a-z0-9_\-]/i', '', $_GET['game'] ?? '');
if ($game === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Kein Spiel angegeben']);
    exit;
}
$file = __DIR__ . '/highscores_' . strtolower($game) . '.txt';

function readScores(string $file): array {
    $scores = [];
    if (!file_exists($file)) return $scores;
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode('|', $line, 3);
        if (count($parts) === 3) {
            $scores[] = [
                'name'  => $parts[0],
                'score' => (int)$parts[1],
                'date'  => $parts[2],
            ];
        }
    }
    return $scores;
}

function writeScores(string $file, array $scores): void {
    $lines = array_map(
        fn($s) => $s['name'] . '|' . $s['score'] . '|' . $s['date'],
        $scores
    );
    file_put_contents($file, implode("\n", $lines), LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $name  = trim((string)($data['name']  ?? ''));
    $score = (int)($data['score'] ?? 0);

    // Sanitize: allow unicode letters, digits, spaces, hyphens, underscores
    $name = preg_replace('/[^\p{L}0-9 _\-]/u', '', $name);
    $name = mb_substr($name, 0, 20);

    if ($name === '' || $score <= 0 || $score > 99999) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Daten']);
        exit;
    }

    if (!file_exists($file)) {
        http_response_code(403);
        echo json_encode(['error' => 'Spiel nicht gefunden']);
        exit;
    }

    $scores   = readScores($file);
    $scores[] = ['name' => $name, 'score' => $score, 'date' => date('d.m.Y')];
    usort($scores, fn($a, $b) => $b['score'] - $a['score']);
    $scores = array_slice($scores, 0, 10);
    writeScores($file, $scores);

    echo json_encode(['success' => true, 'scores' => $scores]);
} else {
    $scores = readScores($file);
    echo json_encode(['scores' => $scores]);
}
