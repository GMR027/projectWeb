#!/usr/bin/env python3
"""Motor de traducción local es<->en (CTranslate2 + SentencePiece, modelos de Argos Open Tech).
Entrada (stdin): {"de": "es", "a": "en", "q": ["texto", ...]}   Salida (stdout): {"t": ["traducción", ...]}
Los modelos viven en storage/traductor/<de>_<a>/ (model/ con model.bin, source.spm y target.spm: Opus-MT convertido a CTranslate2)."""
import json, os, re, sys

RAIZ = os.environ.get("TRADUCTOR_DIR") or os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "storage", "traductor")


def cargar(de, a):
    import ctranslate2, sentencepiece
    m = os.path.join(RAIZ, f"{de}_{a}", "model")
    for n in ("model.bin", "source.spm", "target.spm"):
        if not os.path.exists(os.path.join(m, n)):
            raise SystemExit(f"Falta {n} del modelo {de}->{a} en {m}")
    tr = ctranslate2.Translator(m, device="cpu", inter_threads=1, intra_threads=max(1, os.cpu_count() or 2))
    return tr, sentencepiece.SentencePieceProcessor(model_file=os.path.join(m, "source.spm")), sentencepiece.SentencePieceProcessor(model_file=os.path.join(m, "target.spm"))


def frases(texto):
    """Una frase por traducción (el modelo omite frases cuando recibe varias); las muy largas se parten en comas."""
    salida = []
    for p in re.split(r"(?<=[.!?;:])\s+", texto.strip()):
        while len(p) > 350:
            corte = p.rfind(", ", 0, 350)
            corte = corte + 2 if corte > 80 else p.rfind(" ", 0, 350) + 1 or 350
            salida.append(p[:corte].strip()); p = p[corte:]
        if p.strip():
            salida.append(p.strip())
    return salida or [texto.strip()]


def main():
    d = json.load(sys.stdin)
    tr, sp, sp_out = cargar(d["de"], d["a"])
    items, mapa = [], []   # frases sueltas y a qué texto pertenecen
    for i, q in enumerate(d["q"]):
        if not q.strip():
            continue
        for f in frases(q):
            items.append(f); mapa.append(i)
    # De más corta a más larga: el lote rinde más
    orden = sorted(range(len(items)), key=lambda k: len(items[k]))
    tokens = [sp.encode(items[k], out_type=str) + ["</s>"] for k in orden]
    res = tr.translate_batch(tokens, beam_size=4, max_batch_size=32, batch_type="examples", max_decoding_length=512, repetition_penalty=1.1)
    trad = [""] * len(items)
    for k, r in zip(orden, res):
        trad[k] = sp_out.decode(r.hypotheses[0])
    salida = [""] * len(d["q"])
    for k, i in enumerate(mapa):
        salida[i] = (salida[i] + " " + trad[k]).strip()
    for i, q in enumerate(d["q"]):   # conserva sangría y espacios finales
        if q.strip():
            salida[i] = q[: len(q) - len(q.lstrip())] + salida[i] + q[len(q.rstrip()):]
        else:
            salida[i] = q
    json.dump({"t": salida}, sys.stdout, ensure_ascii=False)


if __name__ == "__main__":
    main()
