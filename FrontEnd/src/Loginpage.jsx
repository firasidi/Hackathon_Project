import { useState } from "react";
import api from "./api";
import Layout, { Toggles } from "./Layout";
import "./Login.css";

const tr = {
  fr: {
    brand: "Insaf",
    brandAr: " إنصاف",
    sub: "Vérifiez que votre place est bien la vôtre",

    loginTitle: "Connexion sécurisée",
    loginBadge: "CIN",
    hint: "Votre identifiant est votre CIN. Le mot de passe vous a été remis par le ministère.",
    cinLabel: "Numéro de CIN (identifiant)",
    cinPh: "8 chiffres",
    pwdLabel: "Mot de passe",
    pwdPh: "Mot de passe remis par le ministère",
    show: "Afficher",
    hide: "Masquer",
    loginBtn: "Se connecter",
    wait: "Veuillez patienter...",
    forgot: "Mot de passe oublié ?",
    note: "Démonstration : identifiants synthétiques.",

    forgotTitle: "Mot de passe oublié",
    forgotBadge: "Étape 1/2",
    forgotText: "Entrez votre CIN. Un code à 6 chiffres sera envoyé à l'adresse e-mail enregistrée auprès du ministère.",
    sendCode: "Envoyer le code",
    back: "← Retour à la connexion",

    resetTitle: "Nouveau mot de passe",
    resetBadge: "Étape 2/2",
    resetText: "Saisissez le code reçu par e-mail (valable 10 minutes) et choisissez un nouveau mot de passe.",
    codeLabel: "Code à 6 chiffres",
    newPwd: "Nouveau mot de passe (8 caractères minimum)",
    confirmPwd: "Confirmer le mot de passe",
    resetBtn: "Changer le mot de passe",
    resend: "Renvoyer le code",

    codeSent: "Si ce CIN existe, un code a été envoyé à l'e-mail enregistré auprès du ministère.",
    pwdChanged: "Mot de passe modifié. Vous pouvez maintenant vous connecter.",

    e: {
      fill: "Veuillez remplir tous les champs.",
      badCin: "Le CIN doit contenir exactement 8 chiffres.",
      badCreds: "CIN ou mot de passe incorrect.",
      tooMany: "Trop de tentatives. Réessayez dans quelques instants.",
      badCode: "Code invalide ou expiré.",
      codeFormat: "Le code contient 6 chiffres.",
      pwdShort: "Le mot de passe doit contenir au moins 8 caractères.",
      pwdMismatch: "Les mots de passe ne correspondent pas.",
      network: "Impossible de joindre le serveur. Vérifiez que Laravel est démarré.",
      generic: "Une erreur est survenue. Réessayez.",
    },
  },
  en: {
    brand: "Insaf",
    brandAr: " إنصاف",
    sub: "Verify that your place is really yours",

    loginTitle: "Secure login",
    loginBadge: "CIN",
    hint: "Your username is your CIN. The password was given to you by the ministry.",
    cinLabel: "CIN number (username)",
    cinPh: "8 digits",
    pwdLabel: "Password",
    pwdPh: "Password given by the ministry",
    show: "Show",
    hide: "Hide",
    loginBtn: "Log in",
    wait: "Please wait...",
    forgot: "Forgot password?",
    note: "Demo: synthetic credentials.",

    forgotTitle: "Forgot password",
    forgotBadge: "Step 1/2",
    forgotText: "Enter your CIN. A 6-digit code will be sent to the email address registered with the ministry.",
    sendCode: "Send the code",
    back: "← Back to login",

    resetTitle: "New password",
    resetBadge: "Step 2/2",
    resetText: "Enter the code received by email (valid for 10 minutes) and choose a new password.",
    codeLabel: "6-digit code",
    newPwd: "New password (8 characters minimum)",
    confirmPwd: "Confirm password",
    resetBtn: "Change password",
    resend: "Send the code again",

    codeSent: "If this CIN exists, a code was sent to the email registered with the ministry.",
    pwdChanged: "Password changed. You can now log in.",

    e: {
      fill: "Please fill in all the fields.",
      badCin: "The CIN must contain exactly 8 digits.",
      badCreds: "Incorrect CIN or password.",
      tooMany: "Too many attempts. Try again in a moment.",
      badCode: "Invalid or expired code.",
      codeFormat: "The code has 6 digits.",
      pwdShort: "The password must have at least 8 characters.",
      pwdMismatch: "The passwords do not match.",
      network: "Cannot reach the server. Check that Laravel is running.",
      generic: "Something went wrong. Try again.",
    },
  },
};

