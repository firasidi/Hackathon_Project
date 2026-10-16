import { lazy, Suspense, useCallback, useEffect, useState } from "react";
import api from "./api";
import LoginPage from "./LoginPage";
import "./App.css";

const Dashboard = lazy(() => import("./Dashboard"));

const read = (key, fallback) => {
  try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; }
};

export default function App() {
  const [lang, setLangState] = useState(() => read("insaf-lang", "fr"));
  const [dark, setDark] = useState(() => localStorage.getItem("insaf-theme") === "dark");
  const [session, setSession] = useState(() => read("insaf-session", null));
  const [screen, setScreen] = useState(() => read("insaf-session", null) ? "dashboard" : "login");

  useEffect(() => {
    const initialScreen = read("insaf-session", null) ? "dashboard" : "login";
    window.history.replaceState({
      ...window.history.state,
      insafScreen: initialScreen,
      ...(initialScreen === "dashboard" ? { dashboardPage: "home" } : { loginView: "login" }),
    }, "", window.location.href);

    const handlePopState = (event) => {
      const nextScreen = event.state?.insafScreen;
      if (nextScreen === "dashboard" && !read("insaf-session", null)) {
        setScreen("login");
        return;
      }
      if (nextScreen === "dashboard" || nextScreen === "login") {
        setScreen(nextScreen);
      }
    };

    window.addEventListener("popstate", handlePopState);
    return () => window.removeEventListener("popstate", handlePopState);
  }, []);

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
    window.history.replaceState({
      ...window.history.state,
      insafScreen: "login",
      loginView: "login",
    }, "", window.location.href);
    window.history.pushState({
      ...window.history.state,
      insafScreen: "dashboard",
      dashboardPage: "home",
    }, "", window.location.href);
    setScreen("dashboard");
  };

  const onSessionExpired = useCallback(() => {
    localStorage.removeItem("insaf-session");
    setSession(null);
    window.history.replaceState({
      ...window.history.state,
      insafScreen: "login",
      loginView: "login",
    }, "", window.location.href);
    setScreen("login");
  }, []);

  const onLogout = async () => {
    try { await api.post("/logout"); } catch { /* token already invalid */ }
    onSessionExpired();
  };

  const shared = { lang, setLang, dark, toggleTheme };

  return (
    <Suspense fallback={
      <div className="ins-busy" role="status">
        <span className="ins-spinner" />
        {lang === "fr" ? "Chargement..." : "Loading..."}
      </div>
    }>
      {session && screen === "dashboard"
        ? <Dashboard {...shared} session={session} onLogout={onLogout} onSessionExpired={onSessionExpired} />
        : <LoginPage {...shared} onLogin={onLogin} />}
    </Suspense>
  );
}
