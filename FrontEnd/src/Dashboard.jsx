import { useEffect, useState } from "react";
import api from "./api";
import Layout, { Toggles } from "./Layout";
import StudentBar from "./StudentBar";
import ProfilePage from "./ProfilePage";
import insatPhoto from "./assets/INSAT.jpg";
import isimsPhoto from "./assets/ISIMS-cover.webp";
import issatSoussePhoto from "./assets/issat sousse.jpg";
import isimmPhoto from "./assets/ISIMM.jpg";
import isimgPhoto from "./assets/isimg1.jpg";
import medicineLogo from "./assets/medecine.jpg";
import medicineTunisPhoto from "./assets/medicine_tunis.jpg";
import medicineSfaxPhoto from "./assets/medicine_sfax.jpg";
import medicineSoussePhoto from "./assets/medicine_sousse.jpg";
import medicineMonastirPhoto from "./assets/medicine_monastir.jpg";
import civilEngineeringTunisPhoto from "./assets/geniecivil_tunis.jpg";
import civilEngineeringSfaxPhoto from "./assets/geniecivilsfax.jpg";
import civilEngineeringSoussePhoto from "./assets/geniecivil_sousse.jpg";
import civilEngineeringMonastirPhoto from "./assets/geniecivil_monastir.jpg";
import civilEngineeringGabesPhoto from "./assets/geniecivil_gabes.jpg";
import pharmacyTunisPhoto from "./assets/pharmacy_tunis.jpg";
import pharmacySfaxPhoto from "./assets/pharmacy_sfax.jpg";
import pharmacySoussePhoto from "./assets/pharmacy_sousse.png";
import pharmacyMonastirPhoto from "./assets/pharmacy_monastir.jpg";
import pharmacyGabesPhoto from "./assets/pharmacy_gabes.jpg";
import architectureTunisPhoto from "./assets/architecture_tunis.png";
import architectureSfaxPhoto from "./assets/architecture_sfax.png";
import architectureSoussePhoto from "./assets/architecture_sousse.png";
import architectureMonastirPhoto from "./assets/architecture_monastir.png";
import architectureGabesPhoto from "./assets/architecture_gabes.png";
import lawTunisPhoto from "./assets/law_tunis.jpg";
import lawSfaxPhoto from "./assets/law_sfax.jpg";
import lawSoussePhoto from "./assets/law_sousse.jpg";
import lawMonastirPhoto from "./assets/law_monastir.jpg";
import lawGabesPhoto from "./assets/law_gabes.jpg";
import economicsTunisPhoto from "./assets/economics_tunis.jpg";
import economicsSfaxPhoto from "./assets/economics_sfax.jpg";
import economicsSoussePhoto from "./assets/economics_sousse.jpg";
import economicsMonastirPhoto from "./assets/economics_monastir.jpg";
import economicsGabesPhoto from "./assets/economics_gabes.jpg";
import literatureTunisPhoto from "./assets/literature_tunis.jpg";
import literatureSfaxPhoto from "./assets/literature_sfax.jpg";
import literatureSoussePhoto from "./assets/literature_sousse.jpg";
import literatureMonastirPhoto from "./assets/literature_monastir.jpg";
import literatureGabesPhoto from "./assets/literature_gabes.jpg";
import informatiqueLogo from "./assets/informatique.png";
import genieCivilLogo from "./assets/marteau.png";
import pharmacieLogo from "./assets/pharmacy.png";
import architectureLogo from "./assets/architecture.png";
import droitLogo from "./assets/droit.jpg";
import economieLogo from "./assets/economie.jpg";
import lettresLogo from "./assets/lettre.jpg";
import "./App.css";

const informaticsUniversityPhotos = [
  insatPhoto,
  isimsPhoto,
  issatSoussePhoto,
  isimmPhoto,
  isimgPhoto,
];

const medicineUniversityPhotos = [
  medicineTunisPhoto,
  medicineSfaxPhoto,
  medicineSoussePhoto,
  medicineMonastirPhoto,
];

const civilEngineeringUniversityPhotos = [
  civilEngineeringTunisPhoto,
  civilEngineeringSfaxPhoto,
  civilEngineeringSoussePhoto,
  civilEngineeringMonastirPhoto,
  civilEngineeringGabesPhoto,
];

const pharmacyUniversityPhotos = [
  pharmacyTunisPhoto,
  pharmacySfaxPhoto,
  pharmacySoussePhoto,
  pharmacyMonastirPhoto,
  pharmacyGabesPhoto,
];

const architectureUniversityPhotos = [
  architectureTunisPhoto,
  architectureSfaxPhoto,
  architectureSoussePhoto,
  architectureMonastirPhoto,
  architectureGabesPhoto,
];

const lawUniversityPhotos = [
  lawTunisPhoto,
  lawSfaxPhoto,
  lawSoussePhoto,
  lawMonastirPhoto,
  lawGabesPhoto,
];

const economicsUniversityPhotos = [
  economicsTunisPhoto,
  economicsSfaxPhoto,
  economicsSoussePhoto,
  economicsMonastirPhoto,
  economicsGabesPhoto,
];

const literatureUniversityPhotos = [
  literatureTunisPhoto,
  literatureSfaxPhoto,
  literatureSoussePhoto,
  literatureMonastirPhoto,
  literatureGabesPhoto,
];

const specialtyLogos = {
  medecine: medicineLogo,
  informatique: informatiqueLogo,
  "genie civil": genieCivilLogo,
  pharmacie: pharmacieLogo,
  architecture: architectureLogo,
  droit: droitLogo,
  economie: economieLogo,
  lettres: lettresLogo,
};

const specialtyNamesEn = {
  medecine: "Medicine",
  informatique: "Computer Science",
  "genie civil": "Civil Engineering",
  pharmacie: "Pharmacy",
  architecture: "Architecture",
  droit: "Law",
  economie: "Economics",
  lettres: "Literature",
};

