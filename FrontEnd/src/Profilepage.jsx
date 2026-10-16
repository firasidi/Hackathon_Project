import { useEffect, useState } from "react";
import api from "./Api";
import { LanguageToggle, Toggles } from "./Layout";
import "./Login.css";
import "./Profile.css";

const tr = {
  fr: {
    officialTitle: "Informations officielles",
    officialText: "Fournies par le ministère. Pour les corriger, adressez-vous à votre établissement.",
    incorrectInfo: "Une information est incorrecte ?",
    reportPrompt: "Décrivez le problème et l’administration l’examinera.",
    reportCategory: "Information concernée",
    reportCategories: {
      name: "Nom et prénom",
      institution: "Établissement",
      score: "Score d’orientation",
      other: "Autre",
    },
    reportMessage: "Décrivez le problème",
    reportSubmit: "Envoyer à l’administration",
    reportSending: "Envoi...",
    reportSent: "Votre signalement a été envoyé à l’administration.",
    reportError: "Impossible d’envoyer le signalement. Réessayez.",
    nameFr: "Nom et prénom",
    nameAr: "الاسم واللقب",
    instFr: "Établissement",
    instAr: "المؤسسة",
    code: "Code étudiant (pseudonyme)",
    score: "Score d'orientation",
    locked: "Non modifiable",

    contactTitle: "Mes coordonnées",
    contactText: "Une modification de l’e-mail ou du téléphone doit être confirmée par un code envoyé à votre adresse actuelle.",
    email: "Adresse e-mail",
    emailHint: "Les codes de vérification sont envoyés à votre adresse actuellement enregistrée.",
    phone: "Téléphone (8 chiffres)",
    city: "Ville",
    verificationCode: "Code de vérification à 6 chiffres",
    verificationSent: "Un code a été envoyé à",
    verify: "Confirmer et enregistrer",
    resend: "Renvoyer un code",
    cancelVerification: "Annuler la vérification",
    save: "Enregistrer les modifications",
    cancel: "Annuler",
    saving: "Enregistrement...",
    sending: "Envoi du code...",
    confirming: "Vérification...",
    saved: "Vos coordonnées ont été vérifiées et mises à jour.",
    citySaved: "Votre ville a été mise à jour.",
    nothing: "Aucune modification à enregistrer.",

    privacyTitle: "Données protégées",
    privacyText: "Votre CIN et votre mot de passe ne sont jamais affichés sur cette page.",

    e: {
      badEmail: "Adresse e-mail invalide.",
      badPhone: "Le téléphone doit contenir exactement 8 chiffres.",
      currentEmailMissing: "Aucune adresse e-mail enregistrée n’est disponible pour confirmer le changement. Contactez l’administration.",
      emailDelivery: "Le code de vérification n’a pas pu être envoyé. Vos coordonnées n’ont pas changé.",
      emailDeliveryUnconfigured: "L’envoi d’e-mails n’est pas configuré. Vos coordonnées n’ont pas changé.",
      invalidCode: "Code invalide ou expiré. Vérifiez le code et réessayez.",
      emailUnavailable: "Cette adresse e-mail n’est plus disponible. Recommencez la demande.",
      requestExpired: "L’adresse enregistrée a changé depuis la demande. Recommencez.",
      verificationRequired: "Un code envoyé à votre adresse actuelle est requis pour modifier l’e-mail ou le téléphone.",
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
    incorrectInfo: "Is some information incorrect?",
    reportPrompt: "Describe the problem and the administration will review it.",
    reportCategory: "Information concerned",
    reportCategories: {
      name: "Full name",
      institution: "Institution",
      score: "Orientation score",
      other: "Other",
    },
    reportMessage: "Describe the problem",
    reportSubmit: "Send to administration",
    reportSending: "Sending...",
    reportSent: "Your report was sent to the administration.",
    reportError: "Could not send your report. Please try again.",
    nameFr: "Full name",
    nameAr: "الاسم واللقب",
    instFr: "Institution",
    instAr: "المؤسسة",
    code: "Student code (pseudonym)",
    score: "Orientation score",
    locked: "Not editable",

    contactTitle: "My contact details",
    contactText: "Email or phone changes must be confirmed with a code sent to your currently registered email address.",
    email: "Email address",
    emailHint: "Verification codes are sent to your currently registered address.",
    phone: "Phone (8 digits)",
    city: "City",
    verificationCode: "6-digit verification code",
    verificationSent: "A code was sent to",
    verify: "Verify and save",
    resend: "Send another code",
    cancelVerification: "Cancel verification",
    save: "Save changes",
    cancel: "Cancel",
    saving: "Saving...",
    sending: "Sending code...",
    confirming: "Verifying...",
    saved: "Your contact details were verified and updated.",
    citySaved: "Your city was updated.",
    nothing: "There is nothing to save.",

    privacyTitle: "Protected data",
    privacyText: "Your CIN and your password are never displayed on this page.",

    e: {
      badEmail: "Invalid email address.",
      badPhone: "The phone number must have exactly 8 digits.",
      currentEmailMissing: "There is no registered email address to confirm this change. Contact the administration.",
      emailDelivery: "The verification code could not be sent. Your contact details were not changed.",
      emailDeliveryUnconfigured: "Email delivery is not configured. Your contact details were not changed.",
      invalidCode: "The code is invalid or expired. Check it and try again.",
      emailUnavailable: "That email address is no longer available. Start the request again.",
      requestExpired: "The registered address changed after this request. Start again.",
      verificationRequired: "A code sent to your current email address is required to change email or phone.",
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

export default function ProfilePage({ lang, setLang, dark, toggleTheme, profile, onSaved }) {
  const t = tr[lang];

  const [email, setEmail] = useState(profile?.email ?? "");
  const [phone, setPhone] = useState(profile?.phone ?? "");
  const [city, setCity] = useState(profile?.city ?? "");
  const [verificationCode, setVerificationCode] = useState("");
  const [verificationPending, setVerificationPending] = useState(false);
  const [verificationEmail, setVerificationEmail] = useState("");
  const [error, setError] = useState("");
  const [ok, setOk] = useState("");
  const [saving, setSaving] = useState(false);
  const [reportOpen, setReportOpen] = useState(false);
  const [reportCategory, setReportCategory] = useState("other");
  const [reportMessage, setReportMessage] = useState("");
  const [reportBusy, setReportBusy] = useState(false);
  const [reportError, setReportError] = useState("");
  const [reportSuccess, setReportSuccess] = useState("");

  // If the profile arrives after the page opens, fill the form
  useEffect(() => {
    setEmail(profile?.email ?? "");
    setPhone(profile?.phone ?? "");
    setCity(profile?.city ?? "");
  }, [profile]);

  const emailChanged = email.trim().toLowerCase() !== (profile?.email ?? "").toLowerCase();
  const phoneChanged = phone !== (profile?.phone ?? "");
  const dirty = emailChanged || phoneChanged || city !== (profile?.city ?? "");

  const clearVerification = () => {
    setVerificationPending(false);
    setVerificationCode("");
    setVerificationEmail("");
  };

  const cancel = async () => {
    setError("");
    if (verificationPending) {
      try {
        await api.delete("/profile/contact-change");
        clearVerification();
      } catch (err) {
        setError(explain(err));
        return;
      }
    }
    setEmail(profile?.email ?? "");
    setPhone(profile?.phone ?? "");
    setCity(profile?.city ?? "");
    setError("");
    setOk("");
  };

  const explain = (err) => {
    if (!err.response) return t.e.network;
    const { status, data } = err.response;
    if (status === 429) return t.e.tooMany;
    if (data?.error === "current_email_unavailable") return t.e.currentEmailMissing;
    if (data?.error === "email_delivery_failed") return t.e.emailDelivery;
    if (data?.error === "email_delivery_unconfigured") return t.e.emailDeliveryUnconfigured;
    if (data?.error === "invalid_code") return t.e.invalidCode;
    if (data?.error === "email_unavailable") return t.e.emailUnavailable;
    if (data?.error === "request_expired") return t.e.requestExpired;
    if (data?.error === "verification_required") return t.e.verificationRequired;
    if (status === 422) {
      if (data?.errors?.email) return t.e.badEmail;
      if (data?.errors?.phone) return t.e.badPhone;
    }
    return t.e.generic;
  };

  const requestContactChange = async () => {
    const changes = {};
    if (emailChanged) changes.email = email.trim();
    if (phoneChanged) changes.phone = phone || null;
    if (city !== (profile?.city ?? "")) changes.city = city.trim() || null;

    const { data } = await api.post("/profile/contact-change", changes);
    setVerificationEmail(data.emailHint);
    setVerificationPending(true);
    setVerificationCode("");
    setOk(`${t.verificationSent} ${data.emailHint}.`);
  };

  const save = async (e) => {
    e.preventDefault();
    setError(""); setOk("");

    if (!dirty) return setOk(t.nothing);
    if (emailChanged && !/^\S+@\S+\.\S+$/.test(email.trim())) return setError(t.e.badEmail);
    if (phoneChanged && phone && !/^\d{8}$/.test(phone)) return setError(t.e.badPhone);

    setSaving(true);
    try {
      if (emailChanged || phoneChanged) {
        await requestContactChange();
        return;
      }
      const { data } = await api.put("/profile", {
        email: profile?.email ?? "",
        phone: profile?.phone ?? null,
        city: city.trim() || null,
      });
      onSaved(data);
      setOk(t.citySaved);
    } catch (err) {
      setError(explain(err));
    } finally {
      setSaving(false);
    }
  };

  const confirmChange = async (e) => {
    e.preventDefault();
    setError("");
    setOk("");
    if (!/^\d{6}$/.test(verificationCode)) return setError(t.e.invalidCode);

    setSaving(true);
    try {
      const { data } = await api.post("/profile/contact-change/confirm", {
        code: verificationCode,
      });
      onSaved(data);
      clearVerification();
      setEmail(data.email ?? "");
      setPhone(data.phone ?? "");
      setCity(data.city ?? "");
      setOk(t.saved);
    } catch (err) {
      setError(explain(err));
    } finally {
      setSaving(false);
    }
  };

  const submitProblemReport = async (event) => {
    event.preventDefault();
    setReportError("");
    setReportSuccess("");
    setReportBusy(true);
    try {
      const { data } = await api.post("/profile/problem-reports", {
        category: reportCategory,
        message: reportMessage.trim(),
      });
      setReportMessage("");
      setReportOpen(false);
      setReportSuccess(data.message || t.reportSent);
    } catch {
      setReportError(t.reportError);
    } finally {
      setReportBusy(false);
    }
  };

  return (
    <div className="ins-content-card">
      <div className="ins-card-head">
        <span>{t.title}</span>
        <div className="ins-profile-header-actions">
          <div className="ins-badge">{t.badge}</div>
          <LanguageToggle lang={lang} setLang={setLang} className="ins-profile-lang-toggle" />
        </div>
      </div>

      {/* Official information, read-only */}
      <section className="ins-section">
        <h2 className="ins-section-title">{t.officialTitle}</h2>
        <p className="ins-text">{t.officialText}</p>
        <button
          className="ins-btn ins-btn-ghost"
          type="button"
          onClick={() => {
            setReportOpen((open) => !open);
            setReportError("");
          }}
        >
          {t.incorrectInfo}
        </button>
        {reportSuccess && <div className="ins-msg ins-msg-ok" role="status">✓ {reportSuccess}</div>}
        {reportError && <div className="ins-msg ins-msg-error" role="alert">⚠ {reportError}</div>}
        {reportOpen && (
          <form className="ins-section" onSubmit={submitProblemReport}>
            <p className="ins-text">{t.reportPrompt}</p>
            <div className="ins-field">
              <label className="ins-flabel" htmlFor="pf-report-category">{t.reportCategory}</label>
              <select
                id="pf-report-category"
                className="ins-input"
                value={reportCategory}
                onChange={(event) => setReportCategory(event.target.value)}
              >
                {Object.entries(t.reportCategories).map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
            </div>
            <div className="ins-field">
              <label className="ins-flabel" htmlFor="pf-report-message">{t.reportMessage}</label>
              <textarea
                id="pf-report-message"
                className="ins-input"
                value={reportMessage}
                onChange={(event) => setReportMessage(event.target.value)}
                maxLength={5000}
                minLength={10}
                rows={4}
                required
              />
            </div>
            <button className="ins-btn" type="submit" disabled={reportBusy || reportMessage.trim().length < 10}>
              {reportBusy ? t.reportSending : t.reportSubmit}
            </button>
          </form>
        )}
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

        {verificationPending ? (
          <form onSubmit={confirmChange} noValidate>
            <p className="ins-text">{t.verificationSent} <strong>{verificationEmail}</strong>.</p>
            <div className="ins-field">
              <label className="ins-flabel" htmlFor="pf-verification-code">{t.verificationCode}</label>
              <input
                id="pf-verification-code"
                className="ins-input"
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={6}
                value={verificationCode}
                onChange={(e) => setVerificationCode(e.target.value.replace(/\D/g, "").slice(0, 6))}
              />
            </div>
            <div className="ins-actions">
              <button className="ins-btn" type="submit" disabled={saving || verificationCode.length !== 6}>
                {saving ? t.confirming : t.verify}
              </button>
              <button
                className="ins-btn ins-btn-ghost"
                type="button"
                onClick={async () => {
                  setError("");
                  setOk("");
                  setSaving(true);
                  try {
                    await requestContactChange();
                  } catch (err) {
                    setError(explain(err));
                  } finally {
                    setSaving(false);
                  }
                }}
                disabled={saving}
              >
                {t.resend}
              </button>
              <button className="ins-btn ins-btn-ghost" type="button" onClick={cancel} disabled={saving}>
                {t.cancelVerification}
              </button>
            </div>
          </form>
        ) : (
        <form onSubmit={save} noValidate>
          <div className="ins-field">
            <label className="ins-flabel" htmlFor="pf-email">{t.email}</label>
            <input id="pf-email" className="ins-input" type="email" autoComplete="email"
              value={email} onChange={(e) => setEmail(e.target.value)} />
            <p className="ins-field-hint">{t.emailHint}</p>
          </div>

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
        )}
      </section>

      <section className="ins-section">
        <h2 className="ins-section-title">{t.privacyTitle}</h2>
        <p className="ins-text">🔒 {t.privacyText}</p>
      </section>

      <Toggles lang={lang} setLang={setLang} dark={dark} onTheme={toggleTheme} showLanguage={false} />
    </div>
  );
}