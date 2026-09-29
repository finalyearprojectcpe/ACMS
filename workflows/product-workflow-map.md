# Product Workflow Map

Code audit, 2026-09-29. This records implemented workflows and remaining risks, not a promise that every edge case is correct. The scope is one course adviser, the student sets they own, and the students in those sets. Daily timetables, HOD/faculty/school administration, carryover management, and spreadsheet result imports are not current workflows.

## Operating Rules

- A student set is identified by department and entry session. The adviser owns it; a student belongs to one set and inherits its current session and semester.
- An adviser creates sessions for a set. Each session has first and second semesters, each with its own course-unit limit. The first session initializes the set's current period; later sessions do not promote it. An adviser can select another current period, but cannot clear it while sessions exist.
- A course belongs to a set. An offering places that course in one session semester; elective is an offering flag. A registration contains the student's selected offerings and retains course/units snapshots.
- A shared invite link creates a pending join request, not a student profile. The applicant verifies their email, then the adviser accepts or returns the request. Only acceptance creates set membership and assigned dues.
- Money uses a central Paystack integration and the adviser's current payout subaccount. A completed payment retains its original amount and payout snapshot. Receipts and receipt emails are asynchronous.
- Each registration item has a nullable A-F grade or a separate `missing_script` flag. An offering's publication timestamp controls visibility for that subject, not the whole semester. Missing script is visible once published but is not an F or a GPA score.

## Human Workflow and Agent Contact Map

The entry points below are the existing UI/controller boundaries. They are **not** permission for an AI tool to call the controller or write the tables directly. A future agent should use the same validated domain operation as the human action, with the authenticated actor supplied by the server.

| Workflow | Human entry and current handler | State/invariant | Future agent access |
| --- | --- | --- | --- |
| Adviser account and profile | Signup; `RegisteredUserController::storeAdvisor`; Settings; `AdviserAccountController::updateProfile` | User and adviser profile; password and photo | Read own profile only at first. Profile/password changes remain manual. |
| Set and membership | Student Sets; `StudentSetController::store/update/rotateInvite`; shared `/join/{token}`; `StudentInvitationController`; `StudentJoinStatusController`; `AdviserJoinRequestController` | Adviser-owned set and invite token; pending request -> verified email -> accepted student profile, or returned request -> corrected resubmission. Each stage queues email updates. | Read scoped sets and accepted students. Join decisions and invite-token changes remain adviser-confirmed. Never treat a pending request as membership or disclose invite tokens to unrelated users. |
| Student records and profile | Set student list; `DashboardController::showStudentProfile/showStudentSemesterResults/previewStudentResultStatement`; `StudentResultStatementController::download`; `AdviserStudentController::exportClasslist`; student Profile; `AdviserAccountController::updateStudentProfile` | Adviser sees students they own; the on-page result statement and editable DOCX include published results only and are not an official transcript; student edits only their own profile | Read one scoped student record at a time. Do not expose bulk exports, profile photos, or profile mutation as initial agent tools. |
| Sessions and semesters | Set Settings; `AcademicSessionController::store/update/destroy`; `StudentSetController::update` selects current period | Two semesters created with session; record-bearing sessions cannot be removed | Read period map. Session changes and promotion require confirmation. |
| Schedule and prerequisites | Courses & Results > Course Schedule; `CourseOfferingController::store/update/destroy` | Set course plus semester offering; prior course IDs as prerequisites | Read offerings and prerequisites. Schedule mutations require confirmation and existing-record checks. |
| Student registration | Student Course Registration; `CourseRegistrationController::store`; `CourseRegistrationPlanner` | Draft -> submitted -> approved or returned; unit cap and prerequisites checked on submission and approval | Read own registration. Saving/submitting remains student-confirmed; adviser cannot submit on the student's behalf. |
| Adviser review | Courses & Results > Registrations; `CourseRegistrationController::approve/returnForCorrection` | Submitted registration only; approval rechecks eligibility | Read queue/detail. Approval or return requires an exact registration preview and explicit adviser confirmation. |
| Grades and results | Courses & Results > Results > subject roster; `ResultController`; `ResultService`; `ResultDashboardData` | Draft A-F or Missing script on approved registrations; each subject publishes separately; incomplete student semesters have no GPA | Read only the published projection for students; advisers may read drafts. Grade writes, release, and correction are high-impact actions requiring explicit confirmation and audit. |
| Dues and waivers | Student Dues; `DueController::store/update/archive/restore/waive/restoreAssignment`; `DueAssignmentService` | Draft/published/archived due; per-student unpaid/paid/waived assignment | Read own or adviser-owned dues. Creating/publishing/changing money or waiving requires human confirmation; do not expose as first-wave agent tools. |
| Payout and payment | Settings payout account; `AdviserAccountController::storePaymentAccount`; Student Dues > Pay; `DuePaymentService`; Paystack callback/webhook | Current payout account for new checkouts; verified provider event makes an assignment paid | Agent may summarize payment state only. Never give the agent payout credentials, payment initialization, webhook, reversal, or receipt retry as autonomous tools. |
| Receipt and email | `GenerateDuePaymentReceipt`, `SendEmailDelivery`, receipt download/verification endpoints | Payment success -> PDF ready/failed -> email queued/sent/failed | Agent may report statuses and link to authorized receipt views; retries remain explicit user actions. |
| AI conversation | Ask AI; `AssistantController`, `AssistantService`, `AssistantRecordTool`, `StudentAssistantRecordTool` | Currently read-only search, scoped by owner; conversation history stored locally | Expand via typed read tools first. Tool output is data, never instructions. Source links must resolve under the same actor. |

