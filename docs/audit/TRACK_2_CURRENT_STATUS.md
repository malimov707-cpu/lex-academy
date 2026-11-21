# Legal EDU Track 2 — Current Audit and Delivery Status

**Verified:** 2026-11-21  
**Scope:** Existing Laravel application and the attached Track 2 implementation brief  
**Status:** Work in progress. This report records verified implementation and open work; it is not a claim that all 20 modules are complete.

## Current technical baseline

| Area | Observed state |
| --- | --- |
| Runtime | PHP 8.4, Laravel 13.42, Pest 5.3, Vite 8.3, Tailwind 4, Babylon.js 9 |
| Routes | 244 |
| Migrations | 39 after adding source monitoring, subscriptions, and source topics |
| Blade views | 124 |
| HTTP controllers | 67 |
| Local SQLite content | 4 courses, 11 lessons, 51 library resources, 3 legal sources, 4 courtroom scenarios, 2 legal cases, 1 document draft |
| Local teacher AI drafts | 0 |
| Tests | 389 total; 388 passed; 1 skipped; 2,167 assertions |
| Production build | Passed. Existing warnings remain for unresolved `/images/...` references, optional `fontaine`, and a JavaScript chunk above 500 KB. |
| Browser QA | The optional real-browser test is skipped because `COURTROOM_PLAYWRIGHT_MODULE` is not configured. |
| Repository metadata | This workspace has no `.git` directory, so Git-based change review and Pint's `--dirty` mode are unavailable. |

Local database counts describe the connected development SQLite database only. They do not establish deployment or user impact metrics.

## Existing implementation inventory

| Track 2 capability | Current status | Evidence and remaining gap |
| --- | --- | --- |
| AI legal explanation | Partial | AI Ustoz, legal library, and verified-source context exist. Some task modes still return prewritten responses; source citations and legal validity are not consistently established. |
| Verified legal knowledge | Partial | `legal_sources`, library resources, manual verification, and AI context are present. No complete source-grounded retrieval and citation audit exists. |
| Legal document drafting trainer | Partial | Students can save and revise five draft types. The current analyzer is deterministic text-pattern checking, not an AI or legal compliance review. Templates and scores now say so explicitly. |
| Legal scenario learning | Partial | Existing legal cases, course lessons, and solo/group Virtual Courtroom provide practical learning. A general branch-and-consequence scenario authoring/player is not present. |
| Legal microlearning | Partial | Existing lessons, interactive lesson blocks, quizzes, and progress tracking cover parts of this need. No separate daily microlearning pathway is verified. |
| Misinformation verification | Partial; first working slice implemented | Authenticated claim checker extracts claims through the configured AI provider and validates citations against manually verified global source excerpts. It does not store submitted text, avoids an AI verdict when no eligible excerpt matches, and downgrades unsupported citations. It can use published law/code/constitution text only when its URL exactly matches an eligible verified global legal source. It does not search unrelated library materials or provide a teacher review queue. |
| Legal update monitoring | Partial; first working slice implemented | A scheduled command checks manually verified LexUZ URLs, stores a text fingerprint and response metadata, flags page changes for human review, and never labels a page change as a legal amendment. |
| Educational content impact | Partial | Course-linked source changes list course lessons as possible review targets and allow course teachers/admins to record impact. Individual provision-to-content mapping remains open. |
| Accessibility and remote use | Partial | Responsive views and text alternatives exist. A full accessibility and low-bandwidth audit remains open. |
| Personalized legal learning | Partial | Course progress and learner analytics exist; there is no verified legal competency recommendation engine. |
| Client interview simulator | Missing as a dedicated workflow | Existing courtroom roles do not provide a structured client interview exercise. |
| Negotiation and mediation simulators | Missing as dedicated workflows | No independent scenario, outcome, or assessment flow for these skills was found. |
| Legal knowledge navigator | Partial | Library/course/source pages exist, but no connected concept and provision navigator was found. |
| AI Ustoz improvement | Partial | Ordinary chat and main legal task modes use the configured AI driver and lesson/source context; source IDs and links are allow-listed. Semantic proof that each legal claim follows from a cited excerpt and retrieval from the full Legal Library remain open. |
| Competency assessment | Partial | Courtroom rubrics, teacher review, and assessments exist. Cross-module competency history and evidence aggregation remain open. |
| Teacher content intelligence | Partial | Teacher AI drafts and course management exist. Legal freshness alerts and source-backed approval workflows remain open. |
| Public legal education | Partial | Public pages and legal library capabilities exist. A dedicated public, source-verified legal literacy center was not verified. |
| Legal update notifications | Partial; first topic subscription slice implemented | Authenticated users can subscribe to published library topics. A changed page hash notifies subscribers of its administrator-assigned topic, or the topic of a published resource linked to that exact source URL. Human review is still required. |
| Legal education analytics | Partial | Course, assessment, and courtroom reports exist. Track 2 impact measures must be based on real data and are not yet complete. |