const getSpecialtyKey = (name) => name
  ?.toLocaleLowerCase("fr")
  .normalize("NFD")
  .replace(/[\u0300-\u036f]/g, "");

const getSpecialtyName = (name, lang) => {
  if (lang !== "en") return name;
  return specialtyNamesEn[getSpecialtyKey(name)] ?? name;
};

const isRetiredUniversity = (specialty, university) =>
  getSpecialtyKey(specialty.name) === "medecine" &&
  getSpecialtyKey(university.location_fr).includes("gabes");

const downloadReceiptPdf = async (receipt, profile, universities, lang, labels) => {
  const { jsPDF } = await import("jspdf");
  const document = new jsPDF();
  const pageWidth = document.internal.pageSize.getWidth();
  const pageHeight = document.internal.pageSize.getHeight();
  const margin = 16;
  const contentWidth = pageWidth - margin * 2;
  let y = 20;

  const addWrapped = (text, fontSize = 10) => {
    document.setFontSize(fontSize);
    const lines = document.splitTextToSize(String(text || "-"), contentWidth);
    const height = lines.length * 5 + 3;
    if (y + height > pageHeight - margin) {
      document.addPage();
      y = margin;
    }
    document.text(lines, margin, y);
    y += height;
  };
  const addField = (label, value) => addWrapped(`${label}: ${value || "-"}`);

  document.setTextColor(11, 31, 58);
  document.setFont("helvetica", "bold");
  document.setFontSize(20);
  document.text(`INSAF — ${labels.receipt}`, margin, y);
  y += 9;
  document.setFont("helvetica", "normal");
  document.setFontSize(11);
  document.text(labels.receiptTitle, margin, y);
  y += 12;
  document.setDrawColor(140, 109, 63);
  document.setLineWidth(0.7);
  document.line(margin, y, pageWidth - margin, y);
  y += 10;

  const date = new Intl.DateTimeFormat(lang === "fr" ? "fr-FR" : "en-US", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(receipt.submittedAt));
  addField(labels.receiptStudent, profile?.name_fr ?? receipt.code);
  addField(labels.receiptCode, receipt.code);
  addField(labels.receiptScore, receipt.score);
  addField(labels.receiptDate, date);
  y += 3;
  document.setFont("helvetica", "bold");
  document.setFontSize(12);
  document.text(labels.receiptChoices, margin, y);
  y += 8;

  receipt.choices.forEach((choiceId, index) => {
    const university = universities.find((item) => item.id === choiceId);
    const name = university
      ? (lang === "en" ? university.name_en : university.name_fr)
      : `#${choiceId}`;
    addWrapped(`${index + 1}. ${name}${university ? ` — ${university.specialtyName}` : ""}`);
  });

  y += 3;
  addField(labels.hash, receipt.hash);
  addField(labels.tx, receipt.tx);
  addField(labels.seq, receipt.sequence);
  document.save(`receipt-${receipt.code}-${receipt.sequence}.pdf`);
};

const downloadIncidentReportPdf = async (incident, universities, lang) => {
  const { jsPDF } = await import("jspdf");
  const document = new jsPDF();
  const margin = 16;
  const pageWidth = document.internal.pageSize.getWidth();
  const pageHeight = document.internal.pageSize.getHeight();
  const contentWidth = pageWidth - margin * 2;
  let y = 20;

  const addText = (value, { bold = false, size = 10, gap = 3 } = {}) => {
    document.setFont("helvetica", bold ? "bold" : "normal");
    document.setFontSize(size);
    const lines = document.splitTextToSize(String(value ?? "Unavailable"), contentWidth);
    const height = lines.length * 5 + gap;
    if (y + height > pageHeight - margin) {
      document.addPage();
      y = margin;
    }
    document.text(lines, margin, y);
    y += height;
  };

  const describeChoices = (choiceIds) => choiceIds.length
    ? choiceIds.map((id, index) => {
      const university = universities.find((item) => item.id === id);
      const name = university
        ? (lang === "en" ? university.name_en : university.name_fr)
        : `University #${id}`;
      return `${index + 1}. ${name} (ID ${id})`;
    }).join("\n")
    : "No preferences recorded";

  const formatIds = (ids) => ids.length
    ? ids.map((id) => {
      const university = universities.find((item) => item.id === id);
      return university
        ? (lang === "en" ? university.name_en : university.name_fr)
        : `University #${id}`;
    }).join(", ")
    : "None";

  document.setTextColor(11, 31, 58);
  addText("INSAF — Incident Report", { bold: true, size: 18, gap: 2 });
  addText("Integrity discrepancy report. This report records a detected mismatch and does not attribute responsibility.", {
    size: 10,
    gap: 8,
  });
  document.setDrawColor(140, 109, 63);
  document.setLineWidth(0.7);
  document.line(margin, y, pageWidth - margin, y);
  y += 8;

  addText(`Incident ID: ${incident.id}`, { bold: true });
  addText(`Student/submission reference: ${incident.studentReference} / ${incident.submissionReference}`);
  addText("Original preferences (from the saved original receipt):");
  addText(describeChoices(incident.originalChoices));
  addText(`Original SHA-256 hash (${incident.originalHashSource}): ${incident.originalHash}`);
  addText("Current preferences (from the database):");
  addText(describeChoices(incident.currentChoices));
  addText(`Recalculated current SHA-256 hash: ${incident.currentHash}`);

  addText("Detected differences:", { bold: true, gap: 2 });
  const differences = incident.differences;
  addText(`Changed ranks: ${differences.byRank.length
    ? differences.byRank.map((item) =>
      `Rank ${item.rank}: ${formatIds(item.original === null ? [] : [item.original])} → ${formatIds(item.current === null ? [] : [item.current])}`
    ).join("; ")
    : "None"}`);
  addText(`Removed preferences: ${formatIds(differences.removed)}`);
  addText(`Added preferences: ${formatIds(differences.added)}`);

  addText("Hedera record:", { bold: true, gap: 2 });
  addText(`Topic ID: ${incident.hedera.topicId ?? "Unavailable"}`);
  addText(`Sequence number: ${incident.hedera.sequence ?? "Unavailable"}`);
  addText(`Consensus timestamp: ${incident.hedera.consensusTimestamp ?? "Unavailable"}`);
  addText(`Record reference: ${incident.hedera.recordReference ?? "Unavailable"}`);
  addText(`Verification timestamp: ${incident.verificationTimestamp}`);
  addText(`Status: ${incident.status}`);
  addText(`Original receipt reference — transaction ID: ${incident.originalReceiptReference.transactionId ?? "Unavailable"}; sequence: ${incident.originalReceiptReference.sequence ?? "Unavailable"}`);

  const pdf = document.output("blob");
  document.save(`incident-${incident.id}.pdf`);
  return pdf;
};