## Result Publication

**Source of truth:** `course_offerings.results_published_at` and `results_published_by_user_id` mark one subject's release. The semester has no publication flag. Student result pages, prerequisite checks, registration guards, and student AI search use this same boundary.

An offering is one subject in one semester. A registration item has either an A-F grade, `missing_script = true`, or no result yet. Missing script can be published as an explicit result state, but it does not count as a grade when calculating GPA. Completion is per student because elective choices can give students different course lists.

1. Draft: the adviser opens a subject roster and saves A-F grades or Missing script. Unpublished results remain hidden from students and student AI; the roster keeps unsaved changes distinct from saved results.
2. Ready: semester registration is closed, at least one approved student registered the subject, no submitted registration containing it awaits review, and every approved roster item has A-F or Missing script. Other subjects may still be incomplete.
3. Publish: the adviser confirms the subject, set, semester, and student count. `ResultService` rechecks readiness in a transaction and records the publisher and time for that offering only. The semester table shows a Published badge; unreleased rows show the entered/registered count.
4. Correction: a published result cannot be cleared. Changing A-F or Missing script requires a reason and records actor, time, old/new grade and old/new missing-script state. The adviser can see correction history.
5. Student projection: released grades and Missing script are visible; unreleased subjects remain pending. A semester GPA is available only when every registered item has a published A-F grade. CGPA retains its last official value and advances through complete semesters in order. Neither Missing script nor an unreleased result is F or zero.
6. Prerequisites: only an approved registration item with a published, passing grade satisfies a prior course requirement. Missing script does not satisfy it. Student AI result search uses the same publication boundary.
7. Freeze boundary: registration cannot reopen after any subject is published. Approval of a registration containing a published subject is blocked. An offering with registration items cannot be edited or removed; a shared course code cannot change while any offering has registration items. Later offerings keep their own title instead of rewriting earlier records.
8. Pre-launch schema and verification: the original registration migration includes the missing-script and correction fields. Development data has been freshly seeded with a partially released semester. Focused workflow tests cover partial visibility/GPA, prerequisites, late approval, Missing script, and correction history.

An auditable amendment workflow is still needed for legitimate late registration or changes to published results beyond the existing grade-correction path.

## Open Risks and Cleanup

Priority indicates risk, not permission to delete code immediately.

