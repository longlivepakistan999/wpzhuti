<?php
/**
 * AI Article Generator
 *
 * Uses Claude API to generate SEO-optimized tutorial articles.
 * Features:
 *   - Auto-categorization into tutorial_category taxonomy
 *   - SEO: meta description, proper headings, internal linking hints
 *   - Low AI detection (<60%) via humanization prompt strategies
 *   - Outputs WordPress block editor (Gutenberg) compatible HTML
 *
 * @package QWE_Auto_Publish
 */

require_once __DIR__ . '/db.php';

class QWE_Generator {

    /**
     * Generate a single article from a keyword.
     *
     * @param string $keyword       The target keyword.
     * @param string $keyword_type  'longtail' or 'trending'.
     * @param string $hint_category Optional category hint.
     * @param string $difficulty    'beginner', 'intermediate', or 'advanced'.
     * @return array|false          Article data or false on failure.
     */
    public static function generate( $keyword, $keyword_type = 'longtail', $hint_category = '', $difficulty = 'beginner' ) {
        $categories = unserialize( QWE_CATEGORIES );

        $category_list = '';
        foreach ( $categories as $slug => $name ) {
            $category_list .= "- {$slug}: {$name}\n";
        }

        $system_prompt = self::build_system_prompt();
        $user_prompt = self::build_user_prompt( $keyword, $keyword_type, $hint_category, $difficulty, $category_list );

        $response = self::call_claude_api( $system_prompt, $user_prompt );

        if ( ! $response ) {
            self::log( "API call failed for keyword: {$keyword}" );
            return false;
        }

        $article = self::parse_response( $response );

        if ( ! $article ) {
            self::log( "Failed to parse response for keyword: {$keyword}" );
            return false;
        }

        $article['keyword'] = $keyword;
        $article['keyword_type'] = $keyword_type;

        // Validate category is one of ours.
        if ( ! isset( $categories[ $article['category'] ] ) ) {
            // Default to first matching category or chatgpt-llms.
            $article['category'] = $hint_category ?: 'chatgpt-llms';
        }

        // Validate difficulty.
        $valid_difficulties = array( 'beginner', 'intermediate', 'advanced' );
        if ( ! in_array( $article['difficulty'], $valid_difficulties, true ) ) {
            $article['difficulty'] = $difficulty;
        }

        return $article;
    }

