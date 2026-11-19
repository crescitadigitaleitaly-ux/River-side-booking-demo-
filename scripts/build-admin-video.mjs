// Builds the admin-panel walkthrough: "your team can update the website" (stills timed to the voice-over).
// Same style as the case study: no zoom/pan, 0.3 s fades, 1920x1080, face bubble, ducked music, subtitles, -16 LUFS.
// Screens are exact 1920x1080 captures (scripts/admin-shots.mjs), shown pixel-for-pixel so the text stays sharp.
// Needs: video/audio/admin-voice.mp3 + admin-words.json (transcribe.py with video-src/admin-markers.json, then align_subs.py).
import { execFileSync, spawnSync } from "node:child_process";
import fs from "node:fs";

const ROOT = "C:/Users/MouzzamChanna/Desktop/projects/riverside-booking-case-study";
const V = `${ROOT}/video`, A = `${V}/audio`, AD = `${V}/admin`, SH = `${AD}/shots`, TMP = `${AD}/tmp`;
fs.mkdirSync(TMP, { recursive: true });
const OUT = `${V}/riverside-admin-panel.mp4`;
const font = "C\\:/Windows/Fonts/segoeuib.ttf";
const ff = (args, cwd) => execFileSync("ffmpeg", ["-hide_banner", "-loglevel", "error", "-y", ...args], { stdio: "inherit", cwd });
const probe = (f) => Number(execFileSync("ffprobe", ["-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", f]).toString());
const enc = ["-r", "30", "-c:v", "libx264", "-preset", "slow", "-crf", "16", "-tune", "stillimage", "-pix_fmt", "yuv420p", "-an"];
const esc = (t) => t.replace(/\\/g, "\\\\").replace(/:/g, "\\:").replace(/'/g, "\u2019").replace(/%/g, "\\%");

const LEAD = 0.6, TAIL = 2.6;
const W = JSON.parse(fs.readFileSync(`${A}/admin-words.json`, "utf8"));
const S = W.sections;
const order = ["menu", "contact", "hours", "notice", "error", "saved", "site", "doctors", "doctorhours", "closures", "roles", "outro"];
for (const k of order) if (S[k] == null) throw new Error(`marker "${k}" missing`);
const END = probe(`${A}/admin-voice.mp3`) + LEAD + TAIL;
const v = (t) => (t === 0 ? 0 : t + LEAD);
const card = (name) => ({ kind: "card", file: `${AD}/${name}.png` });
const screen = (name, step) => ({ kind: "screen", file: `${SH}/${name}.png`, step });

const sections = [
	[0, S.menu, [card("admin-title")]],
	[S.menu, S.contact, [screen("01-menu", "1  Everything under Reception")]],
	[S.contact, S.hours, [screen("02-contact", "2  Phone, email, address")]],
	[S.hours, S.notice, [screen("03-hours", "3  Opening hours")]],
	[S.notice, S.error, [screen("04-notice", "4  Notice bar with dates")]],
	[S.error, S.saved, [screen("05-error", "5  Mistakes are refused")]],
	[S.saved, S.site, [screen("06-saved", "6  Saved")]],
	[S.site, S.doctors, [screen("07-site", "7  Live on the website"), screen("08-footer", "7  Hours updated everywhere")]],
	[S.doctors, S.doctorhours, [screen("09-doctors", "8  Doctors at a glance")]],
	[S.doctorhours, S.closures, [screen("10-doctor-hours", "9  A doctor's working hours")]],
	[S.closures, S.roles, [screen("11-closures", "10  Holidays and leave")]],
	[S.roles, S.outro, [screen("12-reception-blocked", "11  Reception can't change settings")]],
	[S.outro, null, [card("admin-outro")]],
];

const parts = [];
let idx = 0;
for (const [a, b, items, weights] of sections) {
	const start = v(a), end = b == null ? END : v(b);
	const w = weights ?? items.map(() => 1), wsum = w.reduce((x, y) => x + y, 0);
	for (const [k, it] of items.entries()) {
		const dur = +(((end - start) * w[k]) / wsum).toFixed(3);
		const out = `${TMP}/${String(idx++).padStart(2, "0")}.mp4`;
		const fade = "fade=t=in:st=0:d=0.3";
		if (it.kind === "card") {
			ff(["-loop", "1", "-framerate", "30", "-t", String(dur), "-i", it.file, "-vf", `scale=1920:1080:flags=lanczos,${fade}`, ...enc, out]);
		} else if (it.kind === "screen") {
			// Exact-size still; a small step label sits inside the dark admin bar (top-right), so nothing is covered.
			const tw = Math.round(it.step.length * 14.5 + 40);
			ff(["-loop", "1", "-framerate", "30", "-t", String(dur), "-i", it.file, "-vf",
				`scale=1920:1080:flags=lanczos,drawbox=x=${1920 - tw - 24}:y=6:w=${tw}:h=38:color=0xF59E0B@0.95:t=fill,` +
				`drawtext=fontfile='${font}':text='${esc(it.step)}':fontcolor=0x111827:fontsize=26:x=${1920 - tw - 24 + 20}:y=12,${fade}`,
				...enc, out]);
		} else if (it.kind === "shot") {
			ff(["-loop", "1", "-framerate", "30", "-t", String(dur), "-i", it.file, "-filter_complex",
				`color=c=0x0B1D22:s=1920x1080:r=30:d=${dur}[bg];` +
				`[0:v]scale='min(1800,iw*1.4)':'min(800,ih*1.4)':force_original_aspect_ratio=decrease:flags=lanczos,scale=trunc(iw/2)*2:trunc(ih/2)*2[im];` +
				`[bg][im]overlay=(W-w)/2:130+(800-h)/2:shortest=1,` +
				`drawtext=fontfile='${font}':text='${esc(it.title)}':fontcolor=white:fontsize=46:x=60:y=40,` +
				`drawtext=fontfile='${font}':text='${it.label}':fontcolor=0x2DD4BF:fontsize=34:x=w-tw-60:y=48,${fade}`,
				...enc, out]);
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
fs.writeFileSync(`${V}/riverside-admin-panel.en.srt`, cues.map((c, i) => `${i + 1}\n${ts(c.s)} --> ${ts(endOf(c, i))}\n${c.t}\n`).join("\n"));
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
ff(["-i", `${A}/admin-voice.mp3`, "-stream_loop", "-1", "-i", `${A}/music.mp3`, "-filter_complex",
	`[1:a]atrim=0:${END.toFixed(2)},asetpts=PTS-STARTPTS,volume=-22dB,afade=t=in:st=0:d=1.5,afade=t=out:st=${(END - 3).toFixed(2)}:d=3[m];` +
	`[0:a]aresample=44100,adelay=${LEAD * 1000}|${LEAD * 1000},apad=whole_dur=${END.toFixed(2)},asplit=2[vo][key];` +
	`[m][key]sidechaincompress=threshold=0.015:ratio=8:attack=25:release=600[md];[vo][md]amix=inputs=2:normalize=0:duration=first`,
	"-ac", "2", `${TMP}/mix.wav`]);
const r = spawnSync("ffmpeg", ["-hide_banner", "-i", `${TMP}/mix.wav`, "-af", "loudnorm=I=-16:TP=-1.5:LRA=11:print_format=json", "-f", "null", "-"], { encoding: "utf8" }).stderr;
const L = JSON.parse(r.slice(r.lastIndexOf("{"), r.lastIndexOf("}") + 1));
ff(["-i", `${TMP}/mix.wav`, "-af", `loudnorm=I=-16:TP=-1.5:LRA=11:measured_I=${L.input_i}:measured_TP=${L.input_tp}:measured_LRA=${L.input_lra}:measured_thresh=${L.input_thresh}:offset=${L.target_offset}:linear=true,aresample=48000`, "-ac", "2", `${TMP}/mix16.wav`]);
ff(["-i", `${TMP}/picture-final.mp4`, "-i", `${TMP}/mix16.wav`, "-map", "0:v", "-map", "1:a", "-c:v", "copy", "-c:a", "aac", "-b:a", "192k", "-shortest", "-movflags", "+faststart", OUT]);
console.log("FINAL", OUT, probe(OUT).toFixed(1), "s");