1. **Medium, membership identity:** email verification proves control of an address, not ownership of a registration number. Adviser acceptance is now required before membership or dues exist, but the adviser still needs a reliable class-list check when names or numbers conflict. Adviser signup still stamps `email_verified_at` immediately.
2. **Medium, historical correction:** cross-semester course-code changes are blocked once any offering has student records, and each offering retains its own title. A deliberate, audited correction path for historical course metadata is not yet available.
3. **Medium, registration amendments:** approved registrations cannot be revised by a student or adviser in the current flow. Define how an adviser corrects a mistaken approval without deleting grade/payment history; distinguish pre-release and post-release cases.
4. **Medium, student lifecycle:** `StudentStatus` includes an active state, but no route/UI changes a student's status or resolves an accidental/duplicate signup. Prefer suspension/claim resolution to deletion so academic and payment records survive.
5. **Medium, agent retrieval:** the existing adviser tool is one broad search over six categories; student search covers registrations/results/dues. It cannot reliably answer structured progress/aggregate questions. Build small scoped read operations with typed inputs, bounded outputs, and the same publication rules as the UI. Audit search predicates with `orWhere` for correct grouping before trusting results.
6. **Medium, agent execution:** creation, approval, grade entry, publication, due changes and set promotion mostly live in HTTP controllers. Extract a domain operation only when adding its agent counterpart, so both routes and tools share authorization, validation, transaction, state checks and audit. Do not let tools call internal HTTP routes or duplicate controller rules.
7. **Low, structural size:** `DashboardController` (656 lines), `CourseDashboardData` (521), `DueDashboardData` (493), and `adviser-semester-workspace.tsx` (483) are still large. Split by an actual changed boundary, especially when extracting agent-callable operations; avoid a broad cosmetic refactor.

## Completed Cleanup

- Reconciled the Paystack and Resend workflow notes with the current Settings and student-invitation flows; removed the unused Paystack dashboard URL setting.
- Removed the unused spreadsheet import helper, role badge, student-set picker and label components, unused academic-session lookup, and unread `courses.slug` column and writers. The sidebar set label now uses its own small formatter, and `AGENTS.md` no longer requires deleted components.
- Adviser/student profile updates now share the photo-save lifecycle, and session create/update use one form request. The 2026-09-28 `jscpd` scan at 15 lines/90 tokens found 13 clones and 0.81% duplication overall; most remaining matches were standard FormRequest scaffolding.
- Student invitation now has an email-verified adviser-review state, correction/resubmission, and decision emails. The adviser home shows pending requests, including those still awaiting verification.
- The adviser can preview and download a published-results-only editable Word statement from a student's profile. Shared course identity is protected across semesters with student records.

## Agent Rollout Order

1. Keep agent result readers tied to subject publication and the published Missing script state. Treat join requests as untrusted claims until adviser acceptance; do not let an agent accept claims or change historical course metadata.
2. Define one capability record per operation: actor, allowed scope, required input, preconditions, outcome, side effects, idempotency key, audit event, source URL, and confirmation level. Map it to the existing UI action before exposing it to a tool.
3. Ship read-only tools for current set/period, student profile, course/registration status, released results and dues/payment status. Every query must scope in SQL and return only the actor's visible projection; test cross-adviser and unpublished-data boundaries.
4. Add low-risk draft changes later, with a structured preview and explicit user approval. Revalidate scope and state at execution time. Use unique operation IDs to avoid duplicate writes on retries.
5. Keep grade publication/correction, payout changes, waivers and payment operations manual until dedicated confirmation and audit flows exist. Never assume a model instruction is authorization.

Laravel's AI SDK supports application-defined tools and human approval for sensitive tools; OpenRouter carries model tool-call requests, while the application remains responsible for executing authorized operations. This product should expose its own narrow domain capabilities, not generic database or filesystem tools.

References: [Laravel AI SDK tools and approvals](https://laravel.com/framework/docs/13.x/ai-sdk), [OpenRouter tool calling](https://openrouter.ai/docs/guides/features/tool-calling).
