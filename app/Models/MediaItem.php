<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MediaItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'disk',
        'path',
        'filename',
        'original_name',
        'mime_type',
        'extension',
        'size',
        'width',
        'height',
        'alt',
        'title',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function url(): string
    {
        if ($this->disk === 'public') {
            return asset('storage/'.$this->path);
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'disk' => $this->disk,
            'path' => $this->path,
            'filename' => $this->filename,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'human_size' => $this->humanSize(),
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->alt,
            'title' => $this->title,
            'url' => $this->url(),
            'created_by' => $this->created_by,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 2).' MB';
    }
}
