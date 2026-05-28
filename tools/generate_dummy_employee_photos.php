<?php

$root = dirname(__DIR__);
$outDir = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . 'dummies';
$backupDir = $root . DIRECTORY_SEPARATOR . 'exports';

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD extension is required.\n");
    exit(1);
}

if (!is_dir($outDir) && !mkdir($outDir, 0777, true)) {
    fwrite(STDERR, "Cannot create output directory: {$outDir}\n");
    exit(1);
}

if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true)) {
    fwrite(STDERR, "Cannot create backup directory: {$backupDir}\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3307;dbname=newtiffa_timesheet;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$rows = $pdo->query("
    SELECT id, employee_code, first_name, last_name, photo
    FROM users
    WHERE active = '1'
      AND (photo IS NULL OR photo = '' OR photo IN ('default_photo.jpg', 'default-photo.jpg'))
    ORDER BY first_name, id
")->fetchAll();

$timestamp = date('Ymd_His');
$backupFile = $backupDir . DIRECTORY_SEPARATOR . "dummy_employee_photo_backup_{$timestamp}.csv";
$backup = fopen($backupFile, 'w');
fputcsv($backup, ['id', 'employee_code', 'first_name', 'last_name', 'old_photo', 'new_photo']);

$palettes = [
    ['#155e75', '#22d3ee'],
    ['#166534', '#86efac'],
    ['#7c2d12', '#fdba74'],
    ['#581c87', '#d8b4fe'],
    ['#991b1b', '#fca5a5'],
    ['#1e3a8a', '#93c5fd'],
    ['#365314', '#bef264'],
    ['#831843', '#f9a8d4'],
];

$font = 'C:\\Windows\\Fonts\\arialbd.ttf';
$hasTtf = file_exists($font);
$update = $pdo->prepare('UPDATE users SET photo = :photo WHERE id = :id');
$created = 0;

foreach ($rows as $row) {
    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    $initials = build_initials($name, $row['employee_code']);
    $palette = $palettes[((int)$row['id']) % count($palettes)];
    $filename = 'dummy_employee_' . preg_replace('/[^0-9A-Za-z_-]/', '', (string)$row['employee_code']) . '_' . (int)$row['id'] . '.png';
    $path = $outDir . DIRECTORY_SEPARATOR . $filename;

    create_avatar($path, $initials, $palette[0], $palette[1], $hasTtf ? $font : null);
    $relative = 'dummies/' . $filename;

    fputcsv($backup, [
        $row['id'],
        $row['employee_code'],
        $row['first_name'],
        $row['last_name'],
        $row['photo'],
        $relative,
    ]);

    $update->execute([
        ':photo' => $relative,
        ':id' => $row['id'],
    ]);

    $created++;
}

fclose($backup);

echo "Created {$created} dummy employee photos.\n";
echo "Output directory: {$outDir}\n";
echo "Backup CSV: {$backupFile}\n";

function build_initials($name, $fallback)
{
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') {
        return substr((string)$fallback, 0, 2);
    }

    $parts = explode(' ', $name);
    $first = substr($parts[0], 0, 1);
    $second = count($parts) > 1 ? substr($parts[1], 0, 1) : substr($parts[0], 1, 1);
    return strtoupper($first . $second);
}

function create_avatar($path, $initials, $color1, $color2, $font)
{
    $size = 256;
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    [$r1, $g1, $b1] = hex_to_rgb($color1);
    [$r2, $g2, $b2] = hex_to_rgb($color2);

    for ($y = 0; $y < $size; $y++) {
        $ratio = $y / max(1, $size - 1);
        $r = (int)round($r1 + (($r2 - $r1) * $ratio));
        $g = (int)round($g1 + (($g2 - $g1) * $ratio));
        $b = (int)round($b1 + (($b2 - $b1) * $ratio));
        $line = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $size, $y, $line);
    }

    $ring = imagecolorallocatealpha($img, 255, 255, 255, 55);
    imagefilledellipse($img, 190, 55, 145, 145, $ring);
    imagefilledellipse($img, 45, 210, 130, 130, $ring);

    $white = imagecolorallocate($img, 255, 255, 255);
    if ($font) {
        $fontSize = 76;
        $box = imagettfbbox($fontSize, 0, $font, $initials);
        $textWidth = $box[2] - $box[0];
        $textHeight = $box[1] - $box[7];
        $x = (int)(($size - $textWidth) / 2);
        $y = (int)(($size + $textHeight) / 2) - 6;
        imagettftext($img, $fontSize, 0, $x, $y, $white, $font, $initials);
    } else {
        imagestring($img, 5, 105, 118, $initials, $white);
    }

    imagepng($img, $path, 9);
    imagedestroy($img);
}

function hex_to_rgb($hex)
{
    $hex = ltrim($hex, '#');
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}
