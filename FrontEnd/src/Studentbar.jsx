import "./Profile.css";

const tr = {
  fr: { student: "Étudiant", score: "Score d'orientation", logout: "Déconnexion", open: "Voir mon profil" },
  en: { student: "Student", score: "Orientation score", logout: "Logout", open: "View my profile" },
};

export default function StudentBar({ lang, profile, onProfile, onLogout }) {
  const t = tr[lang];

  return (
    <div className="ins-sbar">
      {/* Avatar: click to open the profile page */}
      <button className="ins-avatar" onClick={onProfile} title={t.open} type="button">
        <svg viewBox="0 0 64 64" aria-hidden="true">
          <circle cx="32" cy="23" r="12" />
          <path d="M8 60c0-13 10.7-22 24-22s24 9 24 22z" />
        </svg>
        <span className="ins-avatar-hint">{t.open}</span>
      </button>

      {/* French card */}
      <div className="ins-scard">
        <span className="ins-scard-label">{t.student}</span>
        <strong className="ins-scard-name">{profile?.name_fr ?? profile?.code ?? "-"}</strong>
        <span className="ins-scard-line">{profile?.institution_fr ?? "-"}</span>
        <span className="ins-scard-line ins-scard-score">{t.score} : {profile?.score ?? "-"}</span>
      </div>

      {/* Arabic card */}
      <div className="ins-scard ins-scard-ar" dir="rtl">
        <span className="ins-scard-label">الطالب</span>
        <strong className="ins-scard-name">{profile?.name_ar ?? "-"}</strong>
        <span className="ins-scard-line">{profile?.institution_ar ?? "-"}</span>
      </div>

      <button className="ins-btn ins-logout" onClick={onLogout} type="button">{t.logout}</button>
    </div>
  );
}