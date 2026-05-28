<?php

$root = dirname(__DIR__);
$outDir = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . 'dummy_faces';
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
      AND (
        photo IS NULL
        OR photo = ''
        OR photo IN ('default_photo.jpg', 'default-photo.jpg')
        OR photo LIKE 'dummies/%'
      )
    ORDER BY first_name, id
")->fetchAll();

$timestamp = date('Ymd_His');
$backupFile = $backupDir . DIRECTORY_SEPARATOR . "dummy_face_photo_backup_{$timestamp}.csv";
$backup = fopen($backupFile, 'w');
fputcsv($backup, ['id', 'employee_code', 'first_name', 'last_name', 'old_photo', 'new_photo']);

$update = $pdo->prepare('UPDATE users SET photo = :photo WHERE id = :id');
$created = 0;

foreach ($rows as $row) {
    $seed = crc32($row['id'] . '|' . $row['employee_code'] . '|' . $row['first_name']);
    mt_srand($seed);

    $filename = 'dummy_face_' . preg_replace('/[^0-9A-Za-z_-]/', '', (string)$row['employee_code']) . '_' . (int)$row['id'] . '.png';
    $path = $outDir . DIRECTORY_SEPARATOR . $filename;
    create_face_avatar($path);

    $relative = 'dummy_faces/' . $filename;
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

echo "Created {$created} dummy face photos.\n";
echo "Output directory: {$outDir}\n";
echo "Backup CSV: {$backupFile}\n";

function create_face_avatar($path)
{
    $size = 256;
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $backgrounds = [
        [[226, 239, 253], [188, 219, 255]],
        [[236, 253, 245], [187, 247, 208]],
        [[255, 247, 237], [254, 215, 170]],
        [[250, 245, 255], [221, 214, 254]],
        [[253, 242, 248], [251, 207, 232]],
        [[240, 249, 255], [186, 230, 253]],
    ];
    $skinTones = [
        [242, 201, 164],
        [224, 172, 105],
        [198, 134, 86],
        [141, 85, 54],
        [255, 219, 172],
    ];
    $hairColors = [
        [32, 24, 20],
        [59, 38, 27],
        [91, 59, 38],
        [25, 35, 55],
        [80, 72, 64],
    ];
    $shirtColors = [
        [37, 99, 235],
        [22, 163, 74],
        [220, 38, 38],
        [147, 51, 234],
        [234, 88, 12],
        [8, 145, 178],
    ];

    [$bg1, $bg2] = $backgrounds[mt_rand(0, count($backgrounds) - 1)];
    for ($y = 0; $y < $size; $y++) {
        $ratio = $y / max(1, $size - 1);
        $color = imagecolorallocate(
            $img,
            (int)round($bg1[0] + (($bg2[0] - $bg1[0]) * $ratio)),
            (int)round($bg1[1] + (($bg2[1] - $bg1[1]) * $ratio)),
            (int)round($bg1[2] + (($bg2[2] - $bg1[2]) * $ratio))
        );
        imageline($img, 0, $y, $size, $y, $color);
    }

    $softWhite = imagecolorallocatealpha($img, 255, 255, 255, 75);
    imagefilledellipse($img, 55, 44, 120, 120, $softWhite);
    imagefilledellipse($img, 220, 210, 150, 150, $softWhite);

    $skin = color($img, $skinTones[mt_rand(0, count($skinTones) - 1)]);
    $skinShadow = color_adjust($img, imagecolorsforindex($img, $skin), -24);
    $hair = color($img, $hairColors[mt_rand(0, count($hairColors) - 1)]);
    $shirt = color($img, $shirtColors[mt_rand(0, count($shirtColors) - 1)]);
    $dark = imagecolorallocate($img, 35, 42, 52);
    $white = imagecolorallocate($img, 255, 255, 255);
    $mouth = imagecolorallocate($img, 150, 59, 75);
    $blush = imagecolorallocatealpha($img, 239, 125, 125, 65);

    imagefilledellipse($img, 128, 302, 170, 160, $shirt);
    imagefilledellipse($img, 128, 178, 62, 70, $skinShadow);
    imagefilledellipse($img, 128, 116, 112, 134, $skin);

    $hairStyle = mt_rand(0, 3);
    if ($hairStyle === 0) {
        imagefilledellipse($img, 128, 70, 120, 72, $hair);
        imagefilledrectangle($img, 72, 70, 184, 112, $hair);
        imagefilledellipse($img, 82, 102, 42, 68, $hair);
        imagefilledellipse($img, 174, 102, 42, 68, $hair);
    } elseif ($hairStyle === 1) {
        imagefilledellipse($img, 128, 72, 124, 78, $hair);
        imagefilledarc($img, 128, 110, 122, 120, 180, 360, $hair, IMG_ARC_PIE);
        imagefilledellipse($img, 80, 100, 32, 56, $hair);
    } elseif ($hairStyle === 2) {
        imagefilledellipse($img, 128, 72, 116, 80, $hair);
        imagefilledrectangle($img, 70, 78, 186, 104, $hair);
        for ($i = 0; $i < 7; $i++) {
            imagefilledellipse($img, 78 + ($i * 17), 62 + mt_rand(-5, 7), 30, 30, $hair);
        }
    } else {
        imagefilledarc($img, 128, 92, 122, 110, 190, 350, $hair, IMG_ARC_PIE);
        imagefilledellipse($img, 96, 68, 46, 34, $hair);
        imagefilledellipse($img, 142, 62, 58, 42, $hair);
    }

    imagefilledellipse($img, 74, 124, 18, 28, $skin);
    imagefilledellipse($img, 182, 124, 18, 28, $skin);

    $eyeY = 118 + mt_rand(-3, 3);
    imagefilledellipse($img, 106, $eyeY, 12, 15, $white);
    imagefilledellipse($img, 150, $eyeY, 12, 15, $white);
    imagefilledellipse($img, 106, $eyeY + 1, 6, 8, $dark);
    imagefilledellipse($img, 150, $eyeY + 1, 6, 8, $dark);
    imageline($img, 96, $eyeY - 13, 116, $eyeY - 16, $dark);
    imageline($img, 140, $eyeY - 16, 160, $eyeY - 13, $dark);

    imageline($img, 128, 123, 122, 145, $skinShadow);
    imageline($img, 122, 145, 133, 145, $skinShadow);
    imagefilledellipse($img, 94, 143, 18, 10, $blush);
    imagefilledellipse($img, 162, 143, 18, 10, $blush);

    $smile = mt_rand(0, 2);
    if ($smile === 0) {
        imagearc($img, 128, 151, 42, 28, 18, 162, $mouth);
        imagearc($img, 128, 152, 42, 28, 18, 162, $mouth);
    } elseif ($smile === 1) {
        imageline($img, 110, 155, 146, 155, $mouth);
        imagearc($img, 128, 149, 42, 22, 25, 155, $mouth);
    } else {
        imagefilledellipse($img, 128, 158, 28, 12, $mouth);
        imagefilledellipse($img, 128, 153, 30, 8, $skin);
    }

    $collar = imagecolorallocatealpha($img, 255, 255, 255, 25);
    imagefilledpolygon($img, [104, 210, 128, 236, 152, 210, 128, 220], 4, $collar);

    imagepng($img, $path, 9);
    imagedestroy($img);
}

function color($img, $rgb)
{
    return imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
}

function color_adjust($img, $rgb, $amount)
{
    return imagecolorallocate(
        $img,
        max(0, min(255, $rgb['red'] + $amount)),
        max(0, min(255, $rgb['green'] + $amount)),
        max(0, min(255, $rgb['blue'] + $amount))
    );
}
