# AI Chatbot Core Blueprint

Date: 2026-06-11
Project: IQS-Framework core project
Status: Planning documentation only

## Purpose

This document defines the proposed AI chatbot architecture for IQS-Framework as a reusable core capability.

If implemented successfully, the chatbot should be available to downstream projects through configuration, access control, and project-specific branding without requiring each project to rebuild the same AI integration from scratch.

The initial goal is to validate the feature flow with free or low-cost providers before adopting a production-grade provider for real usage.

## Scope

The chatbot should provide an authenticated in-system assistant that can help users with:

- System navigation and general usage questions.
- FAQ and manual guidance.
- Project-specific help content where configured.
- Controlled read-only assistance for approved system information.
- Future action workflows only after separate security review.

The chatbot must not become an uncontrolled database query interface or an unrestricted automation agent in the first implementation phase.

## Recommended User Experience

The chatbot should appear as a small floating widget at the bottom-right of authenticated pages.

Default behavior:

- A compact circular launcher is shown at the bottom-right corner.
- The launcher displays a small AI character image or avatar.
- Clicking the launcher opens a chat panel.
- The chat panel shows the configured AI character name.
- The avatar and name can be changed by configuration.
- The panel supports loading, error, retry, and empty states.
- The widget should not block normal page workflows.
- On mobile, the chat panel should fit the viewport and avoid covering critical navigation.

Recommended configurable identity fields:

```env
AI_CHATBOT_ENABLED=false
AI_CHATBOT_CHARACTER_NAME="IQS Assistant"
AI_CHATBOT_CHARACTER_AVATAR="assets/images/ai/assistant.png"
AI_CHATBOT_WELCOME_MESSAGE="Hai, saya boleh bantu tentang penggunaan sistem ini."
AI_CHATBOT_POSITION="bottom-right"
```

Downstream projects should be able to replace the avatar image and assistant name without modifying chatbot logic.

## Core Architecture

The chatbot should be provider-agnostic. The UI and framework behavior should not be tied directly to OpenAI, Gemini, Groq, Ollama, or any single provider.

Recommended layers:

- Page/widget shell: renders the floating launcher and chat panel.
- Frontend script: handles panel state, message submission, loading state, and rendering.
- AJAX endpoint: receives user messages and returns assistant responses.
- Chatbot service: validates input, prepares context, calls provider adapter, and normalizes output.
- Provider adapter: implements provider-specific HTTP request and response parsing.
- Configuration resolver: reads active provider, model, API keys, limits, and feature flags.
- Audit/log service: records usage metadata without exposing sensitive message content unnecessarily.

Recommended future file layout:

```text
public/classes/AiChatbotService.php
public/classes/AiChatbotProviderInterface.php
public/classes/AiChatbotProviderRegistry.php
public/classes/AiChatbotProviders/OllamaProvider.php
public/classes/AiChatbotProviders/GeminiProvider.php
public/classes/AiChatbotProviders/GroqProvider.php
public/classes/AiChatbotProviders/OpenRouterProvider.php
public/classes/AiChatbotProviders/OpenAIProvider.php
public/ajax/ai-chatbot-message.php
public/assets/js/ai-chatbot-widget.js
public/assets/css/ai-chatbot-widget.css
```

If a full page is needed later:

```text
public/pages/ai-chatbot.php
public/controllers/AiChatbotController.php
```

## Provider Strategy

The first implementation should support free or low-cost testing providers before production rollout.

Recommended provider order:

1. Ollama local for early development.
2. Gemini API or OpenRouter free models for cloud API testing.
3. Groq for fast OpenAI-compatible cloud testing.
4. OpenAI or another paid provider for production quality and reliability.

### Ollama

Ollama is suitable for local development because it can run models locally without API cost.

Advantages:

- No external API cost.
- Easier to test feature flow.
- Better privacy during early development because prompts can stay local.
- Has OpenAI-compatible API support.

Limitations:

- Requires local/server model setup.
- Quality depends on local model.
- Production scalability depends on available hardware.
- Some OpenAI-compatible behavior may not support all stateful API features.

