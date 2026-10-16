# INSaf jury demo

## Prerequisites

- PHP 8.2+, Composer, Node.js/npm, and the project dependencies installed.
- The intended local demo database selected and backed up before applying migrations or resetting demo data.
- Backend environment set to `APP_ENV=local` and `DEMO_MODE=true`.
- A synthetic student account for `S001` and an administrator account with administrator authorization.
- Hedera testnet settings and an existing testnet HCS topic; sender account settings are configured only in ignored local environment files.
- Laravel and Vite running locally; do not enable demo mode in production.

## Rehearsal sequence

1. **Reset demo state.** From `BackEnd/`, run `php artisan insaf:demo-reset` and confirm the prompt, or explicitly use `php artisan insaf:demo-reset --force`. This clears S001's saved application and incident reports, all commitment rows, and placement/cutoff results. It does not delete HCS messages; previous receipts remain on the topic.
2. **Student login.** Sign in as the synthetic student `S001`. Expect the preference form and no saved receipt after reset.
3. **Submit.** Select the required preferences and submit once. Expect a receipt with the testnet topic and sequence. This sends a real HCS message and may incur network fees. Do not submit again: a saved receipt is protected and a second submission returns HTTP 409.
4. **Verify receipt.** Open the receipt and choose **Verify my preferences**. Expect the original receipt to match the HCS record and current saved preferences. Use the raw Mirror Node link to open the exact message used for verification.
5. **Admin run.** Sign out and sign in with the administrator account. Choose **Run placement and anchor commitments**. Expect the rules, `merkle_root`, and results commitments to be recorded.
6. **Verify commitments.** Choose **Verify commitments**. After records are indexed, expect all three latest commitment statuses to be `verified`. Older records, if any remain, appear as `superseded`.
7. **Tamper demo.** Choose **Hack / Simulate Tampering**. Expect a before/after preference display. The existing receipt, topic, and sequence remain unchanged.
8. **Verify after tampering.** The dashboard runs verification again. Expect `rules` to remain `verified`; `merkle_root` and `results` to show `data_changed`. Receipt verification should confirm the original message on Hedera but report that current preferences differ. This detects a discrepancy; it does not prevent alteration or identify who made it.
9. **Incident PDF.** Choose **Generate Incident Report PDF**. Review the original/current hashes, Hedera reference, detection time, and pending-investigation language. Submit the report if desired; the backend revalidates it against the current record and original HCS record.

## Mirror Node indexing

The Mirror Node can take a few seconds to index a newly submitted message. A `pending` status is normal during this interval and is not a verification failure or success. Wait a few seconds and retry verification. The UI's **Raw record (Mirror Node)** link points to the exact topic/sequence endpoint.

## Troubleshooting

| Symptom | Meaning | Action |
| --- | --- | --- |
| `pending` | The Mirror Node has not indexed the message yet. | Wait a few seconds and retry. |
| `unavailable` | The Mirror Node, network configuration, or request could not be used. | Check local Hedera network/topic/Mirror Node configuration and connectivity; retry when restored. |
| HTTP `409` receipt exists | A receipt is already saved and cannot be overwritten. | For a new rehearsal, run the guarded demo reset; the previous HCS message remains on-chain. |
| HTTP `403` demo mode | The request is not authorized for demo actions, or the environment is not local/testing with demo mode enabled. | Confirm administrator authorization, `APP_ENV=local` (or `testing` for tests), and `DEMO_MODE=true`. Never enable demo mode in production. |
| HashScan transaction link is absent | The transaction identifier was not recognized as a supported format. | Use the topic link or the raw Mirror Node record link. |

All values in this guide are placeholders or synthetic demo identifiers. Never put account keys, passwords, tokens, or real student identifiers in the guide or source control.
