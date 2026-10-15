import axios from "axios";

// One axios client for the whole app. It adds the login token to every request.
const api = axios.create({
  baseURL: "http://127.0.0.1:8000/api",
  headers: { Accept: "application/json" }, // makes Laravel answer in JSON (401, 422...)
});

api.interceptors.request.use((config) => {
  try {
    const s = JSON.parse(localStorage.getItem("insaf-session"));
    if (s?.token) config.headers.Authorization = `Bearer ${s.token}`;
  } catch {
    /* no session */
  }
  return config;
});

export default api;