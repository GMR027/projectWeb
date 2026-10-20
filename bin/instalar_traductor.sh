#!/usr/bin/env bash
# Instala el motor de traducción local es<->en (Python + CTranslate2 + SentencePiece y los modelos Opus-MT de Helsinki-NLP, CC-BY 4.0).
# Deja todo en storage/traductor (≈ 400 MB). Se ejecuta una vez; es seguro repetirlo. Uso: bin/instalar_traductor.sh
set -euo pipefail
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
DIR="${TRADUCTOR_DIR:-$RAIZ/storage/traductor}"
mkdir -p "$DIR"

if [ ! -x "$DIR/venv/bin/python" ]; then
  if python3 -m venv "$DIR/venv" 2>/dev/null; then :; else
    rm -rf "$DIR/venv"; python3 -m venv --without-pip "$DIR/venv"
    curl -fsSL https://bootstrap.pypa.io/get-pip.py -o "$DIR/get-pip.py"; "$DIR/venv/bin/python" "$DIR/get-pip.py" -q; rm -f "$DIR/get-pip.py"
  fi
fi
"$DIR/venv/bin/pip" install -q --upgrade ctranslate2 sentencepiece

bajar() {   # repo carpeta
  local repo="$1" d="$DIR/$2/model"
  mkdir -p "$d"
  for f in model.bin config.json source.spm target.spm shared_vocabulary.txt shared_vocabulary.json; do
    [ -s "$d/$f" ] && continue
    curl -fsSL "https://huggingface.co/$repo/resolve/main/$f" -o "$d/$f.tmp" 2>/dev/null && mv "$d/$f.tmp" "$d/$f" || rm -f "$d/$f.tmp"
  done
  [ -s "$d/model.bin" ] && [ -s "$d/source.spm" ] && [ -s "$d/target.spm" ] || { echo "No se pudo descargar $repo" >&2; exit 1; }
}
bajar gaudi/opus-mt-es-en-ctranslate2 es_en
bajar michaelfeil/ct2fast-opus-mt-en-es en_es
echo '{"de":"es","a":"en","q":["Prueba del traductor."]}' | "$DIR/venv/bin/python" "$RAIZ/bin/traductor.py" 2>/dev/null | grep -q '"t"' && echo "Traductor listo."
