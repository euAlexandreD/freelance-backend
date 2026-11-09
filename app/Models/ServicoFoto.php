<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['servico_id', 'path', 'ordem'])]
#[Hidden(['path'])]
class ServicoFoto extends Model
{
    protected $table = 'servico_fotos';

    protected $appends = ['url'];

    protected static function booted(): void
    {
        static::deleted(fn (ServicoFoto $foto) => Storage::disk('public')->delete($foto->path));
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::disk('public')->url($this->path),
        );
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }
}
