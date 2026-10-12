# AI Chatbot Implementation Readiness

Date: 2026-06-13

Purpose: record implementation status after completing the AI Chatbot upgrade phases from the 2026-06-12 coverage audit.

## Completed Phases

1. Knowledge Manager
   - Added an admin page for `tbl_ai_chat_knowledge`.
   - Supports create, edit, status changes, delete, language, visibility, allowed groups, tags, and priority.
   - Links are available from System Settings > AI Chatbot.

2. Core Knowledge Population
   - Added a safe baseline SQL seed for common support topics.
   - Seed is idempotent through fixed public IDs and `ON DUPLICATE KEY UPDATE`.

3. Review Dashboard
   - Added a metadata-only governance dashboard for `tbl_ai_chat_usage`.
   - Highlights review queue items, no-knowledge candidates, provider failures, category/outcome volume, and provider latency.

4. Conversation Persistence
   - Added optional session/message persistence for `tbl_ai_chat_session` and `tbl_ai_chat_message`.
   - Default remains metadata-only unless `store_conversations` is enabled.
   - Raw message content is stored only when `log_message_content` is enabled.

5. Richer Page Context
   - Widget now sends safe visible UI metadata: heading, active tab, modal title, form labels, validation/error text, and table headings.
   - Server-side sanitization removes secret-like UI text.
   - No form values or hidden fields are sent.

6. Hybrid Knowledge Retrieval
   - Upgraded retrieval from basic keyword matching to hybrid keyword-ranked retrieval.
   - Adds Malay/English support-term aliases and relevance scoring.
   - Keeps language and visibility filtering before prompt construction.
   - Retrieval now merges approved manual knowledge with active PDF chunks when the PDF schema is available.

7. Controlled Action Suggestions
   - Added app-generated navigation suggestions based only on allowed visible menu context.
   - Suggestions are GET links only.
   - No write/action execution is performed by the chatbot.

8. PDF Knowledge Source Workflow
   - Added PDF-only upload support using the System Settings upload limit.
   - Stores PDF source metadata in `tbl_ai_chat_knowledge_source`.
   - Extracts text to a sidecar `.txt` file and generates draft chunks in `tbl_ai_chat_knowledge_chunk`.
   - Adds controlled `Activate`, `Draft`, and `Archive` actions for PDF sources and their chunks.
   - Chatbot retrieval uses PDF chunks only after the source and chunks are active.

## Verification Performed

Syntax and static checks were run for:

- `public/classes/AiChatbotActionSuggestionService.php`
- `public/classes/AiChatbotConversationRepository.php`
- `public/classes/AiChatbotKnowledgeService.php`
- `public/classes/AiChatbotKnowledgeSourceService.php`
- `public/classes/AiChatbotKnowledgeChunkService.php`
- `public/classes/AiChatbotPdfTextExtractor.php`
- `public/classes/AiChatbotReviewDashboardService.php`
- `public/classes/AiChatbotKnowledgeContext.php`
- `public/classes/AiChatbotService.php`
- `public/classes/AiChatbotUsageRepository.php`
- `public/ajax/ai-chatbot-message.php`
- `public/pages/ai-chatbot-knowledge.php`
- `public/pages/ai-chatbot-review.php`
- `public/pages/partials/tetapan-sistem/tab-ai-chatbot.php`
- `public/assets/js/ai-chatbot-widget.js`

All PHP lint checks passed and the widget JavaScript passed `node --check`.

## Deployment Notes

Apply optional database scripts before enabling the related runtime features:

```text
docs/ai-chatbot-tables-2026-06-11.sql
docs/ai-chatbot-knowledge-tables-2026-06-12.sql
docs/ai-chatbot-knowledge-core-seed-2026-06-13.sql
docs/ai-chatbot-knowledge-pdf-schema-2026-06-13.sql
```

Recommended production defaults:

```text
persist_usage=true
store_conversations=false
log_message_content=false
```

Enable `store_conversations` only after privacy approval and retention policy are agreed.

## Remaining Risks

- Browser/UI testing has not been performed in this pass.
- Database migrations and seed SQL were not executed in this pass.
- No automated integration test currently validates chatbot persistence, PDF upload/extraction, chunk activation, or dashboard rendering.
- PDF text extraction depends on readable text PDFs. Scanned or image-only PDFs still require OCR outside this implementation.
- Embedding/vector semantic retrieval is not implemented; current retrieval is hybrid keyword-ranked.
- Controlled action support is intentionally limited to navigation suggestions only.

## Recommended Next Checks

1. Apply SQL scripts in a staging database.
2. Open System Settings > AI Chatbot and confirm both Knowledge Manager and Review Dashboard links work.
3. Create one active knowledge item and verify chatbot retrieval.
4. Upload a text-based PDF, confirm extraction succeeds, confirm draft chunks are generated, then activate the source.
5. Ask the chatbot a question covered by the active PDF source and verify the answer uses only visible approved context.
6. Send a chatbot message with `store_conversations=false` and confirm only usage metadata is recorded.
7. Temporarily enable `store_conversations=true`, keep `log_message_content=false`, and confirm message hashes/lengths are stored without raw content.
8. Verify action suggestion links only point to menus visible to the active role.
