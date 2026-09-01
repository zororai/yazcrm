# ASR Language Evaluation

Per spec §23/§32 Phase 6 — no language is activated (`ASR_MODEL_*` set) without
being run against real audio first. This is a running log of what's been
tested, not a one-time approval.

## Shona — ACTIVATED

- **Model:** `badrex/w2v-bert-2.0-shona-asr` (605M params, `Wav2Vec2BertForCTC`)
- **Test sample:** real 31s clip from `badrex/shona-speech` (the model's own
  training dataset — same-distribution, not held-out or adversarial), with a
  human-written ground-truth transcript.
- **Result:** near-perfect match (only punctuation/casing differences, which
  CTC output doesn't produce). Confidence 0.95.
- **Caveat:** single clean sample from the training distribution, not a real
  helpline call (telephone codec, background noise, Zimbabwean accent
  variation). Treat as "the model works as advertised," not "production WER
  is known."

## English — ACTIVATED

- **Model:** `facebook/wav2vec2-base-960h` (95M params, plain CTC, no LM
  decoder dependency).
- Rejected first: `jonatasgrosman/wav2vec2-large-xlsr-53-english` — uses
  `Wav2Vec2ProcessorWithLM`, which needs `pyctcdecode`/`kenlm` not in this
  service's dependency set. Would need those added to activate that model
  instead, if its LM-boosted accuracy is wanted later.
- **Test sample:** `hf-internal-testing/librispeech_asr_dummy`, ground truth
  *"MISTER QUILTER IS THE APOSTLE OF THE MIDDLE CLASSES AND WE ARE GLAD TO
  WELCOME HIS GOSPEL"*.
- **Result:** exact word-for-word match. Confidence 0.97. 2.8s processing
  time (much faster than Shona — a much smaller model).
- **Caveat:** LibriSpeech is clean read-aloud audiobook speech, not
  telephone-quality or Zimbabwean-accented English. This confirms the
  pipeline and model work correctly, not that WER on real helpline calls
  will match this sample's near-zero error rate. Recommend spot-checking
  against a handful of real recorded English helpline calls before treating
  this as production-verified.

## Ndebele — NOT ACTIVATED

Investigated `fastinom/Ndebele_ASR` (964M params, `Wav2Vec2ForCTC`, CTC —
would have been architecturally compatible with this service unmodified).

**Rejected. Do not activate.** Reasons:

1. **No documentation.** README is the unedited HF auto-template — every
   field says "More Information Needed." No stated training data, dataset
   size, language variant, or evaluation metrics.
2. **The vocabulary file is keyed `"zu_za"`** (`vocab.json`) — i.e. Zulu
   (South Africa), not Ndebele. This is strong evidence the model was
   trained on, or its tokenizer was copied from, a Zulu dataset and
   mislabeled — or at minimum cannot be trusted to target Zimbabwean
   Northern Ndebele (`nd`), which is a distinct language from South African
   Ndebele (`nr`) and from Zulu, despite all three being closely related
   Nguni languages.
3. Searching Hugging Face for isiNdebele-tagged ASR resources surfaced
   `zionia/isindebele-asr`, itself tagged `language:zu, language:xh` — the
   same South-Africa/Nguni ambiguity, not confirmed Zimbabwean Ndebele.
4. Only 4 downloads, 0 likes, no community discussion — no external
   validation to lean on either.
5. **Confirmed by an actual test.** Ran it against a real 33s Ndebele
   WhatsApp voice note (2026-09-01) supplied by the project owner:
   `POST /transcribe` with `language=ndebele`, `model=fastinom/Ndebele_ASR`.
   Output: `"inlkla   ha ula unla ul n kl  a    nn n e  il nh nla a  a h..."`
   (confidence 0.78) — repetitive phoneme-like fragments (`kl`, `hl`, `nl`,
   `a`, `n`), no recognizable words in any language. Contrast with the Shona
   test, which produced a grammatically correct, word-for-word transcript on
   its first real test. This is not "rough but usable" — it's non-functional.

This is exactly the failure mode spec §33 warns against ("assume Shona ASR
works for Ndebele") — except here the risk is trusting an unverified model
rather than reusing the wrong one, and the real-audio test confirms the
vocab-file red flag was correct. `ASR_MODEL_NDEBELE` stays blank; the
service correctly returns a config error rather than silently using this or
any other model for Ndebele requests.

**To actually activate Ndebele:** `DigitalUmuganda/Afrivoice_V2` (Hugging
Face, gated dataset) is properly tagged `language:nd` and documents real
Ndebele collection alongside Kirundi/Ndau/Oshiwambo — a legitimate resource
to fine-tune a real Ndebele model on, or to request access to for evaluating
any future candidate model. No ready-to-use model trained on it exists yet
as of this writing. Any future candidate must be evaluated the same way this
one was — against real audio, not assumed from a HF page description.

## Known gap in this evaluation

Spec §23 recommends 20+ real-world samples per language (multiple speakers,
accents, telephone quality, background noise, code-switching) with a
computed Word Error Rate. What's been done here is a single real,
ground-truthed sample per activated language — enough to confirm the
pipeline and model produce correct output at all, not a statistically
meaningful WER. Before relying on this in production, run a proper batch
evaluation against real recorded helpline calls in each language.
