from __future__ import annotations

import math
import subprocess
import wave
from pathlib import Path

import imageio_ffmpeg
import numpy as np
from pydub import AudioSegment


BASE = Path("exports/dubbing_l7t3HmMdA38")
VOICE = BASE / "vo_our_story_id_emotional_natural.mp3"
BG_WAV = BASE / "bg_ethnic_bali_jawa_original.wav"
BG_MP3 = BASE / "bg_ethnic_bali_jawa_original.mp3"
MIX_MP3 = BASE / "vo_our_story_id_emotional_with_ethnic_bg.mp3"
VOICE_WAV = BASE / "vo_our_story_id_emotional_natural.tmp.wav"

SR = 44_100


def envelope(length: int, attack: float, decay: float) -> np.ndarray:
    env = np.ones(length, dtype=np.float32)
    attack_n = max(1, int(attack * SR))
    decay_n = max(1, int(decay * SR))
    if attack_n < length:
        env[:attack_n] = np.linspace(0, 1, attack_n, dtype=np.float32)
    if decay_n < length:
        env[-decay_n:] *= np.linspace(1, 0, decay_n, dtype=np.float32)
    return env


def add_tone(
    audio: np.ndarray,
    start: float,
    duration: float,
    freq: float,
    amp: float,
    decay: float = 0.8,
    pan: float = 0.0,
    harmonics: tuple[tuple[float, float], ...] = (),
) -> None:
    start_i = int(start * SR)
    n = min(int(duration * SR), len(audio) - start_i)
    if n <= 0:
        return

    t = np.arange(n, dtype=np.float32) / SR
    wave_data = np.sin(2 * math.pi * freq * t)
    for mult, gain in harmonics:
        wave_data += gain * np.sin(2 * math.pi * freq * mult * t)
    wave_data *= np.exp(-decay * t)
    wave_data *= envelope(n, 0.006, min(duration * 0.45, 0.35))
    wave_data *= amp

    left = math.cos((pan + 1) * math.pi / 4)
    right = math.sin((pan + 1) * math.pi / 4)
    audio[start_i : start_i + n, 0] += wave_data * left
    audio[start_i : start_i + n, 1] += wave_data * right


def add_noise_hit(audio: np.ndarray, start: float, amp: float, pan: float = 0.0) -> None:
    start_i = int(start * SR)
    n = min(int(0.11 * SR), len(audio) - start_i)
    if n <= 0:
        return
    rng = np.random.default_rng(int(start * 1000) + 17)
    t = np.arange(n, dtype=np.float32) / SR
    hit = rng.normal(0, 1, n).astype(np.float32)
    hit = np.convolve(hit, np.ones(28, dtype=np.float32) / 28, mode="same")
    hit *= np.exp(-28 * t) * amp
    left = math.cos((pan + 1) * math.pi / 4)
    right = math.sin((pan + 1) * math.pi / 4)
    audio[start_i : start_i + n, 0] += hit * left
    audio[start_i : start_i + n, 1] += hit * right


def add_flute_pad(audio: np.ndarray, duration: float) -> None:
    # Soft suling-like layer, deliberately low in the mix.
    notes = [293.66, 329.63, 392.00, 440.00, 523.25]
    phrase = [0, 2, 1, 3, 2, 4, 3, 1]
    pos = 0.0
    idx = 0
    while pos < duration:
        note = notes[phrase[idx % len(phrase)]]
        seg = 3.8
        start_i = int(pos * SR)
        n = min(int(seg * SR), len(audio) - start_i)
        if n <= 0:
            break
        t = np.arange(n, dtype=np.float32) / SR
        vibrato = 4.5 * np.sin(2 * math.pi * 5.0 * t)
        phase = 2 * math.pi * (note * t + np.cumsum(vibrato) / SR)
        tone = np.sin(phase) + 0.22 * np.sin(2 * phase)
        tone *= envelope(n, 0.45, 0.8) * 0.015
        audio[start_i : start_i + n, 0] += tone * 0.65
        audio[start_i : start_i + n, 1] += tone * 0.78
        pos += 3.2
        idx += 1


def write_wav(path: Path, audio: np.ndarray) -> None:
    peak = float(np.max(np.abs(audio)))
    if peak > 0:
        audio = audio / max(peak, 1.0)
    pcm = np.clip(audio, -1, 1)
    pcm16 = (pcm * 32767).astype(np.int16)
    with wave.open(str(path), "wb") as wav:
        wav.setnchannels(2)
        wav.setsampwidth(2)
        wav.setframerate(SR)
        wav.writeframes(pcm16.tobytes())


def build_background(duration: float) -> np.ndarray:
    audio = np.zeros((int(duration * SR), 2), dtype=np.float32)

    scale = [293.66, 329.63, 392.00, 440.00, 493.88, 587.33]
    pattern = [0, 2, 3, 2, 4, 3, 1, 2]

    beat = 0.64
    t = 0.0
    i = 0
    while t < duration:
        freq = scale[pattern[i % len(pattern)]]
        pan = -0.35 if i % 2 == 0 else 0.35
        add_tone(
            audio,
            t,
            1.15,
            freq,
            0.075,
            decay=2.2,
            pan=pan,
            harmonics=((2.0, 0.28), (3.01, 0.11)),
        )
        if i % 4 == 2:
            add_tone(audio, t + 0.18, 0.9, freq * 1.5, 0.035, decay=2.8, pan=-pan)
        if i % 2 == 0:
            add_noise_hit(audio, t + 0.02, 0.014, pan=pan)
        t += beat
        i += 1

    gong_times = np.arange(0.6, duration, 8.96)
    for gt in gong_times:
        add_tone(
            audio,
            float(gt),
            5.5,
            73.42,
            0.15,
            decay=0.55,
            pan=0.0,
            harmonics=((2.01, 0.55), (3.0, 0.23), (4.02, 0.12)),
        )

    add_flute_pad(audio, duration)
    return audio


def match_dbfs(segment: AudioSegment, target_dbfs: float) -> AudioSegment:
    if segment.dBFS == float("-inf"):
        return segment
    return segment.apply_gain(target_dbfs - segment.dBFS)


def main() -> None:
    ffmpeg = imageio_ffmpeg.get_ffmpeg_exe()
    AudioSegment.converter = ffmpeg
    BASE.mkdir(parents=True, exist_ok=True)

    subprocess.run(
        [
            ffmpeg,
            "-y",
            "-i",
            str(VOICE),
            "-ar",
            str(SR),
            "-ac",
            "2",
            str(VOICE_WAV),
        ],
        check=True,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )

    voice = AudioSegment.from_wav(VOICE_WAV)
    duration_s = len(voice) / 1000

    bg = build_background(duration_s + 0.5)
    write_wav(BG_WAV, bg)

    bg_segment = AudioSegment.from_wav(BG_WAV)
    bg_segment = match_dbfs(bg_segment, -29.0)
    bg_segment = bg_segment.fade_in(4500).fade_out(6500)
    bg_segment = bg_segment[: len(voice)]

    mixed = voice.overlay(bg_segment)

    bg_segment.export(BG_MP3, format="mp3", bitrate="192k")
    mixed.export(MIX_MP3, format="mp3", bitrate="192k")

    print(f"voice_ms={len(voice)}")
    print(f"background={BG_MP3.resolve()}")
    print(f"mixed={MIX_MP3.resolve()}")

    try:
        VOICE_WAV.unlink()
    except FileNotFoundError:
        pass


if __name__ == "__main__":
    main()
