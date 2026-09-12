<?php

return [
    /*
     * Cosine-similarity threshold for a scan to count as a match (0..1).
     * ponytail: calibration knob — tune against the real model + gate hardware;
     * embeddings drift with device, lighting, and camera. Start ~0.6 and adjust
     * from real false-accept / false-reject rates. Override via FACE_MATCH_THRESHOLD.
     */
    'match_threshold' => (float) env('FACE_MATCH_THRESHOLD', 0.6),
];
