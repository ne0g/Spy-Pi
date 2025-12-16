<?php
// recordings.php
require __DIR__ . '/config.php';
require_login();

require __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// S3 Settings
$BUCKET_NAME = 'spy-pi-audio-c0ggerz';
$REGION      = 'eu-north-1';
$PREFIX      = 'pi-recordings/';

$username = $_SESSION['username'] ?? 'user';
$error    = '';
$objects  = [];

// Create S3 client – creds come from the instance role
$s3 = new S3Client([
    'version' => 'latest',
    'region'  => $REGION,
]);

// Helpers
function formatBytes($bytes, $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow   = $bytes ? floor(log($bytes, 1024)) : 0;
    $pow   = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function presignedUrl(S3Client $s3, string $bucket, string $key, string $expires = '+15 minutes'): string {
    $cmd     = $s3->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $key]);
    $request = $s3->createPresignedRequest($cmd, $expires);
    return (string) $request->getUri();
}

// Get S3 object list
try {
    $result = $s3->listObjectsV2([
        'Bucket' => $BUCKET_NAME,
        'Prefix' => $PREFIX,
    ]);

    if (!empty($result['Contents'])) {
        $objects = $result['Contents'];
    }
} catch (AwsException $e) {
    $error = 'Failed to load recordings from S3: ' . $e->getAwsErrorMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Spy-Pi Recordings</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Spy-Pi Recordings</h1>
    <p class="subtitle">
        Logged in as <strong><?= htmlspecialchars($username) ?></strong>.<br>
        Below are audio files uploaded from your Spy-Pi device to AWS S3.
    </p>

    <div class="center mb-3">
        <a href="recordings.php"><button>Refresh</button></a>
        <a href="logout.php"><button class="secondary">Logout</button></a>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php elseif (empty($objects)): ?>
        <p class="info">No recordings found yet. Run your Pi upload script and refresh this page.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                <tr>
                    <th>File</th>
                    <th>Size</th>
                    <th>Last Modified</th>
                    <th>Download</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($objects as $obj): ?>
                    <?php
                    $key = $obj['Key'];

                    // Skip the "folder" object if S3 created one equal to $PREFIX
                    if ($key === $PREFIX) {
                        continue;
                    }

                    $size         = $obj['Size'] ?? 0;
                    $lastModified = $obj['LastModified'] ?? null;

                    try {
                        $url = presignedUrl($s3, $BUCKET_NAME, $key);
                    } catch (AwsException $e) {
                        $url = '';
                    }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars(basename($key)) ?></td>
                        <td><?= formatBytes($size) ?></td>
                        <td>
                            <?php if ($lastModified instanceof DateTimeInterface): ?>
                                <?= htmlspecialchars($lastModified->format('Y-m-d H:i:s')) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($url): ?>
                                <a href="<?= htmlspecialchars($url) ?>" target="_blank">Download / Play</a>
                            <?php else: ?>
                                <span class="badge">N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
