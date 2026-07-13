<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'published'];

    public const LOCALES = ['bn', 'en'];

    protected $fillable = [
        'title',
        'slug',
        'locale',
        'status',
        'excerpt',
        'meta_title',
        'meta_description',
        'focus_keyword',
        'og_image',
        'robots',
        'author_name',
        'faqs_json',
        'body_html',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'faqs_json' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function publicUrl(): string
    {
        return url('/blog/'.$this->slug);
    }

    public function seoTitle(): string
    {
        $title = trim((string) ($this->meta_title ?: $this->title));

        return $title !== '' ? $title : $this->title;
    }

    public function seoDescription(): string
    {
        $description = trim((string) ($this->meta_description ?: $this->excerpt ?: ''));

        if ($description !== '') {
            return Str::limit($description, 160, '');
        }

        return Str::limit(strip_tags($this->body_html), 160, '');
    }

    public static function makeSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);

        // Prefer Latin from focus keywords; never invent random for empty Bangla alone here —
        // callers that need a draft placeholder still get post-xxxxx.
        if ($base === '') {
            $base = 'post-'.Str::lower(Str::random(6));
        }

        $slug = $base;
        $i = 2;

        while (
            static::query()
                ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public static function isPlaceholderSlug(string $slug): bool
    {
        return (bool) preg_match('/^post-[a-z0-9]{6}$/i', $slug);
    }
}
