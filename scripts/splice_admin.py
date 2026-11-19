"""Splice the admin-panel walkthrough voice into the main case-study narration, before "I tested everything".
Writes video/audio/voice-combined.mp3 + words-combined.json (main sections, admin sections as a_*, shifted subs).
The admin take's own call to action is left out (the main video already ends with one)."""
import json, subprocess
A = "video/audio/"
main = json.load(open(A + "words-final.json", encoding="utf8"))
adm = json.load(open(A + "admin-words.json", encoding="utf8"))
CUT = 88.30                      # between "...for others." (88.04) and "I tested" (88.58)
ADM_END = adm["sections"]["outro"] - 0.23   # after "Only the manager can."
GAP = 0.35
B0 = CUT + GAP                   # admin starts here
R0 = B0 + ADM_END + GAP          # main resumes here
shift = R0 - CUT
subprocess.run(["ffmpeg", "-hide_banner", "-loglevel", "error", "-y", "-i", A + "voice-final.mp3", "-i", A + "admin-voice.mp3", "-filter_complex",
    f"[0:a]aresample=44100,atrim=0:{CUT},afade=t=out:st={CUT-0.05}:d=0.05,apad=pad_dur={GAP}[a];"
    f"[1:a]aresample=44100,atrim=0:{ADM_END},afade=t=out:st={ADM_END-0.08}:d=0.08,apad=pad_dur={GAP}[b];"
    f"[0:a]aresample=44100,atrim=start={CUT},asetpts=PTS-STARTPTS[c];[a][b][c]concat=n=3:v=0:a=1",
    "-ac", "1", "-b:a", "192k", A + "voice-combined.mp3"], check=True)
secs = {k: (v if v < CUT else v + shift) for k, v in main["sections"].items()}
secs.update({"a_intro": B0, **{"a_" + k: v + B0 for k, v in adm["sections"].items() if k != "outro"}})
subs = [x for x in main["subs"] if x["s"] < CUT] + [{**x, "s": round(x["s"] + B0, 2), "e": round(x["e"] + B0, 2)} for x in adm["subs"] if x["s"] < ADM_END] \
     + [{**x, "s": round(x["s"] + shift, 2), "e": round(x["e"] + shift, 2)} for x in main["subs"] if x["s"] >= CUT]
json.dump({"sections": secs, "subs": subs, "words": subs}, open(A + "words-combined.json", "w", encoding="utf8"), indent=1)
print(json.dumps({k: round(v, 2) for k, v in sorted(secs.items(), key=lambda i: i[1])}))
