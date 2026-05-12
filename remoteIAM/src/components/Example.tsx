import { ChangeEvent, useRef, useState } from "react";

const Example = () => {
  const [counter, setCounter] = useState<number>(0);
  const [counter1, setCounter1] = useState<string>("");
  const [counter2, setCounter2] = useState<Object>({ a: 0 });
  let inputRef = useRef<HTMLInputElement | null>(null);
  const setValue = (e: ChangeEvent<HTMLInputElement>) =>
    setCounter(Number(e.target.value));
  const setValue1 = (e: ChangeEvent<HTMLInputElement>) =>
    setCounter1(e.target.value);
  const increment = () => {
    setCounter((prev) => prev + 1);
    setCounter2((prev) => {
      return { ...prev, a: counter };
    });
    console.log(inputRef.current);
  };
  return (
    <>
      <form>
        {JSON.stringify(counter2)}
        <input type="text" name="name1" value={counter} onChange={setValue} />
        <input
          type="text"
          name="name2"
          value={counter1}
          ref={inputRef}
          onChange={setValue1}
        />
        <button type="button" onClick={increment}>
          Click
        </button>
      </form>
    </>
  );
};

export default Example;