### Gemini API

Gemini API is suitable for free-tier cloud testing.

Advantages:

- Cloud-hosted.
- Has free usage through Google AI Studio in supported regions.
- Good option for prototype validation.

Limitations:

- Rate limits and free-tier behavior can change.
- Provider-specific API format may require a dedicated adapter.
- Data leaves the organization environment unless separately controlled.

### Groq

Groq is suitable for fast cloud inference testing through OpenAI-compatible endpoints.

Advantages:

- Fast inference.
- OpenAI-compatible base URL.
- Easier adapter implementation if the framework already supports OpenAI-style chat requests.

Limitations:

- Free quota and rate limits depend on account settings.
- Model availability can change.

### OpenRouter

OpenRouter is suitable for experimenting with multiple free models through one API.

Advantages:

- One API can route to many models.
- Useful for comparing models during development.
- Free model options are available.

Limitations:

- Free model behavior may be inconsistent.
- Routing can make output quality less predictable.
- Production governance needs careful provider/model selection.

### OpenAI

OpenAI is recommended as a production candidate after the feature flow is proven.

Advantages:

- Strong model quality.
- Mature API platform.
- Good future fit for tool use and structured workflows.

Limitations:

- Paid usage.
- Requires API key and budget controls.
- Data governance must be reviewed before production use.

## Configuration Requirements

The chatbot should be disabled by default in the core framework.

Recommended environment keys:

```env
AI_CHATBOT_ENABLED=false
AI_CHATBOT_PROVIDER=ollama
AI_CHATBOT_MODEL=llama3.2:3b
AI_CHATBOT_BASE_URL=http://127.0.0.1:11434
AI_CHATBOT_API_KEY=
AI_CHATBOT_TIMEOUT_SECONDS=30
AI_CHATBOT_MAX_INPUT_CHARS=2000
AI_CHATBOT_MAX_OUTPUT_TOKENS=800
AI_CHATBOT_RATE_LIMIT_PER_MINUTE=10
AI_CHATBOT_STORE_CONVERSATIONS=false
AI_CHATBOT_LOG_MESSAGE_CONTENT=false
```

Downstream projects should override these values without modifying core source code.

If System Settings support is added later, sensitive values such as API keys must remain masked and must not be exposed back to the browser.

## Security Requirements

The chatbot endpoint must follow existing IQS-Framework security standards:

- Require authenticated session through `require_login()`.
- Validate CSRF token.
- Enforce access policy or feature permission.
- Apply per-user or per-session rate limiting.
- Enforce maximum input length.
- Reject empty or malformed messages.
- Sanitize rendered output.
- Avoid exposing provider errors directly to users.
- Store API keys only server-side.
- Never call external AI providers directly from browser JavaScript.
- Do not send passwords, cookies, CSRF tokens, session IDs, or authorization headers to AI providers.

For early phases, the chatbot should be read-only.

The chatbot must not:

- Execute arbitrary SQL generated by the model.
- Modify users, groups, menus, permissions, settings, or records.
- Reveal hidden system configuration.
- Bypass existing role and group access.
- Use impersonation context without explicit review.

### Role-Aware Answer Boundary

The chatbot must answer only within the current user's allowed system boundary.

This is a mandatory requirement because the chatbot is visible only after login, and users will naturally expect answers to match what they are allowed to do in the running system.

Role-aware answer rules:

- The assistant must not provide detailed steps for features, routes, settings, or workflows that the current user is not allowed to access.
- If a normal user asks about an administrator-only feature, the assistant should answer generally without exposing hidden menu names, internal routes, permission structure, API keys, provider settings, or setup steps.
- The safe response for restricted topics should be similar to: "Tetapan tersebut hanya tersedia kepada pengguna yang diberi akses. Sila hubungi pentadbir sistem jika perubahan diperlukan."
- The assistant must not reveal the existence of hidden modules unless that module is already available in the allowed context sent by the system.
- The assistant must not explain how to bypass access control, elevate role, change permission, or reach restricted pages.
- Super Admin or explicitly allowed admin users may receive administrator guidance only when the system context confirms the current user is allowed to access that area.
- Any future knowledge-base or retrieval layer must filter content by the current user's allowed groups, role, and menu access before sending context to an AI provider.

