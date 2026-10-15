import { useEffect, useState } from "react";
import api from "./api";
import Layout, { Toggles } from "./Layout";
import StudentBar from "./StudentBar";
import ProfilePage from "./ProfilePage";
import "./App.css";

const translations = {
  fr: {
    brand: "Insaf",
    brandAr: " إنصاف",
    sub: "Vérifiez que votre place est bien la vôtre",
    synthetic: "Données synthétiques uniquement",
    student: "Étudiant",
    scoreLabel: "Score d'orientation",
    placementLabel: "Affectation publiée",
    receiptLabel: "Reçu (séquence Hedera)",
    pending: "En attente",

    s1: "1. L'étudiant classe ses vœux",
    s1Text: "Cliquez sur les filières par ordre de préférence (au moins 3). Un reçu est ancré sur Hedera avec l'heure exacte.",
    s1Label: "Vos vœux",
    submit: "Valider et obtenir le reçu",
    receipt: "REÇU",
    hash: "empreinte",
    tx: "transaction Hedera",
    seq: "séquence",

    s2: "2. Le ministère lance l'affectation",
    s2Text: "Les règles, la racine de Merkle de toutes les candidatures et les résultats sont ancrés sur Hedera.",
    run: "Engager, calculer et publier",
    anchored: "ancré (séquence",
    kinds: { rules: "règles", merkle_root: "racine de Merkle", results: "résultats" },
    thProgram: "Filière",
    thSeats: "Places",
    thAdmitted: "Admis",
    thCutoff: "Seuil",
    placedFor: "Affectation publiée :",
    logout: "Déconnexion",
    back: "← Précédent",

    s3: "3. Le pirate frappe",
    s3Text: "Quelqu'un modifie discrètement le dossier de l'étudiant dans la base, comme dans l'affaire du Kef en août 2025.",
    tamper: "Modifier mon dossier",

    s4: "4. L'étudiant vérifie",
    s4Text: "Le téléphone de l'étudiant compare son reçu à ce que Hedera a enregistré.",
    verify: "Vérifier mon affectation",
    valid: "Votre affectation est vérifiée",
    invalid: "Falsification détectée",
    checks: [
      "Mon reçu correspond à l'empreinte ancrée sur Hedera",
      "Le dossier de la plateforme correspond à mon reçu",
      "La base correspond à la racine de Merkle engagée avant le calcul",
      "Mon affectation est justifiée par mon score et les seuils publiés",
    ],

    busy: {
      apply: "Ancrage de votre reçu sur Hedera...",
      run: "Engagement des règles, des entrées et des résultats sur Hedera...",
      tamper: "Le pirate modifie la base de données...",
      verify: "Vérification auprès de Hedera...",
    },
  },
  en: {
    brand: "Insaf",
    brandAr: " إنصاف",
    sub: "Verify that your place is really yours",
    synthetic: "Synthetic data only",
    student: "Student",
    scoreLabel: "Orientation score",
    placementLabel: "Published placement",
    receiptLabel: "Receipt (Hedera sequence)",
    pending: "Pending",

    s1: "1. The student ranks their choices",
    s1Text: "Click programs in order of preference (at least 3). A receipt is anchored on Hedera with the exact time.",
    s1Label: "Your choices",
    submit: "Submit and get receipt",
    receipt: "RECEIPT",
    hash: "hash",
    tx: "Hedera transaction",
    seq: "sequence",

    s2: "2. The ministry runs the placement",
    s2Text: "The rules, the Merkle root of all applications and the results are anchored on Hedera.",
    run: "Commit, run and publish",
    anchored: "anchored (sequence",
    kinds: { rules: "rules", merkle_root: "Merkle root", results: "results" },
    thProgram: "Program",
    thSeats: "Seats",
    thAdmitted: "Admitted",
    thCutoff: "Cut-off",
    placedFor: "Published placement:",
    logout: "Logout",
    back: "← Back",

    s3: "3. The hacker strikes",
    s3Text: "Someone silently rewrites the student's record in the database, as in the Kef case of August 2025.",
    tamper: "Change my record",

    s4: "4. The student verifies",
    s4Text: "The student's phone compares their receipt with what Hedera recorded.",
    verify: "Verify my placement",
    valid: "Your placement is verified",
    invalid: "Tampering detected",
    checks: [
      "My receipt matches the hash anchored on Hedera",
      "The platform record matches my receipt",
      "The database matches the Merkle root committed before the run",
      "My placement is justified by my score and the published cut-offs",
    ],

    busy: {
      apply: "Anchoring your receipt on Hedera...",
      run: "Committing rules, inputs and results on Hedera...",
      tamper: "The hacker is editing the database...",
      verify: "Checking against Hedera...",
    },
  },
};

