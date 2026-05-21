<?php
/**
 * Google Gemini AI Metadata Generator (Native Curl)
 * YouTube Automation Scheduling Platform
 */

require_once __DIR__ . '/functions.php';

/**
 * Generate SEO-Optimized Metadata using Google Gemini API
 * @param string $topic Title, theme, or keywords
 * @param string $tone Tone style (viral, professional, gaming, motivational, tech, funny, tamil_style, educational)
 * @param string|null $apiKey Gemini API Key
 * @return array
 */
function gemini_generate_metadata($topic, $tone, $apiKey = null) {
    if (empty($topic)) {
        return gemini_get_fallback($topic, $tone);
    }

    if (empty($apiKey)) {
        return gemini_get_fallback($topic, $tone);
    }

    // Build the Tone Description to guide the AI
    $toneGuide = "";
    switch ($tone) {
        case 'viral':
            $toneGuide = "sensational, high CTR hooks, click-worthy, extreme curiosity, target maximum audience reach";
            break;
        case 'professional':
            $toneGuide = "authoritative, structured, business-like, clean formatting, objective and informative";
            break;
        case 'gaming':
            $toneGuide = "energetic, gamer lingo, high excitement, engaging, references to gameplay, strategies, or trends";
            break;
        case 'motivational':
            $toneGuide = "inspiring, uplifting, dramatic, emotional hooks, focus on self-improvement, grit, and success";
            break;
        case 'tech':
            $toneGuide = "analytical, detailed, geeky but accessible, feature-focused, structured, specifications oriented";
            break;
        case 'funny':
            $toneGuide = "humorous, witty, sarcastic, lighthearted, meme references, extremely playful";
            break;
        case 'tamil_style':
            $toneGuide = "Tamil style (Tanglish and Tamil script hybrid). Use popular Tamil viral slangs (e.g., 'Vera Level', 'Gethu', 'Nanba', 'Sema'), high energy, emotional connection, custom tailored for South Indian YouTube viewers";
            break;
        case 'educational':
            $toneGuide = "informative, step-by-step breakdown, clear takeaways, structured outline, scholarly and helpful";
            break;
        default:
            $toneGuide = "balanced, engaging, SEO friendly";
    }

    // Create the system prompt
    $prompt = "You are an expert YouTube growth consultant and SEO specialist. Generate metadata for a video about: \"$topic\".\n";
    $prompt .= "The requested tone is: $tone ($toneGuide).\n\n";
    $prompt .= "IMPORTANT: You must respond ONLY with a raw JSON object. Do not include markdown code blocks (like ```json), no markdown headers, and no text outside the JSON. The JSON structure MUST be exactly like this:
{
  \"titles\": [
    \"Title Option 1 (SEO Rich)\",
    \"Title Option 2 (High CTR Hook)\",
    \"Title Option 3 (Curiosity Gap)\",
    \"Title Option 4\",
    \"Title Option 5\"
  ],
  \"description\": \"Write an engaging description (at least 150 words) structured with: 1. A hook line, 2. A detailed summary of the video topic, 3. Call to Action (e.g. Subscribe), and 4. Spaceholders for links.\",
  \"tags\": \"tag1, tag2, tag3, tag4, tag5, tag6, tag7, tag8, tag9, tag10, tag11, tag12, tag13, tag14, tag15\",
  \"hashtags\": \"#hashtag1 #hashtag2 #hashtag3 #hashtag4\",
  \"short_caption\": \"A short, punchy 10-word caption with an emoji suitable for YouTube Shorts\",
  \"keyword_suggestions\": [
    \"viral keyword suggestion 1\",
    \"viral keyword suggestion 2\",
    \"viral keyword suggestion 3\",
    \"viral keyword suggestion 4\"
  ]
}";

    // Set up the API call
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
    
    $requestBody = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json'
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    // Set reasonable timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (!empty($result['candidates'][0]['content']['parts'][0]['text'])) {
            $jsonText = trim($result['candidates'][0]['content']['parts'][0]['text']);
            
            // Just in case it returned markdown formatting, strip it
            if (strpos($jsonText, '```') === 0) {
                $jsonText = preg_replace('/^```(?:json)?\s+|\s+```$/', '', $jsonText);
            }
            
            $parsedData = json_decode($jsonText, true);
            if (is_array($parsedData) && isset($parsedData['titles']) && isset($parsedData['description'])) {
                return $parsedData;
            }
        }
    }

    // Log the failure and fall back
    error_log("Gemini API call failed (HTTP $httpCode). Response: $response. Falling back to rule-based generation.");
    return gemini_get_fallback($topic, $tone);
}

/**
 * Rule-based fallback generator if API fails or API key is missing
 * @param string $topic
 * @param string $tone
 * @return array
 */
