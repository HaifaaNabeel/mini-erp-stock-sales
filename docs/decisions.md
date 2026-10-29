# Decision Log — Mini ERP Stock & Sales

Records architectural and product decisions. Unresolved items stay **PROPOSED** or **PENDING APPROVAL** until explicitly approved.

| Status meaning | Description |
| --- | --- |
| APPROVED | Explicitly agreed |
| PROPOSED | Suggested default; not yet approved |
| PENDING APPROVAL | Needs a choice before related work proceeds |
| REJECTED | Considered and declined |
| SUPERSEDED | Replaced by a later decision |

---

## DEC-001 — Application framework

| Field | Value |
| --- | --- |
| **Topic** | Web application framework |
| **Status** | APPROVED |
| **Decision / default** | CodeIgniter 3.1.13 |
| **Reason** | Official project specification; installed and Welcome page verified on PHP 8.2.12 |
| **Date** | 2026-10-29 |
| **Notes** | See DEC-002. |

---

## DEC-002 — CodeIgniter 3 installation method

| Field | Value |
| --- | --- |
| **Topic** | How to place CI3 into this repository |
| **Status** | APPROVED (executed) |
| **Decision / default** | **A)** Extract local `CodeIgniter-3.1.13.zip`; copy into project root while preserving project `.gitignore`, `.editorconfig`, `README.md`, `docs/`, `assets/`, `database/` |
| **Reason** | Matches official spec (F01 / §12); archive verified `CI_VERSION = 3.1.13` |
| **Date** | 2026-10-29 |
| **Notes** | ZIP copies of `.gitignore` / `.editorconfig` were not applied. |

---

## DEC-003 — Target runtime environment

| Field | Value |
| --- | --- |
| **Topic** | Local / deployment runtime |
| **Status** | APPROVED |
| **Decision / default** | XAMPP on Windows: PHP 8.2.x + MariaDB (MySQL-compatible) under `C:\xampp\htdocs\...` |
| **Reason** | Verified on this machine: PHP 8.2.12 (CLI), MariaDB 10.4.32 via `C:\xampp\mysql\bin\mysql.exe` |
| **Date** | 2026-10-29 |
| **Notes** | Welcome smoke-tested on PHP 8.2.12. Dynamic-property deprecations addressed via DEC-014. |

---

## DEC-014 — PHP 8.2 dynamic property deprecations (CI3 core)

| Field | Value |
| --- | --- |
| **Topic** | Fix `Creation of dynamic property` on URI/Router/Controller/Loader without hiding errors |
| **Status** | APPROVED (executed) |
| **Decision / default** | Application subclasses `MY_URI`, `MY_Router`, `MY_Loader`, `MY_Controller` with `#[\AllowDynamicProperties]`; app controllers extend `MY_Controller`. Do not patch `system/` unless a later case requires it. |
| **Reason** | Matches CI3 subclass pattern and upstream PHP 8.2 guidance; keeps framework tree pristine; does not weaken `error_reporting` |
| **Date** | 2026-10-29 |

---

## DEC-004 — Database product

| Field | Value |
| --- | --- |
| **Topic** | RDBMS |
| **Status** | PROPOSED |
| **Decision / default** | MariaDB 10.4.x (XAMPP) treated as MySQL-compatible |
| **Reason** | Available in the verified local stack; aligns with project constraint (MySQL or MariaDB). |
| **Date** | 2026-10-29 |

---

## DEC-005 — Default currency

| Field | Value |
| --- | --- |
| **Topic** | System / invoice currency |
| **Status** | PENDING APPROVAL |
| **Decision / default** | *Unresolved* (common proposals: single fixed currency code, e.g. SAR or USD) |
| **Reason** | Affects schema (money columns), UI formatting, and reports. |
| **Date** | — |

---

## DEC-006 — Fractional quantities

| Field | Value |
| --- | --- |
| **Topic** | Whether stock/sales quantities allow decimals |
| **Status** | PENDING APPROVAL |
| **Decision / default** | *Unresolved* (integer-only vs decimal with fixed precision) |
| **Reason** | Affects column types, validation, and stock math. |
| **Date** | — |

---

## DEC-007 — Sales permissions / RBAC baseline

| Field | Value |
| --- | --- |
| **Topic** | Who may create/edit/cancel sales; role model |
| **Status** | PENDING APPROVAL |
| **Decision / default** | *Unresolved* (planned: basic RBAC; roles not yet defined) |
| **Reason** | Auth and permissions are out of scope for foundation; must be decided before auth implementation. |
| **Date** | — |

---

## DEC-008 — Customer master fields

| Field | Value |
| --- | --- |
| **Topic** | Required vs optional customer attributes |
| **Status** | PENDING APPROVAL |
| **Decision / default** | *Unresolved* |
| **Reason** | Needed before customers table / forms. Master document should drive this. |
| **Date** | — |

---

## DEC-009 — Invoice cancellation policy

| Field | Value |
| --- | --- |
| **Topic** | Soft-cancel vs hard-delete; stock reversal rules |
| **Status** | PENDING APPROVAL |
| **Decision / default** | *Unresolved* |
| **Reason** | Impacts inventory integrity and audit trail. |
| **Date** | — |

---

## DEC-010 — Optional discount feature

| Field | Value |
| --- | --- |
| **Topic** | Line/header discounts on sales invoices |
| **Status** | PENDING APPROVAL |
| **Decision / default** | *Unresolved* (include vs defer) |
| **Reason** | Scope control; keep MVP simple unless approved. |
| **Date** | — |

---

## DEC-011 — Optional CSV import/export

| Field | Value |
| --- | --- |
| **Topic** | CSV for products/stock/customers |
| **Status** | PENDING APPROVAL |
| **Decision / default** | *Unresolved* (defer recommended for MVP) |
| **Reason** | Extra surface area; not required for foundation. |
| **Date** | — |

---

## DEC-012 — Git default branch and remote

| Field | Value |
| --- | --- |
| **Topic** | Branch name and GitHub remote |
| **Status** | PENDING APPROVAL |
| **Decision / default** | Local branch name: `main`. Remote: create **private** GitHub repo when approved; no push until explicit approval. |
| **Reason** | Task requirement; avoid public exposure of unfinished work. |
| **Date** | 2026-10-29 |

---

## DEC-013 — Master project document (Arabic)

| Field | Value |
| --- | --- |
| **Topic** | Source of truth document |
| **Status** | APPROVED (executed) |
| **Decision / default** | `docs/MASTER_PROJECT_DOCUMENT_AR.md` holds the full v1.0 specification (byte-identical to `Mini_ERP_Master_Project_Document_AR_v1.0.md`); v1.0 `.md`/`.docx` kept as reference copies |
| **Reason** | Spec §13 requires the official path; consolidation completed 2026-10-29 |
| **Date** | 2026-10-29 |
