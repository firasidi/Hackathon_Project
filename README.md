# INSaf

INSaf is a React and Laravel application for recording ranked university preferences and checking whether the saved preferences still match a fingerprint anchored in Hedera Consensus Service (HCS). It is a tamper-evident record demonstration, not a complete decentralized identity (DID) or verifiable-credentials system.

## Architecture and data flow

- **Frontend:** React 19 and Vite (`FrontEnd/`); Laravel JSON API base URL defaults to `http://127.0.0.1:8000/api` in `FrontEnd/src/Api.js`.
- **Backend:** Laravel 12 (`BackEnd/`) with Sanctum authentication.
- **Database:** MySQL for a shared/deployed instance. PHPUnit uses an in-memory SQLite database.
- **Hedera sender:** Node.js and the Hedera SDK (`BackEnd/hedera/`).
- A student submits an ordered list of university IDs. Laravel serializes the fingerprint as `salt|student-code|comma-separated-ordered-choice-IDs`, calculates SHA-256, and asks the Node sender to anchor `c:<hash>` to the configured HCS topic.
- The application stores the submitted choices and receipt metadata, including network, topic, sequence, transaction ID, and submission time. The consensus timestamp is filled when available from a Mirror Node response.
- Verification reads the exact topic and sequence from the configured Mirror Node, validates the returned record, recalculates the fingerprint, and compares it with the anchored message and current application record. A missing indexed record is pending; network or malformed-record errors do not count as successful verification.
- Incident reports are revalidated against the saved application and the original HCS record before their PDF and report data are stored. Administrator routes require an administrator account. Report PDFs are stored on Laravel's private local disk.

## Requirements

- PHP 8.2+, Composer, and the PHP extensions required by Laravel and the selected database driver.
- MySQL 8 (or a compatible MySQL server) for a shared installation.
- Node.js compatible with Vite 8 and npm.
- A Hedera account and existing HCS topic for live anchoring. Use testnet for demonstrations. Automated tests mock Hedera calls and do not need credentials, network access, or HBAR.

## Configuration and local setup

Keep real credentials in ignored local `.env` files. Do not commit `.env`, account private keys, API tokens, or student credentials.

1. **Configure Laravel**

   ```powershell
   Set-Location BackEnd
   Copy-Item .env.example .env
   composer install
   php artisan key:generate
   ```

   Configure the local `BackEnd/.env` database values for MySQL, for example:

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=<database_name>
   DB_USERNAME=<database_user>
   DB_PASSWORD=<database_password>
   ```

   Set the Hedera values in `BackEnd/.env`:

   ```dotenv
   HEDERA_NETWORK=testnet
   HEDERA_TOPIC_ID=<existing_topic_id>
   HEDERA_MIRROR_NODE_URL=https://testnet.mirrornode.hedera.com
   DEMO_MODE=false
   ```

   For a **new, local demonstration database only**, after confirming the selected database connection and database name, run `php artisan migrate`, then `php artisan db:seed` once. For an existing database, inspect the pending migrations and take a backup before running `php artisan migrate`. The receipt metadata and review-notes migrations add nullable columns; this project does not require `migrate:fresh` or any destructive reset command.

2. **Configure the Hedera sender for live submissions**

   ```powershell
   Set-Location BackEnd\hedera
   Copy-Item .env.example .env
   npm install
   ```

   Fill `BackEnd/hedera/.env` locally with placeholders replaced by your own testnet account ID and ECDSA private key:

   ```dotenv
   HEDERA_ACCOUNT_ID=<testnet_account_id>
   HEDERA_PRIVATE_KEY=<testnet_ecdsa_private_key>
   HEDERA_NETWORK=testnet
   ```

   Laravel supplies the configured network and topic ID to the sender. Never put real key values in this README, source control, or frontend environment.

3. **Run the services** in separate terminals:

   ```powershell
   Set-Location BackEnd
   php artisan serve --host=127.0.0.1 --port=8000
   ```

   ```powershell
   Set-Location FrontEnd
   npm install
   npm run dev
   ```

   Open the Vite URL printed by the frontend. If the Laravel API is hosted elsewhere, update the API base URL in `FrontEnd/src/Api.js`.

## Tests and production build

```powershell
Set-Location BackEnd
php artisan test
```

Tests use SQLite in memory and mock Hedera process and Mirror Node calls. They do not submit transactions to a live network.

```powershell
Set-Location FrontEnd
npm run build
npm run lint
```

## Demonstration

1. Use a local database and a local administrator account prepared for the demonstration. Keep administrator credentials private.
2. Configure Hedera testnet, an existing topic, and the sender credentials above. A live submission sends an HCS message and may incur network fees.
3. Submit preferences once and retain the returned receipt. A second submission is rejected after a receipt is saved; this preserves the original receipt. There is no receipt-version history or resubmission workflow.
4. Verify the receipt. The application reads the specific topic/sequence from the Mirror Node; allow time for indexing if it returns a pending response.
5. The seeded synthetic student `S001` has no application by default. Sign in as `S001`, submit preferences once, and wait for a real testnet receipt. Then set `APP_ENV=local` (or `testing`) and `DEMO_MODE=true` in the backend only, and sign in as an administrator.
6. On the administrator dashboard, choose **Hack / Simulate Tampering**. The server changes only S001's current saved preference order; it does not change or create a Hedera receipt. The UI immediately checks the original receipt against the Mirror Node and shows the before/after preferences, preserved topic/sequence, and the integrity mismatch. The API enforces administrator authorization and the environment/demo-mode restriction. Do not enable demo mode in production.
7. If the current record differs from the anchored preferences, generate the incident PDF and submit the report. The backend checks the report against the saved receipt, current application, and actual HCS record before storage. An administrator can inspect the report, download its PDF, add review notes, and change its review status.

## Inspecting Hedera records

Use the `network`, `topicId`, and `sequence` from the saved receipt to inspect the public Mirror Node record:

```text
<HEDERA_MIRROR_NODE_URL>/api/v1/topics/<HEDERA_TOPIC_ID>/messages/<SEQUENCE>
```

The decoded HCS message for a preference receipt is `c:<sha256 fingerprint>`. The application verifies the topic, sequence, encoding, and hash comparison; it does not rely only on the hash stored in MySQL.

## Placement commitments and limitations

- The demo placement rule orders students by descending score and then student code, assigning each student their highest-ranked university with remaining capacity. The `run` action anchors hashes for the rule/capacities, a Merkle root of submitted preference fingerprints, and a results summary.
- These commitments are **published commitments, not independent placement verification**. The application does not retrieve and verify those commitments from the Mirror Node, provide Merkle inclusion proofs, or independently recompute and validate the published placements. Do not present placement integrity as verified; preference-receipt verification is the implemented verification flow.
- HCS provides an ordered, timestamped public record. It does not prove who controlled an account, that input data was correct, or who caused a discrepancy. A mismatch is evidence of an integrity discrepancy and requires further investigation; it does not identify a culprit.
- Mirror Node indexing may be delayed or unavailable. Verification must be retried when pending or when an upstream/configuration error is reported.
- A saved receipt is immutable through the normal submission endpoint. Intentional resubmission with preserved versions would require a receipt-history design and database migration; that workflow is not currently implemented.
- Production deployments must use real secret management, correct administrator provisioning, backups, HTTPS, and `DEMO_MODE=false`.