## Data Governance

The system must define what data can be sent to external AI providers.

Recommended default:

- User question can be sent.
- Minimal session context can be sent, such as language and generic role label.
- Sensitive identifiers should be excluded unless explicitly required and approved.
- Database records should not be sent unless retrieved through a controlled, access-checked service.
- Message content should not be stored by default.

If conversation storage is enabled, the database should store only what is operationally necessary.

Recommended metadata:

- User ID or staff ID reference.
- Provider.
- Model.
- Request timestamp.
- Latency.
- Token estimate where available.
- Success or failure status.
- Error category.

Message content logging should be optional and disabled by default.

## Access Control

Recommended access model:

- Global feature flag controls whether chatbot is enabled.
- Group/menu access controls who can use chatbot.
- A public authenticated mode may be considered later for basic FAQ-only chatbot.
- Admin-only mode should be available during testing.

Possible modes:

```text
disabled
super_admin_only
selected_groups
all_authenticated
```

Initial rollout should use `super_admin_only` or `selected_groups`.

## Audit Requirements

The chatbot should record audit-safe usage events.

Recommended events:

- Chatbot opened.
- Message submitted.
- Provider request succeeded.
- Provider request failed.
- Rate limit triggered.
- Access denied.
- Provider changed by admin.
- Chatbot enabled or disabled.

Audit events should avoid storing raw sensitive prompt content unless a specific compliance decision allows it.

## Prompt and Context Strategy

The system prompt should be controlled by core configuration and optionally extended by downstream projects.

Recommended core instruction:

- Answer in the user's current language where possible.
- Be concise and operational.
- Focus on help for the current system only.
- Apply the role-aware answer boundary.
- Do not claim to perform actions unless the system has actually performed them.
- Do not expose secrets, tokens, or internal configuration.
- Do not reveal restricted administrator workflows to users whose current context does not show permission for those workflows.
- If unsure, ask the user to contact the system administrator or refer to official manual content.

Project-specific context should be injected from approved sources only, such as FAQ entries, manuals, or curated help text.

Do not let downstream projects edit core provider logic just to change chatbot personality or help content.

### System-Focused Improvement Roadmap

The chatbot should mature from a general assistant inside the system into a controlled system assistant.

Recommended improvement phases after the current provider/settings work:

1. Scope Guard and Role Guard
   - Restrict answers to system usage, navigation, workflow, access help, and troubleshooting.
   - Refuse off-topic questions or redirect them back to system usage.
   - Do not provide restricted steps unless the current user's context permits it.

2. Safe Runtime Context
   - Send only safe context such as language, current role label, current page, app title, and allowed access scope.
   - Do not send role matrices, hidden menus, tokens, secrets, or full configuration.
   - Current implementation sends sanitized page path, page title, app title, chatbot access mode, language, active role label, and active group code/ID only.
   - The browser must not send full URL, query string, hash, cookies, tokens, login ID, staff ID, or raw profile data.
   - This phase does not add menu lookup, knowledge-base retrieval, or free database querying.

3. Read-Only System Context Helper
   - Add controlled helpers that return only allowed menus, visible modules, page labels, and non-sensitive descriptions.
   - The AI must not write SQL or query the database directly.
   - Current implementation uses a server-side helper to read the active group's configured module/menu access and sends a capped visible-menu summary to the prompt.
   - The helper does not accept SQL from the model and does not expose raw group access CSV, user identity, hidden menus, or unrestricted database records.
   - The prompt must answer navigation questions only from the provided visible module/menu context.

4. Knowledge Base With Visibility
   - Store curated help content, FAQ, SOP, and module manuals.
   - Each knowledge item must have visibility metadata such as all authenticated users, selected groups, or super admin only.
   - Current implementation supports an optional `tbl_ai_chat_knowledge` table documented in `docs/ai-chatbot-knowledge-tables-2026-06-12.sql`.
   - Knowledge retrieval is read-only, keyword-based, capped to five items, and filtered by language plus visibility before content is sent to the AI provider.
   - If the table does not exist or no visible item matches, the chatbot continues without knowledge context and must not invent missing knowledge.

