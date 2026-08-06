import React, { useState, useEffect, useRef } from "react";

interface Message {
    id: string;
    sender: "user" | "bot";
    text: string;
    timestamp: string;
}

interface Persona {
    id: string;
    name: string;
    title: string;
    avatar: string;
    color: string;
    greeting: string;
    systemPrompt: string;
    model: string;
    temp: number;
    tokens: number;
}

const PERSONAS: Persona[] = [
    {
        id: "aether",
        name: "Aether",
        title: "General AI Assistant",
        avatar: "✨",
        color: "from-violet-500 to-indigo-500",
        greeting: "Hello! I am Aether, your general intelligence assistant. How can I help you today?",
        systemPrompt: "You are Aether, a general intelligence assistant trained to be helpful, harmless, and honest. You provide clear, concise, and structured answers.",
        model: "Gemini 1.5 Pro",
        temp: 0.7,
        tokens: 2048,
    },
    {
        id: "nyx",
        name: "Nyx",
        title: "Code Architect & Dev",
        avatar: "💻",
        color: "from-cyan-500 to-blue-500",
        greeting: "System online. I am Nyx, your programming and systems design mentor. Post your code or architecture questions here.",
        systemPrompt: "You are Nyx, an expert code compiler and software architect. You write highly optimized, clean code snippets and explain complex technical concepts simply.",
        model: "Claude 3.5 Sonnet",
        temp: 0.2,
        tokens: 4096,
    },
    {
        id: "iris",
        name: "Iris",
        title: "Creative Writer & Muse",
        avatar: "🎨",
        color: "from-amber-500 to-rose-500",
        greeting: "Greetings! I am Iris, your creative copywriter and brainstorm partner. Let's write something spectacular together.",
        systemPrompt: "You are Iris, a creative muse. You write engaging stories, copy, poems, and marketing pitches, using rich metaphors and expressive language.",
        model: "GPT-4o",
        temp: 0.9,
        tokens: 1024,
    },
];

const SUGGESTIONS = {
    aether: [
        "Explain quantum computing in simple terms.",
        "Help me plan a 3-day itinerary for Tokyo.",
        "What are some healthy breakfast ideas?",
    ],
    nyx: [
        "Write a TypeScript generic utility for API responses.",
        "Explain the differences between REST and GraphQL.",
        "How do I prevent SQL injection in Node.js?",
    ],
    iris: [
        "Write a catchy slogan for a high-end coffee shop.",
        "Create a story outline about a time-traveling explorer.",
        "Help me write an email asking for project feedback.",
    ],
};

