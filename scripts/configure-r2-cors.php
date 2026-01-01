<?php

/**
 * Configure CORS on Cloudflare R2 bucket
 * 
 * This script configures CORS headers on your R2 bucket to allow cross-origin requests.
 * 
 * Usage:
 * 1. Set R2_ACCOUNT_ID, R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_BUCKET_NAME in your .env
 * 2. Run: php scripts/configure-r2-cors.php
 * 
 * Or configure manually via Cloudflare Dashboard:
 * 1. Go to R2 > Your Bucket > Settings > CORS Policy
 * 2. Add the following CORS configuration:
 */

$accountId = env('R2_ACCOUNT_ID');
$accessKeyId = env('R2_ACCESS_KEY_ID');
$secretAccessKey = env('R2_SECRET_ACCESS_KEY');
$bucketName = env('R2_BUCKET');

if (! $accountId || ! $accessKeyId || ! $secretAccessKey || ! $bucketName) {
    echo "Error: Missing required environment variables.\n";
    echo "Required: R2_ACCOUNT_ID, R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_BUCKET\n";
    exit(1);
}

$corsConfig = [
    [
        'AllowedOrigins' => ['*'], // Or specify: ['http://localhost:5173', 'https://yourdomain.com']
        'AllowedMethods' => ['GET', 'HEAD'],
        'AllowedHeaders' => ['*'],
        'ExposeHeaders' => ['ETag'],
        'MaxAgeSeconds' => 3600,
    ],
];

$corsJson = json_encode($corsConfig, JSON_PRETTY_PRINT);

$url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/r2/buckets/{$bucketName}/cors";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, $corsJson);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessKeyId . ':' . $secretAccessKey,
    'Content-Type: application/json',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "CORS configured successfully!\n";
} else {
    echo "Error configuring CORS. HTTP Code: {$httpCode}\n";
    echo "Response: {$response}\n";
    echo "\nManual configuration:\n";
    echo "Go to Cloudflare Dashboard > R2 > {$bucketName} > Settings > CORS Policy\n";
    echo "Add this configuration:\n";
    echo $corsJson . "\n";
}