export default function LoginPage({ lang, setLang, dark, toggleTheme, onLogin }) {
  const t = tr[lang];

  const [view, setView] = useState("login"); // login | forgot | reset
  const [cin, setCin] = useState("");
  const [password, setPassword] = useState("");
  const [showPwd, setShowPwd] = useState(false);
  const [code, setCode] = useState("");
  const [newPwd, setNewPwd] = useState("");
  const [newPwd2, setNewPwd2] = useState("");
  const [error, setError] = useState("");
  const [info, setInfo] = useState("");
  const [loading, setLoading] = useState(false);

  const digits = (v, max) => v.replace(/\D/g, "").slice(0, max);

  const go = (v) => { setView(v); setError(""); };

  const explain = (err) => {
    if (!err.response) return t.e.network;
    const { status, data } = err.response;
    if (status === 429) return t.e.tooMany;
    if (data?.error === "invalid_credentials") return t.e.badCreds;
    if (data?.error === "invalid_code") return t.e.badCode;
    if (status === 422) return data?.errors?.password ? t.e.pwdShort : t.e.generic;
    return t.e.generic;
  };

  const run = async (fn) => {
    setError("");
    setLoading(true);
    try { await fn(); } catch (err) { setError(explain(err)); }
    setLoading(false);
  };

  const submitLogin = (e) => {
    e.preventDefault();
    setInfo("");
    if (!cin || !password) return setError(t.e.fill);
    if (cin.length !== 8) return setError(t.e.badCin);
    run(async () => {
      const { data } = await api.post("/login", { cin, password });
      onLogin(data);
    });
  };

  const submitForgot = (e) => {
    e.preventDefault();
    if (cin.length !== 8) return setError(t.e.badCin);
    run(async () => {
      await api.post("/forgot-password", { cin });
      setInfo(t.codeSent);
      setView("reset");
    });
  };

  const submitReset = (e) => {
    e.preventDefault();
    if (code.length !== 6) return setError(t.e.codeFormat);
    if (newPwd.length < 8) return setError(t.e.pwdShort);
    if (newPwd !== newPwd2) return setError(t.e.pwdMismatch);
    run(async () => {
      await api.post("/reset-password", {
        cin, code, password: newPwd, password_confirmation: newPwd2,
      });
      setCode(""); setNewPwd(""); setNewPwd2(""); setPassword("");
      setInfo(t.pwdChanged);
      setView("login");
    });
  };

  const title = view === "login" ? t.loginTitle : view === "forgot" ? t.forgotTitle : t.resetTitle;
  const badge = view === "login" ? t.loginBadge : view === "forgot" ? t.forgotBadge : t.resetBadge;

  return (
    <Layout lang={lang} dark={dark}>
      <div className="ins-brand-header">
        <h1 className="ins-main-logo">{t.brand}<span>{t.brandAr}</span></h1>
        <p className="ins-sub-logo">{t.sub}</p>
      </div>

      <div className="ins-content-card ins-login-card">
        <div className="ins-card-head">
          <span>{title}</span>
          <div className="ins-badge">{badge}</div>
        </div>

        <div className="ins-login-body">
          {error && <div className="ins-msg ins-msg-error">⚠ {error}</div>}
          {info && !error && <div className="ins-msg ins-msg-ok">✓ {info}</div>}

          {/* ── Login ── */}
          {view === "login" && (
            <form onSubmit={submitLogin} noValidate>
              <p className="ins-hint">{t.hint}</p>

              <div className="ins-field">
                <label className="ins-flabel" htmlFor="cin">{t.cinLabel}</label>
                <input id="cin" className="ins-input" inputMode="numeric" autoComplete="username"
                  placeholder={t.cinPh} value={cin} onChange={(e) => setCin(digits(e.target.value, 8))} />
              </div>

              <div className="ins-field">
                <label className="ins-flabel" htmlFor="pwd">{t.pwdLabel}</label>
                <div className="ins-pwd-row">
                  <input id="pwd" className="ins-input" type={showPwd ? "text" : "password"}
                    autoComplete="current-password" placeholder={t.pwdPh}
                    value={password} onChange={(e) => setPassword(e.target.value)} />
                  <button type="button" className="ins-eye" onClick={() => setShowPwd(!showPwd)}>
                    {showPwd ? t.hide : t.show}
                  </button>
                </div>
              </div>

              <button className="ins-btn ins-btn-full" type="submit" disabled={loading}>
                {loading && <span className="ins-spinner" />}
                {loading ? t.wait : t.loginBtn}
              </button>

              <div className="ins-links">
                <button type="button" className="ins-link" onClick={() => { setInfo(""); go("forgot"); }}>
                  {t.forgot}
                </button>
              </div>
              <p className="ins-note">{t.note}</p>
            </form>
          )}

          {/* ── Forgot: ask for the CIN ── */}
          {view === "forgot" && (
            <form onSubmit={submitForgot} noValidate>
              <p className="ins-hint">{t.forgotText}</p>

              <div className="ins-field">
                <label className="ins-flabel" htmlFor="cin2">{t.cinLabel}</label>
                <input id="cin2" className="ins-input" inputMode="numeric" placeholder={t.cinPh}
                  value={cin} onChange={(e) => setCin(digits(e.target.value, 8))} />
              </div>

              <button className="ins-btn ins-btn-full" type="submit" disabled={loading}>
                {loading && <span className="ins-spinner" />}
                {loading ? t.wait : t.sendCode}
              </button>

              <div className="ins-links">
                <button type="button" className="ins-link" onClick={() => go("login")}>{t.back}</button>
              </div>
            </form>
          )}

          {/* ── Reset: code + new password ── */}
          {view === "reset" && (
            <form onSubmit={submitReset} noValidate>
              <p className="ins-hint">{t.resetText}</p>

              <div className="ins-field">
                <label className="ins-flabel" htmlFor="code">{t.codeLabel}</label>
                <input id="code" className="ins-input ins-code" inputMode="numeric" autoComplete="one-time-code"
                  placeholder="------" value={code} onChange={(e) => setCode(digits(e.target.value, 6))} />
              </div>

              <div className="ins-field">
                <label className="ins-flabel" htmlFor="np">{t.newPwd}</label>
                <input id="np" className="ins-input" type="password" autoComplete="new-password"
                  value={newPwd} onChange={(e) => setNewPwd(e.target.value)} />
              </div>

              <div className="ins-field">
                <label className="ins-flabel" htmlFor="np2">{t.confirmPwd}</label>
                <input id="np2" className="ins-input" type="password" autoComplete="new-password"
                  value={newPwd2} onChange={(e) => setNewPwd2(e.target.value)} />
              </div>

              <button className="ins-btn ins-btn-full" type="submit" disabled={loading}>
                {loading && <span className="ins-spinner" />}
                {loading ? t.wait : t.resetBtn}
              </button>

              <div className="ins-links">
                <button type="button" className="ins-link" onClick={() => go("forgot")}>{t.resend}</button>
                <button type="button" className="ins-link" onClick={() => go("login")}>{t.back}</button>
              </div>
            </form>
          )}
        </div>

        <Toggles lang={lang} dark={dark} onTheme={toggleTheme} />
      </div>
    </Layout>
  );
}