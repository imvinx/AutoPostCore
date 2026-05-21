<?php
/**
 * YouTube API v3 Custom Helper (Native PHP Curl)
 * YouTube Automation Scheduling Platform
 */

require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Generate Google OAuth 2.0 Auth URL
 * @param string $clientId
 * @param string $redirectUri
 * @return string
 */
function youtube_get_auth_url($clientId, $redirectUri) {
    $endpoint = "https://accounts.google.com/o/oauth2/v2/auth";
    $scopes = [
        "https://www.googleapis.com/auth/youtube.upload",
        "https://www.googleapis.com/auth/youtube.readonly"
    ];
    
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => implode(' ', $scopes),
        'access_type' => 'offline',
        'prompt' => 'consent' // Forces refresh_token return
    ];

    return $endpoint . '?' . http_build_query($params);
}

/**
 * Exchange Authorization Code for Tokens
 * @param string $clientId
 * @param string $clientSecret
 * @param string $code
 * @param string $redirectUri
 * @return array|false
 */
function youtube_exchange_code($clientId, $clientSecret, $code, $redirectUri) {
    $ch = curl_init("https://oauth2.googleapis.com/token");
    
    $postFields = [
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ];

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    error_log("OAuth exchange failed. HTTP: $httpCode. Response: $response");
    return false;
}

/**
 * Refresh expired Access Token
 * @param string $clientId
 * @param string $clientSecret
 * @param string $refreshToken
 * @return array|false
 */
function youtube_refresh_token($clientId, $clientSecret, $refreshToken) {
    $ch = curl_init("https://oauth2.googleapis.com/token");
    
    $postFields = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token'
    ];

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    error_log("Token refresh failed. HTTP: $httpCode. Response: $response");
    return false;
}

/**
 * Fetch Authorized YouTube Channel Info
 * @param string $accessToken
 * @return array|false
 */
function youtube_get_channel_info($accessToken) {
    $url = "https://www.googleapis.com/youtube/v3/channels?part=snippet,statistics&mine=true";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Accept: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (!empty($data['items'][0])) {
            $item = $data['items'][0];
            return [
                'id' => $item['id'],
                'title' => $item['snippet']['title'],
                'avatar' => $item['snippet']['thumbnails']['default']['url'],
                'subscribers' => $item['statistics']['subscriberCount'] ?? 0,
                'views' => $item['statistics']['viewCount'] ?? 0
            ];
        }
    }
    
    return false;
}

/**
 * Get Channel's Playlists
 * @param string $accessToken
 * @return array
 */
function youtube_get_playlists($accessToken) {
    $url = "https://www.googleapis.com/youtube/v3/playlists?part=snippet&mine=true&maxResults=50";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Accept: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $playlists = [];
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $playlists[] = [
                    'id' => $item['id'],
                    'title' => $item['snippet']['title']
                ];
            }
        }
    }
    
    return $playlists;
}

/**
 * Upload Video to YouTube via Resumable Upload
 * @param string $accessToken
 * @param string $filePath Absolute path to video file
 * @param array $metadata Video information (title, description, tags, privacy, category, publishAt)
 * @return string|false YouTube Video ID on success, false on failure
 */
