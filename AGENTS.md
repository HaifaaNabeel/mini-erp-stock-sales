# AGENTS.md
# Mini ERP Stock & Sales — Agent Engineering Rules

هذا الملف يحتوي على القواعد الهندسية والتنفيذية الإلزامية لجميع وكلاء الذكاء الاصطناعي الذين يعملون على هذا المشروع.

يجب قراءة هذه التعليمات والالتزام بها قبل فحص المشروع أو كتابة أو تعديل أي كود.

---

# 1. Project Identity

- Project: Mini ERP Stock & Sales
- Framework: CodeIgniter 3.1.13
- Language: PHP
- Database: MariaDB / MySQL
- Frontend: HTML, CSS, JavaScript, jQuery, AJAX
- Development Environment: Windows + XAMPP
- Current PHP Environment: PHP 8.2.12
- Minimum PHP Code Compatibility: PHP 7.4
- Currency: SAR
- Quantity Type: Integers only
- Interface Language: Arabic RTL

## Strict Technical Restrictions

1. Use CodeIgniter 3.1.13 only.
2. Write PHP code compatible with PHP 7.4 and test it on PHP 8.2.
3. Do not use PHP 8-only syntax or features.
4. Do not introduce Laravel, Symfony, or another PHP framework.
5. Do not introduce external ORM libraries.
6. Use CodeIgniter Query Builder for database operations.
7. Do not use PDO directly.
8. Use HTML, CSS, JavaScript, jQuery, and AJAX only for frontend implementation.
9. Do not introduce React, Vue, Angular, or another frontend framework.
10. Avoid unnecessary dependencies and packages.

---

# 2. Documentation Is the Source of Truth

Before implementing any feature, inspect these files:

1. `docs/MASTER_PROJECT_DOCUMENT_AR.md`
   - Main functional requirements.
   - Business rules.
   - Roles and permissions.
   - Database requirements.

2. `docs/decisions.md`
   - Approved architectural decisions.
   - Implementation decisions.
   - Constraints and exceptions.

3. `docs/implementation-log.md`
   - Completed work.
   - Current implementation stage.
   - Pending tasks.
   - Known issues.

The master project specification is authoritative.

IMPORTANT:

- Never modify `MASTER_PROJECT_DOCUMENT_AR.md` without explicit approval.
- Never silently remove, reinterpret, or weaken an approved requirement.
- Never introduce a new business rule without approval.
- If two documents conflict, identify the conflict and ask for clarification instead of guessing.
- Do not assume that a proposed feature is approved unless it is documented or explicitly approved.

---

# 3. Project Architecture and Coding Standards

Follow CodeIgniter 3 MVC architecture.

## Controllers

Controllers must be relatively thin.

Their responsibilities:

- Receive HTTP requests.
- Validate request flow.
- Check authentication and authorization.
- Call Models or dedicated business services.
- Return views or JSON responses.

Do not place complex business calculations or large SQL queries inside Controllers.

## Models

Models are responsible for:

- Database access.
- Query Builder operations.
- Data retrieval and persistence.
- Database-related operations.
- Reusable data access methods.

All database queries must be located in Models or dedicated database access components.

## Business Services

For complex business operations, use dedicated services where appropriate.

Examples:

- InventoryService.
- SalesService.
- InvoiceCancellationService.
- AuditService.

Business services should centralize complex business rules and transaction workflows.

Avoid duplicating inventory or financial logic across multiple Controllers.

## Views

Views are responsible for presentation only.

Do not put database queries or business logic inside Views.

Use reusable layouts and partial views when appropriate.

---

# 4. Security Rules

Security rules are mandatory.

## Input Validation

- Validate all user input on the server.
- Never trust values submitted by the browser.
- Validate IDs, quantities, prices, dates, and status values.
- Use CodeIgniter Form Validation where appropriate.

## SQL Injection

- Use CodeIgniter Query Builder.
- Use parameterized queries when raw SQL is necessary.
- Never concatenate untrusted user input into SQL statements.

## XSS Protection

- Escape all dynamic output in Views.
- Use `html_escape()` for HTML output.
- Escape values according to their output context.
- Never render untrusted HTML directly.

## CSRF

- Enable CodeIgniter CSRF protection.
- Use `form_open()` for standard forms.
- Ensure AJAX requests include the appropriate CSRF token.
- Never disable CSRF globally to solve an individual request issue.

## Authentication

- Use `password_hash()` for password storage.
- Use `password_verify()` for password verification.
- Never store plaintext passwords.
- Never expose password hashes in API responses or Views.
- Reject disabled users.
- Invalidate access when a user's account is disabled.

## Authorization

- Enforce permissions on the server.
- Hiding buttons or links is not authorization.
- Protect direct HTTP requests and AJAX endpoints.
- Check the user's role and resource ownership on every protected operation.
- Never trust a user-supplied warehouse ID without authorization validation.

## Secrets

Never commit:

- Database passwords.
- Encryption keys.
- API keys.
- Real user passwords.
- Private credentials.
- Sensitive environment configuration.

Keep local secrets outside tracked source files.

---

# 5. AJAX and JSON Response Standards

All AJAX endpoints must return consistent JSON responses.

Use the `json_response()` helper in `MY_Controller` if it exists.

Before using it:

- Inspect its actual implementation.
- Confirm its arguments and behavior.
- Do not assume an API that does not exist.

If it does not exist, implement a reusable and consistent helper in the appropriate project base controller before using it across the application.

JSON responses should use a consistent structure, for example:

- success
- message
- data
- errors

Use appropriate HTTP status codes.

Do not return HTML error pages from endpoints expected to return JSON.

Never expose SQL errors, stack traces, secrets, or internal server details to users.

---

# 6. Database Engineering Rules

Database implementation is incremental.

