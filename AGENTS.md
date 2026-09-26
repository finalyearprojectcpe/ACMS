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
- The product has exactly two account roles: `Course Adviser` and `Student`. Do not add school-admin, faculty-head, HOD, generic staff, faculty, or department workflows.
- Store the current academic year on the student set; students inherit it. Only an explicit adviser update changes it. Creating sessions must not automatically promote a set. Course-unit limits belong to individual set semesters, never the whole set.
- Every academic session contains exactly a 1st and 2nd semester. Create both semester records with the session in one transaction and collect both semester unit limits in the session form. Do not expose a separate Add Semester workflow.
- Keep set-wide controls together on the route-backed Set Settings form. Keep frequently used invite copying on the set detail header. Closed or blocked invite states use danger text with a subtle danger background; open invites use success styling.
- Use the dedicated `Course Adviser` role label and `RoleBadge` treatment. Include the advised student-set context when it helps distinguish responsibilities.
- Do not track daily timetables, venues, or teaching assignments. The product tracks course advisers, their student sets, and course offerings by session, level, and semester.
- Course advisers collect dues through Paystack subaccounts. Store platform Paystack secret keys centrally, let advisers connect payout bank accounts through Paystack Settings, and attach published dues/payments to the active payout account. Do not ask advisers for Paystack API keys. Fetch provider-owned bank lists from Paystack and show a clear unavailable state when they cannot be loaded.
- Visible course categories are `Normal Course` and `Elective Course`. Elective is a flag chosen by the adviser so students can identify electives during course registration.
- Course offering screens should use `CourseOfferingBrowser` or match its pattern: a left rail grouped by level with semester options under each level, mobile `Select` controls for level and semester, and one course table for the selected level and semester. Do not show multiple semester tables side by side.
- Academic sessions use consecutive years and a department rather than exact dates. When creating a student set, advisers enter only the entry session's first year; derive the next year on the server and display it as muted, read-only context. Qualify standalone session labels with `Session`, for example `2024/2025 Session`.
- Long filter lists such as sessions, student sets, or courses should use a simple searchable popover/command picker. The closed trigger should read like a normal select, with the search field inside the opened picker instead of a noisy always-visible typing input.
- A student set is identified by its entry-session years and full department name. Use the canonical label `2025/2026 Computer Engineering` everywhere instead of inventing separate set names.
- Student set selection must use `StudentSetPicker` with the shared horizontal choice layout. Do not use badge boxes or stacked card-like rows inside set dropdowns.
- Profile photos and person avatars must be square with restrained corner rounding; do not render them as circles. Make profile photos directly editable: the image itself opens the file picker and reveals an edit icon on hover or keyboard focus, with an equally clear touch target. Do not add a separate `Change picture` button when the image can communicate and perform the action.
- Profile editing must be one coherent card and one form. Place the square profile photo to the left of the personal details, with honorific above full name, then place optional password fields in the section below. Use one `Save Changes` action for the complete form instead of independent save buttons for each section.
- Minimize visible UI elements and required clicks. When related fields change the same record as part of one task, combine them into one surface and one primary action. Remove duplicate actions, explanatory copy, summary boxes, and decorative controls that do not help the user decide or complete the task. Use progressive disclosure only when it meaningfully collapses a decision tree; do not hide routine fields merely to make a page look sparse.
- When a table row, card, or repeated item primarily references a person, use `PersonCell` from `@/components/person-cell` or an equivalent `Avatar` treatment instead of bare name text. Prefer square avatars for platform records, initials fallback, and a concise secondary line such as email or registration number when it helps identification.
- Student profiles and student-specific review or decision pages need a prominent square portrait beside the student's name (about 96px on mobile and 112px on desktop). Use the same-size initials fallback when no photo exists. Reserve compact `PersonCell` avatars for dense tables and lists; advisers often recognize students by face.
- When a named person is referenced in a table, card, or course-registration review, link that person to their profile route when a profile route exists. Keep the link out only when navigation would be misleading or visually noisy in a tight metadata row.
- Registration numbers, access codes, and other identifiers that users may need to relay or paste must use `CopyableValue` or an equivalent boxed copy control. Do not render them as bare text in product tables, cards, or profile summaries.
- If a row already has an explicit View/Profile action, do not also make the registration number or identifier navigate to that profile. Keep identifiers copyable and keep navigation in the action column or action link.
- Public student-set pages use `/student-set/{department-slug}/{start-year}{end-year}` and must resolve the set within the signed-in adviser workspace rather than exposing database IDs.
- Browser-facing routes and filter query strings must not expose internal auto-increment IDs. Prefer readable, stable domain identifiers when they clearly identify a record (such as registration number, compact course code, session years, and semester); include the parent context needed to make them unique. Use opaque public IDs only when no stable, unambiguous domain identifier exists or the value must be unguessable. Keep database foreign keys numeric internally, scope every public lookup to its owner, and update Wayfinder callers and redirects together when a route changes.
- Use `DashboardPageSurface` for ordinary dashboard pages. Keep equal outer margins on all four sides so the graph background remains visible, add even internal padding, and let the surface cover at least the usable viewport height. Do not stretch an arbitrary first child or nest another framed card just to fill space. The full-bleed Ask AI conversation is the exception. Give long lists their own bounded `ScrollArea` so page actions stay visible.
- When a set selection determines the course adviser, do not show independent Set and Course Adviser filters. Put the course adviser context in the set option label or adjacent helper text so the dependency is obvious.
- Dashboard screens must keep a route-based way to go back. Nested forms, imports, detail pages, and level pages should link to their parent route with Wayfinder helpers instead of relying only on browser history.
- The global dashboard-header back control should use `window.history.back()` so it follows the user's real navigation path. Keep explicit in-page parent links route-based with Wayfinder; they communicate hierarchy rather than browser history.
- Server-generated files such as PDFs, XLSX exports, and receipts must use native `<a>` download navigation or `DownloadLink`. Do not use an Inertia `Link` for binary responses because Inertia expects an Inertia page response.
- Dashboard action buttons must not sit transparently over patterned backgrounds. Use themed button surfaces so text and icons stay readable over the graph background.
- Text actions in tables, cards, and repeated records must look like actions. Use `ActionLink` from `@/components/action-link` for non-destructive route actions such as Edit, Assign, Replace, View, and Download. Do not render actions with plain foreground/background colors that blend into ordinary record text.
- Spreadsheet imports and exports must stay scoped to the current course adviser and selected student set. Classlist exports should use the currently filtered student list unless the UI clearly says otherwise.
- Cards with variable-length content must reserve stable header and footer zones. Put only the repeated list/body inside a bounded shadcn `ScrollArea`, make the card/content `min-h-0` when flex layouts are involved, and test with many records so footer actions cannot overlap or collide with later content.
- Collection headers contain identity, filters, and concise summaries. Put actions that add to or change the displayed collection in a stable footer when that keeps the workflow predictable. Keep the repeated content inside a bounded `ScrollArea` and the footer outside it; page-level creation actions may remain in page headers.
- When a form wraps `CardHeader`, `CardContent`, or `CardFooter`, use `CardForm` from `@/components/ui/card` so the form preserves the card's vertical spacing. Do not place a plain `<form>` directly inside `Card` around card sections.
- Use Sonner toast notifications for transient dashboard feedback such as successful imports, saves, deletes, and routine failures. Do not render inline alert boxes for normal flash success/error messages; reserve inline alerts for persistent page-specific problems that need to remain visible while the user fixes something.
- Use `AlertDialog` for destructive or irreversible confirmations such as delete, remove, reset, or submit-for-final-review actions. Do not use native `window.confirm()` / `confirm()` in product UI.
- Confirmation dialog copy must speak plainly in the product's voice: name the exact record, say what will happen, mention what stays unchanged when that prevents anxiety, and make the confirm button match the action. Avoid distant system phrasing like "This action cannot be undone" unless it is strictly true and useful.
- When an action has a platform rule behind it, explain the rule before the consequence in plain language.
- Product UI must not use "we" to clarify action intent. Use user-centered phrasing such as "You are replacing..." or neutral outcome phrasing such as "Course removed." Emphasize important dynamic context in confirmation copy, including names, course codes, department abbreviations, semester labels, and affected record names.
- Do not put forms in `Dialog`, `AlertDialog`, `Sheet`, or `Drawer`. Forms that create or edit records must be route-backed full pages, then redirect back on submit.
- Use `Dialog` only for short, non-destructive focused tasks or read-only detail previews. Use `AlertDialog` instead when the user must explicitly confirm a risky action.
- Use `Sheet` for temporary side panels such as mobile navigation or compact contextual panels. Use `Drawer` for mobile-first bottom/edge panels. Neither should replace normal create/edit pages.
- Use `DropdownMenu`, `Menubar`, or `ContextMenu` for action menus and command sets; use direct icon buttons when there are only one or two obvious row actions.
- Use `Tooltip` for icon-only buttons and compact controls whose meaning is not fully visible. Do not rely on tooltip text as the only place critical information appears.
- Use `Popover` for lightweight inline pickers or supplemental controls that stay tied to one trigger. Use `HoverCard` only for optional preview information.
- Use `Tabs` when switching between sibling views of equal importance, `Accordion` for expandable stacked content, and `Collapsible` for a single optional section.
- Use `Table` for dense records, comparisons, dues, and course lists. Tables should have clear low-contrast headers, zebra rows where useful, and explicit `Action` columns for edit/delete behavior.
- Keep short tables and their associated headings/summaries to a content-appropriate max width instead of stretching them across a wide page. Center constrained table blocks horizontally for visual balance; do not leave a max-width table anchored to the left of a wide surface. Preserve horizontal scrolling on narrow screens when the columns cannot fit legibly.
- When a table row's only action is opening its detail page, do not add an `Action` column. Link the row's primary identifying text instead. Reserve `Action` columns for multiple actions or non-navigation actions.
- Use `Field`, `FieldLabel`, `FieldError`, and the shadcn input primitives for forms. Use `Input`, `PasswordInput`, `Textarea`, `InputGroup`, `InputOTP`, `Calendar`, `Select`, `Combobox`, `Checkbox`, `RadioGroup` / `RadioCard`, `Switch`, `Slider`, `Toggle`, and `ToggleGroup` according to the data shape instead of raw controls.
- Use `Avatar` for people, `Badge` for statuses/roles/categorical states, `Empty` for empty data states, `Skeleton` for loading placeholders, `Spinner` for short in-progress actions, `Progress` for measurable multi-step progress, `Pagination` for paged tables, `Breadcrumb` for route hierarchy, and `Separator` only when spacing alone is not enough.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.
- Keep the product limited to course-adviser and student workflows. Do not add speculative roles, approval chains, reports, or academic-result features before their workflow and data model are explicitly requested.
- Controllers and React components should have one clear responsibility. Split files by workflow before they approach roughly 500 lines; generated files are exempt.
- Prefer small domain controllers, form requests, and services over adding unrelated actions or payload builders to a catch-all controller.
- Tests should protect authorization, ownership boundaries, money, data integrity, and complete user workflows. Delete obsolete tests with their removed feature; do not preserve low-value assertions solely to increase test count.

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
