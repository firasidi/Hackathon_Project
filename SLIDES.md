# INSaf — slides / diapositives

Use the French set or English set, not both in one delivery. Each slide has four or fewer short on-slide text lines; speaker notes are spoken, not displayed.

## Jeu français

### Diapositive 1 — Un choix doit pouvoir se prouver

- Kef, août 2025 : douze étudiants réaffectés selon la presse
- L’altération n’a été découverte qu’après les plaintes
- Le reçu étudiant peut établir la soumission initiale

**Visuel suggéré :** deux listes de vœux côte à côte, « soumis » et « dossier actuel », avec un point d’interrogation entre les deux; aucun nom ni donnée réelle.

**Notes orateur :** Présenter le cas comme rapporté par la presse et rappeler qu’un étudiant était soupçonné, sans identifier personne. Les étudiants ont ensuite retrouvé leurs choix initiaux. Sources citées dans le pitch : presse tunisienne, dont allAfrica, Directinfo, La Presse, Tunisie Numerique et Kapitalis.

### Diapositive 2 — INSaf : un reçu étudiant vérifiable sur Hedera

- Vœux ordonnés + sel + code → empreinte SHA-256
- HCS ancre `c:<empreinte>`
- Reçu : empreinte, topic, séquence
- Mirror Node : relire et comparer aux données actuelles

**Visuel suggéré :** reçu fictif imprimé, avec empreinte et topic/sequence clairement étiquetés « démo ».

**Notes orateur :** L’étudiant détient la preuve de référence, sans que les vœux en clair soient écrits dans le message HCS. Le backend relit l’enregistrement exact sur le Mirror Node et recalcule l’empreinte courante. HCS apporte un journal public ordonné et horodaté; il ne garantit pas que les données initiales étaient correctes.

### Diapositive 3 — Du dossier à l’enregistrement HCS

- Étudiant → React → API Laravel
- Laravel : choix ordonnés → SHA-256
- Node sender → Hedera Consensus Service
- Vérification : Mirror Node → comparaison à la base actuelle

**Visuel suggéré :** diagramme textuel: `Étudiant → React → Laravel/SHA-256 → Node sender → HCS topic`; retour: `HCS topic → Mirror Node → Laravel compare → résultat`.

**Notes orateur :** Le reçu individuel est ancré sous la forme `c:<hash>`. L’administrateur peut ancrer `rules`, `merkle_root` et `results`; l’application vérifie les messages HCS et recalcule les engagements depuis les données courantes. Il n’y a pas de nouveau service Hedera: seulement HCS et la lecture du Mirror Node.

### Diapositive 4 — Démo : vérifier, modifier, détecter

- Soumettre → reçu → vérification réussie
- Administrateur : lancer le placement, vérifier les engagements
- Simulation S001 : reçu conservé, données modifiées
- `rules` vérifié; `merkle_root` et `results` modifiés

**Visuel suggéré :** capture synthétique de l’écran avant/après avec les statuts; masquer les secrets et données personnelles.

**Notes orateur :** Décrire chaque résultat au moment où il apparaît. Après altération, la vérification du reçu doit signaler l’écart avec l’enregistrement HCS; la vérification des engagements doit garder `rules` vérifié et marquer la racine et les résultats comme modifiés. Générer ensuite le rapport d’incident; il documente une divergence et demande une enquête, sans attribution.

### Diapositive 5 — Une preuve de divergence, pas une preuve d’auteur

- Détecte une divergence; ne l’empêche pas et n’identifie personne
- Le ministère doit ancrer ses propres règles et résultats
- Données synthétiques; formule générique
- Pas de preuve de Merkle par étudiant ni de recalcul indépendant

**Visuel suggéré :** tableau « Ce que vérifie / Ce qui reste à faire » accompagné d’une icône d’indexation en attente.

**Notes orateur :** Les engagements vérifiés sont ceux du dernier calcul; les anciens sont neutres et affichés comme remplacés. Le système n’exécute pas à nouveau l’algorithme pour certifier chaque affectation. Mirror Node peut mettre quelques secondes à indexer un message.

### Diapositive 6 — Piloter avec l’UNIVGB et le ministère

- Pilote encadré, données minimisées et consentement explicite
- Gouvernance : définir qui ancre les données officielles
- Feuille de route : étudier des justificatifs vérifiables W3C émis par le ministère

**Visuel suggéré :** étudiant, UNIVGB et ministère autour d’un même processus de validation; les justificatifs W3C apparaissent uniquement dans la zone « futur ».

**Notes orateur :** INSaf n’est pas un système DID et n’émet pas de justificatif vérifiable. La prochaine étape proposée est une étude conjointe de gouvernance, de protection des données et de faisabilité pour un pilote. Ne pas promettre une adoption institutionnelle avant cet accord.

