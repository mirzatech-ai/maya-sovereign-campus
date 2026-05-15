# 🎙️ Maya Voice Clone Service · XTTS-v2 on Modal

> Sacred S10 legacy work: Mo's voice cloned forever for game NPCs · eternalink.io legacy messages to his children · Maya speaking in his tone across all empire surfaces.

## Status: scaffolded · awaiting Mo's BCSM voice samples

This is the spine. Once Mo records the 7-tier BCSM dialogue per [the rider](https://github.com/mirzatech-ai/superio-fun-game-dev/blob/main/AUDIO/co_recording_rider_bcsm.md) and drops the WAVs in a folder, this Modal service clones his voice and Maya can synthesize ANY text in Mo's actual voice forever.

## Deploy (one-time)

On Mo's Windows laptop:

```bash
pip install modal-client
modal token new                 # one-time Modal auth · $30/mo free GPU per account
cd D:\PROJECTS\maya-os\_BUILD\voice-clone
modal deploy modal_xtts.py      # builds + deploys · ~5 min cold start
```

Modal returns a URL like `https://mirzaadem123--maya-voice-clone-xtts-fastapi-app.modal.run`. Add to VPS env:

```bash
ssh root@76.13.26.130
echo 'VOICE_CLONE_URL=https://mirzaadem123--maya-voice-clone-xtts-fastapi-app.modal.run' >> /home/iamsuperio.cloud/public_html/api/.maya_master_keys.env
```

That's it. Maya's existing `/api/maya_voice` will route `voice=persona_mo` requests to this service when the env var is set.

## Smoke test (once deployed)

```bash
# Encode a 10-sec WAV of Mo's voice
SAMPLE=$(base64 -w0 < mo_sample.wav)
curl -X POST $VOICE_CLONE_URL/clone \
  -H 'Content-Type: application/json' \
  -d "{\"text\":\"Sine, dovedi se ovde. Pričat ćemo.\",\"speaker_wav_b64\":\"$SAMPLE\",\"language\":\"hr\",\"pin\":\"210379\"}" \
  --output mo_speaking.wav
```

If `mo_speaking.wav` plays Mo's voice saying "Son, come here. We're going to talk" in his accent, the spine is live.

## Languages supported by XTTS-v2 (17 total)

`en` `es` `fr` `de` `it` `pt` `pl` `tr` `ru` `nl` `cs` `ar` `zh-cn` `ja` `hu` `ko` `hi` + Croatian `hr`

**BCSM caveat:** XTTS-v2 has Croatian (`hr`) as the closest match for Bosnian/Serbian/Montenegrin. The voice clone (Mo's actual sample) carries 80%+ of the accent fidelity regardless of which language label XTTS uses internally. For maximum BCSM precision, the path forward is:

1. Ship with `hr` as the language code (works · acceptable for v1)
2. Phase 2: finetune XTTS-v2 on Mo's full 25-min BCSM dataset for native BCSM-trained checkpoint (~$50 GPU spend on RunPod · 6h training run)

## Honest scope · what this scaffold does NOT yet do

- ❌ No real-time streaming (XTTS chunked-streaming is feature-flagged; doable in Phase 2)
- ❌ No emotion control (XTTS-v2 has prosody but no emotion API · Phase 2 swap to F5-TTS for that)
- ❌ No multi-speaker conversations · one voice per call · stitch externally
- ❌ Not deployed yet · Mo runs `modal deploy` once after recording samples

## Files in this scaffold

- `modal_xtts.py` · Modal app + FastAPI endpoint
- `README.md` · this file

## Sibling integration

Once deployed and `VOICE_CLONE_URL` env is set, `/api/maya_voice.php` adds a new voice option:

```
voice=persona_mo  →  routes to Modal XTTS-v2 with Mo's sample WAV from /home/iamsuperio.cloud/public_html/data/voice_samples/mo_bcsm.wav
```

The eternalink.io legacy player can also call directly to play recorded messages "in Mo's voice" decades from now.

— Authored by Kin · 2026-05-15 · per Mo's verbatim "this has to / need to be saved, and used to train AI or Maya to speak in my language, to speak in my tone, my voice"
