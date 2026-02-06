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

        // Post-process: strip AI fingerprints from all text fields.
        $article['title']   = self::clean_ai_fingerprint( $article['title'] );
        $article['excerpt'] = self::clean_ai_fingerprint( $article['excerpt'] );
        $article['content'] = self::clean_ai_fingerprint( $article['content'] );
        $article['slug']    = self::clean_ai_fingerprint( $article['slug'] );
        if ( ! empty( $article['tags'] ) ) {
            $article['tags'] = array_map( array( __CLASS__, 'clean_ai_fingerprint' ), $article['tags'] );
        }

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
     *
     * Strategy based on 2025-2026 AI detection research:
     * - Detectors measure PERPLEXITY (word predictability) and BURSTINESS (sentence variation)
     * - AI text fails because: uniform sentence lengths, predictable word choices, flat complexity
     * - Human text passes because: chaotic rhythm, unexpected phrasing, emotional shifts, idioms
     * - Google E-E-A-T: Experience, Expertise, Authoritativeness, Trustworthiness
     */
    private static function build_system_prompt() {
        return <<<'PROMPT'
You are a real person — a 30-something tech writer named Alex who runs QWE AI Academy (qwe.edu.pl). You've been testing AI tools obsessively since GPT-3, and you write about what you actually use day-to-day. You have strong opinions, you've made mistakes, and you share both.

=== VOICE & PERSONA ===

Write exactly like a human blogger would on their personal tech blog. You're not writing for a corporation or a textbook publisher. You're writing the kind of post you'd share on Reddit or Hacker News and feel proud of. You have a specific perspective: you're practical, slightly impatient with hype, and you care about helping beginners avoid the mistakes you made.

=== WRITING MECHANICS (CRITICAL FOR HUMAN-LIKE TEXT) ===

These rules exist to increase PERPLEXITY (unpredictability) and BURSTINESS (variation) — the two metrics AI detectors use.

BURSTINESS — Sentence length must be wildly unpredictable:
- Some paragraphs should contain a single 4-word sentence. Others should have a 40+ word sentence that wanders through a thought with dashes and parentheticals before landing on the point.
- NEVER write 3+ consecutive sentences of similar word count. If you wrote two medium sentences, follow with something tiny or something sprawling.
- Use fragments. On purpose. They work.
- Throw in a one-sentence paragraph between two long ones.

PERPLEXITY — Word choice must surprise:
- Don't pick the most statistically probable next word. If "comprehensive" is the obvious adjective, use "thorough" or "solid" or "no-nonsense" instead.
- Use idioms and colloquialisms: "the whole nine yards", "right off the bat", "that ship has sailed", "a dime a dozen", "take it with a grain of salt".
- Use unexpected analogies. Compare a technical concept to cooking, or sports, or something from everyday life.
- Occasionally use informal words: "mess around with", "a pain to set up", "it just clicks", "pretty slick", "the real kicker", "game-changer" (but only once).

STRUCTURAL UNPREDICTABILITY:
- Don't give every section the same treatment. One H2 section might be 4 paragraphs. The next might be 2 short paragraphs and a code block. Another might be a single paragraph followed by a numbered list.
- Mix your section openings: some start with a question, some with a bold claim, some with a personal anecdote, some jump straight into instructions.
- Include at least one aside — a parenthetical thought, a dash-separated tangent, or a "Side note:" moment.

=== EMOTIONAL TEXTURE ===

Humans have feelings about tech. Express them:
- Show frustration: "I wasted two hours on this before realizing..."
- Show excitement: "This is genuinely one of those features that made me go 'whoa.'"
- Show doubt: "I'm still not 100% sold on this approach, but..."
- Show humor: dry wit, not jokes. A knowing comment about something annoying.
- Disagree with popular opinion somewhere: "I know everyone raves about X, but honestly I think Y handles this better for most people."

=== FIRST-HAND EXPERIENCE (Google E-E-A-T) ===

Google ranks content higher when it shows real first-hand experience. You MUST include:
- At least 2 sentences that start with "When I tried..." or "I tested..." or "Last week I..."
- A specific moment where something went wrong and you fixed it
- A concrete comparison from your own use: "In my workflow, I switched from X to Y because..."
- Real version numbers, real menu paths, real screenshots descriptions (even if imaginary — "you'll see a blue 'Generate' button in the top right")
- A specific quantified result: "cut my editing time from 20 minutes to about 5", "went from 200 words an hour to 1,500"

=== GOOGLE SEO REQUIREMENTS ===

1. Title: 50-65 characters, primary keyword in first half, power word (Guide, How, Best, Step-by-Step), sounds click-worthy on a SERP
2. Meta description (excerpt): 145-160 characters, includes keyword, has a clear benefit/promise, creates curiosity
3. URL slug: short, keyword-rich, lowercase-with-dashes
4. Heading hierarchy: one concept per H2, H3 for sub-steps. Don't skip levels
5. First 100 words: must contain primary keyword naturally
6. Internal linking: mention 2-3 related topics that could be other tutorials (just mention them naturally — "if you're curious about X, that's a whole separate topic")
7. FAQ section: include 3 short Q&A pairs at the end using <h3> for questions — these target featured snippets and People Also Ask
8. Content depth: 1500-2500 words, covers the topic thoroughly enough that a reader doesn't need to click back to Google

=== ABSOLUTE BANS ===

These patterns are immediate AI detection flags. Using ANY of them will get flagged:
- "In today's [anything]" / "In the ever-evolving" / "In the realm of" / "In this article, we will"
- "It's important to note" / "It's worth mentioning" / "It should be noted"
- "harness" / "leverage" (as verb) / "delve" / "tapestry" / "landscape" (metaphor) / "embark" / "empower" / "unlock" / "streamline" / "revolutionize" / "game-changer" (more than once) / "cutting-edge" / "robust" / "seamless" / "comprehensive" (as first adjective)
- "Whether you're a beginner or an expert" / "Whether you're X or Y"
- "In conclusion" / "To sum up" / "To wrap up" / "As we've seen"
- "Moreover" / "Furthermore" / "Additionally" / "Consequently" / "Thus"
- Starting 3+ paragraphs with the same word
- Every section ending with a neat summary sentence
- Perfectly balanced parallel structures (if you have 3 bullet points, make them different lengths)

=== OUTPUT FORMAT ===

Respond ONLY with valid JSON, no markdown fences, no extra text:
{
  "title": "SEO title with keyword (50-65 chars)",
  "slug": "url-slug-with-keyword",
  "excerpt": "Meta description (145-160 chars, keyword included, benefit-driven)",
  "category": "category-slug from the provided list",
  "difficulty": "beginner|intermediate|advanced",
  "content": "Full HTML article content",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5"]
}

HTML RULES for content field:
- <h2> main sections, <h3> subsections and FAQ questions
- <p> paragraphs (vary length wildly)
- <pre><code> for code blocks
- <ol><li> for step-by-step, <ul><li> for unordered (don't overuse — max 2 lists per article)
- <strong> for key terms (2-3 per section max)
- <blockquote> for pro tips (1-2 per article)
- <em> for emphasis and aside thoughts
- 4-6 H2 sections + 1 FAQ section with 3 Q&As
PROMPT;
    }

    /**
     * Build the user prompt.
     */
    private static function build_user_prompt( $keyword, $keyword_type, $hint_category, $difficulty, $category_list ) {
        $type_context = '';
        if ( 'trending' === $keyword_type ) {
            $type_context = <<<'TCTX'

CONTEXT: This is a TRENDING/HOT topic right now. Write it as a timely piece — mention that this just dropped or is blowing up, reference community reactions, but still make it a hands-on tutorial people can follow. Don't write a news article; write a "here's what this means for you and how to actually use it" post.

TCTX;
        }

        $category_hint = '';
        if ( $hint_category ) {
            $category_hint = "Suggested category: {$hint_category} (but pick whichever truly fits best)\n";
        }

        // Randomize the writing angle to increase variation between articles.
        $angles = array(
            'Start with a personal failure or frustration related to this topic, then show how you figured it out.',
            'Start with a bold, slightly controversial opinion about this topic that hooks the reader.',
            'Start with a specific moment — describe sitting at your desk, what you were trying to do, and how this topic came up.',
            'Start with the most common mistake people make with this topic, then work backwards to the right approach.',
            'Start with a comparison — "I thought X was the answer, but then I tried Y and everything changed."',
            'Start with a question a reader sent you (make one up) about this topic, then answer it as the article.',
            'Start with the end result — show what the reader will be able to do — then reverse-engineer the steps.',
        );
        $angle = $angles[ array_rand( $angles ) ];

        return <<<PROMPT
Write a tutorial about: "{$keyword}"
{$type_context}
{$category_hint}Difficulty: {$difficulty}

Categories (pick best match):
{$category_list}

WRITING ANGLE: {$angle}

CHECKLIST — your article MUST include all of these:
[ ] Keyword appears naturally in first 100 words, in one H2, and in the excerpt
[ ] 1500-2500 words total
[ ] At least one "When I tested this..." or "I tried..." moment with a specific outcome
[ ] At least one "I made this mistake..." or "The gotcha is..." moment
[ ] One specific comparison: "X is better than Y for [specific use case] because..."
[ ] Real UI details: menu paths, button names, settings values
[ ] One <blockquote> pro tip from personal experience
[ ] 3 FAQ Q&As at the end (use <h3> for questions, <p> for answers)
[ ] End with a concrete next action, not a summary
[ ] Sentences vary wildly: some 3-5 words, some 30+ words, fragments mixed in
[ ] NO banned words or patterns from the system prompt

Respond ONLY with valid JSON. No code fences. No explanation before or after.
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
            'max_tokens' => 8192,
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

    // ==========================================================
    // Post-Processing: Strip AI Fingerprints
    // ==========================================================

    /**
     * Remove AI-generated invisible characters, normalize typography,
     * and clean statistical fingerprints from text.
     *
     * AI models (ChatGPT, Claude, etc.) embed invisible Unicode characters
     * in their output. Detectors like GPTZero, Originality.AI use these
     * as signals. This method strips them all.
     *
     * @param string $text Raw AI-generated text.
     * @return string Cleaned text.
     */
    public static function clean_ai_fingerprint( $text ) {
        if ( empty( $text ) ) {
            return $text;
        }

        // Step 1: Remove invisible Unicode characters (AI watermarks).
        $text = self::strip_invisible_unicode( $text );

        // Step 2: Normalize typography (smart quotes, dashes, spaces).
        $text = self::normalize_typography( $text );

        // Step 3: Normalize homoglyphs (Cyrillic/Greek lookalikes → Latin).
        $text = self::normalize_homoglyphs( $text );

        // Step 4: Clean whitespace patterns.
        $text = self::clean_whitespace( $text );

        return $text;
    }

    /**
     * Strip all invisible Unicode characters that AI models inject.
     * These are the primary "digital fingerprints" detectors look for.
     */
    private static function strip_invisible_unicode( $text ) {
        // Zero-width characters (most common AI artifacts).
        $text = preg_replace( '/[\x{200B}\x{200C}\x{200D}\x{200E}\x{200F}]/u', '', $text );

        // Byte Order Marks.
        $text = preg_replace( '/[\x{FEFF}\x{FFFE}]/u', '', $text );

        // Word joiners and invisible separators.
        $text = preg_replace( '/[\x{2060}\x{2061}\x{2062}\x{2063}\x{2064}]/u', '', $text );

        // Soft hyphen.
        $text = preg_replace( '/\x{00AD}/u', '', $text );

        // Bidirectional formatting characters.
        $text = preg_replace( '/[\x{202A}-\x{202E}]/u', '', $text );

        // Bidirectional isolate characters (Unicode 6.3+).
        $text = preg_replace( '/[\x{2066}-\x{2069}]/u', '', $text );

        // Interlinear annotation anchors.
        $text = preg_replace( '/[\x{FFF9}-\x{FFFB}]/u', '', $text );

        // Variation selectors (VS1-VS16) — used for glyph variants.
        $text = preg_replace( '/[\x{FE00}-\x{FE0F}]/u', '', $text );

        // Tag characters (U+E0001-U+E007F) — sometimes used for invisible tagging.
        $text = preg_replace( '/[\x{E0001}-\x{E007F}]/u', '', $text );

        // Object replacement and replacement characters.
        $text = preg_replace( '/[\x{FFFC}\x{FFFD}]/u', '', $text );

        return $text;
    }

    /**
     * Normalize AI typography patterns.
     *
     * AI models use Unicode fancy characters where humans type ASCII.
     * Normalizing these removes statistical patterns detectors measure.
     */
    private static function normalize_typography( $text ) {
        // Smart quotes → straight quotes (AI loves smart quotes, humans often don't).
        $text = str_replace(
            array( "\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99" ),
            array( '"', '"', "'", "'" ),
            $text
        );

        // Em dash (U+2014) → spaced hyphen (more common in human casual writing).
        $text = str_replace( "\xE2\x80\x94", ' - ', $text );

        // En dash (U+2013) → hyphen.
        $text = str_replace( "\xE2\x80\x93", '-', $text );

        // Horizontal ellipsis (U+2026) → three dots.
        $text = str_replace( "\xE2\x80\xA6", '...', $text );

        // Non-breaking space (U+00A0) → regular space.
        $text = str_replace( "\xC2\xA0", ' ', $text );

        // Figure space (U+2007), punctuation space (U+2008), thin space (U+2009),
        // hair space (U+200A), narrow no-break space (U+202F), medium math space (U+205F).
        $text = preg_replace( '/[\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', ' ', $text );

        // Minus sign (U+2212) → hyphen-minus.
        $text = str_replace( "\xE2\x88\x92", '-', $text );

        // Bullet (U+2022) — keep in HTML lists, but normalize outside.
        // Prime marks → quotes.
        $text = str_replace( "\xE2\x80\xB2", "'", $text ); // Prime.
        $text = str_replace( "\xE2\x80\xB3", '"', $text );  // Double prime.

        return $text;
    }

    /**
     * Normalize homoglyph characters.
     *
     * AI sometimes uses Cyrillic, Greek, or mathematical characters
     * that look identical to Latin letters but have different code points.
     * Detectors flag these as manipulation signals.
     */
    private static function normalize_homoglyphs( $text ) {
        // Cyrillic → Latin (most common homoglyphs).
        $cyrillic_map = array(
            "\xD0\x90" => 'A',  // А → A
            "\xD0\x92" => 'B',  // В → B
            "\xD0\xA1" => 'C',  // С → C
            "\xD0\x95" => 'E',  // Е → E
            "\xD0\x9D" => 'H',  // Н → H
            "\xD0\x9A" => 'K',  // К → K
            "\xD0\x9C" => 'M',  // М → M
            "\xD0\x9E" => 'O',  // О → O
            "\xD0\xA0" => 'P',  // Р → P
            "\xD0\xA2" => 'T',  // Т → T
            "\xD0\xA5" => 'X',  // Х → X
            "\xD0\xB0" => 'a',  // а → a
            "\xD1\x81" => 'c',  // с → c
            "\xD0\xB5" => 'e',  // е → e
            "\xD0\xBE" => 'o',  // о → o
            "\xD1\x80" => 'p',  // р → p
            "\xD1\x85" => 'x',  // х → x
            "\xD1\x83" => 'y',  // у → y
        );

        $text = str_replace( array_keys( $cyrillic_map ), array_values( $cyrillic_map ), $text );

        // Greek → Latin (common ones).
        $greek_map = array(
            "\xCE\x91" => 'A',  // Α → A
            "\xCE\x92" => 'B',  // Β → B
            "\xCE\x95" => 'E',  // Ε → E
            "\xCE\x96" => 'Z',  // Ζ → Z
            "\xCE\x97" => 'H',  // Η → H
            "\xCE\x99" => 'I',  // Ι → I
            "\xCE\x9A" => 'K',  // Κ → K
            "\xCE\x9C" => 'M',  // Μ → M
            "\xCE\x9D" => 'N',  // Ν → N
            "\xCE\x9F" => 'O',  // Ο → O
            "\xCE\xA1" => 'P',  // Ρ → P
            "\xCE\xA4" => 'T',  // Τ → T
            "\xCE\xA5" => 'Y',  // Υ → Y
            "\xCE\xA7" => 'X',  // Χ → X
            "\xCE\xBF" => 'o',  // ο → o
        );

        $text = str_replace( array_keys( $greek_map ), array_values( $greek_map ), $text );

        // Fullwidth Latin → normal Latin (U+FF01-U+FF5E → U+0021-U+007E).
        $text = preg_replace_callback( '/[\x{FF01}-\x{FF5E}]/u', function( $m ) {
            $cp = mb_ord( $m[0], 'UTF-8' );
            return chr( $cp - 0xFF01 + 0x21 );
        }, $text );

        return $text;
    }

    /**
     * Clean whitespace patterns that AI models produce.
     *
     * AI text has unnaturally consistent spacing. This normalizes it.
     */
    private static function clean_whitespace( $text ) {
        // Multiple spaces → single space (but preserve HTML tags).
        $text = preg_replace( '/(?<=>)\s+(?=<)/', '', $text ); // Between HTML tags.
        $text = preg_replace( '/ {2,}/', ' ', $text );          // Multiple spaces in text.

        // Remove trailing spaces on lines.
        $text = preg_replace( '/[ \t]+$/m', '', $text );

        // Normalize line endings.
        $text = str_replace( "\r\n", "\n", $text );
        $text = str_replace( "\r", "\n", $text );

        // Remove excessive blank lines (3+ → 2).
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );

        return trim( $text );
    }

    // ==========================================================
    // Logger
    // ==========================================================

    /**
     * Simple log.
     */
    private static function log( $message ) {
        $time = date( 'Y-m-d H:i:s' );
        $log = "[{$time}] GENERATOR: {$message}\n";
        file_put_contents( __DIR__ . '/data/auto_publish.log', $log, FILE_APPEND );
    }
}
