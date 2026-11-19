"""Transcribe the voice-over with word timestamps and find where each script section starts."""
import json
import sys
from faster_whisper import WhisperModel

audio = sys.argv[1]
out = sys.argv[2]
model = WhisperModel("small.en", device="cpu", compute_type="int8")
import subprocess
import numpy as np
# Decode with ffmpeg (avoids the PyAV version mismatch on this laptop): 16 kHz mono float32.
pcm = subprocess.run(["ffmpeg", "-v", "error", "-i", audio, "-f", "s16le", "-ac", "1", "-ar", "16000", "-"], capture_output=True, check=True).stdout
samples = np.frombuffer(pcm, np.int16).astype(np.float32) / 32768.0
segments, _ = model.transcribe(samples, word_timestamps=True, vad_filter=False)
words = []
text = []
for seg in segments:
    text.append(seg.text)
    for w in seg.words:
        words.append({"w": w.word.strip(), "s": round(w.start, 2), "e": round(w.end, 2)})

markers = {
    "intro": ["hi"],
    "functions": ["patients", "can", "explore"],
    "tech": ["i", "built", "it"],
    "value": ["for", "a", "clinic"],
    "problem": ["the", "problem"],
    "solution": ["now", "each", "service"],
    "reception": ["reception", "sees"],
    "conflict": ["if", "two", "people"],
    "email": ["the", "patient", "gets"],
    "patient": ["with", "a", "secure"],
    "tests": ["i", "tested", "everything"],
    "outro": ["if", "your", "clinic"],
}
norm = lambda s: s.lower().strip(".,:!?")
lw = [norm(w["w"]) for w in words]
sections = {}
for key, seq in markers.items():
    seqn = [norm(x) for x in seq]
    for i in range(len(lw) - len(seqn) + 1):
        if lw[i:i + len(seqn)] == seqn and (key not in sections):
            sections[key] = words[i]["s"]
            break
json.dump({"text": " ".join(text).strip(), "words": words, "sections": sections, "end": words[-1]["e"] if words else 0}, open(out, "w", encoding="utf8"), indent=1)
print(json.dumps(sections))
print("END", words[-1]["e"] if words else None)
print(" ".join(text).strip())
