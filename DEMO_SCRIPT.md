# INSaf — jury demo script

Run this script with the synthetic demo account only. Keep `APP_ENV=local` and `DEMO_MODE=true`; never show private keys, passwords, personal student data, or real credentials on screen.

| # | Action / click | Exact words to say | Expected on-screen result |
|---|---|---|---|
| 1 | In the backend terminal, run `php artisan insaf:demo-reset`; confirm. | “I’m resetting only the synthetic demo record and placement state. Existing Hedera messages cannot be deleted.” | Command confirms demo reset and warns that previous HCS receipts remain on the topic. |
| 2 | Open the student browser profile and sign in as synthetic `S001`. | “This is the student view. The student begins with no saved receipt after the reset.” | Preference form appears; no existing receipt is shown. |
| 3 | Select at least three demo universities and submit once. | “The student submits ranked preferences. INSaf creates a fingerprint and anchors it to Hedera Consensus Service.” | Receipt displays fingerprint, topic, sequence, and links. Do not submit a second time. |
| 4 | Open saved receipt and click **Verify my preferences**. | “We retrieve the exact topic and sequence from the Mirror Node, then compare Hedera’s message with the current application record.” | Receipt is verified; both checks agree. |
| 5 | Sign out, switch to the administrator browser profile, and sign in. Click **Run placement and anchor commitments**. | “The administrator runs this demonstration placement and anchors commitments for rules, the preference root, and the results summary.” | Three HCS commitment messages are created. |
| 6 | Click **Verify commitments**. | “Now we read back each latest commitment from the Mirror Node and recompute it from the current data.” | Latest `rules`, `merkle_root`, and `results` show `verified`. If prior runs exist, they show neutral `superseded`. |
| 7 | Click **Hack / Simulate Tampering**. | “This controlled action changes only S001’s saved demo preferences and placement. It does not overwrite the student’s original Hedera receipt.” | Before/after preference lists appear; original topic and sequence remain displayed. |
| 8 | Review the automatically refreshed receipt and commitment results. | “The receipt still points to the original message. The rules are unchanged; the preference root and results now differ from the last commitments.” | `rules` is `verified`; `merkle_root` and `results` are `data_changed`. Receipt verification shows an integrity discrepancy, not a claim about who caused it. |
| 9 | Click **Generate Incident Report PDF**. | “The report records the original and current hashes, the Hedera reference, and the time of detection. It asks for investigation and does not accuse anyone.” | PDF downloads with mismatch details and pending-investigation language. |

## If Mirror Node shows `pending`

Say: “The transaction has been submitted, but the Mirror Node has not indexed it yet. Pending is not a success or a failure; I’ll wait briefly and retry.”

1. Wait about five seconds and retry verification once.
2. If still pending, open the **Raw record (Mirror Node)** link to show the exact endpoint and explain indexing lag; do not say the record was verified.
3. If the record is still missing after a second wait/retry (about 10–15 seconds total), or verification returns `unavailable`, say: “The live index is taking longer than our demo window. I won’t present that as a successful live check; I’ll switch to the backup recording of this same flow.”
4. Play the prepared backup video. Clearly label it as a recording, then resume from the current live app only if it is useful. Do not repeat submissions to try to force indexing; the saved receipt is protected and resubmission returns HTTP 409.

## Backup-video trigger and preparation

Switch to the backup video after two reasonable retries (roughly 10–15 seconds) or immediately on an `unavailable` result. Before the event, record the full expected flow through the final incident PDF, keep the recording accessible offline, and verify audio and screen readability. The video supplements the demo; do not claim its result is live.