const translations = {
  fr: {
    brand: "Insaf",
    brandAr: " إنصاف",
    sub: "Vérifiez que votre place est bien la vôtre",
    student: "Étudiant",
    scoreLabel: "Score d'orientation",
    placementLabel: "Affectation publiée",
    receiptLabel: "Reçu (séquence Hedera)",
    pending: "En attente",

    s1: "1. L'étudiant classe ses vœux",
    s1Text: "Ouvrez une spécialité pour découvrir les universités qui la proposent. Sélectionnez et classez au moins 3 universités par ordre de préférence.",
    s1Label: "Vos vœux",
    selectionTitle: "Universités pour",
    selectionText: "Sélectionnez et classez au moins 3 universités. Ces fiches contiennent des informations de démonstration.",
    aboutPlace: "À propos du lieu",
    aboutUniversity: "À propos",
    location: "Localisation",
    minimumScore: "Score minimum indicatif",
    availablePlaces: "Places disponibles",
    indicativeNote: "Seuils indicatifs de démonstration. Le seuil réel dépend des places et des résultats publiés.",
    select: "Sélectionner",
    remove: "Retirer",
    selectedCount: "Universités sélectionnées",
    unavailableUniversity: "Cette université n'est plus disponible pour les nouveaux choix.",
    minimumChoices: "Sélectionnez au moins 3 universités.",
    viewUniversities: "Voir les universités",
    imageComing: "Photo de l’université à ajouter",
    universityPhotoAlt: "Emplacement réservé à la photo de l’université",
    minimumSelection: "Choisissez au moins 3 universités pour valider vos vœux.",
    submit: "Valider et obtenir le reçu",
    receipt: "REÇU",
    receiptTitle: "Confirmation de vos vœux universitaires",
    receiptStatus: "Vœux enregistrés et empreinte ancrée",
    receiptStudent: "Étudiant",
    receiptCode: "Code étudiant",
    receiptScore: "Score d'orientation",
    receiptDate: "Date de soumission",
    receiptChoices: "Vos vœux classés",
    printReceipt: "Imprimer le reçu",
    savedReceipt: "Votre reçu est enregistré. Vous pouvez le rouvrir ou télécharger le PDF.",
    viewReceipt: "Consulter le reçu",
    downloadReceipt: "Télécharger le PDF",
    university: "Université",
    specialty: "Spécialité",
    preferenceRank: "Rang",
    requiredScore: "Score minimum indicatif",
    studentScore: "Votre score",
    scoreDifference: "Écart",
    noChoicesTable: "Sélectionnez des universités pour voir la comparaison de vos scores.",
    hash: "empreinte",
    tx: "transaction Hedera",
    seq: "séquence",

    s3: "2. Le pirate frappe",
    s4: "3. L'étudiant vérifie",
    placedFor: "Affectation publiée :",
    placementUnavailable: "Pas encore disponible",
    logout: "Déconnexion",
    back: "← Précédent",

    s3Text: "Quelqu'un modifie discrètement le dossier de l'étudiant dans la base, comme dans l'affaire du Kef en août 2025.",
    tamper: "Modifier mon dossier",

    s4Text: "Le téléphone de l'étudiant compare son reçu à ce que Hedera a enregistré.",
    verify: "Vérifier mes vœux",
    integrityMismatch: "Integrity check failed. Your current preferences do not match the original submission recorded on Hedera.",
    generateIncidentPdf: "Generate Incident Report PDF",
    submitIncidentReport: "Submit Report to Administrator",
    incidentSubmitted: "Incident report submitted for administrator review.",
    adminIncidents: "Administrator incidents",
    incidentReports: "Incident reports",
    selectIncident: "Select a report to inspect its hashes, status, and PDF.",
    downloadIncidentPdf: "Download incident PDF",
    updateIncidentStatus: "Update status",
    valid: "Vos vœux sont vérifiés",
    invalid: "Falsification détectée",
    checks: [
      "Mon reçu correspond à l'empreinte ancrée sur Hedera",
      "Le dossier de la plateforme correspond à mon reçu",
      "La base correspond à la racine de Merkle engagée avant le calcul",
      "Mon affectation est justifiée par mon score et les seuils publiés",
    ],

    busy: {
      apply: "Ancrage de votre reçu sur Hedera...",
      tamper: "Le pirate modifie la base de données...",
      verify: "Vérification auprès de Hedera...",
    },
  },
  en: {
    brand: "Insaf",
    brandAr: " إنصاف",
    sub: "Verify that your place is really yours",
    student: "Student",
    scoreLabel: "Orientation score",
    placementLabel: "Published placement",
    receiptLabel: "Receipt (Hedera sequence)",
    pending: "Pending",

    s1: "1. The student ranks their choices",
    s1Text: "Open a specialty to browse its universities. Select and rank at least 3 universities by preference.",
    s1Label: "Your choices",
    selectionTitle: "Universities for",
    selectionText: "Select and rank at least 3 universities. These cards contain demo information.",
    aboutPlace: "About the location",
    aboutUniversity: "About",
    location: "Location",
    minimumScore: "Indicative minimum score",
    availablePlaces: "Available places",
    indicativeNote: "Demo guidance only. Actual cut-offs depend on available seats and published results.",
    select: "Select",
    remove: "Remove",
    selectedCount: "Universities selected",
    unavailableUniversity: "This university is no longer available for new choices.",
    minimumChoices: "Select at least 3 universities.",
    viewUniversities: "View universities",
    imageComing: "University photo coming soon",
    universityPhotoAlt: "Placeholder for the university photo",
    minimumSelection: "Choose at least 3 universities to submit your preferences.",
    submit: "Submit and get receipt",
    receipt: "RECEIPT",
    receiptTitle: "Confirmation of your university preferences",
    receiptStatus: "Preferences recorded and fingerprint anchored",
    receiptStudent: "Student",
    receiptCode: "Student code",
    receiptScore: "Orientation score",
    receiptDate: "Submission date",
    receiptChoices: "Your ranked preferences",
    printReceipt: "Print receipt",
    savedReceipt: "Your receipt is saved. Reopen it or download the PDF.",
    viewReceipt: "View saved receipt",
    downloadReceipt: "Download PDF",
    university: "University",
    specialty: "Specialty",
    preferenceRank: "Rank",
    requiredScore: "Indicative minimum score",
    studentScore: "Your score",
    scoreDifference: "Difference",
    noChoicesTable: "Select universities to see how your score compares.",
    hash: "hash",
    tx: "Hedera transaction",
    seq: "sequence",

    s3: "2. The hacker strikes",
    s4: "3. The student verifies",
    placedFor: "Published placement:",
    placementUnavailable: "Not available yet",
    logout: "Logout",
    back: "← Back",

    s3Text: "Someone silently rewrites the student's record in the database, as in the Kef case of August 2025.",
    tamper: "Change my record",

    s4Text: "The student's phone compares their receipt with what Hedera recorded.",
    verify: "Verify my preferences",
    integrityMismatch: "Integrity check failed. Your current preferences do not match the original submission recorded on Hedera.",
    generateIncidentPdf: "Generate Incident Report PDF",
    submitIncidentReport: "Submit Report to Administrator",
    incidentSubmitted: "Incident report submitted for administrator review.",
    adminIncidents: "Administrator incidents",
    incidentReports: "Incident reports",
    selectIncident: "Select a report to inspect its hashes, status, and PDF.",
    downloadIncidentPdf: "Download incident PDF",
    updateIncidentStatus: "Update status",
    valid: "Your preferences are verified",
    invalid: "Tampering detected",
    checks: [
      "My receipt matches the hash anchored on Hedera",
      "The platform record matches my receipt",
    ],

    busy: {
      apply: "Anchoring your receipt on Hedera...",
      tamper: "The hacker is editing the database...",
      verify: "Checking against Hedera...",
    },
  },
};

