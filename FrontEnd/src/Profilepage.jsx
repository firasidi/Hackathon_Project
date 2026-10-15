import { useEffect, useState } from "react";
import api from "./api";
import { Toggles } from "./Layout";
import "./Login.css";
import "./Profile.css";

const tr = {
  fr: {
    officialTitle: "Informations officielles",
    officialText: "Fournies par le ministère. Pour les corriger, adressez-vous à votre établissement.",
    nameFr: "Nom et prénom",
    nameAr: "الاسم واللقب",
    instFr: "Établissement",
    instAr: "المؤسسة",
    code: "Code étudiant (pseudonyme)",
    score: "Score d'orientation",
    locked: "Non modifiable",

    contactTitle: "Mes coordonnées",
    contactText: "Vous pouvez modifier ces informations.",
    email: "Adresse e-mail",
    emailHint: "C'est à cette adresse que les codes de réinitialisation sont envoyés.",
    phone: "Téléphone (8 chiffres)",
    city: "Ville",
    currentPwd: "Mot de passe actuel",
    currentPwdHint: "Requis pour changer l'adresse e-mail, par sécurité.",
    save: "Enregistrer les modifications",
    cancel: "Annuler",
    saving: "Enregistrement...",
    saved: "Vos informations ont été mises à jour.",
    nothing: "Aucune modification à enregistrer.",

    privacyTitle: "Données protégées",
    privacyText: "Votre CIN et votre mot de passe ne sont jamais affichés sur cette page.",

    e: {
      badEmail: "Adresse e-mail invalide.",
      badPhone: "Le téléphone doit contenir exactement 8 chiffres.",
      needPwd: "Entrez votre mot de passe actuel pour changer l'adresse e-mail.",
      wrongPwd: "Mot de passe actuel incorrect.",
      tooMany: "Trop de tentatives. Réessayez dans une minute.",
      network: "Impossible de joindre le serveur.",
      generic: "Une erreur est survenue. Réessayez.",
    },
    title: "Mon profil",
    badge: "Informations personnelles",
  },
  en: {
    officialTitle: "Official information",
    officialText: "Provided by the ministry. To correct it, contact your school.",
    nameFr: "Full name",
    nameAr: "الاسم واللقب",
    instFr: "Institution",
    instAr: "المؤسسة",
    code: "Student code (pseudonym)",
    score: "Orientation score",
    locked: "Not editable",

    contactTitle: "My contact details",
    contactText: "You can edit this information.",
    email: "Email address",
    emailHint: "Password reset codes are sent to this address.",
    phone: "Phone (8 digits)",
    city: "City",
    currentPwd: "Current password",
    currentPwdHint: "Required to change the email address, for security.",
    save: "Save changes",
    cancel: "Cancel",
    saving: "Saving...",
    saved: "Your information was updated.",
    nothing: "There is nothing to save.",

    privacyTitle: "Protected data",
    privacyText: "Your CIN and your password are never displayed on this page.",

    e: {
      badEmail: "Invalid email address.",
      badPhone: "The phone number must have exactly 8 digits.",
      needPwd: "Enter your current password to change the email address.",
      wrongPwd: "Current password is incorrect.",
      tooMany: "Too many attempts. Try again in a minute.",
      network: "Cannot reach the server.",
      generic: "Something went wrong. Try again.",
    },
    title: "My profile",
    badge: "Personal information",
  },
};

function ReadOnly({ label, value, rtl, locked }) {
  return (
    <div className="ins-ro" dir={rtl ? "rtl" : undefined}>
      <span className="ins-ro-label">{label}</span>
      <span className="ins-ro-value">{value || "-"}</span>
      <span className="ins-ro-lock" title={locked}>🔒</span>
    </div>
  );
}

