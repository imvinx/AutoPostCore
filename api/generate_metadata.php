<?php
/**
 * AJAX AI Metadata Generation Endpoint
 * YouTube Automation Scheduling Platform
 */

require_once dirname(__DIR__) . '/includes/auth_check.php';
require_once dirname(__DIR__) . '/includes/gemini_helper.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Disable inline error display
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, "Invalid request method", [], 405);
}

// Check CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    json_response(false, "Security verification failed. Please refresh.", [], 403);
}

$topic = trim($_POST['topic'] ?? '');
$tone  = trim($_POST['tone'] ?? 'viral');

if (empty($topic)) {
    json_response(false, "Please provide keywords or a topic theme to generate metadata.", [], 400);
}

// Allowed tones check
$allowed_tones = ['viral', 'professional', 'gaming', 'motivational', 'tech', 'funny', 'tamil_style', 'educational'];
if (!in_array($tone, $allowed_tones)) {
    $tone = 'viral';
}

// Fetch user Gemini Key
$gemini_key = $userSettings['gemini_api_key'] ?? '';

// Call generator
$metadata = gemini_generate_metadata($topic, $tone, $gemini_key);

if ($metadata && is_array($metadata)) {
    json_response(true, "AI Metadata generated successfully using Gemini API", [
        'metadata' => $metadata,
        'has_api_key' => !empty($gemini_key)
    ]);
} else {
    json_response(false, "Failed to generate metadata templates.", [], 500);
}