function gemini_get_fallback($topic, $tone) {
    $cleanTopic = xss_clean($topic);
    $words = explode(' ', $cleanTopic);
    $primaryKeyword = count($words) > 0 ? $words[0] : 'Video';
    $secondaryKeyword = count($words) > 1 ? $words[1] : 'Tutorial';

    // Tone variations
    $titles = [];
    $tags = "";
    $hashtags = "";
    $shortCaption = "";

    switch ($tone) {
        case 'tamil_style':
            $titles = [
                "Vera Level! $cleanTopic in Tamil - Shocking Details! 😱",
                "Idha Paakama Video Podadhiga! $cleanTopic Explained Nanba!",
                "$cleanTopic Complete Guide - Mass Combo Tutorial!",
                "Trending $cleanTopic: Ultimate Secret Revealed (தமிழ்)",
                "How to master $cleanTopic in 2026? Vera level tricks!"
            ];
            $tags = "tamil, $cleanTopic tamil, $primaryKeyword, $primaryKeyword tamil, semi tamil, south tech, south growth, $secondaryKeyword";
            $hashtags = "#$primaryKeyword #TamilCreators #VeraLevel #SouthYouTube";
            $shortCaption = "Vera Level Tricks for $cleanTopic! Neengalum paakalam 💥";
            break;

        case 'viral':
            $titles = [
                "I Tried $cleanTopic For 7 Days (Shocking Results!) 🚨",
                "Why 99% Of Creators Fail At $cleanTopic (And How To Fix It)",
                "The Ultimate $cleanTopic Formula No One Tells You!",
                "Stop Doing THIS to your $cleanTopic! (Do this instead)",
                "Secrets of $cleanTopic revealed - 100x Growth Hacks!"
            ];
            $tags = "viral, trending, $cleanTopic, secrets, hack, growth, tutorial, guidelines, $primaryKeyword";
            $hashtags = "#$primaryKeyword #ViralGrowth #TrendingHacks #StopDoingThis";
            $shortCaption = "The shocking truth about $cleanTopic you need to see! 😱";
            break;

        case 'tech':
            $titles = [
                "The Complete $cleanTopic Guide: Spec Review & Benchmarks",
                "Why $cleanTopic is the Future of Tech! (Deep Dive)",
                "Mastering $cleanTopic - Advanced Configurations & Tips",
                "$cleanTopic Explained: How It Works & Setup Tutorial",
                "Is $cleanTopic Actually Worth It? (Detailed Analysis)"
            ];
            $tags = "tech, technology, review, benchmarks, setup, tutorial, guide, configuration, specifications, $primaryKeyword, $secondaryKeyword";
            $hashtags = "#$primaryKeyword #TechGuide #TechReview #DeepDive";
            $shortCaption = "How $cleanTopic works behind the scenes! 🧠";
            break;

        case 'motivational':
            $titles = [
                "How Mastering $cleanTopic Will Change Your Life In 2026",
                "The Pain of $cleanTopic: Why Success Takes Time (Don't Quit)",
                "Mindset Secrets: How to Conquer $cleanTopic Every Single Day",
                "This 5-Minute Routine Will Help You Master $cleanTopic",
                "Unlocking Your True Potential through $cleanTopic"
            ];
            $tags = "motivation, inspiration, success, mindset, self-improvement, discipline, goals, grit, $primaryKeyword";
            $hashtags = "#$primaryKeyword #MotivationDaily #MindsetShift #NeverGiveUp";
            $shortCaption = "Master your mind and master $cleanTopic today! 🔥";
            break;

        default: // Educational / standard
            $titles = [
                "How to Master $cleanTopic: A Step-by-Step Tutorial",
                "$cleanTopic for Beginners: The Ultimate 2026 Guide",
                "5 Simple Steps to Improve your $cleanTopic immediately",
                "Everything you need to know about $cleanTopic",
                "The Best Way to Handle $cleanTopic (Full Breakdown)"
            ];
            $tags = "$cleanTopic, tutorial, education, guide, beginners, how to, step-by-step, $primaryKeyword, $secondaryKeyword";
            $hashtags = "#$primaryKeyword #BeginnerGuide #HowTo #EducationalTutorial";
            $shortCaption = "The easiest way to understand $cleanTopic! 💡";
    }

    $description = "Looking to understand more about {$cleanTopic}? In this video, we do a complete breakdown of {$cleanTopic} and share exactly what you need to know to get started and succeed. \n\n" .
        "👉 Subscribe for more weekly tutorials: https://youtube.com/yourchannel\n\n" .
        "Timeline of the Video:\n" .
        "0:00 - Introduction to {$primaryKeyword}\n" .
        "1:30 - Core Concepts of {$secondaryKeyword}\n" .
        "4:15 - Step-by-step Action Plan\n" .
        "8:00 - Pitfalls & Secrets\n" .
        "11:30 - Summary & Conclusion\n\n" .
        "Let me know in the comments if you have any questions! Connect with us on social media for daily updates.";

    $keywordSuggestions = [
        "$cleanTopic tutorial",
        "how to do $cleanTopic",
        "$primaryKeyword secret hacks",
        "$cleanTopic 2026 guides"
    ];

    return [
        'titles' => $titles,
        'description' => $description,
        'tags' => $tags,
        'hashtags' => $hashtags,
        'short_caption' => $shortCaption,
        'keyword_suggestions' => $keywordSuggestions
    ];
}
