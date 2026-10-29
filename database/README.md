# database/

**Purpose:** Hold versioned SQL schema scripts, seed data (non-secret), and migration notes for Mini ERP Stock & Sales.

**Current contents:** This README only. No business tables or schema files yet (foundation phase).

**Rules:**

- Track schema / migration scripts.
- Do not commit dumps that contain real customer or production data.
- Real credentials belong in local CI config, not in SQL files committed to Git.