5. Retrieval With Permission Filter
   - Search only knowledge and system context the current user is allowed to see.
   - Send filtered context to the AI provider and instruct the model to answer only from that context.
   - Current implementation adds a `permission_filtered` retrieval policy to every provider request.
   - System-specific questions about pages, menus, settings, roles, access, users, providers, models, configuration, or workflows must be grounded in approved runtime, visible system, or curated knowledge context.
   - If approved context is insufficient, the chatbot must say it does not have enough approved system context instead of inventing system behavior.

6. Governance and Review Loop
   - Classify questions as system help, navigation help, access help, troubleshooting, off-topic, sensitive blocked, or unknown.
   - Review failed or unknown questions to improve the knowledge base.
   - Current implementation classifies each request into review-safe metadata such as `system_help`, `navigation_help`, `access_help`, `troubleshooting`, `sensitive_blocked`, or `unknown`.
   - The classification is stored in usage request metadata without storing the raw user question.
   - Sensitive or unknown questions are marked for review so administrators can improve curated knowledge without exposing restricted details.

## UI Customization

The AI character should be customizable through configuration.

Recommended configurable fields:

- Character name.
- Avatar image path.
- Welcome message.
- Launcher tooltip.
- Panel title.
- Primary color or theme token.
- Visibility mode.

The avatar should be stored as a normal project asset path, for example:

```text
public/assets/images/ai/assistant.png
```

Downstream projects may replace the image or point the config to a project-specific asset.

## Database Considerations

For MVP, database tables are optional if conversation history is not stored.

If persistence is required, recommended tables:

```text
tbl_ai_chat_session
tbl_ai_chat_message
tbl_ai_chat_usage
```

Recommended first implementation:

- No raw conversation persistence.
- Store audit-safe usage metadata only.
- Add conversation tables only when there is a clear product need.

## Implementation Phases

### Phase 1: Documentation and Design

- Finalize architecture.
- Confirm provider abstraction.
- Confirm security rules.
- Confirm UI widget behavior.
- Confirm downstream customization model.

Phase 1 is considered complete when this document has enough decisions for implementation to start without guessing the feature boundary.

No application code, database migration, API key, provider credential, or runtime configuration change should be introduced in Phase 1.

#### Phase 1 Design Decisions

The following decisions are accepted as the initial design baseline for Phase 2 implementation:

- The chatbot is a reusable IQS-Framework core capability.
- The chatbot is disabled by default.
- The first UI surface is a bottom-right floating widget on authenticated pages.
- The AI character name and avatar are configurable.
- The first implementation is read-only.
- The chatbot must not write to application data.
- The chatbot must not execute arbitrary SQL.
- The browser must call only IQS backend endpoints, never an external AI provider directly.
- Provider selection must be abstracted behind server-side provider adapters.
- Ollama local is the first provider target for no-cost local prototype testing.
- At least one free cloud provider can be added after the local flow is stable.
- Early access should be restricted to super admin or selected groups.
- Message content storage is disabled by default.
- Audit-safe usage metadata is preferred over raw prompt logging.

#### Phase 1 Architecture Contract

The implementation should follow this contract:

- Frontend widget handles display, input, loading state, and rendering only.
- AJAX endpoint handles authentication, CSRF, request validation, rate limiting, and JSON response shape.
- Chatbot service handles conversation policy, prompt preparation, provider selection, and response normalization.
- Provider adapters handle provider-specific HTTP requests.
- Configuration resolver handles environment and future System Settings values.
- Audit layer records safe metadata for operational visibility.

The frontend must not know which provider is active. It should only send a message to the framework endpoint and render the normalized response.

#### Phase 1 Provider Contract

Each provider adapter should return a normalized response shape:

```text
success
provider
model
message
latency_ms
usage
error_code
error_message
```

Provider-specific raw responses should not be exposed directly to the browser.

