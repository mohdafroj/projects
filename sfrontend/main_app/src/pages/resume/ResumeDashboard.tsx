import React, { useState, useEffect } from "react";

interface ImprovementSuggestion {
  id: string;
  category: "experience" | "skills" | "formatting";
  impact: "high" | "medium" | "low";
  title: string;
  suggestion: string;
}

interface AnalysisResults {
  score: number;
  missingSkills: string[];
  improvements: ImprovementSuggestion[];
  atsChecks: { label: string; passed: boolean; tip?: string }[];
}

const TEMPLATES = [
  {
    title: "Senior Frontend Engineer (React/MFE)",
    jd: `We are looking for a Senior Frontend Developer with 5+ years of experience. Required skills: React 18, TypeScript, Tailwind CSS, Webpack 5, Module Federation, and State Management libraries like Redux or Zustand. Experience with Jest and Cypress testing is a plus. Candidates should have experience optimizing bundle performance.`,
    results: {
      score: 76,
      missingSkills: ["Module Federation", "Webpack 5", "Zustand / Redux", "Cypress E2E"],
      atsChecks: [
        { label: "Contact details parsed", passed: true },
        { label: "Standard headings format", passed: true },
        { label: "Font readability check", passed: true },
        { label: "Quantifiable bullet points", passed: false, tip: "Only 12% of achievements include metrics." },
      ],
      improvements: [
        {
          id: "imp1",
          category: "experience",
          impact: "high",
          title: "Incorporate metrics and KPIs",
          suggestion: "Instead of writing 'Maintained application code', write 'Optimized frontend load-time performance by 28% and reduced main bundle sizes by introducing dynamic code-splitting and Webpack optimization rules.'",
        },
        {
          id: "imp2",
          category: "skills",
          impact: "high",
          title: "Include Micro-Frontend and Federation Keywords",
          suggestion: "Your resume mentions 'Component design' but misses 'Module Federation' and 'Micro-Frontends' which are explicitly requested in the job description.",
        },
        {
          id: "imp3",
          category: "formatting",
          impact: "medium",
          title: "Add a focused Professional Summary",
          suggestion: "Include a 3-sentence summary at the top outlining your React expertise and architectural design experience specifically geared towards modern containerized architectures.",
        },
        {
          id: "imp4",
          category: "skills",
          impact: "low",
          title: "Categorize skills section",
          suggestion: "Split your skill list into subgroups ('Core Languages', 'Frameworks/Libraries', 'DevOps & Tools') to make it easier for parsing scanners to index.",
        },
      ],
    },
  },
  {
    title: "QA Automation Engineer (Cypress/Playwright)",
    jd: `Looking for a QA Automation Engineer. Must have strong experience writing End-to-End automation test suites using Cypress and Playwright. Solid understanding of JavaScript/TypeScript, CI/CD pipelines (GitHub Actions, Jenkins), API testing tools (Postman, Supertest), and Docker containerization.`,
    results: {
      score: 62,
      missingSkills: ["Playwright", "GitHub Actions", "Supertest API Testing", "Docker"],
      atsChecks: [
        { label: "Contact details parsed", passed: true },
        { label: "Standard headings format", passed: true },
        { label: "Font readability check", passed: true },
        { label: "Quantifiable bullet points", passed: true },
      ],
      improvements: [
        {
          id: "imp1",
          category: "skills",
          impact: "high",
          title: "Add Playwright Test Coverage",
          suggestion: "The JD lists Playwright as a core testing framework. Add any projects or test migration tasks where you evaluated or wrote Cypress and Playwright tests side-by-side.",
        },
        {
          id: "imp2",
          category: "experience",
          impact: "high",
          title: "Detail DevOps and CI/CD Integrations",
          suggestion: "Ensure you mention configuring testing pipelines inside CI environment files: 'Integrated automatic Cypress regression tests in GitHub Actions, blocking failing builds from launching into production environments.'",
        },
        {
          id: "imp3",
          category: "formatting",
          impact: "medium",
          title: "Rename heading names",
          suggestion: "Your test experience is currently lumped under 'Work Background'. Rename this to 'Professional Experience' which is a standard ATS header term.",
        },
      ],
    },
  },
];