### Diapositive de secours — Séquence et résultats attendus

- Réinitialiser → étudiant soumet et vérifie
- Administrateur lance → trois engagements vérifiés
- Altérer S001 → vérifier à nouveau
- `rules` vérifié; racine/résultats modifiés; reçu en divergence

**Visuel suggéré :** frise en quatre étapes et légende compacte : `verified`, `data_changed`, `superseded`, `pending`, `unavailable`, `chain_mismatch`; `superseded` en gris neutre.

**Notes orateur :** `pending` signifie que le Mirror Node n’a pas encore indexé le message; attendre quelques secondes et réessayer. Ne jamais présenter `pending` ou `unavailable` comme une vérification réussie.

## English set

### Slide 1 — A student should be able to prove their choice

- Kef, August 2025: press reported twelve students reassigned
- The change was found only after complaints
- A student-held receipt can establish the original submission

**Suggested visual:** Two anonymized preference lists, “submitted” and “current record,” separated by a question mark; no names or real data.

**Speaker notes:** Describe the event as reported by the press and say a student was suspected, without naming or accusing anyone. The students were later reassigned to their original choices. Press outlets cited in the pitch include allAfrica, Directinfo, La Presse, Tunisie Numerique, and Kapitalis.

### Slide 2 — INSaf: a verifiable student record on Hedera

- Ranked choices + salt + student code → SHA-256 fingerprint
- HCS anchors `c:<fingerprint>`
- Receipt: fingerprint, topic, sequence
- Mirror Node: retrieve and compare with current data

**Suggested visual:** A fictional printed receipt with fingerprint and topic/sequence labeled “demo.”

**Speaker notes:** The student holds the reference evidence without writing clear-text preferences into the HCS message. The backend retrieves the exact Mirror Node record and recalculates the current fingerprint. HCS provides a public, ordered, timestamped record; it does not guarantee that the original data was correct.

### Slide 3 — From application record to HCS

- Student → React → Laravel API
- Laravel: ranked choices → SHA-256
- Node sender → Hedera Consensus Service
- Verification: Mirror Node → compare with current database

**Suggested visual:** Text diagram: `Student → React → Laravel/SHA-256 → Node sender → HCS topic`; return path: `HCS topic → Mirror Node → Laravel comparison → result`.

**Speaker notes:** The individual receipt is anchored as `c:<hash>`. An administrator can anchor `rules`, `merkle_root`, and `results`; the application checks the HCS messages and recomputes commitments from current data. No additional Hedera service is used: only HCS and Mirror Node reads.

### Slide 4 — Demo: verify, alter, detect

- Submit → receipt → successful verification
- Admin: run placement, verify commitments
- S001 simulation: receipt preserved, data changed
- `rules` verified; `merkle_root` and `results` changed

**Suggested visual:** Synthetic before/after dashboard capture with statuses; hide secrets and personal data.

**Speaker notes:** Narrate each result as it appears. After the controlled alteration, receipt verification should report a discrepancy against the HCS record; commitment verification should leave `rules` verified and mark the root and results as changed. Generate an incident report that documents a discrepancy and requests investigation without attribution.

### Slide 5 — Evidence of a discrepancy, not evidence of an actor

- Detects a discrepancy; does not prevent it or identify anyone
- Ministry must anchor its own rules and results
- Synthetic data; generic score formula
- No per-student Merkle proofs or independent rerun

**Suggested visual:** “What is checked / What remains” table with an indexing-pending indicator.

**Speaker notes:** The verified commitments represent the latest run; older entries are neutral and shown as superseded. The system does not rerun the algorithm to certify every placement. Mirror Node indexing may take a few seconds.

### Slide 6 — Pilot with UNIVGB and the ministry

- Supervised pilot, minimized data, explicit consent
- Governance: define who anchors official records
- Roadmap: explore ministry-issued W3C verifiable credentials

**Suggested visual:** Student, UNIVGB, and ministry around one validation process; W3C credentials appear only in a “future” area.

**Speaker notes:** INSaf is not a DID system and does not issue verifiable credentials. The proposed next step is a joint study of governance, data protection, and pilot feasibility. Do not promise institutional adoption before agreement.

### Backup slide — Sequence and expected results

- Reset → student submits and verifies
- Admin runs → three commitments verified
- Alter S001 → verify again
- `rules` verified; root/results changed; receipt discrepancy

**Suggested visual:** Four-step timeline and compact legend: `verified`, `data_changed`, `superseded`, `pending`, `unavailable`, `chain_mismatch`; show `superseded` in neutral gray.

**Speaker notes:** `pending` means the Mirror Node has not indexed the record yet; wait a few seconds and retry. Never present `pending` or `unavailable` as successful verification.
