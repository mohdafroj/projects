import React, { useState, useEffect, useRef } from "react";

interface ChatMessage {
  id: string;
  senderName: string;
  senderAvatar: string;
  senderColor: string;
  text: string;
  timestamp: string;
  isUser: boolean;
}

interface ChatRoom {
  id: string;
  name: string;
  type: "channel" | "dm";
  avatar: string;
  avatarColor: string;
  status?: "online" | "idle" | "offline";
  description: string;
  unreadCount: number;
}

const INITIAL_ROOMS: ChatRoom[] = [
  {
    id: "design",
    name: "design-system",
    type: "channel",
    avatar: "🎨",
    avatarColor: "from-pink-500 to-rose-500",
    description: "Coordination space for our global UX component library and UI layouts.",
    unreadCount: 2,
  },
  {
    id: "dev",
    name: "development",
    type: "channel",
    avatar: "⚙️",
    avatarColor: "from-cyan-500 to-blue-500",
    description: "CI/CD checks, PR reviews, and engineering updates.",
    unreadCount: 0,
  },
  {
    id: "sarah",
    name: "Sarah (QA Lead)",
    type: "dm",
    avatar: "👩‍💻",
    avatarColor: "from-emerald-500 to-teal-500",
    status: "online",
    description: "Quality assurance manager. Leads test-automation pipeline.",
    unreadCount: 1,
  },
  {
    id: "alex",
    name: "Alex (Systems Dev)",
    type: "dm",
    avatar: "👨‍💻",
    avatarColor: "from-indigo-500 to-purple-500",
    status: "idle",
    description: "Senior Infrastructure Engineer working on AWS orchestration.",
    unreadCount: 0,
  },
  {
    id: "john",
    name: "John (Product)",
    type: "dm",
    avatar: "💼",
    avatarColor: "from-amber-500 to-orange-500",
    status: "offline",
    description: "Product Manager. Oversees roadmaps and sprint planning.",
    unreadCount: 0,
  },
];

const INITIAL_MESSAGES: Record<string, ChatMessage[]> = {
  design: [
    {
      id: "ds1",
      senderName: "Sarah (QA Lead)",
      senderAvatar: "👩‍💻",
      senderColor: "from-emerald-500 to-teal-500",
      text: "Did everyone review the Figma draft for the chat dashboard?",
      timestamp: "10:14 AM",
      isUser: false,
    },
    {
      id: "ds2",
      senderName: "Alex (Systems Dev)",
      senderAvatar: "👨‍💻",
      senderColor: "from-indigo-500 to-purple-500",
      text: "Yes, looks clean! The dark-theme glassmorphism style is sleek.",
      timestamp: "10:16 AM",
      isUser: false,
    },
  ],
  dev: [
    {
      id: "dv1",
      senderName: "Alex (Systems Dev)",
      senderAvatar: "👨‍💻",
      senderColor: "from-indigo-500 to-purple-500",
      text: "Docker compose services are up and serving local dev servers.",
      timestamp: "09:30 AM",
      isUser: false,
    },
    {
      id: "dv2",
      senderName: "John (Product)",
      senderAvatar: "💼",
      senderColor: "from-amber-500 to-orange-500",
      text: "Great progress. Is MFE host loading remote bundles successfully?",
      timestamp: "09:34 AM",
      isUser: false,
    },
  ],
  sarah: [
    {
      id: "sa1",
      senderName: "Sarah (QA Lead)",
      senderAvatar: "👩‍💻",
      senderColor: "from-emerald-500 to-teal-500",
      text: "Hi, can you verify the logout routing issue in the staging build?",
      timestamp: "Yesterday",
      isUser: false,
    },
  ],
  alex: [
    {
      id: "al1",
      senderName: "Alex (Systems Dev)",
      senderAvatar: "👨‍💻",
      senderColor: "from-indigo-500 to-purple-500",
      text: "Hey! Let's debug the module federation share loading later.",
      timestamp: "2 Days ago",
      isUser: false,
    },
  ],
  john: [
    {
      id: "jo1",
      senderName: "John (Product)",
      senderAvatar: "💼",
      senderColor: "from-amber-500 to-orange-500",
      text: "Good work on the updates, sprint retrospective is scheduled.",
      timestamp: "Yesterday",
      isUser: false,
    },
  ],
};

