# INSaf — pitch du concours UNIVGB

## Version française — 3 minutes

### 0:00–0:30 — Le problème

En août 2025, la presse a rapporté que douze étudiants du Kef avaient été affectés à des filières non choisies malgré des moyennes suffisantes. Un étudiant a été soupçonné; huit mandats de dépôt ont été émis. Les plaintes ont révélé l’affaire et les étudiants ont retrouvé leurs choix. En 2025, environ 76 000 nouveaux bacheliers se disputaient 78 000 places. Comment un étudiant peut-il prouver sa soumission initiale?

### 0:30–1:00 — L’idée et le rôle d’Hedera

INSaf remet à chaque étudiant un reçu vérifiable. L’application calcule une empreinte SHA-256 des choix ordonnés, d’un sel et du code étudiant, puis l’ancre sur Hedera Consensus Service. Le reçu conserve le topic et la séquence. À la vérification, le serveur relit ce message sur le Mirror Node et compare l’empreinte recalculée des données actuelles. Hedera fournit un enregistrement horodaté et consultable.

### 1:00–2:15 — Démonstration en direct

Je soumets des préférences avec le compte étudiant. Voici le reçu : empreinte, topic et séquence. Je vérifie : le message HCS correspond aux préférences actuelles.

En administrateur, je lance le placement de démonstration. INSaf ancre les engagements des règles, de la racine de Merkle des préférences et du résumé des résultats. Le vérificateur relit ces messages sur le Mirror Node et les compare aux données actuelles : les trois engagements du dernier calcul sont vérifiés.

Je simule une altération contrôlée de S001. Les préférences en base changent, mais le reçu et sa séquence restent identiques. Après vérification, les règles restent vérifiées; la racine et les résultats signalent « données modifiées ». Le reçu révèle aussi un écart avec la soumission initiale. Je génère enfin le rapport d’incident : il compare les empreintes et demande une enquête.

### 2:15–2:45 — Limites honnêtes

INSaf détecte une divergence; il ne l’empêche pas et n’identifie pas son auteur. Le ministère doit ancrer ses propres règles et résultats pour donner une portée institutionnelle au système. La démo utilise des données synthétiques et une formule générique. Les engagements sont vérifiés, mais il n’y a ni preuve de Merkle individuelle ni recalcul indépendant du placement. L’indexation peut aussi tarder.

### 2:45–3:00 — Vision et demande

Nous demandons un pilote encadré avec l’UNIVGB et le ministère pour définir responsabilités, protection des données et enregistrements officiels. Ensuite, étudier des justificatifs vérifiables W3C émis par le ministère. Aujourd’hui, INSaf est un reçu étudiant vérifiable sur Hedera, pas un système DID ni de justificatifs vérifiables.

## English version — 3 minutes

### 0:00–0:30 — The problem

In August 2025, press reported that twelve students from Kef were assigned to programs they had not chosen despite qualifying averages. A student was suspected; eight detention orders were issued. Complaints exposed the issue, and the students were reassigned to their original choices. In 2025, about 76,000 new graduates competed for 78,000 places. How can a student prove their original submission?

### 0:30–1:00 — The idea and Hedera’s role

INSaf gives each student a verifiable receipt. The application calculates a SHA-256 fingerprint from ranked choices, a salt, and student code, then anchors it on Hedera Consensus Service. The receipt stores the topic and sequence. For verification, the server retrieves that message from a Mirror Node and compares it with a fingerprint recalculated from current data. Hedera provides a timestamped, publicly inspectable record.

### 1:00–2:15 — Live demonstration

I submit preferences with the demo student account. Here is the receipt: fingerprint, topic, and sequence. I verify it: the HCS message matches the current preferences.

As administrator, I run demo placement. INSaf anchors commitments for the rules, preferences’ Merkle root, and results summary. The verifier reads these messages from the Mirror Node and compares current data; all three latest commitments are verified.

I trigger a controlled alteration of synthetic student S001. The database preferences change; the receipt and Hedera sequence do not. On rechecking, rules stay verified while the root and results say “data changed.” Receipt verification also shows a discrepancy from the original submission. I generate an incident report comparing fingerprints and requesting investigation.

### 2:15–2:45 — Honest limitations

INSaf detects a discrepancy; it does not prevent changes or identify who made one. The ministry must anchor its own rules and results for institutional force. This demo uses synthetic data and a generic score formula. Commitments are verified, but there are no per-student Merkle proofs or independent placement rerun. Mirror Node indexing can lag.