#### Phase 1 Request Flow

Recommended request flow for Phase 2:

```text
User clicks chatbot widget
User submits message
Frontend sends POST to public/ajax/ai-chatbot-message.php
Endpoint validates login, CSRF, access, input length, and rate limit
Endpoint passes request to AiChatbotService
Service builds safe context and selects provider adapter
Provider adapter calls configured provider
Service normalizes response
Endpoint returns JSON response
Frontend renders assistant message
Audit records safe usage metadata
```

#### Phase 1 Security Baseline

The minimum security baseline for implementation:

- `require_login()` is mandatory.
- CSRF validation is mandatory.
- Rate limiting is mandatory.
- Feature flag check is mandatory.
- Access mode check is mandatory.
- API key must stay server-side.
- Raw provider exception details must be logged server-side only.
- User-facing errors must be generic and localized later.
- Message content logging must stay off unless explicitly enabled.
- Sensitive values must be redacted from diagnostics.

#### Phase 1 UI Baseline

The initial widget should be simple and framework-friendly:

- One floating launcher button.
- One chat panel.
- Avatar image in launcher and panel header.
- Configurable assistant name.
- Configurable welcome text.
- Text input and send button.
- Loading indicator while waiting for response.
- Clear error state when provider is unavailable.
- Responsive layout for mobile.

Global rendering should be introduced carefully in later implementation because topbar, sidebar, and script includes are protected core areas.

#### Phase 1 Downstream Customization Baseline

Downstream projects should customize the chatbot through configuration and assets:

- Enable or disable feature.
- Select provider.
- Set model.
- Set base URL.
- Set API key where needed.
- Set character name.
- Set avatar image path.
- Set welcome message.
- Select access mode.

Downstream projects should not need to edit provider classes, service logic, or framework includes for normal customization.

#### Phase 1 Acceptance Criteria

Phase 1 can be reviewed as complete if:

- The intended UI behavior is documented.
- The provider-agnostic architecture is documented.
- Free/testing provider strategy is documented.
- Security baseline is documented.
- Access control direction is documented.
- Data governance defaults are documented.
- Downstream project customization model is documented.
- Phase 2 implementation scope is clear.

#### Phase 1 Review Questions

Before moving to Phase 2, confirm:

- Should the first prototype use only Ollama, or Ollama plus one cloud provider?
- Should the widget appear on all authenticated pages during prototype, or only on a dedicated test page?
- Should Phase 2 store no conversation data, as currently recommended?
- Should the first access mode be `super_admin_only`?
- What default AI character name should be used?
- What default avatar path should be used?

### Phase 2: Local Prototype

- Implement disabled-by-default core widget.
- Implement server-side AJAX endpoint.
- Implement Ollama provider adapter.
- Add basic rate limit and CSRF validation.
- Return simple assistant response.
- Test with super admin only.

#### Phase 2 Implementation Notes

Phase 2 implementation is intentionally limited to a dedicated prototype page instead of global injection into every authenticated page.

Implemented prototype surface:

```text
public/pages/ai-chatbot.php
```

Implemented backend endpoint:

```text
public/ajax/ai-chatbot-message.php
```

Implemented service/provider classes:

```text
public/classes/AiChatbotService.php
public/classes/AiChatbotProviderInterface.php
public/classes/AiChatbotProviderRegistry.php
public/classes/AiChatbotProviders/OllamaProvider.php
```

Implemented assets:

```text
public/assets/js/ai-chatbot-widget.js
public/assets/css/ai-chatbot-widget.css
```

Phase 2 keeps the chatbot disabled by default. To test locally, set:

```env
AI_CHATBOT_ENABLED=true
AI_CHATBOT_PROVIDER=ollama
AI_CHATBOT_MODEL=llama3.2:3b
AI_CHATBOT_BASE_URL=http://127.0.0.1:11434
```

Phase 2 behavior:

- Prototype page is restricted to Super Admin.
- AJAX endpoint requires login.
- AJAX endpoint validates CSRF token.
- AJAX endpoint applies per-session rate limiting.
- Browser calls only IQS backend endpoint.
- Ollama is called server-side.
- Raw conversation storage is not implemented.
- Message content is not stored by default.
- Audit-safe metadata is recorded for message success, failure, and rate limit events.

Phase 2 exclusions:

- No global widget injection into all pages.
- No database migration.
- No System Settings UI.
- No cloud provider adapter.
- No autonomous action or database write capability.

### Phase 3: Free Cloud Provider Test

- Add Gemini, Groq, or OpenRouter adapter.
- Add provider switch through configuration.
- Normalize provider errors.
- Add timeout handling.
- Add usage metadata.

#### Phase 3 Implementation Notes

Phase 3 adds cloud provider testing while keeping the same UI and AJAX endpoint from Phase 2.

Implemented provider adapters:

```text
public/classes/AiChatbotProviders/OpenAICompatibleProvider.php
public/classes/AiChatbotProviders/GroqProvider.php
public/classes/AiChatbotProviders/OpenRouterProvider.php
```

Supported provider values:

```env
AI_CHATBOT_PROVIDER=ollama
AI_CHATBOT_PROVIDER=groq
AI_CHATBOT_PROVIDER=openrouter
```

Groq example:

```env
AI_CHATBOT_ENABLED=true
AI_CHATBOT_PROVIDER=groq
AI_CHATBOT_MODEL=llama-3.1-8b-instant
AI_CHATBOT_BASE_URL=https://api.groq.com/openai/v1
AI_CHATBOT_API_KEY=your_groq_api_key
```

OpenRouter example:

```env
AI_CHATBOT_ENABLED=true
AI_CHATBOT_PROVIDER=openrouter
AI_CHATBOT_MODEL=openrouter/free
AI_CHATBOT_BASE_URL=https://openrouter.ai/api/v1
AI_CHATBOT_API_KEY=your_openrouter_api_key
AI_CHATBOT_APP_URL=https://iqs-framework.dev
AI_CHATBOT_APP_TITLE="IQS-Framework AI Chatbot"
```

Phase 3 behavior:

- Provider can be switched through `.env`.
- Groq uses OpenAI-compatible chat completions through `https://api.groq.com/openai/v1`.
- OpenRouter uses OpenAI-compatible chat completions through `https://openrouter.ai/api/v1`.
- API key remains server-side.
- Response is normalized to the same endpoint response shape used by the widget.
- Provider-specific errors are logged server-side and converted to a generic user-facing error.
- Usage metadata from provider response is passed through when available.

Phase 3 exclusions:

- No Gemini adapter yet.
- No System Settings UI.
- No global widget injection.
- No conversation database persistence.
- No production budget/quota dashboard.
- No tool/action execution.

### Phase 4: Framework Integration

- Add group-based access mode.
- Add language keys under custom/core language architecture.
- Add audit events.
- Add optional System Settings UI for non-secret controls.
- Add deployment notes for downstream projects.

#### Phase 4 Implementation Notes

Phase 4 promotes the chatbot from a dedicated prototype page into a framework-rendered widget.

Implemented framework integration:

```text
public/includes/ai-chatbot-widget.php
public/includes/script.php
public/ajax/ai-chatbot-event.php
```

Widget rendering behavior:

- The widget is included globally through `public/includes/script.php`.
- The include renders nothing when `AI_CHATBOT_ENABLED=false`.
- The include renders nothing when the current user does not pass chatbot access control.
- The dedicated page `public/pages/ai-chatbot.php` remains available as a diagnostic/config review page.

Implemented access modes:

```env
AI_CHATBOT_ACCESS_MODE=super_admin_only
AI_CHATBOT_ACCESS_MODE=selected_groups
AI_CHATBOT_ACCESS_MODE=all_authenticated
```

For selected groups:

```env
AI_CHATBOT_ACCESS_MODE=selected_groups
AI_CHATBOT_ALLOWED_GROUPS=ADM-SA,ADM-PE,12
```

`AI_CHATBOT_ALLOWED_GROUPS` accepts comma-separated active group codes or group IDs. Super Admin remains allowed in `selected_groups` mode.

