<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.
- For selection inputs in React UI, use the shadcn-style primitives from `@/components/ui/select`: `Select`, `SelectTrigger`, `SelectValue`, `SelectContent`, and `SelectItem`. Do not use raw `<select>` elements or `native-select` in product screens unless there is a specific browser-native behavior requirement and the exception is called out.
- Disabled or muted dropdown options must show why they are unavailable when the reason is not obvious.
- Scrollable product views must use the shadcn `ScrollArea` component from `@/components/ui/scroll-area`. Avoid raw `overflow-auto` / `overflow-y-auto` for platform UI scroll containers unless it is inside a shadcn primitive implementation.
- Display user/account roles with `RoleBadge` from `@/components/role-badge` in tables, cards, and summaries. Do not render role names as bare table text unless the surrounding component already has stronger visual grouping.
- Role badge colors should reflect institution hierarchy, not decoration. Keep highest-scope roles visually strongest, then step down through Faculty Head, HOD, Course Adviser, Staff, and Student.
- Role badges should include useful scope when available: Faculty Head badges include the faculty abbreviation, HOD and Staff badges include the department abbreviation, and Course Adviser badges include the relevant level or set context.
- Use the dedicated `Course Adviser` role label and `RoleBadge` treatment when a staff member is being shown in their advising responsibility. Do not reduce course advisers to a generic `Staff` badge in adviser coverage, staff tables, or advising summaries.
- HOD course offering screens are read-only. HODs can inspect department course offerings by session, level, and semester, but course setup/edit/delete controls belong to the appropriate staff/faculty workflows.
- Do not track daily timetables, venues, or which staff member teaches each course offering. The product tracks course advisers by student set and course offerings by faculty/department/session/level/semester only.
- Staff advisers collect dues through Paystack subaccounts. Store the platform Paystack secret keys centrally, let advisers connect payout bank accounts through Paystack Settings, and attach published dues/payments to the active payout account for the selected student set. Do not ask staff for Paystack API keys or route all adviser dues into one global settlement account. Do not hardcode provider-owned bank lists or payment metadata; fetch supported banks from Paystack, cache them, and show a clear unavailable state if Paystack cannot provide them.
- Visible course category wording must be `Faculty Course`, `Departmental Course`, or `Elective Course`. Do not show `Mandatory` / `Optional`, and do not add a separate owner column when the category already communicates ownership scope.
- Course offering screens should use `CourseOfferingBrowser` or match its pattern: a left rail grouped by level with semester options under each level, mobile `Select` controls for level and semester, and one course table for the selected level and semester. Do not show multiple semester tables side by side.
- Academic session labels in product UI must include the word `Session`, for example `2024/2025 Session`, especially inside selects and page headers.
- Long filter lists such as sessions, student sets, staff, departments, or courses should use a simple searchable popover/command picker. The closed trigger should read like a normal select, with the search field inside the opened picker instead of a noisy always-visible typing input.
- Dashboard charts must keep their filters local to the chart card. Do not use page-level filters to silently change multiple unrelated charts. Population GPA-by-level comparisons should be bar charts, not line charts, because academic levels are categorical.
- Individual student GPA progress should be shown as a line chart across the levels they have taken, because the user is reading one student's academic movement over time.
- When the same chart can show a general population or one student set, use one chart with a local scope selector instead of two separate charts. The general population should be the default scope.
- GPA-by-level bar charts must render against the full relevant academic-year list. Missing years should stay as empty/null slots so one recorded year does not become one oversized bar.
- Visible student set labels in selects, selected triggers, chart controls, filters, tables, cards, and exports must use `StudentSetLabel`, `StudentSetChoiceLabel`, `StudentSetPicker`, or `studentSetText`. Do not manually compose labels like `${session} Set`, because the current level or `Graduated` state must travel with the set name.
- Student set selection must use `StudentSetPicker` with the shared horizontal set choice layout. The trigger and option rows must both keep the set name and current level or `Graduated` state visible; do not use badge boxes or stacked card-like rows inside set dropdowns.
- Chart palettes should be categorical by default. Use clearly different hues for unrelated categories, not lighter/darker shades of one color. Reserve shade ramps for continuous values only.
- Pie and multi-category chart legends must include visible color markers that match the chart slices or bars. Do not rely on color alone; keep labels and counts visible.
- Mobile chart layouts must be explicitly composed. Center the chart, stack legends below it, and only switch to side-by-side chart/legend layouts on wider screens.
- Approved result sheets are the academic source of truth. Student course schedules are advisory planning records and must not block result import.
- Staff enter letter grades, not numeric scores. Grade points are A=5, B=4, C=3, D=2, E=1, F=0; only `F` creates a carryover.
- Grade displays must be compact text values, not boxed badges. Use a consistent A-to-F semantic ramp: A green, B lime/yellow-green, C yellow, D amber, E orange, and F red.
- Student-facing results, CGPA, and transcript data must come from HOD-approved result sheets. Draft, submitted, and returned sheets stay private to the responsible staff/HOD workflow.
- Result index and aggregation pages must stay compact. Show result sheets as searchable, filterable tables or categorized browsers with explicit `View Sheet` / `Review` actions; do not stack full result matrices on dashboard/index pages.
- Organize result archives by the human context the viewer thinks in: HODs by student set/level/semester, faculty heads by department or faculty scope, and staff by their assigned set. Avoid flat all-record tables when the list crosses multiple sets or semesters.
- Result archive pages must not expose workflow states like draft, submitted, returned, or awaiting review. Archive pages show only published/approved results; workflow states belong on workflow pages such as imports and result approvals.
- HOD `Student Results` is a read-only archive. Keep it set-first with a searchable set picker and one table for the selected set across all levels. Do not put approval controls, review queues, charts, or performance insight sections on this page.
- HOD result approval work belongs on `Result Approvals`. Approval controls must only appear in approval-specific contexts, and the actions should live in the result matrix header so the HOD reviews the data and decides in the same workspace.
- Use `Return for Correction` for submitted results that need changes. Do not call this `Unapprove`; reserve terms like `Withdraw Approval` only for a future action that reverses an already approved sheet.
- Return-for-correction dialogs should allow an optional preset reason such as incomplete data, incorrect grades, or missing students/courses, plus an optional custom note. Do not force a correction note when the preset reason is enough.
- Full result matrices belong on a route-backed result-sheet detail page. The matrix should use the shared spreadsheet-style pattern: sticky student identity, scrollable course columns, fixed GPA, visible course units, course title tooltips/collapsible key, and compact color-coded grade cells.
- Spreadsheet/result import flows should show one decision at a time. Prefer route-backed step flows such as Context > Upload > Courses > Review, and only show the full matrix preview on the review step.
- Profile-photo change UI must use `ProfilePhotoIdentityContent` or preserve its layout exactly: avatar, name/email/identifier, then the compact change-picture action in one aligned row. Do not introduce separate noisy profile-photo cards for each role.
- When a table row, card, or repeated item primarily references a person, use `PersonCell` from `@/components/person-cell` or an equivalent `Avatar` treatment instead of bare name text. Prefer square avatars for platform records, initials fallback, and a concise secondary line such as email or registration number when it helps identification.
- When a named person is referenced in a table, card, review queue, approval record, or result sheet, link that person to their profile route when a profile route exists. Keep the link out only when navigation would be misleading or visually noisy in a tight metadata row.
- Registration numbers, access codes, and other identifiers that users may need to relay or paste must use `CopyableValue` or an equivalent boxed copy control. Do not render them as bare text in product tables, cards, or profile summaries.
- If a row already has an explicit View/Profile action, do not also make the registration number or identifier navigate to that profile. Keep identifiers copyable and keep navigation in the action column or action link.
- Compact ranking and summary tables should avoid copyable identifiers unless copying is the primary task. Prefer person identity, the relevant academic context, the metric, and an explicit action; keep copy controls in full record tables and detail views.
- Student sets must be displayed with their current academic level when the viewer is likely thinking in level terms, using `StudentSetLabel` / `studentSetText`. If the inferred current level is greater than the department's `minimum_years_to_degree`, display `Graduated` instead of showing a level.
- When a set selection determines the course adviser, do not show independent Set and Course Adviser filters. Put the course adviser context in the set option label or adjacent helper text so the dependency is obvious.
- Dashboard screens must keep a route-based way to go back. Nested forms, imports, detail pages, and level pages should link to their parent route with Wayfinder helpers instead of relying only on browser history.
- The global dashboard-header back control should use `window.history.back()` so it follows the user's real navigation path. Keep explicit in-page parent links route-based with Wayfinder; they communicate hierarchy rather than browser history.
- Server-generated files such as PDFs, CSV exports, spreadsheets, receipts, and source uploads must use native `<a>` download navigation or `DownloadLink`. Do not use an Inertia `Link` for binary responses because Inertia expects an Inertia page response.
- Dashboard action buttons must not sit transparently over patterned backgrounds. Use themed button surfaces so text and icons stay readable over the graph background.
- Text actions in tables, cards, and repeated records must look like actions. Use `ActionLink` from `@/components/action-link` for non-destructive route actions such as Edit, Assign, Replace, View, and Download. Do not render actions with plain foreground/background colors that blend into ordinary record text.
- Spreadsheet import/export actions should match the user’s current scope. HOD staff imports are department-scoped, faculty-head staff imports are faculty-scoped, and classlist exports should use the currently filtered student list unless the UI clearly says otherwise.
- Cards with variable-length content must reserve stable header and footer zones. Put only the repeated list/body inside a bounded shadcn `ScrollArea`, make the card/content `min-h-0` when flex layouts are involved, and test with many records so footer actions cannot overlap or collide with later content.
- When a form wraps `CardHeader`, `CardContent`, or `CardFooter`, use `CardForm` from `@/components/ui/card` so the form preserves the card's vertical spacing. Do not place a plain `<form>` directly inside `Card` around card sections.
- Use Sonner toast notifications for transient dashboard feedback such as successful imports, saves, deletes, and routine failures. Do not render inline alert boxes for normal flash success/error messages; reserve inline alerts for persistent page-specific problems that need to remain visible while the user fixes something.
- Use `AlertDialog` for destructive or irreversible confirmations such as delete, remove, reset, or submit-for-final-review actions. Do not use native `window.confirm()` / `confirm()` in product UI.
- Confirmation dialog copy must speak plainly in the product's voice: name the exact record, say what will happen, mention what stays unchanged when that prevents anxiety, and make the confirm button match the action. Avoid distant system phrasing like "This action cannot be undone" unless it is strictly true and useful.
- When an action has a platform rule behind it, explain the rule before the consequence in plain language. Prefer copy like "Only one account can be the school admin, so your school admin access will be disabled once the invite is accepted" over vague phrasing such as "After that person completes the transfer...".
- Product UI must not use "we" to clarify action intent. Use user-centered phrasing such as "You are replacing..." or neutral outcome phrasing such as "Course removed." Emphasize important dynamic context in confirmation copy, including names, course codes, department abbreviations, semester labels, and affected record names.
- Do not put forms in `Dialog`, `AlertDialog`, `Sheet`, or `Drawer`. Forms that create or edit records must be route-backed full pages, then redirect back on submit.
- Use `Dialog` only for short, non-destructive focused tasks or read-only detail previews. Use `AlertDialog` instead when the user must explicitly confirm a risky action.
- Use `Sheet` for temporary side panels such as mobile navigation or compact contextual panels. Use `Drawer` for mobile-first bottom/edge panels. Neither should replace normal create/edit pages.
- Use `DropdownMenu`, `Menubar`, or `ContextMenu` for action menus and command sets; use direct icon buttons when there are only one or two obvious row actions.
- Use `Tooltip` for icon-only buttons and compact controls whose meaning is not fully visible. Do not rely on tooltip text as the only place critical information appears.
- Use `Popover` for lightweight inline pickers or supplemental controls that stay tied to one trigger. Use `HoverCard` only for optional preview information.
- Use `Tabs` when switching between sibling views of equal importance, `Accordion` for expandable stacked content, and `Collapsible` for a single optional section.
- Use `Table` for dense records, comparisons, schedules, dues, results, and course lists. Tables should have clear low-contrast headers, zebra rows where useful, and explicit `Action` columns for edit/delete behavior.
- Use `Field`, `FieldLabel`, `FieldError`, and the shadcn input primitives for forms. Use `Input`, `PasswordInput`, `Textarea`, `InputGroup`, `InputOTP`, `Calendar`, `Select`, `Combobox`, `Checkbox`, `RadioGroup` / `RadioCard`, `Switch`, `Slider`, `Toggle`, and `ToggleGroup` according to the data shape instead of raw controls.
- Use `Avatar` for people, `Badge` for statuses/roles/categorical states, `Empty` for empty data states, `Skeleton` for loading placeholders, `Spinner` for short in-progress actions, `Progress` for measurable multi-step progress, `Pagination` for paged tables, `Breadcrumb` for route hierarchy, and `Separator` only when spacing alone is not enough.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Project Tool-Use Override

- Use Laravel Boost tools only when they materially improve the work. Do not repeatedly call `search-docs` for routine UI edits, simple controller mock payloads, local refactors, or changes where existing repo patterns already answer the question.
- Use Boost when the task is version-sensitive, needs current Laravel/Inertia/Pest/Wayfinder API details, requires database schema/query inspection, needs browser/app log debugging, or needs correct absolute URL generation.
- If a previous Boost result in the same task already covers the needed version-specific guidance, reuse that result instead of calling Boost again.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
    - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