    /**
     * Build the system prompt.
     * Key strategy for low AI detection:
     *   1. Vary sentence length (mix short and long)
     *   2. Use conversational tone with contractions
     *   3. Include personal opinions and anecdotes
     *   4. Use transitional phrases humans use naturally
     *   5. Occasional informal language
     *   6. Imperfect but natural paragraph structures
     */
    private static function build_system_prompt() {
        return <<<'PROMPT'
You are an experienced tech blogger and AI educator writing for QWE AI Academy (qwe.edu.pl). You write practical, hands-on tutorials about AI tools.

CRITICAL WRITING STYLE RULES (you MUST follow ALL of these to avoid AI detection):

1. SENTENCE VARIATION: Alternate between very short sentences (3-6 words) and longer ones. Never write more than 2 sentences of similar length in a row.

2. CONVERSATIONAL TONE: Write like you're explaining to a friend. Use contractions (don't, isn't, you'll, I've, it's, that's, we're, they're). NEVER use the formal uncontracted form.

3. PERSONAL VOICE: Start some paragraphs with "I", "Look,", "Here's the thing:", "So,", "Now,", "Honestly,", "In my experience,". Share your personal take on things.

4. NATURAL TRANSITIONS: Use phrases like "The thing is", "What I mean is", "So basically", "Here's what that means", "Let me break this down", "But here's the catch". NEVER use "Moreover", "Furthermore", "Additionally", "It is worth noting", "It should be noted".

5. IMPERFECT STRUCTURE: Not every paragraph needs to be the same length. Some can be just one sentence. Others can be 4-5 sentences. This is natural.

6. AVOID THESE AI TELLS:
   - Never start with "In today's digital landscape" or "In the ever-evolving world"
   - Never use "it's important to note that" or "it's worth mentioning"
   - Never use "harness", "leverage", "delve", "tapestry", "landscape" (as metaphor), "embark", "empower", "unlock"
   - Never use bullet points for every section - mix paragraphs with occasional lists
   - Never end with a paragraph that starts with "In conclusion" or "To sum up"

7. SPECIFIC DETAILS: Include real menu paths (File > Settings > ...), actual button names, specific version numbers, concrete examples with real data. Vague content reads as AI-generated.

8. OPINIONATED: Say what YOU think works best. "I'd recommend X over Y because..." is more human than "Both X and Y have their merits."

9. RHYTHM: Read your text aloud mentally. If it sounds like a textbook, rewrite it. It should sound like a blog post by someone who actually uses these tools daily.

OUTPUT FORMAT: You must respond in valid JSON with these exact keys:
{
  "title": "SEO-optimized article title (50-65 characters, includes primary keyword)",
  "slug": "url-friendly-slug-with-keyword",
  "excerpt": "Meta description for SEO (145-160 characters, compelling, includes keyword)",
  "category": "one of the category slugs provided",
  "difficulty": "beginner or intermediate or advanced",
  "content": "Full article HTML content (WordPress Gutenberg block compatible)",
  "tags": ["tag1", "tag2", "tag3"]
}

CONTENT HTML RULES:
- Use <h2> for main sections, <h3> for subsections
- Use <p> for paragraphs
- Use <pre><code> for code blocks
- Use <ul><li> or <ol><li> for lists (but don't overuse lists)
- Use <strong> for emphasis on key terms
- Use <blockquote> for tips or important callouts
- Article must have 1500-2500 words
- Include 4-6 h2 sections
- First paragraph should hook the reader and include the keyword naturally
PROMPT;
    }

    /**
     * Build the user prompt.
     */
    private static function build_user_prompt( $keyword, $keyword_type, $hint_category, $difficulty, $category_list ) {
        $type_context = '';
        if ( 'trending' === $keyword_type ) {
            $type_context = "This is a TRENDING topic. The content should be timely and reference that this is a new/hot development in AI. But still make it a practical tutorial, not just a news article.\n\n";
        }

        $category_hint = '';
        if ( $hint_category ) {
            $category_hint = "Suggested category: {$hint_category} (but choose the best fit)\n\n";
        }

        return <<<PROMPT
Write a comprehensive tutorial article about: "{$keyword}"

{$type_context}{$category_hint}Target difficulty level: {$difficulty}

Available categories (pick the BEST match):
{$category_list}

REQUIREMENTS:
1. Title must include the primary keyword naturally
2. Write 1500-2500 words of practical, actionable content
3. Include step-by-step instructions where appropriate
4. Include specific examples, real tool names, actual settings
5. Add a practical tip in a <blockquote> near the middle
6. End with a practical "what to do next" suggestion (NOT a generic conclusion)
7. The excerpt/meta description must be compelling and include the keyword

Remember: Write like an experienced human blogger, NOT like an AI. Follow ALL the writing style rules from your system instructions. This is critical.

Respond ONLY with valid JSON. No markdown code fences, no extra text.
PROMPT;
    }

    /**
     * Call the Claude API.
     *
     * @param string $system_prompt System message.
     * @param string $user_prompt   User message.
     * @return string|false         Raw response text or false.
     */
    private static function call_claude_api( $system_prompt, $user_prompt ) {
        $api_key = QWE_CLAUDE_API_KEY;
        $model = QWE_CLAUDE_MODEL;

        if ( empty( $api_key ) ) {
            self::log( 'Claude API key not configured' );
            return false;
        }

        $payload = json_encode( array(
            'model'      => $model,
            'max_tokens' => 4096,
            'system'     => $system_prompt,
            'messages'   => array(
                array(
                    'role'    => 'user',
                    'content' => $user_prompt,
                ),
            ),
        ) );

        $ch = curl_init( 'https://api.anthropic.com/v1/messages' );
        curl_setopt_array( $ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'x-api-key: ' . $api_key,
                'anthropic-version: 2023-06-01',
            ),
            CURLOPT_TIMEOUT        => 120,
        ) );

        $response = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $error = curl_error( $ch );
        curl_close( $ch );

        if ( $error ) {
            self::log( "cURL error: {$error}" );
            return false;
        }

        if ( 200 !== $http_code ) {
            self::log( "API HTTP {$http_code}: " . substr( $response, 0, 500 ) );
            return false;
        }

        $data = json_decode( $response, true );

        if ( ! isset( $data['content'][0]['text'] ) ) {
            self::log( 'Unexpected API response structure' );
            return false;
        }

        return $data['content'][0]['text'];
    }

    /**
     * Parse the AI response JSON into article data.
     *
     * @param string $response Raw JSON string from API.
     * @return array|false     Parsed article data or false.
     */
    private static function parse_response( $response ) {
        // Clean potential markdown code fences.
        $response = trim( $response );
        $response = preg_replace( '/^```json\s*/i', '', $response );
        $response = preg_replace( '/\s*```$/', '', $response );

        $article = json_decode( $response, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            self::log( 'JSON parse error: ' . json_last_error_msg() );
            self::log( 'Raw response (first 500 chars): ' . substr( $response, 0, 500 ) );
            return false;
        }

        // Validate required fields.
        $required = array( 'title', 'slug', 'excerpt', 'category', 'difficulty', 'content' );
        foreach ( $required as $field ) {
            if ( empty( $article[ $field ] ) ) {
                self::log( "Missing required field: {$field}" );
                return false;
            }
        }

        // Ensure tags is an array.
        if ( ! isset( $article['tags'] ) || ! is_array( $article['tags'] ) ) {
            $article['tags'] = array();
        }

        return $article;
    }

    /**
     * Simple log.
     */
    private static function log( $message ) {
        $time = date( 'Y-m-d H:i:s' );
        $log = "[{$time}] GENERATOR: {$message}\n";
        file_put_contents( __DIR__ . '/data/auto_publish.log', $log, FILE_APPEND );
    }
}
