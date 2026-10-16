# INSaf — questions difficiles du jury

## 1. Pourquoi Hedera plutôt qu’une base normale ou un service d’horodatage?

Une base contrôlée par une seule organisation peut être modifiée par cette organisation. HCS ajoute un message ordonné et horodaté que l’application peut relire indépendamment via un Mirror Node. Ce n’est pas une preuve que les données sont vraies, ni une garantie contre toute attaque; c’est un point de comparaison vérifiable pour détecter une divergence. Un service d’horodatage pourrait aussi répondre à certains besoins; le choix d’Hedera correspond ici au défi et à un journal partagé.

## 2. Qui ancre les enregistrements du ministère et pourquoi l’adopterait-il?

Dans le prototype, l’application de démonstration ancre les reçus étudiants et les engagements de placement; le ministère n’ancre pas encore ses propres enregistrements. Pour une portée institutionnelle réelle, le ministère ou un opérateur explicitement mandaté devrait publier les règles et résultats officiels. Il pourrait le faire pour améliorer l’auditabilité et la possibilité pour les étudiants de vérifier leur reçu, mais cela exige gouvernance, budget, responsabilités opérationnelles et accord institutionnel. L’adoption n’est pas acquise.

## 3. Qu’est-ce qui empêche un pirate de modifier aussi le reçu?

Le reçu de référence est déjà ancré dans HCS. Modifier la copie en base ne change pas le message on-chain; la vérification recalcule l’empreinte des préférences courantes et la compare à l’enregistrement HCS. Une modification du reçu local ne remplace pas le message original, mais la sécurité du compte, des clés d’ancrage et des systèmes reste importante. HCS ne rend pas l’application impossible à compromettre.

## 4. Que prouve un écart — et que ne prouve-t-il pas?

Il prouve que les valeurs comparées ne concordent pas avec l’engagement consulté, sous réserve de la validité du processus de vérification et des données de référence. Il ne prouve ni qui a causé l’écart, ni pourquoi, ni qu’un acte criminel a eu lieu. Il ne prouve pas que les choix initiaux étaient légitimes. Il faut une enquête et des éléments indépendants.

## 5. Que se passe-t-il si le Mirror Node est lent ou indisponible?

Un message pas encore indexé apparaît `pending`; on attend et on réessaie. Une erreur réseau ou de configuration apparaît `unavailable`, jamais `verified`. Le Mirror Node est une dépendance de lecture; il faut prévoir une reprise et ne pas confondre absence temporaire de résultat et intégrité confirmée.

## 6. Le placement est-il vérifié?

Seuls les engagements sont vérifiés contre Hedera et les données actuelles. Il n’y a pas de preuve d’inclusion Merkle par étudiant, et l’algorithme de placement n’est pas recalculé indépendamment par le vérificateur. L’engagement `results` est comparé au résumé stocké actuel; cela ne démontre pas, à lui seul, que chaque affectation découle correctement des règles.

## 7. Quel est le coût pour 76 000 reçus?

