"""Replace speech-recognition words with the exact script text, keeping the recognised timings.
Usage: python align_subs.py words.json subtitle-text.txt  (adds "subs" to words.json)
Equal words copy their times; changed blocks (e.g. "CO" vs "SEO", "97" vs "ninety-seven") share the time span."""
import difflib
import json
import re
import sys

words_file, text_file = sys.argv[1], sys.argv[2]
data = json.load(open(words_file, encoding="utf8"))
heard = data["words"]
script = open(text_file, encoding="utf8").read().split()
norm = lambda s: re.sub(r"[^a-z0-9%]", "", s.lower())
a = [norm(w["w"]) for w in heard]
b = [norm(w) for w in script]
out = []
for op, i1, i2, j1, j2 in difflib.SequenceMatcher(None, a, b, autojunk=False).get_opcodes():
    if op == "equal":
        for k in range(j2 - j1):
            out.append({"w": script[j1 + k], "s": heard[i1 + k]["s"], "e": heard[i1 + k]["e"]})
    elif j2 > j1:
        # Time span of the heard words in this block, or the gap between neighbours if nothing was heard.
        if i2 > i1:
            s, e = heard[i1]["s"], heard[i2 - 1]["e"]
        else:
            s = heard[i1 - 1]["e"] if i1 > 0 else 0.0
            e = heard[i1]["s"] if i1 < len(heard) else s + 0.4
        n = j2 - j1
        for k in range(n):
            out.append({"w": script[j1 + k], "s": round(s + (e - s) * k / n, 2), "e": round(s + (e - s) * (k + 1) / n, 2)})
data["subs"] = out
json.dump(data, open(words_file, "w", encoding="utf8"), indent=1)
print("aligned", len(out), "script words to", len(heard), "heard words")
