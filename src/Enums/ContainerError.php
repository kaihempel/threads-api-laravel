<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Enums;

/**
 * error_message values of a container with status ERROR.
 */
enum ContainerError: string
{
    case FailedDownloadingVideo = 'FAILED_DOWNLOADING_VIDEO';
    case FailedProcessingAudio = 'FAILED_PROCESSING_AUDIO';
    case FailedProcessingVideo = 'FAILED_PROCESSING_VIDEO';
    case InvalidAspectRatio = 'INVALID_ASPEC_RATIO'; // sic! spelled like this by Meta
    case InvalidBitRate = 'INVALID_BIT_RATE';
    case InvalidDuration = 'INVALID_DURATION';
    case InvalidFrameRate = 'INVALID_FRAME_RATE';
    case InvalidAudioChannels = 'INVALID_AUDIO_CHANNELS';
    case InvalidAudioChannelLayout = 'INVALID_AUDIO_CHANNEL_LAYOUT';
    case Unknown = 'UNKNOWN';

    public static function fromApi(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = strtoupper(trim($value));

        if ($value === 'INVALID_ASPECT_RATIO') {
            return self::InvalidAspectRatio;
        }

        return self::tryFrom($value) ?? self::Unknown;
    }
}