La documentation officielle Hedera consultée le 16 octobre 2026 liste un prix de base standard de **0,0008 USD** pour `ConsensusSubmitMessage`. À ce tarif de base, 76 000 messages valent **60,80 USD**. C’est un calcul de base, pas un devis complet: les signatures et caractéristiques de transaction peuvent ajouter des frais, et la grille change. Il faut utiliser le fee estimator officiel au moment du pilote. Source : [Hedera network fees](https://docs.hedera.com/networks/fees).

## 8. Comment gérez-vous la vie privée et le droit tunisien?

Le message HCS est une empreinte, pas la liste en clair des préférences. Toutefois, un hash ne rend pas automatiquement les données anonymes : les identifiants et le contexte peuvent permettre une réidentification, et les données de l’application restent personnelles. Avant tout pilote, il faut déterminer les obligations applicables, la base légale, l’information et le consentement lorsque requis, la minimisation, la durée de conservation et les droits d’accès/rectification. Nous n’avons pas réalisé d’avis juridique ni consulté l’INPDP; l’autorité compétente et le cadre précis doivent être confirmés avec des juristes tunisiens.

## 9. Pourquoi présenter cela sous le thème DID?

Nous le présentons prudemment comme « un enregistrement étudiant vérifiable sur Hedera », dans la famille des dossiers numériques vérifiables. INSaf ne crée pas de DIDs, ne gère pas de clés d’identité par étudiant et n’émet pas de justificatifs vérifiables signés. Ce n’est pas un système DID complet. Des justificatifs W3C émis par le ministère sont une piste de feuille de route, pas une fonction actuelle.

## 10. Comment cela passerait-il à 76 000 étudiants?

À ce volume, il faudrait tester le débit et les frais réels, la taille des messages, le parallélisme et la reprise des transactions, l’indexation Mirror Node, les limites API, le support utilisateur et l’exploitation de la base. Les vérifications ne doivent pas toutes se déclencher simultanément sans contrôle de charge. Le prototype ne démontre pas encore un essai de charge à 76 000 utilisateurs; un pilote progressif et des tests de capacité seraient nécessaires.

## 11. Quel est le modèle économique et qui paie?

Ce n’est pas encore défini. Options à évaluer avec les institutions : financement public ou programme pilote, contrat de service opéré, ou intégration dans une plateforme nationale. Il faut décider qui paie les frais HBAR, qui gère les clés, qui répond aux incidents et qui assure la maintenance. Nous ne prétendons pas avoir déjà un client ou un revenu.

## 12. Que construiriez-vous ensuite?

D’abord un pilote gouverné avec l’UNIVGB et le ministère, une analyse juridique et de protection des données, ainsi que des essais de charge et de reprise. Ensuite, faire ancrer les règles et résultats par le ministère, puis évaluer une relecture indépendante du placement et des preuves d’inclusion individuelles. À plus long terme seulement, étudier des justificatifs vérifiables W3C émis par l’autorité compétente. Les prochaines étapes dépendent des résultats et de l’accord institutionnel.

---

# INSaf — hard questions from the judges

## 1. Why Hedera instead of a normal database or timestamping service?

A database controlled by one organization can be changed by that organization. HCS adds an ordered, timestamped message that the application can independently retrieve through a Mirror Node. This does not prove the data is true or guarantee protection from every attack; it provides a verifiable comparison point for detecting discrepancies. A timestamping service could meet some needs too; Hedera is used here because it fits the challenge and provides a shared record.

## 2. Who anchors the ministry’s records, and why would the ministry adopt this?

In the prototype, the demo application anchors student receipts and placement commitments; the ministry does not yet anchor its own records. For institutional force, the ministry or an explicitly authorized operator would need to publish official rules and results. It might do so to improve auditability and let students verify receipts, but that requires governance, budget, operational responsibility, and institutional agreement. Adoption is not assured.

## 3. What stops a hacker from editing the receipt too?

The reference receipt has already been anchored in HCS. Editing the database copy does not change the on-chain message; verification recalculates the fingerprint from current preferences and compares it with HCS. Editing a local receipt does not replace the original message, but account security, anchoring keys, and application systems still matter. HCS does not make the application impossible to compromise.

## 4. What does a mismatch prove—and not prove?

It shows that the compared values do not agree with the retrieved commitment, assuming the verification process and reference data are valid. It does not prove who caused the mismatch, why it happened, or that a crime occurred. It does not prove that the original choices were legitimate. Investigation and independent evidence are required.

## 5. What if the Mirror Node is slow or unavailable?

A record not indexed yet is `pending`; wait and retry. A network or configuration error is `unavailable`, never `verified`. Mirror Node access is a read dependency; recovery must be planned, and a temporary lack of results must not be confused with confirmed integrity.

## 6. Is placement verified?

Only the commitments are checked against Hedera and current data. There are no per-student Merkle inclusion proofs, and the verifier does not independently rerun the placement algorithm. The `results` commitment is compared with the current stored summary; by itself, that does not establish that every assignment correctly follows the rules.

## 7. What would 76,000 receipts cost?

Hedera’s official documentation checked on 16 October 2026 lists a standard base price of **US$0.0008** for `ConsensusSubmitMessage`. At that base rate, 76,000 messages total **US$60.80**. This is a base calculation, not a complete quote: signatures and transaction characteristics can add fees, and the fee schedule can change. Use the official fee estimator for a pilot estimate. Source: [Hedera network fees](https://docs.hedera.com/networks/fees).

## 8. How is privacy handled under Tunisian law?

The HCS message is a fingerprint, not the clear-text preference list. However, a hash does not automatically make data anonymous: identifiers and context can allow re-identification, and application records remain personal data. Before a pilot, determine applicable obligations, lawful basis, notice and consent where required, minimization, retention, and access/correction rights. No legal opinion or consultation with Tunisia’s INPDP has been completed; the competent authority and precise framework need confirmation with Tunisian counsel.

## 9. Why call this DID?

We present it cautiously as “a verifiable student record on Hedera,” within the broader area of verifiable digital records. INSaf does not create DIDs, manage per-student identity keys, or issue signed verifiable credentials. It is not a complete DID system. Ministry-issued W3C credentials are a roadmap idea, not a current feature.

## 10. How would it scale to 76,000 students?

At that volume we would need to test actual throughput and costs, message size, transaction parallelism and retries, Mirror Node indexing, API limits, user support, and database operations. Verification requests should not all be triggered at once without load controls. The prototype has not demonstrated a 76,000-user load test; phased piloting and capacity testing would be necessary.

## 11. What is the business model, and who pays?

It is not defined yet. Options to assess with institutions include public or pilot funding, an operated service contract, or integration into a national platform. The parties must decide who pays HBAR fees, manages keys, handles incidents, and maintains the service. We do not claim to have a customer or revenue today.

## 12. What would you build next?

First, a governed pilot with UNIVGB and the ministry, legal/privacy review, and load and recovery tests. Then have the ministry anchor official rules and results, and evaluate independent placement replay and per-student inclusion proofs. Only later should we explore W3C verifiable credentials issued by the competent authority. These steps depend on results and institutional agreement.
