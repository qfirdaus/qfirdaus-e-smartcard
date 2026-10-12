# AI Chatbot Coverage Audit

Date: 2026-06-12

Purpose: record current AI Chatbot coverage, known gaps, and upgrade recommendations for later review.

## Current Coverage

The AI Chatbot currently works as a role-aware user support assistant for IQS-Framework. Its useful coverage comes from three approved context sources:

1. Runtime user context
   - Current user language.
   - Current active role/group.
   - Current page path and page title.
   - Chatbot access mode.
   - Question classification metadata.

2. Visible system context
   - Allowed modules and menus for the current active group.
   - Current page matching against allowed menu paths.
   - Prompt limits are enforced to avoid sending too much navigation data to the AI provider.
   - Current limits: 8 modules, 8 menus per module, 40 total menus.

3. Optional curated knowledge context
   - Reads from `tbl_ai_chat_knowledge` when the table exists.
   - Supports FAQ, SOP, and manual-style content.
   - Filters by language, visibility, group ID/group code, and Super Admin status.
   - Sends only matching visible knowledge items to the provider.

The chatbot does not execute model-generated SQL, does not expose unrestricted database records, and is currently designed as a read-only assistant.

## Supported Runtime Features

- Floating global widget through `public/includes/ai-chatbot-widget.php`.
- Dedicated page through `public/pages/ai-chatbot.php`.
- Message endpoint through `public/ajax/ai-chatbot-message.php`.
- Widget open audit endpoint through `public/ajax/ai-chatbot-event.php`.
- Dynamic provider model fetch endpoint through `public/ajax/ai-chatbot-models.php`.
- Runtime settings under System Settings > AI Chatbot.
- Provider support for:
  - Ollama
  - OpenAI
  - Gemini
  - Grok
  - Groq
  - Anthropic
  - OpenRouter
  - OpenAI-compatible APIs
- Access modes:
  - Super Admin only
  - Selected groups
  - All authenticated users
- Per-minute session rate limit.
- User daily request limit.
- Global daily request limit.
- Usage metadata logging through `tbl_ai_chat_usage` when persistence is enabled.
- Audit events for message completion, failure, rate limit, and widget open.

## Safety Boundaries

The system prompt instructs the assistant to:

- Answer only within the current user role and access context.
- Avoid revealing hidden menus, hidden routes, role structures, or restricted setup steps.
- Avoid asking for or revealing passwords, tokens, cookies, CSRF tokens, API keys, or internal configuration.
- Avoid claiming that it has performed system actions.
- Treat the prototype as read-only.
- Ground system-specific answers in runtime context, visible system context, or curated knowledge context.
- Refuse operational details for sensitive or blocked questions.

This is a good baseline for support usage because the assistant is constrained by application-visible context instead of free database access.

## Current Gaps

1. No knowledge management UI

   The chatbot can read `tbl_ai_chat_knowledge`, but there is no visible admin CRUD page for maintaining FAQ/SOP/manual content. Knowledge content currently needs to be inserted through SQL or another manual process.

2. Keyword-based knowledge retrieval only

   `AiChatbotKnowledgeContext` extracts terms from the message and searches with SQL `LIKE`. This is acceptable for early use, but it will miss semantically similar questions when the wording differs from the stored article.

3. Conversation/session storage is not fully wired

   The SQL schema includes `tbl_ai_chat_session` and `tbl_ai_chat_message`, and the settings UI includes `store_conversations` and `log_message_content`. The current message endpoint records usage metadata, but does not yet write chat sessions or message rows.

4. Page-specific context is still thin

   The widget sends current path and title only. It does not send visible form labels, selected tabs, current modal title, validation messages, table headers, or page-specific help hints. This limits its ability to answer questions such as "what does this field mean?".

5. No administrator review workflow

   Usage metadata records useful signals such as question category, risk, review need, and knowledge item count, but there is no UI to review failed, unknown, or high-risk questions and convert them into curated knowledge.

6. No action execution

   This is intentional for the current safety posture. The assistant cannot create users, reset passwords, update settings, change permissions, or perform workflow actions.

## Upgrade Recommendations

### Priority 1: Knowledge Manager

Build an admin page for `tbl_ai_chat_knowledge` with:

- Title
- Question
- Answer
- Language
- Visibility
- Allowed groups
- Tags
- Priority
- Status: draft, active, archived

This is the highest-impact upgrade because chatbot quality depends heavily on approved help content.

### Priority 2: Review Dashboard

Build a governance dashboard using `tbl_ai_chat_usage.f_requestMetaJson` to review:

- Unknown questions
- Sensitive or blocked questions
- Questions with no knowledge item in prompt
- Frequent troubleshooting questions
- Provider failures and timeout patterns
- Latency by provider/model

The dashboard should help administrators turn real user questions into curated knowledge items.

### Priority 3: Conversation Persistence

Wire `store_conversations` to `tbl_ai_chat_session` and `tbl_ai_chat_message`.

Recommended behavior:

- If `store_conversations` is false, keep current metadata-only behavior.
- If `store_conversations` is true and `log_message_content` is false, store message hash, role, length, status, and metadata only.
- If `log_message_content` is true, store raw message content only for controlled debugging.
- Add retention controls before enabling broad content storage.

### Priority 4: Richer Page Context

Enhance the widget runtime context to include safe visible UI metadata:

- Current page heading.
- Active tab label.
- Visible form labels.
- Current modal title.
- Validation error text.
- Table headings.
- Page help keys where available.

Do not send hidden fields, passwords, CSRF tokens, API keys, or raw form values.

### Priority 5: Semantic Knowledge Search

After the knowledge manager is stable, add semantic retrieval:

- Store embeddings for active knowledge items.
- Search by semantic similarity before or alongside keyword matching.
- Keep existing language and visibility filters.
- Limit returned items before sending them to the model.

This will improve answers when users ask the same thing with different wording.

### Priority 6: Controlled Action Suggestions

Keep the assistant read-only until support quality is stable. If action support is added later, use a strict pattern:

- The model suggests an action.
- Application code checks permission.
- User confirms.
- Server performs the action with normal CSRF and audit controls.
- The model never executes SQL or permission changes directly.

## Practical Upgrade Sequence

1. Build Knowledge Manager.
2. Populate core help articles for login, dashboard, menu navigation, roles/access, manuals, notifications, email templates, page generator, system settings, and AI chatbot settings.
3. Build Review Dashboard.
4. Wire conversation persistence with privacy defaults.
5. Add richer page context.
6. Add semantic search.
7. Consider controlled action suggestions only after review workflow is mature.

## Current Assessment

The foundation is strong for a safe role-aware support assistant. The main limitation is not the provider layer or widget layer; it is the lack of curated content operations. The next best investment is to make it easy for administrators to maintain and improve approved knowledge.
