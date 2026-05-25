<?php

namespace PDMFC\ImageEditor\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhotosUploadedFromMobile implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $userId,
        public int $saved,
        public array $photos,
        public array $newFilenames = [],
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('image-editor.photos.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PhotosUploadedFromMobile';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'saved' => $this->saved,
            'photos' => $this->photos,
            'new_filenames' => $this->newFilenames,
        ];
    }
}