Implemented language keys:

```text
public/lang/core/ms.php
public/lang/core/en.php
```

The initial key namespace is:

```text
aiChatbot_*
```

Implemented audit event:

```text
AI_CHATBOT_OPENED
```

The frontend records this event once per page load when the widget is opened.

Downstream deployment notes:

- Enable the feature through `.env`.
- Choose `super_admin_only` during first downstream testing.
- Switch to `selected_groups` only after confirming the target group code or group ID.
- Keep `AI_CHATBOT_LOG_MESSAGE_CONTENT=false` unless a separate governance decision approves message storage.
- Keep provider API keys in server-side environment only.
- Override assistant name/avatar/welcome text through `.env` or future settings UI.
- Disable immediately with `AI_CHATBOT_ENABLED=false` if provider errors, quota issues, or policy concerns occur.

Phase 4 exclusions:

- System Settings UI is documented but not implemented yet.
- API key masking UI is not implemented yet.
- Conversation persistence is not implemented.
- Budget/quota dashboard is not implemented.
- Tool/action execution is not implemented.

### Phase 5: Production Readiness

- Review data governance.
- Add budget and quota controls.
- Add production provider.
- Add monitoring and operational runbook.
- Add security review for any tool/action capability.

#### Phase 5 Implementation Notes

Phase 5 adds production-readiness controls without enabling autonomous actions.

Implemented usage persistence:

```text
public/classes/AiChatbotUsageRepository.php
tbl_ai_chat_usage
```

Implemented quota configuration:

```env
AI_CHATBOT_USER_DAILY_REQUEST_LIMIT=100
AI_CHATBOT_GLOBAL_DAILY_REQUEST_LIMIT=1000
AI_CHATBOT_PERSIST_USAGE=true
```

Runtime behavior:

- Per-minute session rate limit remains active.
- Per-user daily request limit is checked before provider calls.
- Global daily request limit is checked before provider calls.
- Success, failure, timeout, and rate-limit outcomes can be recorded in `tbl_ai_chat_usage`.
- Provider token usage is stored when the provider returns usage metadata.
- Raw conversation persistence remains disabled by default.

Production runbook:

```text
docs/ai-chatbot-production-runbook-2026-06-11.md
```

Phase 5 exclusions:

- No model tool/action execution.
- No arbitrary SQL or database search.
- No user/group/setting mutation.
- No raw conversation storage by default.
- No provider billing integration beyond usage metadata.

## Recommended MVP

The first working version should include:

- Bottom-right floating AI character launcher.
- Configurable name and avatar.
- Authenticated chat panel.
- Server-side provider call.
- Ollama provider support.
- One cloud free provider support.
- CSRF validation.
- Rate limiting.
- Basic audit event.
- Disabled-by-default feature flag.

The first version should not include:

- Autonomous actions.
- Database writes.
- Arbitrary database search.
- User management operations.
- Permission changes.
- Sensitive data summarization.

## Downstream Project Usage

Downstream projects should consume the chatbot as a configurable core feature.

Expected downstream customization:

- Enable or disable chatbot.
- Select provider.
- Set provider model.
- Set API key or local base URL.
- Change assistant name.
- Change assistant avatar.
- Add project-specific help content.
- Control which groups can access it.

Downstream projects should not need to modify core chatbot service classes for normal customization.

## Open Questions

- Should the chatbot appear globally on every authenticated page or only on selected pages?
- Should conversation history be stored at all during MVP?
- Which free cloud provider should be the first adapter after Ollama?
- Should System Settings expose provider selection in Phase 2 or only after the adapter design is stable?
- Should the assistant be allowed to search manuals in Phase 1, or should it answer only from static prompt context first?

## Current Recommendation

Proceed with a provider-agnostic core chatbot design.

Use Ollama local as the first provider for no-cost functional testing.

Add one cloud free provider after the UI and endpoint flow are stable.

Keep the feature disabled by default and restrict early testing to super admin or selected groups.

Treat production provider selection, budget controls, and data governance as a separate readiness decision after the feature works end to end.