### 2:45–3:00 — Vision and ask

We ask UNIVGB and the ministry for a supervised pilot to define responsibility, data protection, and official records to anchor. Next, explore ministry-issued W3C verifiable credentials. Today, INSaf is a verifiable student record on Hedera, not a DID or verifiable-credentials system.

## Version française — 90 secondes

### 0:00–0:20 — Problème

En août 2025, la presse a rapporté que douze étudiants du Kef avaient été affectés à des filières non choisies malgré leurs moyennes. Un étudiant a été soupçonné; huit mandats de dépôt ont été émis. Les plaintes ont révélé l’affaire; les étudiants ont retrouvé leurs choix. Comment prouver la soumission initiale?

### 0:20–0:40 — Hedera

INSaf calcule l’empreinte SHA-256 des vœux, d’un sel et du code étudiant, puis l’ancre sur Hedera Consensus Service. Le reçu conserve topic et séquence. Le serveur relit ce message sur le Mirror Node et le compare aux données actuelles.

### 0:40–1:10 — Démo

Je soumets les vœux et vérifie le reçu contre Hedera. En administrateur, je lance le placement et vérifie règles, racine et résultats. Je simule une modification de S001. Le reçu reste intact; les vœux changent. Les règles restent vérifiées, la racine et les résultats signalent « données modifiées ». Le rapport compare les empreintes et demande une enquête.

### 1:10–1:30 — Limites et demande

INSaf détecte; il n’empêche pas et n’identifie pas l’auteur. Le ministère doit ancrer ses données. La démo est synthétique; sans preuve de Merkle individuelle ni recalcul indépendant du placement. L’indexation peut tarder. Nous demandons un pilote avec l’UNIVGB et le ministère.

## English version — 90 seconds

### 0:00–0:20 — Problem

In August 2025, press reported that twelve Kef students were assigned to unchosen programs despite their averages. A student was suspected; eight detention orders were issued. Complaints exposed the issue, and students got their original choices back. How can they prove their submission?

### 0:20–0:40 — Hedera

INSaf fingerprints ranked preferences, a salt, and student code with SHA-256, then anchors it on Hedera Consensus Service. The receipt keeps topic and sequence. The server retrieves the message from a Mirror Node and compares it with current data.

### 0:40–1:10 — Demo

I submit preferences and verify the receipt against Hedera. As administrator, I run placement and verify rules, root, and results. I simulate a change to S001. The receipt stays intact; preferences change. Rules remain verified; root and results say “data changed.” The report compares fingerprints and requests investigation.

### 1:10–1:30 — Limits and ask

INSaf detects; it does not prevent changes or identify an actor. The ministry must anchor its records. The demo is synthetic, with no per-student Merkle proofs or independent placement rerun. Indexing can lag. We ask UNIVGB and the ministry for a supervised pilot.

## Sources and speaking assumptions

- The Kef incident details above are attributed to Tunisian press coverage named in the project materials: allAfrica, Directinfo, La Presse, Tunisie Numerique, and Kapitalis. The project proposal PDF containing its article bibliography was not present in the repository, so article-level URLs were not independently checked here.
- The 2025 scale figure is about 76,000 new baccalaureate holders for about 78,000 places, as reported by allAfrica in July 2025.
- Hedera's official [network fees documentation](https://docs.hedera.com/networks/fees), checked 16 October 2026, lists a standard base fee of US$0.0008 for `ConsensusSubmitMessage`. At that base fee, 76,000 messages equal US$60.80. This is not a guaranteed all-in estimate: transaction characteristics and fee changes can affect actual charges. Check the official fee estimator before budgeting.
- Timings are target delivery times, not a word-for-word stopwatch guarantee. The French and English versions are written for approximately 120–140 spoken words per minute, with a deliberate pause for the live demonstration. Rehearse with the actual interface and shorten on stage if needed.

## Checklist — prepare before the event

- [ ] Record and test a backup video of the complete demo.
- [ ] Prepare the demo devices, chargers, display adapter, and a reliable network connection.
- [ ] Confirm the HCS topic and testnet account are ready and funded with enough test HBAR for rehearsal; never put or show any private key.
- [ ] Prepare a second browser profile for the student and administrator accounts.
- [ ] Print a copy of [DEMO_SCRIPT.md](./DEMO_SCRIPT.md).