const ResumeDashboard = () => {
  const [activeStep, setActiveStep] = useState<"input" | "loading" | "results">("input");
  const [resumeFile, setResumeFile] = useState<File | null>(null);
  const [jobDescription, setJobDescription] = useState("");
  const [loadingStep, setLoadingStep] = useState(0);
  const [analysisResults, setAnalysisResults] = useState<AnalysisResults | null>(null);
  const [dragOver, setDragOver] = useState(false);

  // Mock loading animation steps
  const LOADING_MESSAGES = [
    "Uploading and extracting text from document...",
    "Parsing contact details and work experience structures...",
    "Analyzing semantic keyword density...",
    "Matching credentials with target job requirements...",
    "Evaluating ATS formatting rules and parsing readability...",
    "Finalizing suggestions report...",
  ];

  useEffect(() => {
    let timer: NodeJS.Timeout;
    if (activeStep === "loading") {
      if (loadingStep < LOADING_MESSAGES.length) {
        timer = setTimeout(() => {
          setLoadingStep((prev) => prev + 1);
        }, 800);
      } else {
        // Trigger results
        // Match chosen template results or give a standard one
        const matchedTemplate = TEMPLATES.find((t) => jobDescription.includes(t.jd.substring(0, 40)));
        if (matchedTemplate) {
          setAnalysisResults(matchedTemplate.results);
        } else {
          // Default mock analysis fallback
          setAnalysisResults({
            score: 55,
            missingSkills: ["GraphQL", "Docker Orchestration", "CI/CD Deployment", "NodeJS Backend"],
            atsChecks: [
              { label: "Contact details parsed", passed: true },
              { label: "Standard headings format", passed: false, tip: "Headers styled as images could not be parsed." },
              { label: "Font readability check", passed: true },
              { label: "Quantifiable bullet points", passed: false, tip: "Only 8% of work history includes metrics." },
            ],
            improvements: [
              {
                id: "imp_d1",
                category: "experience",
                impact: "high",
                title: "Quantify achievements and task success",
                suggestion: "Incorporate metrics showing task efficiency gains, code reduction, load speed enhancements, or team outputs.",
              },
              {
                id: "imp_d2",
                category: "skills",
                impact: "high",
                title: "Map keywords to Job Description",
                suggestion: "The JD lists NodeJS and GraphQL. Add any relevant server-side integrations or schema definitions you have worked on.",
              },
              {
                id: "imp_d3",
                category: "formatting",
                impact: "medium",
                title: "Remove non-standard header formats",
                suggestion: "Format headings as clear H2/H3 text tags instead of graphics or borders to prevent scanner failure.",
              },
            ],
          });
        }
        setActiveStep("results");
      }
    }
    return () => clearTimeout(timer);
  }, [activeStep, loadingStep]);

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(true);
  };

  const handleDragLeave = () => {
    setDragOver(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(false);
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      setResumeFile(e.dataTransfer.files[0]);
    }
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      setResumeFile(e.target.files[0]);
    }
  };

  const handleRunAnalysis = () => {
    if (!resumeFile || !jobDescription.trim()) return;
    setLoadingStep(0);
    setActiveStep("loading");
  };

  const handleReset = () => {
    setResumeFile(null);
    setJobDescription("");
    setAnalysisResults(null);
    setActiveStep("input");
  };

  const handleLoadTemplate = (jdText: string) => {
    setJobDescription(jdText);
    // Load a mock file automatically for validation speed
    const mockFile = new File(["dummy pdf content"], "john_doe_resume.pdf", { type: "application/pdf" });
    setResumeFile(mockFile);
  };

  // SVGs / Gauges math
  const score = analysisResults?.score || 0;
  const radius = 60;
  const circumference = 2 * Math.PI * radius;
  const strokeDashoffset = circumference - (score / 100) * circumference;

  return (
    <div className="max-w-6xl mx-auto py-6 px-2 md:px-4">
      {/* HEADER SECTION */}
      <div className="text-center mb-8 space-y-2 select-none">
        <h1 className="text-2xl md:text-3xl font-extrabold bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-500 bg-clip-text text-transparent">
          AI Resume & JD Analyzer
        </h1>
        <p className="text-sm text-slate-400 max-w-xl mx-auto font-normal">
          Optimize your resume for applicant tracking systems (ATS). Upload your profile, supply the target job specification, and get a list of recommendations.
        </p>
      </div>

      {/* STEP 1: INPUT AND UPLOAD SPACE */}
      {activeStep === "input" && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">
          {/* LEFT COLUMN: RESUME FILE DRAG ZONE */}
          <div className="flex flex-col bg-[#11162A]/70 border border-slate-800/80 rounded-2xl p-5 shadow-lg relative">
            <h3 className="text-sm font-semibold mb-3 flex items-center gap-2">
              <span className="text-lg">📁</span> Upload Your Resume
            </h3>

            {/* Drop Zone */}
            <div
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
              onDrop={handleDrop}
              className={`flex-1 min-h-[220px] border-2 border-dashed rounded-xl flex flex-col items-center justify-center p-6 text-center transition-all ${
                dragOver
                  ? "border-indigo-500 bg-indigo-500/10"
                  : resumeFile
                  ? "border-emerald-500/50 bg-emerald-500/5"
                  : "border-slate-800 hover:border-slate-700 bg-slate-900/40"
              }`}
            >
              {resumeFile ? (
                <div className="space-y-3">
                  <div className="text-4xl text-emerald-400">📄</div>
                  <div>
                    <p className="text-sm font-bold text-slate-200 truncate max-w-[250px]">{resumeFile.name}</p>
                    <p className="text-[10px] text-slate-400">{(resumeFile.size / 1024).toFixed(1)} KB</p>
                  </div>
                  <button
                    onClick={() => setResumeFile(null)}
                    className="text-xs text-rose-400 hover:text-rose-300 font-semibold underline bg-transparent border-0 cursor-pointer"
                  >
                    Remove file
                  </button>
                </div>
              ) : (
                <div className="space-y-4">
                  <div className="text-4xl text-slate-600">📥</div>
                  <div>
                    <p className="text-sm font-semibold text-slate-300">Drag and drop your resume here</p>
                    <p className="text-xs text-slate-500">Supports PDF, DOCX, or TXT</p>
                  </div>
                  <label className="inline-block px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-750 text-xs font-semibold cursor-pointer border border-slate-750 hover:border-slate-700 transition-all select-none">
                    Browse Files
                    <input type="file" accept=".pdf,.docx,.txt" onChange={handleFileSelect} className="hidden" />
                  </label>
                </div>
              )}
            </div>

            {/* Quick JD Pre-fills */}
            <div className="mt-5 pt-4 border-t border-slate-800/80">
              <h4 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 select-none">
                Or quick test with templates:
              </h4>
              <div className="flex flex-col sm:flex-row gap-2">
                {TEMPLATES.map((tmpl, idx) => (
                  <button
                    key={idx}
                    onClick={() => handleLoadTemplate(tmpl.jd)}
                    className="flex-1 text-left text-xs bg-slate-900 hover:bg-slate-800 border border-slate-800/80 p-2.5 rounded-xl transition-all cursor-pointer font-medium hover:border-slate-700 truncate"
                  >
                    📝 {tmpl.title}
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* RIGHT COLUMN: JOB DESCRIPTION INPUT */}
          <div className="flex flex-col bg-[#11162A]/70 border border-slate-800/80 rounded-2xl p-5 shadow-lg">
            <h3 className="text-sm font-semibold mb-3 flex items-center gap-2">
              <span className="text-lg">📋</span> Target Job Description
            </h3>

            <textarea
              placeholder="Paste the target job description or requirements list here to compare with your resume..."
              value={jobDescription}
              onChange={(e) => setJobDescription(e.target.value)}
              className="flex-1 w-full min-h-[220px] bg-slate-900/60 border border-slate-800 p-4 rounded-xl text-xs outline-none focus:border-indigo-500/60 resize-none text-slate-200 placeholder-slate-500 leading-relaxed font-sans"
            />

            <button
              onClick={handleRunAnalysis}
              disabled={!resumeFile || !jobDescription.trim()}
              className="mt-4 w-full py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 disabled:opacity-30 disabled:from-slate-800 disabled:to-slate-800 disabled:text-slate-500 text-white font-bold text-sm transition-all duration-300 shadow-md shadow-indigo-950/20 active:scale-98 cursor-pointer select-none"
            >
              ⚡ Run AI ATS Analysis
            </button>
          </div>
        </div>
      )}

      {/* STEP 2: ANIMATED ANALYSIS LOADER SCREEN */}
      {activeStep === "loading" && (
        <div className="max-w-md mx-auto bg-[#11162A]/80 border border-slate-800 rounded-2xl p-8 shadow-xl text-center space-y-6 my-12">
          {/* Animated Spinner Icon */}
          <div className="relative inline-block select-none">
            <div className="w-16 h-16 rounded-full border-4 border-slate-850 border-t-indigo-500 animate-spin"></div>
            <div className="absolute inset-0 flex items-center justify-center text-xl">
              🤖
            </div>
          </div>

          <div className="space-y-2">
            <h3 className="text-sm font-bold text-slate-200">Evaluating Your Profile</h3>
            <p className="text-xs text-indigo-400 font-medium">Running parser pipelines...</p>
          </div>

          {/* Steps checklist */}
          <div className="text-left space-y-3 bg-slate-900 border border-slate-800 p-4 rounded-xl text-xs font-normal">
            {LOADING_MESSAGES.map((msg, i) => {
              const isPast = loadingStep > i;
              const isCurrent = loadingStep === i;
              return (
                <div
                  key={i}
                  className={`flex items-center gap-2 transition-opacity duration-300 ${
                    isPast ? "text-emerald-400" : isCurrent ? "text-indigo-400 font-medium" : "text-slate-600"
                  }`}
                >
                  <span className="text-sm shrink-0">
                    {isPast ? "✔" : isCurrent ? "⚡" : "○"}
                  </span>
                  <span className="truncate">{msg}</span>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* STEP 3: RESULTS ANALYTICS VIEW */}
      {activeStep === "results" && analysisResults && (
        <div className="space-y-6">
          {/* TOP CARD: SCORES OVERVIEW */}
          <div className="bg-[#11162A]/80 border border-slate-800/80 rounded-2xl p-6 shadow-lg flex flex-col md:flex-row gap-8 items-center">
            
            {/* MATCH SCORE GAUGE */}
            <div className="relative flex-shrink-0 select-none">
              <svg className="w-36 h-36 transform -rotate-90">
                <circle
                  cx="72"
                  cy="72"
                  r={radius}
                  stroke="#1e293b"
                  strokeWidth="8"
                  fill="transparent"
                />
                <circle
                  cx="72"
                  cy="72"
                  r={radius}
                  stroke={score >= 70 ? "#10b981" : score >= 50 ? "#f59e0b" : "#f43f5e"}
                  strokeWidth="8"
                  fill="transparent"
                  strokeDasharray={circumference}
                  strokeDashoffset={strokeDashoffset}
                  strokeLinecap="round"
                  className="transition-all duration-1000 ease-out"
                />
              </svg>
              <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className="text-2xl font-black">{score}%</span>
                <span className="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">ATS Match</span>
              </div>
            </div>

            {/* SCORE EVALUATION DESCRIPTION */}
            <div className="flex-1 space-y-3 text-center md:text-left">
              <div>
                <span
                  className={`inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider mb-1 ${
                    score >= 70
                      ? "bg-emerald-500/20 text-emerald-400"
                      : score >= 50
                      ? "bg-amber-500/20 text-amber-400"
                      : "bg-rose-500/20 text-rose-400"
                  }`}
                >
                  {score >= 70 ? "Strong Match" : score >= 50 ? "Moderate Gap" : "Weak Match"}
                </span>
                <h2 className="text-lg font-bold text-slate-200">
                  {score >= 70
                    ? "Great alignment with target requirements!"
                    : score >= 50
                    ? "A few key skills are holding you back."
                    : "Significant adjustments required to clear automated scanners."}
                </h2>
              </div>
              <p className="text-xs text-slate-400 leading-relaxed font-normal">
                An ATS score of **{score}%** is calculated by looking at key qualifications, tool requirements, and sentence layouts. Below, we've identified the missing terms you should address.
              </p>
              <div className="flex flex-wrap justify-center md:justify-start gap-2 pt-1.5">
                <button
                  onClick={handleReset}
                  className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-750 border border-slate-700 text-xs font-semibold transition-all cursor-pointer select-none"
                >
                  ← Analyze Another
                </button>
              </div>
            </div>
          </div>

          {/* LOWER SECTION LAYOUT */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            {/* LEFT COLUMN: MISSING SKILLS & ATS CHECKLIST (1/3 width) */}
            <div className="space-y-6">
              {/* MISSING SKILLS BADGES */}
              <div className="bg-[#11162A]/60 border border-slate-800/80 rounded-2xl p-5 shadow-sm space-y-4">
                <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5 select-none">
                  ⚠️ Missing Keywords
                </h3>
                <div className="flex flex-wrap gap-2">
                  {analysisResults.missingSkills.map((skill, idx) => (
                    <span
                      key={idx}
                      className="px-2.5 py-1 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-lg text-xs font-semibold select-none flex items-center gap-1.5"
                    >
                      <span className="w-1.5 h-1.5 rounded-full bg-rose-400" />
                      {skill}
                    </span>
                  ))}
                </div>
                <p className="text-[10px] text-slate-500 font-normal leading-normal">
                  *Tip: Adding these key tech phrases naturally in your experiences will improve search relevance.*
                </p>
              </div>

              {/* ATS SCANNER FORMATTING CHECKS */}
              <div className="bg-[#11162A]/60 border border-slate-800/80 rounded-2xl p-5 shadow-sm space-y-4">
                <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5 select-none">
                  🔍 ATS Formatting Checks
                </h3>
                <div className="space-y-3 font-normal text-xs">
                  {analysisResults.atsChecks.map((check, idx) => (
                    <div key={idx} className="space-y-1">
                      <div className="flex items-center justify-between">
                        <span className="text-slate-300 font-medium">{check.label}</span>
                        <span
                          className={`font-semibold text-xs ${
                            check.passed ? "text-emerald-400" : "text-rose-400"
                          }`}
                        >
                          {check.passed ? "Pass ✓" : "Fail ✗"}
                        </span>
                      </div>
                      {check.tip && <p className="text-[10px] text-slate-500 leading-normal">{check.tip}</p>}
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* RIGHT COLUMN: DETAILED RECOMMENDATIONS (2/3 width) */}
            <div className="lg:col-span-2 bg-[#11162A]/60 border border-slate-800/80 rounded-2xl p-5 shadow-sm space-y-4">
              <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider select-none">
                💡 Suggested Improvements
              </h3>

              <div className="space-y-3">
                {analysisResults.improvements.map((imp) => (
                  <div
                    key={imp.id}
                    className="p-4 bg-slate-900/60 border border-slate-800 rounded-xl space-y-2 hover:border-slate-700 transition-all"
                  >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <span className="text-xs font-bold text-slate-200">
                        {imp.title}
                      </span>
                      <div className="flex gap-2 select-none">
                        <span className="text-[9px] bg-slate-800 text-slate-400 px-2 py-0.5 rounded-md uppercase font-bold tracking-wider">
                          {imp.category}
                        </span>
                        <span
                          className={`text-[9px] px-2 py-0.5 rounded-md uppercase font-bold tracking-wider ${
                            imp.impact === "high"
                              ? "bg-rose-500/20 text-rose-400"
                              : imp.impact === "medium"
                              ? "bg-amber-500/20 text-amber-400"
                              : "bg-blue-500/20 text-blue-400"
                          }`}
                        >
                          {imp.impact} Impact
                        </span>
                      </div>
                    </div>
                    <p className="text-[11px] text-slate-400 leading-relaxed font-normal">
                      {imp.suggestion}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ResumeDashboard;