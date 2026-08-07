import os
import asyncio
from typing import AsyncGenerator
from openai import AsyncOpenAI
from app.schemas.chat import ChatMessage

class AIService:
    def __init__(self):
        # Read API key from environment
        self.api_key = os.getenv("OPENAI_API_KEY", "")
        self.has_real_key = bool(self.api_key and not self.api_key.startswith("placeholder") and len(self.api_key) > 10)
        if self.has_real_key:
            self.client = AsyncOpenAI(api_key=self.api_key)

    async def generate_stream(
        self,
        prompt: str,
        system_prompt: str,
        model: str,
        temperature: float,
        max_tokens: int,
        history: list[ChatMessage]
    ) -> AsyncGenerator[str, None]:
        if self.has_real_key:
            # Map user-friendly model names to valid OpenAI model IDs
            model_mapping = {
                "Gemini 1.5 Pro": "gpt-4o",
                "Claude 3.5 Sonnet": "gpt-4o",
                "GPT-4o": "gpt-4o"
            }
            mapped_model = model_mapping.get(model, model)

            # Actual OpenAI stream
            try:
                messages = [{"role": "system", "content": system_prompt}]
                for msg in history:
                    messages.append({"role": msg.role, "content": msg.content})
                messages.append({"role": "user", "content": prompt})

                completion = await self.client.chat.completions.create(
                    model=mapped_model,
                    messages=messages,
                    temperature=temperature,
                    max_tokens=max_tokens,
                    stream=True
                )

                async for chunk in completion:
                    token = chunk.choices[0].delta.content
                    if token:
                        yield f"data: {token}\n\n"
            except Exception as e:
                yield f"data: [ERROR: {str(e)}]\n\n"
        else:
            # Fallback high-fidelity simulation stream
            # Simulates the personas Aether, Nyx, and Iris directly!
            # It sends back text in small chunk delays to mimic real LLMs.
            response_text = ""
            # Detect persona from system prompt or model
            if "Aether" in system_prompt or "general" in system_prompt.lower():
                response_text = f"Hello! This is a dynamic streaming response from **Aether** (simulated since no `OPENAI_API_KEY` was found in ToolsService environment).\n\nYou asked: \"{prompt}\"\n\nI can help you analyze tasks, plan itineraries, or brainstorm answers. Try adding a valid `OPENAI_API_KEY` to the `.env` file of ToolsService to connect me to real API endpoints!"
            elif "Nyx" in system_prompt or "software architect" in system_prompt.lower():
                response_text = f"""```typescript
// Nyx Code Assistant (Simulated Stream)
interface CodeSolution {{
  language: string;
  code: string;
  isValid: boolean;
}}

export const solution = (): CodeSolution => {{
  return {{
    language: "typescript",
    code: "console.log('Query: {prompt}');",
    isValid: true
  }};
}};
```
I processed your system requirements with a strict temperature coefficient of `{temperature}`. Provide a valid OpenAI key to execute live model compilations."""
            else:
                response_text = f"Ah, what a spark of creative expression! Under the creative aura of **Iris**, here is an expressive prose inspired by your query:\n\n*The morning sun peeked through the digital grid, casting warm, pixelated rays across the workspace. A single prompt, like a pebble dropped in a deep lake, sent ripples of code outward into the server networks...*\n\n(Simulated stream for model {model} at creativity temperature {temperature})"

            # Yield words/tokens over simulated time
            words = response_text.split(" ")
            for i, word in enumerate(words):
                val = word + (" " if i < len(words) - 1 else "")
                yield f"data: {val}\n\n"
                await asyncio.sleep(0.04) # ~40ms delay for natural output speed
