import { useEffect, useRef, useState } from "react";
import "./App.css";
import Login from "./pages/Login";
import Test from "./pages/Test";

function App() {
  const [count, setCount] = useState(0);
  const [data, setData] = useState([1, 2, 3, 4, 5]);

  return (
    <div>
      <ul>
        {data.map((item, index) => (
          <li key={index}>{item}</li>
        ))}
      </ul>
    </div>
  );
}

export default App;