Create tables when the feature requiring them is implemented.

Do not create the entire database schema prematurely.

## Database Standards

- Engine: InnoDB.
- Charset: utf8mb4.
- Use appropriate collations.
- Define primary keys.
- Define foreign keys.
- Define unique constraints.
- Add indexes based on actual query patterns.
- Use compatible MariaDB 10.4.32 syntax.
- Match foreign key data types exactly.
- Avoid unnecessary columns and redundant data.

## Database Integrity

- Use database constraints in addition to application validation.
- Do not rely only on frontend validation.
- Preserve historical business records.
- Prefer disabling records over deleting referenced records.
- Use transactions for multi-step business operations.

## Database Scripts

Store incremental SQL scripts under:

`database/`

Use a clear naming convention, for example:

- `001_f03_roles_users.sql`
- Subsequent scripts should follow the actual implementation order.

Document how each script is applied.

Do not drop, truncate, or recreate existing tables without explicit approval.

Never run destructive database commands without approval.

---

# 7. Critical Business Rules

The following rules must never be violated.

## Inventory

1. Quantities must be integers.
2. Stock must never become negative.
3. Stock is maintained independently for each product and warehouse.
4. Every stock-changing operation must have a corresponding stock movement.
5. Stock movements are historical records and must not be deleted or silently modified.
6. Stock adjustments require a reason.
7. Stock and movement updates must be atomic.

## Sales

1. All financial calculations must be performed on the server.
2. Never trust client-side invoice totals.
3. Use appropriate decimal precision for financial values.
4. Currency is SAR.
5. Preserve the historical unit price in invoice items.
6. Invoice creation and inventory deduction must happen within a single database transaction.
7. Prevent concurrent sales from exceeding available stock.
8. Never allow negative stock.

## Invoice Cancellation

1. Cancellation is not deletion.
2. Preserve the original invoice and its items.
3. Restore inventory through reverse stock movements.
4. Prevent duplicate cancellation.
5. Execute cancellation atomically.
6. Preserve cancellation details and historical information.

## Warehouse Access

1. Admin has access to all warehouses.
2. Warehouse users are restricted to their assigned warehouses.
3. Sales users are restricted to their assigned warehouses.
4. Validate warehouse access on the server for every relevant operation.
5. Never rely on warehouse IDs submitted by the browser.

---

# 8. User Roles and Permissions

The project has exactly three approved roles:

- admin
- warehouse
- sales

Do not create additional roles without approval.

Follow the permissions defined in the master specification.

Do not expand a role's permissions because an implementation is easier that way.

Every protected action must be checked server-side.

---

# 9. Project Implementation Workflow

For every feature, follow this workflow.

## Phase 1 — Inspect

- Read the relevant documentation.
- Inspect existing files.
- Understand existing architecture.
- Identify dependencies.
- Identify required database changes.
- Check whether similar functionality already exists.

## Phase 2 — Plan

- Define the exact implementation scope.
- Identify Controllers, Models, Services, and Views.
- Identify SQL changes.
- Identify security requirements.
- Identify test scenarios.

Do not expand the approved scope.

## Phase 3 — Implement

- Implement only the approved feature.
- Follow existing naming conventions.
- Reuse existing helpers and components.
- Avoid duplicated business logic.
- Preserve existing functionality.

## Phase 4 — Verify

Run relevant checks:

- PHP syntax checks.
- Database compatibility checks.
- Functional tests.
- Authorization tests.
- Validation tests.
- Transaction tests.
- Security checks.

Do not claim that a test passed unless it was actually executed.

## Phase 5 — Document

Update:

- `docs/implementation-log.md`
- `database/README.md` when database changes occur.
- `docs/decisions.md` only when an approved architectural decision needs recording.

Never modify the master specification without approval.

## Phase 6 — Report and Stop

Provide a report containing:

- Files created.
- Files modified.
- Database changes.
- Implemented functionality.
- Tests executed.
- Test results.
- Known issues.
- Remaining work.

Stop after completing the requested feature.

Do not automatically begin the next feature.

---

# 10. Project-Specific Restrictions

1. Work only inside:

   `mini-erp-stock-sales`

2. Never modify the sibling project:

   `mini_erp_frist`

3. Never modify CodeIgniter's `system/` directory.

4. Preserve existing PHP 8.2 compatibility fixes.

5. Do not delete existing files without approval.

6. Do not change Git branches without approval.

7. Do not create Git commits without explicit approval.

8. Do not push to remote repositories.

9. Do not modify Git remotes or repository visibility.

10. Do not perform destructive database operations.

11. Do not install new dependencies without explaining why they are needed.

12. Do not overwrite local configuration or user data.

---

# 11. Current Implementation Roadmap

The approved execution order is:

1. F01 — Project Foundation (initially established; review before rebuilding).
2. F03 — Authentication & Authorization.
3. F04 — Categories, Products & Warehouses.
4. F05 — Warehouse Stock.
5. F06 — Stock Movements.
6. F07 — Sales Invoices.
7. F08 — Invoice Cancellation.
8. F09 — Reports.
9. F10 — Audit Logs & Final Testing.

Database implementation proceeds incrementally with each feature.

F02 is reconciled against the complete database schema at the end of implementation.

Do not skip or reorder features without approval.

---

# 12. Final Instruction

These instructions apply to every AI agent working on this repository.

When receiving a task:

1. Read this AGENTS.md.
2. Read the relevant project documentation.
3. Inspect the current implementation.
4. Execute only the requested task.
5. Verify the work.
6. Document the results.
7. Report honestly.
8. Stop and wait for approval.

If an instruction is ambiguous, a requirement conflicts with another, or a destructive action is needed, stop and ask the project owner before proceeding.

Correctness, security, data integrity, and compliance with the approved project specification take priority over implementation speed.