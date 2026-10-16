import "./App.css";
import flagImg from "./assets/tunisia_flag.jpg";
import sunImg from "./assets/sun.png";
import moonImg from "./assets/moon.png";

const L = {
  fr: {
    gov: "République Tunisienne",
    min: "Ministère de l'Enseignement Supérieur",
    welcome: "Bienvenue sur Insaf",
    welcomeSub: "Plateforme d'orientation universitaire vérifiable",
    badge: "Hedera Testnet",
    switchLanguage: "Passer en anglais",
    footer: "Hedera Cross Campus Challenge — données synthétiques uniquement",
    themeTitle: "Changer le thème",
  },
  en: {
    gov: "Tunisian Republic",
    min: "Ministry of Higher Education",
    welcome: "Welcome to Insaf",
    welcomeSub: "Verifiable university placement platform",
    badge: "Hedera Testnet",
    switchLanguage: "Switch to French",
    footer: "Hedera Cross Campus Challenge — synthetic data only",
    themeTitle: "Switch theme",
  },
};

export function LanguageToggle({ lang, setLang, className = "" }) {
  const nextLanguage = lang === "fr" ? "en" : "fr";

  return (
    <button
      className={`ins-lang-toggle ${className}`.trim()}
      onClick={() => setLang(nextLanguage)}
      title={L[lang].switchLanguage}
      aria-label={L[lang].switchLanguage}
      type="button"
    >
      {nextLanguage.toUpperCase()}
    </button>
  );
}

export function Toggles({ lang, setLang, dark, onTheme, showLanguage = true }) {
  return (
    <>
      <button className="ins-theme-btn" onClick={onTheme} title={L[lang].themeTitle} type="button">
        <img
          src={dark ? sunImg : moonImg}
          alt={dark ? "Light mode" : "Dark mode"}
          className="ins-theme-icon"
        />
      </button>
      {showLanguage && <LanguageToggle lang={lang} setLang={setLang} />}
    </>
  );
}

// Header + footer shared by the login page and the dashboard
export default function Layout({ lang, dark, children }) {
  const t = L[lang];
  return (
    <div className={`ins-wrapper ${dark ? "ins-dark" : ""}`}>
      <header className="ins-header">
        <div className="ins-header-content">
          <div className="ins-gov-info">
            <img src={flagImg} alt="Drapeau Tunisie" className="ins-tn-flag" />
            <div className="ins-gov-text">
              <p className="ins-title-country">{t.gov}</p>
              <p className="ins-title-ministry">{t.min}</p>
            </div>
          </div>
          <div className="ins-welcome">
            <p className="ins-welcome-title">{t.welcome}</p>
            <p className="ins-welcome-sub">{t.welcomeSub}</p>
          </div>
          <button className="ins-btn-intranet" type="button" disabled>{t.badge}</button>
        </div>
      </header>

      <main className="ins-main">{children}</main>

      <footer className="ins-footer">
        <p><strong>Insaf</strong> — {t.footer}</p>
      </footer>
    </div>
  );
}