const ChatappDashboard = () => {
  const [rooms, setRooms] = useState<ChatRoom[]>(INITIAL_ROOMS);
  const [activeRoom, setActiveRoom] = useState<ChatRoom>(INITIAL_ROOMS[0]);
  const [messages, setMessages] = useState<Record<string, ChatMessage[]>>(INITIAL_MESSAGES);
  const [searchText, setSearchText] = useState("");
  const [categoryFilter, setCategoryFilter] = useState<"all" | "channel" | "dm">("all");
  const [inputText, setInputText] = useState("");
  const [showRightPanel, setShowRightPanel] = useState(true);
  const [callState, setCallState] = useState<"idle" | "calling" | "connected">("idle");
  const [isMuted, setIsMuted] = useState(false);
  const [callTime, setCallTime] = useState(0);

  const messagesEndRef = useRef<HTMLDivElement>(null);
  const callTimerRef = useRef<NodeJS.Timeout | null>(null);
  const wsRef = useRef<WebSocket | null>(null);

  // Auto-scroll messages
  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages, activeRoom]);

  // Fetch rooms list from the backend on mount
  useEffect(() => {
    const fetchRooms = async () => {
      try {
        const response = await fetch("http://localhost:8004/api/v1/chat/rooms");
        const resData = await response.json();
        if (resData.success && resData.data && resData.data.length > 0) {
          setRooms(resData.data);
          // Set first room as active initially
          setActiveRoom(resData.data[0]);
        }
      } catch (error) {
        console.error("Error fetching rooms from backend:", error);
      }
    };
    fetchRooms();
  }, []);

  // Fetch messages and manage WebSocket connection on activeRoom change
  useEffect(() => {
    if (!activeRoom) return;

    // Check if room ID is a valid UUID (backend vs initial hardcoded mock string)
    const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(activeRoom.id);
    
    if (isUuid) {
      // Fetch historical messages from REST API
      const fetchMessages = async () => {
        try {
          const response = await fetch(`http://localhost:8004/api/v1/chat/rooms/${activeRoom.id}/messages`);
          const resData = await response.json();
          if (resData.success && resData.data) {
            setMessages((prev) => ({
              ...prev,
              [activeRoom.id]: resData.data,
            }));
          }
        } catch (error) {
          console.error(`Error fetching messages for room ${activeRoom.id}:`, error);
        }
      };
      fetchMessages();

      // Establish live WebSocket connection
      const wsUrl = `ws://localhost:8004/api/v1/chat/ws/${activeRoom.id}`;
      const ws = new WebSocket(wsUrl);
      wsRef.current = ws;

      ws.onopen = () => {
        console.log(`WebSocket connected to room: ${activeRoom.name}`);
      };

      ws.onmessage = (event) => {
        try {
          const newMsg: ChatMessage = JSON.parse(event.data);
          setMessages((prev) => {
            const roomMsgs = prev[activeRoom.id] || [];
            // Prevent duplicate message rendering
            if (roomMsgs.some((m) => m.id === newMsg.id)) {
              return prev;
            }
            return {
              ...prev,
              [activeRoom.id]: [...roomMsgs, newMsg],
            };
          });
        } catch (err) {
          console.error("Failed to parse WebSocket message:", err);
        }
      };

      ws.onerror = (err) => {
        console.error("WebSocket error:", err);
      };

      ws.onclose = () => {
        console.log(`WebSocket disconnected from room: ${activeRoom.name}`);
      };

      return () => {
        ws.close();
        wsRef.current = null;
      };
    }
  }, [activeRoom?.id]);

  // Mark room as read when selected
  useEffect(() => {
    if (activeRoom && activeRoom.unreadCount > 0) {
      setRooms((prev) =>
        prev.map((r) => (r.id === activeRoom.id ? { ...r, unreadCount: 0 } : r))
      );
    }
  }, [activeRoom?.id]);

  // Call timer simulation
  useEffect(() => {
    if (callState === "connected") {
      callTimerRef.current = setInterval(() => {
        setCallTime((prev) => prev + 1);
      }, 1000);
    } else {
      if (callTimerRef.current) {
        clearInterval(callTimerRef.current);
      }
      setCallTime(0);
    }
    return () => {
      if (callTimerRef.current) clearInterval(callTimerRef.current);
    };
  }, [callState]);

  const handleSendMessage = (text: string) => {
    if (!text.trim() || !activeRoom) return;

    const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(activeRoom.id);

    if (isUuid && wsRef.current && wsRef.current.readyState === WebSocket.OPEN) {
      const payload = {
        senderName: "Admin",
        senderAvatar: "👤",
        senderColor: "from-indigo-600 to-indigo-700",
        text: text,
        isUser: true,
      };
      wsRef.current.send(JSON.stringify(payload));
      setInputText("");
    } else {
      // Fallback local simulation if database service is not fully updated
      const newMsg: ChatMessage = {
        id: Math.random().toString(),
        senderName: "Admin",
        senderAvatar: "👤",
        senderColor: "from-indigo-600 to-indigo-700",
        text: text,
        timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
        isUser: true,
      };

      setMessages((prev) => ({
        ...prev,
        [activeRoom.id]: [...(prev[activeRoom.id] || []), newMsg],
      }));

      setInputText("");

      // Simulate Reply after 1.5 seconds
      setTimeout(() => {
        const responses = [
          "Sounds good! Let's sync up later on this.",
          "Got it, reviewing the details right now.",
          "Understood, I am on it.",
          "Interesting. Let's discuss this during our morning standup.",
          "Can you send the links/docs for references?",
        ];
        const randomResponse = responses[Math.floor(Math.random() * responses.length)];

        const replyMsg: ChatMessage = {
          id: Math.random().toString(),
          senderName: activeRoom.type === "dm" ? activeRoom.name : "Alex (Systems Dev)",
          senderAvatar: activeRoom.type === "dm" ? activeRoom.avatar : "👨‍💻",
          senderColor: activeRoom.type === "dm" ? activeRoom.avatarColor : "from-indigo-500 to-purple-500",
          text: randomResponse,
          timestamp: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
          isUser: false,
        };

        setMessages((prev) => ({
          ...prev,
          [activeRoom.id]: [...(prev[activeRoom.id] || []), replyMsg],
        }));
      }, 1500);
    }
  };

  const handleStartCall = () => {
    setCallState("calling");
    setTimeout(() => {
      setCallState("connected");
    }, 2000);
  };

  const handleEndCall = () => {
    setCallState("idle");
  };

  const formatCallTime = (secs: number) => {
    const mins = Math.floor(secs / 60);
    const remainingSecs = secs % 60;
    return `${mins.toString().padStart(2, "0")}:${remainingSecs.toString().padStart(2, "0")}`;
  };

  // Filter rooms
  const filteredRooms = rooms.filter((room) => {
    const matchesSearch = room.name.toLowerCase().includes(searchText.toLowerCase());
    const matchesCategory = categoryFilter === "all" ? true : room.type === categoryFilter;
    return matchesSearch && matchesCategory;
  });

  const activeMessages = messages[activeRoom.id] || [];

  return (
    <div className="flex h-[calc(100vh-3.5rem)] -mx-4 -mb-4 overflow-hidden bg-[#0A0D1A] text-slate-100 font-sans relative">

      {/* 1. LEFT PANEL: CHATS LIST */}
      <aside className="w-80 bg-[#0F1324] border-r border-slate-800/80 flex flex-col h-full shrink-0">
        {/* Search Header */}
        <div className="p-4 border-b border-slate-800/60 space-y-3">
          <div className="flex justify-between items-center">
            <h1 className="text-base font-bold tracking-wide">Workspace Chat</h1>
            <button
              title="Create Room (Not supported in simulation)"
              className="p-1.5 rounded-lg bg-slate-800/60 border border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white cursor-pointer"
            >
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" />
              </svg>
            </button>
          </div>

          {/* Search Box */}
          <div className="flex items-center bg-slate-900 border border-slate-800 p-1.5 px-3 rounded-xl focus-within:border-indigo-500/60 transition-all">
            <svg className="w-4 h-4 text-slate-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              type="text"
              placeholder="Search chat or channel..."
              value={searchText}
              onChange={(e) => setSearchText(e.target.value)}
              className="bg-transparent border-0 outline-none text-xs w-full text-slate-100 placeholder-slate-500"
            />
          </div>

          {/* Category Tabs */}
          <div className="flex gap-1.5 bg-slate-950 p-1 rounded-lg">
            {(["all", "channel", "dm"] as const).map((tab) => (
              <button
                key={tab}
                onClick={() => setCategoryFilter(tab)}
                className={`flex-1 text-[10px] font-bold uppercase tracking-wider py-1.5 rounded-md cursor-pointer transition-all ${categoryFilter === tab
                  ? "bg-slate-800 text-white shadow-xs"
                  : "text-slate-400 hover:text-white"
                  }`}
              >
                {tab === "all" ? "All" : tab === "channel" ? "Channels" : "DMs"}
              </button>
            ))}
          </div>
        </div>

        {/* Chat Rooms Scroll list */}
        <div className="flex-1 overflow-y-auto p-3 space-y-1">
          {filteredRooms.map((room) => {
            const isSelected = room.id === activeRoom.id;
            const messagesForRoom = messages[room.id] || [];
            const lastMsg = messagesForRoom[messagesForRoom.length - 1];

            return (
              <button
                key={room.id}
                onClick={() => setActiveRoom(room)}
                className={`w-full flex gap-3 p-2.5 rounded-xl text-left border transition-all cursor-pointer ${isSelected
                  ? "bg-slate-800/80 border-indigo-500/40 shadow-xs"
                  : "bg-transparent border-transparent hover:bg-slate-800/30 text-slate-300 hover:text-white"
                  }`}
              >
                {/* Avatar Icon */}
                <div className="relative shrink-0 select-none">
                  <div className={`flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br ${room.avatarColor} text-lg shadow-sm`}>
                    {room.avatar}
                  </div>
                  {/* Status Indicator bubble */}
                  {room.type === "dm" && room.status && (
                    <span
                      className={`absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-[#0F1324] ${room.status === "online"
                        ? "bg-emerald-400"
                        : room.status === "idle"
                          ? "bg-amber-400"
                          : "bg-slate-500"
                        }`}
                    />
                  )}
                </div>

                {/* Details snippet */}
                <div className="flex-1 min-w-0">
                  <div className="flex justify-between items-baseline mb-0.5">
                    <span className="font-semibold text-xs truncate">
                      {room.type === "channel" ? `# ${room.name}` : room.name}
                    </span>
                    <span className="text-[9px] text-slate-500 font-normal">
                      {lastMsg ? lastMsg.timestamp : ""}
                    </span>
                  </div>
                  <p className="text-[11px] text-slate-400 truncate leading-snug">
                    {lastMsg ? lastMsg.text : "No messages yet"}
                  </p>
                </div>

                {/* Unread badge */}
                {room.unreadCount > 0 && !isSelected && (
                  <span className="self-center flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-indigo-500 text-[10px] font-bold text-white shrink-0">
                    {room.unreadCount}
                  </span>
                )}
              </button>
            );
          })}
        </div>
      </aside>

      {/* 2. CENTER PANEL: ACTIVE CHAT SCREEN */}
      <main className="flex-1 flex flex-col min-w-0 bg-[#080B15]">
        {/* Chat Window Header */}
        <header className="flex justify-between items-center h-14 px-4 bg-[#0F1424]/90 border-b border-slate-800/80 sticky top-0 z-20">
          <div className="flex items-center gap-3">
            <div className={`flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br ${activeRoom.avatarColor} text-lg shadow-sm`}>
              {activeRoom.avatar}
            </div>
            <div>
              <h2 className="text-sm font-semibold leading-tight">
                {activeRoom.type === "channel" ? `# ${activeRoom.name}` : activeRoom.name}
              </h2>
              <p className="text-[10px] text-slate-400 font-normal truncate max-w-sm">
                {activeRoom.type === "channel" ? "Group channel workspace" : "Direct messaging chat"}
              </p>
            </div>
          </div>

          {/* Quick actions (Calls/Panel toggle) */}
          <div className="flex items-center gap-2">
            <button
              onClick={handleStartCall}
              title="Start voice call (Simulation)"
              className="p-2 rounded-lg bg-slate-800/50 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white transition-all cursor-pointer"
            >
              <svg className="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
            </button>

            <button
              onClick={() => setShowRightPanel(!showRightPanel)}
              title="Toggle Information drawer"
              className={`p-2 rounded-lg border transition-all cursor-pointer ${showRightPanel
                ? "bg-indigo-600/20 border-indigo-500/50 text-indigo-300"
                : "bg-slate-800/50 border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white"
                }`}
            >
              <svg className="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </button>
          </div>
        </header>

        {/* Message Feed list */}
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          <div className="text-center py-2 select-none">
            <span className="text-[10px] bg-slate-900 border border-slate-800 text-slate-400 font-semibold px-2 py-0.5 rounded-full uppercase tracking-wider">
              Today
            </span>
          </div>

          {activeMessages.map((msg) => {
            return (
              <div key={msg.id} className={`flex gap-3 max-w-[80%] ${msg.isUser ? "ml-auto flex-row-reverse" : "mr-auto"}`}>
                {/* User icon */}
                <div className={`flex items-center justify-center w-8 h-8 rounded-lg text-sm shrink-0 shadow-sm bg-gradient-to-br ${msg.senderColor}`}>
                  {msg.senderAvatar}
                </div>

                {/* Msg text bubble */}
                <div>
                  <div className="flex items-baseline gap-2 mb-0.5 px-1 select-none">
                    <span className="text-[10px] font-bold text-slate-300">{msg.senderName}</span>
                    <span className="text-[8px] text-slate-500">{msg.timestamp}</span>
                  </div>
                  <div
                    className={`p-3 rounded-2xl shadow-md border text-xs whitespace-pre-line leading-relaxed ${msg.isUser
                      ? "bg-gradient-to-br from-indigo-600 to-indigo-700 border-indigo-500/50 text-white rounded-tr-none"
                      : "bg-slate-900 border-slate-800 text-slate-200 rounded-tl-none"
                      }`}
                  >
                    {msg.text}
                  </div>
                </div>
              </div>
            );
          })}

          <div ref={messagesEndRef} />
        </div>

        {/* Input Controls */}
        <div className="p-4 bg-[#0A0D1A] border-t border-slate-850/80 sticky bottom-0">
          <form
            onSubmit={(e) => {
              e.preventDefault();
              handleSendMessage(inputText);
            }}
            className="max-w-4xl mx-auto flex gap-2 items-center bg-slate-900/90 border border-slate-800 p-1.5 px-3 rounded-2xl focus-within:border-indigo-500/60 focus-within:shadow-xs transition-all"
          >
            {/* Attachment paperclip */}
            <button
              type="button"
              title="Attach document/image"
              className="p-2 text-slate-400 hover:text-slate-200 hover:bg-slate-800 rounded-xl transition-all cursor-pointer"
            >
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
              </svg>
            </button>

            <input
              type="text"
              placeholder={`Message ${activeRoom.type === "channel" ? `#${activeRoom.name}` : activeRoom.name}...`}
              value={inputText}
              onChange={(e) => setInputText(e.target.value)}
              className="flex-1 bg-transparent border-0 outline-none text-slate-100 text-xs py-2 placeholder-slate-500"
            />

            {/* Emoji trigger (Simulation placeholder) */}
            <button
              type="button"
              title="Select emoji"
              className="p-2 text-slate-400 hover:text-slate-200 hover:bg-slate-800 rounded-xl transition-all cursor-pointer"
            >
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </button>

            {/* Send button */}
            <button
              type="submit"
              disabled={!inputText.trim()}
              className="p-2 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 text-white transition-all disabled:opacity-30 disabled:from-slate-800 disabled:to-slate-800 disabled:text-slate-500 active:scale-95 shadow-md shadow-indigo-950/20 cursor-pointer"
            >
              <svg className="w-4 h-4 transform rotate-90" fill="currentColor" viewBox="0 0 24 24">
                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
              </svg>
            </button>
          </form>
        </div>
      </main>

      {/* 3. RIGHT PANEL: DETAILS & SHARESPACE */}
      {showRightPanel && (
        <aside className="w-64 bg-[#0F1324] border-l border-slate-800/80 flex flex-col h-full shrink-0 overflow-y-auto">
          {/* Header */}
          <div className="p-4 border-b border-slate-800/60 flex items-center justify-between bg-[#0c101d] select-none">
            <h3 className="font-semibold text-xs tracking-wide">Information</h3>
            <span className="text-[9px] bg-emerald-500/20 text-emerald-400 font-bold px-1.5 py-0.5 rounded-md uppercase">
              Detail
            </span>
          </div>

          <div className="p-4 space-y-6">
            {/* Info card (large avatar / name) */}
            <div className="text-center space-y-2">
              <div className={`inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br ${activeRoom.avatarColor} text-3xl shadow-md select-none`}>
                {activeRoom.avatar}
              </div>
              <div>
                <h4 className="font-bold text-sm">
                  {activeRoom.type === "channel" ? `#${activeRoom.name}` : activeRoom.name}
                </h4>
                <p className="text-[10px] text-slate-400">
                  {activeRoom.type === "channel" ? "Public channel space" : "Direct Message"}
                </p>
              </div>
            </div>

            {/* Room description */}
            <div className="space-y-1.5">
              <label className="text-[9px] font-bold text-slate-500 uppercase tracking-wider block">Description</label>
              <p className="text-xs text-slate-300 leading-relaxed font-normal bg-slate-900 border border-slate-800 p-3 rounded-xl">
                {activeRoom.description}
              </p>
            </div>

            <hr className="border-slate-800" />

            {/* Shared files section */}
            <div className="space-y-2">
              <label className="text-[9px] font-bold text-slate-500 uppercase tracking-wider block select-none">Shared Documents</label>
              <div className="space-y-1.5">
                {[
                  { name: "System_Architecture_v1.pdf", size: "2.4 MB" },
                  { name: "Design_Sprint_Specs.fig", size: "12.8 MB" },
                  { name: "Test_Case_Automation_Plan.xlsx", size: "480 KB" },
                ].map((file, i) => (
                  <div key={i} className="flex items-center justify-between p-2 bg-slate-900 border border-slate-800 rounded-xl hover:border-slate-700 transition-all select-none">
                    <div className="flex items-center gap-2 min-w-0">
                      <span className="text-sm shrink-0">📄</span>
                      <div className="min-w-0">
                        <div className="text-[10px] font-semibold truncate text-slate-300">{file.name}</div>
                        <div className="text-[8px] text-slate-500">{file.size}</div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </aside>
      )}

      {/* 4. MOCK CALLING INTERFACE OVERLAY */}
      {callState !== "idle" && (
        <div className="absolute inset-0 z-50 bg-[#060812]/95 backdrop-blur-md flex flex-col items-center justify-center space-y-6">
          {/* Pulsing Avatar */}
          <div className="relative">
            <span className="absolute inset-0 rounded-3xl bg-indigo-500/25 animate-ping" />
            <div className={`relative flex items-center justify-center w-24 h-24 rounded-3xl bg-gradient-to-br ${activeRoom.avatarColor} text-5xl shadow-xl border border-indigo-400/20 select-none`}>
              {activeRoom.avatar}
            </div>
          </div>

          {/* Call Metadata */}
          <div className="text-center space-y-1">
            <h3 className="text-lg font-bold">
              {activeRoom.type === "channel" ? `Calling Channel: #${activeRoom.name}` : activeRoom.name}
            </h3>
            <p className="text-xs text-slate-400 font-normal">
              {callState === "calling" ? "Calling..." : `Call Connected — ${formatCallTime(callTime)}`}
            </p>
          </div>

          {/* Action buttons */}
          <div className="flex gap-4">
            {/* Mute Button */}
            <button
              onClick={() => setIsMuted(!isMuted)}
              className={`p-3 rounded-full border transition-all cursor-pointer ${isMuted
                ? "bg-amber-600/20 border-amber-500/50 text-amber-300"
                : "bg-slate-800 border-slate-750 text-slate-300 hover:text-white"
                }`}
            >
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
              </svg>
            </button>

            {/* End Call Button */}
            <button
              onClick={handleEndCall}
              className="p-3 bg-rose-600 hover:bg-rose-500 text-white rounded-full border border-rose-500 shadow-lg shadow-rose-950/20 cursor-pointer"
            >
              <svg className="w-5 h-5 transform rotate-135" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default ChatappDashboard;
