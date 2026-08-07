<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'url', 'section_selectors'];

    protected function casts(): array
    {
        return ['section_selectors' => 'array'];
    }

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }

    public function latestAudit(): ?Audit
    {
        return $this->audits()->latest('id')->first();
    }
}