export default function Dashboard({ lang, setLang, dark, toggleTheme, session, onLogout, onSessionExpired }) {
  const code = session.student.code; // the logged-in student

  const [programs, setPrograms] = useState([]);
  const [student, setStudent] = useState(null);
  const [choices, setChoices] = useState([]);
  const [receipt, setReceipt] = useState(null);
  const [incidentPdf, setIncidentPdf] = useState(null);
  const [incidentSubmission, setIncidentSubmission] = useState(null);
  const [incidentSubmissionBusy, setIncidentSubmissionBusy] = useState(false);
  const [adminReports, setAdminReports] = useState([]);
  const [adminReportsBusy, setAdminReportsBusy] = useState(true);
  const [adminReportsError, setAdminReportsError] = useState("");
  const [selectedAdminReport, setSelectedAdminReport] = useState(null);
  const [adminReportStatus, setAdminReportStatus] = useState("Pending Review");
  const [adminActionBusy, setAdminActionBusy] = useState(false);
  const [result, setResult] = useState(null);
  const [busy, setBusy] = useState("");
  const [page, setPage] = useState(() =>
    window.history.state?.insafScreen === "dashboard" &&
    ["universities", "profile", "receipt", "admin-incidents"].includes(window.history.state.dashboardPage)
      ? window.history.state.dashboardPage
      : "home"
  ); // home | universities | profile | receipt | admin-incidents
  const [selectedSpecialtyId, setSelectedSpecialtyId] = useState(() =>
    window.history.state?.insafScreen === "dashboard" ? window.history.state.specialtyId ?? null : null
  );
  const formatScore = (score) => new Intl.NumberFormat(lang === "fr" ? "fr-FR" : "en-US", {
    maximumFractionDigits: 2,
  }).format(score);
  const [profile, setProfile] = useState(null);

  const t = translations[lang];
  const allUniversities = programs.flatMap((specialty) =>
    (specialty.universities ?? []).map((university) => ({
      ...university,
      specialtyName: getSpecialtyName(specialty.name, lang),
    }))
  );

  const navigatePage = (nextPage, specialtyId = null) => {
    window.history.pushState({
      ...window.history.state,
      insafScreen: "dashboard",
      dashboardPage: nextPage,
      specialtyId,
    }, "", window.location.href);
    setPage(nextPage);
    setSelectedSpecialtyId(specialtyId);
  };

  useEffect(() => {
    const handlePopState = (event) => {
      setPage(
        event.state?.insafScreen === "dashboard" &&
        ["universities", "profile", "receipt", "admin-incidents"].includes(event.state.dashboardPage)
          ? event.state.dashboardPage
          : "home"
      );
      setSelectedSpecialtyId(event.state?.specialtyId ?? null);
    };

    window.addEventListener("popstate", handlePopState);
    return () => window.removeEventListener("popstate", handlePopState);
  }, []);

  const refresh = async () => {
    const [programsResponse, studentResponse] = await Promise.all([
      api.get("/programs"),
      api.get(`/students/${code}`),
    ]);
    setPrograms(programsResponse.data);
    setStudent(studentResponse.data);
  };
  useEffect(() => {
    api.get("/dashboard").then(({ data }) => {
      const retiredIds = new Set(data.programs.flatMap((specialty) =>
        (specialty.universities ?? [])
          .filter((university) => isRetiredUniversity(specialty, university))
          .map((university) => university.id)
      ));
      setPrograms(data.programs);
      setStudent(data.student);
      setProfile(data.profile);
      setChoices(data.choices.filter((id) => !retiredIds.has(id)));
      setReceipt(data.receipt);
    }).catch((error) => {
      if (error.response?.status === 401) {
        onSessionExpired();
        return;
      }
      alert(error.response?.data?.message || error.message);
    });
  }, [onSessionExpired]);
  useEffect(() => {
    if (page !== "admin-incidents" || !profile?.is_admin) return;
    api.get("/admin/incident-reports")
      .then((response) => {
        setAdminReports(response.data.data);
        setAdminReportsError("");
      })
      .catch((error) => setAdminReportsError(error.response?.data?.message || error.message))
      .finally(() => setAdminReportsBusy(false));
  }, [page, profile?.is_admin]);

  // key = "apply" | "tamper" | "verify", so the banner follows the language
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
    const response = (await api.post("/apply", { code, choices })).data;
    setReceipt(response);
    navigatePage("receipt");
  });
  const tamper = () => act("tamper", async () => { await api.post(`/admin/tamper/${code}`); });
  const verify = () => act("verify", async () => {
    setIncidentPdf(null);
    setIncidentSubmission(null);
    setResult((await api.post("/verify", receipt)).data);
  });
  const generateIncidentPdf = async () => {
    if (!result?.incident) return;
    try {
      const pdf = await downloadIncidentReportPdf(result.incident, allUniversities, lang);
      setIncidentPdf(pdf);
      setIncidentSubmission(null);
    } catch (error) {
      alert(error.message);
    }
  };
  const submitIncidentReport = async () => {
    if (!result?.incident || !incidentPdf) return;
    setIncidentSubmissionBusy(true);
    try {
      const formData = new FormData();
      formData.append("incident", JSON.stringify(result.incident));
      formData.append("pdf", incidentPdf, `incident-${result.incident.id}.pdf`);
      const response = await api.post("/incident-reports", formData);
      setIncidentSubmission(response.data);
    } catch (error) {
      alert(error.response?.data?.message || error.message);
    } finally {
      setIncidentSubmissionBusy(false);
    }
  };
  const openAdminReport = async (incidentId) => {
    setAdminActionBusy(true);
    setAdminReportsError("");
    try {
      const response = await api.get(`/admin/incident-reports/${incidentId}`);
      setSelectedAdminReport(response.data);
      setAdminReportStatus(response.data.status);
    } catch (error) {
      setAdminReportsError(error.response?.data?.message || error.message);
    } finally {
      setAdminActionBusy(false);
    }
  };
  const downloadAdminIncidentPdf = async () => {
    if (!selectedAdminReport) return;
    setAdminActionBusy(true);
    try {
      const response = await api.get(
        `/admin/incident-reports/${selectedAdminReport.id}/pdf`,
        { responseType: "blob" }
      );
      const url = URL.createObjectURL(response.data);
      const link = document.createElement("a");
      link.href = url;
      link.download = `incident-${selectedAdminReport.id}.pdf`;
      link.click();
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) {
      alert(error.response?.data?.message || error.message);
    } finally {
      setAdminActionBusy(false);
    }
  };
  const saveAdminIncidentStatus = async () => {
    if (!selectedAdminReport) return;
    setAdminActionBusy(true);
    try {
      const response = await api.patch(
        `/admin/incident-reports/${selectedAdminReport.id}`,
        { status: adminReportStatus }
      );
      setSelectedAdminReport((report) => ({ ...report, status: response.data.status }));
      setAdminReports((reports) => reports.map((report) =>
        report.incident_id === response.data.id
          ? { ...report, status: response.data.status }
          : report
      ));
    } catch (error) {
      alert(error.response?.data?.message || error.message);
    } finally {
      setAdminActionBusy(false);
    }
  };

  return (
    <Layout lang={lang} dark={dark}>
        {/* ── Branding ── */}
        <div className="ins-brand-header">
          <h1 className="ins-main-logo">{t.brand}<span>{t.brandAr}</span></h1>
          <p className="ins-sub-logo">{t.sub}</p>
        </div>

        {/* ── Utility bar ── */}
        <div className="ins-utility-bar">
          {page !== "home" ? (
            <button
              className="ins-back-link"
              onClick={() => {
                if (
                  window.history.state?.insafScreen === "dashboard" &&
                  window.history.state.dashboardPage === page
                ) {
                  window.history.back();
                } else {
                  setPage("home");
                }
              }}
              type="button"
            >
              {t.back}
            </button>
          ) : busy ? (
            <div className="ins-busy"><span className="ins-spinner" />{t.busy[busy]}</div>
          ) : null}
        </div>

        {/* ── Student bar: avatar opens the profile ── */}
        <StudentBar lang={lang} profile={profile} onProfile={() => navigatePage("profile")} onLogout={onLogout} />

        {page === "admin-incidents" && profile?.is_admin ? (
          <div className="ins-content-card">
            <section className="ins-section">
              <h2 className="ins-section-title">{t.incidentReports}</h2>
              <p className="ins-text">{t.selectIncident}</p>
              {adminReportsError && <p className="ins-admin-error" role="alert">{adminReportsError}</p>}
              {adminReportsBusy ? (
                <p className="ins-text">{t.pending}</p>
              ) : (
                <div className="ins-admin-report-list">
                  {adminReports.map((report) => (
                    <button
                      className="ins-admin-report-item"
                      type="button"
                      key={report.incident_id}
                      onClick={() => openAdminReport(report.incident_id)}
                      disabled={adminActionBusy}
                    >
                      <strong>{report.incident_id}</strong>
                      <span>{report.student?.code ?? "-"} · {report.submission_reference} · {report.status}</span>
                    </button>
                  ))}
                </div>
              )}
              {selectedAdminReport && (
                <article className="ins-admin-report-detail">
                  <h3>{selectedAdminReport.id}</h3>
                  <dl className="ins-receipt-proof">
                    <div><dt>Student/submission reference</dt><dd>{selectedAdminReport.studentReference} / {selectedAdminReport.submissionReference}</dd></div>
                    <div><dt>Original SHA-256</dt><dd>{selectedAdminReport.originalHash}</dd></div>
                    <div><dt>Current SHA-256</dt><dd>{selectedAdminReport.currentHash}</dd></div>
                    <div><dt>Hedera topic / sequence</dt><dd>{selectedAdminReport.report.hedera.topicId ?? "-"} / {selectedAdminReport.report.hedera.sequence ?? "-"}</dd></div>
                    <div><dt>Consensus timestamp</dt><dd>{selectedAdminReport.report.hedera.consensusTimestamp ?? "-"}</dd></div>
                    <div><dt>Hedera record reference</dt><dd>{selectedAdminReport.report.hedera.recordReference ?? "-"}</dd></div>
                    <div><dt>Original receipt reference</dt><dd>{selectedAdminReport.report.originalReceiptReference.transactionId ?? "-"} / {selectedAdminReport.report.originalReceiptReference.sequence}</dd></div>
                    <div><dt>Original preferences</dt><dd>{selectedAdminReport.report.originalChoices.map((id, index) => {
                      const university = allUniversities.find((item) => item.id === id);
                      return `${index + 1}. ${university
                        ? (lang === "en" ? university.name_en : university.name_fr)
                        : `#${id}`}`;
                    }).join(" · ")}</dd></div>
                    <div><dt>Current preferences</dt><dd>{selectedAdminReport.report.currentChoices.map((id, index) => {
                      const university = allUniversities.find((item) => item.id === id);
                      return `${index + 1}. ${university
                        ? (lang === "en" ? university.name_en : university.name_fr)
                        : `#${id}`}`;
                    }).join(" · ")}</dd></div>
                  </dl>
                  <label className="ins-admin-status-control">
                    Report status
                    <select value={adminReportStatus} onChange={(event) => setAdminReportStatus(event.target.value)}>
                      <option>Pending Review</option>
                      <option>Under Investigation</option>
                      <option>Confirmed</option>
                      <option>False Alarm</option>
                    </select>
                  </label>
                  <div className="ins-print-actions">
                    <button className="ins-btn" type="button" disabled={adminActionBusy} onClick={saveAdminIncidentStatus}>
                      {t.updateIncidentStatus}
                    </button>
                    <button className="ins-btn" type="button" disabled={adminActionBusy} onClick={downloadAdminIncidentPdf}>
                      {t.downloadIncidentPdf}
                    </button>
                  </div>
                </article>
              )}
            </section>
            <Toggles lang={lang} setLang={setLang} dark={dark} onTheme={toggleTheme} />
          </div>
        ) : page === "profile" ? (
          <ProfilePage lang={lang} setLang={setLang} dark={dark} toggleTheme={toggleTheme} profile={profile} onSaved={setProfile} />
        ) : page === "receipt" ? (
          <div className="ins-content-card">
            <section className="ins-section">
              <h2 className="ins-section-title">{t.receiptTitle}</h2>
              {receipt ? (
                <div className="ins-printable-receipt">
                  <header className="ins-receipt-heading">
                    <div>
                      <p className="ins-receipt-brand">{t.brand}</p>
                      <h2>{t.receipt}</h2>
                      <p>{t.receiptTitle}</p>
                    </div>
                    <span className="ins-receipt-status">{t.receiptStatus}</span>
                  </header>
                  <dl className="ins-receipt-student">
                    <div><dt>{t.receiptStudent}</dt><dd>{profile?.name_fr ?? code}</dd></div>
                    <div><dt>{t.receiptCode}</dt><dd>{receipt.code}</dd></div>
                    <div><dt>{t.receiptScore}</dt><dd>{formatScore(receipt.score)}</dd></div>
                    <div><dt>{t.receiptDate}</dt><dd>{new Intl.DateTimeFormat(lang === "fr" ? "fr-FR" : "en-US", {
                      dateStyle: "medium",
                      timeStyle: "short",
                    }).format(new Date(receipt.submittedAt))}</dd></div>
                  </dl>
                  <h3>{t.receiptChoices}</h3>
                  <ol className="ins-receipt-choices">
                    {receipt.choices.map((choiceId) => {
                      const university = allUniversities.find((item) => item.id === choiceId);
                      return (
                        <li key={choiceId}>
                          <span>{university
                            ? (lang === "en" ? university.name_en : university.name_fr)
                            : `#${choiceId}`}</span>
                          {university && <span>{t.specialty}: {university.specialtyName}</span>}
                        </li>
                      );
                    })}
                  </ol>
                  <dl className="ins-receipt-proof">
                    <div><dt>{t.hash}</dt><dd>{receipt.hash}</dd></div>
                    <div><dt>{t.tx}</dt><dd>{receipt.tx}</dd></div>
                    <div><dt>{t.seq}</dt><dd>{receipt.sequence}</dd></div>
                  </dl>
                  <div className="ins-print-actions">
                    <button
                      className="ins-btn"
                      type="button"
                      onClick={() => downloadReceiptPdf(receipt, profile, allUniversities, lang, t)
                        .catch((error) => alert(error.message))}
                    >
                      {t.downloadReceipt}
                    </button>
                    <button className="ins-btn" type="button" onClick={() => window.print()}>
                      {t.printReceipt}
                    </button>
                  </div>
                </div>
              ) : (
                <p className="ins-text">{t.minimumSelection}</p>
              )}
            </section>
            <Toggles lang={lang} setLang={setLang} dark={dark} onTheme={toggleTheme} />
          </div>
        ) : page === "universities" ? (
          <div className="ins-content-card">
            <section className="ins-section">
              {(() => {
                const specialty = programs.find((program) => program.id === selectedSpecialtyId);
                if (!specialty) {
                  return (
                    <>
                      <h2 className="ins-section-title">{t.selectionTitle}</h2>
                      <p className="ins-text">{t.minimumChoices}</p>
                    </>
                  );
                }

                return (
                  <>
                    <h2 className="ins-section-title">{t.selectionTitle} {getSpecialtyName(specialty.name, lang)}</h2>
                    <p className="ins-text">{t.selectionText}</p>
                    <p className="ins-label">{t.selectedCount}: {choices.length}</p>
                    <div className="ins-university-grid">
                      {(specialty.universities ?? [])
                        .filter((university) => !isRetiredUniversity(specialty, university))
                        .map((university, universityIndex) => {
                        const rank = choices.indexOf(university.id);
                        const selected = rank !== -1;
                        const universityName = lang === "en" ? university.name_en : university.name_fr;
                        const description = lang === "en" ? university.description_en : university.description_fr;
                        const location = lang === "en" ? university.location_en : university.location_fr;
                        const specialtyKey = getSpecialtyKey(specialty.name);
                        const photoPath = specialtyKey === "informatique"
                          ? informaticsUniversityPhotos[universityIndex]
                          : specialtyKey === "medecine"
                            ? medicineUniversityPhotos[universityIndex]
                            : specialtyKey === "genie civil"
                              ? civilEngineeringUniversityPhotos[universityIndex]
                              : specialtyKey === "pharmacie"
                                ? pharmacyUniversityPhotos[universityIndex]
                                : specialtyKey === "architecture"
                                  ? architectureUniversityPhotos[universityIndex]
                                  : specialtyKey === "droit"
                                    ? lawUniversityPhotos[universityIndex]
                                    : specialtyKey === "economie"
                                      ? economicsUniversityPhotos[universityIndex]
                                      : specialtyKey === "lettres"
                                        ? literatureUniversityPhotos[universityIndex]
                            : university.photo_path;

                        return (
                          <article className={"ins-university-card" + (selected ? " selected" : "")} key={university.id}>
                            {photoPath ? (
                              <img
                                className="ins-university-photo ins-university-photo-image"
                                src={photoPath}
                                alt={universityName}
                              />
                            ) : (
                              <div className="ins-university-photo" role="img" aria-label={t.universityPhotoAlt}>
                                <span>{t.imageComing}</span>
                              </div>
                            )}
                            <div className="ins-university-card-head">
                              <div>
                                <p className="ins-university-kicker">{getSpecialtyName(specialty.name, lang)}</p>
                                <h3>{universityName}</h3>
                              </div>
                              {selected && <span className="ins-choice-rank">#{rank + 1}</span>}
                            </div>
                            <div className="ins-university-detail">
                              <strong>{t.aboutUniversity}</strong>
                              <p>{description}</p>
                            </div>
                            <div className="ins-university-facts">
                              <div>
                                <strong>{t.aboutPlace}</strong>
                                <span>{location}</span>
                              </div>
                              <div>
                                <strong>{t.minimumScore}</strong>
                                <span>{university.minimum_score}</span>
                              </div>
                              <div>
                                <strong>{t.availablePlaces}</strong>
                                <span>{Math.max(0, Number(university.capacity) - Number(university.admitted ?? 0))}</span>
                              </div>
                            </div>
                            <button
                              className={"ins-btn ins-university-select" + (selected ? " selected" : "")}
                              type="button"
                              aria-pressed={selected}
                              onClick={() => toggle(university.id)}
                            >
                              {selected ? t.remove : t.select}
                            </button>
                          </article>
                        );
                      })}
                    </div>
                    <p className="ins-university-note">{t.indicativeNote}</p>
                    <button className="ins-btn" disabled={choices.length < 3 || !!busy} onClick={submit}>
                      {t.submit}
                    </button>
                    {receipt && (
                      <button className="ins-btn" type="button" onClick={() => navigatePage("receipt")}>
                        {t.viewReceipt}
                      </button>
                    )}
                  </>
                );
              })()}
            </section>
            <Toggles lang={lang} setLang={setLang} dark={dark} onTheme={toggleTheme} />
          </div>
        ) : (
        <div className="ins-content-card">
          {/* 1. Student */}
          <section className="ins-section">
            <h2 className="ins-section-title">{t.s1}</h2>
            <p className="ins-text">{t.s1Text}</p>
            <p className="ins-label">{t.s1Label} · {t.selectedCount}: {choices.length}</p>
            <div className="ins-specialty-grid">
              {programs.map((specialty) => {
                const selectedCount = (specialty.universities ?? [])
                  .filter((university) => choices.includes(university.id)).length;
                const specialtyKey = getSpecialtyKey(specialty.name);
                return (
                  <article className={"ins-specialty-card" + (selectedCount ? " selected" : "")} key={specialty.id}>
                    <div className="ins-specialty-card-head">
                      <h3>
                        <button
                          className="ins-specialty-open"
                          type="button"
                          onClick={() => navigatePage("universities", specialty.id)}
                        >
                          <img
                            className={`ins-specialty-logo${["medecine", "genie civil", "economie"].includes(specialtyKey) ? " compact" : specialtyKey === "informatique" ? " medium" : ""}`}
                            src={specialtyLogos[specialtyKey]}
                            alt=""
                          />
                          <span className="ins-specialty-open-labels">
                            <span>{getSpecialtyName(specialty.name, lang)}</span>
                            <span>{t.viewUniversities} ({(specialty.universities ?? []).length}) →</span>
                          </span>
                        </button>
                      </h3>
                      {selectedCount > 0 && <span className="ins-choice-rank">{selectedCount}</span>}
                    </div>
                  </article>
                );
              })}
            </div>
            {choices.length
              ? <p className="ins-text">{choices.map((id, index) => {
                  const university = allUniversities.find((item) => item.id === id);
                  return university
                    ? `${index + 1}. ${lang === "en" ? university.name_en : university.name_fr} — ${university.specialtyName}`
                    : null;
                }).filter(Boolean).join(" · ")}</p>
              : <p className="ins-text">{t.minimumSelection}</p>}
            <button className="ins-btn" disabled={choices.length < 3 || !!busy} onClick={submit}>
              {t.submit}
            </button>
            {receipt && (
              <div className="ins-saved-receipt">
                <p className="ins-text">{t.savedReceipt}</p>
                <button className="ins-btn" type="button" onClick={() => navigatePage("receipt")}>
                  {t.viewReceipt}
                </button>
              </div>
            )}
          </section>

          {/* 2. Preference score comparison */}
          <section className="ins-section">
            <h3 className="ins-score-table-title">{t.s1Label}</h3>
            {choices.length > 0 ? (
              <div className="ins-score-table-wrap">
                <table className="ins-table ins-score-table">
                  <thead>
                    <tr>
                      <th>{t.preferenceRank}</th>
                      <th>{t.university}</th>
                      <th>{t.specialty}</th>
                      <th className="c">{t.requiredScore}</th>
                      <th className="c">{t.studentScore}</th>
                      <th className="c">{t.scoreDifference}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {choices.map((choiceId, index) => {
                      const university = allUniversities.find((item) => item.id === choiceId);
                      if (!university) return null;
                      const threshold = university.minimum_score == null
                        ? Number.NaN
                        : Number(university.minimum_score);
                      const score = student?.score == null ? Number.NaN : Number(student.score);
                      const difference = score - threshold;
                      const canCompare = Number.isFinite(score) && Number.isFinite(threshold);

                      return (
                        <tr key={choiceId}>
                          <td className="c">{index + 1}</td>
                          <td>{lang === "en" ? university.name_en : university.name_fr}</td>
                          <td>{university.specialtyName}</td>
                          <td className="c">{Number.isFinite(threshold) ? formatScore(threshold) : "-"}</td>
                          <td className="c">{Number.isFinite(score) ? formatScore(score) : "-"}</td>
                          <td className={`c ins-score-difference ${canCompare ? (difference >= 0 ? "positive" : "negative") : ""}`}>
                            {canCompare
                              ? `${difference >= 0 ? "+" : ""}${formatScore(difference)}`
                              : "-"}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="ins-text">{t.noChoicesTable}</p>
            )}
            <p className="ins-text">{t.indicativeNote}</p>
            <div className="ins-placed">
              {t.placedFor} <b>{t.placementUnavailable}</b>
            </div>
          </section>

          {/* 2. Hacker */}
          <section className="ins-section">
            <h2 className="ins-section-title">{t.s3}</h2>
            <p className="ins-text">{t.s3Text}</p>
            <button className="ins-btn danger" disabled={!!busy} onClick={tamper}>{t.tamper}</button>
          </section>

          {/* 3. Verify */}
          <section className="ins-section">
            <h2 className="ins-section-title">{t.s4}</h2>
            <p className="ins-text">{t.s4Text}</p>
            <button className="ins-btn" disabled={!receipt || !!busy} onClick={verify}>{t.verify}</button>
            {result && (
              <div className={"ins-result " + (result.valid ? "ok" : "ko")}>
                <h2>{result.incident
                  ? `⚠️ ${t.integrityMismatch}`
                  : result.valid
                    ? `✅ ${t.valid}`
                    : `🚨 ${t.invalid}`}</h2>
                {result.incident && (
                  <>
                    <button className="ins-btn" type="button" onClick={generateIncidentPdf}>
                      {t.generateIncidentPdf}
                    </button>
                    {incidentPdf && !incidentSubmission && (
                      <button
                        className="ins-btn"
                        type="button"
                        onClick={submitIncidentReport}
                        disabled={incidentSubmissionBusy}
                      >
                        {t.submitIncidentReport}
                      </button>
                    )}
                    {incidentSubmission && (
                      <p className="ins-admin-success" role="status">
                        {incidentSubmission.message || t.incidentSubmitted}
                      </p>
                    )}
                  </>
                )}
                {result.checks.map((c, i) => (
                  <div key={i} className="ins-check">{c.ok ? "✅" : "❌"} {t.checks[i] ?? c.name}</div>
                ))}
              </div>
            )}
          </section>

          {profile?.is_admin && (
            <section className="ins-section">
              <h2 className="ins-section-title">{t.adminIncidents}</h2>
              <button className="ins-btn" type="button" onClick={() => navigatePage("admin-incidents")}>
                {t.incidentReports}
              </button>
            </section>
          )}

          <Toggles lang={lang} setLang={setLang} dark={dark} onTheme={toggleTheme} />
        </div>
        )}
    </Layout>
  );
}