function youtube_upload_video($accessToken, $filePath, $metadata) {
    if (!file_exists($filePath)) {
        error_log("Video upload failed: file $filePath does not exist.");
        return false;
    }

    $fileSize = filesize($filePath);
    
    // 1. Initialize Resumable Session
    $initUrl = "https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status";
    
    $body = [
        'snippet' => [
            'title' => substr($metadata['title'] ?? 'Auto Uploaded Video', 0, 100),
            'description' => $metadata['description'] ?? '',
            'categoryId' => $metadata['categoryId'] ?? '22' // People & Blogs
        ],
        'status' => [
            'privacyStatus' => $metadata['privacyStatus'] ?? 'private'
        ]
    ];

    if (!empty($metadata['tags'])) {
        $tagsArray = is_array($metadata['tags']) ? $metadata['tags'] : array_map('trim', explode(',', $metadata['tags']));
        $body['snippet']['tags'] = array_slice($tagsArray, 0, 50); // Limit tags
    }

    // Handle Scheduling. Note: YouTube API requires privacyStatus to be 'private' if publishAt is set
    if (!empty($metadata['publishAt'])) {
        // Convert to ISO 8601 UTC string format: YYYY-MM-DDTHH:MM:SSZ
        $publishDate = new DateTime($metadata['publishAt']);
        $publishDate->setTimezone(new DateTimeZone('UTC'));
        $body['status']['publishAt'] = $publishDate->format('Y-m-d\TH:i:s\Z');
        $body['status']['privacyStatus'] = 'private'; 
    }

    $headers = [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json; charset=UTF-8",
        "X-Upload-Content-Length: $fileSize",
        "X-Upload-Content-Type: video/*"
    ];

    $ch = curl_init($initUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, true); // We need response headers
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("YouTube Resumable Init Failed. HTTP: $httpCode. Response: $response");
        return false;
    }

    // Extract Location Header
    $uploadUrl = '';
    $rows = explode("\n", $response);
    foreach ($rows as $row) {
        if (stripos($row, 'Location:') === 0) {
            $uploadUrl = trim(substr($row, 9));
            break;
        }
    }

    if (empty($uploadUrl)) {
        error_log("YouTube Resumable Init did not return Location header.");
        return false;
    }

    // 2. Upload Video Binary Data via PUT
    $ch = curl_init($uploadUrl);
    $fp = fopen($filePath, "rb");

    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt($ch, CURLOPT_INFILESIZE, $fileSize);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: video/*",
        "Content-Length: $fileSize"
    ]);

    $uploadResponse = curl_exec($ch);
    $uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    fclose($fp);
    curl_close($ch);

    if ($uploadHttpCode === 200 || $uploadHttpCode === 201) {
        $result = json_decode($uploadResponse, true);
        if (!empty($result['id'])) {
            return $result['id'];
        }
    }

    error_log("YouTube Put File Failed. HTTP: $uploadHttpCode. Response: $uploadResponse");
    return false;
}

/**
 * Set Video Thumbnail Image
 * @param string $accessToken
 * @param string $youtubeVideoId
 * @param string $thumbnailPath Absolute path to thumbnail image
 * @return bool
 */
function youtube_set_thumbnail($accessToken, $youtubeVideoId, $thumbnailPath) {
    if (!file_exists($thumbnailPath)) {
        return false;
    }

    $fileSize = filesize($thumbnailPath);
    $url = "https://www.googleapis.com/upload/youtube/v3/thumbnails/set?videoId=" . $youtubeVideoId;

    $ch = curl_init($url);
    $fp = fopen($thumbnailPath, "rb");

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt($ch, CURLOPT_INFILESIZE, $fileSize);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: image/jpeg", // YouTube supports jpeg/png
        "Content-Length: $fileSize"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    fclose($fp);
    curl_close($ch);

    if ($httpCode === 200 || $httpCode === 201) {
        return true;
    }

    error_log("YouTube thumbnail upload failed. HTTP: $httpCode. Response: $response");
    return false;
}

/**
 * Add Video to Playlist
 * @param string $accessToken
 * @param string $youtubeVideoId
 * @param string $playlistId
 * @return bool
 */
function youtube_add_to_playlist($accessToken, $youtubeVideoId, $playlistId) {
    $url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet";
    
    $body = [
        'snippet' => [
            'playlistId' => $playlistId,
            'resourceId' => [
                'kind' => 'youtube#video',
                'videoId' => $youtubeVideoId
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json; charset=UTF-8"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 || $httpCode === 201) {
        return true;
    }

    error_log("YouTube add to playlist failed. HTTP: $httpCode. Response: $response");
    return false;
}
