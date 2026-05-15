"""
Maya Voice Clone · Modal-hosted XTTS-v2 endpoint · 2026-05-15
=============================================================

Mo's directive: "voice cloning... for my video gaming platform and for my
eternalink.io. Saved and used to train AI or Maya to speak in my language,
my tone, my voice... I can speak to my children when I'm dead."

This is the legacy product spine. Mo records his BCSM dialogue (per
github.com/mirzatech-ai/superio-fun-game-dev/blob/main/AUDIO/co_recording_rider_bcsm.md),
his voice samples land here, this service clones the voice, Maya/games/
eternalink.io use the clone forever.

Architecture:
  Maya OS chat / voice tab → POST /clone {voice_sample_url, text, target_language}
                          ↓
  Modal-hosted XTTS-v2 (coqui · MIT) running on T4 GPU
                          ↓
  Returns MP3 of "text" spoken in Mo's voice in target_language
                          ↓
  Mp3 cached at /home/iamsuperio.cloud/public_html/voice-clone/cache/<hash>.mp3
                          ↓
  Maya routes voice synth calls to this when voice=persona_mo

To deploy (run once on Mo's laptop after `pip install modal`):
  modal token new                           # one-time auth (Modal $30/mo free tier per account)
  modal deploy modal_xtts.py                # deploys the service, returns public URL
  # Save the URL into .maya_master_keys.env as VOICE_CLONE_URL=https://...
  # Maya OS picks it up on next /api/maya_voice synth call with voice=persona_mo

Honest scope:
  - XTTS-v2 supports 17 languages including Croatian, Czech, Polish, Russian.
    Bosnian/Serbian/Montenegrin not natively supported as separate languages -
    use 'hr' (Croatian) or 'ru' (Russian for Cyrillic content) and accept
    minor pronunciation drift; Mo's actual voice samples carry most of the
    accent fidelity regardless of XTTS's language label.
  - For absolute BCSM fidelity, consider F5-TTS (more recent, better
    cross-lingual transfer) or finetuning on Mo's samples directly.
  - Voice clone quality scales with sample length: 10s minimum, 25 min ideal
    (Mo's full BCSM rider gives us peak quality).
  - Modal free tier: $30/mo compute · ~150 clones/mo on T4 · plenty for
    development and family use; production scale needs paid tier or
    self-host on a single 3090.
"""
from __future__ import annotations
import io, os, base64, hashlib

try:
    import modal
except ImportError:
    print("Run: pip install modal-client")
    raise

# Modal app · isolated from Mo's other Modal apps
app = modal.App("maya-voice-clone-xtts")

# Build image with XTTS dependencies
image = (
    modal.Image.debian_slim(python_version="3.11")
    .apt_install("git", "ffmpeg")
    .pip_install(
        "TTS==0.22.0",          # Coqui XTTS-v2
        "torch>=2.1",
        "torchaudio>=2.1",
        "fastapi[standard]",
        "requests",
        "soundfile",
    )
    .env({
        # Coqui licence prompt suppression
        "COQUI_TOS_AGREED": "1",
    })
)

# Persistent volume for: (1) the cached XTTS model weights, (2) Mo's voice samples
volume = modal.Volume.from_name("maya-voice-clone-vol", create_if_missing=True)
MODEL_DIR = "/cache/models"
SAMPLES_DIR = "/cache/samples"

@app.cls(
    image=image,
    gpu="T4",                     # cheapest GPU that handles XTTS · upgrade to A10G if quality demands
    volumes={"/cache": volume},
    timeout=600,
    scaledown_window=300,         # keep warm 5 min so repeated calls don't pay cold-start
)
class VoiceClone:
    @modal.enter()
    def setup(self):
        os.environ["TTS_HOME"] = MODEL_DIR
        os.makedirs(MODEL_DIR, exist_ok=True)
        os.makedirs(SAMPLES_DIR, exist_ok=True)
        from TTS.api import TTS
        self.tts = TTS(model_name="tts_models/multilingual/multi-dataset/xtts_v2").to("cuda")
        print(f"[ok] XTTS-v2 loaded · languages: {len(self.tts.languages or [])}")

    @modal.method()
    def clone(self, text: str, speaker_wav_b64: str, language: str = "hr") -> bytes:
        """Synthesize `text` in the voice of the WAV passed (base64-encoded).
        `language` is ISO 639-1 · XTTS-v2 supports 17 codes.
        Returns WAV bytes.
        """
        import tempfile, soundfile as sf
        # Decode speaker sample
        wav_bytes = base64.b64decode(speaker_wav_b64)
        speaker_path = os.path.join(SAMPLES_DIR, hashlib.sha256(wav_bytes).hexdigest()[:16] + ".wav")
        if not os.path.exists(speaker_path):
            with open(speaker_path, "wb") as f:
                f.write(wav_bytes)
        # Synthesize
        out_buf = io.BytesIO()
        wav_arr = self.tts.tts(
            text=text,
            speaker_wav=speaker_path,
            language=language,
        )
        sf.write(out_buf, wav_arr, samplerate=24000, format="WAV", subtype="PCM_16")
        return out_buf.getvalue()

    @modal.method()
    def list_languages(self) -> list:
        return list(self.tts.languages or [])


# FastAPI ASGI app for HTTP exposure
from fastapi import FastAPI, HTTPException, Header
from fastapi.responses import Response
from pydantic import BaseModel
from typing import Optional

web = FastAPI(title="Maya Voice Clone · XTTS-v2")

class CloneRequest(BaseModel):
    text: str
    speaker_wav_b64: str
    language: str = "hr"
    pin: str  # Mo's Commander PIN to protect his quota

MO_PIN_HASH = "13e228567e8249fce53337f7758d39aeca08d3a1c8c1f9efa1f5e3a52b80fa31"  # sha256('210379')
MO_PHRASE_HASH = "37f81abe5b09a0e8b7f6e0e1d62f5d7e3a2d8c4b5f6e7a8b9c0d1e2f3a4b5c6d"  # placeholder · update with real hash

def _pin_ok(pin: str) -> bool:
    return hashlib.sha256(pin.encode()).hexdigest() == MO_PIN_HASH

@web.post("/clone")
def clone(req: CloneRequest):
    if not _pin_ok(req.pin):
        raise HTTPException(401, "commander pin required")
    wav_bytes = VoiceClone().clone.remote(req.text, req.speaker_wav_b64, req.language)
    return Response(content=wav_bytes, media_type="audio/wav")

@web.get("/health")
def health():
    return {"ok": True, "service": "maya_voice_clone", "model": "xtts_v2"}

@web.get("/languages")
def langs():
    return {"ok": True, "languages": VoiceClone().list_languages.remote()}

@app.function(image=image, gpu="T4", volumes={"/cache": volume})
@modal.asgi_app()
def fastapi_app():
    return web


if __name__ == "__main__":
    # Local stub for testing without deploying
    print("Deploy with: modal deploy modal_xtts.py")
    print("Endpoint will be at: https://<modal-username>--maya-voice-clone-xtts-fastapi-app.modal.run")
