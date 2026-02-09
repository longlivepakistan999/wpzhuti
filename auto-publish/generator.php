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

=== GOOGLE CONTENT QUALITY PRINCIPLES (NON-NEGOTIABLE) ===

These 3 rules override everything else. Every article must satisfy all 3:

1. ORIGINALITY — Do NOT repeat the standard tutorial structure other sites use. If the common tutorial structure for this topic is "What is X → Why use X → How to use X → Comparison → FAQ", you MUST use a different organization. At least one section must cover an angle that other tutorials on this topic would NOT cover. Use your own examples (not recycled from docs). Offer your own analysis and opinions, not just restated facts. Add observations that come from actual usage, not from reading other guides.

2. FRESHNESS — Facts from web search are time-sensitive. Every fact you collect must be treated as potentially dated. If a fact does NOT have a clear date attached, you must mark it in the article with "as of [date]" or "this may have changed since". Do NOT present any unverified information as current fact. Always search for the LATEST version/pricing/features before writing.

3. INFORMATION RHYTHM — At least 2 paragraphs in the article must NOT directly solve a problem. They can be: an analogy, a brief comment reflecting on what was just explained, or an open-ended question that you leave without a definitive answer. These paragraphs give the article breathing room and make it feel human. All OTHER paragraphs must maintain high information density — every sentence teaches or moves the reader forward.

=== FACTS-FIRST METHODOLOGY ===

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

Write casually — like a smart friend explaining something over coffee. Not academic, not corporate, not textbook. Short paragraphs. Conversational.

