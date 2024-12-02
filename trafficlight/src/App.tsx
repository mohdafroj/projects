import { useEffect, useState } from "react";
import "./App.css";

function App() {
  const [light, setLight] = useState<number>(0);
  useEffect(() => {
    let timeoutId: number = 0;
    timeoutId = setTimeout(
      () => {
        setLight((prev) => (prev + 1) % 3);
      },
      light === 0 ? 4000 : light === 1 ? 3000 : 5000
    );
    return () => {
      clearTimeout(timeoutId);
    };
  }, [light]);
  return (
    <div className="traffic-light">
      <div className={`light red ${light == 0 ? "active" : ""}`}></div>
      <div className={`light yellow ${light == 1 ? "active" : ""}`}></div>
      <div className={`light green ${light == 2 ? "active" : ""}`}></div>
    </div>
  );
}

export default App;
