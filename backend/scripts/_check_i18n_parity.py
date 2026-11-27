#!/usr/bin/env python3
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
t = (ROOT / "backend/resources/js/i18n/fuel.ts").read_text(encoding="utf-8")
m = re.search(
    r"const dict = \{\s*en:\s*\{(.*?)\n\s*\},\s*ur:\s*\{(.*?)\n\s*\},\s*\} as const",
    t,
    re.S,
)
assert m
pat = re.compile(r"^\s{8}([a-zA-Z][a-zA-Z0-9]*):", re.M)
en, ur = set(pat.findall(m.group(1))), set(pat.findall(m.group(2)))
print(f"PWA EN={len(en)} UR={len(ur)} parity={'OK' if en == ur else 'FAIL'}")
if en != ur:
    print(" miss UR", sorted(en - ur))
    print(" miss EN", sorted(ur - en))

t2 = (ROOT / "mobile/lib/core/i18n/strings.dart").read_text(encoding="utf-8")
en_b = t2.split("_en = S._('en', {")[1].split("});")[0]
ur_b = t2.split("_ur = S._('ur', {")[1].split("});")[0]
kpat = re.compile(r"'([a-zA-Z][a-zA-Z0-9]*)':")
en2, ur2 = set(kpat.findall(en_b)), set(kpat.findall(ur_b))
print(f"Flutter EN={len(en2)} UR={len(ur2)} parity={'OK' if en2 == ur2 else 'FAIL'}")
if en2 != ur2:
    print(" miss UR", sorted(en2 - ur2))
    print(" miss EN", sorted(ur2 - en2))