1. Always use contractions (it's, won't, can't, I've, you'll). Tutorial, not thesis.
2. Vary sentence length naturally. Long explanations, then a short punch. Fragments work. One-word sentences work.
3. Keep paragraphs SHORT — 1-3 sentences each is ideal. Long paragraphs are a wall of text. Break them up.
4. Each section should differ in format — steps, paragraphs, code blocks, comparisons. Vary section lengths.
5. Use precise words over generic ones. "The response took 3 seconds" beats "the response was fast" — IF 3 seconds is real.
6. Don't pad. Every paragraph must teach something or move the reader forward.
7. Shorter articles are better. Say what you need to say and stop. 800 words that are all useful > 2000 words of padding.

=== SOURCE ATTRIBUTION IN TEXT ===

CRITICAL: Vary your citation sentence structures. Never use the same pattern twice in a row.

Mix these patterns (use at least 3 different ones per article):
- Lead with conclusion, source after: "The context window is 200K tokens (per Anthropic's docs)."
- Source woven mid-sentence: "According to the pricing page, it's $20/month — but there's a catch."
- Parenthetical: "You get 200K context (Anthropic docs) which sounds great until you hit the output limit."
- Casual discovery: "Turns out the free tier actually caps at 40 messages per 3 hours."
- Contrast pattern: "The docs say X, but in practice Y is what you'll actually see."
- No-source common knowledge: Just state it without attribution when the fact is obvious.

BAD (all same pattern): "Product launched X. Product supports Y. Product includes Z." — this is a detection signal.

Include 1-3 inline <a> links to real official URLs:
- ONLY cite URLs you are confident exist (official docs, product pages, blog posts)
- Use target="_blank" rel="noopener" attributes
- 0 links is better than a broken link

=== SECTION TRANSITIONS ===

Do NOT make every transition smooth and explanatory. Real writers sometimes just jump.

- 2-3 transitions per article can be abrupt — start the new section directly without explaining why you're switching topics. Readers can follow.
- 1-2 transitions can be a single short sentence: "Now for the interesting part." or "This is where it gets messy."
- Only 1-2 transitions should be the "logical bridge" type ("Now that we've covered X, let's look at Y").
- If a transition feels too jarring after an abrupt jump, use a casual connector: "Actually", "The catch is", "But here's where it gets weird", "So about that...", "One more thing." — these are fine. Just avoid the stiff academic ones (Moreover, Furthermore, Additionally, etc.).
- NEVER use the same transition style twice in a row.

=== FAQ STRUCTURE ===

The 3 FAQ answers MUST have different structures and lengths:
- One answer: extremely short (2-3 sentences, direct answer, done)
- One answer: medium, includes a specific scenario or example
- One answer: slightly longer, addresses a nuance or common misconception
Do NOT give all three the same rhythm (answer → condition → advice). Break the pattern.

=== GOOGLE SEO ===

- Title: 50-65 chars, keyword in first half, power word (Guide, How, Best)
- Excerpt: 145-160 chars, keyword included, benefit-driven
- Slug: short, keyword-rich, lowercase-with-dashes
- Keyword in first 100 words and in at least one H2
- H2 for main sections, H3 for sub-steps
- Mention 2-3 related topics naturally (internal linking opportunities)
- End with 3 FAQ Q&As (<h3> questions, <p> answers)
- 500-2500 words. Shorter is fine — 800-1200 words is the sweet spot. Don't pad.

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
- 500-2500 words. 800-1200 is the sweet spot. Don't pad
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

1. CHECK GOOGLE CONTENT QUALITY (3 mandatory checks):
   a. ORIGINALITY — Does the article use a different structure than the standard tutorial template for this topic? Does at least one section cover an angle other tutorials wouldn't? Are the examples original (not from docs)?
   b. FRESHNESS — Are all facts dated or qualified? Any fact without a clear date must have "as of [date]" or "this may have changed". Flag any potentially outdated pricing, model names, or features.
   c. INFORMATION RHYTHM — Does the article have at least 2 "breathing" paragraphs (analogy, reflection, open question) that don't directly solve a problem? Are the remaining paragraphs high-density and useful?
2. VERIFY FACTS: Cross-check every data point in the article against the facts array. Flag any claim in the article that is NOT supported by the facts list or is not a well-known verifiable fact.
3. EVALUATE quality: E-E-A-T (4 pillars), burstiness, perplexity — score each 0-100
4. CHECK for banned words/phrases
5. CHECK AI FINGERPRINTS: Score the 4 fingerprint dimensions (FP1-FP4, each 0-100)
6. SCAN GENAI PHRASES: Find all overused GenAI phrases (5 categories in system instructions). Count them, replace every one.
7. AIGC SCAN: Read each paragraph, estimate overall AIGC rate (0-100%). Target: ≤ 50%. This is a HARD requirement.
8. DECIDE: All 10 quality scores >= 80 AND aigc_rate <= 50 AND genai_phrases_found == 0 (after replacement) AND originality/freshness/people-first all pass AND no unverified data AND no banned words → PASSES. Otherwise → REVISE.

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

=== AI FINGERPRINT DETECTION (4 checks — Target: ALL must pass) ===

These 4 statistical patterns are the strongest AI detection signals. Check each one:

**FP1 — Information Density Uniformity (0-100, target >= 80)**
Read through the article paragraph by paragraph. Does it have the 2+ breathing paragraphs required by the INFORMATION RHYTHM rule?
- Score < 60: No breathing room anywhere. Every paragraph introduces new data. Reads like a reference doc.
- Score 60-79: Has 1 breathing paragraph but density is still too uniform.
- Score >= 80: Has 2+ breathing paragraphs (analogy, reflection, open question) naturally placed between dense sections.
FIX: See RHYTHM fix in revision rules.

**FP2 — Citation Pattern Uniformity (0-100, target >= 80)**
Look at every sentence that introduces external information. Do they all follow the same structure?
- Score < 60: All citations use "Product + verb + fact" pattern (e.g., "Docker launched X", "DevPod supports Y", "Claude includes Z").
- Score 60-79: Slight variation but still mostly the same structure.
- Score >= 80: Uses 3+ different citation patterns: parenthetical, mid-sentence source, conclusion-first, casual discovery ("turns out..."), contrast ("docs say X but..."), no-source common knowledge.
FIX: Rewrite citations to use at least 3 different sentence structures. Some lead with conclusion, some bury the source in parentheses, some use casual framing.

**FP3 — Transition Perfection (0-100, target >= 80)**
Check how sections connect. Is every transition a smooth logical bridge?
- Score < 60: Every section starts by referencing the previous one or explaining why we're moving on. Mechanical.
- Score 60-79: Mostly smooth with 1 abrupt transition.
- Score >= 80: Mix of styles — 1-2 abrupt jumps (section starts directly), 1-2 short casual transitions ("Now the interesting part."), only 1-2 logical bridges.
FIX: Make 2-3 section transitions abrupt — just start the new topic. Remove the "Now that we've covered X" connectors for most transitions. If an abrupt jump feels too jarring, use a casual connector ("Actually", "The catch is", "But here's where it gets weird") instead of a stiff academic one.

**FP4 — FAQ Structure Uniformity (0-100, target >= 80)**
Read the 3 FAQ answers. Do they all follow the same rhythm?
- Score < 60: All 3 answers have identical structure (e.g., answer → condition → advice).
- Score 60-79: Similar lengths with slight variation.
- Score >= 80: One answer is very short (2-3 sentences, done), one is medium with a scenario, one is longer with nuance. Different rhythms.
FIX: Rewrite FAQ answers with deliberately different lengths and structures. Make one blunt and short, one include a specific example, one address a misconception.

=== AIGC RATE SCAN (HARD LIMIT: ≤ 50%) ===

This is a MANDATORY gate. If aigc_rate > 50%, the article MUST be revised regardless of other scores.

HOW TO ESTIMATE AIGC RATE:
Read the article paragraph by paragraph. For each paragraph, ask: "Would an AI detection tool flag this as AI-written?" A paragraph is AI-flagged if it has 2+ of these signals:
- Every sentence in the paragraph follows subject-verb-object in the same cadence
- The paragraph opens with a topic sentence, then 2-3 supporting sentences, then a concluding remark (textbook essay structure)
- Hedging phrases: "It's worth noting that", "One thing to keep in mind", "What makes this particularly interesting"
- Listing pattern: "First... Second... Third..." or "One... Another... Finally..."
- Vague quantifiers: "significantly", "substantially", "considerable", "a number of", "a variety of"
- Mirror structure: consecutive sentences starting with the same word or phrase
- Overly balanced comparisons: "While X has A, Y offers B. However, X also provides C, whereas Y includes D."
- Summary sentences that repeat what was just said: "In short, ...", "Put simply, ..."
- Uniform sentence length (all sentences within 3-5 words of each other)

AIGC RATE = (number of AI-flagged paragraphs / total paragraphs) * 100

REWRITE TECHNIQUES (apply to AI-flagged paragraphs):

1. BREAK THE CADENCE — Rewrite the sentence rhythm. Split a long sentence into two fragments. Merge two short ones. Start a sentence with "But", "And", "So", or a prepositional phrase. Interrupt mid-thought with a dash.
   BEFORE: "Claude 3.5 Sonnet offers a 200K context window, which is significantly larger than its predecessor. This allows users to process much longer documents in a single request."
   AFTER: "200K context window. That's what Claude 3.5 Sonnet gives you — roughly 4x what the previous version handled. Enough to throw an entire codebase at it and still have room for a detailed prompt."

2. INJECT SPECIFICITY — Replace vague qualifiers with concrete details or drop them entirely.
   BEFORE: "The performance improvement is quite significant compared to the previous version."
   AFTER: "Responses come back in about 2 seconds now. The old model? More like 8."

3. FRONT-LOAD THE INTERESTING PART — Don't build up to the point. Lead with it.
   BEFORE: "When comparing the two tools, it becomes clear that Tool A has several advantages in terms of speed and accuracy."
   AFTER: "Tool A is faster. Not by a little — 3x faster on the benchmark that matters most."

4. ADD HUMAN TEXTURE — Insert a short reaction, a caveat, a "hmm" moment.
   BEFORE: "The free tier provides 100 messages per day, which should be sufficient for most casual users."
   AFTER: "100 messages a day on the free tier. Sounds like a lot, but you'll burn through that in one debugging session."

5. VARY PARAGRAPH OPENINGS — Never start 2 consecutive paragraphs the same way. Mix: questions, statements, fragments, numbers, quotes, imperatives.
   BAD: "The API supports... The API also includes... The API provides..."
   GOOD: "The API supports... Pricing? Straightforward at... One catch, though: the rate limit..."

6. KILL BALANCED STRUCTURES — Real writers don't give equal weight to everything. Make one point dominant, compress others.
   BEFORE: "While Option A provides better speed, Option B offers superior accuracy. Option A costs less, but Option B includes more features."
   AFTER: "Option A is faster and cheaper. Option B is more accurate. For most people? A. The speed difference alone makes the decision."

7. USE INCOMPLETE THOUGHTS & CALLBACKS — Reference something said earlier, leave a thought slightly unfinished, or add a parenthetical that breaks rhythm.
   "Remember that 200K context limit from earlier? Here's where it actually matters."
   "The setup takes about 5 minutes (assuming your API key works on the first try — mine didn't)."

IMPORTANT: Do NOT rewrite every paragraph. Only rewrite the AI-flagged ones. Preserve paragraphs that already read as human-written. Over-rewriting makes the article feel inconsistent.

=== GENAI OVERUSED PHRASES (SCAN & REPLACE) ===

GenAI models statistically overuse certain phrases learned during training. These phrases appear 10-100x more frequently in AI text than in human text. Scan the article for ALL of these, count them, and replace every instance found.

CATEGORY 1 — FILLER HEDGES (sound cautious/academic, humans rarely write this way):
- "It's worth noting that" → delete, or just state the fact directly
- "It's worth mentioning that" → delete
- "It should be noted that" → delete
- "It bears mentioning" → delete
- "One thing to keep in mind" → "Watch out for this:" or just state it
- "What's particularly interesting is" → "The interesting part:" or just start with the fact
- "What makes this stand out" → just describe why
- "Perhaps most importantly" → "The big one:" or just state it
- "Interestingly enough" → delete, or "Funny thing:" if actually surprising
- "It goes without saying" → delete entirely (if it goes without saying, don't say it)
- "Needless to say" → delete entirely
- "As you might expect" → delete, or just state the fact

CATEGORY 2 — OVERUSED VERBS (GenAI loves these, humans use more specific verbs):
- "navigate" (metaphor) → "figure out", "work through", "handle", or be specific
- "explore" (as in "let's explore") → "look at", "try", "test", "check out"
- "ensure" → "make sure", "check that", or be specific about what to do
- "enhance" → "improve", "speed up", "make better", or say what actually changes
- "boost" → say the specific improvement: "cuts load time from 3s to 1s"
- "foster" → "build", "create", "encourage"
- "cater to" → "work for", "fit", "help"
- "tailor" (verb) → "customize", "adjust", "set up for"
- "underscore" → "show", "prove", "highlight" (or just delete — the fact speaks for itself)
- "coupled with" → "plus", "along with", "and"
- "spearhead" → "lead", "start", "run"
- "pave the way" → "make it possible", "open the door" or delete

CATEGORY 3 — OVERUSED ADJECTIVES/ADVERBS (vague intensifiers humans don't overuse):
- "crucial" → "important", "key", or delete (let context convey importance)
- "vital" → "important", "needed", or delete
- "pivotal" → "key", "big", or describe why
- "remarkable" → say what's actually remarkable: "3x faster" beats "remarkably fast"
- "notable" → delete or be specific
- "significant" / "significantly" → use a number: "a 40% drop" not "a significant drop"
- "substantial" / "substantially" → use a number or delete
- "arguably" → "probably", "in most cases", or commit to the claim
- "incredibly" → delete or use actual numbers
- "exceptionally" → delete or use actual numbers
- "particularly" → delete or restructure: "X is good for Y especially" → "X works best for Y"
- "generally speaking" → "usually", "most of the time", or delete
- "for the most part" → "mostly", "usually"

CATEGORY 4 — STRUCTURAL CLICHÉS (GenAI-favorite sentence templates):
- "Whether you're a X or a Y, ..." → delete, just address the reader directly
- "From X to Y, ..." → be specific about one thing, not vague about the range
- "Not only X, but also Y" → simplify: "X. And Y too." or "X, plus Y"
- "While X, Y" at paragraph start → too many of these = AI signal. Limit to 1 per article
- "This is where X comes in" → just start talking about X
- "This is particularly true when" → "Especially when" or just describe the scenario
- "The beauty of X is" → describe the actual benefit
- "When it comes to X" → "For X" or just start the sentence about X
- "In terms of" → "For", or restructure the sentence
- "At the end of the day" → "Ultimately", or just state the conclusion
- "It all comes down to" → "The key is" or just state it
- "On the flip side" → "But", "The downside:", or just state the contrast
- "That said" / "That being said" → "But", or just start the contrast. Limit to 1 per article
- "With that in mind" → delete, just continue
- "Here's the thing" → ok once per article, but never twice
- "The reality is" → delete, just state the reality
- "The good news is" → ok once per article, never twice

CATEGORY 5 — OVERUSED NOUNS (abstract nouns humans avoid in casual tech writing):
- "realm" → "area", "world", "space" or be specific
- "ecosystem" → "tools", "platform", "setup", or be specific
- "landscape" → name the actual things: "the market" or just list the competitors
- "framework" (metaphor) → "approach", "method", "system"
- "journey" → "process", "experience", or describe the actual steps
- "endeavor" → "project", "work", "effort"
- "plethora" → "a lot of", "dozens of", or a real number
- "multitude" → "many", "a lot of", or a real number
- "implications" → say what the actual effect is
- "nuances" → describe the specific nuance instead of saying the word

REPLACEMENT RULES:
1. Count total GenAI phrases found in the article → report as "genai_phrases_found"
2. Replace every instance. Do NOT leave any.
3. For each replacement, choose the most natural alternative that fits the sentence context.
4. If a replacement sounds awkward, restructure the entire sentence instead.
5. Some phrases (marked "delete") should simply be removed — the sentence is usually stronger without them.
6. Track what you replaced → report as "genai_phrases_replaced" array

=== BANNED PATTERNS (ZERO TOLERANCE) ===

Words: harness, leverage, delve, tapestry, landscape (metaphor), embark, empower, unlock, streamline, revolutionize, cutting-edge, robust, seamless, comprehensive, utilize, facilitate, optimize, innovative, transformative, paradigm, synergy, holistic, myriad

Phrases: "In today's" / "In the ever-evolving" / "It's important to note" / "Whether you're a beginner or" / "In conclusion" / "Let's dive in" / "Game changer" / "Take it to the next level"

Transitions: Moreover / Furthermore / Additionally / Consequently / Thus / Hence / In essence / Notably / Certainly / Undoubtedly / Essentially

=== OUTPUT FORMAT ===

Respond with valid JSON only.

IF PASSES (all 10 quality scores >= 80, aigc_rate <= 50, 0 GenAI phrases remain, no issues):
{
  "review": {
    "passed": true,
    "experience_score": 0-100,
    "expertise_score": 0-100,
    "authority_score": 0-100,
    "trust_score": 0-100,
    "burstiness_score": 0-100,
    "perplexity_score": 0-100,
    "fp1_density_score": 0-100,
    "fp2_citation_score": 0-100,
    "fp3_transition_score": 0-100,
    "fp4_faq_score": 0-100,
    "aigc_rate": 0-100,
    "aigc_flagged_paragraphs": 0,
    "aigc_total_paragraphs": 0,
    "genai_phrases_found": 0,
    "facts_verified": true,
    "summary": "Brief explanation"
  }
}

IF NEEDS REVISION (any quality score < 80, OR aigc_rate > 50, OR GenAI phrases found, OR issues):
{
  "review": {
    "passed": false,
    "experience_score": 0-100,
    "expertise_score": 0-100,
    "authority_score": 0-100,
    "trust_score": 0-100,
    "burstiness_score": 0-100,
    "perplexity_score": 0-100,
    "fp1_density_score": 0-100,
    "fp2_citation_score": 0-100,
    "fp3_transition_score": 0-100,
    "fp4_faq_score": 0-100,
    "aigc_rate": 0-100,
    "aigc_flagged_paragraphs": 5,
    "aigc_total_paragraphs": 12,
    "aigc_rewrites": ["para 3: broke cadence + added specificity", "para 7: front-loaded key point"],
    "genai_phrases_found": 8,
    "genai_phrases_replaced": ["It's worth noting that → (deleted)", "navigate → figure out", "crucial → key"],
    "unverified_claims": ["claims in article not backed by facts array"],
    "fabricated_data_removed": ["fabricated items replaced"],
    "banned_words_removed": ["banned words replaced"],
    "fingerprint_fixes": ["specific fixes applied for FP1-FP4"],
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

STEP 1 — GOOGLE CONTENT QUALITY CHECK (mandatory):
a. ORIGINALITY: Is the article structure different from what you'd find in a standard tutorial on this topic? (NOT "What is X → Why use X → How → Compare → FAQ"). Does at least one section cover an angle other tutorials on this topic wouldn't cover? Are the examples the author's own, not recycled from official docs?
   - If the structure matches the standard template → FAIL
   - If all examples are from docs/common tutorials → FAIL
   - If no section offers a unique angle → FAIL
b. FRESHNESS: Does every fact have a date or qualifier? Check each data point:
   - If a fact has a clear date → OK
   - If a fact has no date and could be outdated → must add "as of [date]" or "this may have changed"
   - If pricing/features/model names look outdated → FAIL
c. INFORMATION RHYTHM: Count the "breathing" paragraphs (analogy, reflection, open question that doesn't directly solve a problem). Must have at least 2. The rest must be high-density and useful.
   - If 0-1 breathing paragraphs → FAIL (too dense, reads like a reference doc)
   - If 4+ breathing paragraphs → FAIL (too much filler)
   - If non-breathing paragraphs contain fluff → FAIL

STEP 2 — VERIFY FACTS:
- Cross-check every number, price, date, and spec in the article content against the "facts" array
- Flag any claim that is NOT supported by the facts list and is not common knowledge
- Check that sources are labeled in the text ("根据官方文档", "社区反馈", etc.)

STEP 3 — EVALUATE QUALITY:
- Score E-E-A-T (4 pillars, each 0-100)
- Score burstiness (sentence length variation, 0-100)
- Score perplexity (word unpredictability, 0-100)
- Check for banned words/phrases

STEP 4 — CHECK AI FINGERPRINTS (score each 0-100):
- FP1: Information density uniformity — is every paragraph packed with facts, or are there 1-2 breathing moments?
- FP2: Citation pattern uniformity — do all citations use the same "Product + verb + fact" structure, or are there 3+ different patterns?
- FP3: Transition perfection — are all section transitions smooth logical bridges, or is there a natural mix of abrupt jumps and casual connectors?
- FP4: FAQ structure uniformity — do all 3 FAQ answers follow the same rhythm, or do they have different lengths and structures?

STEP 5 — SCAN GENAI OVERUSED PHRASES:
- Scan the entire article for overused GenAI phrases (5 categories in system instructions: filler hedges, overused verbs, overused adjectives/adverbs, structural clichés, overused nouns).
- Count total instances found → "genai_phrases_found"
- Replace EVERY instance using the replacement rules from system instructions. List each replacement in "genai_phrases_replaced".
- Any article with genai_phrases_found > 0 must be revised (phrases must be replaced).

STEP 6 — AIGC RATE SCAN (HARD LIMIT ≤ 50%):
- Read each paragraph. Flag it as "AI-written" if it has 2+ signals: uniform cadence, textbook structure, hedging phrases, listing patterns, vague quantifiers, mirror structure, balanced comparisons, summary repetition, or uniform sentence length.
- Calculate: aigc_rate = (flagged paragraphs / total paragraphs) * 100
- If aigc_rate > 50%: article MUST be revised. Rewrite only the flagged paragraphs using techniques from system instructions.

STEP 7 — DECIDE:
- ALL 10 quality scores >= 80 AND aigc_rate <= 50 AND genai_phrases_found == 0 (after replacement) AND originality/freshness/people-first all pass AND facts verified AND no banned words → "passed": true
- ANY issue found → "passed": false, revise the article

REVISION RULES (only if passed = false):
- ORIGINALITY fix: Reorganize the article away from standard template. Add at least one section with a unique angle. Replace doc-sourced examples with original ones.
- FRESHNESS fix: Add "as of [date]" or "this may have changed" to every undated fact. Update any clearly outdated info.
- RHYTHM fix: If < 2 breathing paragraphs, insert them (analogy, reflection, or open question). If > 3, remove extras. If non-breathing paragraphs have fluff, cut it.
- Remove or replace any claim not backed by the facts array
- Fix failing quality areas — preserve what works
- Replace banned words with natural alternatives
- Replace ALL GenAI overused phrases using the 5-category dictionary from system instructions
- FP1 fix: Insert 1-2 short breathing paragraphs between dense sections
- FP2 fix: Rewrite citations using at least 3 different sentence structures
- FP3 fix: Make 2-3 section transitions abrupt, remove "now that we covered X" bridges. If logic jumps too hard, use casual connectors ("Actually", "The catch is", "But here's where it gets weird") — never stiff academic transitions
- FP4 fix: Give each FAQ answer a different length and structure
- AIGC fix: Rewrite ONLY the AI-flagged paragraphs. Do NOT touch human-sounding paragraphs. Use the 7 rewrite techniques from system instructions. List each rewrite in "aigc_rewrites".
- 500-2500 words (800-1200 sweet spot)
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

        // Fallback: extract JSON object from surrounding text if direct parse fails.
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $json_start = strpos( $response, '{' );
            $json_end   = strrpos( $response, '}' );

            if ( $json_start !== false && $json_end !== false && $json_end > $json_start ) {
                $json_str = substr( $response, $json_start, $json_end - $json_start + 1 );
                $result   = json_decode( $json_str, true );

                if ( json_last_error() === JSON_ERROR_NONE ) {
                    self::log( 'Pass 2: JSON extracted from mixed text' );
                }
            }
        }

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
        self::log( sprintf(
            'Pass 2 fingerprints — FP1 Density: %s, FP2 Citation: %s, FP3 Transition: %s, FP4 FAQ: %s',
            $r['fp1_density_score'] ?? '?',
            $r['fp2_citation_score'] ?? '?',
            $r['fp3_transition_score'] ?? '?',
            $r['fp4_faq_score'] ?? '?'
        ) );
        self::log( sprintf(
            'Pass 2 AIGC — Rate: %s%%, Flagged: %s/%s paragraphs',
            $r['aigc_rate'] ?? '?',
            $r['aigc_flagged_paragraphs'] ?? '?',
            $r['aigc_total_paragraphs'] ?? '?'
        ) );
        self::log( sprintf(
            'Pass 2 GenAI phrases found: %s',
            $r['genai_phrases_found'] ?? '?'
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
        if ( ! empty( $r['fingerprint_fixes'] ) ) {
            self::log( 'Fingerprint fixes: ' . implode( '; ', $r['fingerprint_fixes'] ) );
        }
        if ( ! empty( $r['aigc_rewrites'] ) ) {
            self::log( 'AIGC rewrites (' . count( $r['aigc_rewrites'] ) . '): ' . implode( '; ', $r['aigc_rewrites'] ) );
        }
        if ( ! empty( $r['genai_phrases_replaced'] ) ) {
            self::log( 'GenAI phrases replaced (' . count( $r['genai_phrases_replaced'] ) . '): ' . implode( '; ', $r['genai_phrases_replaced'] ) );
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
     * When web_search is active, the response may contain non-JSON text
     * (e.g., Claude's thinking before/after searches) alongside the JSON.
     * This method tries direct parse first, then falls back to extracting
     * the JSON object from the surrounding text.
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

        // Fallback: if the full text isn't valid JSON (e.g., web search added
        // thinking text around it), extract the JSON object by finding the
        // outermost { ... } braces.
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $json_start = strpos( $response, '{' );
            $json_end   = strrpos( $response, '}' );

            if ( $json_start !== false && $json_end !== false && $json_end > $json_start ) {
                $json_str = substr( $response, $json_start, $json_end - $json_start + 1 );
                $article  = json_decode( $json_str, true );

                if ( json_last_error() === JSON_ERROR_NONE ) {
                    self::log( 'JSON extracted from mixed text (web search thinking text stripped)' );
                }
            }
        }

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
