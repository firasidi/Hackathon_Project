# INSaf — differentiation and innovation note

Hash anchoring and verification are common in the Hedera Cross Campus Challenge. INSaf does not claim novelty for “putting a hash on HCS”; it connects a documented incident to a student-held receipt, investigation workflow, and placement-run checks.

## Differentiators

**A real public-interest problem.** Press reports in August 2025 described twelve Kef students assigned to unchosen programs despite qualifying averages. A student was suspected; complaints exposed the issue, and students regained their original choices. This motivates auditability, not an accusation. Verify citations against the project bibliography.

**A receipt the student can keep.** INSaf fingerprints ranked preferences with SHA-256 and anchors `c:<hash>` in HCS; the message contains no clear-text choices. The receipt stores topic and sequence. Verification reads the record through a Mirror Node and compares current application data.

**A path from discrepancy to review.** The application can submit an incident report after a discrepancy. The backend checks it against the receipt, current application, and original HCS record. An administrator can download it, add notes, and update status. It requests investigation, not attribution.

**Commitments cover a placement run.** An administrator anchors rules/capacities, the preference Merkle root, and results. Verification reads the latest messages from the Mirror Node and recomputes them from current data. Older runs are neutral `superseded` entries.

## Honest boundary

INSaf is not a complete DID system and issues no signed credentials. There are no per-student Merkle proofs or independent placement rerun. A matching results commitment means the HCS message, saved commitment, and summary agree; it does not prove each assignment followed the rules. HCS exposes discrepancies; it does not prevent changes or identify an actor. The ministry must anchor official records.

The demo uses synthetic data and a generic score formula. Deployment needs ministry ownership, legal/privacy review, governance, key management, load tests, support, and Mirror Node recovery planning.

## Why this is meaningful

The story is not simply “we used a ledger.” A student keeps and checks a receipt, then can report a discrepancy. An administrator checks current placement commitments. The prototype states where this evidence ends.

## Sources and preparation

- Kef incident: project materials identify allAfrica, Directinfo, La Presse, Tunisie Numerique, and Kapitalis. Do not identify or accuse anyone suspected.
- Scale: about 76,000 new baccalaureate holders competed for 78,000 places in 2025, reported by allAfrica in July 2025. This is not an INSaf load-test or adoption result.
- HCS fee: Hedera’s [network fees documentation](https://docs.hedera.com/networks/fees), checked 16 October 2026, lists a US$0.0008 standard base fee for `ConsensusSubmitMessage`. 76,000 messages at that base are US$60.80; actual costs can vary.

**One-sentence distinction:** INSaf combines a student-held, HCS-verifiable preference receipt with checked placement-run commitments and a validated incident-report workflow, while limiting its claims to discrepancy detection—not placement correctness or attribution.