## Implemented in this pass

### Source-change monitoring

- Added `legal_source_checks` history. It stores timestamps, HTTP status, ETag/Last-Modified headers, a SHA-256 text fingerprint, and an optional human reviewer. It does not archive fetched legal text.
- Added `legal:sources:check` and scheduled it daily at 03:15. A deployment must run Laravel's scheduler for this to execute automatically.
- Checks require a source marked verified by a user, HTTPS, an exact allow-listed hostname, no URL credentials, and standard HTTPS port. Redirects are not followed.
- The default allow-list is `lex.uz,www.lex.uz`, configurable with `LEGAL_SOURCE_MONITOR_HOSTS`.
- A changed page is shown as **human review required**, not as a confirmed legal update. An administrator or the linked course's teacher records the review; this does not change legal-source verification status.
- The connected development database was checked against the three verified LexUZ sources. All three returned HTTP success: one matched its existing baseline and two established their initial baselines.
- The page now reports actual check history and source/course links. It no longer claims continuous LexUZ synchronization or treats a source's verification status as proof that legislation is currently in force.

LexUZ is the official national legislation database; see the [official legal information distribution law](https://www.lex.uz/uz/acts/-3329163) and [LexUZ](https://lex.uz/). The monitor only detects page-text changes; it does not interpret legal effect, commencement dates, repeal, or applicability.

### Document trainer safety and accuracy

- Removed the hard-coded universal 10-day demand deadline, fixed 2026 dates, and unsupported court outcomes from the sample templates.
- Removed unverified article-number examples and claims that every document must contain the same details.
- The analyzer and interface now identify their output as a text-pattern checklist. It does not verify legal citations, current law, procedural requirements, or filing readiness.
- The implementation remains a deterministic heuristic, not an AI legal reviewer. A source-grounded AI review and teacher rubric remain future work.

### AI Tutor legal-source safeguards

- Lesson and standalone tutor contexts now include the source's human-review timestamp and latest monitor status.
- The tutor is instructed that a human review or unchanged page fingerprint does not establish current legal force; changed or unavailable sources require caution.
- The standalone prompt no longer claims that statutory codes are supplied when only titles or no source text are available. It directs the model to cite only claims supported by supplied excerpts and never invent article numbers or citations.
- Article, topic, case, document, and lesson-summary actions now use the configured AI driver. Every returned `SOURCE-ID` and URL is checked against eligible context sources; an unknown reference rejects the response instead of saving it.
- If a legal AI provider is unavailable, the action returns an error rather than the former fabricated statute meanings, case outcomes, source links, or compliance scores.
- A changed-page record made after a source's human verification excludes it from AI legal evidence even if a later scheduled check says `unchanged`; an administrator must re-verify the source first. A latest `unavailable` check also excludes it until a clean check. Provider-unavailable and invalid-reference responses return an error rather than a fabricated answer.
- Legal quizzes require eligible source context, validate provider-generated structured questions and source IDs, and keep answer keys server-side. The initial quiz response and session history do not expose correct answers; answers are released after submission.
- A quiz stores a fingerprint of the cited source excerpt. Grading is rejected if a cited source changes or becomes ineligible after the quiz was created.
- Flashcard and practice-case actions now use the configured AI driver and eligible legal source context; the former fixed article claims and fictional case with asserted legal outcomes were removed. Study plans use course objectives and general learning activities without hard-coded article ranges.

### Source-grounded claim checker

- Added an authenticated checker at `/legal-check` with a five-request-per-minute limit. Submitted text is not persisted; it is sent to the configured AI provider only when a matching, eligible verified excerpt exists. The interface discloses provider processing.
- Retrieval is anchored to manually verified global `legal_sources`. Draft, course-only, changed-after-review, and latest-unavailable sources are excluded. A published `library_resources` law/code/constitution body can expand the excerpt only when its URL exactly matches that eligible global source; an unrelated or unreviewed library resource cannot establish evidence by itself.
- The provider must return claims and source references in a constrained JSON shape. Each claim must occur in the submitted text, each reference must be among supplied sources, and each quote must occur verbatim in that source excerpt. Unsupported claims are downgraded to “cannot be verified”; absence of evidence is never described as proof that a claim is false.
- Results show server-resolved source titles, quotes, and HTTPS URLs. Browser rendering uses text nodes rather than injecting provider HTML. Responses are marked private and no-store.
- Automated coverage includes authentication, verified-source citations, no-source/no-provider behavior, invalid citations, unsupported quotes, input validation, and throttling. This is an educational triage tool, not legal advice or a semantic guarantee that model conclusions are correct. A teacher review workflow and retrieval for other published library resource types remain open.

### Legal update topic subscriptions and notifications

- Added per-user subscriptions to currently published Legal Library topics. Users can add or remove subscriptions from the legal monitoring page; submitted topics are validated against the published catalogue and unique constraints prevent duplicates.
- Administrators can assign a published Library topic to a legal source when creating or verifying it. This gives sources an explicit category instead of relying only on URL matching.
- When a verified source page fingerprint changes, the monitor writes the check and matching topic notifications in one database transaction. It uses the source topic and any published Library topic attached to the exact source URL. Baseline and unchanged checks do not notify.
- The notification explicitly says the source page changed and links to the monitoring center; it does not claim that legislation changed. Human review remains required.
- Coverage includes subscription validation, clearing, authentication, matching versus unrelated subscribers, and the full monitoring flow. Sources without a matching published library resource still need an explicit topic mapping before topic alerts can reach subscribers.

### Educational material impact review

- A detected LexUZ page change for a course-linked source now lists the course's lessons as potentially requiring review; it does not automatically label them outdated.
- An administrator or teacher assigned to that course must select an impact state and may add a note before acknowledging the change. Teacher ownership is checked against the source's linked course.
- The check history stores the reviewer, review time, impact status, and note. This remains a manual workflow: the source row currently links to one course and does not yet map individual legal provisions to specific assignments, lessons, or scenario text.

## Security, operational, and content limits

- Source monitoring is read-only against the external source and writes only local check records.
- The checker rejects unverified URLs and off-allow-list domains, uses bounded connection/response timeouts, and does not follow redirects.
- A page hash difference may be a website layout/content change; a human must inspect the official page before changing educational material.
- Scheduler registration is verified with `php artisan schedule:list`; production cron/scheduler execution has not been verified.
- The one skipped test is the browser integration test; an interactive browser run is still required before claiming end-to-end browser QA.
- Production build passes. Existing unresolved `/images/...` references, optional `fontaine` optimization dependency, and the 604 KB courtroom scene chunk still produce warnings.
- Some older project reports contain unverified or stale assertions such as “100%” completion, live legal synchronization, or AI legal accuracy. Use this current status file until those reports are reconciled.

## Next work, ordered by risk and user value

- [ ] Replace or clearly label remaining deterministic AI learning handlers such as study plans, quizzes, and flashcards; verify that their legal content comes from reviewed learning material.
- [ ] Extend source-to-course impact review to specific provisions and individual lessons, assignments, scenarios, and drafting exercises.
- [ ] Send a separate notification when a human reviewer records the educational impact decision, and extend delivery to any user-level update subscriptions required by the final product.
- [ ] Extend the first source-grounded misinformation-check slice to reviewed library resources and add a teacher review/escalation workflow.
- [ ] Extend document practice with a reviewed rubric, version comparison, and optional AI feedback grounded in verified sources.
- [ ] Integrate short scenario/microlearning flows into existing lessons rather than creating a parallel LMS.
- [ ] Assess whether client interview, negotiation, and mediation can reuse the existing scenario and courtroom role architecture.
- [ ] Reconcile old readiness, pitch, and audit reports against runtime evidence.
- [ ] Configure and run the real-browser QA suite in an environment with Playwright and a browser installed.
- [ ] Confirm `schedule:run` is installed in the target deployment before describing legal monitoring as automatic there.
