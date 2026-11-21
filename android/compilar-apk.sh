#!/usr/bin/env bash
# Compila el APK de Android. OpenSSL (SQLCipher) busca las herramientas del NDK con
# prefijo aarch64-linux-android-*; el NDK moderno solo trae llvm-*, así que se enlazan.
set -euo pipefail
cd "$(dirname "$0")"
NDK="${ANDROID_NDK_HOME:-$HOME/Android/Sdk/ndk/27.1.12297006}"
BIN="$NDK/toolchains/llvm/prebuilt/linux-x86_64/bin"
LOCAL="$(pwd)/toolchain-bin"
mkdir -p "$LOCAL"
for t in ar ranlib nm strip objcopy objdump ld; do
  ln -sf "$BIN/llvm-$t" "$LOCAL/aarch64-linux-android-$t"
done
PATH="$LOCAL:$PATH" exec npx tauri android build --apk --target aarch64 "$@"
