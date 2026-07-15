<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'text',
        'image_url',
        'has_filter',
        'authenticity_score',
    ];

    protected $hidden = ['embedding'];

    protected $casts = [
        'has_filter' => 'boolean',
        'authenticity_score' => 'float',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function interactions()
    {
        return $this->hasMany(Interaction::class);
    }

    /**
     * pgvector doesn't have a native Eloquent cast, so embeddings are
     * written/read via raw SQL in EmbeddingClient / FeedRankingService.
     * This helper formats a PHP float array into pgvector's literal syntax.
     */
    public static function vectorLiteral(array $embedding): string
    {
        return '[' . implode(',', $embedding) . ']';
    }
}
