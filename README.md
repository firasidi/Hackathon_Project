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
- Placement commitments can be checked at `/api/commitments/verify`: the latest commitment of each kind is checked against its HCS message and recomputed from current database state. Older rows are marked `superseded`, not treated as evidence of a discrepancy. The panel links to both HashScan and the exact Mirror Node message URL used by verification.
- Incident reports are revalidated against the saved application and the original HCS record before their PDF and report data are stored. Administrator routes require an administrator account. Report PDFs are stored on Laravel's private local disk.
- Changing a student's email or phone requires a short-lived verification code sent to the currently registered email. Proposed contact details remain pending until confirmation; password-reset codes are stored separately. City-only profile edits do not require a contact verification code.
- University choices are saved as a separate, editable draft as the student makes selections; draft changes are not placement input and are not submitted or anchored on HCS. Clicking **Submit and get receipt** sends a short-lived code to the student's registered email and locks further edits. Only successful code confirmation promotes the saved draft to the submitted application and anchors its receipt; expired codes can be resent without changing the locked choices.

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

   Configure Laravel's mail transport (`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_FROM_ADDRESS`) so preference-submission, profile-change, and password-reset codes reach the registered inbox. Laravel's default `log` mailer does not deliver mail; protected changes and submissions are blocked with an explicit error when the mailer is `log` or `array` outside tests.

   For a **new, local demonstration database only**, after confirming the selected database connection and database name, run `php artisan migrate`, then `php artisan db:seed` once. For an existing database, inspect pending migrations and take a backup before running `php artisan migrate`. Migrations are additive; this project does not require `migrate:fresh`, `migrate:refresh`, or `db:wipe`.

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

1. Use a local demo database, the synthetic student `S001`, and a local administrator account. Keep administrator credentials private.
2. Configure Hedera testnet, an existing topic, and the sender credentials above. A live submission sends an HCS message and may incur network fees.
3. Set backend `APP_ENV=local` and `DEMO_MODE=true`. After confirming the target database and backup, use `php artisan insaf:demo-reset` to clear only the specified demo/application placement state. Existing on-chain messages remain on the topic.
4. Sign in as `S001`; selecting universities saves an editable draft only, which remains unsubmitted. After selecting at least three, choose **Submit and get receipt**. The code is sent to the registered email and the draft becomes locked. Enter the confirmation code; only then are the saved draft promoted to submitted preferences and anchored on testnet. A second submission is rejected with HTTP 409; this preserves the original receipt. There is no receipt-version history or resubmission workflow.
5. Verify the receipt. The application reads the specific topic/sequence from the Mirror Node; allow time for indexing if it returns a pending response. The receipt page provides HashScan and raw Mirror Node links.
6. Sign in as an administrator and run placement to anchor the rules, preference Merkle root, and results. The **Verify commitments** panel checks the latest set; older sets are shown as superseded.
7. Choose **Hack / Simulate Tampering**. The server changes only S001's current saved preference order; it does not change or create a Hedera receipt. The UI immediately checks the original receipt and commitment records against the Mirror Node and shows the before/after preferences and mismatch. This demonstrates detection, not prevention or attribution.
8. Generate the incident report PDF and submit it. The backend checks the report against the saved receipt, current application, and actual HCS record before storage. An administrator can inspect the report, download its PDF, add review notes, and change its review status.

See [DEMO.md](./DEMO.md) for the exact rehearsal sequence and troubleshooting.

## Inspecting Hedera records

Use the `network`, `topicId`, and `sequence` from the saved receipt to inspect the public Mirror Node record:

```text
<HEDERA_MIRROR_NODE_URL>/api/v1/topics/<HEDERA_TOPIC_ID>/messages/<SEQUENCE>
```

The decoded HCS message for a preference receipt is `c:<sha256 fingerprint>`. The application verifies the topic, sequence, encoding, and hash comparison; it does not rely only on the hash stored in MySQL.

## Placement commitment verification and limitations

- The demo placement rule orders students by descending score and then student code, assigning each student their highest-ranked university with remaining capacity. The `run` action anchors hashes for the rule/capacities, a Merkle root of submitted preference fingerprints, and a results summary.
- The **Verify commitments** panel reads each latest commitment sequence from the Mirror Node, validates topic and sequence, checks that the message is exactly `<kind>:<stored hash>`, and compares the stored hash with a recomputation from current data. `verified` means these checks agree; `data_changed` means the HCS record matches the stored commitment but current database data no longer does. `superseded` identifies an older run and is neutral; `pending`, `unavailable`, and `chain_mismatch` are not successes.
- The Merkle root is recomputed from current preference fingerprints, but the application does **not** provide Merkle inclusion proofs. The placement algorithm is **not independently re-run by the verifier**; the `results` commitment is checked against the current stored placement/cutoff snapshot, not proven to be the result of the rules. These checks strengthen commitment integrity but do not constitute independent verification of every placement decision.
- The ministry must anchor its own rules, input root, and results records for the comparison to have evidentiary value. A commitment generated only in the application database is not an HCS commitment.
- New commitment rows store the network and topic used for anchoring. Older rows without those nullable fields are checked using the currently configured Hedera network/topic; if the configuration has changed since those rows were anchored, configure the original network/topic before verifying them.
- HCS provides an ordered, timestamped public record. It does not prove who controlled an account, that input data was correct, or who caused a discrepancy. A mismatch is evidence of an integrity discrepancy and requires further investigation; it does not identify a culprit.
- Mirror Node indexing may be delayed or unavailable. Verification must be retried when pending or when an upstream/configuration error is reported.
- `php artisan insaf:demo-reset` is available only in local/testing with `DEMO_MODE=true`, prompts for confirmation unless `--force` is supplied, and scopes deletion to S001's application/reports and all placement commitments/results. Hedera messages cannot be deleted; old receipts remain on the topic.
- A saved receipt is immutable through the normal submission endpoint. Intentional resubmission with preserved versions would require a receipt-history design and database migration; that workflow is not currently implemented.
- HashScan topic and transaction links use the established `/testnet|mainnet/topic/<topic-id>` and `/testnet|mainnet/transaction/<transaction-id>` routes. When a transaction identifier is unavailable or invalid, the UI falls back to the topic page.
- Production deployments must use real secret management, correct administrator provisioning, backups, HTTPS, and `DEMO_MODE=false`.
