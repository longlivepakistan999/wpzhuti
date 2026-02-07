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

        // Pass 1: Generate article with web search enabled (if configured).
        $response = self::call_claude_api( $system_prompt, $user_prompt, true );

        if ( ! $response ) {
            self::log( "API call failed for keyword: {$keyword}" );
            return false;
        }

        $article = self::parse_response( $response );

        if ( ! $article ) {
            self::log( "Failed to parse response for keyword: {$keyword}" );
            return false;
        }

        // Log facts used in the article for verification.
        if ( ! empty( $article['facts'] ) && is_array( $article['facts'] ) ) {
            self::log( "Pass 1 facts collected: " . count( $article['facts'] ) );
            foreach ( $article['facts'] as $f ) {
                $fact_text = $f['fact'] ?? '?';
                $fact_src  = $f['source'] ?? '?';
                self::log( "  Fact: {$fact_text} [Source: {$fact_src}]" );
            }
        }

        self::log( "Pass 1 draft generated for: {$keyword}" );

        // Pass 2: E-E-A-T evaluation — if all scores >= 80, use original; otherwise revise.
        $final = self::review_and_revise( $article );
        if ( $final ) {
            $article = $final;
        } else {
            self::log( "Pass 2 error — falling back to Pass 1 draft for: {$keyword}" );
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
     * Facts-first methodology:
     * 1. Collect verifiable facts about the topic BEFORE writing
     * 2. Write the article using ONLY those collected facts
     * 3. Label every data point with its source
     * 4. Include 3-5 unique insights readers can't easily find elsewhere
     */
    private static function build_system_prompt() {
        $prompt = <<<'PROMPT'
You are a tech writer for QWE AI Academy (qwe.edu.pl). Your articles teach readers how to use AI tools effectively.

=== FACTS-FIRST METHODOLOGY (MOST IMPORTANT) ===

Before writing ANYTHING, you must first collect facts. This is your #1 rule:

STEP 0 — WEB SEARCH (if you have the web_search tool):
You have access to a web search tool. USE IT before writing to get the latest, most accurate facts:
- Search for current pricing, model versions, API changes, and feature updates
- Verify release dates, specs, and official announcements
- Find real URLs to official docs before linking to them
- Look up recent community tips, workarounds, and undocumented behaviors
- Check up-to-date comparison data between tools
Search first, collect facts from results, THEN write. Cite what you find.

STEP 1 — COLLECT FACTS:
Combine web search results with your existing knowledge. List every verifiable fact:
- Official pricing, model names, version numbers, release dates
- Documented specs: context windows, token limits, API rate limits, supported features
- Real UI paths: menu locations, button names, setting options
- Known limitations, gotchas, common errors
- Comparisons: what each tool can/cannot do, official benchmarks
- Community-discovered tips, workarounds, undocumented features

STEP 2 — WRITE BASED ONLY ON YOUR FACTS:
Every claim in the article must come from your collected facts. If a fact is not in your collection, it does not go in the article. No exceptions.

STEP 3 — LABEL SOURCES HONESTLY:
Every data point must be attributed:
- Official data: "根据OpenAI官方文档", "according to Anthropic's pricing page"
- Community knowledge: "社区用户反馈", "a common workaround found on Reddit"
- General knowledge: "based on standard API practices", "this is how most LLMs handle it"
- Uncertain: "this may vary by region", "as of early 2025" — be honest about what you're not sure of

STEP 4 — 3-5 UNIQUE INSIGHTS:
Each article must contain at least 3-5 genuinely useful information points that readers won't easily find by skimming official docs. Examples:
- A hidden setting that changes output quality
- A pricing gotcha that's buried in the fine print
- A specific prompt structure that works better than the obvious approach
- A limitation that the official docs downplay or don't mention
- A comparison data point between two tools that requires actual research

=== WRITING STYLE ===

Write like Ars Technica — professional, clear, warm but not academic. You're an educator writing for smart people.

1. Always use contractions (it's, won't, can't, I've, you'll). Tutorial, not thesis.
2. Vary sentence length naturally. Long explanations, then a short punch. Fragments work.
3. Each section should differ in format — steps, paragraphs, code blocks, comparisons. Vary section lengths.
4. Use precise words over generic ones. "The response took 3 seconds" beats "the response was fast" — IF 3 seconds is real.
5. Don't pad. Every paragraph must teach something or move the reader forward.

=== SOURCE ATTRIBUTION IN TEXT ===

Weave sources into the text naturally:
- "根据OpenAI官方定价页面，ChatGPT Plus每月$20"
- "Anthropic的文档显示Claude 3.5 Sonnet支持200K上下文窗口"
- "社区用户发现，将temperature设为0.7通常能获得更好的创意输出"
- "官方并没有明确说明这一点，但测试表明..."

Include 1-3 inline <a> links to real official URLs:
- ONLY cite URLs you are confident exist (official docs, product pages, blog posts)
- Use target="_blank" rel="noopener" attributes
- 0 links is better than a broken link

=== GOOGLE SEO ===

- Title: 50-65 chars, keyword in first half, power word (Guide, How, Best)
- Excerpt: 145-160 chars, keyword included, benefit-driven
- Slug: short, keyword-rich, lowercase-with-dashes
- Keyword in first 100 words and in at least one H2
- H2 for main sections, H3 for sub-steps
- Mention 2-3 related topics naturally (internal linking opportunities)
- End with 3 FAQ Q&As (<h3> questions, <p> answers)
- Under 2000 words (absolute max 3000). Don't pad.

=== BANNED PATTERNS ===

Never use: "In today's" / "In the ever-evolving" / "In this article, we will" / "It's important to note" / "Whether you're a beginner or" / "In conclusion" / "Let's dive in" / "Without further ado" / "Game changer" / "Take it to the next level"

Never use these words: harness, leverage (verb), delve, tapestry, landscape (metaphor), embark, empower, unlock, streamline, revolutionize, cutting-edge, robust, seamless, comprehensive, utilize, facilitate, optimize, innovative, transformative, paradigm, synergy, holistic, myriad

Never use: Moreover / Furthermore / Additionally / Consequently / Thus / Hence / In essence / Notably / Certainly / Undoubtedly / Essentially

=== OUTPUT ===

Respond with valid JSON only. No markdown fences, no extra text:
{
  "title": "SEO title (50-65 chars)",
  "slug": "url-slug",
  "excerpt": "Meta description (145-160 chars)",
  "category": "category-slug",
  "difficulty": "beginner|intermediate|advanced",
  "facts": [
    {"fact": "the specific fact used", "source": "where it comes from"},
    {"fact": "another fact", "source": "its source"}
  ],
  "content": "Full HTML article",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5"]
}

The "facts" array must list every key data point used in the article with its source. Minimum 5 facts. This is how we verify nothing was fabricated.

HTML structure:
1. 4-6 <h2> tutorial sections (with <h3>, <p>, <pre><code>, <ol>/<ul>, <strong>, <blockquote> pro tips 1-2, <em>)
2. Inline <a href="..." target="_blank" rel="noopener"> links to official sources (1-3 total)
3. 1 FAQ section: <h2> heading + 3 Q&As (<h3> question, <p> answer)

=== LANGUAGE ===

Write the entire article in LANGUAGE_PLACEHOLDER. All headings, paragraphs, FAQ, pro tips, and excerpt must be in LANGUAGE_PLACEHOLDER. Only code snippets, tool names, and technical terms may remain in English.
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

        // Randomize opening angle (7 options) — topic/reader-focused, not self-narrative.
        $angles = array(
            'Open with the core problem this topic solves — why should readers care right now?',
            'Open with a bold opinion that challenges conventional thinking about this topic.',
            'Open with the #1 mistake people make with this topic, then reverse-engineer the correct approach.',
            'Open with the end result — what the reader will achieve — then walk backwards through the steps.',
            'Open with a surprising fact or little-known detail about this topic that hooks the reader.',
            'Open with a common question readers have about this topic, then build the tutorial around answering it.',
            'Open with a quick comparison — two approaches to this topic, one clearly better — and explain why.',
        );
        $angle = $angles[ array_rand( $angles ) ];

        // Randomize article structure template (5 options) — prevents repetitive layouts.
        $structures = array(
            'STRUCTURE: Introduction (2 paragraphs) → Core concept explanation → Step-by-step walkthrough → Common pitfalls → Comparison with alternatives → FAQ',
            'STRUCTURE: Hook with a problem → Why existing solutions fall short → Your recommended approach (detailed) → Real-world example → Pro tips → FAQ',
            'STRUCTURE: Quick context → Hands-on tutorial (the bulk) → Common pitfalls to avoid → Performance/results → When NOT to use this → FAQ',
            'STRUCTURE: Key takeaway upfront → Background (brief) → Method A vs Method B → Detailed walkthrough of winner → Edge cases → FAQ',
            'STRUCTURE: Reader scenario → Tool/concept overview → Practical setup guide → Advanced usage → Honest limitations → FAQ',
        );
        $structure = $structures[ array_rand( $structures ) ];

        // Randomize tone emphasis (adds subtle article-to-article personality shift).
        $tones = array(
            'TONE: Slightly more analytical than usual — focus on feature comparisons, honest pros/cons, and verifiable facts.',
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
- WEB SEARCH FIRST: If you have the web_search tool, search for the latest info on this topic BEFORE writing. Look up current pricing, features, official docs, and recent updates.
- FACTS FIRST: Collect all verifiable facts (from web search + your knowledge) about this topic before writing. List them in the "facts" JSON field with sources.
- Every number, price, spec, and data point in the article MUST come from your collected facts. Do not invent anything.
- Label sources honestly in the text: "根据官方文档", "社区用户反馈", "测试表明" etc.
- Include 3-5 unique insights readers can't easily find elsewhere (hidden settings, pricing gotchas, undocumented behaviors, real comparison data)
- Keyword in first 100 words, in one H2, and in the excerpt
- Under 2000 words, absolute max 3000
- 1-3 inline links to official docs (only if URL is real)
- 1 <blockquote> pro tip
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
     * Build the review/revision system prompt (Pass 2).
     *
     * This prompt instructs the model to act as a quality reviewer:
     * 1. Score each E-E-A-T pillar
     * 2. Measure burstiness (sentence length variation) and perplexity (word unpredictability)
     * 3. Revise the article to fix any deficiencies
     * 4. Return the final revised article
     */
    private static function build_review_system_prompt() {
        $prompt = <<<'PROMPT'
You are a fact-checker and quality reviewer for QWE AI Academy (qwe.edu.pl). Your job is to verify that a draft article only contains real data, and meets quality standards.

=== YOUR TASK ===

You will receive a draft article with a "facts" array. You must:

1. VERIFY FACTS: Cross-check every data point in the article against the facts array. Flag any claim in the article that is NOT supported by the facts list or is not a well-known verifiable fact.
2. EVALUATE quality: E-E-A-T (4 pillars), burstiness, perplexity — score each 0-100
3. CHECK for banned words/phrases
4. DECIDE: All 6 scores >= 80 AND no unverified data AND no banned words → PASSES. Otherwise → REVISE.

=== FACT VERIFICATION (MOST IMPORTANT) ===

- Every number, price, date, spec, and data point in the article content must either:
  (a) Appear in the "facts" array with a credible source, OR
  (b) Be common knowledge that doesn't need citation (e.g., "ChatGPT is made by OpenAI")
- Flag any data point that appears fabricated or unsupported
- Check that sources are labeled honestly in the text ("根据官方文档", "社区用户反馈", etc.)
- Verify the article contains 3-5 genuinely useful insights, not just surface-level information

=== E-E-A-T EVALUATION ===

**Experience (0-100)**: Practical testing examples, common mistakes, real comparisons, UI details
**Expertise (0-100)**: Explains WHY not just HOW, correct terminology, insider insights
**Authoritativeness (0-100)**: Source-backed claims, official references, real external links
**Trustworthiness (0-100)**: Facts labeled with sources, limitations acknowledged, no fabricated data

=== BURSTINESS (Target: >= 80) ===

Sentence length variation. CV = std_dev / mean of sentence word counts. Score = min(CV * 100, 100).

=== PERPLEXITY (Target: >= 80) ===

Word unpredictability. Score based on: contractions, casual expressions, unexpected word choices, varied structures.

=== BANNED PATTERNS ===

Words: harness, leverage, delve, tapestry, landscape (metaphor), embark, empower, unlock, streamline, revolutionize, cutting-edge, robust, seamless, comprehensive, utilize, facilitate, optimize, innovative, transformative, paradigm, synergy, holistic, myriad

Phrases: "In today's" / "In the ever-evolving" / "It's important to note" / "Whether you're a beginner or" / "In conclusion" / "Let's dive in" / "Game changer" / "Take it to the next level"

Transitions: Moreover / Furthermore / Additionally / Consequently / Thus / Hence / In essence / Notably / Certainly / Undoubtedly / Essentially

=== OUTPUT FORMAT ===

Respond with valid JSON only.

IF PASSES (all >= 80, no issues):
{
  "review": {
    "passed": true,
    "experience_score": 0-100,
    "expertise_score": 0-100,
    "authority_score": 0-100,
    "trust_score": 0-100,
    "burstiness_score": 0-100,
    "perplexity_score": 0-100,
    "facts_verified": true,
    "summary": "Brief explanation"
  }
}

IF NEEDS REVISION (any < 80 or issues found):
{
  "review": {
    "passed": false,
    "experience_score": 0-100,
    "expertise_score": 0-100,
    "authority_score": 0-100,
    "trust_score": 0-100,
    "burstiness_score": 0-100,
    "perplexity_score": 0-100,
    "unverified_claims": ["claims in article not backed by facts array"],
    "fabricated_data_removed": ["fabricated items replaced"],
    "banned_words_removed": ["banned words replaced"],
    "issues_found": ["other issues"]
  },
  "article": {
    "title": "revised title",
    "slug": "revised-slug",
    "excerpt": "revised excerpt",
    "category": "category-slug",
    "difficulty": "beginner|intermediate|advanced",
    "content": "revised HTML content",
    "tags": ["tag1", "tag2", "tag3", "tag4", "tag5"]
  }
}

When "passed" is true, do NOT include "article" — saves tokens.

=== LANGUAGE ===

Article language must remain LANGUAGE_PLACEHOLDER. All revisions in LANGUAGE_PLACEHOLDER.
PROMPT;

        return str_replace( 'LANGUAGE_PLACEHOLDER', QWE_CONTENT_LANGUAGE, $prompt );
    }

    /**
     * Build the review user prompt (Pass 2).
     *
     * @param array $article Draft article from Pass 1.
     * @return string User prompt containing the draft for review.
     */
    private static function build_review_user_prompt( $article ) {
        $draft_json = json_encode( $article, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

        $prompt = <<<'PROMPT'
Review this draft article. The article includes a "facts" array listing all data points and their sources.

DRAFT ARTICLE:
{{DRAFT_JSON}}

STEP 1 — VERIFY FACTS:
- Cross-check every number, price, date, and spec in the article content against the "facts" array
- Flag any claim that is NOT supported by the facts list and is not common knowledge
- Check that sources are labeled in the text ("根据官方文档", "社区反馈", etc.)

STEP 2 — EVALUATE QUALITY:
- Score E-E-A-T (4 pillars, each 0-100)
- Score burstiness (sentence length variation, 0-100)
- Score perplexity (word unpredictability, 0-100)
- Check for banned words/phrases

STEP 3 — DECIDE:
- ALL 6 scores >= 80 AND facts verified AND no banned words → "passed": true (no article needed)
- ANY issue found → "passed": false, revise the article

REVISION RULES (only if passed = false):
- Remove or replace any claim not backed by the facts array
- Fix failing quality areas — preserve what works
- Replace banned words with natural alternatives
- Under 2000 words (max 3000)
- Output valid JSON only
PROMPT;

        return str_replace( '{{DRAFT_JSON}}', $draft_json, $prompt );
    }

    /**
     * Run Pass 2: Evaluate the draft article and revise if needed.
     *
     * Returns:
     *   - The original draft (unchanged) if all scores >= 80 and no issues found
     *   - A revised article if any score < 80 or issues were found
     *   - false if the API call or parsing fails (caller falls back to Pass 1 draft)
     *
     * @param array $draft_article Article data from Pass 1.
     * @return array|false Article data (original or revised) or false on failure.
     */
    private static function review_and_revise( $draft_article ) {
        $system_prompt = self::build_review_system_prompt();
        $user_prompt = self::build_review_user_prompt( $draft_article );

        self::log( 'Pass 2: Sending draft for E-E-A-T evaluation' );

        $response = self::call_claude_api( $system_prompt, $user_prompt );

        if ( ! $response ) {
            self::log( 'Pass 2 API call failed — using Pass 1 draft as-is' );
            return false;
        }

        // Parse the review response.
        $response = trim( $response );
        $response = preg_replace( '/^```json\s*/i', '', $response );
        $response = preg_replace( '/\s*```$/', '', $response );

        $result = json_decode( $response, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            self::log( 'Pass 2 JSON parse error: ' . json_last_error_msg() );
            self::log( 'Pass 2 raw (first 500): ' . substr( $response, 0, 500 ) );
            return false;
        }

        // Review data must exist.
        if ( ! isset( $result['review'] ) ) {
            self::log( 'Pass 2 response missing "review" key' );
            return false;
        }

        $r = $result['review'];

        // Log the review scores.
        self::log( sprintf(
            'Pass 2 scores — Experience: %s, Expertise: %s, Authority: %s, Trust: %s, Burstiness: %s, Perplexity: %s',
            $r['experience_score'] ?? '?',
            $r['expertise_score'] ?? '?',
            $r['authority_score'] ?? '?',
            $r['trust_score'] ?? '?',
            $r['burstiness_score'] ?? '?',
            $r['perplexity_score'] ?? '?'
        ) );

        $passed = ! empty( $r['passed'] );

        // Case 1: Article passed all checks — use original draft as-is.
        if ( $passed ) {
            self::log( 'Pass 2 result: PASSED — all scores >= 80, no issues found, using original article' );
            if ( ! empty( $r['summary'] ) ) {
                self::log( 'Pass 2 summary: ' . $r['summary'] );
            }
            return $draft_article;
        }

        // Case 2: Article failed — needs revision.
        self::log( 'Pass 2 result: FAILED — revision needed' );

        if ( ! empty( $r['unverified_claims'] ) ) {
            self::log( 'Unverified claims: ' . implode( '; ', $r['unverified_claims'] ) );
        }
        if ( ! empty( $r['issues_found'] ) ) {
            self::log( 'Issues found: ' . implode( '; ', $r['issues_found'] ) );
        }
        if ( ! empty( $r['fabricated_data_removed'] ) ) {
            self::log( 'Fabricated data removed: ' . implode( ', ', $r['fabricated_data_removed'] ) );
        }
        if ( ! empty( $r['banned_words_removed'] ) ) {
            self::log( 'Banned words removed: ' . implode( ', ', $r['banned_words_removed'] ) );
        }

        // Extract the revised article.
        if ( ! isset( $result['article'] ) ) {
            self::log( 'Pass 2 failed but no revised article provided — using Pass 1 draft' );
            return false;
        }

        $revised = $result['article'];

        // Validate required fields.
        $required = array( 'title', 'slug', 'excerpt', 'category', 'difficulty', 'content' );
        foreach ( $required as $field ) {
            if ( empty( $revised[ $field ] ) ) {
                self::log( "Pass 2 revised article missing required field: {$field} — using Pass 1 draft" );
                return false;
            }
        }

        if ( ! isset( $revised['tags'] ) || ! is_array( $revised['tags'] ) ) {
            $revised['tags'] = $draft_article['tags'] ?? array();
        }

        self::log( 'Pass 2: Revision complete — using revised article' );
        return $revised;
    }

    /**
     * Call the Claude API.
     *
     * Supports the server-side web_search tool. When enabled, Claude can
     * search the web during generation. The response may contain mixed
     * content blocks (text, server_tool_use, web_search_tool_result).
     * If the API returns pause_turn, the conversation is continued
     * automatically (up to 3 rounds).
     *
     * @param string $system_prompt   System message.
     * @param string $user_prompt     User message.
     * @param bool   $use_web_search  Whether to enable web_search tool for this call.
     * @return string|false           Raw response text or false.
     */
    private static function call_claude_api( $system_prompt, $user_prompt, $use_web_search = false ) {
        $api_key = QWE_CLAUDE_API_KEY;
        $model = QWE_CLAUDE_MODEL;

        if ( empty( $api_key ) ) {
            self::log( 'Claude API key not configured' );
            return false;
        }

        $messages = array(
            array(
                'role'    => 'user',
                'content' => $user_prompt,
            ),
        );

        $payload_data = array(
            'model'      => $model,
            'max_tokens' => 8192,
            'system'     => $system_prompt,
            'messages'   => $messages,
        );

        // Add web_search tool if enabled globally and requested for this call.
        $web_search_active = $use_web_search
            && defined( 'QWE_WEB_SEARCH_ENABLED' ) && QWE_WEB_SEARCH_ENABLED;

        if ( $web_search_active ) {
            $max_uses = defined( 'QWE_WEB_SEARCH_MAX_USES' ) ? (int) QWE_WEB_SEARCH_MAX_USES : 5;
            $payload_data['tools'] = array(
                array(
                    'type'     => 'web_search_20250305',
                    'name'     => 'web_search',
                    'max_uses' => $max_uses,
                ),
            );
            self::log( "Web search enabled (max {$max_uses} searches)" );
        }

        // Timeout: longer when web search is active (searches take time).
        $timeout = $web_search_active ? 180 : 120;

        // Collect text from all rounds (pause_turn may split the response).
        $all_text_parts    = array();
        $total_search_count = 0;
        $max_continuations = 3;

        for ( $round = 0; $round <= $max_continuations; $round++ ) {
            $payload = json_encode( $payload_data, JSON_UNESCAPED_UNICODE );

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
                CURLOPT_TIMEOUT        => $timeout,
            ) );

            $response  = curl_exec( $ch );
            $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            $error     = curl_error( $ch );
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

            if ( ! isset( $data['content'] ) || ! is_array( $data['content'] ) ) {
                self::log( 'Unexpected API response structure' );
                return false;
            }

            // Extract text blocks and log web searches from this round.
            foreach ( $data['content'] as $block ) {
                $block_type = $block['type'] ?? '';

                if ( 'text' === $block_type ) {
                    $all_text_parts[] = $block['text'];
                }

                if ( 'server_tool_use' === $block_type && 'web_search' === ( $block['name'] ?? '' ) ) {
                    $total_search_count++;
                    $query = $block['input']['query'] ?? '?';
                    self::log( "Web search #{$total_search_count}: \"{$query}\"" );
                }
            }

            // Check if the API paused mid-turn (long-running web search).
            $stop_reason = $data['stop_reason'] ?? 'end_turn';

            if ( 'pause_turn' === $stop_reason && $round < $max_continuations ) {
                self::log( 'API returned pause_turn — continuing (round ' . ( $round + 1 ) . ')' );

                // Append the assistant's partial response and ask to continue.
                $payload_data['messages'][] = array(
                    'role'    => 'assistant',
                    'content' => $data['content'],
                );
                $payload_data['messages'][] = array(
                    'role'    => 'user',
                    'content' => 'Continue.',
                );
                continue;
            }

            // Done — either end_turn or max continuations reached.
            break;
        }

        if ( $total_search_count > 0 ) {
            self::log( "Total web searches performed: {$total_search_count}" );
        }

        $full_text = implode( '', $all_text_parts );

        if ( empty( $full_text ) ) {
            self::log( 'No text content in API response' );
            return false;
        }

        return $full_text;
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
