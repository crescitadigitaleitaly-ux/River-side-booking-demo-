"""Platform-safe copies of the quality screenshots: blur website addresses, IPs and account avatars.
Usage: python scripts/blur-quality.py   (reads evidence/quality/{before,after}, writes evidence/quality/safe/)
Regions are for the 1280-px-wide Chrome captures (x0, y0, x1, y1)."""
import os, subprocess

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, 'evidence', 'quality')
OUT = os.path.join(SRC, 'safe')
os.makedirs(OUT, exist_ok=True)
REGIONS = {
    'security-headers': [(470, 140, 920, 192)],
    'security-observatory': [(230, 292, 560, 330)],
    'security-ssllabs': [(262, 136, 620, 172), (180, 315, 560, 500), (390, 108, 560, 130)],
    'schema-validator-home': [(45, 110, 640, 145), (0, 160, 640, 560)],
    'w3c-home': [(36, 116, 820, 142), (55, 240, 1175, 266)],
    'w3c-book': [(36, 116, 860, 142), (55, 240, 1175, 266)],
    'rich-results-patients': [(65, 78, 520, 114), (1218, 8, 1272, 56)],
    'rich-results-patients-top': [(65, 78, 520, 114), (1218, 8, 1272, 56)],
    'psi-home-mobile': [], 'psi-home-desktop': [], 'psi-book-mobile': [], 'psi-book-desktop': [],
}
for phase in ('before', 'after'):
    for name, regs in REGIONS.items():
        src = os.path.join(SRC, phase, name + '.jpg')
        if not os.path.exists(src):
            continue
        dst = os.path.join(OUT, f'{phase}-{name}.jpg')
        if not regs:
            subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', src, '-q:v', '2', dst], check=True)
            continue
        w, h = map(int, subprocess.run(['ffprobe', '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=width,height', '-of', 'csv=p=0', src], capture_output=True, text=True).stdout.strip().split(','))
        chain, last = [], '0:v'
        for i, (x0, y0, x1, y1) in enumerate(regs):
            x1, y1 = min(x1, w), min(y1, h)
            chain.append(f'[{last}]split[b{i}][c{i}];[c{i}]crop={x1-x0}:{y1-y0}:{x0}:{y0},boxblur=luma_radius=10:luma_power=3:chroma_radius=4:chroma_power=2[k{i}];[b{i}][k{i}]overlay={x0}:{y0}[o{i}]')
            last = f'o{i}'
        subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', src, '-filter_complex', ';'.join(chain), '-map', f'[{last}]', '-q:v', '2', dst], check=True)
        print('safe', phase, name)
