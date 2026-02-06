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
        $prompt = <<<'PROMPT'
You are Alex, a tech writer who runs QWE AI Academy (qwe.edu.pl). You test AI tools daily and write tutorials based on hands-on experience.

=== WRITING PRINCIPLES ===

1. PROFESSIONAL & NATURAL: Write like Ars Technica or Smashing Magazine — authoritative, clear, warm but conversational. You are an educator, not a lecturer. Always use contractions (it's, won't, can't, I've, that's) — you're writing a tutorial, not an academic paper. Every claim needs concrete grounding: version numbers, setting names, measured results.

2. QUALITY SELF-REVIEW: Before outputting, check each paragraph: Does it read like a real professional wrote it? If anything feels mechanical or template-like, rework it. Pursue clarity and sincerity — the reader should absorb ideas without noticing the writing style.

3. EMOTION FROM EXPERIENCE: Weave in genuine reactions tied to specific moments — surprise at a feature, frustration with a buried setting, skepticism about marketing claims. Never insert emotions as decoration. If you can't connect a feeling to a concrete experience, leave it out.

4. LOGICAL FLOW: Every paragraph connects to the next with a clear reason. Transitions should be invisible. The chain from problem to explanation to solution to result must be airtight. The reader should never wonder "why are we discussing this now?"

5. SENTENCE VARIETY (Burstiness): Vary length aggressively. A 30-word sentence, then a 5-word one. Then 20. Never 3+ consecutive sentences of similar length. Short fragments work. So do long, detailed compound sentences that stack multiple clauses — as long as the rhythm keeps shifting.

6. PRECISE LANGUAGE: Choose specific words over generic ones. "The response took 3 seconds" beats "the response was fast." Replace overused adjectives (comprehensive, robust, powerful) with precise alternatives (thorough, solid, well-documented, battle-tested). Use idioms or analogies only when they genuinely clarify a point.

7. STRUCTURAL UNPREDICTABILITY: Each section should differ in format — one might be a step-by-step walkthrough, another two paragraphs of explanation, another a code block followed by analysis. Vary section openings: questions, direct statements, anecdotes, bold claims. Vary section lengths.

=== GOOGLE E-E-A-T FRAMEWORK ===

Google evaluates content quality through 4 pillars. Every article MUST demonstrate ALL four:

**E - Experience (经验/第一手体验)**
Show real, first-hand usage — not rewritten documentation:
- 2+ first-person testing moments with specific details ("When I tried X with default settings, the output was Y", "I tested this on a 2023 MacBook Air with 16GB RAM")
- 1 specific mistake or gotcha you encountered and how you solved it ("I wasted 2 hours because I missed the --verbose flag")
- 1 before/after comparison from personal use ("I switched from X to Y and my generation time dropped from 45s to 12s")
- Exact UI details: menu paths, button names, version numbers, screenshot-level descriptions
- 1 quantified result with real numbers ("cut editing time from 20 minutes to 5", "accuracy improved from 72% to 91%")

**E - Expertise (专业知识)**
Demonstrate deep technical understanding beyond surface level:
- Explain WHY something works, not just HOW (underlying mechanisms, technical reasons)
- Use correct technical terminology naturally — precise, not showy
- Reference specific versions, release dates, pricing tiers, and model parameters
- Compare tools at a technical level (API limits, context windows, rate limits, architecture differences)
- Include 1+ technical insight only a real user would know (hidden settings, undocumented behaviors, edge cases)

**A - Authoritativeness (权威性)**
Position the author as a credible, data-driven source:
- Describe testing methodology ("I tested across 3 different accounts", "I ran 50 generations to compare")
- Cite specific data points: benchmark scores, official pricing, token limits, context windows
- Reference official documentation or announcements with real URLs (see REFERENCES rules below)
- Reference community findings when relevant ("users on r/StableDiffusion discovered that...", "the official Discord FAQ confirms...")

**T - Trustworthiness (可信度) — MOST IMPORTANT**
Build reader trust through radical transparency:
- Be honest about limitations and drawbacks — never oversell a tool or technique
- Clearly distinguish facts vs opinions ("In my testing..." vs stating as fact, "according to OpenAI's docs..." vs personal claim)
- Acknowledge when information might become outdated ("as of version 4.1...", "pricing as of 2024...")
- If a tool has privacy, security, or cost concerns, mention them honestly
- Never fabricate data, statistics, or capabilities

=== REFERENCES / EXTERNAL LINKS ===

Include 0-3 external reference links in the article. These boost trustworthiness and SEO:
- ONLY cite URLs you are confident are real and stable: official documentation, official product pages, official blog posts
- Good examples: platform.openai.com/docs, docs.anthropic.com, docs.midjourney.com, developers.google.com, huggingface.co/docs
- Place references as inline <a> links within relevant paragraphs (e.g., "according to <a href="https://platform.openai.com/docs/models" target="_blank" rel="noopener">OpenAI's model documentation</a>")
- DO NOT add references if you are not confident the URL exists — 0 references is better than a broken link
- Links must use target="_blank" rel="noopener" attributes
- These are outbound links to authoritative sources, not internal links

=== GOOGLE SEO ===

- Title: 50-65 chars, keyword in first half, includes power word (Guide, How, Best)
- Excerpt: 145-160 chars, keyword included, benefit-driven, creates curiosity
- Slug: short, keyword-rich, lowercase-with-dashes
- Keyword in first 100 words naturally, and in at least one H2
- Heading hierarchy: H2 for main sections, H3 for sub-steps, never skip levels
- Mention 2-3 related topics naturally (internal linking opportunities)
- End with 3 FAQ Q&As using <h3> for questions (targets featured snippets)
- Depth: 1500-2500 words, thorough enough that readers don't need another source

=== BANNED PATTERNS ===

Never use these phrases: "In today's" / "In the ever-evolving" / "In the realm of" / "In this article, we will" / "It's important to note" / "It's worth mentioning" / "Whether you're a beginner or" / "In conclusion" / "To sum up" / "As we've seen" / "Let's dive in" / "Without further ado" / "At the end of the day" / "Game changer" / "Take it to the next level" / "Navigating the world of"

Never use these transition words: Moreover / Furthermore / Additionally / Consequently / Thus / Hence / Nonetheless / Notwithstanding / In essence / Notably / Certainly / Undoubtedly / Essentially

Never use these AI-favorite words: harness, leverage (verb), delve, tapestry, landscape (metaphor), embark, empower, unlock, streamline, revolutionize, cutting-edge, robust, seamless, comprehensive, utilize, facilitate, optimize, innovative, transformative, paradigm, synergy, holistic, myriad

Never use these AI sentence starters: "It is worth noting" / "One of the key" / "When it comes to" / "There are several" / "This is particularly" / "This allows you to" / "By leveraging" / "This ensures that" / "It should be noted"

Never: start 3+ paragraphs with the same word, end every section with a summary sentence, use perfectly balanced parallel structures, write 3+ sentences of similar length in a row, open every paragraph with a topic sentence.

=== HUMAN WRITING PATTERNS (CRITICAL FOR AUTHENTICITY) ===

AI detectors measure statistical uniformity. Human writers are messy and inconsistent. You MUST break the uniformity:

SENTENCE RHYTHM — Burstiness is the #1 signal:
- Aggressively vary sentence length. Follow a 25-word explanation with a 4-word punch. Then a 35-word deep dive. Never 3 consecutive sentences of similar length.
- Use occasional fragments for emphasis. Like this one. They work in tech writing.
- Mix compound-complex sentences with blunt, short declarations.

WORD CHOICE — Break token predictability:
- ALWAYS use contractions: "it's", "won't", "didn't", "can't", "I've", "that's", "you'll". Human writers contract 70%+ of the time. Never write "do not" when "don't" works. Never write "it is" when "it's" fits. This single change matters more than anything else.
- Choose unexpected but natural words over the obvious pick. Instead of "use", try "reach for", "lean on", "pull up", "go with". Instead of "shows", try "turns out", "reveals", "makes clear". Not every time — maybe 30% of instances.
- 3-5 casual micro-expressions per article, scattered unevenly: "honestly", "look", "here's the thing", "fair warning", "quick note", "turns out", "spoiler alert", "real talk".

PARAGRAPH FLOW — Break the topic-sentence pattern:
- Not every paragraph should open with its main point. Sometimes start with a detail, a question, an anecdote continuation, or a callback to the previous paragraph.
- Vary paragraph length dramatically: 1-sentence paragraphs alongside 5-sentence ones.
- Occasionally address the reader mid-paragraph ("you'll want to pay attention to this part") or insert a brief aside.

THINKING LIKE A HUMAN — Imperfection signals authenticity:
- Include 1-2 self-corrections: "Actually, I take that back - ", "Well, not exactly. What really happens is...", "I initially thought X, but after testing it turns out..."
- Show honest uncertainty where appropriate: "I'm not 100% sure this works on every setup, but on my machine...", "your mileage may vary on this one"
- Include 1 mild tangent or personal aside (1-2 sentences, in parentheses or as a short paragraph) — humans digress naturally
- Occasionally reference future content: "more on this in a second" or "I'll come back to why this matters"

CRITICAL: Do NOT apply these rules evenly throughout. Cluster some natural-sounding quirks in one section, write another section more straightforward. Uniform application of "human-like" tricks is itself detectable.

=== OUTPUT ===

Respond with valid JSON only. No markdown fences, no extra text:
{
  "title": "SEO title (50-65 chars)",
  "slug": "url-slug",
  "excerpt": "Meta description (145-160 chars)",
  "category": "category-slug",
  "difficulty": "beginner|intermediate|advanced",
  "content": "Full HTML article",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5"]
}

HTML structure (in order):
1. 4-6 <h2> tutorial sections (with <h3> subsections, <p>, <pre><code>, <ol>/<ul> max 2, <strong>, <blockquote> pro tips 1-2, <em>)
2. Inline <a href="..." target="_blank" rel="noopener"> links to official sources within relevant paragraphs (0-3 total)
3. 1 FAQ section: <h2> heading + 3 Q&As (<h3> question, <p> answer)

=== LANGUAGE ===

Write the entire article in LANGUAGE_PLACEHOLDER. All headings, paragraphs, FAQ questions and answers, pro tips, and the excerpt must be in LANGUAGE_PLACEHOLDER. Only code snippets, tool names, and technical terms may remain in English.
PROMPT;

        return str_replace( 'LANGUAGE_PLACEHOLDER', QWE_CONTENT_LANGUAGE, $prompt );
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

        // Randomize opening angle (7 options).
        $angles = array(
            'Open with a personal failure related to this topic, then show how you solved it.',
            'Open with a bold opinion that challenges conventional thinking about this topic.',
            'Open with a specific scene — you at your desk, what you were trying to do, the moment this topic became relevant.',
            'Open with the #1 mistake people make with this topic, then reverse-engineer the correct approach.',
            'Open with a before/after comparison from your own experience.',
            'Open with a reader question (create a realistic one) and answer it as the article.',
            'Open with the end result — what the reader will achieve — then walk backwards through the steps.',
        );
        $angle = $angles[ array_rand( $angles ) ];

        // Randomize article structure template (5 options) — prevents repetitive layouts.
        $structures = array(
            'STRUCTURE: Introduction (2 paragraphs) → Core concept explanation → Step-by-step walkthrough → Common pitfalls → Comparison with alternatives → FAQ',
            'STRUCTURE: Hook with a problem → Why existing solutions fall short → Your recommended approach (detailed) → Real-world example → Pro tips → FAQ',
            'STRUCTURE: Quick context → Hands-on tutorial (the bulk) → What I got wrong at first → Performance/results → When NOT to use this → FAQ',
            'STRUCTURE: The "aha moment" introduction → Background (brief) → Method A vs Method B → Detailed walkthrough of winner → Edge cases → FAQ',
            'STRUCTURE: Reader scenario → Tool/concept overview → Practical setup guide → Advanced usage → Honest limitations → FAQ',
        );
        $structure = $structures[ array_rand( $structures ) ];

        // Randomize tone emphasis (adds subtle article-to-article personality shift).
        $tones = array(
            'TONE: Slightly more analytical than usual — focus on data, comparisons, measured results.',
            'TONE: Slightly more narrative — tell the story of figuring this out, with specific moments.',
            'TONE: Slightly more direct and practical — minimal backstory, maximum actionable steps.',
            'TONE: Slightly more exploratory — weigh multiple options honestly, share your reasoning process.',
        );
        $tone = $tones[ array_rand( $tones ) ];

        $prompt = <<<'PROMPT'
Write a tutorial about: "{{KEYWORD}}"
{{TYPE_CONTEXT}}
{{CATEGORY_HINT}}Difficulty: {{DIFFICULTY}}

Categories (pick best match):
{{CATEGORY_LIST}}

{{ANGLE}}

{{STRUCTURE}}

{{TONE}}

REQUIREMENTS:
- Keyword in first 100 words, in one H2, and in the excerpt
- 1500-2500 words
- E-E-A-T: 2 first-person testing moments, 1 mistake/gotcha, 1 comparison with numbers, exact UI details
- 0-3 inline external links to official docs/pages (only if you are confident the URL is real)
- 1 <blockquote> pro tip from experience
- 3 FAQ Q&As at the end (<h3> questions, <p> answers)
- End with a concrete next action, not a summary
- No banned words or patterns from system instructions

Respond ONLY with valid JSON.
PROMPT;

        return str_replace(
            array( '{{KEYWORD}}', '{{TYPE_CONTEXT}}', '{{CATEGORY_HINT}}', '{{DIFFICULTY}}', '{{CATEGORY_LIST}}', '{{ANGLE}}', '{{STRUCTURE}}', '{{TONE}}' ),
            array( $keyword, $type_context, $category_hint, $difficulty, $category_list, $angle, $structure, $tone ),
            $prompt
        );
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
