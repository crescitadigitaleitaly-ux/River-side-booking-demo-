// Builds the Riverside case-study video: overview + problem → solution, timed to the narration.
// Style (approved on earlier videos): no zoom/pan, 0.3 s fades, 1920x1080, round face bubble bottom-right,
// quiet ducked music, burned-in English subtitles, -16 LUFS. Contact details are masked in the recordings.
// Needs: video/audio/voice-full.mp3 + words.json (scripts/transcribe.py + align_subs.py), video/rec/*.webm, video/cards/*.png
import { execFileSync, spawnSync } from "node:child_process";
import fs from "node:fs";

const ROOT = "C:/Users/MouzzamChanna/Desktop/projects/riverside-booking-case-study";
const V = `${ROOT}/video`, A = `${V}/audio`, REC = `${V}/rec`, CARDS = `${V}/cards`, TMP = `${V}/tmp`;
fs.mkdirSync(TMP, { recursive: true });
const OUT = `${V}/riverside-booking-case-study.mp4`;
const font = "C\\:/Windows/Fonts/segoeuib.ttf";
const ff = (args, cwd) => execFileSync("ffmpeg", ["-hide_banner", "-loglevel", "error", "-y", ...args], { stdio: "inherit", cwd });
const probe = (f) => Number(execFileSync("ffprobe", ["-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", f]).toString());
const enc = ["-r", "30", "-c:v", "libx264", "-preset", "slow", "-crf", "18", "-pix_fmt", "yuv420p", "-an"];
const esc = (t) => t.replace(/\\/g, "\\\\").replace(/:/g, "\\:").replace(/'/g, "\u2019").replace(/%/g, "\\%");

const LEAD = 0.6, TAIL = 2.6;
const W = JSON.parse(fs.readFileSync(`${A}/words.json`, "utf8"));
const S = W.sections;
const order = ["functions", "tech", "value", "problem", "solution", "reception", "conflict", "email", "patient", "tests", "outro"];
for (const k of order) if (S[k] == null) throw new Error(`marker "${k}" missing`);
const END = probe(`${A}/voice-full.mp3`) + LEAD + TAIL;
const v = (t) => (t === 0 ? 0 : t + LEAD);

const card = (name) => ({ kind: "card", file: `${CARDS}/${name}.png` });
// Recorded clip window [from, to] in seconds; max speed-up; caption; "before"/"after" colour.
const clip = (name, from, to, caption, tone = "after", max = 1.35) => ({ kind: "clip", file: `${REC}/${name}.webm`, from, to, caption, tone, max });

const sections = [
	[0, S.functions, [card("title")]],
	[S.functions, S.tech, [clip("tour", 0.7, 13.3, "The clinic website", "after", 1.5)]],
	[S.tech, S.value, [card("tech")]],
	[S.value, S.problem, [card("value")]],
	[S.problem, S.solution, [clip("problem", 1.0, 11.1, "Before: the old form (live site)", "before")]],
	[S.solution, S.reception, [clip("solution", 1.2, 15.9, "After: the new booking form")]],
	[S.reception, S.conflict, [clip("reception", 2.4, 13.2, "Reception dashboard: one-click confirm", "after", 1.6)]],
	[S.conflict, S.email, [clip("conflict", 9.2, 15.6, "Same time, second confirmation: refused")]],
	[S.email, S.patient, [clip("outbox", 9.0, 19.5, "Test outbox: no real emails are sent")]],
	[S.patient, S.tests, [clip("patient", 15.5, 22.0, "Patient's secure link: cancel")]],
	[S.tests, S.outro, [card("tests")]],
	[S.outro, null, [card("outro")]],
];

const parts = [];
let idx = 0;
for (const [a, b, items] of sections) {
	const start = v(a), end = b == null ? END : v(b);
	const dur = +((end - start) / items.length).toFixed(3);
	for (const it of items) {
		const out = `${TMP}/${String(idx++).padStart(2, "0")}.mp4`;
		const fade = "fade=t=in:st=0:d=0.3";
		if (it.kind === "card") {
			ff(["-loop", "1", "-framerate", "30", "-t", String(dur), "-i", it.file, "-vf", `scale=1920:1080:flags=lanczos,${fade}`, ...enc, out]);
		} else {
			// Fit the clip: speed up at most `max`; if still too long keep the start (trim the end);
			// if too short, hold the last frame. Caption pill top-left (red = before, teal = after).
			const len = it.to - it.from;
			const speed = Math.min(it.max, Math.max(1, len / dur));
			const pill = it.tone === "before" ? "0xB91C1C@0.92" : "0x0F766E@0.92";
			const cap = `drawbox=x=40:y=36:w=${Math.round(it.caption.length * 21.5 + 56)}:h=70:color=${pill}:t=fill,drawtext=fontfile='${font}':text='${esc(it.caption)}':fontcolor=white:fontsize=38:x=68:y=51`;
			ff(["-ss", String(it.from), "-t", String(len), "-i", it.file, "-vf",
				`setpts=(PTS-STARTPTS)/${speed.toFixed(4)},fps=30,scale=1920:1080:flags=lanczos,tpad=stop_mode=clone:stop_duration=${Math.max(0, dur - len / speed + 0.2).toFixed(2)},trim=0:${dur},setpts=PTS-STARTPTS,${cap},${fade}`,
				...enc, out]);
			console.log(`${it.file.split("/").pop()}: ${speed.toFixed(2)}x, ${(len / speed).toFixed(1)} s into ${dur.toFixed(1)} s${len / speed > dur + 0.05 ? " (end trimmed)" : ""}`);
		}
		parts.push(out);
	}
}
fs.writeFileSync(`${TMP}/list.txt`, parts.map((p) => `file '${p}'`).join("\n"));
ff(["-f", "concat", "-safe", "0", "-i", `${TMP}/list.txt`, "-c", "copy", `${TMP}/picture.mp4`]);
console.log("picture", parts.length, "parts, target", END.toFixed(2), "s");

// Subtitles from the exact script text, timed to the voice (short lines, max ~42 characters / 3.2 s).
const words = (W.subs ?? W.words).map((x) => ({ ...x, s: x.s + LEAD, e: x.e + LEAD }));
const cues = [];
let cur = [];
const flush = () => { if (cur.length) cues.push({ s: cur[0].s, e: cur[cur.length - 1].e, t: cur.map((x) => x.w).join(" ") }); cur = []; };
for (const w of words) {
	const text = [...cur, w].map((x) => x.w).join(" ");
	if (cur.length && (text.length > 42 || w.e - cur[0].s > 3.2)) flush();
	cur.push(w);
	if (/[.!?:]$/.test(w.w)) flush();
}
flush();
const ts = (x) => { const ms = Math.round(x * 1000); return `${String(Math.floor(ms / 3600000)).padStart(2, "0")}:${String(Math.floor(ms / 60000) % 60).padStart(2, "0")}:${String(Math.floor(ms / 1000) % 60).padStart(2, "0")},${String(ms % 1000).padStart(3, "0")}`; };
const endOf = (c, i) => Math.min(c.e + 0.25, i + 1 < cues.length ? cues[i + 1].s : c.e + 0.25);
fs.writeFileSync(`${V}/riverside-booking-case-study.en.srt`, cues.map((c, i) => `${i + 1}\n${ts(c.s)} --> ${ts(endOf(c, i))}\n${c.t}\n`).join("\n"));
const assT = (x) => { const cs = Math.round(x * 100); return `${Math.floor(cs / 360000)}:${String(Math.floor(cs / 6000) % 60).padStart(2, "0")}:${String(Math.floor(cs / 100) % 60).padStart(2, "0")}.${String(cs % 100).padStart(2, "0")}`; };
fs.writeFileSync(`${TMP}/subs.ass`, ["[Script Info]", "ScriptType: v4.00+", "PlayResX: 1920", "PlayResY: 1080", "WrapStyle: 0", "", "[V4+ Styles]",
	"Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding",
	"Style: Sub,Segoe UI Semibold,46,&H00FFFFFF,&H00FFFFFF,&H96000000,&H96000000,0,0,0,0,100,100,0,0,3,14,0,2,330,330,58,1",
	"", "[Events]", "Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text",
	...cues.map((c, i) => `Dialogue: 0,${assT(c.s)},${assT(endOf(c, i))},Sub,,0,0,0,,${c.t}`)].join("\n"));
ff(["-i", "picture.mp4", "-loop", "1", "-i", `${A}/face-bubble.png`, "-filter_complex",
	`[1:v]scale=225:225:flags=lanczos,format=rgba,fade=t=in:st=0.8:d=0.6:alpha=1[b];[0:v][b]overlay=W-w-40:H-h-34:shortest=1,subtitles=subs.ass`,
	...enc, "picture-final.mp4"], TMP);

// Audio: voice + quiet looping music ducked under the voice, two-pass loudness to -16 LUFS.
ff(["-i", `${A}/voice-full.mp3`, "-stream_loop", "-1", "-i", `${A}/music.mp3`, "-filter_complex",
	`[1:a]atrim=0:${END.toFixed(2)},asetpts=PTS-STARTPTS,volume=-22dB,afade=t=in:st=0:d=1.5,afade=t=out:st=${(END - 3).toFixed(2)}:d=3[m];` +
	`[0:a]aresample=44100,adelay=${LEAD * 1000}|${LEAD * 1000},apad=whole_dur=${END.toFixed(2)},asplit=2[vo][key];` +
	`[m][key]sidechaincompress=threshold=0.015:ratio=8:attack=25:release=600[md];[vo][md]amix=inputs=2:normalize=0:duration=first`,
	"-ac", "2", `${TMP}/mix.wav`]);
const r = spawnSync("ffmpeg", ["-hide_banner", "-i", `${TMP}/mix.wav`, "-af", "loudnorm=I=-16:TP=-1.5:LRA=11:print_format=json", "-f", "null", "-"], { encoding: "utf8" }).stderr;
const L = JSON.parse(r.slice(r.lastIndexOf("{"), r.lastIndexOf("}") + 1));
ff(["-i", `${TMP}/mix.wav`, "-af", `loudnorm=I=-16:TP=-1.5:LRA=11:measured_I=${L.input_i}:measured_TP=${L.input_tp}:measured_LRA=${L.input_lra}:measured_thresh=${L.input_thresh}:offset=${L.target_offset}:linear=true,aresample=48000`, "-ac", "2", `${TMP}/mix16.wav`]);
ff(["-i", `${TMP}/picture-final.mp4`, "-i", `${TMP}/mix16.wav`, "-map", "0:v", "-map", "1:a", "-c:v", "copy", "-c:a", "aac", "-b:a", "192k", "-shortest", "-movflags", "+faststart", OUT]);
console.log("FINAL", OUT, probe(OUT).toFixed(1), "s");