export default function Dashboard({ lang, setLang, dark, toggleTheme, session, onLogout }) {
  const code = session.student.code; // the logged-in student

  const [programs, setPrograms] = useState([]);
  const [student, setStudent] = useState(null);
  const [choices, setChoices] = useState([]);
  const [receipt, setReceipt] = useState(null);
  const [commitments, setCommitments] = useState([]);
  const [result, setResult] = useState(null);
  const [busy, setBusy] = useState("");
  const [page, setPage] = useState("home"); // home | profile
  const [profile, setProfile] = useState(null);

  const t = translations[lang];


  const refresh = async () => {
    setPrograms((await api.get("/programs")).data);
    setStudent((await api.get(`/students/${code}`)).data);
    setCommitments((await api.get("/commitments")).data);
  };
  useEffect(() => { refresh(); }, []);
  useEffect(() => {
    api.get("/profile").then((r) => setProfile(r.data)).catch(() => {});
  }, []);

  // key = "apply" | "run" | "tamper" | "verify", so the banner follows the language
  const act = async (key, fn) => {
    setBusy(key);
    try { await fn(); await refresh(); }
    catch (e) { alert(e.response?.data?.message || e.message); }
    setBusy("");
  };

  const toggle = (id) =>
    setChoices((c) => (c.includes(id) ? c.filter((x) => x !== id) : [...c, id]));

  const submit = () => act("apply", async () => {
    setResult(null);
    setReceipt((await api.post("/apply", { code, choices })).data);
  });
  const run = () => act("run", async () => { await api.post("/admin/run"); });
  const tamper = () => act("tamper", async () => { await api.post(`/admin/tamper/${code}`); });
  const verify = () => act("verify", async () => {
    setResult((await api.post("/verify", receipt)).data);
  });

  return (
    <Layout lang={lang} dark={dark}>
        {/* ── Branding ── */}
        <div className="ins-brand-header">
          <h1 className="ins-main-logo">{t.brand}<span>{t.brandAr}</span></h1>
          <p className="ins-sub-logo">{t.sub}</p>
        </div>

        {/* ── Utility bar ── */}
        <div className="ins-utility-bar">
          {page === "profile" ? (
            <button className="ins-back-link" onClick={() => setPage("home")} type="button">{t.back}</button>
          ) : busy ? (
            <div className="ins-busy"><span className="ins-spinner" />{t.busy[busy]}</div>
          ) : (
            <span className="ins-synthetic-tag">{t.synthetic}</span>
          )}
        </div>

        {/* ── Student bar: avatar opens the profile ── */}
        <StudentBar lang={lang} profile={profile} onProfile={() => setPage("profile")} onLogout={onLogout} />

        {page === "profile" ? (
          <ProfilePage lang={lang} dark={dark} toggleTheme={toggleTheme} profile={profile} onSaved={setProfile} />
        ) : (
        <div className="ins-content-card">
          {/* 1. Student */}
          <section className="ins-section">
            <h2 className="ins-section-title">{t.s1}</h2>
            <p className="ins-text">{t.s1Text}</p>
            <p className="ins-label">{t.s1Label}</p>
            <div className="ins-chips">
              {programs.map((p) => (
                <button
                  key={p.id}
                  className={"ins-chip" + (choices.includes(p.id) ? " selected" : "")}
                  onClick={() => toggle(p.id)}
                >
                  {choices.includes(p.id) ? `#${choices.indexOf(p.id) + 1} ` : ""}{p.name}
                </button>
              ))}
            </div>
            <button className="ins-btn" disabled={choices.length < 3 || !!busy} onClick={submit}>
              {t.submit}
            </button>
            {receipt && (
              <pre className="ins-receipt">
{`${t.receipt}
${t.hash}: ${receipt.hash}
${t.tx}: ${receipt.tx}
${t.seq}: ${receipt.sequence}`}
              </pre>
            )}
          </section>

          {/* 2. Ministry */}
          <section className="ins-section">
            <h2 className="ins-section-title">{t.s2}</h2>
            <p className="ins-text">{t.s2Text}</p>
            <button className="ins-btn" disabled={!!busy} onClick={run}>{t.run}</button>
            {commitments.map((c) => (
              <p key={c.id} className="ins-commit">
                &#10003; {t.kinds[c.kind] ?? c.kind} {t.anchored} {c.hcs_sequence})
              </p>
            ))}
            <table className="ins-table">
              <thead>
                <tr>
                  <th>{t.thProgram}</th>
                  <th className="c">{t.thSeats}</th>
                  <th className="c">{t.thAdmitted}</th>
                  <th className="c">{t.thCutoff}</th>
                </tr>
              </thead>
              <tbody>
                {programs.map((p) => (
                  <tr key={p.id}>
                    <td>{p.name}</td>
                    <td className="c">{p.capacity}</td>
                    <td className="c">{p.admitted}</td>
                    <td className="c">{p.cutoff_score ?? "-"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            <div className="ins-placed">{t.placedFor} <b>{student?.placed ?? "-"}</b></div>
          </section>

          {/* 3. Hacker */}
          <section className="ins-section">
            <h2 className="ins-section-title">{t.s3}</h2>
            <p className="ins-text">{t.s3Text}</p>
            <button className="ins-btn danger" disabled={!!busy} onClick={tamper}>{t.tamper}</button>
          </section>

          {/* 4. Verify */}
          <section className="ins-section">
            <h2 className="ins-section-title">{t.s4}</h2>
            <p className="ins-text">{t.s4Text}</p>
            <button className="ins-btn" disabled={!receipt || !!busy} onClick={verify}>{t.verify}</button>
            {result && (
              <div className={"ins-result " + (result.valid ? "ok" : "ko")}>
                <h2>{result.valid ? `✅ ${t.valid}` : `🚨 ${t.invalid}`}</h2>
                {result.checks.map((c, i) => (
                  <div key={i} className="ins-check">{c.ok ? "✅" : "❌"} {t.checks[i] ?? c.name}</div>
                ))}
              </div>
            )}
          </section>

          <Toggles lang={lang} dark={dark} onTheme={toggleTheme} />
        </div>
        )}
    </Layout>
  );
}