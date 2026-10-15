import { useEffect, useState } from "react";
import api from "./api";
import LoginPage from "./LoginPage";
import Dashboard from "./Dashboard";
import "./App.css";

const read = (key, fallback) => {
  try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; }
};

export default function App() {
  const [lang, setLangState] = useState(() => read("insaf-lang", "fr"));
  const [dark, setDark] = useState(() => localStorage.getItem("insaf-theme") === "dark");
  const [session, setSession] = useState(() => read("insaf-session", null));

  const setLang = (l) => {
    setLangState(l);
    try { localStorage.setItem("insaf-lang", JSON.stringify(l)); } catch { /* ignore */ }
  };

  const toggleTheme = () => {
    const next = !dark;
    setDark(next);
    try { localStorage.setItem("insaf-theme", next ? "dark" : "light"); } catch { /* ignore */ }
  };

  const onLogin = (data) => {
    const s = { token: data.token, student: data.student };
    localStorage.setItem("insaf-session", JSON.stringify(s));
    setSession(s);
  };

  const onLogout = async () => {
    try { await api.post("/logout"); } catch { /* token already invalid */ }
    localStorage.removeItem("insaf-session");
    setSession(null);
  };

  // If the saved token is no longer valid (for example after a password reset), go back to login
  useEffect(() => {
    if (!session) return;
    api.get("/me").catch((e) => {
      if (e.response?.status === 401) {
        localStorage.removeItem("insaf-session");
        setSession(null);
      }
    });
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const shared = { lang, setLang, dark, toggleTheme };

  return session
    ? <Dashboard {...shared} session={session} onLogout={onLogout} />
    : <LoginPage {...shared} onLogin={onLogin} />;
}