export default function ProfilePage({ lang, dark, toggleTheme, profile, onSaved }) {
  const t = tr[lang];

  const [email, setEmail] = useState(profile?.email ?? "");
  const [phone, setPhone] = useState(profile?.phone ?? "");
  const [city, setCity] = useState(profile?.city ?? "");
  const [pwd, setPwd] = useState("");
  const [error, setError] = useState("");
  const [ok, setOk] = useState("");
  const [saving, setSaving] = useState(false);

  // If the profile arrives after the page opens, fill the form
  useEffect(() => {
    setEmail(profile?.email ?? "");
    setPhone(profile?.phone ?? "");
    setCity(profile?.city ?? "");
  }, [profile]);

  const emailChanged = email.trim().toLowerCase() !== (profile?.email ?? "").toLowerCase();
  const dirty = emailChanged || phone !== (profile?.phone ?? "") || city !== (profile?.city ?? "");

  const cancel = () => {
    setEmail(profile?.email ?? "");
    setPhone(profile?.phone ?? "");
    setCity(profile?.city ?? "");
    setPwd("");
    setError("");
    setOk("");
  };

  const explain = (err) => {
    if (!err.response) return t.e.network;
    const { status, data } = err.response;
    if (status === 429) return t.e.tooMany;
    if (data?.error === "wrong_password") return t.e.wrongPwd;
    if (status === 422) {
      if (data?.errors?.email) return t.e.badEmail;
      if (data?.errors?.phone) return t.e.badPhone;
    }
    return t.e.generic;
  };

  const save = async (e) => {
    e.preventDefault();
    setError(""); setOk("");

    if (!dirty) return setOk(t.nothing);
    if (!/^\S+@\S+\.\S+$/.test(email)) return setError(t.e.badEmail);
    if (phone && !/^\d{8}$/.test(phone)) return setError(t.e.badPhone);
    if (emailChanged && !pwd) return setError(t.e.needPwd);

    setSaving(true);
    try {
      const { data } = await api.put("/profile", {
        email: email.trim(),
        phone: phone || null,
        city: city.trim() || null,
        current_password: emailChanged ? pwd : null,
      });
      onSaved(data);
      setPwd("");
      setOk(t.saved);
    } catch (err) {
      setError(explain(err));
    }
    setSaving(false);
  };

  return (
    <div className="ins-content-card">
      <div className="ins-card-head">
        <span>{t.title}</span>
        <div className="ins-badge">{t.badge}</div>
      </div>

      {/* Official information, read-only */}
      <section className="ins-section">
        <h2 className="ins-section-title">{t.officialTitle}</h2>
        <p className="ins-text">{t.officialText}</p>
        <div className="ins-ro-grid">
          <ReadOnly label={t.nameFr} value={profile?.name_fr} locked={t.locked} />
          <ReadOnly label={t.nameAr} value={profile?.name_ar} rtl locked={t.locked} />
          <ReadOnly label={t.instFr} value={profile?.institution_fr} locked={t.locked} />
          <ReadOnly label={t.instAr} value={profile?.institution_ar} rtl locked={t.locked} />
          <ReadOnly label={t.code} value={profile?.code} locked={t.locked} />
          <ReadOnly label={t.score} value={profile?.score} locked={t.locked} />
        </div>
      </section>

      {/* Contact details, editable */}
      <section className="ins-section">
        <h2 className="ins-section-title">{t.contactTitle}</h2>
        <p className="ins-text">{t.contactText}</p>

        {error && <div className="ins-msg ins-msg-error">⚠ {error}</div>}
        {ok && !error && <div className="ins-msg ins-msg-ok">✓ {ok}</div>}

        <form onSubmit={save} noValidate>
          <div className="ins-field">
            <label className="ins-flabel" htmlFor="pf-email">{t.email}</label>
            <input id="pf-email" className="ins-input" type="email" autoComplete="email"
              value={email} onChange={(e) => setEmail(e.target.value)} />
            <p className="ins-field-hint">{t.emailHint}</p>
          </div>

          {emailChanged && (
            <div className="ins-field">
              <label className="ins-flabel" htmlFor="pf-pwd">{t.currentPwd}</label>
              <input id="pf-pwd" className="ins-input" type="password" autoComplete="current-password"
                value={pwd} onChange={(e) => setPwd(e.target.value)} />
              <p className="ins-field-hint">{t.currentPwdHint}</p>
            </div>
          )}

          <div className="ins-row2">
            <div className="ins-field">
              <label className="ins-flabel" htmlFor="pf-phone">{t.phone}</label>
              <input id="pf-phone" className="ins-input" inputMode="numeric" autoComplete="tel"
                value={phone} onChange={(e) => setPhone(e.target.value.replace(/\D/g, "").slice(0, 8))} />
            </div>
            <div className="ins-field">
              <label className="ins-flabel" htmlFor="pf-city">{t.city}</label>
              <input id="pf-city" className="ins-input" autoComplete="address-level2"
                value={city} onChange={(e) => setCity(e.target.value)} />
            </div>
          </div>

          <div className="ins-actions">
            <button className="ins-btn" type="submit" disabled={saving || !dirty}>
              {saving && <span className="ins-spinner" />}
              {saving ? t.saving : t.save}
            </button>
            <button className="ins-btn ins-btn-ghost" type="button" onClick={cancel} disabled={saving || !dirty}>
              {t.cancel}
            </button>
          </div>
        </form>
      </section>

      <section className="ins-section">
        <h2 className="ins-section-title">{t.privacyTitle}</h2>
        <p className="ins-text">🔒 {t.privacyText}</p>
      </section>

      <Toggles lang={lang} dark={dark} onTheme={toggleTheme} />
    </div>
  );
}