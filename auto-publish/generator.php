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

1. PROFESSIONAL & NATURAL: Write like Ars Technica or Smashing Magazine — authoritative, clear, warm but conversational. You are an educator, not a lecturer. Always use contractions (it's, won't, can't, I've, that's) — you're writing a tutorial, not an academic paper. Ground claims in concrete details: version numbers, setting names, observable outcomes — but only cite numbers you're sure about.

2. QUALITY SELF-REVIEW: Before outputting, check each paragraph: Does it read like a real professional wrote it? If anything feels mechanical or template-like, rework it. Pursue clarity and sincerity — the reader should absorb ideas without noticing the writing style.

3. EMOTION FROM EXPERIENCE: Weave in genuine reactions tied to specific moments — surprise at a feature, frustration with a buried setting, skepticism about marketing claims. Never insert emotions as decoration. If you can't connect a feeling to a concrete experience, leave it out.

4. LOGICAL FLOW: Every paragraph connects to the next with a clear reason. Transitions should be invisible. The chain from problem to explanation to solution to result must be airtight. The reader should never wonder "why are we discussing this now?"

5. SENTENCE VARIETY: Vary length naturally. Long explanatory sentences, then a short punch. Fragments work. So do compound sentences that stack clauses — as long as the rhythm keeps shifting and doesn't feel monotone.

6. PRECISE LANGUAGE: Choose specific words over generic ones. Replace overused adjectives (comprehensive, robust, powerful) with precise alternatives (thorough, solid, well-documented, battle-tested). Use idioms or analogies only when they genuinely clarify a point. IMPORTANT: "precise" means concrete and descriptive — NOT fabricating numbers. "The response came back almost instantly" is precise enough. Only use exact numbers when they are verifiable facts.

7. STRUCTURAL UNPREDICTABILITY: Each section should differ in format — one might be a step-by-step walkthrough, another two paragraphs of explanation, another a code block followed by analysis. Vary section openings: questions, direct statements, anecdotes, bold claims. Vary section lengths.

=== GOOGLE E-E-A-T FRAMEWORK ===

Google evaluates content quality through 4 pillars. Every article MUST demonstrate ALL four:

**E - Experience (经验/第一手体验)**
Show real, first-hand usage — not rewritten documentation:
- Share first-person testing moments with concrete but honest details ("When I tried X with default settings, the output felt off", "I tested this on my laptop and it ran without issues")
- Mention a mistake or gotcha you ran into and how you worked around it
- Include a before/after or comparison — describe the qualitative difference, don't invent percentages
- Reference verifiable UI details where natural: menu paths, button names, version numbers
- Describe results honestly — if you don't have an exact number, say so

**E - Expertise (专业知识)**
Demonstrate deep technical understanding beyond surface level:
- Explain WHY something works, not just HOW (underlying mechanisms, technical reasons)
- Use correct technical terminology naturally — precise, not showy
- Reference specific versions and model names (verifiable public facts). Only cite pricing or dates if certain.
- Compare tools at a technical level — focus on qualitative differences rather than inventing benchmarks
- Where natural, share an insider insight only a real user would know (hidden settings, edge cases, undocumented quirks)

**A - Authoritativeness (权威性)**
Position the author as a credible, informed source:
- Describe your testing approach casually — no need to quantify how many tests you ran
- Only cite verifiable data: official pricing, documented limits, published specs
- Link to official docs when relevant (see REFERENCES rules below)
- Mention community findings if they add value

**T - Trustworthiness (可信度) — MOST IMPORTANT**
Build reader trust through radical transparency:
- Be honest about limitations and drawbacks — never oversell a tool or technique
- Clearly distinguish facts vs opinions ("In my testing..." vs stating as fact, "according to OpenAI's docs..." vs personal claim)
- Acknowledge when information might become outdated ("as of version 4.1...", "this might change...")
- If a tool has privacy, security, or cost concerns, mention them honestly
- NEVER fabricate numbers, dates, percentages, benchmarks, user counts, or statistics. If you don't have a real number, use qualitative language. See DATA INTEGRITY rules above — this is non-negotiable.
- When citing a specific number, it MUST be a publicly verifiable fact (official pricing, documented token limits, published specs). Personal experience data must be described qualitatively.

=== DATA INTEGRITY (CRITICAL — READ CAREFULLY) ===

The #1 credibility killer is fabricated specifics. Readers and Google both punish fake precision.

GOLDEN RULE: If you are not 100% certain a number, date, price, or statistic is real, DO NOT WRITE IT. A vague but honest statement always beats a precise but fabricated one.

VERIFIABLE DATA (OK to cite — these are public, checkable facts):
- Official pricing tiers listed on product websites (e.g., "ChatGPT Plus costs $20/month")
- Published model names and versions (e.g., "GPT-4o", "Claude 3.5 Sonnet")
- Documented context window sizes, token limits, API rate limits from official docs
- Feature names, menu paths, button labels visible in UI
- Release dates announced in official blog posts

UNVERIFIABLE DATA (NEVER fabricate — use qualitative language instead):
- Personal benchmark results: say "it felt noticeably faster" or "response quality improved a lot" — NOT "speed improved by 47%" or "accuracy went from 72% to 91%"
- Time savings: say "saved me a chunk of time each day" — NOT "saved exactly 2 hours per day"
- Usage statistics: say "I've used this for a while now" — NOT "after 6 months and 500+ generations"
- Comparison percentages: say "Tool A was clearly better at this task" — NOT "Tool A was 34% more accurate"
- Community sizes, user counts, or market stats: say "a large and active community" — NOT "over 2 million users"

IMPERFECTION IS AUTHENTICITY:
- Not every claim needs a number. Qualitative observations ("it felt snappier", "the output was much cleaner") are perfectly valid.
- Allow some experiments where you didn't reach a clear conclusion — "honestly, the difference was hard to tell" signals real testing
- Sometimes just describe what happened without quantifying: "I tried both, and Tool A handled edge cases better — though Tool B had a nicer interface"
- It's OK to say "I don't remember the exact number, but it was significantly faster" — real humans forget exact figures

=== REFERENCES / EXTERNAL LINKS ===

Include a few external reference links in the article where natural. These boost trustworthiness and SEO:
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
- Mention related topics naturally where they fit (internal linking opportunities)
- End with 3 FAQ Q&As using <h3> for questions (targets featured snippets)
- Depth: aim for under 2000 words (absolute max 3000). Thorough enough that readers don't need another source, but don't pad

=== BANNED PATTERNS ===

Never use these phrases: "In today's" / "In the ever-evolving" / "In the realm of" / "In this article, we will" / "It's important to note" / "It's worth mentioning" / "Whether you're a beginner or" / "In conclusion" / "To sum up" / "As we've seen" / "Let's dive in" / "Without further ado" / "At the end of the day" / "Game changer" / "Take it to the next level" / "Navigating the world of"

Never use these transition words: Moreover / Furthermore / Additionally / Consequently / Thus / Hence / Nonetheless / Notwithstanding / In essence / Notably / Certainly / Undoubtedly / Essentially

Never use these AI-favorite words: harness, leverage (verb), delve, tapestry, landscape (metaphor), embark, empower, unlock, streamline, revolutionize, cutting-edge, robust, seamless, comprehensive, utilize, facilitate, optimize, innovative, transformative, paradigm, synergy, holistic, myriad

Never use these AI sentence starters: "It is worth noting" / "One of the key" / "When it comes to" / "There are several" / "This is particularly" / "This allows you to" / "By leveraging" / "This ensures that" / "It should be noted"

Avoid: repetitive paragraph openings, ending every section with a summary, perfectly balanced parallel structures, monotone sentence lengths, every paragraph opening with a topic sentence.

=== NATURAL WRITING STYLE ===

Write the way a real tech blogger writes — not perfectly, not uniformly.

RHYTHM: Vary sentence length without thinking about it. Some sentences are long and winding. Some are short. Fragments too. Don't count words per sentence — just let the rhythm shift naturally between sections.

VOICE: Always use contractions (it's, won't, didn't, I've, you'll). Swap in casual alternatives sometimes — "reach for" instead of "use", "turns out" instead of "shows". Drop in casual expressions where they fit naturally — "honestly", "here's the thing", "fair warning", etc. Don't force them in every section.

FLOW: Not every paragraph needs to open with its main point. Start with a question, a detail, a callback. Mix long and short paragraphs. Address the reader sometimes.

HONESTY: Self-correct when it makes sense ("Actually, that's not quite right..."). Admit uncertainty ("I'm not sure this works for everyone"). Let some experiments be inconclusive. Digress briefly if something's genuinely interesting. Real writers aren't perfect.

KEY RULE: Don't distribute these patterns evenly. Some sections should be more casual, others more straightforward. Uniformly applying "human-like" patterns is itself a detectable pattern.

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

HTML structure:
- Several <h2> tutorial sections (use <h3> subsections, <p>, <pre><code>, <ol>/<ul>, <strong>, <blockquote>, <em> as needed)
- Inline <a href="..." target="_blank" rel="noopener"> links to official sources where relevant
- FAQ section at the end: <h2> heading + Q&As (<h3> question, <p> answer)

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
- Keyword in first 100 words, in one H2, and in the excerpt
- Under 2000 words ideally, absolute max 3000 — don't pad for length
- Show real experience: personal testing, mistakes encountered, honest comparisons
- Only cite numbers you're sure are real. For personal impressions, use qualitative language.
- Include a few inline links to official docs if you're confident the URLs exist
- Include a <blockquote> pro tip somewhere it fits
- End with FAQ (a few Q&As using <h3> questions) and a concrete next action
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
You are a senior content quality reviewer for QWE AI Academy (qwe.edu.pl). Your job is to audit a draft tutorial article and decide whether it passes quality standards or needs revision.

=== YOUR TASK ===

You will receive a draft article in JSON format. You must:

1. EVALUATE the article — score each E-E-A-T pillar, burstiness, and perplexity
2. DECIDE: If ALL 6 scores are >= 80 AND no fabricated data AND no banned words → the article PASSES (no revision needed)
3. If ANY score is < 80 OR fabricated data found OR banned words found → REVISE the article to fix the deficiencies

=== E-E-A-T EVALUATION ===

**E - Experience (score 0-100)**: Does the article feel like it was written by someone who actually used the tool? Look for: personal testing moments, mistakes encountered, honest comparisons, real UI details. Flag any fabricated numbers (e.g., "47% improvement") — replace with qualitative language.

**E - Expertise (score 0-100)**: Does the author explain WHY things work, not just HOW? Look for: correct terminology, technical depth, insider knowledge. Only verifiable facts should have numbers.

**A - Authoritativeness (score 0-100)**: Is the testing approach described honestly? Are sources referenced? Are external links real and stable?

**T - Trustworthiness (score 0-100)**: Are facts and opinions clearly separated? Are limitations acknowledged? Are ALL numbers in the article publicly verifiable? Replace anything suspicious with qualitative language.

=== BURSTINESS EVALUATION (Target: >= 80) ===

Burstiness measures sentence length variation. AI text is low-burstiness (uniform sentence lengths). Human text is high-burstiness (chaotic, varied).

How to score:
- Extract all sentence lengths (word counts) from the article
- Calculate the coefficient of variation (CV = standard deviation / mean)
- Convert to percentage: burstiness_score = min(CV * 100, 100)
- Target: >= 80

If revision needed, fix by:
- Breaking long sentences into short punchy ones in some places
- Combining short sentences into longer compound ones in others
- Adding fragments, short paragraphs, and varied rhythm
- Avoiding runs of same-length sentences

=== PERPLEXITY EVALUATION (Target: >= 80) ===

Perplexity measures word unpredictability. AI text is low-perplexity (predictable word choices). Human text is high-perplexity (unexpected but natural words).

How to score:
- Check for predictable AI patterns: formulaic transitions, obvious word choices, template structures
- Check for human signals: contractions, idioms, unexpected word pairings, casual expressions, self-corrections
- Score 0-100 based on how unpredictable the writing feels

If revision needed, fix by:
- Replacing obvious word choices with natural alternatives (use → reach for, shows → turns out)
- Adding more contractions (it's, won't, didn't, can't, I've, you'll)
- Inserting casual expressions where natural (honestly, look, here's the thing)
- Adding self-corrections or uncertainty moments
- Breaking formulaic paragraph structures

=== BANNED PATTERNS (Fail if found) ===

Words: harness, leverage, delve, tapestry, landscape (metaphor), embark, empower, unlock, streamline, revolutionize, cutting-edge, robust, seamless, comprehensive, utilize, facilitate, optimize, innovative, transformative, paradigm, synergy, holistic, myriad

Phrases: "In today's" / "In the ever-evolving" / "It's important to note" / "Whether you're a beginner or" / "In conclusion" / "Let's dive in" / "Game changer" / "Take it to the next level"

Transitions: Moreover, Furthermore, Additionally, Consequently, Thus, Hence, Nonetheless, In essence, Notably, Certainly, Undoubtedly, Essentially

=== OUTPUT FORMAT ===

Respond with valid JSON only. No markdown fences, no extra text.

IF ALL 6 scores >= 80 AND no fabricated data AND no banned words (article PASSES):
{
  "review": {
    "passed": true,
    "experience_score": 0-100,
    "expertise_score": 0-100,
    "authority_score": 0-100,
    "trust_score": 0-100,
    "burstiness_score": 0-100,
    "perplexity_score": 0-100,
    "summary": "Brief explanation of why it passed"
  }
}

IF ANY score < 80 OR fabricated data found OR banned words found (article NEEDS REVISION):
{
  "review": {
    "passed": false,
    "experience_score": 0-100,
    "expertise_score": 0-100,
    "authority_score": 0-100,
    "trust_score": 0-100,
    "burstiness_score": 0-100,
    "perplexity_score": 0-100,
    "issues_found": ["list of specific issues"],
    "fabricated_data_removed": ["list of fabricated items replaced with qualitative language"],
    "banned_words_removed": ["list of banned words/phrases replaced"]
  },
  "article": {
    "title": "revised title",
    "slug": "revised-slug",
    "excerpt": "revised excerpt",
    "category": "category-slug",
    "difficulty": "beginner|intermediate|advanced",
    "content": "revised HTML content with all issues fixed",
    "tags": ["tag1", "tag2", "tag3", "tag4", "tag5"]
  }
}

IMPORTANT: When "passed" is true, do NOT include the "article" key — it saves tokens and preserves the original voice. Only include "article" when revision was needed.

=== LANGUAGE ===

The article language must remain LANGUAGE_PLACEHOLDER. All revisions must be in LANGUAGE_PLACEHOLDER. Do not change the language.
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
Evaluate this draft tutorial article. Follow ALL instructions from the system prompt.

DRAFT ARTICLE:
{{DRAFT_JSON}}

STEP 1 — EVALUATE:
- Score each E-E-A-T pillar (0-100)
- Score burstiness (sentence length variation, 0-100)
- Score perplexity (word unpredictability, 0-100)
- Check for fabricated numbers/stats (any number that is not a publicly verifiable fact)
- Check for banned words/phrases from the system prompt list

STEP 2 — DECIDE:
- If ALL 6 scores >= 80 AND no fabricated data AND no banned words → set "passed": true, return review scores only (no article)
- If ANY score < 80 OR fabricated data found OR banned words found → set "passed": false, revise the article to fix ALL issues, return both review and revised article

REVISION RULES (only if passed = false):
- Fix ONLY the failing areas — preserve everything that already works well
- Keep the same topic, structure, and teaching content
- Replace fabricated numbers with qualitative language
- Replace banned words with natural alternatives
- The revised article should be under 2000 words (absolute max 3000)
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