const ChatbotDashboard = () => {
    const [activePersona, setActivePersona] = useState<Persona>(PERSONAS[0]);
    const [messages, setMessages] = useState<Record<string, Message[]>>({
        aether: [
            {
                id: "init",
                sender: "bot",
                text: PERSONAS[0].greeting,
                timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
            },
        ],
        nyx: [
            {
                id: "init",
                sender: "bot",
                text: PERSONAS[1].greeting,
                timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
            },
        ],
        iris: [
            {
                id: "init",
                sender: "bot",
                text: PERSONAS[2].greeting,
                timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
            },
        ],
    });

    const [inputText, setInputText] = useState("");
    const [isTyping, setIsTyping] = useState(false);
    const [showInfoPanel, setShowInfoPanel] = useState(true);
    const [showMobileSidebar, setShowMobileSidebar] = useState(false);

    // Settings values for active persona
    const [temp, setTemp] = useState<Record<string, number>>({
        aether: PERSONAS[0].temp,
        nyx: PERSONAS[1].temp,
        iris: PERSONAS[2].temp,
    });
    const [tokens, setTokens] = useState<Record<string, number>>({
        aether: PERSONAS[0].tokens,
        nyx: PERSONAS[1].tokens,
        iris: PERSONAS[2].tokens,
    });

    const messagesEndRef = useRef<HTMLDivElement>(null);

    // Auto scroll to bottom
    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages, isTyping, activePersona]);

    const handleSendMessage = async (textToSend: string) => {
        if (!textToSend.trim()) return;

        const currentTime = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
        const userMsg: Message = {
            id: Math.random().toString(),
            sender: "user",
            text: textToSend,
            timestamp: currentTime,
        };

        // Add user message
        const updatedMessages = [...(messages[activePersona.id] || []), userMsg];
        setMessages((prev) => ({
            ...prev,
            [activePersona.id]: updatedMessages,
        }));

        setInputText("");
        setIsTyping(true);

        // Prepare conversation history payload
        const historyPayload = updatedMessages
            .filter((msg) => msg.id !== "init")
            .map((msg) => ({
                role: msg.sender === "user" ? "user" : "assistant",
                content: msg.text,
            }));

        // Place a streaming bot message placeholder
        const botMsgId = Math.random().toString();
        const newBotMsg: Message = {
            id: botMsgId,
            sender: "bot",
            text: "",
            timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
        };

        setMessages((prev) => ({
            ...prev,
            [activePersona.id]: [...prev[activePersona.id], newBotMsg],
        }));

        try {
            const response = await fetch("http://localhost:8004/api/v1/chat/stream", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    prompt: textToSend,
                    system_prompt: activePersona.systemPrompt,
                    model: activePersona.model,
                    temperature: temp[activePersona.id],
                    max_tokens: tokens[activePersona.id],
                    history: historyPayload.slice(0, -1),
                }),
            });

            if (!response.body) throw new Error("No response body received");

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let cumulativeText = "";

            setIsTyping(false);

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;

                const chunk = decoder.decode(value);
                const lines = chunk.split("\n");

                for (const line of lines) {
                    if (line.startsWith("data: ")) {
                        const token = line.slice(6);
                        cumulativeText += token;

                        setMessages((prev) => {
                            const list = prev[activePersona.id] || [];
                            return {
                                ...prev,
                                [activePersona.id]: list.map((msg) =>
                                    msg.id === botMsgId ? { ...msg, text: cumulativeText } : msg
                                ),
                            };
                        });
                    }
                }
            }
        } catch (error) {
            console.error("AI Chatbot streaming error:", error);
            setIsTyping(false);
            setMessages((prev) => {
                const list = prev[activePersona.id] || [];
                return {
                    ...prev,
                    [activePersona.id]: list.map((msg) =>
                        msg.id === botMsgId
                            ? { ...msg, text: "⚠️ Error contacting ToolsService. Please verify the backend is running." }
                            : msg
                    ),
                };
            });
        }
    };

    const handleClearChat = () => {
        setMessages((prev) => ({
            ...prev,
            [activePersona.id]: [
                {
                    id: "init",
                    sender: "bot",
                    text: activePersona.greeting,
                    timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
                },
            ],
        }));
    };

    const activeMessages = messages[activePersona.id] || [];

    return (
        <div className="flex h-[calc(100vh-3.5rem)] -mx-4 -mb-4 overflow-hidden bg-[#0A0D1A] text-slate-100 font-sans relative">
            {/* SIDEBAR */}
            <aside
                className={`fixed inset-y-12 left-0 z-40 w-64 bg-[#0F1424] border-r border-slate-800 transition-transform duration-300 transform lg:translate-x-0 lg:static ${showMobileSidebar ? "translate-x-0" : "-translate-x-full"
                    }`}
            >
                <div className="flex flex-col h-full">
                    {/* Logo / New Chat */}
                    <div className="p-4 border-b border-slate-800">
                        <button
                            onClick={() => {
                                handleClearChat();
                                setShowMobileSidebar(false);
                            }}
                            className="w-full flex items-center justify-center gap-2 py-2 px-4 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-medium text-sm transition-all duration-300 shadow-lg shadow-indigo-500/20 active:scale-95 cursor-pointer"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" />
                            </svg>
                            New Conversation
                        </button>
                    </div>

                    {/* Persona Selection */}
                    <div className="p-4 border-b border-slate-800">
                        <h4 className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">AI Personas</h4>
                        <div className="space-y-2">
                            {PERSONAS.map((persona) => {
                                const isActive = activePersona.id === persona.id;
                                return (
                                    <button
                                        key={persona.id}
                                        onClick={() => {
                                            setActivePersona(persona);
                                            setShowMobileSidebar(false);
                                        }}
                                        className={`w-full flex items-center gap-3 p-2.5 rounded-xl text-left border transition-all duration-300 cursor-pointer ${isActive
                                            ? "bg-slate-800/80 border-indigo-500/50 shadow-md shadow-indigo-950/20"
                                            : "bg-transparent border-transparent hover:bg-slate-800/40 text-slate-300 hover:text-white"
                                            }`}
                                    >
                                        <div className={`flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br ${persona.color} text-lg`}>
                                            {persona.avatar}
                                        </div>
                                        <div>
                                            <div className="font-semibold text-sm leading-tight">{persona.name}</div>
                                            <div className="text-xs text-slate-400 font-normal">{persona.title}</div>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Navigation / Recent Chats */}
                    <div className="flex-1 overflow-y-auto p-4 space-y-2">
                        <h4 className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">History</h4>
                        <div className="space-y-1">
                            <div className="p-2 rounded-lg bg-slate-800/30 text-xs text-slate-400 flex items-center gap-2">
                                💬 Active simulated chat
                            </div>
                        </div>
                    </div>

                    {/* User Profile */}
                    <div className="p-4 border-t border-slate-800 bg-[#0c101d] flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center font-bold text-sm text-indigo-300">
                                A
                            </div>
                            <div>
                                <div className="text-xs font-semibold">Admin Account</div>
                                <div className="text-[10px] text-emerald-400 flex items-center gap-1 font-normal">
                                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block animate-pulse"></span>
                                    Online
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            {/* MOBILE OVERLAY */}
            {showMobileSidebar && (
                <div
                    onClick={() => setShowMobileSidebar(false)}
                    className="fixed inset-0 z-30 bg-black/60 backdrop-blur-xs lg:hidden"
                />
            )}

            {/* MAIN CHAT AREA */}
            <main className="flex-1 flex flex-col min-w-0 bg-[#080B15]">
                {/* Header */}
                <header className="flex justify-between items-center h-14 px-4 bg-[#0F1424]/90 backdrop-blur-md border-b border-slate-800/80 sticky top-0 z-20">
                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => setShowMobileSidebar(true)}
                            className="lg:hidden p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 cursor-pointer"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <div className={`flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br ${activePersona.color} text-lg shadow-md`}>
                            {activePersona.avatar}
                        </div>
                        <div>
                            <h2 className="text-sm font-semibold leading-tight">{activePersona.name}</h2>
                            <div className="text-[10px] text-indigo-400 font-medium flex items-center gap-1.5">
                                <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span>
                                {activePersona.model}
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleClearChat}
                            title="Clear current chat"
                            className="p-1.5 rounded-lg bg-slate-800/50 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white transition-all cursor-pointer"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>

                        <button
                            onClick={() => setShowInfoPanel(!showInfoPanel)}
                            title="Toggle Parameters Panel"
                            className={`p-1.5 rounded-lg border transition-all cursor-pointer ${showInfoPanel
                                ? "bg-indigo-600/20 border-indigo-500/50 text-indigo-300"
                                : "bg-slate-800/50 border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white"
                                }`}
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    </div>
                </header>

                {/* Message Container */}
                <div className="flex-1 overflow-y-auto p-4 space-y-4">
                    {activeMessages.map((msg) => {
                        const isUser = msg.sender === "user";
                        return (
                            <div key={msg.id} className={`flex gap-3 max-w-[85%] ${isUser ? "ml-auto flex-row-reverse" : "mr-auto"}`}>
                                {/* Avatar */}
                                <div
                                    className={`flex items-center justify-center w-8 h-8 rounded-lg text-sm shrink-0 shadow-md ${isUser ? "bg-indigo-600 text-white" : `bg-gradient-to-br ${activePersona.color}`
                                        }`}
                                >
                                    {isUser ? "👤" : activePersona.avatar}
                                </div>

                                {/* Bubble */}
                                <div>
                                    <div
                                        className={`p-3 rounded-2xl shadow-lg border text-sm whitespace-pre-line leading-relaxed ${isUser
                                            ? "bg-gradient-to-br from-indigo-600 to-indigo-700 border-indigo-500/50 text-white rounded-tr-none"
                                            : "bg-slate-900 border-slate-800 text-slate-100 rounded-tl-none"
                                            }`}
                                    >
                                        {msg.text}
                                    </div>
                                    <div className={`text-[10px] text-slate-500 mt-1 ${isUser ? "text-right" : "text-left"}`}>
                                        {msg.timestamp}
                                    </div>
                                </div>
                            </div>
                        );
                    })}

                    {/* Typing Indicator */}
                    {isTyping && (
                        <div className="flex gap-3 max-w-[85%] mr-auto">
                            <div className={`flex items-center justify-center w-8 h-8 rounded-lg text-sm shrink-0 bg-gradient-to-br ${activePersona.color}`}>
                                {activePersona.avatar}
                            </div>
                            <div className="p-3 bg-slate-900 border border-slate-800 rounded-2xl rounded-tl-none shadow-md flex items-center gap-1">
                                <span className="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-bounce" style={{ animationDelay: "0ms" }}></span>
                                <span className="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-bounce" style={{ animationDelay: "150ms" }}></span>
                                <span className="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-bounce" style={{ animationDelay: "300ms" }}></span>
                            </div>
                        </div>
                    )}

                    {/* Prompt Suggestions (Only if chat contains just the greeting) */}
                    {activeMessages.length === 1 && !isTyping && (
                        <div className="max-w-2xl mx-auto pt-8">
                            <h3 className="text-xs font-semibold text-slate-400 uppercase tracking-wider text-center mb-4">
                                Ask a sample query to get started
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                {SUGGESTIONS[activePersona.id as keyof typeof SUGGESTIONS]?.map((sug, i) => (
                                    <button
                                        key={i}
                                        onClick={() => handleSendMessage(sug)}
                                        className="p-3 text-left text-xs bg-slate-900/60 border border-slate-800/80 rounded-xl hover:bg-slate-850 hover:border-slate-700 hover:-translate-y-0.5 transition-all duration-300 text-slate-300 hover:text-white cursor-pointer select-none font-normal shadow-sm hover:shadow-indigo-950/20"
                                    >
                                        {sug}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    <div ref={messagesEndRef} />
                </div>

                {/* Input Bar */}
                <div className="p-4 bg-[#0A0E1A] border-t border-slate-850/80 sticky bottom-0">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            handleSendMessage(inputText);
                        }}
                        className="max-w-4xl mx-auto flex gap-2 items-center bg-slate-900/90 border border-slate-800 p-1.5 px-3 rounded-2xl focus-within:border-indigo-500/60 focus-within:shadow-md focus-within:shadow-indigo-950/30 transition-all duration-300"
                    >
                        {/* Attachment Button */}
                        <button
                            type="button"
                            title="Attach File (Not supported in simulation)"
                            className="p-2 text-slate-400 hover:text-slate-200 hover:bg-slate-800 rounded-xl transition-all cursor-pointer"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                            </svg>
                        </button>

                        {/* Main Input Textbox */}
                        <input
                            type="text"
                            placeholder={`Message ${activePersona.name}...`}
                            value={inputText}
                            onChange={(e) => setInputText(e.target.value)}
                            className="flex-1 bg-transparent border-0 outline-none text-slate-100 text-sm py-2 placeholder-slate-500"
                        />

                        {/* Microphone / Audio */}
                        <button
                            type="button"
                            title="Voice Input (Not supported in simulation)"
                            className="p-2 text-slate-400 hover:text-slate-200 hover:bg-slate-800 rounded-xl transition-all cursor-pointer"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                            </svg>
                        </button>

                        {/* Send Button */}
                        <button
                            type="submit"
                            disabled={!inputText.trim()}
                            className="p-2 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 text-white transition-all disabled:opacity-30 disabled:from-slate-800 disabled:to-slate-800 disabled:text-slate-500 active:scale-95 shadow-md shadow-indigo-950/20 cursor-pointer"
                        >
                            <svg className="w-4.5 h-4.5 transform rotate-90" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                            </svg>
                        </button>
                    </form>
                    <div className="text-[10px] text-slate-500 text-center mt-2 font-normal leading-normal">
                        Simulated output is powered by local mocked responses. Parameters configured in the side panel will affect local simulation descriptions.
                    </div>
                </div>
            </main>

            {/* PARAMETERS / SETTINGS PANEL */}
            {showInfoPanel && (
                <aside className="w-64 bg-[#0F1424] border-l border-slate-800 hidden xl:flex flex-col h-full overflow-y-auto">
                    <div className="p-4 border-b border-slate-800 flex justify-between items-center bg-[#0c101d]">
                        <h3 className="font-semibold text-sm">Model Parameters</h3>
                        <span className="text-[10px] bg-indigo-600/20 text-indigo-300 font-bold px-1.5 py-0.5 rounded-md uppercase">
                            Config
                        </span>
                    </div>

                    <div className="p-4 space-y-5">
                        {/* Active Model Name */}
                        <div>
                            <label className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block mb-1">
                                Active Model
                            </label>
                            <div className="bg-slate-900 border border-slate-800 p-2.5 rounded-xl font-medium text-xs text-indigo-400">
                                🤖 {activePersona.model}
                            </div>
                        </div>

                        {/* Temperature Slider */}
                        <div>
                            <div className="flex justify-between text-xs mb-1">
                                <label className="font-semibold text-slate-400 uppercase tracking-wider text-[10px]">
                                    Temperature
                                </label>
                                <span className="font-mono text-indigo-400 font-bold text-xs">
                                    {temp[activePersona.id]}
                                </span>
                            </div>
                            <input
                                type="range"
                                min="0.0"
                                max="1.0"
                                step="0.1"
                                value={temp[activePersona.id]}
                                onChange={(e) => {
                                    const val = parseFloat(e.target.value);
                                    setTemp((prev) => ({ ...prev, [activePersona.id]: val }));
                                }}
                                className="w-full accent-indigo-500 bg-slate-900 rounded-lg cursor-pointer"
                            />
                            <div className="flex justify-between text-[9px] text-slate-500 mt-1 font-normal">
                                <span>Deterministic (0.0)</span>
                                <span>Creative (1.0)</span>
                            </div>
                        </div>

                        {/* Max Output Tokens */}
                        <div>
                            <div className="flex justify-between text-xs mb-1">
                                <label className="font-semibold text-slate-400 uppercase tracking-wider text-[10px]">
                                    Max Output Tokens
                                </label>
                                <span className="font-mono text-indigo-400 font-bold text-xs">
                                    {tokens[activePersona.id]}
                                </span>
                            </div>
                            <input
                                type="range"
                                min="256"
                                max="4096"
                                step="256"
                                value={tokens[activePersona.id]}
                                onChange={(e) => {
                                    const val = parseInt(e.target.value);
                                    setTokens((prev) => ({ ...prev, [activePersona.id]: val }));
                                }}
                                className="w-full accent-indigo-500 bg-slate-900 rounded-lg cursor-pointer"
                            />
                            <div className="flex justify-between text-[9px] text-slate-500 mt-1 font-normal">
                                <span>Short (256)</span>
                                <span>Long (4096)</span>
                            </div>
                        </div>

                        <hr className="border-slate-800" />

                        {/* System Instructions display */}
                        <div>
                            <label className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block mb-1">
                                System Persona Instructions
                            </label>
                            <div className="bg-slate-900/60 border border-slate-800/80 p-3 rounded-xl text-xs text-slate-300 leading-relaxed font-normal">
                                {activePersona.systemPrompt}
                            </div>
                        </div>
                    </div>
                </aside>
            )}
        </div>
    );
};

export default ChatbotDashboard;